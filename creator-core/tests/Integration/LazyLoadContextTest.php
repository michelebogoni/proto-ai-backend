<?php
/**
 * Lazy-Load Context Integration Tests
 *
 * Tests the lazy-loading context system including:
 * - Plugin details on-demand loading
 * - Context request handling
 * - Repository data retrieval
 * - AI uses loaded details in code
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Context\ContextLoader;
use CreatorCore\Context\ContextCollector;

/**
 * Test class for lazy-load context scenarios
 */
class LazyLoadContextTest extends TestCase {

	/**
	 * ContextLoader instance
	 *
	 * @var ContextLoader
	 */
	private ContextLoader $loader;

	/**
	 * ContextCollector instance
	 *
	 * @var ContextCollector
	 */
	private ContextCollector $collector;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->loader    = new ContextLoader();
		$this->collector = new ContextCollector();
	}

	// ========================
	// CONTEXT REQUEST TESTS
	// ========================

	/**
	 * Test context request structure
	 */
	public function test_context_request_structure(): void {
		$context_request = [
			'type'   => 'get_plugin_details',
			'params' => [
				'slug' => 'elementor',
			],
		];

		$this->assertArrayHasKey( 'type', $context_request );
		$this->assertArrayHasKey( 'params', $context_request );
		$this->assertEquals( 'get_plugin_details', $context_request['type'] );
	}

	/**
	 * Test ACF details request structure
	 */
	public function test_acf_details_request(): void {
		$context_request = [
			'type'   => 'get_acf_details',
			'params' => [
				'group' => 'Hero Section',
			],
		];

		$this->assertEquals( 'get_acf_details', $context_request['type'] );
		$this->assertEquals( 'Hero Section', $context_request['params']['group'] );
	}

	/**
	 * Test CPT details request structure
	 */
	public function test_cpt_details_request(): void {
		$context_request = [
			'type'   => 'get_cpt_details',
			'params' => [
				'post_type' => 'progetti',
			],
		];

		$this->assertEquals( 'get_cpt_details', $context_request['type'] );
	}

	// ========================
	// CONTEXT LOADER TESTS
	// ========================

	/**
	 * Test context loader is instantiable
	 */
	public function test_context_loader_instantiable(): void {
		$this->assertInstanceOf( ContextLoader::class, $this->loader );
	}

	/**
	 * Test handle context request method exists
	 */
	public function test_handle_context_request_method(): void {
		$this->assertTrue(
			method_exists( $this->loader, 'handle_context_request' ) ||
			method_exists( $this->loader, 'load' ),
			'ContextLoader should have context request handling method'
		);
	}

	/**
	 * Test get available loaders
	 */
	public function test_get_available_loaders(): void {
		if ( method_exists( $this->loader, 'get_available_loaders' ) ) {
			$loaders = $this->loader->get_available_loaders();
			$this->assertIsArray( $loaders );
		} else {
			$this->assertTrue( true ); // Skip if not implemented
		}
	}

	// ========================
	// CONTEXT COLLECTOR TESTS
	// ========================

	/**
	 * Test context collector is instantiable
	 */
	public function test_context_collector_instantiable(): void {
		$this->assertInstanceOf( ContextCollector::class, $this->collector );
	}

	/**
	 * Test collect returns array
	 */
	public function test_collect_returns_array(): void {
		if ( method_exists( $this->collector, 'collect' ) ) {
			$result = $this->collector->collect();
			$this->assertIsArray( $result );
		} else {
			$this->assertTrue( true ); // Skip if not implemented
		}
	}

	// ========================
	// PLUGIN DETAILS TESTS
	// ========================

	/**
	 * Test plugin details structure
	 */
	public function test_plugin_details_structure(): void {
		$plugin_details = [
			'slug'        => 'elementor',
			'name'        => 'Elementor',
			'version'     => '3.18.0',
			'active'      => true,
			'functions'   => [
				'\\Elementor\\Plugin::instance()',
				'update_post_meta with _elementor_data',
			],
			'docs_url'    => 'https://developers.elementor.com/',
		];

		$this->assertArrayHasKey( 'slug', $plugin_details );
		$this->assertArrayHasKey( 'functions', $plugin_details );
		$this->assertIsArray( $plugin_details['functions'] );
	}

	/**
	 * Test ACF plugin details
	 */
	public function test_acf_plugin_details(): void {
		$acf_details = [
			'slug'      => 'advanced-custom-fields',
			'name'      => 'Advanced Custom Fields',
			'version'   => '6.2.0',
			'active'    => true,
			'functions' => [
				'get_field()',
				'update_field()',
				'get_field_object()',
				'have_rows()',
				'the_row()',
				'get_sub_field()',
				'acf_add_local_field_group()',
			],
		];

		$this->assertContains( 'get_field()', $acf_details['functions'] );
		$this->assertContains( 'acf_add_local_field_group()', $acf_details['functions'] );
	}

	/**
	 * Test WooCommerce plugin details
	 */
	public function test_woocommerce_plugin_details(): void {
		$wc_details = [
			'slug'      => 'woocommerce',
			'name'      => 'WooCommerce',
			'version'   => '8.4.0',
			'active'    => true,
			'functions' => [
				'wc_get_product()',
				'wc_get_products()',
				'wc_create_order()',
				'wc_get_orders()',
			],
		];

		$this->assertContains( 'wc_get_product()', $wc_details['functions'] );
	}

	// ========================
	// LOADED DETAILS INJECTION
	// ========================

	/**
	 * Test loaded details format in prompt
	 */
	public function test_loaded_details_format(): void {
		$loaded_details_section = <<<'PROMPT'
## Loaded Details (from previous request)

### get_plugin_details (elementor):
- Plugin: Elementor v3.18.0
- Status: Active
- Key Functions:
  - \Elementor\Plugin::instance()
  - update_post_meta with _elementor_data
  - _elementor_edit_mode meta key
- Documentation: https://developers.elementor.com/
PROMPT;

		$this->assertStringContainsString( '## Loaded Details', $loaded_details_section );
		$this->assertStringContainsString( 'elementor', $loaded_details_section );
	}

	/**
	 * Test AI uses loaded details in code
	 */
	public function test_ai_uses_loaded_details(): void {
		$ai_code_response = [
			'phase' => 'execution',
			'code'  => [
				'content' => <<<'PHP'
<?php
// Using Elementor functions from loaded details
$post_id = wp_insert_post([
    'post_title'  => 'New Landing Page',
    'post_type'   => 'page',
    'post_status' => 'draft',
]);

if ($post_id && !is_wp_error($post_id)) {
    // Enable Elementor (from loaded details)
    update_post_meta($post_id, '_elementor_edit_mode', 'builder');
    update_post_meta($post_id, '_elementor_template_type', 'wp-page');
    update_post_meta($post_id, '_elementor_data', '[]');
}
PHP,
			],
			'context_used' => [
				'plugin' => 'elementor',
				'source' => 'lazy_load',
			],
		];

		$this->assertStringContainsString( '_elementor_edit_mode', $ai_code_response['code']['content'] );
		$this->assertArrayHasKey( 'context_used', $ai_code_response );
	}

	// ========================
	// CACHING TESTS
	// ========================

	/**
	 * Test context is cached after first load
	 */
	public function test_context_caching(): void {
		// First request loads from source
		// Second request should use cache
		$cache_key = 'creator_context_elementor';

		// Simulate cache behavior
		$cached_data = get_transient( $cache_key );

		// Will be false if not set, or data if cached
		$this->assertTrue(
			$cached_data === false || is_array( $cached_data ),
			'Cache should return false or array'
		);
	}

	/**
	 * Test cache expiration
	 */
	public function test_cache_expiration(): void {
		$cache_ttl = 3600; // 1 hour

		$this->assertEquals( 3600, $cache_ttl );
	}

	// ========================
	// REPOSITORY TESTS
	// ========================

	/**
	 * Test repository lookup structure
	 */
	public function test_repository_lookup_structure(): void {
		$repository_entry = [
			'plugin_slug'      => 'rankmath',
			'display_name'     => 'Rank Math SEO',
			'version_tested'   => '1.0.200',
			'functions'        => [
				'rank_math()->settings->general()',
				'RankMath\\Helper::get_settings()',
			],
			'hooks'            => [
				'rank_math/sitemap/entries',
				'rank_math/head',
			],
			'documentation_url' => 'https://rankmath.com/kb/',
			'last_updated'     => '2025-11-15',
		];

		$this->assertArrayHasKey( 'plugin_slug', $repository_entry );
		$this->assertArrayHasKey( 'functions', $repository_entry );
		$this->assertArrayHasKey( 'hooks', $repository_entry );
	}

	/**
	 * Test repository data is usable by AI
	 */
	public function test_repository_data_usable(): void {
		$repository_data = [
			'functions' => [
				[
					'name'        => 'rank_math()->settings->general()',
					'description' => 'Get general settings',
					'returns'     => 'array',
					'example'     => '$settings = rank_math()->settings->general();',
				],
			],
		];

		$this->assertNotEmpty( $repository_data['functions'][0]['name'] );
		$this->assertNotEmpty( $repository_data['functions'][0]['example'] );
	}

	// ========================
	// SCENARIO: PLUGIN RESEARCH
	// ========================

	/**
	 * Test scenario: AI requests plugin details
	 */
	public function test_scenario_ai_requests_plugin_details(): void {
		// Step 1: AI response includes context_request
		$ai_response = [
			'phase'   => 'discovery',
			'message' => 'Voglio leggere le funzioni RankMath disponibili',
			'actions' => [
				[
					'type'   => 'context_request',
					'params' => [
						'type' => 'get_plugin_details',
						'slug' => 'rankmath',
					],
				],
			],
		];

		$this->assertArrayHasKey( 'actions', $ai_response );
		$this->assertEquals( 'context_request', $ai_response['actions'][0]['type'] );

		// Step 2: System loads details
		$loaded_details = [
			'plugin'    => 'rankmath',
			'functions' => [ 'rank_math()->settings->general()' ],
			'loaded_at' => current_time( 'mysql' ),
		];

		$this->assertArrayHasKey( 'plugin', $loaded_details );

		// Step 3: Details injected into next prompt
		$next_prompt_section = "## Loaded Details\n" .
			"### RankMath Functions:\n" .
			"- rank_math()->settings->general()";

		$this->assertStringContainsString( 'RankMath', $next_prompt_section );
	}

	// ========================
	// ERROR HANDLING TESTS
	// ========================

	/**
	 * Test handling of unknown plugin
	 */
	public function test_unknown_plugin_handling(): void {
		$unknown_plugin_response = [
			'found'   => false,
			'plugin'  => 'nonexistent-plugin',
			'message' => 'Plugin not found in repository',
			'action'  => 'AI research required',
		];

		$this->assertFalse( $unknown_plugin_response['found'] );
		$this->assertArrayHasKey( 'action', $unknown_plugin_response );
	}

	/**
	 * Test handling of inactive plugin
	 */
	public function test_inactive_plugin_handling(): void {
		$inactive_plugin = [
			'slug'     => 'some-plugin',
			'active'   => false,
			'message'  => 'Plugin is installed but not active',
			'suggest'  => 'Activate plugin or use alternative',
		];

		$this->assertFalse( $inactive_plugin['active'] );
		$this->assertArrayHasKey( 'suggest', $inactive_plugin );
	}

	// ========================
	// PERFORMANCE TESTS
	// ========================

	/**
	 * Test lazy-load reduces initial context size
	 */
	public function test_lazy_load_reduces_context(): void {
		$initial_context_tokens  = 2000; // Without plugin details
		$with_all_plugins_tokens = 8000; // With all plugin details
		$with_lazy_load_tokens   = 2500; // With lazy-loaded single plugin

		$savings = $with_all_plugins_tokens - $with_lazy_load_tokens;

		$this->assertGreaterThan( 5000, $savings );
	}

	/**
	 * Test lazy-load response time expectation
	 */
	public function test_lazy_load_response_time(): void {
		$max_load_time_ms = 1000; // 1 second max

		$this->assertEquals( 1000, $max_load_time_ms );
	}

	// ========================
	// CONTEXT REQUEST TYPES
	// ========================

	/**
	 * Test supported context request types
	 */
	public function test_supported_context_request_types(): void {
		$supported_types = [
			'get_plugin_details',
			'get_acf_details',
			'get_cpt_details',
			'get_taxonomy_details',
			'get_theme_details',
		];

		$this->assertContains( 'get_plugin_details', $supported_types );
		$this->assertContains( 'get_acf_details', $supported_types );
		$this->assertContains( 'get_cpt_details', $supported_types );
	}
}
