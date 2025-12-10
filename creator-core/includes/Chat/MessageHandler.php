<?php
/**
 * Message Handler
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class MessageHandler
 *
 * Handles chat message operations
 */
class MessageHandler {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger Audit logger instance.
     */
    public function __construct( ?AuditLogger $logger = null ) {
        $this->logger = $logger ?? new AuditLogger();
    }

    /**
     * Save a message
     *
     * @param int    $chat_id      Chat ID.
     * @param string $content      Message content.
     * @param string $role         Message role (user, assistant).
     * @param string $message_type Message type (text, action, error, info).
     * @param array  $metadata     Additional metadata.
     * @return int|false Message ID or false on failure.
     */
    public function save_message(
        int $chat_id,
        string $content,
        string $role = 'user',
        string $message_type = 'text',
        array $metadata = []
    ) {
        global $wpdb;

        $user_id = get_current_user_id();

        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_messages',
            [
                'chat_id'      => $chat_id,
                'user_id'      => $user_id,
                'role'         => $role,
                'content'      => $content,
                'message_type' => $message_type,
                'metadata'     => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            $this->logger->failure( 'message_save_failed', [
                'chat_id' => $chat_id,
                'role'    => $role,
                'error'   => $wpdb->last_error,
            ]);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get messages for a chat
     *
     * @param int   $chat_id Chat ID.
     * @param array $args    Query arguments.
     * @return array
     */
    public function get_messages( int $chat_id, array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'per_page' => 50,
            'page'     => 1,
            'order'    => 'ASC',
        ];

        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_messages
                 WHERE chat_id = %d
                 ORDER BY created_at {$args['order']}
                 LIMIT %d OFFSET %d",
                $chat_id,
                $args['per_page'],
                $offset
            ),
            ARRAY_A
        );

        // Parse metadata
        foreach ( $messages as &$message ) {
            $message['metadata'] = $message['metadata'] ? json_decode( $message['metadata'], true ) : [];
            $message['actions']  = $this->get_message_actions( $message['id'] );
        }

        return $messages;
    }

    /**
     * Get a single message
     *
     * @param int $message_id Message ID.
     * @return array|null
     */
    public function get_message( int $message_id ): ?array {
        global $wpdb;

        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_messages WHERE id = %d",
                $message_id
            ),
            ARRAY_A
        );

        if ( $message ) {
            $message['metadata'] = $message['metadata'] ? json_decode( $message['metadata'], true ) : [];
            $message['actions']  = $this->get_message_actions( $message_id );
        }

        return $message;
    }

    /**
     * Update message content
     *
     * @param int    $message_id Message ID.
     * @param string $content    New content.
     * @return bool
     */
    public function update_message( int $message_id, string $content ): bool {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'creator_messages',
            [ 'content' => $content ],
            [ 'id' => $message_id ],
            [ '%s' ],
            [ '%d' ]
        ) !== false;
    }

    /**
     * Update message metadata
     *
     * @param int   $message_id Message ID.
     * @param array $metadata   Metadata to update.
     * @param bool  $merge      Whether to merge with existing metadata.
     * @return bool
     */
    public function update_metadata( int $message_id, array $metadata, bool $merge = true ): bool {
        global $wpdb;

        if ( $merge ) {
            $message = $this->get_message( $message_id );
            if ( $message ) {
                $metadata = array_merge( $message['metadata'], $metadata );
            }
        }

        return $wpdb->update(
            $wpdb->prefix . 'creator_messages',
            [ 'metadata' => wp_json_encode( $metadata ) ],
            [ 'id' => $message_id ],
            [ '%s' ],
            [ '%d' ]
        ) !== false;
    }

    /**
     * Delete a message
     *
     * @param int $message_id Message ID.
     * @return bool
     */
    public function delete_message( int $message_id ): bool {
        global $wpdb;

        // Delete associated actions first
        $wpdb->delete(
            $wpdb->prefix . 'creator_actions',
            [ 'message_id' => $message_id ],
            [ '%d' ]
        );

        return $wpdb->delete(
            $wpdb->prefix . 'creator_messages',
            [ 'id' => $message_id ],
            [ '%d' ]
        ) !== false;
    }

    /**
     * Get actions for a message
     *
     * @param int $message_id Message ID.
     * @return array
     */
    public function get_message_actions( int $message_id ): array {
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
     * Count messages in a chat
     *
     * @param int         $chat_id Chat ID.
     * @param string|null $role    Filter by role.
     * @return int
     */
    public function count_messages( int $chat_id, ?string $role = null ): int {
        global $wpdb;

        $where  = 'chat_id = %d';
        $values = [ $chat_id ];

        if ( $role !== null ) {
            $where   .= ' AND role = %s';
            $values[] = $role;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages WHERE {$where}",
                $values
            )
        );
    }

    /**
     * Get last message in a chat
     *
     * @param int         $chat_id Chat ID.
     * @param string|null $role    Filter by role.
     * @return array|null
     */
    public function get_last_message( int $chat_id, ?string $role = null ): ?array {
        global $wpdb;

        $where  = 'chat_id = %d';
        $values = [ $chat_id ];

        if ( $role !== null ) {
            $where   .= ' AND role = %s';
            $values[] = $role;
        }

        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_messages
                 WHERE {$where}
                 ORDER BY created_at DESC
                 LIMIT 1",
                $values
            ),
            ARRAY_A
        );

        if ( $message ) {
            $message['metadata'] = $message['metadata'] ? json_decode( $message['metadata'], true ) : [];
        }

        return $message;
    }

    /**
     * Format message for display
     *
     * @param array $message Message data.
     * @return array
     */
    public function format_for_display( array $message ): array {
        return [
            'id'           => $message['id'],
            'role'         => $message['role'],
            'content'      => $this->sanitize_content( $message['content'] ),
            'type'         => $message['message_type'],
            'created_at'   => $message['created_at'],
            'formatted_time' => $this->format_time( $message['created_at'] ),
            'has_actions'  => ! empty( $message['actions'] ),
            'actions'      => $message['actions'] ?? [],
            'metadata'     => $message['metadata'] ?? [],
        ];
    }

    /**
     * Sanitize message content for display
     *
     * @param string $content Raw content.
     * @return string
     */
    private function sanitize_content( string $content ): string {
        // Allow basic HTML formatting
        $allowed_tags = [
            'p'      => [],
            'br'     => [],
            'strong' => [],
            'em'     => [],
            'code'   => [],
            'pre'    => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
            'a'      => [ 'href' => [], 'target' => [] ],
        ];

        return wp_kses( $content, $allowed_tags );
    }

    /**
     * Format timestamp for display
     *
     * @param string $datetime MySQL datetime.
     * @return string
     */
    private function format_time( string $datetime ): string {
        $timestamp = strtotime( $datetime );
        $now       = current_time( 'timestamp' );
        $diff      = $now - $timestamp;

        if ( $diff < 60 ) {
            return __( 'Just now', 'creator-core' );
        }

        if ( $diff < 3600 ) {
            $minutes = floor( $diff / 60 );
            return sprintf(
                /* translators: %d: Number of minutes */
                _n( '%d minute ago', '%d minutes ago', $minutes, 'creator-core' ),
                $minutes
            );
        }

        if ( $diff < 86400 ) {
            return date_i18n( get_option( 'time_format' ), $timestamp );
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }

    /**
     * Search messages
     *
     * @param int    $chat_id Chat ID.
     * @param string $query   Search query.
     * @return array
     */
    public function search_messages( int $chat_id, string $query ): array {
        global $wpdb;

        $search = '%' . $wpdb->esc_like( $query ) . '%';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_messages
                 WHERE chat_id = %d AND content LIKE %s
                 ORDER BY created_at DESC",
                $chat_id,
                $search
            ),
            ARRAY_A
        );
    }
}
