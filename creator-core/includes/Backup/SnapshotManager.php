<?php
/**
 * Snapshot Manager
 *
 * @package CreatorCore
 */

namespace CreatorCore\Backup;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class SnapshotManager
 *
 * Manages delta snapshots for rollback capability
 */
class SnapshotManager {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Backup base path
     *
     * @var string
     */
    private string $backup_path;

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger Audit logger instance.
     */
    public function __construct( ?AuditLogger $logger = null ) {
        $this->logger      = $logger ?? new AuditLogger();
        $this->backup_path = get_option( 'creator_backup_path', '' );

        if ( empty( $this->backup_path ) ) {
            $upload_dir        = wp_upload_dir();
            $this->backup_path = $upload_dir['basedir'] . '/creator-backups';
        }
    }

    /**
     * Create a snapshot for an operation
     *
     * @param int   $chat_id    Chat ID.
     * @param int   $message_id Message ID.
     * @param int   $action_id  Action ID.
     * @param array $operations Operations to snapshot.
     * @return int|false|\WP_Error Snapshot ID, false on failure, or WP_Error if directory not writable.
     */
    public function create_snapshot( int $chat_id, int $message_id, int $action_id, array $operations ) {
        global $wpdb;

        // Sanitize IDs
        $chat_id    = absint( $chat_id );
        $message_id = absint( $message_id );
        $action_id  = absint( $action_id );

        // Generate storage file path
        $date_folder = gmdate( 'Y-m-d' );
        $folder_path = wp_normalize_path( $this->backup_path . '/' . $date_folder . '/chat_' . $chat_id );

        if ( ! file_exists( $folder_path ) ) {
            wp_mkdir_p( $folder_path );
        }

        // Verify directory is writable
        if ( ! is_writable( $folder_path ) ) {
            $this->logger->failure( 'snapshot_directory_not_writable', [
                'folder_path' => $folder_path,
            ]);
            return new \WP_Error(
                'backup_directory_not_writable',
                sprintf(
                    /* translators: %s: Directory path */
                    __( 'Backup directory is not writable: %s', 'creator-core' ),
                    $folder_path
                ),
                [ 'status' => 500 ]
            );
        }

        $filename     = 'snapshot_msg_' . absint( $message_id ) . '_' . time() . '.json';
        $storage_file = wp_normalize_path( $folder_path . '/' . $filename );

        // Prepare snapshot data
        $snapshot_data = [
            'snapshot_id'  => null, // Will be set after DB insert
            'chat_id'      => $chat_id,
            'message_id'   => $message_id,
            'action_id'    => $action_id,
            'timestamp'    => gmdate( 'c' ),
            'operations'   => $operations,
            'rollback_instructions' => $this->generate_rollback_instructions( $operations ),
        ];

        // Insert snapshot record
        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_snapshots',
            [
                'chat_id'       => $chat_id,
                'message_id'    => $message_id,
                'action_id'     => $action_id,
                'snapshot_type' => 'DELTA',
                'operations'    => wp_json_encode( $operations ),
                'storage_file'  => $storage_file,
                'storage_size_kb' => 0,
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( $result === false ) {
            $this->logger->failure( 'snapshot_create_failed', [
                'chat_id'    => $chat_id,
                'message_id' => $message_id,
                'error'      => $wpdb->last_error,
            ]);
            return false;
        }

        $snapshot_id = $wpdb->insert_id;
        $snapshot_data['snapshot_id'] = $snapshot_id;

        // Save to file
        $json_content = wp_json_encode( $snapshot_data, JSON_PRETTY_PRINT );
        $file_result  = file_put_contents( $storage_file, $json_content );

        if ( $file_result === false ) {
            $this->logger->warning( 'snapshot_file_failed', [
                'snapshot_id'  => $snapshot_id,
                'storage_file' => $storage_file,
            ]);
        } else {
            // Update file size
            $wpdb->update(
                $wpdb->prefix . 'creator_snapshots',
                [ 'storage_size_kb' => round( $file_result / 1024 ) ],
                [ 'id' => $snapshot_id ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        $this->logger->success( 'snapshot_created', [
            'snapshot_id' => $snapshot_id,
            'chat_id'     => $chat_id,
            'operations'  => count( $operations ),
        ]);

        return $snapshot_id;
    }

    /**
     * Get snapshot by ID
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null
     */
    public function get_snapshot( int $snapshot_id ): ?array {
        global $wpdb;

        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_snapshots WHERE id = %d AND deleted = 0",
                $snapshot_id
            ),
            ARRAY_A
        );

        if ( ! $snapshot ) {
            return null;
        }

        $snapshot['operations'] = json_decode( $snapshot['operations'], true );

        // Load full data from file if available
        if ( ! empty( $snapshot['storage_file'] ) && file_exists( $snapshot['storage_file'] ) ) {
            $file_content = file_get_contents( $snapshot['storage_file'] );
            $file_data    = json_decode( $file_content, true );

            if ( $file_data ) {
                $snapshot['file_data'] = $file_data;
            }
        }

        return $snapshot;
    }

    /**
     * Get snapshots for a chat
     *
     * @param int $chat_id Chat ID.
     * @return array
     */
    public function get_chat_snapshots( int $chat_id ): array {
        global $wpdb;

        $snapshots = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_snapshots
                 WHERE chat_id = %d AND deleted = 0
                 ORDER BY created_at DESC",
                $chat_id
            ),
            ARRAY_A
        );

        foreach ( $snapshots as &$snapshot ) {
            $snapshot['operations'] = json_decode( $snapshot['operations'], true );
        }

        return $snapshots;
    }

    /**
     * Get snapshot for an action
     *
     * @param int $action_id Action ID.
     * @return array|null
     */
    public function get_action_snapshot( int $action_id ): ?array {
        global $wpdb;

        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_snapshots
                 WHERE action_id = %d AND deleted = 0",
                $action_id
            ),
            ARRAY_A
        );

        if ( $snapshot ) {
            $snapshot['operations'] = json_decode( $snapshot['operations'], true );
        }

        return $snapshot;
    }

    /**
     * Get snapshot for a message
     *
     * @param int $message_id Message ID.
     * @return array|null
     */
    public function get_message_snapshot( int $message_id ): ?array {
        global $wpdb;

        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_snapshots
                 WHERE message_id = %d AND deleted = 0
                 ORDER BY created_at DESC
                 LIMIT 1",
                $message_id
            ),
            ARRAY_A
        );

        if ( ! $snapshot ) {
            return null;
        }

        $snapshot['operations'] = json_decode( $snapshot['operations'], true );

        // Load full data from file if available
        if ( ! empty( $snapshot['storage_file'] ) && file_exists( $snapshot['storage_file'] ) ) {
            $file_content = file_get_contents( $snapshot['storage_file'] );
            $file_data    = json_decode( $file_content, true );

            if ( $file_data ) {
                $snapshot['file_data'] = $file_data;
            }
        }

        return $snapshot;
    }

    /**
     * Generate rollback instructions for operations
     *
     * @param array $operations Operations array.
     * @return array
     */
    private function generate_rollback_instructions( array $operations ): array {
        $instructions = [];

        foreach ( $operations as $operation ) {
            $type = $operation['type'] ?? '';

            switch ( $type ) {
                case 'create_post':
                case 'create_page':
                    if ( isset( $operation['after']['post_id'] ) ) {
                        $instructions[] = [
                            'action' => 'delete_post',
                            'post_id' => $operation['after']['post_id'],
                        ];
                    }
                    break;

                case 'update_post':
                case 'update_page':
                    if ( isset( $operation['before'] ) ) {
                        $instructions[] = [
                            'action' => 'restore_post',
                            'post_id' => $operation['target'],
                            'data'   => $operation['before'],
                        ];
                    }
                    break;

                case 'delete_post':
                    if ( isset( $operation['before'] ) ) {
                        $instructions[] = [
                            'action' => 'recreate_post',
                            'data'   => $operation['before'],
                        ];
                    }
                    break;

                case 'update_meta':
                    $instructions[] = [
                        'action'    => 'restore_meta',
                        'object_id' => $operation['target'],
                        'meta_key'  => $operation['meta_key'] ?? '',
                        'old_value' => $operation['before'] ?? null,
                    ];
                    break;

                case 'add_elementor_widget':
                    if ( isset( $operation['before'] ) ) {
                        $instructions[] = [
                            'action'  => 'restore_elementor',
                            'post_id' => $operation['target'],
                            'data'    => $operation['before'],
                        ];
                    }
                    break;

                case 'create_acf_field':
                    if ( isset( $operation['after']['field_key'] ) ) {
                        $instructions[] = [
                            'action'    => 'delete_acf_field',
                            'field_key' => $operation['after']['field_key'],
                        ];
                    }
                    break;

                case 'custom_file_modify':
                    // Restore custom code file from before state
                    if ( isset( $operation['before']['file'] ) ) {
                        $instructions[] = [
                            'action'   => 'restore_custom_file',
                            'type'     => $operation['target'] ?? '',
                            'file'     => $operation['before']['file'],
                            'manifest' => $operation['before']['manifest'] ?? null,
                        ];
                    }
                    break;

                default:
                    // Generic instruction for unknown types
                    if ( isset( $operation['before'] ) ) {
                        $instructions[] = [
                            'action' => 'generic_restore',
                            'type'   => $type,
                            'target' => $operation['target'] ?? null,
                            'data'   => $operation['before'],
                        ];
                    }
                    break;
            }
        }

        return $instructions;
    }

    /**
     * Mark snapshot as deleted (soft delete)
     *
     * @param int $snapshot_id Snapshot ID.
     * @return bool
     */
    public function delete_snapshot( int $snapshot_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_snapshots',
            [
                'deleted'    => 1,
                'deleted_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $snapshot_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            $this->logger->success( 'snapshot_deleted', [ 'snapshot_id' => $snapshot_id ] );
        }

        return $result !== false;
    }

    /**
     * Get total backup size for a chat
     *
     * @param int $chat_id Chat ID.
     * @return int Size in KB.
     */
    public function get_chat_backup_size( int $chat_id ): int {
        global $wpdb;

        $size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(storage_size_kb) FROM {$wpdb->prefix}creator_snapshots
                 WHERE chat_id = %d AND deleted = 0",
                $chat_id
            )
        );

        return (int) $size;
    }

    /**
     * Get total backup statistics
     *
     * @return array
     */
    public function get_backup_stats(): array {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_snapshots,
                SUM(storage_size_kb) as total_size_kb,
                COUNT(DISTINCT chat_id) as total_chats
             FROM {$wpdb->prefix}creator_snapshots
             WHERE deleted = 0",
            ARRAY_A
        );

        // Get directory size
        $dir_size = 0;
        if ( is_dir( $this->backup_path ) ) {
            $dir_size = $this->get_directory_size( $this->backup_path );
        }

        return [
            'total_snapshots' => (int) ( $stats['total_snapshots'] ?? 0 ),
            'total_size_kb'   => (int) ( $stats['total_size_kb'] ?? 0 ),
            'total_size_mb'   => round( ( $stats['total_size_kb'] ?? 0 ) / 1024, 2 ),
            'total_chats'     => (int) ( $stats['total_chats'] ?? 0 ),
            'directory_size'  => $dir_size,
            'backup_path'     => $this->backup_path,
        ];
    }

    /**
     * Get directory size recursively
     *
     * @param string $path Directory path.
     * @return int Size in bytes.
     */
    private function get_directory_size( string $path ): int {
        $size = 0;

        if ( ! is_dir( $path ) ) {
            return $size;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Cleanup old snapshots
     *
     * @param int $days Days to keep.
     * @return int Number of deleted snapshots.
     */
    public function cleanup_old_snapshots( int $days = 30 ): int {
        global $wpdb;

        // Get old snapshots
        $old_snapshots = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, storage_file FROM {$wpdb->prefix}creator_snapshots
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ),
            ARRAY_A
        );

        $deleted_count = 0;

        foreach ( $old_snapshots as $snapshot ) {
            // Delete file
            if ( ! empty( $snapshot['storage_file'] ) && file_exists( $snapshot['storage_file'] ) ) {
                unlink( $snapshot['storage_file'] );
            }

            // Delete record
            $wpdb->delete(
                $wpdb->prefix . 'creator_snapshots',
                [ 'id' => $snapshot['id'] ],
                [ '%d' ]
            );

            $deleted_count++;
        }

        // Clean up empty directories
        $this->cleanup_empty_directories( $this->backup_path );

        if ( $deleted_count > 0 ) {
            $this->logger->success( 'snapshots_cleanup', [
                'deleted_count'  => $deleted_count,
                'retention_days' => $days,
            ]);
        }

        return $deleted_count;
    }

    /**
     * Clean up empty directories
     *
     * @param string $path Base path.
     * @return void
     */
    private function cleanup_empty_directories( string $path ): void {
        if ( ! is_dir( $path ) ) {
            return;
        }

        $files = array_diff( scandir( $path ), [ '.', '..', '.htaccess', 'index.php' ] );

        foreach ( $files as $file ) {
            $full_path = $path . '/' . $file;
            if ( is_dir( $full_path ) ) {
                $this->cleanup_empty_directories( $full_path );

                // Check if directory is empty
                $contents = array_diff( scandir( $full_path ), [ '.', '..' ] );
                if ( empty( $contents ) ) {
                    rmdir( $full_path );
                }
            }
        }
    }

    /**
     * Get backup path
     *
     * @return string
     */
    public function get_backup_path(): string {
        return $this->backup_path;
    }

    /**
     * Enforce max size limit by deleting oldest snapshots
     *
     * @param int $max_size_mb Maximum size in MB.
     * @return int Number of deleted snapshots.
     */
    public function enforce_size_limit( int $max_size_mb ): int {
        global $wpdb;

        $max_size_kb = $max_size_mb * 1024;
        $deleted_count = 0;

        // Get current total size
        $current_size_kb = (int) $wpdb->get_var(
            "SELECT SUM(storage_size_kb) FROM {$wpdb->prefix}creator_snapshots WHERE deleted = 0"
        );

        // If under limit, nothing to do
        if ( $current_size_kb <= $max_size_kb ) {
            return 0;
        }

        // Get oldest snapshots first
        $snapshots = $wpdb->get_results(
            "SELECT id, storage_file, storage_size_kb FROM {$wpdb->prefix}creator_snapshots
             WHERE deleted = 0
             ORDER BY created_at ASC",
            ARRAY_A
        );

        foreach ( $snapshots as $snapshot ) {
            // Stop if we're under the limit
            if ( $current_size_kb <= $max_size_kb ) {
                break;
            }

            // Delete file
            if ( ! empty( $snapshot['storage_file'] ) && file_exists( $snapshot['storage_file'] ) ) {
                unlink( $snapshot['storage_file'] );
            }

            // Delete record
            $wpdb->delete(
                $wpdb->prefix . 'creator_snapshots',
                [ 'id' => $snapshot['id'] ],
                [ '%d' ]
            );

            $current_size_kb -= (int) $snapshot['storage_size_kb'];
            $deleted_count++;
        }

        // Clean up empty directories
        if ( $deleted_count > 0 ) {
            $this->cleanup_empty_directories( $this->backup_path );

            $this->logger->success( 'snapshots_size_cleanup', [
                'deleted_count' => $deleted_count,
                'max_size_mb'   => $max_size_mb,
            ]);
        }

        return $deleted_count;
    }
}
