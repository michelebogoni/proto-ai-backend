<?php
/**
 * Action Result Value Object
 *
 * Represents the result of an action execution.
 *
 * @package CreatorCore
 * @since 2.0.0
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

/**
 * Class ActionResult
 *
 * Immutable value object representing the outcome of an action execution.
 * Provides factory methods for creating success and failure results.
 */
class ActionResult {

	/**
	 * Whether the action was successful
	 *
	 * @var bool
	 */
	private bool $success;

	/**
	 * Result data (for successful executions)
	 *
	 * @var array|null
	 */
	private ?array $data;

	/**
	 * Error message (for failed executions)
	 *
	 * @var string|null
	 */
	private ?string $error;

	/**
	 * Execution output (captured from echo/print)
	 *
	 * @var string
	 */
	private string $output;

	/**
	 * Timestamp of execution
	 *
	 * @var string
	 */
	private string $timestamp;

	/**
	 * Private constructor - use factory methods
	 *
	 * @param bool        $success Whether successful.
	 * @param array|null  $data    Result data.
	 * @param string|null $error   Error message.
	 * @param string      $output  Captured output.
	 */
	private function __construct( bool $success, ?array $data = null, ?string $error = null, string $output = '' ) {
		$this->success   = $success;
		$this->data      = $data;
		$this->error     = $error;
		$this->output    = $output;
		$this->timestamp = current_time( 'c' );
	}

	/**
	 * Create a successful result
	 *
	 * @param array  $data   Result data.
	 * @param string $output Captured output.
	 * @return self
	 */
	public static function success( array $data = [], string $output = '' ): self {
		return new self( true, $data, null, $output );
	}

	/**
	 * Create a failed result
	 *
	 * @param string $error  Error message.
	 * @param string $output Captured output before failure.
	 * @return self
	 */
	public static function fail( string $error, string $output = '' ): self {
		return new self( false, null, $error, $output );
	}

	/**
	 * Check if the result is successful
	 *
	 * @return bool
	 */
	public function isSuccess(): bool {
		return $this->success;
	}

	/**
	 * Check if the result is a failure
	 *
	 * @return bool
	 */
	public function isFailure(): bool {
		return ! $this->success;
	}

	/**
	 * Get result data
	 *
	 * @return array|null
	 */
	public function getData(): ?array {
		return $this->data;
	}

	/**
	 * Get error message
	 *
	 * @return string|null
	 */
	public function getError(): ?string {
		return $this->error;
	}

	/**
	 * Get captured output
	 *
	 * @return string
	 */
	public function getOutput(): string {
		return $this->output;
	}

	/**
	 * Get timestamp
	 *
	 * @return string
	 */
	public function getTimestamp(): string {
		return $this->timestamp;
	}

	/**
	 * Convert to array (for API responses)
	 *
	 * @return array
	 */
	public function toArray(): array {
		$result = [
			'success'   => $this->success,
			'timestamp' => $this->timestamp,
		];

		if ( $this->success ) {
			$result['data']   = $this->data;
			$result['output'] = $this->output;
		} else {
			$result['error']  = $this->error;
			$result['output'] = $this->output;
		}

		return $result;
	}

	/**
	 * Convert to JSON string
	 *
	 * @return string
	 */
	public function toJson(): string {
		return wp_json_encode( $this->toArray() );
	}
}
