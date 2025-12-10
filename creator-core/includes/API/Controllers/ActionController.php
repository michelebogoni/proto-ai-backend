<?php
/**
 * Action Controller
 *
 * Handles action-related REST API endpoints:
 * - Execute action (context request or code execution)
 * - Rollback action
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ChatInterface;
use CreatorCore\Backup\Rollback;
use CreatorCore\Context\ContextLoader;
use CreatorCore\Executor\ActionDispatcher;

/**
 * Class ActionController
 *
 * REST API controller for action operations.
 * Uses the Universal PHP Engine pattern via ActionDispatcher.
 */
class ActionController extends BaseController {

	/**
	 * Chat interface instance (kept for backward compatibility)
	 *
	 * @var ChatInterface
	 */
	private ChatInterface $chat_interface;

	/**
	 * Action dispatcher for Universal PHP Engine
	 *
	 * @var ActionDispatcher
	 */
	private ActionDispatcher $dispatcher;

	/**
	 * Constructor
	 *
	 * @param ChatInterface         $chat_interface Chat interface instance.
	 * @param ActionDispatcher|null $dispatcher     Optional action dispatcher.
	 */
	public function __construct( ChatInterface $chat_interface, ?ActionDispatcher $dispatcher = null ) {
		$this->chat_interface = $chat_interface;
		$this->dispatcher     = $dispatcher ?? new ActionDispatcher();
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Execute action
		register_rest_route( self::NAMESPACE, '/actions/execute', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'execute_action' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'action' => [
					'required'          => true,
					'type'              => 'object',
					'validate_callback' => [ $this, 'validate_action_object' ],
					'sanitize_callback' => [ $this, 'sanitize_action_object' ],
				],
				'chat_id' => [
					'required'          => false,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		]);

		// Rollback action
		register_rest_route( self::NAMESPACE, '/actions/(?P<action_id>\d+)/rollback', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'rollback_action' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);
	}

	/**
	 * Execute an action (context request or code execution)
	 *
	 * Supports the Universal PHP Engine pattern where AI generates executable code.
	 * Routes actions through ActionDispatcher for clean separation of concerns.
	 *
	 * Two action categories are supported:
	 * 1. Context requests (get_plugin_details, get_acf_details, etc.) - for discovery phase
	 * 2. Code execution (execute_code) - Universal PHP Engine via ActionDispatcher
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function execute_action( \WP_REST_Request $request ) {
		$action  = $request->get_param( 'action' );
		$chat_id = (int) $request->get_param( 'chat_id' );

		if ( empty( $action ) || ! is_array( $action ) ) {
			return $this->error(
				'invalid_action',
				__( 'Invalid action data', 'creator-core' ),
				400
			);
		}

		$type = $action['type'] ?? '';

		// Handle context request actions (lazy-load for discovery phase)
		// These bypass the dispatcher as they don't execute code
		$context_types = [
			'get_plugin_details',
			'get_acf_details',
			'get_cpt_details',
			'get_taxonomy_details',
			'get_wp_functions',
		];

		if ( in_array( $type, $context_types, true ) ) {
			return $this->handle_context_request( $action, $type );
		}

		// Universal PHP Engine: Route through ActionDispatcher
		return $this->handle_code_execution( $action, $chat_id );
	}

	/**
	 * Handle context request actions
	 *
	 * @param array  $action Action data.
	 * @param string $type   Action type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_context_request( array $action, string $type ) {
		try {
			$context_loader = new ContextLoader();
			$result         = $context_loader->handle_context_request( $action );

			return $this->success( [
				'success' => $result['success'] ?? false,
				'data'    => $result['data'] ?? null,
				'error'   => $result['error'] ?? null,
				'type'    => $type,
			] );
		} catch ( \Throwable $e ) {
			return $this->error(
				'context_request_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Handle code execution via ActionDispatcher
	 *
	 * @param array $action  Action data with code.
	 * @param int   $chat_id Chat ID for context.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_code_execution( array $action, int $chat_id ) {
		// Extract code to verify we have something to execute
		$code = $this->extract_code_from_action( $action );

		if ( empty( $code ) ) {
			return $this->error(
				'invalid_action',
				__( 'Action must contain executable code (type: execute_code with code in details)', 'creator-core' ),
				400
			);
		}

		try {
			// Dispatch through the Universal PHP Engine
			$result = $this->dispatcher->dispatch( $action );

			// Convert ActionResult to response format
			$response_data = $result->toArray();

			// Add chat context
			if ( $chat_id > 0 ) {
				$response_data['chat_id'] = $chat_id;
			}

			// Fire action for logging/tracking
			do_action( 'creator_code_executed', $action, $result, $chat_id );

			if ( $result->isSuccess() ) {
				return $this->success( $response_data );
			}

			return $this->error(
				'code_execution_failed',
				$result->getError() ?? __( 'Code execution failed', 'creator-core' ),
				500,
				$response_data
			);
		} catch ( \Throwable $e ) {
			return $this->error(
				'code_execution_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Rollback an action
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rollback_action( \WP_REST_Request $request ) {
		$action_id = (int) $request->get_param( 'action_id' );

		$rollback = new Rollback();
		$result   = $rollback->rollback_action( $action_id );

		if ( ! $result['success'] ) {
			return $this->error(
				'rollback_failed',
				$result['error'] ?? __( 'Rollback failed', 'creator-core' ),
				500
			);
		}

		return $this->success( $result );
	}

	/**
	 * Validate action object structure
	 *
	 * With the Universal PHP Engine pattern, we only validate:
	 * 1. Action is an object
	 * 2. Has a type (context request) OR has code (execute_code)
	 *
	 * @param mixed            $value   The action value.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $key     Parameter key.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_action_object( $value, $request, $key ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'invalid_action_type',
				__( 'Action must be an object', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		$type = $value['type'] ?? '';

		// Extract code from multiple possible locations (handles different AI response formats)
		$code = '';
		if ( isset( $value['code'] ) ) {
			if ( is_array( $value['code'] ) ) {
				// Nested format: {"code": {"content": "...", "type": "wpcode_snippet"}}
				$code = $value['code']['content'] ?? $value['code']['code'] ?? '';
			} else {
				// Direct format: {"code": "..."}
				$code = $value['code'];
			}
		}
		if ( empty( $code ) && isset( $value['details']['code'] ) ) {
			$code = $value['details']['code'];
		}

		// Context request types (for discovery phase)
		$context_types = [
			'get_plugin_details',
			'get_acf_details',
			'get_cpt_details',
			'get_taxonomy_details',
			'get_wp_functions',
		];

		// Valid if: it's a context request OR it's execute_code with code OR it has code directly
		$is_context_request = in_array( $type, $context_types, true );
		$is_code_execution  = ( $type === 'execute_code' && ! empty( $code ) );
		$has_code_directly  = ! empty( $code ) && is_string( $code );

		if ( ! $is_context_request && ! $is_code_execution && ! $has_code_directly ) {
			return new \WP_Error(
				'invalid_action',
				__( 'Action must be a context request (get_plugin_details, etc.) or contain executable code (type: execute_code with code)', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Sanitize action object
	 *
	 * Recursively sanitize all string values in the action object.
	 *
	 * @param mixed $value The action value.
	 * @return array Sanitized action object.
	 */
	public function sanitize_action_object( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return $this->sanitize_recursive( $value );
	}

	/**
	 * Recursively sanitize an array
	 *
	 * @param array $data Array to sanitize.
	 * @param int   $depth Current depth (max 10 to prevent infinite recursion).
	 * @return array Sanitized array.
	 */
	private function sanitize_recursive( array $data, int $depth = 0 ): array {
		// Prevent infinite recursion
		if ( $depth > 10 ) {
			return [];
		}

		$sanitized = [];

		foreach ( $data as $key => $value ) {
			// Sanitize key
			$clean_key = is_string( $key ) ? sanitize_key( $key ) : $key;

			// Sanitize value based on type
			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = $this->sanitize_recursive( $value, $depth + 1 );
			} elseif ( is_string( $value ) ) {
				// For code content, preserve the value but remove null bytes
				if ( in_array( $key, [ 'code', 'content', 'file_content' ], true ) ) {
					$sanitized[ $clean_key ] = str_replace( "\0", '', $value );
				} else {
					// For other strings, use standard sanitization
					$sanitized[ $clean_key ] = sanitize_text_field( $value );
				}
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $clean_key ] = (bool) $value;
			} elseif ( is_int( $value ) ) {
				$sanitized[ $clean_key ] = (int) $value;
			} elseif ( is_float( $value ) ) {
				$sanitized[ $clean_key ] = (float) $value;
			} elseif ( is_null( $value ) ) {
				$sanitized[ $clean_key ] = null;
			}
			// Skip other types (objects, resources, etc.)
		}

		return $sanitized;
	}

	/**
	 * Extract code from action object
	 *
	 * Supports multiple formats:
	 * - New format: $action['details']['code'] (Universal PHP Engine)
	 * - Legacy format: $action['code'] (string or array with 'content')
	 *
	 * @param array $action Action object.
	 * @return string PHP code or empty string.
	 */
	private function extract_code_from_action( array $action ): string {
		// New Universal PHP Engine format: details.code
		if ( ! empty( $action['details']['code'] ) ) {
			return $action['details']['code'];
		}

		// Legacy format: code as string
		if ( ! empty( $action['code'] ) && is_string( $action['code'] ) ) {
			return $action['code'];
		}

		// Legacy format: code as array with content or code key
		if ( is_array( $action['code'] ?? null ) ) {
			if ( ! empty( $action['code']['content'] ) ) {
				return $action['code']['content'];
			}
			if ( ! empty( $action['code']['code'] ) ) {
				return $action['code']['code'];
			}
		}

		// Legacy format: content directly
		if ( ! empty( $action['content'] ) ) {
			return $action['content'];
		}

		return '';
	}
}
