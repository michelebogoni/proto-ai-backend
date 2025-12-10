<?php
/**
 * Plugin Deactivator
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 *
 * Handles plugin deactivation tasks
 */
class Deactivator {

    /**
     * Deactivate the plugin
     *
     * @return void
     */
    public static function deactivate(): void {
        self::clear_scheduled_hooks();
        self::clear_transients();

        // Log deactivation (if logger is available)
        if ( class_exists( '\CreatorCore\Audit\AuditLogger' ) ) {
            $logger = new \CreatorCore\Audit\AuditLogger();
            $logger->log( 'plugin_deactivated', 'success', [
                'version' => CREATOR_CORE_VERSION,
            ]);
        }

        // Clear cache
        wp_cache_flush();
    }

    /**
     * Clear scheduled hooks
     *
     * @return void
     */
    private static function clear_scheduled_hooks(): void {
        wp_clear_scheduled_hook( 'creator_cleanup_backups' );
        wp_clear_scheduled_hook( 'creator_sync_license' );
    }

    /**
     * Clear plugin transients
     *
     * @return void
     */
    private static function clear_transients(): void {
        delete_transient( 'creator_activation_redirect' );
        delete_transient( 'creator_license_status' );
        delete_transient( 'creator_detected_plugins' );
        delete_transient( 'creator_site_context' );
    }
}
