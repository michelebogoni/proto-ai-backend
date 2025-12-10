<?php
/**
 * Delta Backup
 *
 * @package CreatorCore
 */

namespace CreatorCore\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeltaBackup
 *
 * Handles delta backup operations (before/after states)
 */
class DeltaBackup {

    /**
     * Capture post state
     *
     * @param int $post_id Post ID.
     * @return array|null
     */
    public function capture_post_state( int $post_id ): ?array {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return null;
        }

        $state = [
            'post_id'      => $post->ID,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => $post->post_status,
            'post_type'    => $post->post_type,
            'post_author'  => $post->post_author,
            'post_date'    => $post->post_date,
            'post_modified' => $post->post_modified,
            'post_parent'  => $post->post_parent,
            'menu_order'   => $post->menu_order,
            'post_name'    => $post->post_name,
            'guid'         => $post->guid,
        ];

        // Capture post meta
        $state['meta'] = get_post_meta( $post_id );

        // Capture taxonomies
        $state['taxonomies'] = [];
        $taxonomies = get_object_taxonomies( $post->post_type );

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $terms ) ) {
                $state['taxonomies'][ $taxonomy ] = $terms;
            }
        }

        // Capture Elementor data if available
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
            if ( $elementor_data ) {
                $state['elementor_data'] = $elementor_data;
                $state['elementor_edit_mode'] = get_post_meta( $post_id, '_elementor_edit_mode', true );
                $state['elementor_template_type'] = get_post_meta( $post_id, '_elementor_template_type', true );
            }
        }

        return $state;
    }

    /**
     * Capture option state
     *
     * @param string $option_name Option name.
     * @return array
     */
    public function capture_option_state( string $option_name ): array {
        return [
            'option_name'  => $option_name,
            'option_value' => get_option( $option_name ),
            'autoload'     => $this->get_option_autoload( $option_name ),
        ];
    }

    /**
     * Get option autoload value
     *
     * @param string $option_name Option name.
     * @return string
     */
    private function get_option_autoload( string $option_name ): string {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                $option_name
            )
        ) ?? 'yes';
    }

    /**
     * Capture term state
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy name.
     * @return array|null
     */
    public function capture_term_state( int $term_id, string $taxonomy ): ?array {
        $term = get_term( $term_id, $taxonomy );

        if ( is_wp_error( $term ) || ! $term ) {
            return null;
        }

        return [
            'term_id'     => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'parent'      => $term->parent,
            'taxonomy'    => $term->taxonomy,
            'term_meta'   => get_term_meta( $term_id ),
        ];
    }

    /**
     * Capture user meta state
     *
     * @param int    $user_id  User ID.
     * @param string $meta_key Meta key (optional, captures all if empty).
     * @return array|null
     */
    public function capture_user_meta_state( int $user_id, string $meta_key = '' ): ?array {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return null;
        }

        if ( $meta_key ) {
            return [
                'user_id'  => $user_id,
                'meta_key' => $meta_key,
                'meta_value' => get_user_meta( $user_id, $meta_key, true ),
            ];
        }

        return [
            'user_id'   => $user_id,
            'user_meta' => get_user_meta( $user_id ),
        ];
    }

    /**
     * Create delta between two states
     *
     * @param array $before Before state.
     * @param array $after  After state.
     * @return array
     */
    public function create_delta( array $before, array $after ): array {
        $delta = [
            'before'  => $before,
            'after'   => $after,
            'changes' => [],
        ];

        // Find changed fields
        foreach ( $after as $key => $value ) {
            if ( ! isset( $before[ $key ] ) ) {
                $delta['changes'][ $key ] = [
                    'type' => 'added',
                    'value' => $value,
                ];
            } elseif ( $before[ $key ] !== $value ) {
                $delta['changes'][ $key ] = [
                    'type'      => 'modified',
                    'old_value' => $before[ $key ],
                    'new_value' => $value,
                ];
            }
        }

        // Find removed fields
        foreach ( $before as $key => $value ) {
            if ( ! isset( $after[ $key ] ) ) {
                $delta['changes'][ $key ] = [
                    'type'      => 'removed',
                    'old_value' => $value,
                ];
            }
        }

        return $delta;
    }

    /**
     * Format operation for snapshot
     *
     * @param string     $type   Operation type.
     * @param string|int $target Target identifier.
     * @param array|null $before Before state.
     * @param array|null $after  After state.
     * @param string     $status Operation status.
     * @return array
     */
    public function format_operation( string $type, $target, ?array $before, ?array $after, string $status = 'completed' ): array {
        return [
            'type'   => $type,
            'target' => $target,
            'status' => $status,
            'before' => $before,
            'after'  => $after,
        ];
    }

    /**
     * Capture ACF field group state
     *
     * @param string $group_key Field group key.
     * @return array|null
     */
    public function capture_acf_group_state( string $group_key ): ?array {
        if ( ! function_exists( 'acf_get_field_group' ) ) {
            return null;
        }

        $group = acf_get_field_group( $group_key );

        if ( ! $group ) {
            return null;
        }

        $group['fields'] = acf_get_fields( $group_key );

        return $group;
    }

    /**
     * Capture menu state
     *
     * @param int $menu_id Menu ID.
     * @return array|null
     */
    public function capture_menu_state( int $menu_id ): ?array {
        $menu = wp_get_nav_menu_object( $menu_id );

        if ( ! $menu ) {
            return null;
        }

        $items = wp_get_nav_menu_items( $menu_id, [ 'update_post_term_cache' => false ] );

        return [
            'menu_id'    => $menu->term_id,
            'name'       => $menu->name,
            'slug'       => $menu->slug,
            'locations'  => get_nav_menu_locations(),
            'items'      => $items ? array_map( function( $item ) {
                return [
                    'ID'          => $item->ID,
                    'title'       => $item->title,
                    'url'         => $item->url,
                    'menu_order'  => $item->menu_order,
                    'parent'      => $item->menu_item_parent,
                    'type'        => $item->type,
                    'object'      => $item->object,
                    'object_id'   => $item->object_id,
                ];
            }, $items ) : [],
        ];
    }

    /**
     * Capture widget state
     *
     * @param string $sidebar_id Sidebar ID.
     * @return array
     */
    public function capture_widget_state( string $sidebar_id ): array {
        $sidebars_widgets = get_option( 'sidebars_widgets', [] );

        return [
            'sidebar_id' => $sidebar_id,
            'widgets'    => $sidebars_widgets[ $sidebar_id ] ?? [],
            'all_widgets' => $this->get_all_widget_instances(),
        ];
    }

    /**
     * Get all widget instances
     *
     * @return array
     */
    private function get_all_widget_instances(): array {
        global $wp_registered_widgets;

        $instances = [];

        foreach ( $wp_registered_widgets as $widget_id => $widget ) {
            if ( isset( $widget['callback'][0] ) && is_object( $widget['callback'][0] ) ) {
                $widget_class = get_class( $widget['callback'][0] );
                $option_name  = 'widget_' . $widget['callback'][0]->id_base;
                $instances[ $widget_id ] = [
                    'class'   => $widget_class,
                    'options' => get_option( $option_name, [] ),
                ];
            }
        }

        return $instances;
    }
}
