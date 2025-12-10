<?php
/**
 * Plugin Controller
 *
 * Handles plugin development REST API endpoints:
 * - Create plugin
 * - List plugins
 * - Plugin info
 * - Activate/Deactivate
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Development\PluginGenerator;

/**
 * Class PluginController
 *
 * REST API controller for plugin operations.
 */
class PluginController extends BaseController {

	/**
	 * Get rate limit type (development operations)
	 *
	 * @return string
	 */
	protected function get_rate_limit_type(): string {
		return 'dev';
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Create plugin
		register_rest_route( self::NAMESPACE, '/plugins/create', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create_plugin' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// List plugins
		register_rest_route( self::NAMESPACE, '/plugins/list', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_plugins' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Plugin info
		register_rest_route( self::NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/info', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_plugin_info' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Activate plugin
		register_rest_route( self::NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/activate', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'activate_plugin' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Deactivate plugin
		register_rest_route( self::NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/deactivate', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'deactivate_plugin' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Create a plugin
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_plugin( \WP_REST_Request $request ) {
		$config    = $request->get_json_params();
		$generator = new PluginGenerator( $this->get_logger() );
		$result    = $generator->create_plugin( $config );

		if ( ! $result['success'] ) {
			return $this->error( 'plugin_creation_failed', $result['error'], 400 );
		}

		// Auto-activate if requested
		if ( ! empty( $config['activate'] ) ) {
			$generator->activate_plugin( $result['plugin_slug'] );
			$result['activated'] = true;
		}

		$this->log( 'plugin_created', [ 'slug' => $result['plugin_slug'] ?? '' ] );

		return $this->success( $result );
	}

	/**
	 * List all plugins
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_plugins( \WP_REST_Request $request ): \WP_REST_Response {
		$generator = new PluginGenerator( $this->get_logger() );
		return $this->success( $generator->list_plugins() );
	}

	/**
	 * Get plugin info
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_plugin_info( \WP_REST_Request $request ) {
		$slug      = $request->get_param( 'slug' );
		$generator = new PluginGenerator( $this->get_logger() );
		$result    = $generator->get_plugin_info( $slug );

		if ( ! $result['success'] ) {
			return $this->error( 'plugin_not_found', $result['error'], 404 );
		}

		return $this->success( $result );
	}

	/**
	 * Activate a plugin
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function activate_plugin( \WP_REST_Request $request ) {
		$slug      = $request->get_param( 'slug' );
		$generator = new PluginGenerator( $this->get_logger() );
		$result    = $generator->activate_plugin( $slug );

		if ( ! $result['success'] ) {
			return $this->error( 'activation_failed', $result['error'], 400 );
		}

		$this->log( 'plugin_activated', [ 'slug' => $slug ] );

		return $this->success( $result );
	}

	/**
	 * Deactivate a plugin
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function deactivate_plugin( \WP_REST_Request $request ): \WP_REST_Response {
		$slug      = $request->get_param( 'slug' );
		$generator = new PluginGenerator( $this->get_logger() );

		$this->log( 'plugin_deactivated', [ 'slug' => $slug ] );

		return $this->success( $generator->deactivate_plugin( $slug ) );
	}
}
