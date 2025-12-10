<?php
/**
 * Database Controller
 *
 * Handles database-related REST API endpoints:
 * - Database info
 * - Execute query
 * - Table structure
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Development\DatabaseManager;

/**
 * Class DatabaseController
 *
 * REST API controller for database operations.
 */
class DatabaseController extends BaseController {

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
		// Database info
		register_rest_route( self::NAMESPACE, '/database/info', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_database_info' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Execute query
		register_rest_route( self::NAMESPACE, '/database/query', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'database_query' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Table structure
		register_rest_route( self::NAMESPACE, '/database/table/(?P<table>[a-zA-Z0-9_]+)/structure', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_table_structure' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Get database info
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_database_info( \WP_REST_Request $request ): \WP_REST_Response {
		$database = new DatabaseManager( $this->get_logger() );
		return $this->success( $database->get_database_info() );
	}

	/**
	 * Execute database query
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function database_query( \WP_REST_Request $request ) {
		$query  = $request->get_param( 'query' );
		$limit  = $request->get_param( 'limit' ) ?? 100;
		$offset = $request->get_param( 'offset' ) ?? 0;

		if ( empty( $query ) ) {
			return $this->error(
				'missing_param',
				__( 'Query is required', 'creator-core' ),
				400
			);
		}

		$database = new DatabaseManager( $this->get_logger() );
		$result   = $database->select( $query, (int) $limit, (int) $offset );

		if ( ! $result['success'] ) {
			return $this->error( 'query_failed', $result['error'], 400 );
		}

		$this->log( 'database_query_executed', [ 'query_preview' => substr( $query, 0, 100 ) ] );

		return $this->success( $result );
	}

	/**
	 * Get table structure
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_table_structure( \WP_REST_Request $request ) {
		global $wpdb;
		$table    = $request->get_param( 'table' );
		$database = new DatabaseManager( $this->get_logger() );
		$result   = $database->get_table_structure( $wpdb->prefix . $table );

		if ( ! $result['success'] ) {
			return $this->error( 'table_not_found', $result['error'], 404 );
		}

		return $this->success( $result );
	}
}
