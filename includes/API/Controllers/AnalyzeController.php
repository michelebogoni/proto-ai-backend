<?php
/**
 * Analyze Controller
 *
 * Handles code analysis REST API endpoints:
 * - Analyze file
 * - Analyze plugin
 * - Analyze theme
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Development\CodeAnalyzer;

/**
 * Class AnalyzeController
 *
 * REST API controller for code analysis operations.
 */
class AnalyzeController extends BaseController {

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
		// Analyze file
		register_rest_route( self::NAMESPACE, '/analyze/file', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'analyze_file' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Analyze plugin
		register_rest_route( self::NAMESPACE, '/analyze/plugin/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'analyze_plugin' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Analyze theme
		register_rest_route( self::NAMESPACE, '/analyze/theme/(?P<slug>[a-zA-Z0-9_-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'analyze_theme' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Analyze a file
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function analyze_file( \WP_REST_Request $request ) {
		$file_path = $request->get_param( 'file_path' );

		if ( empty( $file_path ) ) {
			return $this->error(
				'missing_param',
				__( 'File path is required', 'creator-core' ),
				400
			);
		}

		$analyzer = new CodeAnalyzer( $this->get_logger() );
		return $this->success( $analyzer->analyze_file( $file_path ) );
	}

	/**
	 * Analyze a plugin
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function analyze_plugin( \WP_REST_Request $request ) {
		$slug     = $request->get_param( 'slug' );
		$analyzer = new CodeAnalyzer( $this->get_logger() );
		$result   = $analyzer->analyze_plugin( $slug );

		if ( ! $result['success'] ) {
			return $this->error( 'analysis_failed', $result['error'], 400 );
		}

		return $this->success( $result );
	}

	/**
	 * Analyze a theme
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function analyze_theme( \WP_REST_Request $request ) {
		$slug     = $request->get_param( 'slug' );
		$analyzer = new CodeAnalyzer( $this->get_logger() );
		$result   = $analyzer->analyze_theme( $slug );

		if ( ! $result['success'] ) {
			return $this->error( 'analysis_failed', $result['error'], 400 );
		}

		return $this->success( $result );
	}
}
