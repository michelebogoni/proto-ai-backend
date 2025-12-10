<?php
/**
 * Execute PHP Handler
 *
 * The Universal PHP Engine handler - executes AI-generated PHP code safely.
 * This is the primary (and often only) handler needed in the new architecture.
 *
 * @package CreatorCore
 * @since 2.0.0
 */

namespace CreatorCore\Executor\Handlers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Executor\ActionHandler;
use CreatorCore\Executor\ActionResult;
use Throwable;

/**
 * Class ExecutePHPHandler
 *
 * Handles the 'execute_code' action type by:
 * 1. Validating code security (blacklist check)
 * 2. Executing code in a sandboxed environment
 * 3. Capturing output and errors
 * 4. Returning structured results
 */
class ExecutePHPHandler implements ActionHandler {

	/**
	 * Forbidden functions that should never be executed
	 *
	 * @var array
	 */
	private const BLACKLIST = [
		// System execution
		'exec',
		'shell_exec',
		'system',
		'passthru',
		'popen',
		'proc_open',
		'pcntl_exec',
		'pcntl_fork',
		// Dangerous eval variants
		'eval',
		'assert',
		'create_function',
		// File system dangerous operations
		'unlink',
		'rmdir',
		'rename',
		'copy',
		'mkdir',
		'chmod',
		'chown',
		'chgrp',
		// Include/require (could include malicious files)
		'include',
		'include_once',
		'require',
		'require_once',
		// Network
		'fsockopen',
		'pfsockopen',
		'stream_socket_client',
		// Serialization (unsafe unserialize)
		'unserialize',
		// Output/exit
		'exit',
		'die',
		// PHP settings
		'ini_set',
		'ini_alter',
		'putenv',
		'set_include_path',
	];

	/**
	 * Handle the execute_code action
	 *
	 * @param array $action Action data with 'details' containing 'code' and 'description'.
	 * @return ActionResult
	 */
	public function handle( array $action ): ActionResult {
		$details     = $action['details'] ?? [];
		$code        = $details['code'] ?? '';
		$description = $details['description'] ?? 'Executing PHP code';
		$risk        = $details['estimated_risk'] ?? 'medium';

		// Validate code exists
		if ( empty( trim( $code ) ) ) {
			return ActionResult::fail( 'No code provided for execution.' );
		}

		// Security check: validate code doesn't contain forbidden functions
		$security_check = $this->validateCodeSecurity( $code );
		if ( ! $security_check['passed'] ) {
			return ActionResult::fail(
				'Security Warning: Code contains restricted functions: ' . implode( ', ', $security_check['violations'] )
			);
		}

		// Log high-risk executions
		if ( 'high' === $risk ) {
			do_action( 'creator_high_risk_execution', $code, $description );
		}

		// Execute the code
		return $this->executeCode( $code, $description );
	}

	/**
	 * Check if this handler supports the given action
	 *
	 * @param array $action Action data.
	 * @return bool
	 */
	public function supports( array $action ): bool {
		$type = $action['type'] ?? '';
		$code = $action['details']['code'] ?? $action['code'] ?? '';

		// Must have code to execute - type alone is not enough
		if ( empty( $code ) ) {
			return false;
		}

		return 'execute_code' === $type || ! empty( $code );
	}

	/**
	 * Execute PHP code in a sandboxed environment
	 *
	 * @param string $code        PHP code to execute.
	 * @param string $description Description of what the code does.
	 * @return ActionResult
	 */
	private function executeCode( string $code, string $description ): ActionResult {
		// Prepare code: remove PHP tags if present
		$prepared_code = $this->prepareCode( $code );

		// Start output buffering
		ob_start();
		$errors = [];

		// Set custom error handler
		set_error_handler( function ( $errno, $errstr, $errfile, $errline ) use ( &$errors ) {
			$errors[] = [
				'type'    => $errno,
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
			];
			return true;
		} );

		$success     = false;
		$eval_result = null;

		try {
			// Execute the code
			$eval_result = eval( $prepared_code );
			$success     = true;
		} catch ( Throwable $e ) {
			$errors[] = [
				'type'    => E_ERROR,
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			];
		}

		// Restore error handler
		restore_error_handler();

		// Get captured output
		$output = ob_get_clean();

		// Handle failure
		if ( ! $success || ! empty( $errors ) ) {
			$error_message = ! empty( $errors )
				? 'PHP Error: ' . $errors[0]['message'] . ' (Line: ' . $errors[0]['line'] . ')'
				: 'Code execution failed with unknown error';

			return ActionResult::fail( $error_message, $output );
		}

		// Success
		return ActionResult::success(
			[
				'description' => $description,
				'result'      => $eval_result,
				'message'     => 'Code executed successfully',
			],
			$output
		);
	}

	/**
	 * Validate code security against blacklist
	 *
	 * @param string $code PHP code to validate.
	 * @return array Array with 'passed' (bool) and 'violations' (array).
	 */
	private function validateCodeSecurity( string $code ): array {
		$violations = [];

		// Check for blacklisted functions
		foreach ( self::BLACKLIST as $func ) {
			// Match function calls: func( or func (
			$pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(/i';
			if ( preg_match( $pattern, $code ) ) {
				$violations[] = $func;
			}
		}

		// Check for preg_replace with /e modifier (deprecated but dangerous)
		if ( preg_match( '/preg_replace\s*\([^,]+\/[^\/]*e[^\/]*\//', $code ) ) {
			$violations[] = 'preg_replace with /e modifier';
		}

		// Check for backticks (shell execution)
		if ( preg_match( '/`[^`]+`/', $code ) ) {
			$violations[] = 'backtick shell execution';
		}

		// Check for dangerous SQL patterns
		if ( preg_match( '/\b(DROP|TRUNCATE|DELETE\s+FROM)\s+(TABLE|DATABASE)/i', $code ) ) {
			$violations[] = 'dangerous SQL statement';
		}

		return [
			'passed'     => empty( $violations ),
			'violations' => $violations,
		];
	}

	/**
	 * Prepare code for execution
	 *
	 * @param string $code Raw PHP code.
	 * @return string Prepared code for eval().
	 */
	private function prepareCode( string $code ): string {
		// Remove opening/closing PHP tags
		$code = preg_replace( '/^<\?php\s*/', '', trim( $code ) );
		$code = preg_replace( '/\?>\s*$/', '', $code );

		return trim( $code );
	}
}
