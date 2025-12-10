<?php
/**
 * Admin Dashboard
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\PluginDetector;
use CreatorCore\Audit\AuditLogger;
use CreatorCore\Chat\ChatInterface;
use CreatorCore\Backup\SnapshotManager;

/**
 * Class Dashboard
 *
 * Handles the admin dashboard display
 */
class Dashboard {

    /**
     * Plugin detector instance
     *
     * @var PluginDetector
     */
    private PluginDetector $plugin_detector;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Constructor
     *
     * @param PluginDetector $plugin_detector Plugin detector instance.
     * @param AuditLogger    $logger          Audit logger instance.
     */
    public function __construct( PluginDetector $plugin_detector, AuditLogger $logger ) {
        $this->plugin_detector = $plugin_detector;
        $this->logger          = $logger;
    }

    /**
     * Render the dashboard
     *
     * @return void
     */
    public function render(): void {
        $data = $this->get_dashboard_data();
        include CREATOR_CORE_PATH . 'templates/admin-dashboard.php';
    }

    /**
     * Get dashboard data
     *
     * @return array
     */
    public function get_dashboard_data(): array {
        return [
            'recent_chats'     => $this->get_recent_chats(),
            'stats'            => $this->get_stats(),
            'integrations'     => $this->plugin_detector->get_all_integrations(),
            'recent_activity'  => $this->get_recent_activity(),
            'license_status'   => $this->get_license_status(),
            'quick_actions'    => $this->get_quick_actions(),
        ];
    }

    /**
     * Get recent chats
     *
     * @return array
     */
    private function get_recent_chats(): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*,
                        (SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages WHERE chat_id = c.id) as message_count
                 FROM {$wpdb->prefix}creator_chats c
                 WHERE c.user_id = %d AND c.status = 'active'
                 ORDER BY c.updated_at DESC
                 LIMIT 5",
                get_current_user_id()
            ),
            ARRAY_A
        );
    }

    /**
     * Get statistics
     *
     * @return array
     */
    private function get_stats(): array {
        global $wpdb;

        $user_id = get_current_user_id();

        // Token usage (from proxy or mock)
        $usage = get_transient( 'creator_license_status' );
        $tokens_used = $usage['usage']['tokens_used'] ?? 0;

        // Actions count
        $actions_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}creator_actions a
                 JOIN {$wpdb->prefix}creator_messages m ON a.message_id = m.id
                 JOIN {$wpdb->prefix}creator_chats c ON m.chat_id = c.id
                 WHERE c.user_id = %d AND a.status = 'completed'",
                $user_id
            )
        );

        // Backup size
        $snapshot_manager = new SnapshotManager();
        $backup_stats     = $snapshot_manager->get_backup_stats();

        // Last action
        $last_action = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, m.chat_id FROM {$wpdb->prefix}creator_actions a
                 JOIN {$wpdb->prefix}creator_messages m ON a.message_id = m.id
                 JOIN {$wpdb->prefix}creator_chats c ON m.chat_id = c.id
                 WHERE c.user_id = %d
                 ORDER BY a.created_at DESC
                 LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        return [
            'tokens_used'       => $tokens_used,
            'tokens_formatted'  => number_format( $tokens_used ),
            'actions_completed' => (int) $actions_count,
            'backup_size_mb'    => $backup_stats['total_size_mb'],
            'backup_size'       => $this->format_size( $backup_stats['total_size_kb'] * 1024 ),
            'last_action'       => $last_action,
            'last_action_time'  => $last_action ? $this->time_ago( $last_action['created_at'] ) : __( 'Never', 'creator-core' ),
        ];
    }

    /**
     * Get recent activity
     *
     * @return array
     */
    private function get_recent_activity(): array {
        $logs = $this->logger->get_logs( [
            'user_id'  => get_current_user_id(),
            'per_page' => 10,
        ]);

        return $logs['items'] ?? [];
    }

    /**
     * Get license status
     *
     * @return array
     */
    private function get_license_status(): array {
        $status = get_transient( 'creator_license_status' );

        if ( ! $status ) {
            return [
                'valid'      => false,
                'plan'       => 'none',
                'expires_at' => null,
            ];
        }

        return [
            'valid'      => $status['success'] ?? false,
            'plan'       => $status['plan'] ?? 'unknown',
            'expires_at' => $status['expires_at'] ?? null,
            'features'   => $status['features'] ?? [],
        ];
    }

    /**
     * Get quick actions
     *
     * @return array
     */
    private function get_quick_actions(): array {
        return [
            [
                'label' => __( 'New Chat', 'creator-core' ),
                'url'   => admin_url( 'admin.php?page=creator-chat' ),
                'icon'  => 'dashicons-format-chat',
            ],
            [
                'label' => __( 'Create Page', 'creator-core' ),
                'url'   => admin_url( 'admin.php?page=creator-chat&action=create_page' ),
                'icon'  => 'dashicons-admin-page',
            ],
            [
                'label' => __( 'Create Post', 'creator-core' ),
                'url'   => admin_url( 'admin.php?page=creator-chat&action=create_post' ),
                'icon'  => 'dashicons-admin-post',
            ],
            [
                'label' => __( 'Settings', 'creator-core' ),
                'url'   => admin_url( 'admin.php?page=creator-settings' ),
                'icon'  => 'dashicons-admin-generic',
            ],
        ];
    }

    /**
     * Format file size
     *
     * @param int $bytes Size in bytes.
     * @return string
     */
    private function format_size( int $bytes ): string {
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $i     = 0;

        while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
            $bytes /= 1024;
            $i++;
        }

        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }

    /**
     * Format time ago
     *
     * @param string $datetime MySQL datetime.
     * @return string
     */
    private function time_ago( string $datetime ): string {
        $timestamp = strtotime( $datetime );
        $diff      = current_time( 'timestamp' ) - $timestamp;

        if ( $diff < 60 ) {
            return __( 'Just now', 'creator-core' );
        }

        if ( $diff < 3600 ) {
            $minutes = floor( $diff / 60 );
            return sprintf(
                /* translators: %d: Number of minutes */
                _n( '%d min ago', '%d mins ago', $minutes, 'creator-core' ),
                $minutes
            );
        }

        if ( $diff < 86400 ) {
            $hours = floor( $diff / 3600 );
            return sprintf(
                /* translators: %d: Number of hours */
                _n( '%d hour ago', '%d hours ago', $hours, 'creator-core' ),
                $hours
            );
        }

        $days = floor( $diff / 86400 );
        return sprintf(
            /* translators: %d: Number of days */
            _n( '%d day ago', '%d days ago', $days, 'creator-core' ),
            $days
        );
    }
}
