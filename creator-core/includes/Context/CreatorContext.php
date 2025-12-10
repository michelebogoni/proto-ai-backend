<?php
/**
 * Creator Context Manager
 *
 * Generates, stores, and manages the comprehensive Creator Context document
 * that is passed to AI at each chat session.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ContextCollector;
use CreatorCore\Integrations\PluginDetector;
use CreatorCore\User\UserProfile;

/**
 * Class CreatorContext
 *
 * Manages the Creator Context document lifecycle:
 * - Generation at plugin activation
 * - Two-level caching (transient + option) for performance
 * - Retrieval for chat sessions
 * - Auto-refresh when system changes
 */
class CreatorContext {

	/**
	 * Option name for storing context document (legacy, used as fallback)
	 */
	private const CONTEXT_OPTION = 'creator_context_document';

	/**
	 * Option name for context version/timestamp (legacy)
	 */
	private const VERSION_OPTION = 'creator_context_version';

	/**
	 * Context collector instance
	 *
	 * @var ContextCollector
	 */
	private ContextCollector $context_collector;

	/**
	 * Plugin detector instance
	 *
	 * @var PluginDetector
	 */
	private PluginDetector $plugin_detector;

	/**
	 * Plugin docs repository instance
	 *
	 * @var PluginDocsRepository
	 */
	private PluginDocsRepository $docs_repository;

	/**
	 * System prompts instance
	 *
	 * @var SystemPrompts
	 */
	private SystemPrompts $system_prompts;

	/**
	 * Context cache instance
	 *
	 * @var ContextCache
	 */
	private ContextCache $cache;

	/**
	 * Constructor
	 *
	 * @param ContextCollector|null     $context_collector Context collector instance.
	 * @param PluginDetector|null       $plugin_detector   Plugin detector instance.
	 * @param PluginDocsRepository|null $docs_repository   Plugin docs repository instance.
	 * @param SystemPrompts|null        $system_prompts    System prompts instance.
	 * @param ContextCache|null         $cache             Context cache instance.
	 */
	public function __construct(
		?ContextCollector $context_collector = null,
		?PluginDetector $plugin_detector = null,
		?PluginDocsRepository $docs_repository = null,
		?SystemPrompts $system_prompts = null,
		?ContextCache $cache = null
	) {
		$this->context_collector = $context_collector ?? new ContextCollector();
		$this->plugin_detector   = $plugin_detector ?? new PluginDetector();
		$this->docs_repository   = $docs_repository ?? new PluginDocsRepository();
		$this->system_prompts    = $system_prompts ?? new SystemPrompts();
		$this->cache             = $cache ?? new ContextCache();
	}

	/**
	 * Generate and store the complete Creator Context document
	 *
	 * @param bool $force Force regeneration even if recent.
	 * @return array The generated context document.
	 */
	public function generate( bool $force = false ): array {
		// Check if we have a recent valid context
		if ( ! $force && $this->is_context_valid() ) {
			return $this->get_stored_context();
		}

		$context = [
			'meta'              => $this->generate_meta(),
			'user_profile'      => $this->generate_user_profile(),
			'system_info'       => $this->generate_system_info(),
			'system_analysis'   => $this->generate_system_analysis(),
			'plugins'           => $this->generate_plugins_info(),
			'custom_post_types' => $this->generate_cpt_info(),
			'taxonomies'        => $this->generate_taxonomies_info(),
			'acf_fields'        => $this->generate_acf_info(),
			'integrations'      => $this->generate_integrations_info(),
			'sitemap'           => $this->generate_sitemap(),
			'system_prompts'    => $this->generate_system_prompts(),
			'ai_instructions'   => $this->generate_ai_instructions(),
			'forbidden'         => $this->get_forbidden_functions(),
		];

		// Store the context
		$this->store_context( $context );

		return $context;
	}

	/**
	 * Get the stored context document
	 *
	 * @return array|null
	 */
	public function get_stored_context(): ?array {
		$context = get_option( self::CONTEXT_OPTION );
		return is_array( $context ) ? $context : null;
	}

	/**
	 * Get context for AI chat (formatted for injection)
	 *
	 * Uses two-level caching for performance:
	 * - Level 1: Transient (5 min TTL)
	 * - Level 2: Persistent option
	 *
	 * @return array Context ready for AI consumption.
	 */
	public function get_context_for_chat(): array {
		return $this->cache->get( fn() => $this->generate_fresh_context() );
	}

	/**
	 * Generate fresh context (used as cache generator)
	 *
	 * @return array Fresh context document.
	 */
	private function generate_fresh_context(): array {
		return [
			'meta'              => $this->generate_meta(),
			'user_profile'      => $this->generate_user_profile(),
			'system_info'       => $this->generate_system_info(),
			'system_analysis'   => $this->generate_system_analysis(),
			'plugins'           => $this->generate_plugins_info(),
			'custom_post_types' => $this->generate_cpt_info(),
			'taxonomies'        => $this->generate_taxonomies_info(),
			'acf_fields'        => $this->generate_acf_info(),
			'integrations'      => $this->generate_integrations_info(),
			'sitemap'           => $this->generate_sitemap(),
			'system_prompts'    => $this->generate_system_prompts(),
			'ai_instructions'   => $this->generate_ai_instructions(),
			'forbidden'         => $this->get_forbidden_functions(),
		];
	}

	/**
	 * Get context as formatted string for AI prompt injection
	 *
	 * ULTRA-COMPACT version: only essential data to stay under 10k chars.
	 * Detailed info (plugin docs, ACF fields, etc.) can be loaded on-demand.
	 * Uses separate prompt cache with shorter TTL (1 min).
	 *
	 * @return string
	 */
	public function get_context_as_prompt(): string {
		return $this->cache->get_prompt( fn() => $this->build_prompt_string() );
	}

	/**
	 * Build prompt string from context (used as prompt cache generator)
	 *
	 * @return string Prompt string.
	 */
	private function build_prompt_string(): string {
		$context = $this->get_context_for_chat();

		if ( empty( $context ) ) {
			return '';
		}

		$level = $context['user_profile']['level'] ?? 'intermediate';
		$si    = $context['system_info'] ?? [];

		// Build ultra-compact context (~500-1000 chars max)
		$prompt = "# SITE CONTEXT\n";
		$prompt .= sprintf( "User: %s | WP %s | Theme: %s\n", strtoupper( $level ), $si['wordpress_version'] ?? '?', $si['theme_name'] ?? '?' );

		// Plugins: only slugs
		$plugins = $context['plugins'] ?? [];
		if ( ! empty( $plugins ) ) {
			$slugs = array_map( fn( $p ) => $p['slug'] ?? '', $plugins );
			$prompt .= 'Plugins: ' . implode( ', ', array_filter( $slugs ) ) . "\n";
		}

		// CPT: only slugs
		$cpts = $context['custom_post_types'] ?? [];
		if ( ! empty( $cpts ) ) {
			$cpt_slugs = array_map( fn( $c ) => $c['name'] ?? '', $cpts );
			$prompt .= 'CPT: ' . implode( ', ', array_filter( $cpt_slugs ) ) . "\n";
		}

		// ACF: only group count
		$acf = $context['acf_fields'] ?? [];
		if ( ! empty( $acf ) ) {
			$prompt .= sprintf( "ACF: %d groups\n", count( $acf ) );
		}

		return $prompt;
	}

	/**
	 * Get the context cache instance
	 *
	 * @return ContextCache
	 */
	public function get_cache(): ContextCache {
		return $this->cache;
	}

	/**
	 * Check if stored context is valid
	 *
	 * @return bool
	 */
	public function is_context_valid(): bool {
		$context = $this->get_stored_context();
		$version = get_option( self::VERSION_OPTION );

		if ( ! $context || ! $version ) {
			return false;
		}

		// Check if version matches current system hash
		$current_hash = $this->get_system_hash();
		return $version === $current_hash;
	}

	/**
	 * Check if context is stale (needs refresh)
	 *
	 * @return bool
	 */
	public function is_context_stale(): bool {
		$context = $this->get_stored_context();

		if ( ! $context ) {
			return true;
		}

		$stored_hash = $context['meta']['system_hash'] ?? '';
		$current_hash = $this->get_system_hash();

		return $stored_hash !== $current_hash;
	}

	/**
	 * Store context document in database
	 *
	 * @param array $context Context document.
	 * @return bool
	 */
	private function store_context( array $context ): bool {
		update_option( self::CONTEXT_OPTION, $context, false );
		update_option( self::VERSION_OPTION, $context['meta']['system_hash'] ?? '', false );
		return true;
	}

	/**
	 * Generate meta information
	 *
	 * @return array
	 */
	private function generate_meta(): array {
		return [
			'version'      => '1.0',
			'generated_at' => current_time( 'c' ),
			'site_url'     => get_site_url(),
			'system_hash'  => $this->get_system_hash(),
		];
	}

	/**
	 * Generate user profile section
	 *
	 * @return array
	 */
	private function generate_user_profile(): array {
		$level = UserProfile::get_level() ?: 'intermediate';

		return [
			'user_id'               => get_current_user_id(),
			'level'                 => $level,
			'profile_system_prompt' => $this->system_prompts->get_profile_prompt( $level ),
			'discovery_rules'       => $this->system_prompts->get_discovery_rules( $level ),
			'proposal_rules'        => $this->system_prompts->get_proposal_rules( $level ),
			'execution_rules'       => $this->system_prompts->get_execution_rules( $level ),
		];
	}

	/**
	 * Generate system information section
	 *
	 * @return array
	 */
	private function generate_system_info(): array {
		global $wp_version, $wpdb;

		$theme = wp_get_theme();

		return [
			'wordpress_version' => $wp_version,
			'php_version'       => PHP_VERSION,
			'mysql_version'     => $wpdb->db_version(),
			'site_title'        => get_bloginfo( 'name' ),
			'site_url'          => get_site_url(),
			'home_url'          => get_home_url(),
			'admin_url'         => admin_url(),
			'locale'            => get_locale(),
			'timezone'          => wp_timezone_string(),
			'multisite'         => is_multisite(),
			'theme_name'        => $theme->get( 'Name' ),
			'theme_version'     => $theme->get( 'Version' ),
			'is_child_theme'    => $theme->parent() !== false,
			'parent_theme'      => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			'db_prefix'         => $wpdb->prefix,
			'db_charset'        => $wpdb->charset,
			'memory_limit'      => WP_MEMORY_LIMIT,
			'debug_mode'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
		];
	}

	/**
	 * Generate system analysis section
	 *
	 * Includes installed plugins, missing suggested plugins, and available vanilla solutions.
	 * This helps AI understand what tools are available and what can be done without plugins.
	 *
	 * @return array
	 */
	private function generate_system_analysis(): array {
		global $wp_version, $wpdb;

		// Get installed plugins
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$installed      = [];

		foreach ( $active_plugins as $plugin_path ) {
			if ( isset( $all_plugins[ $plugin_path ] ) ) {
				$installed[] = [
					'slug'    => dirname( $plugin_path ),
					'version' => $all_plugins[ $plugin_path ]['Version'],
					'status'  => 'active',
				];
			}
		}

		// Get missing suggested plugins
		$missing_suggested = [];
		$suggested_plugins = [
			'acf_pro'   => [ 'slug' => 'advanced-custom-fields-pro', 'reason' => 'Professional custom fields with repeaters and flexible content' ],
			'rank_math' => [ 'slug' => 'seo-by-rank-math', 'reason' => 'SEO management and optimization' ],
			'wpcode'    => [ 'slug' => 'insert-headers-and-footers', 'reason' => 'Safe code snippet management with easy rollback' ],
			'elementor' => [ 'slug' => 'elementor', 'reason' => 'Visual page builder for easier content creation' ],
		];

		$integrations = $this->plugin_detector->get_all_integrations();
		foreach ( $suggested_plugins as $key => $info ) {
			if ( empty( $integrations[ $key ]['active'] ) ) {
				$missing_suggested[] = [
					'slug'   => $info['slug'],
					'reason' => $info['reason'],
				];
			}
		}

		// Available vanilla WordPress solutions (always available)
		$vanilla_solutions = [
			'custom_post_types',
			'custom_taxonomy',
			'meta_fields',
			'gutenberg_blocks',
			'wordpress_hooks',
			'database_queries',
			'rest_api',
			'transients',
			'wp_cron',
			'shortcodes',
			'widgets',
			'user_roles',
			'capabilities',
		];

		return [
			'wordpress'                 => $wp_version,
			'php'                       => PHP_VERSION,
			'mysql'                     => $wpdb->db_version(),
			'installed_plugins'         => $installed,
			'missing_suggested_plugins' => $missing_suggested,
			'available_vanilla_solutions' => $vanilla_solutions,
		];
	}

	/**
	 * Generate plugins information (compact: only slug, version, docs_url)
	 *
	 * @return array
	 */
	private function generate_plugins_info(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$result         = [];

		foreach ( $active_plugins as $plugin_path ) {
			if ( ! isset( $all_plugins[ $plugin_path ] ) ) {
				continue;
			}

			$plugin  = $all_plugins[ $plugin_path ];
			$slug    = dirname( $plugin_path );
			$version = $plugin['Version'];

			// Get only docs_url from repository (lazy-load rest on demand)
			$docs = $this->docs_repository->get_plugin_docs( $slug, $version );

			$result[] = [
				'slug'     => $slug,
				'version'  => $version,
				'docs_url' => $docs['docs_url'] ?? null,
			];
		}

		return $result;
	}

	/**
	 * Generate custom post types information (compact: only name, label)
	 *
	 * @return array
	 */
	private function generate_cpt_info(): array {
		$cpts   = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
		$result = [];

		foreach ( $cpts as $cpt ) {
			$result[] = [
				'name'  => $cpt->name,
				'label' => $cpt->label,
			];
		}

		return $result;
	}

	/**
	 * Generate taxonomies information (compact: only name, label)
	 *
	 * @return array
	 */
	private function generate_taxonomies_info(): array {
		$taxonomies = get_taxonomies( [ 'public' => true, '_builtin' => false ], 'objects' );
		$result     = [];

		foreach ( $taxonomies as $tax ) {
			$result[] = [
				'name'  => $tax->name,
				'label' => $tax->label,
			];
		}

		return $result;
	}

	/**
	 * Generate ACF field groups information (compact: only title, field_count)
	 *
	 * @return array|null
	 */
	private function generate_acf_info(): ?array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return null;
		}

		$groups = acf_get_field_groups();
		$result = [];

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group['key'] );

			$result[] = [
				'title'       => $group['title'],
				'field_count' => is_array( $fields ) ? count( $fields ) : 0,
			];
		}

		return $result;
	}

	/**
	 * Simplify ACF location rules
	 *
	 * @param array $location ACF location rules.
	 * @return array
	 */
	private function simplify_acf_location( array $location ): array {
		$simplified = [];

		foreach ( $location as $group ) {
			$rules = [];
			foreach ( $group as $rule ) {
				$rules[] = sprintf(
					'%s %s %s',
					$rule['param'] ?? '',
					$rule['operator'] ?? '',
					$rule['value'] ?? ''
				);
			}
			$simplified[] = implode( ' AND ', $rules );
		}

		return $simplified;
	}

	/**
	 * Generate integrations information
	 *
	 * @return array
	 */
	private function generate_integrations_info(): array {
		$integrations = $this->plugin_detector->get_all_integrations();
		$result = [];

		foreach ( $integrations as $key => $status ) {
			if ( $status['active'] ) {
				$result[ $key ] = [
					'name'       => $status['name'],
					'active'     => true,
					'compatible' => $status['compatible'],
					'version'    => $status['version'] ?? null,
				];
			}
		}

		return $result;
	}

	/**
	 * Generate sitemap
	 *
	 * @return array
	 */
	private function generate_sitemap(): array {
		return $this->context_collector->get_sitemap( 100 );
	}

	/**
	 * Generate system prompts for all phases
	 *
	 * @return array
	 */
	private function generate_system_prompts(): array {
		$level = UserProfile::get_level() ?: 'intermediate';

		return [
			'universal'  => $this->system_prompts->get_universal_rules(),
			'discovery'  => $this->system_prompts->get_discovery_rules( $level ),
			'proposal'   => $this->system_prompts->get_proposal_rules( $level ),
			'execution'  => $this->system_prompts->get_execution_rules( $level ),
		];
	}

	/**
	 * Generate AI instructions by category
	 *
	 * @return array
	 */
	private function generate_ai_instructions(): array {
		return [
			'wordpress' => [
				'wp_insert_post()',
				'wp_update_post()',
				'wp_delete_post()',
				'get_post()',
				'get_posts()',
				'WP_Query',
				'register_post_type()',
				'register_taxonomy()',
				'add_action()',
				'add_filter()',
				'get_option()',
				'update_option()',
				'add_shortcode()',
				'wp_enqueue_script()',
				'wp_enqueue_style()',
			],
			'woocommerce' => [
				'wc_get_product()',
				'wc_create_product()',
				'WC()->cart',
				'WC()->session',
				'wc_get_orders()',
				'wc_create_order()',
				'wc_add_notice()',
				'wc_price()',
				'wc_get_template()',
			],
			'acf' => [
				'get_field()',
				'update_field()',
				'get_field_object()',
				'acf_add_local_field_group()',
				'acf_add_local_field()',
				'acf_get_field_groups()',
				'have_rows()',
				'the_row()',
				'get_sub_field()',
			],
			'elementor' => [
				'\Elementor\Plugin::instance()',
				'\Elementor\Controls_Manager',
				'\Elementor\Widget_Base',
				'elementor_get_option()',
				'\Elementor\Core\Documents_Manager',
			],
			'database' => [
				'$wpdb->get_results()',
				'$wpdb->get_row()',
				'$wpdb->get_var()',
				'$wpdb->insert()',
				'$wpdb->update()',
				'$wpdb->delete()',
				'$wpdb->prepare()',
				'$wpdb->query()',
			],
		];
	}

	/**
	 * Get forbidden functions list (compact: only critical 3)
	 *
	 * @return array
	 */
	private function get_forbidden_functions(): array {
		return [
			'eval()',
			'exec()',
			'shell_exec()',
		];
	}

	/**
	 * Get system hash for change detection
	 *
	 * @return string
	 */
	private function get_system_hash(): string {
		global $wp_version;

		$hash_data = [
			'wp_version'     => $wp_version,
			'php_version'    => PHP_VERSION,
			'active_plugins' => get_option( 'active_plugins', [] ),
			'theme'          => get_stylesheet(),
			'user_level'     => UserProfile::get_level(),
		];

		return md5( serialize( $hash_data ) );
	}

	/**
	 * Force refresh context
	 *
	 * Invalidates all cache levels and regenerates fresh context.
	 *
	 * @return array
	 */
	public function refresh(): array {
		// Invalidate both cache levels
		$this->cache->invalidate();

		// Also clear legacy transient
		delete_transient( 'creator_site_context' );

		return $this->generate( true );
	}

	/**
	 * Get context generation timestamp
	 *
	 * @return string|null
	 */
	public function get_generated_at(): ?string {
		$context = $this->get_stored_context();
		return $context['meta']['generated_at'] ?? null;
	}

	/**
	 * Delete stored context
	 *
	 * @return bool
	 */
	public function delete(): bool {
		delete_option( self::CONTEXT_OPTION );
		delete_option( self::VERSION_OPTION );
		return true;
	}
}
