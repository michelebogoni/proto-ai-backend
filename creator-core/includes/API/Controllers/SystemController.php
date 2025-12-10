<?php
/**
 * System Controller
 *
 * Handles system-related REST API endpoints:
 * - Statistics
 * - Health check
 * - Thinking logs
 * - Debug information
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\OperationTracker;
use CreatorCore\Context\ThinkingLogger;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\API\RateLimiter;
use CreatorCore\Audit\AuditLogger;

/**
 * Class SystemController
 *
 * REST API controller for system operations.
 */
class SystemController extends BaseController {

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Statistics
		register_rest_route( self::NAMESPACE, '/stats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);

		// Health check (public but rate-limited)
		register_rest_route( self::NAMESPACE, '/health', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'health_check' ],
			'permission_callback' => [ $this, 'check_health_rate_limit' ],
		]);

		// Thinking logs
		register_rest_route( self::NAMESPACE, '/thinking/(?P<chat_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_thinking_log' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'after_index' => [
					'type'    => 'integer',
					'default' => 0,
				],
			],
		]);

		// Thinking stream (SSE)
		register_rest_route( self::NAMESPACE, '/thinking/stream/(?P<chat_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stream_thinking' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'last_index' => [
					'type'    => 'integer',
					'default' => 0,
				],
			],
		]);

		// Debug log
		register_rest_route( self::NAMESPACE, '/debug/log', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_debug_log' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Get usage statistics
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$tracker = new OperationTracker();

		$period = $request->get_param( 'period' ) ?? 'day';
		$stats  = $tracker->get_stats( $period );

		return $this->success( $stats );
	}

	/**
	 * Health check endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function health_check( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$health = [
			'status'    => 'healthy',
			'timestamp' => current_time( 'c' ),
			'version'   => CREATOR_CORE_VERSION ?? '1.0.0',
			'checks'    => [
				'database'   => $wpdb->check_connection() ? 'ok' : 'error',
				'filesystem' => is_writable( WP_CONTENT_DIR ) ? 'ok' : 'warning',
				'memory'     => $this->check_memory(),
			],
		];

		// Set overall status based on checks
		if ( in_array( 'error', $health['checks'], true ) ) {
			$health['status'] = 'unhealthy';
		} elseif ( in_array( 'warning', $health['checks'], true ) ) {
			$health['status'] = 'degraded';
		}

		return $this->success( $health );
	}

	/**
	 * Check memory usage
	 *
	 * @return string 'ok', 'warning', or 'error'
	 */
	private function check_memory(): string {
		$limit = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT );
		$used  = memory_get_usage( true );
		$ratio = $used / $limit;

		if ( $ratio > 0.9 ) {
			return 'error';
		} elseif ( $ratio > 0.7 ) {
			return 'warning';
		}

		return 'ok';
	}

	/**
	 * Get thinking log for a chat
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_thinking_log( \WP_REST_Request $request ): \WP_REST_Response {
		$chat_id     = (int) $request->get_param( 'chat_id' );
		$after_index = (int) $request->get_param( 'after_index' );

		$logger = new ThinkingLogger( $chat_id );
		$logs   = $logger->get_logs( $after_index );

		return $this->success( [
			'logs'       => $logs,
			'chat_id'    => $chat_id,
			'last_index' => count( $logs ) > 0 ? end( $logs )['index'] ?? 0 : $after_index,
		] );
	}

	/**
	 * Stream thinking logs via SSE
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function stream_thinking( \WP_REST_Request $request ): void {
		$chat_id    = (int) $request->get_param( 'chat_id' );
		$last_index = (int) $request->get_param( 'last_index' );

		// Set SSE headers
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		// Disable output buffering
		if ( ob_get_level() ) {
			ob_end_flush();
		}

		$logger      = new ThinkingLogger( $chat_id );
		$max_time    = 30; // 30 second timeout
		$start_time  = time();
		$poll_count  = 0;
		$max_polls   = 300; // 5 minutes max with 1-second polling

		while ( ( time() - $start_time ) < $max_time && $poll_count < $max_polls ) {
			$logs = $logger->get_logs( $last_index );

			if ( ! empty( $logs ) ) {
				foreach ( $logs as $log ) {
					echo "data: " . wp_json_encode( $log ) . "\n\n";
					$last_index = $log['index'] ?? $last_index;
				}
				flush();
			}

			// Check if processing is complete
			$status = $logger->get_status();
			if ( isset( $status['complete'] ) && $status['complete'] ) {
				echo "event: complete\n";
				echo "data: " . wp_json_encode( [ 'status' => 'complete' ] ) . "\n\n";
				flush();
				break;
			}

			usleep( 100000 ); // 100ms poll interval
			$poll_count++;
		}

		exit;
	}

	/**
	 * Get debug log (last 100 lines)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_debug_log( \WP_REST_Request $request ) {
		$log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			return $this->success( [
				'exists' => false,
				'lines'  => [],
			] );
		}

		$lines = [];
		$file  = new \SplFileObject( $log_file );
		$file->seek( PHP_INT_MAX );
		$total_lines = $file->key();

		// Get last 100 lines
		$start = max( 0, $total_lines - 100 );
		$file->seek( $start );

		while ( ! $file->eof() ) {
			$line = $file->fgets();
			if ( trim( $line ) !== '' ) {
				$lines[] = $line;
			}
		}

		return $this->success( [
			'exists'      => true,
			'total_lines' => $total_lines,
			'lines'       => $lines,
		] );
	}

	/**
	 * Rate limit check for health endpoint
	 *
	 * Allows public access but with IP-based rate limiting to prevent abuse.
	 * Limits: 60 requests per minute per IP.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public function check_health_rate_limit( \WP_REST_Request $request ) {
		// Get client IP
		$ip = $this->get_client_ip();

		// Transient key for this IP
		$transient_key = 'creator_health_rate_' . md5( $ip );

		// Get current request count
		$count = (int) get_transient( $transient_key );

		// Check rate limit (60 requests per minute)
		if ( $count >= 60 ) {
			// Log the rate limit hit
			$logger = new AuditLogger();
			$logger->warning( 'health_rate_limited', [
				'ip'    => $ip,
				'count' => $count,
			]);

			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'creator-core' ),
				[ 'status' => 429 ]
			);
		}

		// Increment counter (expires in 60 seconds)
		set_transient( $transient_key, $count + 1, 60 );

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_headers = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_X_FORWARDED_FOR',      // Proxy
			'HTTP_X_REAL_IP',            // Nginx
			'REMOTE_ADDR',               // Standard
		];

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				// Validate IP format
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
