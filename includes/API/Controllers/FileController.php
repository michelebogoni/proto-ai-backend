<?php
/**
 * File Controller
 *
 * Handles file system REST API endpoints:
 * - Read files
 * - Write files
 * - List files
 * - Search files
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Development\FileSystemManager;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\API\RateLimiter;
use CreatorCore\Audit\AuditLogger;

/**
 * Class FileController
 *
 * REST API controller for file operations.
 */
class FileController extends BaseController {

	/**
	 * File system manager instance
	 *
	 * @var FileSystemManager|null
	 */
	private ?FileSystemManager $fs_manager = null;

	/**
	 * Get rate limit type (development operations)
	 *
	 * @return string
	 */
	protected function get_rate_limit_type(): string {
		return 'dev';
	}

	/**
	 * Get file system manager (lazy load)
	 *
	 * @return FileSystemManager
	 */
	private function get_fs_manager(): FileSystemManager {
		if ( $this->fs_manager === null ) {
			$this->fs_manager = new FileSystemManager();
		}
		return $this->fs_manager;
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Read file
		register_rest_route( self::NAMESPACE, '/files/read', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'read_file' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args'                => [
				'path' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]);

		// Write file
		register_rest_route( self::NAMESPACE, '/files/write', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'write_file' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args'                => [
				'path'    => [
					'required' => true,
					'type'     => 'string',
				],
				'content' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]);

		// List files
		register_rest_route( self::NAMESPACE, '/files/list', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_files' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Search files
		register_rest_route( self::NAMESPACE, '/files/search', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'search_files' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Read a file
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function read_file( \WP_REST_Request $request ) {
		$path = $request->get_param( 'path' );

		$result = $this->get_fs_manager()->read_file( $path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->log( 'file_read', [ 'path' => $path ] );

		return $this->success( $result );
	}

	/**
	 * Write a file
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function write_file( \WP_REST_Request $request ) {
		$path    = $request->get_param( 'path' );
		$content = $request->get_param( 'content' );

		$result = $this->get_fs_manager()->write_file( $path, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->log( 'file_written', [ 'path' => $path ] );

		return $this->success( $result );
	}

	/**
	 * List files in a directory
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_files( \WP_REST_Request $request ) {
		$path      = $request->get_param( 'path' ) ?? ABSPATH;
		$recursive = $request->get_param( 'recursive' ) ?? false;

		$result = $this->get_fs_manager()->list_files( $path, $recursive );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}

	/**
	 * Search files
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search_files( \WP_REST_Request $request ) {
		$pattern = $request->get_param( 'pattern' ) ?? '';
		$path    = $request->get_param( 'path' ) ?? ABSPATH;
		$content = $request->get_param( 'content' ) ?? '';

		$result = $this->get_fs_manager()->search_files( $path, $pattern, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}
}
