<?php
/**
 * WP Code Integration
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPCodeIntegration
 *
 * Handles WP Code (Insert Headers and Footers) operations
 */
class WPCodeIntegration {

    /**
     * WP Code post type
     */
    const POST_TYPE = 'wpcode';

    /**
     * Check if WP Code is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return function_exists( 'wpcode' ) || post_type_exists( self::POST_TYPE );
    }

    /**
     * Get WP Code version
     *
     * @return string|null
     */
    public function get_version(): ?string {
        if ( ! $this->is_available() ) {
            return null;
        }

        if ( defined( 'WPCODE_VERSION' ) ) {
            return WPCODE_VERSION;
        }

        // Fallback for older versions
        if ( defined( 'FLAVOR_FILENAME' ) ) {
            return '1.x';
        }

        return null;
    }

    /**
     * Get all code snippets
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_snippets( array $args = [] ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $defaults = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];

        $query = new \WP_Query( wp_parse_args( $args, $defaults ) );
        $result = [];

        foreach ( $query->posts as $post ) {
            $result[] = $this->format_snippet( $post );
        }

        return $result;
    }

    /**
     * Get a single snippet
     *
     * @param int $snippet_id Snippet ID.
     * @return array|null
     */
    public function get_snippet( int $snippet_id ): ?array {
        if ( ! $this->is_available() ) {
            return null;
        }

        $post = get_post( $snippet_id );

        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return null;
        }

        return $this->format_snippet( $post );
    }

    /**
     * Format snippet data
     *
     * @param \WP_Post $post Post object.
     * @return array
     */
    private function format_snippet( \WP_Post $post ): array {
        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'code'        => $post->post_content,
            'status'      => $post->post_status,
            'code_type'   => get_post_meta( $post->ID, '_wpcode_code_type', true ) ?: 'php',
            'location'    => get_post_meta( $post->ID, '_wpcode_location', true ),
            'priority'    => get_post_meta( $post->ID, '_wpcode_priority', true ) ?: 10,
            'auto_insert' => get_post_meta( $post->ID, '_wpcode_auto_insert', true ),
            'created'     => $post->post_date,
            'modified'    => $post->post_modified,
        ];
    }

    /**
     * Create a new code snippet
     *
     * @param array $data Snippet data.
     * @return int|false Snippet ID or false on failure.
     */
    public function create_snippet( array $data ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        $post_data = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $data['title'] ?? 'New Snippet',
            'post_content' => $data['code'] ?? '',
            'post_status'  => $data['status'] ?? 'draft',
        ];

        $snippet_id = wp_insert_post( $post_data );

        if ( is_wp_error( $snippet_id ) ) {
            return false;
        }

        // Set snippet meta
        $this->update_snippet_meta( $snippet_id, $data );

        return $snippet_id;
    }

    /**
     * Update a code snippet
     *
     * @param int   $snippet_id Snippet ID.
     * @param array $data       Snippet data.
     * @return bool
     */
    public function update_snippet( int $snippet_id, array $data ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        $post = get_post( $snippet_id );

        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        $post_data = [ 'ID' => $snippet_id ];

        if ( isset( $data['title'] ) ) {
            $post_data['post_title'] = $data['title'];
        }

        if ( isset( $data['code'] ) ) {
            $post_data['post_content'] = $data['code'];
        }

        if ( isset( $data['status'] ) ) {
            $post_data['post_status'] = $data['status'];
        }

        $result = wp_update_post( $post_data );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        // Update meta
        $this->update_snippet_meta( $snippet_id, $data );

        return true;
    }

    /**
     * Update snippet meta fields
     *
     * @param int   $snippet_id Snippet ID.
     * @param array $data       Meta data.
     * @return void
     */
    private function update_snippet_meta( int $snippet_id, array $data ): void {
        $meta_fields = [
            'code_type'   => '_wpcode_code_type',
            'location'    => '_wpcode_location',
            'priority'    => '_wpcode_priority',
            'auto_insert' => '_wpcode_auto_insert',
        ];

        foreach ( $meta_fields as $key => $meta_key ) {
            if ( isset( $data[ $key ] ) ) {
                update_post_meta( $snippet_id, $meta_key, $data[ $key ] );
            }
        }
    }

    /**
     * Delete a code snippet
     *
     * @param int  $snippet_id   Snippet ID.
     * @param bool $force_delete Whether to permanently delete.
     * @return bool
     */
    public function delete_snippet( int $snippet_id, bool $force_delete = false ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        $post = get_post( $snippet_id );

        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        $result = wp_delete_post( $snippet_id, $force_delete );

        return $result !== false;
    }

    /**
     * Activate a snippet
     *
     * @param int $snippet_id Snippet ID.
     * @return bool
     */
    public function activate_snippet( int $snippet_id ): bool {
        return $this->update_snippet( $snippet_id, [ 'status' => 'publish' ] );
    }

    /**
     * Deactivate a snippet
     *
     * @param int $snippet_id Snippet ID.
     * @return bool
     */
    public function deactivate_snippet( int $snippet_id ): bool {
        return $this->update_snippet( $snippet_id, [ 'status' => 'draft' ] );
    }

    /**
     * Get available code types
     *
     * @return array
     */
    public function get_code_types(): array {
        return [
            'php'        => __( 'PHP Snippet', 'creator-core' ),
            'js'         => __( 'JavaScript', 'creator-core' ),
            'css'        => __( 'CSS', 'creator-core' ),
            'html'       => __( 'HTML', 'creator-core' ),
            'text'       => __( 'Text', 'creator-core' ),
            'universal'  => __( 'Universal', 'creator-core' ),
        ];
    }

    /**
     * Get available locations
     *
     * @return array
     */
    public function get_locations(): array {
        return [
            'site_wide_header'  => __( 'Site Wide Header', 'creator-core' ),
            'site_wide_body'    => __( 'Site Wide Body', 'creator-core' ),
            'site_wide_footer'  => __( 'Site Wide Footer', 'creator-core' ),
            'before_content'    => __( 'Before Content', 'creator-core' ),
            'after_content'     => __( 'After Content', 'creator-core' ),
            'between_posts'     => __( 'Between Posts', 'creator-core' ),
            'before_paragraph'  => __( 'Before Paragraph', 'creator-core' ),
            'after_paragraph'   => __( 'After Paragraph', 'creator-core' ),
            'php_everywhere'    => __( 'Run Everywhere', 'creator-core' ),
            'admin_only'        => __( 'Admin Only', 'creator-core' ),
            'frontend_only'     => __( 'Frontend Only', 'creator-core' ),
        ];
    }

    /**
     * Get snippet count by status
     *
     * @return array
     */
    public function get_counts(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $counts = wp_count_posts( self::POST_TYPE );

        return [
            'active'   => $counts->publish ?? 0,
            'inactive' => $counts->draft ?? 0,
            'trash'    => $counts->trash ?? 0,
            'total'    => ( $counts->publish ?? 0 ) + ( $counts->draft ?? 0 ),
        ];
    }

    /**
     * Duplicate a snippet
     *
     * @param int $snippet_id Snippet ID.
     * @return int|false New snippet ID or false on failure.
     */
    public function duplicate_snippet( int $snippet_id ) {
        $snippet = $this->get_snippet( $snippet_id );

        if ( ! $snippet ) {
            return false;
        }

        $new_data = $snippet;
        $new_data['title']  = $snippet['title'] . ' (Copy)';
        $new_data['status'] = 'draft';
        unset( $new_data['id'], $new_data['created'], $new_data['modified'] );

        return $this->create_snippet( $new_data );
    }
}
