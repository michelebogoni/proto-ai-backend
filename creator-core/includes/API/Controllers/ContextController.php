<?php
/**
 * Context Controller
 *
 * Handles context-related REST API endpoints for lazy-loading:
 * - Plugin details
 * - ACF group details
 * - CPT details
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ContextLoader;

/**
 * Class ContextController
 *
 * REST API controller for context operations.
 */
class ContextController extends BaseController {

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Plugin context
		register_rest_route( self::NAMESPACE, '/context/plugins/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_plugin_context' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'slug' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]);

		// ACF group context
		register_rest_route( self::NAMESPACE, '/context/acf/(?P<group>[a-zA-Z0-9_-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_acf_context' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);

		// CPT context
		register_rest_route( self::NAMESPACE, '/context/cpt/(?P<post_type>[a-zA-Z0-9_-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_cpt_context' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);
	}

	/**
	 * Get plugin context details (lazy-load)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_plugin_context( \WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		try {
			$context_loader = new ContextLoader();
			$result         = $context_loader->get_plugin_details( $slug );

			if ( ! $result['success'] ) {
				return $this->error(
					'plugin_not_found',
					$result['error'] ?? __( 'Plugin not found', 'creator-core' ),
					404
				);
			}

			return $this->success( $result );
		} catch ( \Throwable $e ) {
			return $this->error(
				'context_request_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get ACF group context details (lazy-load)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_acf_context( \WP_REST_Request $request ) {
		$group = $request->get_param( 'group' );

		try {
			$context_loader = new ContextLoader();
			$result         = $context_loader->get_acf_group_details( $group );

			if ( ! $result['success'] ) {
				return $this->error(
					'acf_group_not_found',
					$result['error'] ?? __( 'ACF group not found', 'creator-core' ),
					404
				);
			}

			return $this->success( $result );
		} catch ( \Throwable $e ) {
			return $this->error(
				'context_request_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get CPT context details (lazy-load)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_cpt_context( \WP_REST_Request $request ) {
		$post_type = $request->get_param( 'post_type' );

		try {
			$context_loader = new ContextLoader();
			$result         = $context_loader->get_cpt_details( $post_type );

			if ( ! $result['success'] ) {
				return $this->error(
					'cpt_not_found',
					$result['error'] ?? __( 'Post type not found', 'creator-core' ),
					404
				);
			}

			return $this->success( $result );
		} catch ( \Throwable $e ) {
			return $this->error(
				'context_request_failed',
				$e->getMessage(),
				500
			);
		}
	}
}
