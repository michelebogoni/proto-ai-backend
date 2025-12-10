<?php
/**
 * Elementor Integration
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorIntegration
 *
 * Handles Elementor-specific operations
 */
class ElementorIntegration {

    /**
     * Check if Elementor is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return class_exists( '\Elementor\Plugin' );
    }

    /**
     * Check if Elementor Pro is available
     *
     * @return bool
     */
    public function is_pro_available(): bool {
        return class_exists( '\ElementorPro\Plugin' );
    }

    /**
     * Get Elementor version
     *
     * @return string|null
     */
    public function get_version(): ?string {
        if ( ! $this->is_available() ) {
            return null;
        }

        return ELEMENTOR_VERSION ?? null;
    }

    /**
     * Check if post is built with Elementor
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public function is_elementor_page( int $post_id ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return \Elementor\Plugin::$instance->documents->get( $post_id )->is_built_with_elementor();
    }

    /**
     * Get Elementor data for a post
     *
     * @param int $post_id Post ID.
     * @return array|null
     */
    public function get_page_data( int $post_id ): ?array {
        if ( ! $this->is_available() ) {
            return null;
        }

        $document = \Elementor\Plugin::$instance->documents->get( $post_id );

        if ( ! $document ) {
            return null;
        }

        return [
            'is_elementor'   => $document->is_built_with_elementor(),
            'elements'       => $document->get_elements_data(),
            'settings'       => $document->get_settings(),
            'edit_url'       => $document->get_edit_url(),
        ];
    }

    /**
     * Save Elementor data for a post
     *
     * @param int   $post_id Post ID.
     * @param array $data    Elementor data.
     * @return bool
     */
    public function save_page_data( int $post_id, array $data ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        $document = \Elementor\Plugin::$instance->documents->get( $post_id );

        if ( ! $document ) {
            return false;
        }

        $document->save( [
            'elements' => $data['elements'] ?? [],
            'settings' => $data['settings'] ?? [],
        ]);

        return true;
    }

    /**
     * Get available widget types
     *
     * @return array
     */
    public function get_widget_types(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
        $result  = [];

        foreach ( $widgets as $widget ) {
            $result[ $widget->get_name() ] = [
                'name'       => $widget->get_name(),
                'title'      => $widget->get_title(),
                'icon'       => $widget->get_icon(),
                'categories' => $widget->get_categories(),
            ];
        }

        return $result;
    }

    /**
     * Get all Elementor templates
     *
     * @param string $type Template type (page, section, widget).
     * @return array
     */
    public function get_templates( string $type = 'page' ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );

        if ( ! $source ) {
            return [];
        }

        return $source->get_items( [ 'type' => $type ] );
    }

    /**
     * Create Elementor template
     *
     * @param string $title   Template title.
     * @param string $type    Template type.
     * @param array  $content Template content.
     * @return int|false Template ID or false on failure.
     */
    public function create_template( string $title, string $type, array $content = [] ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        $template_data = [
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_type'   => 'elementor_library',
        ];

        $template_id = wp_insert_post( $template_data );

        if ( is_wp_error( $template_id ) ) {
            return false;
        }

        // Set template type
        update_post_meta( $template_id, '_elementor_template_type', $type );

        // Set Elementor edit mode
        update_post_meta( $template_id, '_elementor_edit_mode', 'builder' );

        // Save content if provided
        if ( ! empty( $content ) ) {
            update_post_meta( $template_id, '_elementor_data', wp_json_encode( $content ) );
        }

        return $template_id;
    }

    /**
     * Add widget to page
     *
     * @param int    $post_id     Post ID.
     * @param string $widget_type Widget type.
     * @param array  $settings    Widget settings.
     * @param int    $position    Position index.
     * @return bool
     */
    public function add_widget( int $post_id, string $widget_type, array $settings = [], int $position = -1 ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        $data = get_post_meta( $post_id, '_elementor_data', true );
        $elements = $data ? json_decode( $data, true ) : [];

        $new_widget = [
            'id'         => \Elementor\Utils::generate_random_string(),
            'elType'     => 'widget',
            'widgetType' => $widget_type,
            'settings'   => $settings,
        ];

        // Find or create a section/column structure
        if ( empty( $elements ) ) {
            $elements = [
                [
                    'id'       => \Elementor\Utils::generate_random_string(),
                    'elType'   => 'section',
                    'settings' => [],
                    'elements' => [
                        [
                            'id'       => \Elementor\Utils::generate_random_string(),
                            'elType'   => 'column',
                            'settings' => [ '_column_size' => 100 ],
                            'elements' => [ $new_widget ],
                        ],
                    ],
                ],
            ];
        } else {
            // Add to first section's first column
            if ( isset( $elements[0]['elements'][0]['elements'] ) ) {
                if ( $position === -1 ) {
                    $elements[0]['elements'][0]['elements'][] = $new_widget;
                } else {
                    array_splice( $elements[0]['elements'][0]['elements'], $position, 0, [ $new_widget ] );
                }
            }
        }

        update_post_meta( $post_id, '_elementor_data', wp_json_encode( $elements ) );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

        return true;
    }

    /**
     * Get edit URL for post
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public function get_edit_url( int $post_id ): string {
        if ( ! $this->is_available() ) {
            return get_edit_post_link( $post_id );
        }

        $document = \Elementor\Plugin::$instance->documents->get( $post_id );

        if ( $document ) {
            return $document->get_edit_url();
        }

        return add_query_arg(
            [
                'post'   => $post_id,
                'action' => 'elementor',
            ],
            admin_url( 'post.php' )
        );
    }

    /**
     * Clear Elementor cache
     *
     * @return void
     */
    public function clear_cache(): void {
        if ( ! $this->is_available() ) {
            return;
        }

        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
}
