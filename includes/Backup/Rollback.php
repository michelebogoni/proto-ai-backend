<?php
/**
 * Rollback Manager
 *
 * @package CreatorCore
 */

namespace CreatorCore\Backup;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;
use CreatorCore\Executor\CustomFileManager;
use CreatorCore\Executor\CodeExecutor;

/**
 * Class Rollback
 *
 * Handles rollback operations from snapshots
 */
class Rollback {

    /**
     * Snapshot manager instance
     *
     * @var SnapshotManager
     */
    private SnapshotManager $snapshot_manager;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Delta backup instance
     *
     * @var DeltaBackup
     */
    private DeltaBackup $delta_backup;

    /**
     * Constructor
     *
     * @param SnapshotManager|null $snapshot_manager Snapshot manager instance.
     * @param AuditLogger|null     $logger           Audit logger instance.
     */
    public function __construct( ?SnapshotManager $snapshot_manager = null, ?AuditLogger $logger = null ) {
        $this->snapshot_manager = $snapshot_manager ?? new SnapshotManager();
        $this->logger           = $logger ?? new AuditLogger();
        $this->delta_backup     = new DeltaBackup();
    }

    /**
     * Rollback a specific snapshot
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Result with success status and details.
     */
    public function rollback_snapshot( int $snapshot_id ): array {
        $snapshot = $this->snapshot_manager->get_snapshot( $snapshot_id );

        if ( ! $snapshot ) {
            return [
                'success' => false,
                'error'   => __( 'Snapshot not found', 'creator-core' ),
            ];
        }

        $operations = $snapshot['operations'] ?? [];
        $results    = [];
        $errors     = [];

        // Process operations in reverse order
        $operations = array_reverse( $operations );

        foreach ( $operations as $operation ) {
            $result = $this->rollback_operation( $operation );

            if ( $result['success'] ) {
                $results[] = $result;
            } else {
                $errors[] = $result;
            }
        }

        // Mark snapshot as rolled back
        $this->snapshot_manager->delete_snapshot( $snapshot_id );

        $success = empty( $errors );

        $this->logger->log(
            'rollback_completed',
            $success ? AuditLogger::STATUS_SUCCESS : AuditLogger::STATUS_WARNING,
            [
                'snapshot_id'  => $snapshot_id,
                'operations'   => count( $operations ),
                'successful'   => count( $results ),
                'failed'       => count( $errors ),
            ]
        );

        return [
            'success'    => $success,
            'results'    => $results,
            'errors'     => $errors,
            'message'    => $success
                ? __( 'Rollback completed successfully', 'creator-core' )
                : sprintf(
                    /* translators: %d: Number of errors */
                    __( 'Rollback completed with %d errors', 'creator-core' ),
                    count( $errors )
                ),
        ];
    }

    /**
     * Rollback a single operation
     *
     * @param array $operation Operation data.
     * @return array
     */
    public function rollback_operation( array $operation ): array {
        $type = $operation['type'] ?? '';

        switch ( $type ) {
            case 'create_post':
            case 'create_page':
                return $this->rollback_create_post( $operation );

            case 'update_post':
            case 'update_page':
                return $this->rollback_update_post( $operation );

            case 'delete_post':
                return $this->rollback_delete_post( $operation );

            case 'update_meta':
                return $this->rollback_update_meta( $operation );

            case 'add_elementor_widget':
            case 'update_elementor':
                return $this->rollback_elementor( $operation );

            case 'create_acf_field':
            case 'update_acf_field':
                return $this->rollback_acf_field( $operation );

            case 'update_option':
                return $this->rollback_option( $operation );

            case 'create_term':
                return $this->rollback_create_term( $operation );

            case 'custom_file_modify':
                return $this->rollback_custom_file( $operation );

            case 'wpcode_snippet':
                return $this->rollback_wpcode_snippet( $operation );

            default:
                return $this->rollback_generic( $operation );
        }
    }

    /**
     * Rollback post creation (delete the created post)
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_create_post( array $operation ): array {
        $post_id = $operation['after']['post_id'] ?? $operation['target'] ?? null;

        if ( ! $post_id ) {
            return [
                'success' => false,
                'error'   => __( 'No post ID found in operation', 'creator-core' ),
            ];
        }

        $result = wp_delete_post( $post_id, true );

        if ( $result ) {
            return [
                'success'   => true,
                'operation' => 'delete_post',
                'post_id'   => $post_id,
            ];
        }

        return [
            'success' => false,
            'error'   => sprintf(
                /* translators: %d: Post ID */
                __( 'Failed to delete post %d', 'creator-core' ),
                $post_id
            ),
        ];
    }

    /**
     * Rollback post update (restore previous state)
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_update_post( array $operation ): array {
        $before  = $operation['before'] ?? null;
        $post_id = $operation['target'] ?? $before['post_id'] ?? null;

        if ( ! $before || ! $post_id ) {
            return [
                'success' => false,
                'error'   => __( 'No previous state to restore', 'creator-core' ),
            ];
        }

        // Restore post data
        $post_data = [
            'ID'           => $post_id,
            'post_title'   => $before['post_title'] ?? '',
            'post_content' => $before['post_content'] ?? '',
            'post_excerpt' => $before['post_excerpt'] ?? '',
            'post_status'  => $before['post_status'] ?? 'draft',
            'post_name'    => $before['post_name'] ?? '',
            'menu_order'   => $before['menu_order'] ?? 0,
        ];

        $result = wp_update_post( $post_data, true );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        // Restore meta
        if ( isset( $before['meta'] ) ) {
            // Delete current meta
            $current_meta = get_post_meta( $post_id );
            foreach ( $current_meta as $key => $values ) {
                delete_post_meta( $post_id, $key );
            }

            // Restore old meta
            foreach ( $before['meta'] as $key => $values ) {
                foreach ( $values as $value ) {
                    add_post_meta( $post_id, $key, maybe_unserialize( $value ) );
                }
            }
        }

        // Restore taxonomies
        if ( isset( $before['taxonomies'] ) ) {
            foreach ( $before['taxonomies'] as $taxonomy => $term_ids ) {
                wp_set_object_terms( $post_id, $term_ids, $taxonomy );
            }
        }

        // Restore Elementor data
        if ( isset( $before['elementor_data'] ) ) {
            update_post_meta( $post_id, '_elementor_data', $before['elementor_data'] );
        }

        return [
            'success'   => true,
            'operation' => 'restore_post',
            'post_id'   => $post_id,
        ];
    }

    /**
     * Rollback post deletion (recreate the post)
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_delete_post( array $operation ): array {
        $before = $operation['before'] ?? null;

        if ( ! $before ) {
            return [
                'success' => false,
                'error'   => __( 'No previous state to restore', 'creator-core' ),
            ];
        }

        // Create post with specific ID if possible
        $post_data = [
            'post_title'   => $before['post_title'] ?? '',
            'post_content' => $before['post_content'] ?? '',
            'post_excerpt' => $before['post_excerpt'] ?? '',
            'post_status'  => $before['post_status'] ?? 'draft',
            'post_type'    => $before['post_type'] ?? 'post',
            'post_author'  => $before['post_author'] ?? get_current_user_id(),
            'post_name'    => $before['post_name'] ?? '',
            'menu_order'   => $before['menu_order'] ?? 0,
        ];

        $new_post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $new_post_id ) ) {
            return [
                'success' => false,
                'error'   => $new_post_id->get_error_message(),
            ];
        }

        // Restore meta
        if ( isset( $before['meta'] ) ) {
            foreach ( $before['meta'] as $key => $values ) {
                foreach ( $values as $value ) {
                    add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
                }
            }
        }

        return [
            'success'     => true,
            'operation'   => 'recreate_post',
            'new_post_id' => $new_post_id,
            'old_post_id' => $before['post_id'] ?? null,
        ];
    }

    /**
     * Rollback meta update
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_update_meta( array $operation ): array {
        $object_id = $operation['target'] ?? null;
        $meta_key  = $operation['meta_key'] ?? null;
        $old_value = $operation['before'] ?? null;

        if ( ! $object_id || ! $meta_key ) {
            return [
                'success' => false,
                'error'   => __( 'Invalid meta operation', 'creator-core' ),
            ];
        }

        if ( $old_value === null ) {
            delete_post_meta( $object_id, $meta_key );
        } else {
            update_post_meta( $object_id, $meta_key, $old_value );
        }

        return [
            'success'   => true,
            'operation' => 'restore_meta',
            'object_id' => $object_id,
            'meta_key'  => $meta_key,
        ];
    }

    /**
     * Rollback Elementor changes
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_elementor( array $operation ): array {
        $post_id = $operation['target'] ?? null;
        $before  = $operation['before'] ?? null;

        if ( ! $post_id ) {
            return [
                'success' => false,
                'error'   => __( 'No post ID found', 'creator-core' ),
            ];
        }

        if ( isset( $before['elementor_data'] ) ) {
            update_post_meta( $post_id, '_elementor_data', $before['elementor_data'] );
        } elseif ( $before === null ) {
            delete_post_meta( $post_id, '_elementor_data' );
        }

        // Clear Elementor cache
        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return [
            'success'   => true,
            'operation' => 'restore_elementor',
            'post_id'   => $post_id,
        ];
    }

    /**
     * Rollback ACF field changes
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_acf_field( array $operation ): array {
        if ( ! function_exists( 'acf_delete_field' ) ) {
            return [
                'success' => false,
                'error'   => __( 'ACF not available', 'creator-core' ),
            ];
        }

        $type = $operation['type'] ?? '';

        if ( $type === 'create_acf_field' ) {
            // Delete the created field
            $field_key = $operation['after']['field_key'] ?? $operation['after']['key'] ?? null;
            if ( $field_key ) {
                acf_delete_field( $field_key );
                return [
                    'success'   => true,
                    'operation' => 'delete_acf_field',
                    'field_key' => $field_key,
                ];
            }
        }

        // Restore previous state
        $before = $operation['before'] ?? null;
        if ( $before ) {
            acf_update_field( $before );
            return [
                'success'   => true,
                'operation' => 'restore_acf_field',
            ];
        }

        return [
            'success' => false,
            'error'   => __( 'Unable to rollback ACF field', 'creator-core' ),
        ];
    }

    /**
     * Rollback option change
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_option( array $operation ): array {
        $option_name = $operation['target'] ?? $operation['before']['option_name'] ?? null;
        $old_value   = $operation['before']['option_value'] ?? null;

        if ( ! $option_name ) {
            return [
                'success' => false,
                'error'   => __( 'No option name found', 'creator-core' ),
            ];
        }

        if ( $old_value === null ) {
            delete_option( $option_name );
        } else {
            update_option( $option_name, $old_value );
        }

        return [
            'success'     => true,
            'operation'   => 'restore_option',
            'option_name' => $option_name,
        ];
    }

    /**
     * Rollback term creation
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_create_term( array $operation ): array {
        $term_id  = $operation['after']['term_id'] ?? null;
        $taxonomy = $operation['after']['taxonomy'] ?? 'category';

        if ( ! $term_id ) {
            return [
                'success' => false,
                'error'   => __( 'No term ID found', 'creator-core' ),
            ];
        }

        $result = wp_delete_term( $term_id, $taxonomy );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        return [
            'success'   => true,
            'operation' => 'delete_term',
            'term_id'   => $term_id,
        ];
    }

    /**
     * Generic rollback for unknown operation types
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_generic( array $operation ): array {
        $this->logger->warning( 'rollback_generic_attempted', [
            'operation_type' => $operation['type'] ?? 'unknown',
            'operation'      => $operation,
        ]);

        return [
            'success' => false,
            'error'   => sprintf(
                /* translators: %s: Operation type */
                __( 'Unsupported rollback operation type: %s', 'creator-core' ),
                $operation['type'] ?? 'unknown'
            ),
        ];
    }

    /**
     * Rollback an action by ID
     *
     * @param int $action_id Action ID.
     * @return array
     */
    public function rollback_action( int $action_id ): array {
        $snapshot = $this->snapshot_manager->get_action_snapshot( $action_id );

        if ( ! $snapshot ) {
            return [
                'success' => false,
                'error'   => __( 'No snapshot found for this action', 'creator-core' ),
            ];
        }

        return $this->rollback_snapshot( $snapshot['id'] );
    }

    /**
     * Preview rollback (show what would be changed)
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array
     */
    public function preview_rollback( int $snapshot_id ): array {
        $snapshot = $this->snapshot_manager->get_snapshot( $snapshot_id );

        if ( ! $snapshot ) {
            return [
                'success' => false,
                'error'   => __( 'Snapshot not found', 'creator-core' ),
            ];
        }

        $operations = $snapshot['operations'] ?? [];
        $preview    = [];

        foreach ( $operations as $operation ) {
            $preview[] = [
                'type'        => $operation['type'],
                'target'      => $operation['target'] ?? null,
                'description' => $this->get_operation_description( $operation ),
                'reversible'  => $this->is_operation_reversible( $operation ),
            ];
        }

        return [
            'success'    => true,
            'snapshot_id' => $snapshot_id,
            'operations' => $preview,
            'created_at' => $snapshot['created_at'],
        ];
    }

    /**
     * Get human-readable operation description
     *
     * @param array $operation Operation data.
     * @return string
     */
    private function get_operation_description( array $operation ): string {
        $type   = $operation['type'] ?? 'unknown';
        $target = $operation['target'] ?? '';

        $descriptions = [
            'create_post'   => sprintf( __( 'Delete created post #%s', 'creator-core' ), $target ),
            'create_page'   => sprintf( __( 'Delete created page #%s', 'creator-core' ), $target ),
            'update_post'   => sprintf( __( 'Restore post #%s to previous state', 'creator-core' ), $target ),
            'update_page'   => sprintf( __( 'Restore page #%s to previous state', 'creator-core' ), $target ),
            'delete_post'   => __( 'Recreate deleted post', 'creator-core' ),
            'update_meta'   => sprintf( __( 'Restore meta for #%s', 'creator-core' ), $target ),
            'add_elementor_widget' => sprintf( __( 'Remove Elementor widget from #%s', 'creator-core' ), $target ),
        ];

        return $descriptions[ $type ] ?? sprintf( __( 'Rollback %s operation', 'creator-core' ), $type );
    }

    /**
     * Check if operation can be reversed
     *
     * @param array $operation Operation data.
     * @return bool
     */
    private function is_operation_reversible( array $operation ): bool {
        // Most operations with before state can be reversed
        return isset( $operation['before'] ) || isset( $operation['after'] );
    }

    /**
     * Rollback custom file modification
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_custom_file( array $operation ): array {
        $before = $operation['before'] ?? null;

        if ( ! $before ) {
            return [
                'success' => false,
                'error'   => __( 'No previous state to restore for custom file', 'creator-core' ),
            ];
        }

        $file_state = $before['file'] ?? null;
        $manifest_state = $before['manifest'] ?? null;

        if ( ! $file_state ) {
            return [
                'success' => false,
                'error'   => __( 'No file state found in snapshot', 'creator-core' ),
            ];
        }

        try {
            $custom_file_manager = new CustomFileManager( $this->logger );

            // Restore file state
            $file_restored = $custom_file_manager->restore_file_state( $file_state );

            // Restore manifest state if available
            $manifest_restored = true;
            if ( $manifest_state ) {
                $manifest_restored = $custom_file_manager->restore_file_state( $manifest_state );
            }

            if ( $file_restored && $manifest_restored ) {
                return [
                    'success'   => true,
                    'operation' => 'restore_custom_file',
                    'type'      => $file_state['type'] ?? 'unknown',
                    'file'      => $file_state['path'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error'   => __( 'Failed to restore custom file state', 'creator-core' ),
            ];
        } catch ( \Throwable $e ) {
            return [
                'success' => false,
                'error'   => sprintf(
                    /* translators: %s: Error message */
                    __( 'Error restoring custom file: %s', 'creator-core' ),
                    $e->getMessage()
                ),
            ];
        }
    }

    /**
     * Rollback WP Code snippet creation
     *
     * @param array $operation Operation data.
     * @return array
     */
    private function rollback_wpcode_snippet( array $operation ): array {
        $snippet_id = $operation['after']['snippet_id'] ?? $operation['target'] ?? null;

        if ( ! $snippet_id ) {
            return [
                'success' => false,
                'error'   => __( 'No snippet ID found in operation', 'creator-core' ),
            ];
        }

        try {
            $code_executor = new CodeExecutor();
            $result = $code_executor->rollback( $snippet_id, CodeExecutor::METHOD_WPCODE );

            if ( $result['success'] ) {
                return [
                    'success'    => true,
                    'operation'  => 'rollback_wpcode_snippet',
                    'snippet_id' => $snippet_id,
                ];
            }

            return [
                'success' => false,
                'error'   => $result['message'] ?? __( 'Failed to rollback WP Code snippet', 'creator-core' ),
            ];
        } catch ( \Throwable $e ) {
            return [
                'success' => false,
                'error'   => sprintf(
                    /* translators: %s: Error message */
                    __( 'Error rolling back snippet: %s', 'creator-core' ),
                    $e->getMessage()
                ),
            ];
        }
    }
}
