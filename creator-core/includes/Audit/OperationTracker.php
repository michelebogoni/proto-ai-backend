<?php
/**
 * Operation Tracker
 *
 * @package CreatorCore
 */

namespace CreatorCore\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Class OperationTracker
 *
 * Tracks individual operations within a chat session
 */
class OperationTracker {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Current operation ID
     *
     * @var int|null
     */
    private ?int $current_operation_id = null;

    /**
     * Operation start time
     *
     * @var float|null
     */
    private ?float $start_time = null;

    /**
     * Steps in current operation
     *
     * @var array
     */
    private array $steps = [];

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger Audit logger instance.
     */
    public function __construct( ?AuditLogger $logger = null ) {
        $this->logger = $logger ?? new AuditLogger();
    }

    /**
     * Start tracking an operation
     *
     * @param string $action_type Action type.
     * @param string $target      Target identifier.
     * @param int    $message_id  Related message ID.
     * @return int|false Operation ID or false on failure.
     */
    public function start_operation( string $action_type, string $target, int $message_id ) {
        global $wpdb;

        $this->start_time = microtime( true );
        $this->steps      = [];

        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_actions',
            [
                'message_id'  => $message_id,
                'action_type' => sanitize_text_field( $action_type ),
                'target'      => sanitize_text_field( $target ),
                'status'      => 'executing',
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return false;
        }

        $this->current_operation_id = $wpdb->insert_id;

        $this->logger->log( 'operation_started', AuditLogger::STATUS_SUCCESS, [
            'operation_id' => $this->current_operation_id,
            'action_type'  => $action_type,
            'target'       => $target,
            'message_id'   => $message_id,
        ], $this->current_operation_id );

        return $this->current_operation_id;
    }

    /**
     * Add a step to the current operation
     *
     * @param string $step_name   Step name.
     * @param array  $step_data   Step data.
     * @param string $status      Step status.
     * @return void
     */
    public function add_step( string $step_name, array $step_data = [], string $status = 'completed' ): void {
        $this->steps[] = [
            'name'      => $step_name,
            'data'      => $step_data,
            'status'    => $status,
            'timestamp' => current_time( 'mysql' ),
        ];
    }

    /**
     * Complete the current operation successfully
     *
     * @param int|null $snapshot_id Related snapshot ID.
     * @param array    $result_data Result data.
     * @return bool
     */
    public function complete_operation( ?int $snapshot_id = null, array $result_data = [] ): bool {
        if ( ! $this->current_operation_id ) {
            return false;
        }

        global $wpdb;

        $duration = $this->start_time ? round( microtime( true ) - $this->start_time, 3 ) : 0;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_actions',
            [
                'status'       => 'completed',
                'snapshot_id'  => $snapshot_id,
                'completed_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $this->current_operation_id ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );

        $this->logger->log( 'operation_completed', AuditLogger::STATUS_SUCCESS, [
            'operation_id' => $this->current_operation_id,
            'duration_sec' => $duration,
            'steps_count'  => count( $this->steps ),
            'steps'        => $this->steps,
            'result'       => $result_data,
            'snapshot_id'  => $snapshot_id,
        ], $this->current_operation_id );

        $this->reset();

        return $result !== false;
    }

    /**
     * Fail the current operation
     *
     * @param string $error_message Error message.
     * @param array  $error_data    Additional error data.
     * @return bool
     */
    public function fail_operation( string $error_message, array $error_data = [] ): bool {
        if ( ! $this->current_operation_id ) {
            return false;
        }

        global $wpdb;

        $duration = $this->start_time ? round( microtime( true ) - $this->start_time, 3 ) : 0;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_actions',
            [
                'status'        => 'failed',
                'error_message' => sanitize_textarea_field( $error_message ),
                'completed_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $this->current_operation_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        $this->logger->log( 'operation_failed', AuditLogger::STATUS_FAILURE, [
            'operation_id'  => $this->current_operation_id,
            'error_message' => $error_message,
            'error_data'    => $error_data,
            'duration_sec'  => $duration,
            'steps_count'   => count( $this->steps ),
            'steps'         => $this->steps,
        ], $this->current_operation_id );

        $this->reset();

        return $result !== false;
    }

    /**
     * Get operation by ID
     *
     * @param int $operation_id Operation ID.
     * @return array|null
     */
    public function get_operation( int $operation_id ): ?array {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_actions WHERE id = %d",
                $operation_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get operations for a message
     *
     * @param int $message_id Message ID.
     * @return array
     */
    public function get_message_operations( int $message_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_actions
                 WHERE message_id = %d
                 ORDER BY created_at ASC",
                $message_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get pending operations
     *
     * @return array
     */
    public function get_pending_operations(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}creator_actions
             WHERE status IN ('pending', 'executing')
             ORDER BY created_at ASC",
            ARRAY_A
        );
    }

    /**
     * Get operation statistics
     *
     * @param string $period Period (today, week, month).
     * @return array
     */
    public function get_stats( string $period = 'today' ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_actions';
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

        return $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'executing' THEN 1 ELSE 0 END) as executing
             FROM {$table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
    }

    /**
     * Get current operation ID
     *
     * @return int|null
     */
    public function get_current_operation_id(): ?int {
        return $this->current_operation_id;
    }

    /**
     * Reset tracker state
     *
     * @return void
     */
    private function reset(): void {
        $this->current_operation_id = null;
        $this->start_time           = null;
        $this->steps                = [];
    }
}
