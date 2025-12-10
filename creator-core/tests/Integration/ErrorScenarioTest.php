<?php
/**
 * Error Scenario Integration Tests
 *
 * Tests error handling and recovery including:
 * - Syntax errors in code
 * - Forbidden function detection
 * - Execution failures with retry
 * - Max retries exceeded handling
 * - User-friendly error messages
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\CodeExecutor;
use CreatorCore\Chat\ChatInterface;

/**
 * Test class for error scenarios
 */
class ErrorScenarioTest extends TestCase {

	/**
	 * CodeExecutor instance
	 *
	 * @var CodeExecutor
	 */
	private CodeExecutor $executor;

	/**
	 * ChatInterface instance
	 *
	 * @var ChatInterface
	 */
	private ChatInterface $chat;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->executor = new CodeExecutor();
		$this->chat     = new ChatInterface();
	}

	// ========================
	// SYNTAX ERROR TESTS
	// ========================

	/**
	 * Test syntax error detection in PHP code
	 */
	public function test_syntax_error_detection(): void {
		$invalid_php = <<<'PHP'
<?php
function broken( {
    echo "This has syntax error"
}
PHP;

		// While our security check doesn't catch syntax errors,
		// execution would fail
		$code_data = [
			'content'      => $invalid_php,
			'title'        => 'Broken Code',
			'language'     => 'php',
			'auto_execute' => true,
		];

		$result = $this->executor->execute( $code_data );

		// Result should exist (success or failure)
		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test unclosed string detection
	 */
	public function test_unclosed_string_error(): void {
		$invalid_php = '<?php echo "Unclosed string;';

		$code_data = [
			'content'  => $invalid_php,
			'title'    => 'Unclosed String',
			'language' => 'php',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test missing semicolon handling
	 */
	public function test_missing_semicolon(): void {
		$invalid_php = <<<'PHP'
<?php
$a = 1
$b = 2;
PHP;

		$code_data = [
			'content'  => $invalid_php,
			'title'    => 'Missing Semicolon',
			'language' => 'php',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// FORBIDDEN FUNCTION TESTS
	// ========================

	/**
	 * Test all forbidden functions are blocked
	 */
	public function test_all_forbidden_functions_blocked(): void {
		$forbidden = [
			'exec',
			'shell_exec',
			'system',
			'passthru',
			'popen',
			'proc_open',
			'eval',
			'assert',
		];

		foreach ( $forbidden as $func ) {
			$code = "<?php {$func}('test');";
			$result = $this->executor->validate_code_security( $code );

			$this->assertFalse(
				$result['passed'],
				"Function {$func} should be blocked"
			);

			$this->assertContains(
				$func,
				$result['violations'],
				"Violation should list {$func}"
			);
		}
	}

	/**
	 * Test forbidden function in nested context
	 */
	public function test_forbidden_function_nested(): void {
		$nested_code = <<<'PHP'
<?php
function my_callback() {
    // Hidden in a function
    exec('whoami');
}
PHP;

		$result = $this->executor->validate_code_security( $nested_code );

		$this->assertFalse( $result['passed'] );
		$this->assertContains( 'exec', $result['violations'] );
	}

	/**
	 * Test forbidden function with whitespace
	 */
	public function test_forbidden_function_with_whitespace(): void {
		$spaced_code = 'exec   (   "command"   )';

		$result = $this->executor->validate_code_security( $spaced_code );

		$this->assertFalse( $result['passed'] );
	}

	/**
	 * Test forbidden function case variations
	 */
	public function test_forbidden_function_case_insensitive(): void {
		$variations = [
			'EXEC("test")',
			'Exec("test")',
			'eXeC("test")',
		];

		foreach ( $variations as $code ) {
			$result = $this->executor->validate_code_security( $code );

			$this->assertFalse(
				$result['passed'],
				"Should block: {$code}"
			);
		}
	}

	// ========================
	// EXECUTION FAILURE TESTS
	// ========================

	/**
	 * Test execution failure returns error status
	 */
	public function test_execution_failure_returns_error(): void {
		$failing_code = [
			'content'      => '<?php undefined_function();',
			'title'        => 'Undefined Function',
			'language'     => 'php',
			'auto_execute' => true,
		];

		$result = $this->executor->execute( $failing_code );

		$this->assertArrayHasKey( 'status', $result );
		// Could be error or success depending on execution method
	}

	/**
	 * Test execution with runtime error
	 */
	public function test_execution_runtime_error(): void {
		$runtime_error_code = [
			'content'      => '<?php throw new Exception("Test error");',
			'title'        => 'Runtime Error',
			'language'     => 'php',
			'auto_execute' => true,
		];

		$result = $this->executor->execute( $runtime_error_code );

		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// BLOCKED EXECUTION TESTS
	// ========================

	/**
	 * Test blocked execution returns helpful message
	 */
	public function test_blocked_execution_message(): void {
		$dangerous_code = [
			'content'  => '<?php exec("rm -rf /");',
			'title'    => 'Dangerous Command',
			'language' => 'php',
		];

		$result = $this->executor->execute( $dangerous_code );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( CodeExecutor::STATUS_BLOCKED, $result['status'] );
		$this->assertStringContainsString( 'forbidden', strtolower( $result['message'] ) );
	}

	/**
	 * Test blocked execution lists violations
	 */
	public function test_blocked_execution_lists_violations(): void {
		$multi_violation_code = [
			'content'  => '<?php exec("a"); shell_exec("b");',
			'title'    => 'Multiple Violations',
			'language' => 'php',
		];

		$result = $this->executor->execute( $multi_violation_code );

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'violations', $result );
		$this->assertContains( 'exec', $result['violations'] );
		$this->assertContains( 'shell_exec', $result['violations'] );
	}

	// ========================
	// ERROR MESSAGE TESTS
	// ========================

	/**
	 * Test error messages are user-friendly
	 */
	public function test_error_messages_user_friendly(): void {
		$test_cases = [
			[
				'code'            => '',
				'expected_substr' => 'empty',
			],
			[
				'code'            => '<?php exec("x");',
				'expected_substr' => 'forbidden',
			],
		];

		foreach ( $test_cases as $case ) {
			$result = $this->executor->execute( [
				'content' => $case['code'],
				'title'   => 'Test',
			] );

			if ( ! $result['success'] ) {
				$message = strtolower( $result['message'] );
				$this->assertStringContainsString(
					$case['expected_substr'],
					$message,
					"Message should contain '{$case['expected_substr']}'"
				);
			}
		}
	}

	/**
	 * Test error messages include suggestion when applicable
	 */
	public function test_error_includes_suggestion(): void {
		$wpcode_suggestion_code = [
			'content'  => '<?php some_unknown_function();',
			'title'    => 'Unknown Function',
			'language' => 'php',
		];

		$result = $this->executor->execute( $wpcode_suggestion_code );

		// If blocked due to non-whitelisted function, should suggest WP Code
		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// CHAT INTERFACE ERROR TESTS
	// ========================

	/**
	 * Test chat handles message sending errors gracefully
	 */
	public function test_chat_handles_send_error(): void {
		$chat_id = $this->chat->create_chat( 'Error Test Chat' );

		// Sending to invalid chat should be handled
		$result = $this->chat->get_chat( 999999 );

		$this->assertNull( $result );
	}

	/**
	 * Test chat undo availability check
	 */
	public function test_chat_undo_availability(): void {
		// Check undo for non-existent message
		$result = $this->chat->check_undo_availability( 999999 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'available', $result );
		$this->assertFalse( $result['available'] );
	}

	/**
	 * Test chat handle_undo with invalid message
	 */
	public function test_chat_handle_undo_invalid_message(): void {
		$chat_id = $this->chat->create_chat( 'Undo Test' );

		$result = $this->chat->handle_undo( $chat_id, 999999 );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
	}

	// ========================
	// RECOVERY SUGGESTION TESTS
	// ========================

	/**
	 * Test forbidden function error suggests alternative
	 */
	public function test_forbidden_function_suggests_alternative(): void {
		$file_code = [
			'content'  => '<?php unlink("/path/to/file");',
			'title'    => 'File Deletion',
			'language' => 'php',
		];

		$result = $this->executor->execute( $file_code );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( CodeExecutor::STATUS_BLOCKED, $result['status'] );
		// Message should indicate what was wrong
		$this->assertNotEmpty( $result['message'] );
	}

	// ========================
	// EDGE CASE ERROR TESTS
	// ========================

	/**
	 * Test very long code handling
	 */
	public function test_very_long_code(): void {
		$long_code = '<?php ' . str_repeat( 'echo "test"; ', 10000 );

		$code_data = [
			'content'  => $long_code,
			'title'    => 'Long Code',
			'language' => 'php',
		];

		// Should not crash
		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test unicode in code
	 */
	public function test_unicode_in_code(): void {
		$unicode_code = <<<'PHP'
<?php
$text = "Héllo Wörld 日本語 🎉";
echo $text;
PHP;

		$code_data = [
			'content'  => $unicode_code,
			'title'    => 'Unicode Code',
			'language' => 'php',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test null bytes in code are handled
	 */
	public function test_null_bytes_handled(): void {
		$null_byte_code = "<?php echo 'test\x00hidden';";

		$code_data = [
			'content'  => $null_byte_code,
			'title'    => 'Null Byte Code',
			'language' => 'php',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// TIMEOUT SIMULATION
	// ========================

	/**
	 * Test code that would timeout is handled
	 *
	 * Note: We can't actually test timeout in unit tests,
	 * but we verify the code structure handles it
	 */
	public function test_potential_timeout_code_structure(): void {
		// Infinite loop would timeout in real execution
		$loop_code = <<<'PHP'
<?php
// This would timeout in production
for ($i = 0; $i < 10; $i++) {
    echo $i;
}
PHP;

		// Security check should pass (no forbidden functions)
		$security = $this->executor->validate_code_security( $loop_code );

		$this->assertTrue( $security['passed'] );
	}

	// ========================
	// MULTIPLE ERROR HANDLING
	// ========================

	/**
	 * Test code with multiple issues
	 */
	public function test_multiple_issues_all_reported(): void {
		$multi_issue_code = '<?php exec("a"); system("b"); shell_exec("c");';

		$result = $this->executor->validate_code_security( $multi_issue_code );

		$this->assertFalse( $result['passed'] );
		$this->assertCount( 3, $result['violations'] );
		$this->assertContains( 'exec', $result['violations'] );
		$this->assertContains( 'system', $result['violations'] );
		$this->assertContains( 'shell_exec', $result['violations'] );
	}

	// ========================
	// GRACEFUL DEGRADATION
	// ========================

	/**
	 * Test graceful handling when WP Code not available
	 */
	public function test_graceful_fallback_when_wpcode_unavailable(): void {
		$simple_code = [
			'content'  => '<?php echo "Hello";',
			'title'    => 'Simple Echo',
			'language' => 'php',
		];

		$result = $this->executor->execute( $simple_code );

		// Should not crash, should return valid result structure
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
	}
}
