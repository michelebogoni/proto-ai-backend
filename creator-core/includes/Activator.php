<?php
/**
 * Plugin Activator
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 *
 * Handles plugin activation tasks
 */
class Activator {

    /**
     * Activate the plugin
     *
     * @return void
     */
    public static function activate(): void {
        self::create_tables();
        self::create_backup_directory();
        self::create_custom_role();
        self::set_default_options();
        self::schedule_cleanup_cron();
        self::set_activation_redirect();
        self::generate_initial_context();

        // Clear any cached data
        wp_cache_flush();

        // Log activation
        if ( class_exists( '\CreatorCore\Audit\AuditLogger' ) ) {
            $logger = new \CreatorCore\Audit\AuditLogger();
            $logger->log( 'plugin_activated', 'success', [
                'version' => CREATOR_CORE_VERSION,
            ]);
        }
    }

    /**
     * Generate initial Creator Context document
     *
     * Creates the comprehensive context document on plugin activation.
     *
     * @return void
     */
    private static function generate_initial_context(): void {
        if ( class_exists( '\CreatorCore\Context\CreatorContext' ) ) {
            try {
                $context = new \CreatorCore\Context\CreatorContext();
                $context->generate( true );
            } catch ( \Exception $e ) {
                // Log error but don't fail activation
                error_log( 'Creator: Failed to generate initial context: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Create database tables
     *
     * @return void
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Chats table
        $table_chats = $wpdb->prefix . 'creator_chats';
        $sql_chats = "CREATE TABLE {$table_chats} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(255) DEFAULT '',
            status varchar(20) DEFAULT 'active',
            ai_model varchar(20) DEFAULT 'gemini',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY ai_model (ai_model),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql_chats );

        // Migration: Rename performance_tier to ai_model if it exists
        self::migrate_tier_to_model();

        // Messages table
        $table_messages = $wpdb->prefix . 'creator_messages';
        $sql_messages = "CREATE TABLE {$table_messages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role varchar(20) DEFAULT 'user',
            content longtext,
            message_type varchar(20) DEFAULT 'text',
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY user_id (user_id),
            KEY role (role),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql_messages );

        // Actions table
        $table_actions = $wpdb->prefix . 'creator_actions';
        $sql_actions = "CREATE TABLE {$table_actions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            action_type varchar(255) NOT NULL,
            target varchar(255) DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            error_message longtext,
            snapshot_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY action_type (action_type),
            KEY status (status),
            KEY snapshot_id (snapshot_id)
        ) {$charset_collate};";
        dbDelta( $sql_actions );

        // Snapshots table
        $table_snapshots = $wpdb->prefix . 'creator_snapshots';
        $sql_snapshots = "CREATE TABLE {$table_snapshots} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) unsigned NOT NULL,
            message_id bigint(20) unsigned DEFAULT NULL,
            action_id bigint(20) unsigned DEFAULT NULL,
            snapshot_type varchar(20) DEFAULT 'DELTA',
            operations longtext,
            storage_file varchar(500) DEFAULT '',
            storage_size_kb int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            deleted tinyint(1) DEFAULT 0,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY message_id (message_id),
            KEY action_id (action_id),
            KEY deleted (deleted)
        ) {$charset_collate};";
        dbDelta( $sql_snapshots );

        // Audit log table
        $table_audit = $wpdb->prefix . 'creator_audit_log';
        $sql_audit = "CREATE TABLE {$table_audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(255) NOT NULL,
            operation_id bigint(20) unsigned DEFAULT NULL,
            details longtext,
            ip_address varchar(45) DEFAULT '',
            status varchar(20) DEFAULT 'success',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql_audit );

        // Backups table
        $table_backups = $wpdb->prefix . 'creator_backups';
        $sql_backups = "CREATE TABLE {$table_backups} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) unsigned NOT NULL,
            file_path varchar(500) DEFAULT '',
            file_size_kb int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        dbDelta( $sql_backups );

        // Thinking logs table - stores Creator's reasoning process for transparency
        $table_thinking = $wpdb->prefix . 'creator_thinking_logs';
        $sql_thinking = "CREATE TABLE {$table_thinking} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) unsigned NOT NULL,
            message_id bigint(20) unsigned DEFAULT NULL,
            logs longtext NOT NULL,
            summary longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY message_id (message_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta( $sql_thinking );

        // Store database version
        update_option( 'creator_core_db_version', CREATOR_CORE_VERSION );
    }

    /**
     * Create backup directory
     *
     * @return void
     */
    private static function create_backup_directory(): void {
        $upload_dir  = wp_upload_dir();
        $backup_path = $upload_dir['basedir'] . '/creator-backups';

        if ( ! file_exists( $backup_path ) ) {
            wp_mkdir_p( $backup_path );

            // Create .htaccess to protect backups
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents( $backup_path . '/.htaccess', $htaccess_content );

            // Create index.php for additional protection
            file_put_contents( $backup_path . '/index.php', '<?php // Silence is golden.' );
        }

        // Store backup path
        update_option( 'creator_backup_path', $backup_path );
    }

    /**
     * Create custom Creator Admin role
     *
     * @return void
     */
    private static function create_custom_role(): void {
        // Get administrator capabilities as base
        $admin_role = get_role( 'administrator' );
        if ( ! $admin_role ) {
            return;
        }

        // Remove existing role if it exists (for updates)
        remove_role( 'creator_admin' );

        // Create Creator Admin role with specific capabilities
        add_role(
            'creator_admin',
            __( 'Creator Admin', 'creator-core' ),
            [
                'read'                   => true,
                'edit_posts'             => true,
                'edit_pages'             => true,
                'edit_others_posts'      => true,
                'edit_others_pages'      => true,
                'edit_published_posts'   => true,
                'edit_published_pages'   => true,
                'publish_posts'          => true,
                'publish_pages'          => true,
                'delete_posts'           => true,
                'delete_pages'           => true,
                'upload_files'           => true,
                'manage_categories'      => true,
                // Creator-specific capabilities
                'use_creator'            => true,
                'manage_creator_chats'   => true,
                'view_creator_audit'     => true,
                'manage_creator_backups' => true,
            ]
        );

        // Add Creator capabilities to administrator
        $admin_role->add_cap( 'use_creator' );
        $admin_role->add_cap( 'manage_creator_chats' );
        $admin_role->add_cap( 'view_creator_audit' );
        $admin_role->add_cap( 'manage_creator_backups' );
        $admin_role->add_cap( 'manage_creator_settings' );
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options(): void {
        $defaults = [
            'creator_license_key'         => '',
            'creator_site_token'          => '',
            'creator_proxy_url'           => CREATOR_PROXY_URL,
            'creator_backup_retention'    => 30, // days
            'creator_max_backup_size_mb'  => 500,
            'creator_debug_mode'          => false,
            'creator_log_level'           => 'info',
            'creator_allowed_roles'       => [ 'administrator', 'creator_admin' ],
            'creator_setup_completed'     => false,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }

    /**
     * Schedule cleanup cron job
     *
     * @return void
     */
    private static function schedule_cleanup_cron(): void {
        if ( ! wp_next_scheduled( 'creator_cleanup_backups' ) ) {
            wp_schedule_event( time(), 'daily', 'creator_cleanup_backups' );
        }

        // Schedule thinking logs cleanup (30 days retention)
        if ( ! wp_next_scheduled( 'creator_cleanup_thinking_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'creator_cleanup_thinking_logs' );
        }
    }

    /**
     * Cleanup old thinking logs (older than 30 days)
     *
     * @return int Number of deleted rows.
     */
    public static function cleanup_thinking_logs(): int {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_thinking_logs';

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return 0;
        }

        // Delete logs older than 30 days
        $deleted = $wpdb->query(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Set activation redirect flag
     * Uses simple string value as per WordPress official documentation
     *
     * @see https://developer.wordpress.org/reference/functions/register_activation_hook/
     * @return void
     */
    private static function set_activation_redirect(): void {
        // Only redirect if not already completed setup
        if ( ! get_option( 'creator_setup_completed' ) ) {
            // Use update_option to ensure value is always written (add_option won't overwrite)
            update_option( 'creator_do_activation_redirect', 'yes' );
        }
    }

    /**
     * Migrate from performance_tier to ai_model column
     *
     * This handles existing installations that have the old column name.
     * Maps old tier values to new model values:
     * - flow -> gemini (was the fast/default option)
     * - craft -> gemini (was the quality option, now both use same model)
     *
     * @return void
     */
    private static function migrate_tier_to_model(): void {
        global $wpdb;

        $table_chats = $wpdb->prefix . 'creator_chats';

        // Check if performance_tier column exists
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'performance_tier'",
                DB_NAME,
                $table_chats
            )
        );

        if ( $column_exists ) {
            // Check if ai_model column already exists
            $new_column_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'ai_model'",
                    DB_NAME,
                    $table_chats
                )
            );

            if ( ! $new_column_exists ) {
                // Add new ai_model column
                $wpdb->query( "ALTER TABLE {$table_chats} ADD COLUMN ai_model varchar(20) DEFAULT 'gemini' AFTER status" );

                // Migrate data: all old tiers become gemini (the new default)
                $wpdb->query( "UPDATE {$table_chats} SET ai_model = 'gemini'" );

                // Add index on new column
                $wpdb->query( "ALTER TABLE {$table_chats} ADD KEY ai_model (ai_model)" );
            }

            // Drop old column and its index
            $wpdb->query( "ALTER TABLE {$table_chats} DROP KEY IF EXISTS performance_tier" );
            $wpdb->query( "ALTER TABLE {$table_chats} DROP COLUMN IF EXISTS performance_tier" );
        }

        // Also migrate the option from default_tier to default_model
        $old_tier = get_option( 'creator_default_tier' );
        if ( $old_tier !== false ) {
            // Map old tier to model (both map to gemini as default)
            update_option( 'creator_default_model', 'gemini' );
            delete_option( 'creator_default_tier' );
        }
    }
}
