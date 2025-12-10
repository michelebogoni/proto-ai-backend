<?php
/**
 * WooCommerce Integration
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooCommerceIntegration
 *
 * Handles WooCommerce-specific operations
 */
class WooCommerceIntegration {

    /**
     * Check if WooCommerce is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Get WooCommerce version
     *
     * @return string|null
     */
    public function get_version(): ?string {
        if ( ! $this->is_available() ) {
            return null;
        }

        return defined( 'WC_VERSION' ) ? WC_VERSION : null;
    }

    /**
     * Create a simple product
     *
     * @param array $data Product data.
     * @return int|false Product ID or false on failure.
     */
    public function create_product( array $data ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        $product = new \WC_Product_Simple();

        $product->set_name( $data['name'] ?? 'New Product' );
        $product->set_status( $data['status'] ?? 'draft' );
        $product->set_regular_price( $data['price'] ?? '0' );

        if ( isset( $data['sale_price'] ) ) {
            $product->set_sale_price( $data['sale_price'] );
        }

        if ( isset( $data['description'] ) ) {
            $product->set_description( $data['description'] );
        }

        if ( isset( $data['short_description'] ) ) {
            $product->set_short_description( $data['short_description'] );
        }

        if ( isset( $data['sku'] ) ) {
            $product->set_sku( $data['sku'] );
        }

        if ( isset( $data['stock_quantity'] ) ) {
            $product->set_stock_quantity( $data['stock_quantity'] );
            $product->set_manage_stock( true );
        }

        if ( isset( $data['categories'] ) ) {
            $product->set_category_ids( $data['categories'] );
        }

        $product_id = $product->save();

        return $product_id > 0 ? $product_id : false;
    }

    /**
     * Get product data
     *
     * @param int $product_id Product ID.
     * @return array|null
     */
    public function get_product( int $product_id ): ?array {
        if ( ! $this->is_available() ) {
            return null;
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return null;
        }

        return [
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'type'              => $product->get_type(),
            'status'            => $product->get_status(),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku'               => $product->get_sku(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'stock_status'      => $product->get_stock_status(),
            'categories'        => $product->get_category_ids(),
            'tags'              => $product->get_tag_ids(),
            'image_id'          => $product->get_image_id(),
            'gallery_ids'       => $product->get_gallery_image_ids(),
        ];
    }

    /**
     * Update product
     *
     * @param int   $product_id Product ID.
     * @param array $data       Product data.
     * @return bool
     */
    public function update_product( int $product_id, array $data ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return false;
        }

        if ( isset( $data['name'] ) ) {
            $product->set_name( $data['name'] );
        }

        if ( isset( $data['price'] ) ) {
            $product->set_regular_price( $data['price'] );
        }

        if ( isset( $data['sale_price'] ) ) {
            $product->set_sale_price( $data['sale_price'] );
        }

        if ( isset( $data['description'] ) ) {
            $product->set_description( $data['description'] );
        }

        if ( isset( $data['short_description'] ) ) {
            $product->set_short_description( $data['short_description'] );
        }

        if ( isset( $data['status'] ) ) {
            $product->set_status( $data['status'] );
        }

        if ( isset( $data['stock_quantity'] ) ) {
            $product->set_stock_quantity( $data['stock_quantity'] );
        }

        $product->save();

        return true;
    }

    /**
     * Get product categories
     *
     * @return array
     */
    public function get_categories(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $terms as $term ) {
            $result[] = [
                'id'     => $term->term_id,
                'name'   => $term->name,
                'slug'   => $term->slug,
                'parent' => $term->parent,
                'count'  => $term->count,
            ];
        }

        return $result;
    }

    /**
     * Create product category
     *
     * @param array $data Category data.
     * @return int|false Category ID or false on failure.
     */
    public function create_category( array $data ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        $result = wp_insert_term(
            $data['name'],
            'product_cat',
            [
                'description' => $data['description'] ?? '',
                'slug'        => $data['slug'] ?? '',
                'parent'      => $data['parent'] ?? 0,
            ]
        );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        return $result['term_id'];
    }

    /**
     * Get recent orders
     *
     * @param int $limit Number of orders.
     * @return array
     */
    public function get_recent_orders( int $limit = 10 ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $orders = wc_get_orders( [
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        $result = [];

        foreach ( $orders as $order ) {
            $result[] = [
                'id'          => $order->get_id(),
                'status'      => $order->get_status(),
                'total'       => $order->get_total(),
                'currency'    => $order->get_currency(),
                'date'        => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
                'customer'    => $order->get_billing_email(),
                'items_count' => $order->get_item_count(),
            ];
        }

        return $result;
    }

    /**
     * Get store statistics
     *
     * @return array
     */
    public function get_stats(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        global $wpdb;

        // Get product counts
        $product_counts = wp_count_posts( 'product' );

        // Get order counts
        $order_counts = [];
        foreach ( wc_get_order_statuses() as $status => $label ) {
            $order_counts[ $status ] = wc_orders_count( str_replace( 'wc-', '', $status ) );
        }

        // Get revenue (last 30 days)
        $revenue = $wpdb->get_var(
            "SELECT SUM(meta_value) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_order_total'
             AND p.post_type = 'shop_order'
             AND p.post_status IN ('wc-completed', 'wc-processing')
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return [
            'products'     => [
                'published' => $product_counts->publish ?? 0,
                'draft'     => $product_counts->draft ?? 0,
                'total'     => array_sum( (array) $product_counts ),
            ],
            'orders'       => $order_counts,
            'revenue_30d'  => (float) $revenue,
            'currency'     => get_woocommerce_currency(),
        ];
    }

    /**
     * Clear WooCommerce transients
     *
     * @return void
     */
    public function clear_cache(): void {
        if ( ! $this->is_available() ) {
            return;
        }

        wc_delete_product_transients();
        \WC_Cache_Helper::get_transient_version( 'product', true );
    }
}
