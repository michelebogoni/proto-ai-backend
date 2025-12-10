<?php
/**
 * Context Loader - Lazy-load detailed context on demand
 *
 * Provides methods to load detailed information about plugins, ACF fields,
 * CPTs, etc. when the AI requests them.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContextLoader
 *
 * Lazy-loads detailed context information on demand.
 * Used when AI needs more info than the compact context provides.
 */
class ContextLoader {

	/**
	 * Plugin docs repository
	 *
	 * @var PluginDocsRepository
	 */
	private PluginDocsRepository $docs_repository;

	/**
	 * Constructor
	 *
	 * @param PluginDocsRepository|null $docs_repository Docs repository instance.
	 */
	public function __construct( ?PluginDocsRepository $docs_repository = null ) {
		$this->docs_repository = $docs_repository ?? new PluginDocsRepository();
	}

	/**
	 * Get detailed plugin information
	 *
	 * @param string $slug Plugin slug.
	 * @return array Plugin details or error.
	 */
	public function get_plugin_details( string $slug ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		// Find plugin by slug
		foreach ( $active_plugins as $plugin_path ) {
			if ( dirname( $plugin_path ) === $slug && isset( $all_plugins[ $plugin_path ] ) ) {
				$plugin  = $all_plugins[ $plugin_path ];
				$version = $plugin['Version'];

				// Get full docs from repository
				$docs = $this->docs_repository->get_plugin_docs( $slug, $version );

				return [
					'success' => true,
					'data'    => [
						'name'           => $plugin['Name'],
						'slug'           => $slug,
						'version'        => $version,
						'author'         => $plugin['Author'],
						'description'    => $plugin['Description'],
						'plugin_uri'     => $plugin['PluginURI'],
						'docs_url'       => $docs['docs_url'] ?? null,
						'main_functions' => $docs['main_functions'] ?? [],
						'api_reference'  => $docs['api_reference'] ?? null,
						'version_notes'  => $docs['version_notes'] ?? [],
					],
				];
			}
		}

		return [
			'success' => false,
			'error'   => sprintf( 'Plugin "%s" not found or not active', $slug ),
		];
	}

	/**
	 * Get detailed ACF field group information
	 *
	 * @param string $group_title ACF group title or key.
	 * @return array Group details or error.
	 */
	public function get_acf_group_details( string $group_title ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return [
				'success' => false,
				'error'   => 'ACF not active',
			];
		}

		$groups = acf_get_field_groups();

		foreach ( $groups as $group ) {
			// Match by title or key
			if ( $group['title'] === $group_title || $group['key'] === $group_title ) {
				$fields     = acf_get_fields( $group['key'] );
				$field_data = [];

				if ( $fields ) {
					foreach ( $fields as $field ) {
						$field_data[] = [
							'name'         => $field['name'],
							'label'        => $field['label'],
							'type'         => $field['type'],
							'required'     => $field['required'] ?? false,
							'instructions' => $field['instructions'] ?? '',
						];
					}
				}

				return [
					'success' => true,
					'data'    => [
						'title'    => $group['title'],
						'key'      => $group['key'],
						'location' => $this->simplify_location( $group['location'] ?? [] ),
						'fields'   => $field_data,
					],
				];
			}
		}

		return [
			'success' => false,
			'error'   => sprintf( 'ACF group "%s" not found', $group_title ),
		];
	}

	/**
	 * Get detailed CPT information
	 *
	 * @param string $post_type CPT slug.
	 * @return array CPT details or error.
	 */
	public function get_cpt_details( string $post_type ): array {
		$cpt = get_post_type_object( $post_type );

		if ( ! $cpt ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Post type "%s" not found', $post_type ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'name'         => $cpt->name,
				'label'        => $cpt->label,
				'description'  => $cpt->description,
				'public'       => $cpt->public,
				'hierarchical' => $cpt->hierarchical,
				'has_archive'  => $cpt->has_archive,
				'supports'     => get_all_post_type_supports( $cpt->name ),
				'taxonomies'   => get_object_taxonomies( $cpt->name ),
				'rewrite'      => $cpt->rewrite,
				'rest_base'    => $cpt->rest_base,
				'menu_icon'    => $cpt->menu_icon,
			],
		];
	}

	/**
	 * Get detailed taxonomy information
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array Taxonomy details or error.
	 */
	public function get_taxonomy_details( string $taxonomy ): array {
		$tax = get_taxonomy( $taxonomy );

		if ( ! $tax ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Taxonomy "%s" not found', $taxonomy ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'name'         => $tax->name,
				'label'        => $tax->label,
				'description'  => $tax->description,
				'hierarchical' => $tax->hierarchical,
				'object_types' => $tax->object_type,
				'rewrite'      => $tax->rewrite,
				'rest_base'    => $tax->rest_base,
				'show_in_rest' => $tax->show_in_rest,
			],
		];
	}

	/**
	 * Get WP functions reference for a category
	 *
	 * @param string $category Category (wordpress, woocommerce, acf, elementor, database).
	 * @return array Functions list or error.
	 */
	public function get_wp_functions( string $category ): array {
		$functions = [
			'wordpress'   => [
				'wp_insert_post( $postarr, $wp_error, $fire_after_hooks )',
				'wp_update_post( $postarr, $wp_error, $fire_after_hooks )',
				'wp_delete_post( $postid, $force_delete )',
				'get_post( $post, $output, $filter )',
				'get_posts( $args )',
				'WP_Query( $args ) - Main query class',
				'register_post_type( $post_type, $args )',
				'register_taxonomy( $taxonomy, $object_type, $args )',
				'add_action( $hook_name, $callback, $priority, $accepted_args )',
				'add_filter( $hook_name, $callback, $priority, $accepted_args )',
				'get_option( $option, $default_value )',
				'update_option( $option, $value, $autoload )',
				'add_shortcode( $tag, $callback )',
				'wp_enqueue_script( $handle, $src, $deps, $ver, $args )',
				'wp_enqueue_style( $handle, $src, $deps, $ver, $media )',
			],
			'woocommerce' => [
				'wc_get_product( $the_product )',
				'wc_get_products( $args )',
				'wc_create_order( $args )',
				'wc_get_orders( $args )',
				'WC()->cart - Cart instance',
				'WC()->session - Session instance',
				'wc_add_notice( $message, $notice_type, $data )',
				'wc_price( $price, $args )',
				'wc_get_template( $template_name, $args )',
				'wc_get_checkout_url()',
				'wc_get_cart_url()',
			],
			'acf'         => [
				'get_field( $selector, $post_id, $format_value )',
				'update_field( $selector, $value, $post_id )',
				'get_field_object( $selector, $post_id, $format_value, $load_value )',
				'have_rows( $selector, $post_id )',
				'the_row()',
				'get_sub_field( $selector, $format_value )',
				'acf_add_local_field_group( $field_group )',
				'acf_get_field_groups( $filter )',
			],
			'elementor'   => [
				'\Elementor\Plugin::instance()',
				'\Elementor\Plugin::$instance->documents->get( $post_id )',
				'\Elementor\Controls_Manager - Widget controls',
				'\Elementor\Widget_Base - Base widget class',
				'elementor_get_option( $option_name )',
			],
			'database'    => [
				'$wpdb->get_results( $query, $output_type )',
				'$wpdb->get_row( $query, $output_type, $row_offset )',
				'$wpdb->get_var( $query, $column_offset, $row_offset )',
				'$wpdb->get_col( $query, $column_offset )',
				'$wpdb->insert( $table, $data, $format )',
				'$wpdb->update( $table, $data, $where, $format, $where_format )',
				'$wpdb->delete( $table, $where, $where_format )',
				'$wpdb->prepare( $query, ...$args ) - ALWAYS use for user input!',
				'$wpdb->query( $query )',
				'$wpdb->prefix - Table prefix',
			],
		];

		$category = strtolower( $category );

		if ( ! isset( $functions[ $category ] ) ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Category "%s" not found. Available: %s', $category, implode( ', ', array_keys( $functions ) ) ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'category'  => $category,
				'functions' => $functions[ $category ],
			],
		];
	}

	/**
	 * Simplify ACF location rules for display
	 *
	 * @param array $location ACF location rules.
	 * @return string Simplified location string.
	 */
	private function simplify_location( array $location ): string {
		$parts = [];

		foreach ( $location as $group ) {
			$rules = [];
			foreach ( $group as $rule ) {
				$rules[] = sprintf( '%s %s %s', $rule['param'] ?? '', $rule['operator'] ?? '', $rule['value'] ?? '' );
			}
			$parts[] = implode( ' AND ', $rules );
		}

		return implode( ' OR ', $parts );
	}

	/**
	 * Handle a context request action
	 *
	 * @param array $action Action with type and params.
	 * @return array Result data.
	 */
	public function handle_context_request( array $action ): array {
		$type   = $action['type'] ?? '';
		$params = $action['params'] ?? [];

		switch ( $type ) {
			case 'get_plugin_details':
				return $this->get_plugin_details( $params['slug'] ?? '' );

			case 'get_acf_details':
				return $this->get_acf_group_details( $params['group'] ?? '' );

			case 'get_cpt_details':
				return $this->get_cpt_details( $params['post_type'] ?? '' );

			case 'get_taxonomy_details':
				return $this->get_taxonomy_details( $params['taxonomy'] ?? '' );

			case 'get_wp_functions':
				return $this->get_wp_functions( $params['category'] ?? '' );

			default:
				return [
					'success' => false,
					'error'   => sprintf( 'Unknown context request type: %s', $type ),
				];
		}
	}
}
