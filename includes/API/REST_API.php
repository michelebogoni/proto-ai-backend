<?php
/**
 * REST API Router
 *
 * Central router that delegates to specialized controllers.
 * Implements the Controller pattern for clean separation of concerns.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ChatInterface;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\Audit\AuditLogger;
use CreatorCore\API\Controllers\ChatController;
use CreatorCore\API\Controllers\FileController;
use CreatorCore\API\Controllers\ElementorController;
use CreatorCore\API\Controllers\SystemController;
use CreatorCore\API\Controllers\ContextController;
use CreatorCore\API\Controllers\PluginController;
use CreatorCore\API\Controllers\AnalyzeController;
use CreatorCore\API\Controllers\DatabaseController;
use CreatorCore\API\Controllers\ActionController;

/**
 * Class REST_API
 *
 * Central REST API router for Creator.
 * Coordinates multiple specialized controllers.
 */
class REST_API {

	/**
	 * API namespace
	 */
	const NAMESPACE = 'creator/v1';

	/**
	 * Chat interface instance
	 *
	 * @var ChatInterface
	 */
	private ChatInterface $chat_interface;

	/**
	 * Capability checker instance
	 *
	 * @var CapabilityChecker
	 */
	private CapabilityChecker $capability_checker;

	/**
	 * Audit logger instance
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $logger;

	/**
	 * Rate limiter instance
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Registered controllers
	 *
	 * @var array
	 */
	private array $controllers = [];

	/**
	 * Constructor
	 *
	 * @param ChatInterface     $chat_interface     Chat interface instance.
	 * @param CapabilityChecker $capability_checker Capability checker instance.
	 * @param AuditLogger       $logger             Audit logger instance.
	 */
	public function __construct(
		ChatInterface $chat_interface,
		CapabilityChecker $capability_checker,
		AuditLogger $logger
	) {
		$this->chat_interface     = $chat_interface;
		$this->capability_checker = $capability_checker;
		$this->logger             = $logger;
		$this->rate_limiter       = new RateLimiter();

		// Initialize controllers
		$this->init_controllers();

		// Add rate limit headers to all responses
		add_filter( 'rest_post_dispatch', [ $this, 'add_rate_limit_headers' ], 10, 3 );
	}

	/**
	 * Initialize all controllers
	 *
	 * Controllers use lazy-loading for dependencies (capability checker,
	 * rate limiter, audit logger). Only controllers requiring ChatInterface
	 * receive it via constructor.
	 *
	 * @return void
	 */
	private function init_controllers(): void {
		$this->controllers = [
			// Chat operations (requires ChatInterface)
			'chat'      => new ChatController( $this->chat_interface ),

			// Action execution (requires ChatInterface)
			'action'    => new ActionController( $this->chat_interface ),

			// Controllers with lazy-loaded dependencies
			'file'      => new FileController(),
			'elementor' => new ElementorController(),
			'system'    => new SystemController(),
			'context'   => new ContextController(),
			'plugin'    => new PluginController(),
			'analyze'   => new AnalyzeController(),
			'database'  => new DatabaseController(),
		];
	}

	/**
	 * Register all REST routes
	 *
	 * Delegates to specialized controllers for each endpoint group.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Register routes from all controllers
		foreach ( $this->controllers as $name => $controller ) {
			$controller->register_routes();
		}

		/**
		 * Fires after Creator REST routes are registered.
		 *
		 * @param array $controllers Registered controllers.
		 */
		do_action( 'creator_rest_routes_registered', $this->controllers );
	}

	/**
	 * Add rate limit headers to REST API responses
	 *
	 * @param \WP_REST_Response $response Response object.
	 * @param \WP_REST_Server   $server   Server object.
	 * @param \WP_REST_Request  $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function add_rate_limit_headers( \WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_REST_Response {
		// Only add headers for our namespace
		$route = $request->get_route();
		if ( strpos( $route, '/' . self::NAMESPACE ) !== 0 ) {
			return $response;
		}

		// Determine endpoint type based on route
		$endpoint_type = $this->get_endpoint_type( $route );

		// Add rate limit headers
		$headers = $this->rate_limiter->get_rate_limit_headers( $endpoint_type );
		foreach ( $headers as $header => $value ) {
			$response->header( $header, $value );
		}

		return $response;
	}

	/**
	 * Determine endpoint type from route
	 *
	 * @param string $route Route path.
	 * @return string Endpoint type (ai, dev, or default).
	 */
	private function get_endpoint_type( string $route ): string {
		// AI endpoints - stricter rate limiting
		$ai_patterns = [
			'/messages',
			'/elementor/pages',
		];

		foreach ( $ai_patterns as $pattern ) {
			if ( strpos( $route, $pattern ) !== false ) {
				return 'ai';
			}
		}

		// Development endpoints
		$dev_patterns = [
			'/files/',
			'/plugins/',
			'/analyze/',
			'/database/',
		];

		foreach ( $dev_patterns as $pattern ) {
			if ( strpos( $route, $pattern ) !== false ) {
				return 'dev';
			}
		}

		return 'default';
	}

	/**
	 * Get a specific controller
	 *
	 * @param string $name Controller name.
	 * @return object|null Controller instance or null.
	 */
	public function get_controller( string $name ): ?object {
		return $this->controllers[ $name ] ?? null;
	}

	/**
	 * Get all controllers
	 *
	 * @return array Controllers array.
	 */
	public function get_controllers(): array {
		return $this->controllers;
	}
}
