<?php
/**
 * Plugin Loader
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Admin\Dashboard;
use CreatorCore\Admin\Settings;
use CreatorCore\Admin\SetupWizard;
use CreatorCore\Chat\ChatInterface;
use CreatorCore\Backup\SnapshotManager;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\Audit\AuditLogger;
use CreatorCore\Integrations\ProxyClient;
use CreatorCore\Integrations\PluginDetector;
use CreatorCore\API\REST_API;
use CreatorCore\Context\ContextRefresher;
use CreatorCore\Executor\CustomCodeLoader;

/**
 * Class Loader
 *
 * Main plugin loader that initializes all components
 */
class Loader {

    /**
     * Dashboard instance
     *
     * @var Dashboard
     */
    private Dashboard $dashboard;

    /**
     * Settings instance
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Setup wizard instance
     *
     * @var SetupWizard
     */
    private SetupWizard $setup_wizard;

    /**
     * Chat interface instance
     *
     * @var ChatInterface
     */
    private ChatInterface $chat_interface;

    /**
     * Snapshot manager instance
     *
     * @var SnapshotManager
     */
    private SnapshotManager $snapshot_manager;

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
    private AuditLogger $audit_logger;

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
     * REST API instance
     *
     * @var REST_API
     */
    private REST_API $rest_api;

    /**
     * Context refresher instance
     *
     * @var ContextRefresher
     */
    private ContextRefresher $context_refresher;

    /**
     * Custom code loader instance
     *
     * @var CustomCodeLoader
     */
    private CustomCodeLoader $custom_code_loader;

    /**
     * Run the loader
     *
     * @return void
     */
    public function run(): void {
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Initialize all components
     *
     * @return void
     */
    private function init_components(): void {
        // Core services (order matters - dependencies first)
        $this->audit_logger       = new AuditLogger();
        $this->capability_checker = new CapabilityChecker();
        $this->plugin_detector    = new PluginDetector();
        $this->proxy_client       = new ProxyClient();
        $this->snapshot_manager   = new SnapshotManager( $this->audit_logger );

        // Admin components
        $this->dashboard     = new Dashboard( $this->plugin_detector, $this->audit_logger );
        $this->settings      = new Settings( $this->proxy_client, $this->plugin_detector );
        $this->setup_wizard  = new SetupWizard( $this->plugin_detector );

        // Chat system
        $this->chat_interface = new ChatInterface(
            $this->proxy_client,
            $this->capability_checker,
            $this->snapshot_manager,
            $this->audit_logger
        );

        // REST API
        $this->rest_api = new REST_API(
            $this->chat_interface,
            $this->capability_checker,
            $this->audit_logger
        );

        // Context auto-refresh
        $this->context_refresher = new ContextRefresher();

        // Custom code loader (for WP Code fallback)
        $this->custom_code_loader = new CustomCodeLoader();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void {
        // Admin menu
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

        // Admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // REST API
        add_action( 'rest_api_init', [ $this->rest_api, 'register_routes' ] );

        // Setup wizard redirect on activation (priority 1 to run early)
        add_action( 'admin_init', [ $this->setup_wizard, 'maybe_redirect' ], 1 );

        // Plugin action links
        add_filter( 'plugin_action_links_' . CREATOR_CORE_BASENAME, [ $this, 'add_action_links' ] );

        // Add custom capabilities on init
        add_action( 'init', [ $this->capability_checker, 'register_capabilities' ] );

        // Register context auto-refresh hooks
        $this->context_refresher->register_hooks();

        // Initialize custom code loader (loads PHP, enqueues CSS/JS)
        $this->custom_code_loader->init();

        // Register cron handler for backup cleanup
        add_action( 'creator_cleanup_backups', [ $this, 'run_backup_cleanup' ] );

        // Register cron handler for thinking logs cleanup (30 days retention)
        add_action( 'creator_cleanup_thinking_logs', [ Activator::class, 'cleanup_thinking_logs' ] );
    }

    /**
     * Run scheduled backup cleanup
     *
     * Enforces both retention days and max size limits.
     *
     * @return void
     */
    public function run_backup_cleanup(): void {
        $retention_days = (int) get_option( 'creator_backup_retention', 30 );
        $max_size_mb    = (int) get_option( 'creator_max_backup_size_mb', 500 );

        // Clean up old snapshots by retention days
        $deleted_by_age = $this->snapshot_manager->cleanup_old_snapshots( $retention_days );

        // Enforce max size limit
        $deleted_by_size = $this->snapshot_manager->enforce_size_limit( $max_size_mb );

        if ( $deleted_by_age > 0 || $deleted_by_size > 0 ) {
            $this->audit_logger->success( 'scheduled_backup_cleanup', [
                'deleted_by_age'  => $deleted_by_age,
                'deleted_by_size' => $deleted_by_size,
                'retention_days'  => $retention_days,
                'max_size_mb'     => $max_size_mb,
            ]);
        }
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_admin_menu(): void {
        // Main menu - accessible to anyone who can edit posts
        add_menu_page(
            __( 'Creator', 'creator-core' ),
            __( 'Creator', 'creator-core' ),
            'edit_posts',
            'creator-dashboard',
            [ $this->dashboard, 'render' ],
            'dashicons-superhero-alt',
            30
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            'creator-dashboard',
            __( 'Dashboard', 'creator-core' ),
            __( 'Dashboard', 'creator-core' ),
            'edit_posts',
            'creator-dashboard',
            [ $this->dashboard, 'render' ]
        );

        // New Chat
        add_submenu_page(
            'creator-dashboard',
            __( 'New Chat', 'creator-core' ),
            __( 'New Chat', 'creator-core' ),
            'edit_posts',
            'creator-chat',
            [ $this->chat_interface, 'render' ]
        );

        // Settings
        add_submenu_page(
            'creator-dashboard',
            __( 'Settings', 'creator-core' ),
            __( 'Settings', 'creator-core' ),
            'manage_options',
            'creator-settings',
            [ $this->settings, 'render' ]
        );

        // Setup Wizard (hidden from menu - use 'options.php' as parent for PHP 8 compatibility)
        add_submenu_page(
            'options.php',
            __( 'Setup Wizard', 'creator-core' ),
            __( 'Setup Wizard', 'creator-core' ),
            'manage_options',
            'creator-setup',
            [ $this->setup_wizard, 'render' ]
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Ensure $hook is a valid string (PHP 8 compatibility)
        $hook = (string) $hook;
        if ( $hook === '' ) {
            return;
        }

        // Only load on Creator pages
        if ( strpos( $hook, 'creator' ) === false ) {
            return;
        }

        // Common styles
        wp_enqueue_style(
            'creator-admin-common',
            CREATOR_CORE_URL . 'assets/css/admin-common.css',
            [],
            CREATOR_CORE_VERSION
        );

        // Page-specific assets
        if ( strpos( $hook, 'creator-dashboard' ) !== false ) {
            wp_enqueue_style(
                'creator-admin-dashboard',
                CREATOR_CORE_URL . 'assets/css/admin-dashboard.css',
                [ 'creator-admin-common' ],
                CREATOR_CORE_VERSION
            );
            wp_enqueue_script(
                'creator-admin-dashboard',
                CREATOR_CORE_URL . 'assets/js/admin-dashboard.js',
                [ 'jquery' ],
                CREATOR_CORE_VERSION,
                true
            );
            wp_localize_script( 'creator-admin-dashboard', 'creatorDashboard', [
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'restUrl'    => rest_url( 'creator/v1/' ),
                'nonce'      => wp_create_nonce( 'creator_dashboard_nonce' ),
                'restNonce'  => wp_create_nonce( 'wp_rest' ),
                'newChatUrl' => admin_url( 'admin.php?page=creator-chat' ),
                'i18n'       => [
                    'loading'      => __( 'Loading...', 'creator-core' ),
                    'error'        => __( 'An error occurred', 'creator-core' ),
                    'noChats'      => __( 'No recent chats', 'creator-core' ),
                    'refreshStats' => __( 'Refresh statistics', 'creator-core' ),
                ],
            ]);
        }

        if ( strpos( $hook, 'creator-chat' ) !== false ) {
            wp_enqueue_style(
                'creator-chat-interface',
                CREATOR_CORE_URL . 'assets/css/chat-interface.css',
                [ 'creator-admin-common' ],
                CREATOR_CORE_VERSION
            );
            wp_enqueue_script(
                'creator-chat-interface',
                CREATOR_CORE_URL . 'assets/js/chat-interface.js',
                [ 'jquery', 'wp-util' ],
                CREATOR_CORE_VERSION,
                true
            );
            wp_enqueue_script(
                'creator-action-handler',
                CREATOR_CORE_URL . 'assets/js/action-handler.js',
                [ 'creator-chat-interface' ],
                CREATOR_CORE_VERSION,
                true
            );
            $current_user = wp_get_current_user();
            $chat_id      = isset( $_GET['chat_id'] ) ? absint( $_GET['chat_id'] ) : null;
            wp_localize_script( 'creator-chat-interface', 'creatorChat', [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'restUrl'     => rest_url( 'creator/v1/' ),
                'adminUrl'    => admin_url( 'admin.php' ),
                'settingsUrl' => admin_url( 'admin.php?page=creator-settings#api' ),
                'nonce'       => wp_create_nonce( 'creator_chat_nonce' ),
                'restNonce'   => wp_create_nonce( 'wp_rest' ),
                'chatId'      => $chat_id,
                'userName'    => $current_user->display_name,
                'userAvatar'  => get_avatar_url( $current_user->ID, [ 'size' => 32 ] ),
                'i18n'        => [
                    'sending'       => __( 'Sending...', 'creator-core' ),
                    'error'         => __( 'An error occurred. Please try again.', 'creator-core' ),
                    'confirmUndo'   => __( 'Are you sure you want to undo this action?', 'creator-core' ),
                    'undoSuccess'   => __( 'Action undone successfully.', 'creator-core' ),
                    'processing'    => __( 'Processing...', 'creator-core' ),
                    'goToSettings'  => __( 'Go to Settings', 'creator-core' ),
                ],
            ]);
        }

        if ( strpos( $hook, 'creator-setup' ) !== false ) {
            wp_enqueue_style(
                'creator-setup-wizard',
                CREATOR_CORE_URL . 'assets/css/setup-wizard.css',
                [ 'creator-admin-common' ],
                CREATOR_CORE_VERSION
            );
            wp_enqueue_script(
                'creator-setup-wizard',
                CREATOR_CORE_URL . 'assets/js/setup-wizard.js',
                [ 'jquery' ],
                CREATOR_CORE_VERSION,
                true
            );
            $current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'safety';
            $step_map     = [ 'safety' => 1, 'overview' => 2, 'backup' => 3, 'license' => 4, 'profile' => 5, 'finish' => 6 ];
            wp_localize_script( 'creator-setup-wizard', 'creatorSetup', [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'creator_setup_nonce' ),
                'currentStep'  => $step_map[ $current_step ] ?? 1,
                'dashboardUrl' => admin_url( 'admin.php?page=creator-dashboard' ),
                'i18n'         => [
                    'installing' => __( 'Installing...', 'creator-core' ),
                    'installed'  => __( 'Installed', 'creator-core' ),
                    'error'      => __( 'Installation failed', 'creator-core' ),
                    'validating' => __( 'Validating...', 'creator-core' ),
                    'valid'      => __( 'Valid', 'creator-core' ),
                    'invalid'    => __( 'Invalid license key', 'creator-core' ),
                ],
            ]);
        }

        if ( strpos( $hook, 'creator-settings' ) !== false ) {
            wp_enqueue_style(
                'creator-settings',
                CREATOR_CORE_URL . 'assets/css/settings.css',
                [ 'creator-admin-common' ],
                CREATOR_CORE_VERSION
            );
            wp_enqueue_script(
                'creator-settings',
                CREATOR_CORE_URL . 'assets/js/settings.js',
                [ 'jquery' ],
                CREATOR_CORE_VERSION,
                true
            );
            wp_localize_script( 'creator-settings', 'creatorSettings', [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'restUrl'   => rest_url( 'creator/v1/' ),
                'nonce'     => wp_create_nonce( 'creator_settings_nonce' ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'i18n'      => [
                    'saving'       => __( 'Saving...', 'creator-core' ),
                    'saved'        => __( 'Settings saved', 'creator-core' ),
                    'error'        => __( 'An error occurred', 'creator-core' ),
                    'validating'   => __( 'Validating...', 'creator-core' ),
                    'clearing'     => __( 'Clearing cache...', 'creator-core' ),
                    'cacheCleared' => __( 'Cache cleared', 'creator-core' ),
                    'confirmDelete' => __( 'Are you sure you want to delete this?', 'creator-core' ),
                ],
            ]);
        }
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_action_links( array $links ): array {
        $plugin_links = [
            '<a href="' . admin_url( 'admin.php?page=creator-settings' ) . '">' . __( 'Settings', 'creator-core' ) . '</a>',
            '<a href="' . admin_url( 'admin.php?page=creator-dashboard' ) . '">' . __( 'Dashboard', 'creator-core' ) . '</a>',
        ];

        return array_merge( $plugin_links, $links );
    }

    /**
     * Get component instance
     *
     * @param string $component Component name.
     * @return object|null
     */
    public function get_component( string $component ): ?object {
        $property = str_replace( '-', '_', $component );
        return $this->$property ?? null;
    }
}
