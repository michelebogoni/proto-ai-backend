<?php
/**
 * Plugin Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package CreatorCore
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 *
 * Note: This will permanently delete all Creator data.
 * Tables and backups are removed only if the option is enabled.
 */

// Always delete activation redirect option on uninstall
// This ensures the wizard will show again on reinstall
delete_option( 'creator_do_activation_redirect' );
delete_option( 'creator_setup_completed' );

// Check if user wants to delete data on uninstall
$delete_data = get_option( 'creator_delete_data_on_uninstall', false );

if ( $delete_data ) {
    global $wpdb;

    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'creator_chats',
        $wpdb->prefix . 'creator_messages',
        $wpdb->prefix . 'creator_actions',
        $wpdb->prefix . 'creator_snapshots',
        $wpdb->prefix . 'creator_audit_log',
        $wpdb->prefix . 'creator_backups',
    ];

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Delete backup directory
    $backup_path = get_option( 'creator_backup_path' );
    if ( $backup_path && file_exists( $backup_path ) ) {
        creator_uninstall_delete_directory( $backup_path );
    }

    // Delete all options
    $options = [
        'creator_license_key',
        'creator_site_token',
        'creator_proxy_url',
        'creator_backup_path',
        'creator_backup_retention',
        'creator_max_backup_size_mb',
        'creator_debug_mode',
        'creator_log_level',
        'creator_allowed_roles',
        'creator_setup_completed',
        'creator_core_db_version',
        'creator_delete_data_on_uninstall',
    ];

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Delete transients
    delete_transient( 'creator_activation_redirect' );
    delete_transient( 'creator_license_status' );
    delete_transient( 'creator_detected_plugins' );
    delete_transient( 'creator_site_context' );

    // Remove custom role
    remove_role( 'creator_admin' );

    // Remove capabilities from administrator
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $admin_role->remove_cap( 'use_creator' );
        $admin_role->remove_cap( 'manage_creator_chats' );
        $admin_role->remove_cap( 'view_creator_audit' );
        $admin_role->remove_cap( 'manage_creator_backups' );
        $admin_role->remove_cap( 'manage_creator_settings' );
    }

    // Clear scheduled hooks
    wp_clear_scheduled_hook( 'creator_cleanup_backups' );
    wp_clear_scheduled_hook( 'creator_sync_license' );
}

/**
 * Recursively delete a directory
 *
 * @param string $dir Directory path.
 * @return bool
 */
function creator_uninstall_delete_directory( string $dir ): bool {
    if ( ! is_dir( $dir ) ) {
        return false;
    }

    $files = array_diff( scandir( $dir ), [ '.', '..' ] );

    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        if ( is_dir( $path ) ) {
            creator_uninstall_delete_directory( $path );
        } else {
            unlink( $path );
        }
    }

    return rmdir( $dir );
}
