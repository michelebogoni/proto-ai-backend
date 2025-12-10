<?php
/**
 * Audit Logger
 *
 * @package CreatorCore
 */

namespace CreatorCore\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuditLogger
 *
 * Handles logging of all plugin operations
 */
class AuditLogger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Status types
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';
    const STATUS_WARNING = 'warning';

    /**
     * Current log level
     *
     * @var string
     */
    private string $log_level;

    /**
     * Log level priorities
     *
     * @var array
     */
    private array $level_priority = [
        self::LEVEL_DEBUG   => 0,
        self::LEVEL_INFO    => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR   => 3,
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_level = get_option( 'creator_log_level', self::LEVEL_INFO );

        // Register hook listener for audit logging from other components
        add_action( 'creator_audit_log', [ $this, 'handle_audit_hook' ], 10, 2 );
    }

    /**
     * Handle audit log hook from other components
     *
     * @param string $action Action identifier.
     * @param array  $details Additional details.
     * @return void
     */
    public function handle_audit_hook( string $action, array $details = [] ): void {
        $this->log( $action, self::STATUS_SUCCESS, $details );
    }

    /**
     * Log an operation
     *
     * @param string      $action       Action identifier.
     * @param string      $status       Status (success, failure, warning).
     * @param array       $details      Additional details.
     * @param int|null    $operation_id Related operation ID.
     * @return int|false Log entry ID or false on failure.
     */
    public function log( string $action, string $status = self::STATUS_SUCCESS, array $details = [], ?int $operation_id = null ) {
        global $wpdb;

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $user_id = 0; // System action
        }

        $data = [
            'user_id'      => $user_id,
            'action'       => sanitize_text_field( $action ),
            'operation_id' => $operation_id,
            'details'      => wp_json_encode( $details ),
            'ip_address'   => $this->get_client_ip(),
            'status'       => sanitize_text_field( $status ),
            'created_at'   => current_time( 'mysql' ),
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_audit_log',
            $data,
            [ '%d', '%s', '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            $this->write_to_file( 'error', 'Failed to write audit log', $data );
            return false;
        }

        // Also write to file if debug mode is enabled
        if ( CREATOR_DEBUG ) {
            $this->write_to_file( self::LEVEL_INFO, $action, array_merge( $details, [ 'status' => $status ] ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Log a success operation
     *
     * @param string   $action       Action identifier.
     * @param array    $details      Additional details.
     * @param int|null $operation_id Related operation ID.
     * @return int|false
     */
    public function success( string $action, array $details = [], ?int $operation_id = null ) {
        return $this->log( $action, self::STATUS_SUCCESS, $details, $operation_id );
    }

    /**
     * Log a failure operation
     *
     * @param string   $action       Action identifier.
     * @param array    $details      Additional details.
     * @param int|null $operation_id Related operation ID.
     * @return int|false
     */
    public function failure( string $action, array $details = [], ?int $operation_id = null ) {
        return $this->log( $action, self::STATUS_FAILURE, $details, $operation_id );
    }

    /**
     * Log a warning
     *
     * @param string   $action       Action identifier.
     * @param array    $details      Additional details.
     * @param int|null $operation_id Related operation ID.
     * @return int|false
     */
    public function warning( string $action, array $details = [], ?int $operation_id = null ) {
        return $this->log( $action, self::STATUS_WARNING, $details, $operation_id );
    }

    /**
     * Get audit logs with pagination
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_logs( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'user_id'  => null,
            'action'   => null,
            'status'   => null,
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'from'     => null,
            'to'       => null,
        ];

        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = [ '1=1' ];
        $values = [];

        if ( $args['user_id'] !== null ) {
            $where[]  = 'user_id = %d';
            $values[] = $args['user_id'];
        }

        if ( $args['action'] !== null ) {
            $where[]  = 'action = %s';
            $values[] = $args['action'];
        }

        if ( $args['status'] !== null ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        if ( $args['from'] !== null ) {
            $where[]  = 'created_at >= %s';
            $values[] = $args['from'];
        }

        if ( $args['to'] !== null ) {
            $where[]  = 'created_at <= %s';
            $values[] = $args['to'];
        }

        $where_clause = implode( ' AND ', $where );
        $orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        $table = $wpdb->prefix . 'creator_audit_log';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Get results
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Parse JSON details
        foreach ( $results as &$row ) {
            $row['details'] = json_decode( $row['details'], true );
        }

        return [
            'items'      => $results,
            'total'      => $total,
            'page'       => $args['page'],
            'per_page'   => $args['per_page'],
            'total_pages' => ceil( $total / $args['per_page'] ),
        ];
    }

    /**
     * Get a single log entry
     *
     * @param int $id Log ID.
     * @return array|null
     */
    public function get_log( int $id ): ?array {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_audit_log WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( $result ) {
            $result['details'] = json_decode( $result['details'], true );
        }

        return $result;
    }

    /**
     * Get recent activity for a user
     *
     * @param int $user_id User ID.
     * @param int $limit   Number of entries.
     * @return array
     */
    public function get_user_activity( int $user_id, int $limit = 10 ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_audit_log
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        foreach ( $results as &$row ) {
            $row['details'] = json_decode( $row['details'], true );
        }

        return $results;
    }

    /**
     * Get statistics
     *
     * @param string $period Period (today, week, month, all).
     * @return array
     */
    public function get_stats( string $period = 'today' ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_audit_log';
        $where = '';

        switch ( $period ) {
            case 'today':
                $where = "WHERE DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_operations,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warnings,
                COUNT(DISTINCT user_id) as active_users
             FROM {$table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        // Get most common actions
        $top_actions = $wpdb->get_results(
            "SELECT action, COUNT(*) as count
             FROM {$table} {$where}
             GROUP BY action
             ORDER BY count DESC
             LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        $stats['top_actions'] = $top_actions;
        $stats['period']      = $period;

        return $stats;
    }

    /**
     * Clean old logs
     *
     * @param int $days Number of days to keep.
     * @return int Number of deleted rows.
     */
    public function cleanup( int $days = 90 ): int {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}creator_audit_log
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        if ( $result > 0 ) {
            $this->log( 'audit_cleanup', self::STATUS_SUCCESS, [
                'deleted_count' => $result,
                'retention_days' => $days,
            ]);
        }

        return $result;
    }

    /**
     * Write log to file (for debugging)
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     * @return void
     */
    private function write_to_file( string $level, string $message, array $context = [] ): void {
        if ( ! $this->should_log( $level ) ) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/creator-logs';

        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            file_put_contents( $log_dir . '/.htaccess', "Order deny,allow\nDeny from all" );
        }

        $log_file = $log_dir . '/creator-' . gmdate( 'Y-m-d' ) . '.log';
        $time     = gmdate( 'Y-m-d H:i:s' );

        $log_entry = sprintf(
            "[%s] [%s] %s %s\n",
            $time,
            strtoupper( $level ),
            $message,
            ! empty( $context ) ? wp_json_encode( $context ) : ''
        );

        error_log( $log_entry, 3, $log_file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    /**
     * Check if message should be logged based on level
     *
     * @param string $level Log level.
     * @return bool
     */
    private function should_log( string $level ): bool {
        $current_priority  = $this->level_priority[ $this->log_level ] ?? 1;
        $message_priority  = $this->level_priority[ $level ] ?? 1;

        return $message_priority >= $current_priority;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
