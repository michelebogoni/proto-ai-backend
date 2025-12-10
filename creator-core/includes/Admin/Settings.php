<?php
/**
 * Settings Page
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ProxyClient;
use CreatorCore\Integrations\PluginDetector;
use CreatorCore\Permission\RoleMapper;
use CreatorCore\Backup\SnapshotManager;
use CreatorCore\User\UserProfile;
use CreatorCore\Context\CreatorContext;
use CreatorCore\Context\ContextRefresher;

/**
 * Class Settings
 *
 * Handles the plugin settings page
 */
class Settings {

    /**
     * Proxy client instance
     *
     * @var ProxyClient
     */
    private ProxyClient $proxy_client;

    /**
     * Plugin detector instance
     *
     * @var PluginDetector
     */
    private PluginDetector $plugin_detector;

    /**
     * Settings groups
     *
     * @var array
     */
    private array $settings_groups = [
        'api'         => 'API Configuration',
        'profile'     => 'Your Profile',
        'context'     => 'AI Context',
        'backup'      => 'Backup Settings',
        'permissions' => 'User Permissions',
        'advanced'    => 'Advanced',
    ];

    /**
     * Constructor
     *
     * @param ProxyClient    $proxy_client    Proxy client instance.
     * @param PluginDetector $plugin_detector Plugin detector instance.
     */
    public function __construct( ProxyClient $proxy_client, PluginDetector $plugin_detector ) {
        $this->proxy_client    = $proxy_client;
        $this->plugin_detector = $plugin_detector;

        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_creator_validate_license', [ $this, 'ajax_validate_license' ] );
        add_action( 'wp_ajax_creator_clear_cache', [ $this, 'ajax_clear_cache' ] );
        add_action( 'wp_ajax_creator_cleanup_backups', [ $this, 'ajax_cleanup_backups' ] );
        add_action( 'wp_ajax_creator_save_profile', [ $this, 'ajax_save_profile' ] );
        add_action( 'wp_ajax_creator_refresh_context', [ $this, 'ajax_refresh_context' ] );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render(): void {
        // Handle form submission
        if ( isset( $_POST['creator_settings_nonce'] ) ) {
            $this->handle_save();
        }

        $data = [
            'settings'       => $this->get_all_settings(),
            'roles'          => $this->get_roles_settings(),
            'backup_stats'   => $this->get_backup_stats(),
            'connection'     => $this->proxy_client->check_connection(),
            'user_profile'   => [
                'current_level' => UserProfile::get_level(),
                'levels'        => UserProfile::get_levels_info(),
                'is_set'        => UserProfile::is_level_set(),
            ],
            'context_status' => $this->get_context_status(),
        ];

        include CREATOR_CORE_PATH . 'templates/settings.php';
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void {
        // API Settings
        register_setting( 'creator_api_settings', 'creator_license_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'creator_api_settings', 'creator_proxy_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => CREATOR_PROXY_URL,
        ]);

        // Backup Settings
        register_setting( 'creator_backup_settings', 'creator_backup_retention', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);
        register_setting( 'creator_backup_settings', 'creator_max_backup_size_mb', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 500,
        ]);

        // Permission Settings
        register_setting( 'creator_permission_settings', 'creator_allowed_roles', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_roles' ],
            'default'           => [ 'administrator', 'creator_admin' ],
        ]);

        // Advanced Settings
        register_setting( 'creator_advanced_settings', 'creator_debug_mode', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
        register_setting( 'creator_advanced_settings', 'creator_log_level', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'info',
        ]);
        register_setting( 'creator_advanced_settings', 'creator_delete_data_on_uninstall', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
    }

    /**
     * Handle settings save
     *
     * @return void
     */
    private function handle_save(): void {
        if ( ! wp_verify_nonce( $_POST['creator_settings_nonce'], 'creator_save_settings' ) ) {
            add_settings_error( 'creator_settings', 'nonce_error', __( 'Security check failed.', 'creator-core' ), 'error' );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error( 'creator_settings', 'permission_error', __( 'Permission denied.', 'creator-core' ), 'error' );
            return;
        }

        // API Settings
        if ( isset( $_POST['creator_license_key'] ) ) {
            update_option( 'creator_license_key', sanitize_text_field( $_POST['creator_license_key'] ) );
        }
        if ( isset( $_POST['creator_proxy_url'] ) ) {
            update_option( 'creator_proxy_url', esc_url_raw( $_POST['creator_proxy_url'] ) );
        }
        // Backup Settings
        if ( isset( $_POST['creator_backup_retention'] ) ) {
            update_option( 'creator_backup_retention', absint( $_POST['creator_backup_retention'] ) );
        }
        if ( isset( $_POST['creator_max_backup_size_mb'] ) ) {
            update_option( 'creator_max_backup_size_mb', absint( $_POST['creator_max_backup_size_mb'] ) );
        }

        // Permission Settings
        if ( isset( $_POST['creator_allowed_roles'] ) ) {
            $roles = array_map( 'sanitize_text_field', (array) $_POST['creator_allowed_roles'] );
            update_option( 'creator_allowed_roles', $roles );
        }

        // Advanced Settings
        update_option( 'creator_debug_mode', isset( $_POST['creator_debug_mode'] ) );
        if ( isset( $_POST['creator_log_level'] ) ) {
            update_option( 'creator_log_level', sanitize_text_field( $_POST['creator_log_level'] ) );
        }
        update_option( 'creator_delete_data_on_uninstall', isset( $_POST['creator_delete_data_on_uninstall'] ) );

        add_settings_error( 'creator_settings', 'settings_saved', __( 'Settings saved successfully.', 'creator-core' ), 'success' );
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all_settings(): array {
        return [
            'license_key'               => get_option( 'creator_license_key', '' ),
            'proxy_url'                 => get_option( 'creator_proxy_url', CREATOR_PROXY_URL ),
            'backup_retention'          => get_option( 'creator_backup_retention', 30 ),
            'max_backup_size_mb'        => get_option( 'creator_max_backup_size_mb', 500 ),
            'allowed_roles'             => get_option( 'creator_allowed_roles', [ 'administrator', 'creator_admin' ] ),
            'debug_mode'                => get_option( 'creator_debug_mode', false ),
            'log_level'                 => get_option( 'creator_log_level', 'info' ),
            'delete_data_on_uninstall'  => get_option( 'creator_delete_data_on_uninstall', false ),
            'setup_completed'           => get_option( 'creator_setup_completed', false ),
        ];
    }

    /**
     * Get roles settings
     *
     * @return array
     */
    private function get_roles_settings(): array {
        $role_mapper   = new RoleMapper();
        $all_roles     = $role_mapper->get_available_roles();
        $allowed_roles = get_option( 'creator_allowed_roles', [ 'administrator', 'creator_admin' ] );

        $roles = [];
        foreach ( $all_roles as $slug => $role_data ) {
            $roles[ $slug ] = [
                'name'    => $role_data['name'],
                'enabled' => in_array( $slug, $allowed_roles, true ),
            ];
        }

        return $roles;
    }

    /**
     * Get backup statistics
     *
     * @return array
     */
    private function get_backup_stats(): array {
        $snapshot_manager = new SnapshotManager();
        return $snapshot_manager->get_backup_stats();
    }

    /**
     * Sanitize roles array
     *
     * @param mixed $input Input value.
     * @return array
     */
    public function sanitize_roles( $input ): array {
        if ( ! is_array( $input ) ) {
            return [ 'administrator' ];
        }

        return array_map( 'sanitize_text_field', $input );
    }

    /**
     * AJAX: Validate license
     *
     * @return void
     */
    public function ajax_validate_license(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'License key is required', 'creator-core' ) ] );
        }

        $result = $this->proxy_client->validate_license( $license_key );

        if ( $result['success'] ) {
            update_option( 'creator_license_key', $license_key );
            wp_send_json_success( [
                'message' => __( 'License validated successfully', 'creator-core' ),
                'data'    => $result,
            ]);
        } else {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'License validation failed', 'creator-core' ),
            ]);
        }
    }

    /**
     * AJAX: Clear cache
     *
     * @return void
     */
    public function ajax_clear_cache(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        // Clear plugin caches
        delete_transient( 'creator_detected_plugins' );
        delete_transient( 'creator_site_context' );
        delete_transient( 'creator_license_status' );

        wp_cache_flush();

        wp_send_json_success( [ 'message' => __( 'Cache cleared successfully', 'creator-core' ) ] );
    }

    /**
     * AJAX: Cleanup backups
     *
     * @return void
     */
    public function ajax_cleanup_backups(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $retention = get_option( 'creator_backup_retention', 30 );
        $snapshot_manager = new SnapshotManager();
        $deleted = $snapshot_manager->cleanup_old_snapshots( $retention );

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: Number of snapshots deleted */
                __( 'Cleanup completed. %d snapshots removed.', 'creator-core' ),
                $deleted
            ),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Get log levels
     *
     * @return array
     */
    public function get_log_levels(): array {
        return [
            'debug'   => __( 'Debug', 'creator-core' ),
            'info'    => __( 'Info', 'creator-core' ),
            'warning' => __( 'Warning', 'creator-core' ),
            'error'   => __( 'Error', 'creator-core' ),
        ];
    }

    /**
     * AJAX: Save user profile (competency level and default model)
     *
     * @return void
     */
    public function ajax_save_profile(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $level = isset( $_POST['user_level'] ) ? sanitize_key( wp_unslash( $_POST['user_level'] ) ) : '';
        $model = isset( $_POST['default_model'] ) ? sanitize_key( wp_unslash( $_POST['default_model'] ) ) : '';

        if ( empty( $level ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select a competency level', 'creator-core' ) ] );
        }

        if ( ! in_array( $level, UserProfile::get_valid_levels(), true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid competency level', 'creator-core' ) ] );
        }

        // Validate model if provided
        if ( ! empty( $model ) && ! in_array( $model, UserProfile::get_valid_models(), true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid AI model', 'creator-core' ) ] );
        }

        $level_saved = UserProfile::set_level( $level );

        // Save default model if provided
        $model_saved = true;
        if ( ! empty( $model ) ) {
            $model_saved = UserProfile::set_default_model( $model );
        }

        if ( $level_saved && $model_saved ) {
            $levels_info = UserProfile::get_levels_info();
            $models_info = UserProfile::get_models_info();

            $response = [
                'message' => __( 'Profile updated successfully', 'creator-core' ),
                'level'   => $level,
                'label'   => $levels_info[ $level ]['label'],
            ];

            if ( ! empty( $model ) ) {
                $response['model']       = $model;
                $response['model_label'] = $models_info[ $model ]['label'];
            }

            wp_send_json_success( $response );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to save profile', 'creator-core' ) ] );
        }
    }

    /**
     * Get context status for settings page
     *
     * @return array
     */
    private function get_context_status(): array {
        try {
            $context   = new CreatorContext();
            $refresher = new ContextRefresher();

            $stored = $context->get_stored_context();

            return [
                'has_context'     => $stored !== null,
                'generated_at'    => $context->get_generated_at(),
                'is_valid'        => $context->is_context_valid(),
                'is_stale'        => $context->is_context_stale(),
                'pending_refresh' => $refresher->get_status()['pending_refresh'] ?? false,
                'plugins_count'   => count( $stored['plugins'] ?? [] ),
                'cpts_count'      => count( $stored['custom_post_types'] ?? [] ),
                'acf_groups'      => count( $stored['acf_fields'] ?? [] ),
                'sitemap_count'   => count( $stored['sitemap'] ?? [] ),
            ];
        } catch ( \Exception $e ) {
            // Return safe defaults if context loading fails
            return [
                'has_context'     => false,
                'generated_at'    => null,
                'is_valid'        => false,
                'is_stale'        => true,
                'pending_refresh' => false,
                'plugins_count'   => 0,
                'cpts_count'      => 0,
                'acf_groups'      => 0,
                'sitemap_count'   => 0,
            ];
        }
    }

    /**
     * AJAX: Refresh Creator Context
     *
     * @return void
     */
    public function ajax_refresh_context(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $start_time = microtime( true );

        try {
            $context = new CreatorContext();
            $result  = $context->refresh();

            $duration = round( ( microtime( true ) - $start_time ) * 1000 );

            wp_send_json_success( [
                'message'     => __( 'Creator Context refreshed successfully', 'creator-core' ),
                'duration_ms' => $duration,
                'timestamp'   => $context->get_generated_at(),
                'stats'       => [
                    'plugins'  => count( $result['plugins'] ?? [] ),
                    'cpts'     => count( $result['custom_post_types'] ?? [] ),
                    'acf'      => count( $result['acf_fields'] ?? [] ),
                    'sitemap'  => count( $result['sitemap'] ?? [] ),
                ],
            ] );
        } catch ( \Exception $e ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __( 'Failed to refresh context: %s', 'creator-core' ),
                    $e->getMessage()
                ),
            ] );
        }
    }
}
