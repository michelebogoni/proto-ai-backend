<?php
/**
 * Error Handler
 *
 * @package CreatorCore
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class ErrorHandler
 *
 * Handles errors during action execution
 */
class ErrorHandler {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Error codes
     */
    const ERROR_PERMISSION    = 'permission_denied';
    const ERROR_VALIDATION    = 'validation_failed';
    const ERROR_EXECUTION     = 'execution_failed';
    const ERROR_NOT_FOUND     = 'not_found';
    const ERROR_DEPENDENCY    = 'dependency_missing';
    const ERROR_RATE_LIMIT    = 'rate_limited';
    const ERROR_UNKNOWN       = 'unknown_error';

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger Audit logger instance.
     */
    public function __construct( ?AuditLogger $logger = null ) {
        $this->logger = $logger ?? new AuditLogger();
    }

    /**
     * Handle an exception
     *
     * @param \Exception $exception The exception.
     * @param array      $context   Error context.
     * @return array Formatted error response.
     */
    public function handle( \Exception $exception, array $context = [] ): array {
        $error_code    = $this->determine_error_code( $exception );
        $error_message = $this->format_error_message( $exception, $error_code );

        // Log the error
        $this->logger->failure( 'executor_error', [
            'code'      => $error_code,
            'message'   => $exception->getMessage(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'context'   => $context,
            'trace'     => CREATOR_DEBUG ? $exception->getTraceAsString() : null,
        ]);

        return [
            'success'   => false,
            'code'      => $error_code,
            'message'   => $error_message,
            'debug'     => CREATOR_DEBUG ? [
                'exception' => get_class( $exception ),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
            ] : null,
        ];
    }

    /**
     * Handle a WP_Error
     *
     * @param \WP_Error $error   The WP_Error.
     * @param array     $context Error context.
     * @return array Formatted error response.
     */
    public function handle_wp_error( \WP_Error $error, array $context = [] ): array {
        $error_code    = $error->get_error_code();
        $error_message = $error->get_error_message();

        // Log the error
        $this->logger->failure( 'wp_error', [
            'code'    => $error_code,
            'message' => $error_message,
            'data'    => $error->get_error_data(),
            'context' => $context,
        ]);

        return [
            'success' => false,
            'code'    => $error_code,
            'message' => $error_message,
        ];
    }

    /**
     * Create an error response
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param array  $data    Additional data.
     * @return array
     */
    public function create_error( string $code, string $message, array $data = [] ): array {
        $this->logger->failure( 'error_created', [
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ]);

        return [
            'success' => false,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ];
    }

    /**
     * Determine error code from exception
     *
     * @param \Exception $exception The exception.
     * @return string
     */
    private function determine_error_code( \Exception $exception ): string {
        $message = strtolower( $exception->getMessage() );

        if ( strpos( $message, 'permission' ) !== false || strpos( $message, 'capability' ) !== false ) {
            return self::ERROR_PERMISSION;
        }

        if ( strpos( $message, 'not found' ) !== false || strpos( $message, 'does not exist' ) !== false ) {
            return self::ERROR_NOT_FOUND;
        }

        if ( strpos( $message, 'invalid' ) !== false || strpos( $message, 'required' ) !== false ) {
            return self::ERROR_VALIDATION;
        }

        if ( strpos( $message, 'rate limit' ) !== false || strpos( $message, 'too many' ) !== false ) {
            return self::ERROR_RATE_LIMIT;
        }

        if ( $exception instanceof \InvalidArgumentException ) {
            return self::ERROR_VALIDATION;
        }

        return self::ERROR_EXECUTION;
    }

    /**
     * Format error message for display
     *
     * @param \Exception $exception  The exception.
     * @param string     $error_code Error code.
     * @return string
     */
    private function format_error_message( \Exception $exception, string $error_code ): string {
        // Return original message in debug mode
        if ( CREATOR_DEBUG ) {
            return $exception->getMessage();
        }

        // Return user-friendly messages
        $messages = [
            self::ERROR_PERMISSION  => __( 'You do not have permission to perform this action.', 'creator-core' ),
            self::ERROR_VALIDATION  => __( 'The provided data is invalid. Please check your input.', 'creator-core' ),
            self::ERROR_NOT_FOUND   => __( 'The requested resource was not found.', 'creator-core' ),
            self::ERROR_DEPENDENCY  => __( 'A required plugin or feature is not available.', 'creator-core' ),
            self::ERROR_RATE_LIMIT  => __( 'Too many requests. Please try again later.', 'creator-core' ),
            self::ERROR_EXECUTION   => __( 'An error occurred while executing the action.', 'creator-core' ),
            self::ERROR_UNKNOWN     => __( 'An unexpected error occurred.', 'creator-core' ),
        ];

        return $messages[ $error_code ] ?? $messages[ self::ERROR_UNKNOWN ];
    }

    /**
     * Check if error is recoverable
     *
     * @param string $error_code Error code.
     * @return bool
     */
    public function is_recoverable( string $error_code ): bool {
        $recoverable = [
            self::ERROR_RATE_LIMIT,
            self::ERROR_VALIDATION,
        ];

        return in_array( $error_code, $recoverable, true );
    }

    /**
     * Get retry recommendation
     *
     * @param string $error_code Error code.
     * @return array
     */
    public function get_retry_recommendation( string $error_code ): array {
        switch ( $error_code ) {
            case self::ERROR_RATE_LIMIT:
                return [
                    'should_retry' => true,
                    'delay_seconds' => 60,
                    'message' => __( 'Please wait a minute before trying again.', 'creator-core' ),
                ];

            case self::ERROR_VALIDATION:
                return [
                    'should_retry' => true,
                    'delay_seconds' => 0,
                    'message' => __( 'Please correct the input and try again.', 'creator-core' ),
                ];

            default:
                return [
                    'should_retry' => false,
                    'delay_seconds' => 0,
                    'message' => __( 'This error cannot be automatically resolved.', 'creator-core' ),
                ];
        }
    }
}
