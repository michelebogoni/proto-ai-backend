<?php
/**
 * Code Executor Integration Tests
 *
 * Tests the complete code execution flow including:
 * - WP Code snippet creation
 * - Custom file fallback
 * - Direct execution
 * - Security validation
 * - Rollback functionality
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\CodeExecutor;

/**
 * Test class for CodeExecutor integration scenarios
 */
class CodeExecutorIntegrationTest extends TestCase {

	/**
	 * CodeExecutor instance
	 *
	 * @var CodeExecutor
	 */
	private CodeExecutor $executor;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->executor = new CodeExecutor();
	}

	// ========================
	// SECURITY VALIDATION TESTS
	// ========================

	/**
	 * Test forbidden functions are blocked
	 *
	 * @dataProvider forbiddenFunctionsProvider
	 */
	public function test_forbidden_function_blocked( string $code, string $expected_violation ): void {
		$result = $this->executor->validate_code_security( $code );

		$this->assertFalse( $result['passed'] );
		$this->assertContains( $expected_violation, $result['violations'] );
	}

	/**
	 * Data provider for forbidden functions
	 */
	public static function forbiddenFunctionsProvider(): array {
		return [
			'exec function'        => [ 'exec("ls -la");', 'exec' ],
			'shell_exec function'  => [ 'shell_exec("whoami");', 'shell_exec' ],
			'system function'      => [ 'system("pwd");', 'system' ],
			'eval function'        => [ 'eval($code);', 'eval' ],
			'passthru function'    => [ 'passthru("cat /etc/passwd");', 'passthru' ],
			'popen function'       => [ '$fp = popen("/bin/ls", "r");', 'popen' ],
			'proc_open function'   => [ 'proc_open("cmd", $desc, $pipes);', 'proc_open' ],
			'unlink function'      => [ 'unlink("/tmp/file.txt");', 'unlink' ],
			'rmdir function'       => [ 'rmdir("/tmp/dir");', 'rmdir' ],
			'backtick execution'   => [ '$output = `ls -la`;', 'backtick shell execution' ],
		];
	}

	/**
	 * Test safe code passes security check
	 */
	public function test_safe_code_passes_security(): void {
		$safe_code = <<<'PHP'
<?php
$post_id = wp_insert_post([
    'post_title'   => 'Test Post',
    'post_content' => 'Test content',
    'post_status'  => 'publish',
    'post_type'    => 'post',
]);

if ($post_id && !is_wp_error($post_id)) {
    update_post_meta($post_id, 'custom_key', 'custom_value');
}

return ['success' => true, 'post_id' => $post_id];
PHP;

		$result = $this->executor->validate_code_security( $safe_code );

		$this->assertTrue( $result['passed'] );
		$this->assertEmpty( $result['violations'] );
	}

	/**
	 * Test dangerous SQL patterns are blocked
	 */
	public function test_dangerous_sql_blocked(): void {
		$dangerous_sql = 'DROP TABLE wp_posts;';

		$result = $this->executor->validate_code_security( $dangerous_sql );

		$this->assertFalse( $result['passed'] );
		$this->assertContains( 'dangerous SQL statement', $result['violations'] );
	}

	/**
	 * Test preg_replace with /e modifier is blocked
	 */
	public function test_preg_replace_e_modifier_blocked(): void {
		$dangerous_code = "preg_replace('/test/e', 'phpinfo()', \$input);";

		$result = $this->executor->validate_code_security( $dangerous_code );

		$this->assertFalse( $result['passed'] );
		$this->assertContains( 'preg_replace with /e modifier', $result['violations'] );
	}

	// ========================
	// CODE EXECUTION TESTS
	// ========================

	/**
	 * Test execution with empty code returns error
	 */
	public function test_execute_empty_code_returns_error(): void {
		$code_data = [
			'content' => '',
			'title'   => 'Empty Code',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( CodeExecutor::STATUS_ERROR, $result['status'] );
		$this->assertStringContainsString( 'Empty code', $result['message'] );
	}

	/**
	 * Test execution with forbidden code is blocked
	 */
	public function test_execute_forbidden_code_blocked(): void {
		$code_data = [
			'content' => '<?php exec("whoami");',
			'title'   => 'Dangerous Code',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( CodeExecutor::STATUS_BLOCKED, $result['status'] );
		$this->assertArrayHasKey( 'violations', $result );
	}

	/**
	 * Test execution returns expected result structure
	 */
	public function test_execute_returns_expected_structure(): void {
		$code_data = [
			'content'     => '<?php echo "Hello World";',
			'title'       => 'Hello World Snippet',
			'description' => 'A simple hello world',
			'language'    => 'php',
			'location'    => 'everywhere',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'timestamp', $result );
	}

	/**
	 * Test get available methods returns all methods
	 */
	public function test_get_available_methods(): void {
		$methods = $this->executor->get_available_methods();

		$this->assertArrayHasKey( CodeExecutor::METHOD_WPCODE, $methods );
		$this->assertArrayHasKey( CodeExecutor::METHOD_CUSTOM_FILE, $methods );
		$this->assertArrayHasKey( CodeExecutor::METHOD_DIRECT, $methods );

		// Direct is always available (last resort)
		$this->assertTrue( $methods[ CodeExecutor::METHOD_DIRECT ] );
	}

	// ========================
	// CODE TYPE DETECTION TESTS
	// ========================

	/**
	 * Test PHP code type detection
	 */
	public function test_detect_php_code_type(): void {
		$code_data = [
			'content'  => '<?php add_action("init", function() { /* ... */ });',
			'title'    => 'PHP Hook',
			'language' => 'php',
		];

		$result = $this->executor->execute( $code_data );

		// Should attempt execution (success depends on environment)
		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test CSS code type detection
	 */
	public function test_detect_css_code_type(): void {
		$code_data = [
			'content'  => '.my-class { color: red; background: blue; }',
			'title'    => 'Custom CSS',
			'language' => 'css',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test JavaScript code type detection
	 */
	public function test_detect_js_code_type(): void {
		$code_data = [
			'content'  => 'document.addEventListener("DOMContentLoaded", function() { console.log("Ready"); });',
			'title'    => 'Custom JS',
			'language' => 'javascript',
		];

		$result = $this->executor->execute( $code_data );

		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// ROLLBACK TESTS
	// ========================

	/**
	 * Test rollback with WP Code method
	 */
	public function test_rollback_wpcode_snippet(): void {
		$result = $this->executor->rollback( 123, CodeExecutor::METHOD_WPCODE );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test rollback with custom file method
	 */
	public function test_rollback_custom_file(): void {
		$result = $this->executor->rollback( 'mod_123_abc', CodeExecutor::METHOD_CUSTOM_FILE );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test rollback with direct execution returns error
	 */
	public function test_rollback_direct_execution_not_possible(): void {
		$result = $this->executor->rollback( 'anything', CodeExecutor::METHOD_DIRECT );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( CodeExecutor::STATUS_ERROR, $result['status'] );
		$this->assertStringContainsString( 'cannot be automatically rolled back', $result['message'] );
	}

	/**
	 * Test rollback modification convenience method
	 */
	public function test_rollback_modification(): void {
		$result = $this->executor->rollback_modification( 'mod_456_def' );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// CPT CREATION SCENARIO
	// ========================

	/**
	 * Test CPT creation code execution
	 */
	public function test_scenario_cpt_creation(): void {
		$cpt_code = <<<'PHP'
<?php
/**
 * Register Projects CPT
 */
add_action('init', function() {
    register_post_type('progetti', [
        'label'               => 'Progetti',
        'public'              => true,
        'has_archive'         => true,
        'supports'            => ['title', 'editor', 'thumbnail'],
        'menu_icon'           => 'dashicons-portfolio',
        'show_in_rest'        => true,
    ]);
});
PHP;

		$code_data = [
			'content'      => $cpt_code,
			'title'        => 'Register CPT Progetti',
			'description'  => 'Registers a custom post type for projects',
			'language'     => 'php',
			'location'     => 'everywhere',
			'auto_execute' => false,
		];

		// Security check should pass
		$security = $this->executor->validate_code_security( $cpt_code );
		$this->assertTrue( $security['passed'], 'CPT code should pass security check' );

		// Execution should return result
		$result = $this->executor->execute( $code_data );
		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test ACF field group creation code execution
	 */
	public function test_scenario_acf_field_creation(): void {
		$acf_code = <<<'PHP'
<?php
/**
 * Register ACF Field Group for Projects
 */
add_action('acf/init', function() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group([
            'key'      => 'group_project_details',
            'title'    => 'Project Details',
            'fields'   => [
                [
                    'key'   => 'field_project_budget',
                    'label' => 'Budget',
                    'name'  => 'project_budget',
                    'type'  => 'number',
                ],
                [
                    'key'   => 'field_project_date',
                    'label' => 'Start Date',
                    'name'  => 'project_date',
                    'type'  => 'date_picker',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'progetti',
                    ],
                ],
            ],
        ]);
    }
});
PHP;

		$code_data = [
			'content'     => $acf_code,
			'title'       => 'Register ACF Fields for Projects',
			'description' => 'Adds budget and date fields to Projects CPT',
			'language'    => 'php',
		];

		// Security check should pass
		$security = $this->executor->validate_code_security( $acf_code );
		$this->assertTrue( $security['passed'], 'ACF code should pass security check' );

		$result = $this->executor->execute( $code_data );
		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// POST CREATION SCENARIO
	// ========================

	/**
	 * Test post creation code execution
	 */
	public function test_scenario_post_creation(): void {
		$post_code = <<<'PHP'
<?php
$post_id = wp_insert_post([
    'post_title'   => 'Nuovo Progetto',
    'post_content' => 'Contenuto del progetto...',
    'post_status'  => 'draft',
    'post_type'    => 'progetti',
    'post_author'  => get_current_user_id(),
]);

if ($post_id && !is_wp_error($post_id)) {
    update_post_meta($post_id, 'project_budget', 50000);
    update_post_meta($post_id, 'project_date', '2025-01-15');

    return [
        'success' => true,
        'post_id' => $post_id,
        'message' => 'Progetto creato con successo',
    ];
}

return ['success' => false, 'message' => 'Errore nella creazione'];
PHP;

		$code_data = [
			'content'      => $post_code,
			'title'        => 'Create New Project Post',
			'description'  => 'Creates a new project with budget and date',
			'language'     => 'php',
			'auto_execute' => true,
		];

		// Security check should pass
		$security = $this->executor->validate_code_security( $post_code );
		$this->assertTrue( $security['passed'] );

		$result = $this->executor->execute( $code_data );
		$this->assertArrayHasKey( 'status', $result );
	}

	// ========================
	// ELEMENTOR PAGE SCENARIO
	// ========================

	/**
	 * Test Elementor page creation code
	 */
	public function test_scenario_elementor_page_creation(): void {
		$elementor_code = <<<'PHP'
<?php
// Create page with Elementor enabled
$post_id = wp_insert_post([
    'post_title'   => 'Landing Page',
    'post_content' => '',
    'post_status'  => 'draft',
    'post_type'    => 'page',
    'post_author'  => get_current_user_id(),
]);

if ($post_id && !is_wp_error($post_id)) {
    // Enable Elementor
    update_post_meta($post_id, '_elementor_edit_mode', 'builder');
    update_post_meta($post_id, '_elementor_template_type', 'wp-page');
    update_post_meta($post_id, '_elementor_data', '[]');

    return [
        'success'  => true,
        'post_id'  => $post_id,
        'edit_url' => admin_url("post.php?post={$post_id}&action=elementor"),
    ];
}

return ['success' => false];
PHP;

		$security = $this->executor->validate_code_security( $elementor_code );
		$this->assertTrue( $security['passed'] );
	}

	// ========================
	// WHITELIST VALIDATION
	// ========================

	/**
	 * Test WordPress core functions are allowed
	 */
	public function test_wordpress_core_functions_allowed(): void {
		$wp_code = <<<'PHP'
<?php
$posts = get_posts(['post_type' => 'post', 'numberposts' => 5]);
$option = get_option('blogname');
$terms = get_terms(['taxonomy' => 'category']);
$meta = get_post_meta(1, 'key', true);
PHP;

		$security = $this->executor->validate_code_security( $wp_code );
		$this->assertTrue( $security['passed'] );
	}

	/**
	 * Test ACF functions are allowed
	 */
	public function test_acf_functions_allowed(): void {
		$acf_code = <<<'PHP'
<?php
$value = get_field('my_field', $post_id);
update_field('my_field', 'new_value', $post_id);
$field_object = get_field_object('my_field');

if (have_rows('repeater_field')) {
    while (have_rows('repeater_field')) {
        the_row();
        $sub_value = get_sub_field('sub_field');
    }
}
PHP;

		$security = $this->executor->validate_code_security( $acf_code );
		$this->assertTrue( $security['passed'] );
	}

	/**
	 * Test WooCommerce functions are allowed
	 */
	public function test_woocommerce_functions_allowed(): void {
		$wc_code = <<<'PHP'
<?php
$product = wc_get_product(123);
$products = wc_get_products(['limit' => 10]);
$orders = wc_get_orders(['status' => 'processing']);
PHP;

		$security = $this->executor->validate_code_security( $wc_code );
		$this->assertTrue( $security['passed'] );
	}

	// ========================
	// EDGE CASES
	// ========================

	/**
	 * Test execution with context for snapshot
	 */
	public function test_execute_with_context_for_snapshot(): void {
		$code_data = [
			'content'  => '<?php echo "test";',
			'title'    => 'Test with Context',
			'language' => 'php',
		];

		$context = [
			'chat_id'    => 123,
			'message_id' => 456,
			'action_id'  => 789,
		];

		$result = $this->executor->execute( $code_data, $context );

		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test delete snippet method
	 */
	public function test_delete_snippet(): void {
		$result = $this->executor->delete_snippet( 999 );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * Test get custom modifications
	 */
	public function test_get_custom_modifications(): void {
		$modifications = $this->executor->get_custom_modifications();

		$this->assertIsArray( $modifications );
	}

	/**
	 * Test get custom file manager
	 */
	public function test_get_custom_file_manager(): void {
		$manager = $this->executor->get_custom_file_manager();

		$this->assertNotNull( $manager );
	}
}
