<?php
/**
 * Rate Limiter
 *
 * Implements rate limiting for Creator REST API endpoints
 * to prevent abuse and ensure fair usage.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API;

defined( 'ABSPATH' ) || exit;

/**
 * Class RateLimiter
 *
 * Provides rate limiting functionality using WordPress transients.
 * Supports per-user and per-IP rate limiting with configurable windows.
 */
class RateLimiter {

	/**
	 * Default requests per minute for regular endpoints
	 *
	 * @var int
	 */
	const DEFAULT_RATE_LIMIT = 60;

	/**
	 * Rate limit for AI endpoints (more expensive operations)
	 *
	 * @var int
	 */
	const AI_RATE_LIMIT = 30;

	/**
	 * Rate limit for development endpoints (file operations, etc.)
	 *
	 * @var int
	 */
	const DEV_RATE_LIMIT = 100;

	/**
	 * Time window in seconds (1 minute)
	 *
	 * @var int
	 */
	const WINDOW_SECONDS = 60;

	/**
	 * Transient prefix for rate limit keys
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'creator_rl_';

	/**
	 * Check if the current request is within rate limits
	 *
	 * @param string $endpoint_type Type of endpoint: 'default', 'ai', or 'dev'.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public function check_rate_limit( string $endpoint_type = 'default' ) {
		$limit = $this->get_limit_for_type( $endpoint_type );
		$key   = $this->get_rate_limit_key();

		// Get current count from transient
		$current = $this->get_current_count( $key );

		// Check if limit exceeded
		if ( $current >= $limit ) {
			$retry_after = $this->get_retry_after( $key );

			return new \WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: 1: Rate limit, 2: Time window in seconds */
					__( 'Rate limit exceeded. Maximum %1$d requests per %2$d seconds.', 'creator-core' ),
					$limit,
					self::WINDOW_SECONDS
				),
				[
					'status'       => 429,
					'retry_after'  => $retry_after,
					'limit'        => $limit,
					'remaining'    => 0,
					'reset'        => time() + $retry_after,
				]
			);
		}

		// Increment counter
		$this->increment_count( $key );

		return true;
	}

	/**
	 * Get rate limit headers for response
	 *
	 * @param string $endpoint_type Type of endpoint.
	 * @return array Headers array.
	 */
	public function get_rate_limit_headers( string $endpoint_type = 'default' ): array {
		$limit     = $this->get_limit_for_type( $endpoint_type );
		$key       = $this->get_rate_limit_key();
		$current   = $this->get_current_count( $key );
		$remaining = max( 0, $limit - $current );
		$reset     = $this->get_window_reset_time( $key );

		return [
			'X-RateLimit-Limit'     => $limit,
			'X-RateLimit-Remaining' => $remaining,
			'X-RateLimit-Reset'     => $reset,
		];
	}

	/**
	 * Get the appropriate limit for an endpoint type
	 *
	 * @param string $endpoint_type Type of endpoint.
	 * @return int Rate limit.
	 */
	private function get_limit_for_type( string $endpoint_type ): int {
		// Allow override via filter
		$limits = apply_filters( 'creator_rate_limits', [
			'default' => self::DEFAULT_RATE_LIMIT,
			'ai'      => self::AI_RATE_LIMIT,
			'dev'     => self::DEV_RATE_LIMIT,
		]);

		return $limits[ $endpoint_type ] ?? self::DEFAULT_RATE_LIMIT;
	}

	/**
	 * Generate rate limit key based on user or IP
	 *
	 * @return string Unique key for rate limiting.
	 */
	private function get_rate_limit_key(): string {
		// Use user ID if logged in, otherwise use IP
		if ( is_user_logged_in() ) {
			$identifier = 'user_' . get_current_user_id();
		} else {
			$identifier = 'ip_' . $this->get_client_ip();
		}

		// Add minute bucket for sliding window
		$minute_bucket = floor( time() / self::WINDOW_SECONDS );

		return self::TRANSIENT_PREFIX . $identifier . '_' . $minute_bucket;
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address.
	 */
	private function get_client_ip(): string {
		$ip = '';

		// Check various headers for proxied requests
		$headers = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// X-Forwarded-For may contain multiple IPs, get the first one
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0];
				$ip = trim( $ip );
				break;
			}
		}

		// Hash the IP for privacy
		return md5( $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Get current request count for a key
	 *
	 * @param string $key Rate limit key.
	 * @return int Current count.
	 */
	private function get_current_count( string $key ): int {
		$count = get_transient( $key );
		return $count ? (int) $count : 0;
	}

	/**
	 * Increment request count for a key
	 *
	 * @param string $key Rate limit key.
	 * @return void
	 */
	private function increment_count( string $key ): void {
		$current = $this->get_current_count( $key );
		set_transient( $key, $current + 1, self::WINDOW_SECONDS );
	}

	/**
	 * Get seconds until rate limit resets
	 *
	 * @param string $key Rate limit key.
	 * @return int Seconds until reset.
	 */
	private function get_retry_after( string $key ): int {
		$current_bucket = floor( time() / self::WINDOW_SECONDS );
		$next_bucket    = ( $current_bucket + 1 ) * self::WINDOW_SECONDS;

		return max( 1, $next_bucket - time() );
	}

	/**
	 * Get timestamp when window resets
	 *
	 * @param string $key Rate limit key.
	 * @return int Unix timestamp.
	 */
	private function get_window_reset_time( string $key ): int {
		$current_bucket = floor( time() / self::WINDOW_SECONDS );
		return ( $current_bucket + 1 ) * self::WINDOW_SECONDS;
	}

	/**
	 * Clear rate limit for current user/IP (admin function)
	 *
	 * @return bool True on success.
	 */
	public function clear_rate_limit(): bool {
		$key = $this->get_rate_limit_key();
		return delete_transient( $key );
	}

	/**
	 * Check if user is exempt from rate limiting
	 *
	 * Administrators are exempt by default.
	 *
	 * @return bool True if exempt.
	 */
	public function is_exempt(): bool {
		// Admins are exempt
		if ( current_user_can( 'manage_options' ) ) {
			return apply_filters( 'creator_rate_limit_admin_exempt', true );
		}

		// Allow custom exemptions via filter
		return apply_filters( 'creator_rate_limit_exempt', false, get_current_user_id() );
	}
}
