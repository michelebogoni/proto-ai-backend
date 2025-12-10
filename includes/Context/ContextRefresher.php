<?php
/**
 * Context Refresher
 *
 * Handles automatic refresh of Creator Context when system changes occur.
 * Hooks into WordPress events to detect changes and trigger context regeneration.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class ContextRefresher
 *
 * Automatically refreshes Creator Context when:
 * - Plugin is activated/deactivated/updated
 * - WordPress is updated
 * - User changes competence level
 * - Theme is changed
 * - CPT or taxonomy is registered
 */
class ContextRefresher {

	/**
	 * Creator context instance
	 *
	 * @var CreatorContext|null
	 */
	private ?CreatorContext $context = null;

	/**
	 * Audit logger instance
	 *
	 * @var AuditLogger|null
	 */
	private ?AuditLogger $logger = null;

	/**
	 * Constructor
	 *
	 * @param CreatorContext|null $context Creator context instance.
	 * @param AuditLogger|null    $logger  Audit logger instance.
	 */
	public function __construct( ?CreatorContext $context = null, ?AuditLogger $logger = null ) {
		try {
			$this->context = $context ?? new CreatorContext();
			$this->logger  = $logger ?? new AuditLogger();
		} catch ( \Throwable $e ) {
			error_log( 'Creator: Failed to initialize ContextRefresher: ' . $e->getMessage() );
		}
	}

	/**
	 * Get context instance (with lazy initialization)
	 *
	 * @return CreatorContext
	 */
	private function get_context(): CreatorContext {
		if ( $this->context === null ) {
			$this->context = new CreatorContext();
		}
		return $this->context;
	}

	/**
	 * Get logger instance (with lazy initialization)
	 *
	 * @return AuditLogger
	 */
	private function get_logger(): AuditLogger {
		if ( $this->logger === null ) {
			$this->logger = new AuditLogger();
		}
		return $this->logger;
	}

	/**
	 * Register all hooks for auto-refresh
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Plugin events
		add_action( 'activated_plugin', [ $this, 'on_plugin_change' ], 10, 2 );
		add_action( 'deactivated_plugin', [ $this, 'on_plugin_change' ], 10, 2 );
		add_action( 'upgrader_process_complete', [ $this, 'on_plugin_update' ], 10, 2 );
		add_action( 'deleted_plugin', [ $this, 'on_plugin_delete' ], 10, 2 );

		// WordPress core update
		add_action( '_core_updated_successfully', [ $this, 'on_wordpress_update' ] );

		// Theme events
		add_action( 'switch_theme', [ $this, 'on_theme_change' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'on_theme_update' ], 10, 2 );

		// User profile change
		add_action( 'update_option_creator_user_profile', [ $this, 'on_user_level_change' ], 10, 3 );

		// CPT and taxonomy registration (late priority to catch all)
		add_action( 'init', [ $this, 'check_cpt_changes' ], 9999 );

		// ACF field group changes
		add_action( 'acf/update_field_group', [ $this, 'on_acf_change' ] );
		add_action( 'acf/delete_field_group', [ $this, 'on_acf_change' ] );

		// Manual refresh via AJAX
		add_action( 'wp_ajax_creator_refresh_context', [ $this, 'ajax_refresh_context' ] );
	}

	/**
	 * Handle plugin activation/deactivation
	 *
	 * @param string $plugin       Plugin path.
	 * @param bool   $network_wide Whether network-wide.
	 * @return void
	 */
	public function on_plugin_change( string $plugin, bool $network_wide = false ): void {
		$this->schedule_refresh( 'plugin_change', [ 'plugin' => $plugin ] );
	}

	/**
	 * Handle plugin update
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Update options.
	 * @return void
	 */
	public function on_plugin_update( $upgrader, array $options ): void {
		if ( $options['type'] !== 'plugin' ) {
			return;
		}

		$this->schedule_refresh( 'plugin_update', [ 'action' => $options['action'] ?? 'update' ] );
	}

	/**
	 * Handle plugin deletion
	 *
	 * @param string $plugin  Plugin path.
	 * @param bool   $deleted Whether deleted.
	 * @return void
	 */
	public function on_plugin_delete( string $plugin, bool $deleted ): void {
		if ( $deleted ) {
			$this->schedule_refresh( 'plugin_delete', [ 'plugin' => $plugin ] );
		}
	}

	/**
	 * Handle WordPress core update
	 *
	 * @param string $wp_version New WordPress version.
	 * @return void
	 */
	public function on_wordpress_update( string $wp_version ): void {
		$this->schedule_refresh( 'wordpress_update', [ 'version' => $wp_version ] );
	}

	/**
	 * Handle theme change
	 *
	 * @param string    $new_name  New theme name.
	 * @param \WP_Theme $new_theme New theme object.
	 * @param \WP_Theme $old_theme Old theme object.
	 * @return void
	 */
	public function on_theme_change( string $new_name, \WP_Theme $new_theme, \WP_Theme $old_theme ): void {
		$this->schedule_refresh( 'theme_change', [
			'new_theme' => $new_name,
			'old_theme' => $old_theme->get( 'Name' ),
		] );
	}

	/**
	 * Handle theme update
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Update options.
	 * @return void
	 */
	public function on_theme_update( $upgrader, array $options ): void {
		if ( $options['type'] !== 'theme' ) {
			return;
		}

		$this->schedule_refresh( 'theme_update', [ 'action' => $options['action'] ?? 'update' ] );
	}

	/**
	 * Handle user level change
	 *
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function on_user_level_change( $old_value, $new_value, string $option ): void {
		if ( $old_value !== $new_value ) {
			$this->refresh_now( 'user_level_change', [
				'old_level' => $old_value,
				'new_level' => $new_value,
			] );
		}
	}

	/**
	 * Check for CPT/taxonomy changes
	 *
	 * Compares current registered CPTs/taxonomies with stored ones.
	 *
	 * @return void
	 */
	public function check_cpt_changes(): void {
		// Only run in admin
		if ( ! is_admin() ) {
			return;
		}

		// Get current CPTs and taxonomies
		$current_cpts = array_keys( get_post_types( [ '_builtin' => false ], 'names' ) );
		$current_taxes = array_keys( get_taxonomies( [ '_builtin' => false ], 'names' ) );

		// Get stored ones
		$stored_cpts = get_option( 'creator_known_cpts', [] );
		$stored_taxes = get_option( 'creator_known_taxonomies', [] );

		// Check for changes
		$cpts_changed = $current_cpts !== $stored_cpts;
		$taxes_changed = $current_taxes !== $stored_taxes;

		if ( $cpts_changed || $taxes_changed ) {
			// Update stored values
			update_option( 'creator_known_cpts', $current_cpts, false );
			update_option( 'creator_known_taxonomies', $current_taxes, false );

			// Schedule refresh
			$this->schedule_refresh( 'cpt_taxonomy_change', [
				'cpts_changed'  => $cpts_changed,
				'taxes_changed' => $taxes_changed,
			] );
		}
	}

	/**
	 * Handle ACF field group change
	 *
	 * @param array $field_group Field group data.
	 * @return void
	 */
	public function on_acf_change( array $field_group ): void {
		$this->schedule_refresh( 'acf_change', [
			'field_group' => $field_group['title'] ?? 'unknown',
		] );
	}

	/**
	 * Schedule a context refresh
	 *
	 * Uses a transient to debounce multiple rapid changes.
	 *
	 * @param string $reason  Refresh reason.
	 * @param array  $details Additional details.
	 * @return void
	 */
	private function schedule_refresh( string $reason, array $details = [] ): void {
		// Use transient to debounce (wait 5 seconds before refreshing)
		$debounce_key = 'creator_context_refresh_pending';

		if ( get_transient( $debounce_key ) ) {
			// Already pending, just update the reason
			$pending = get_transient( $debounce_key );
			$pending['reasons'][] = $reason;
			set_transient( $debounce_key, $pending, 10 );
			return;
		}

		// Set pending flag
		set_transient( $debounce_key, [
			'reasons'    => [ $reason ],
			'details'    => $details,
			'started_at' => time(),
		], 10 );

		// Schedule the actual refresh
		if ( ! wp_next_scheduled( 'creator_do_context_refresh' ) ) {
			wp_schedule_single_event( time() + 5, 'creator_do_context_refresh' );
		}
	}

	/**
	 * Refresh context immediately
	 *
	 * @param string $reason  Refresh reason.
	 * @param array  $details Additional details.
	 * @return array Refresh result.
	 */
	public function refresh_now( string $reason, array $details = [] ): array {
		$start_time = microtime( true );

		try {
			// Generate new context
			$context = $this->get_context()->refresh();

			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			$this->get_logger()->success( 'context_refreshed', [
				'reason'      => $reason,
				'details'     => $details,
				'duration_ms' => $duration,
			] );

			return [
				'success'     => true,
				'reason'      => $reason,
				'duration_ms' => $duration,
				'timestamp'   => current_time( 'c' ),
			];
		} catch ( \Exception $e ) {
			$this->get_logger()->failure( 'context_refresh_failed', [
				'reason' => $reason,
				'error'  => $e->getMessage(),
			] );

			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * AJAX handler for manual context refresh
	 *
	 * @return void
	 */
	public function ajax_refresh_context(): void {
		// Verify nonce
		check_ajax_referer( 'creator_refresh_context', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
		}

		$result = $this->refresh_now( 'manual_refresh', [
			'user_id' => get_current_user_id(),
		] );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Get context status
	 *
	 * @return array
	 */
	public function get_status(): array {
		$context        = $this->get_context();
		$stored_context = $context->get_stored_context();

		return [
			'has_context'     => $stored_context !== null,
			'generated_at'    => $context->get_generated_at(),
			'is_valid'        => $context->is_context_valid(),
			'is_stale'        => $context->is_context_stale(),
			'pending_refresh' => get_transient( 'creator_context_refresh_pending' ) !== false,
		];
	}
}

// Hook for scheduled refresh
add_action( 'creator_do_context_refresh', function() {
	$pending = get_transient( 'creator_context_refresh_pending' );

	if ( $pending ) {
		delete_transient( 'creator_context_refresh_pending' );

		$refresher = new ContextRefresher();
		$refresher->refresh_now( 'scheduled_refresh', [
			'reasons' => $pending['reasons'] ?? [],
		] );
	}
} );
