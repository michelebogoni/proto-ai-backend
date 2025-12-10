<?php
/**
 * Elementor Controller
 *
 * Handles Elementor-related REST API endpoints:
 * - Create Elementor pages from AI specifications
 * - Get Elementor status and capabilities
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ElementorPageBuilder;
use CreatorCore\Integrations\ElementorIntegration;
use CreatorCore\Context\ThinkingLogger;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\API\RateLimiter;
use CreatorCore\Audit\AuditLogger;

/**
 * Class ElementorController
 *
 * REST API controller for Elementor operations.
 */
class ElementorController extends BaseController {

	/**
	 * Get rate limit type (AI operations)
	 *
	 * @return string
	 */
	protected function get_rate_limit_type(): string {
		return 'ai';
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Create Elementor page
		register_rest_route( self::NAMESPACE, '/elementor/pages', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create_page' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args'                => [
				'title'    => [
					'required' => true,
					'type'     => 'string',
				],
				'sections' => [
					'required' => true,
					'type'     => 'array',
				],
			],
		]);

		// Get Elementor status
		register_rest_route( self::NAMESPACE, '/elementor/status', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);
	}

	/**
	 * Create an Elementor page from specification
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_page( \WP_REST_Request $request ) {
		// Check if Elementor is available
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return $this->error(
				'elementor_not_active',
				__( 'Elementor is not installed or activated.', 'creator-core' ),
				400
			);
		}

		$chat_id = $request->get_param( 'chat_id' );
		$logger  = null;

		if ( $chat_id ) {
			$logger = new ThinkingLogger( (int) $chat_id );
		}

		try {
			$builder = new ElementorPageBuilder( $logger );

			$spec = [
				'title'          => $request->get_param( 'title' ),
				'slug'           => $request->get_param( 'slug' ) ?? '',
				'sections'       => $request->get_param( 'sections' ),
				'seo'            => $request->get_param( 'seo' ) ?? [],
				'featured_image' => $request->get_param( 'featured_image' ) ?? '',
			];

			$result = $builder->generate_page_from_freeform_spec( $spec );

			$this->log( 'elementor_page_created', [
				'page_id' => $result['page_id'] ?? null,
				'title'   => $spec['title'],
			] );

			return $this->success( $result, 201 );
		} catch ( \Exception $e ) {
			$this->log( 'elementor_page_failed', [
				'error' => $e->getMessage(),
			] );

			return $this->error(
				'page_creation_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get Elementor status and capabilities
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		$status = [
			'installed'  => class_exists( '\Elementor\Plugin' ),
			'active'     => class_exists( '\Elementor\Plugin' ),
			'pro'        => defined( 'ELEMENTOR_PRO_VERSION' ),
			'version'    => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
			'compatible' => true,
			'widgets'    => [],
		];

		if ( $status['active'] ) {
			$elementor_integration = new ElementorIntegration();
			$widgets               = $elementor_integration->get_widget_types();
			$status['widgets']     = array_keys( $widgets );
		}

		return $this->success( $status );
	}
}
