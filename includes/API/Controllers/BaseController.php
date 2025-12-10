<?php
/**
 * Base Controller
 *
 * Abstract base class for all REST API controllers.
 * Provides common functionality and enforces consistent structure.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\API\RateLimiter;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\Audit\AuditLogger;

/**
 * Abstract Class BaseController
 *
 * Base class for all API controllers providing:
 * - Common permission checking
 * - Rate limiting
 * - Error response formatting
 * - Logging
 */
abstract class BaseController {

	/**
	 * API namespace
	 */
	const NAMESPACE = 'creator/v1';

	/**
	 * Capability checker instance (lazy-loaded)
	 *
	 * @var CapabilityChecker|null
	 */
	protected ?CapabilityChecker $capability_checker = null;

	/**
	 * Rate limiter instance (lazy-loaded)
	 *
	 * @var RateLimiter|null
	 */
	protected ?RateLimiter $rate_limiter = null;

	/**
	 * Audit logger instance (lazy-loaded)
	 *
	 * @var AuditLogger|null
	 */
	protected ?AuditLogger $logger = null;

	/**
	 * Register routes for this controller
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Get the rate limit type for this controller
	 *
	 * @return string 'default', 'ai', or 'dev'
	 */
	protected function get_rate_limit_type(): string {
		return 'default';
	}

	/**
	 * Get capability checker (lazy-loads if needed)
	 *
	 * @return CapabilityChecker
	 */
	protected function get_capability_checker(): CapabilityChecker {
		if ( null === $this->capability_checker ) {
			$this->capability_checker = new CapabilityChecker();
		}
		return $this->capability_checker;
	}

	/**
	 * Get rate limiter (lazy-loads if needed)
	 *
	 * @return RateLimiter
	 */
	protected function get_rate_limiter(): RateLimiter {
		if ( null === $this->rate_limiter ) {
			$this->rate_limiter = new RateLimiter();
		}
		return $this->rate_limiter;
	}

	/**
	 * Get audit logger (lazy-loads if needed)
	 *
	 * @return AuditLogger
	 */
	protected function get_logger(): AuditLogger {
		if ( null === $this->logger ) {
			$this->logger = new AuditLogger();
		}
		return $this->logger;
	}

	/**
	 * Check permission for API access
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to use Creator API.', 'creator-core' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! $this->get_capability_checker()->can_use_creator() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use Creator.', 'creator-core' ),
				[ 'status' => 403 ]
			);
		}

		// Check rate limit (unless exempt)
		$rate_limiter = $this->get_rate_limiter();
		if ( ! $rate_limiter->is_exempt() ) {
			$rate_check = $rate_limiter->check_rate_limit( $this->get_rate_limit_type() );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	/**
	 * Check admin permission
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_admin_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You must be logged in.', 'creator-core' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Administrator access required.', 'creator-core' ),
				[ 'status' => 403 ]
			);
		}

		// Check dev rate limit
		$rate_limiter = $this->get_rate_limiter();
		if ( ! $rate_limiter->is_exempt() ) {
			$rate_check = $rate_limiter->check_rate_limit( 'dev' );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	/**
	 * Create a success response
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function success( $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Create an error response
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @param array  $data    Additional data.
	 * @return \WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400, array $data = [] ): \WP_Error {
		return new \WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}

	/**
	 * Log an action
	 *
	 * @param string $action  Action name.
	 * @param array  $details Action details.
	 * @return void
	 */
	protected function log( string $action, array $details = [] ): void {
		$this->get_logger()->info( $action, $details );
	}
}
