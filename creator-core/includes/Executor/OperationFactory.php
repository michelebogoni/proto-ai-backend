<?php
/**
 * Operation Factory
 *
 * @package CreatorCore
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

/**
 * Class OperationFactory
 *
 * Creates operation handlers for different action types
 */
class OperationFactory {

    /**
     * Available operation types
     *
     * @var array
     */
    private array $operation_types = [
        // Post operations
        'create_post'   => 'post',
        'update_post'   => 'post',
        'delete_post'   => 'post',
        'publish_post'  => 'post',

        // Page operations
        'create_page'   => 'page',
        'update_page'   => 'page',
        'delete_page'   => 'page',
        'publish_page'  => 'page',

        // Meta operations
        'update_meta'   => 'meta',
        'delete_meta'   => 'meta',

        // Elementor operations
        'add_elementor_widget'    => 'elementor',
        'update_elementor_widget' => 'elementor',
        'remove_elementor_widget' => 'elementor',
        'create_elementor_template' => 'elementor',

        // ACF operations
        'create_acf_field'  => 'acf',
        'update_acf_field'  => 'acf',
        'delete_acf_field'  => 'acf',
        'create_acf_group'  => 'acf',

        // WooCommerce operations
        'create_product' => 'woocommerce',
        'update_product' => 'woocommerce',
        'delete_product' => 'woocommerce',

        // Media operations
        'upload_media' => 'media',
        'delete_media' => 'media',

        // Option operations
        'update_option' => 'option',
        'delete_option' => 'option',

        // Term operations
        'create_term' => 'term',
        'update_term' => 'term',
        'delete_term' => 'term',

        // Menu operations
        'create_menu'      => 'menu',
        'add_menu_item'    => 'menu',
        'update_menu_item' => 'menu',

        // WP Code operations
        'create_snippet' => 'wpcode',
        'update_snippet' => 'wpcode',
        'delete_snippet' => 'wpcode',
    ];

    /**
     * Get operation category
     *
     * @param string $action_type Action type.
     * @return string|null
     */
    public function get_category( string $action_type ): ?string {
        return $this->operation_types[ $action_type ] ?? null;
    }

    /**
     * Check if operation type is valid
     *
     * @param string $action_type Action type.
     * @return bool
     */
    public function is_valid_operation( string $action_type ): bool {
        return isset( $this->operation_types[ $action_type ] );
    }

    /**
     * Get all operations for a category
     *
     * @param string $category Category name.
     * @return array
     */
    public function get_operations_by_category( string $category ): array {
        return array_keys( array_filter( $this->operation_types, function( $cat ) use ( $category ) {
            return $cat === $category;
        }));
    }

    /**
     * Get all operation types
     *
     * @return array
     */
    public function get_all_operations(): array {
        return array_keys( $this->operation_types );
    }

    /**
     * Get all categories
     *
     * @return array
     */
    public function get_all_categories(): array {
        return array_unique( array_values( $this->operation_types ) );
    }

    /**
     * Get operation description
     *
     * @param string $action_type Action type.
     * @return string
     */
    public function get_operation_description( string $action_type ): string {
        $descriptions = [
            'create_post'             => __( 'Create a new blog post', 'creator-core' ),
            'update_post'             => __( 'Update an existing blog post', 'creator-core' ),
            'delete_post'             => __( 'Delete a blog post', 'creator-core' ),
            'publish_post'            => __( 'Publish a draft blog post', 'creator-core' ),
            'create_page'             => __( 'Create a new page', 'creator-core' ),
            'update_page'             => __( 'Update an existing page', 'creator-core' ),
            'delete_page'             => __( 'Delete a page', 'creator-core' ),
            'publish_page'            => __( 'Publish a draft page', 'creator-core' ),
            'update_meta'             => __( 'Update post meta data', 'creator-core' ),
            'delete_meta'             => __( 'Delete post meta data', 'creator-core' ),
            'add_elementor_widget'    => __( 'Add a widget to Elementor page', 'creator-core' ),
            'update_elementor_widget' => __( 'Update an Elementor widget', 'creator-core' ),
            'remove_elementor_widget' => __( 'Remove an Elementor widget', 'creator-core' ),
            'create_elementor_template' => __( 'Create an Elementor template', 'creator-core' ),
            'create_acf_field'        => __( 'Create a custom field', 'creator-core' ),
            'update_acf_field'        => __( 'Update a custom field', 'creator-core' ),
            'delete_acf_field'        => __( 'Delete a custom field', 'creator-core' ),
            'create_acf_group'        => __( 'Create a field group', 'creator-core' ),
            'create_product'          => __( 'Create a WooCommerce product', 'creator-core' ),
            'update_product'          => __( 'Update a WooCommerce product', 'creator-core' ),
            'delete_product'          => __( 'Delete a WooCommerce product', 'creator-core' ),
            'upload_media'            => __( 'Upload a media file', 'creator-core' ),
            'delete_media'            => __( 'Delete a media file', 'creator-core' ),
            'update_option'           => __( 'Update a site option', 'creator-core' ),
            'delete_option'           => __( 'Delete a site option', 'creator-core' ),
            'create_term'             => __( 'Create a taxonomy term', 'creator-core' ),
            'update_term'             => __( 'Update a taxonomy term', 'creator-core' ),
            'delete_term'             => __( 'Delete a taxonomy term', 'creator-core' ),
            'create_menu'             => __( 'Create a navigation menu', 'creator-core' ),
            'add_menu_item'           => __( 'Add a menu item', 'creator-core' ),
            'update_menu_item'        => __( 'Update a menu item', 'creator-core' ),
            'create_snippet'          => __( 'Create a code snippet', 'creator-core' ),
            'update_snippet'          => __( 'Update a code snippet', 'creator-core' ),
            'delete_snippet'          => __( 'Delete a code snippet', 'creator-core' ),
        ];

        return $descriptions[ $action_type ] ?? sprintf(
            /* translators: %s: Action type */
            __( 'Execute %s operation', 'creator-core' ),
            $action_type
        );
    }

    /**
     * Get required parameters for an operation
     *
     * @param string $action_type Action type.
     * @return array
     */
    public function get_required_params( string $action_type ): array {
        $params = [
            'create_post' => [ 'title' ],
            'update_post' => [ 'post_id' ],
            'delete_post' => [ 'post_id' ],
            'create_page' => [ 'title' ],
            'update_page' => [ 'post_id' ],
            'delete_page' => [ 'post_id' ],
            'update_meta' => [ 'object_id', 'meta_key' ],
            'add_elementor_widget' => [ 'post_id', 'widget_type' ],
            'create_product' => [ 'name', 'price' ],
            'update_option' => [ 'option_name' ],
        ];

        return $params[ $action_type ] ?? [];
    }

    /**
     * Validate operation parameters
     *
     * @param string $action_type Action type.
     * @param array  $params      Parameters.
     * @return array Array of missing parameters.
     */
    public function validate_params( string $action_type, array $params ): array {
        $required = $this->get_required_params( $action_type );
        $missing  = [];

        foreach ( $required as $param ) {
            if ( ! isset( $params[ $param ] ) || $params[ $param ] === '' ) {
                $missing[] = $param;
            }
        }

        return $missing;
    }
}
