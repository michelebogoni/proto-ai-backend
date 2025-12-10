<?php
/**
 * Universal PHP Engine Integration Tests
 *
 * End-to-end tests for the Universal PHP Engine pattern.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\ActionDispatcher;
use CreatorCore\Executor\ActionResult;
use CreatorCore\Executor\Handlers\ExecutePHPHandler;

/**
 * Integration test class for Universal PHP Engine
 */
class UniversalPHPEngineTest extends TestCase {

	/**
	 * Dispatcher instance
	 *
	 * @var ActionDispatcher
	 */
	private ActionDispatcher $dispatcher;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dispatcher = new ActionDispatcher();
	}

	/**
	 * Test complete flow: AI-generated action format to execution
	 */
	public function test_complete_ai_action_flow(): void {
		// Simulate AI response format
		$ai_action = [
			'type'    => 'execute_code',
			'target'  => 'system',
			'details' => [
				'description'    => 'Create a simple greeting function',
				'code'           => '
					function creator_test_greeting($name) {
						return "Hello, " . $name . "!";
					}
					$result = creator_test_greeting("World");
					echo $result;
					return $result;
				',
				'estimated_risk' => 'low',
			],
			'message' => 'Created greeting function',
		];

		$result = $this->dispatcher->dispatch( $ai_action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertStringContainsString( 'Hello, World!', $result->getOutput() );
		$this->assertEquals( 'Hello, World!', $result->getData()['result'] );
	}

	/**
	 * Test security: Multiple dangerous patterns blocked
	 */
	public function test_security_blocks_all_dangerous_patterns(): void {
		$dangerous_codes = [
			'exec("whoami");'                              => 'exec',
			'shell_exec("ls");'                            => 'shell_exec',
			'system("cat /etc/passwd");'                   => 'system',
			'passthru("id");'                              => 'passthru',
			'popen("ls", "r");'                            => 'popen',
			'proc_open("ls", [], $pipes);'                 => 'proc_open',
			'eval("echo 1;");'                             => 'eval',
			'assert("true");'                              => 'assert',
			'$x = `ls -la`;'                               => 'backticks',
			'unlink("/tmp/file");'                         => 'unlink',
			'include("/etc/passwd");'                      => 'include',
			'require_once("/var/www/evil.php");'           => 'require_once',
		];

		foreach ( $dangerous_codes as $code => $description ) {
			$action = [
				'type'    => 'execute_code',
				'details' => [
					'code' => $code,
				],
			];

			$result = $this->dispatcher->dispatch( $action );

			$this->assertTrue(
				$result->isFailure(),
				"Failed to block dangerous pattern: {$description}"
			);
			$this->assertStringContainsString(
				'restricted',
				strtolower( $result->getError() ),
				"Error message should mention restriction for: {$description}"
			);
		}
	}

	/**
	 * Test complex WordPress-like operations (simulated)
	 */
	public function test_wordpress_like_operations(): void {
		// Simulate WordPress-like post creation logic
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'description' => 'Simulate WordPress post creation',
				'code'        => '
					// Simulate wp_insert_post behavior
					$post_data = [
						"post_title"   => "Test Post",
						"post_content" => "This is test content",
						"post_status"  => "publish",
						"post_type"    => "post",
					];

					// Simulate success with ID
					$post_id = 12345;

					// Return result like WordPress would
					echo "Post created successfully with ID: " . $post_id;
					return $post_id;
				',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertStringContainsString( '12345', $result->getOutput() );
		$this->assertEquals( 12345, $result->getData()['result'] );
	}

	/**
	 * Test error handling and recovery
	 */
	public function test_error_handling(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '
					try {
						throw new Exception("Simulated error");
					} catch (Exception $e) {
						echo "Caught: " . $e->getMessage();
						return "handled";
					}
				',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertStringContainsString( 'Caught: Simulated error', $result->getOutput() );
		$this->assertEquals( 'handled', $result->getData()['result'] );
	}

	/**
	 * Test uncaught exception is captured
	 */
	public function test_uncaught_exception_captured(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'throw new Exception("Uncaught test exception");',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'Uncaught test exception', $result->getError() );
	}

	/**
	 * Test multiple outputs are captured
	 */
	public function test_multiple_outputs_captured(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '
					echo "Line 1\n";
					print "Line 2\n";
					printf("Line %d\n", 3);
				',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );
		$output = $result->getOutput();
		$this->assertStringContainsString( 'Line 1', $output );
		$this->assertStringContainsString( 'Line 2', $output );
		$this->assertStringContainsString( 'Line 3', $output );
	}

	/**
	 * Test array operations
	 */
	public function test_array_operations(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '
					$products = [
						["name" => "Widget", "price" => 10],
						["name" => "Gadget", "price" => 25],
						["name" => "Thing",  "price" => 15],
					];

					$total = array_sum(array_column($products, "price"));
					$names = implode(", ", array_column($products, "name"));

					echo "Products: " . $names . "\n";
					echo "Total: $" . $total;

					return ["total" => $total, "count" => count($products)];
				',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertStringContainsString( 'Widget, Gadget, Thing', $result->getOutput() );
		$this->assertStringContainsString( 'Total: $50', $result->getOutput() );

		$data = $result->getData();
		$this->assertEquals( [ 'total' => 50, 'count' => 3 ], $data['result'] );
	}

	/**
	 * Test batch operations with mixed results
	 */
	public function test_batch_operations(): void {
		$actions = [
			[
				'type'    => 'execute_code',
				'details' => [
					'code' => 'echo "Step 1 complete"; return 1;',
				],
			],
			[
				'type'    => 'execute_code',
				'details' => [
					'code' => 'echo "Step 2 complete"; return 2;',
				],
			],
			[
				'type'    => 'execute_code',
				'details' => [
					'code' => 'echo "Step 3 complete"; return 3;',
				],
			],
		];

		$results = $this->dispatcher->dispatchBatch( $actions );

		$this->assertCount( 3, $results );

		foreach ( $results as $i => $result ) {
			$this->assertTrue( $result->isSuccess() );
			$step = $i + 1;
			$this->assertStringContainsString( "Step {$step} complete", $result->getOutput() );
			$this->assertEquals( $step, $result->getData()['result'] );
		}
	}

	/**
	 * Test legacy action rejection with helpful message
	 */
	public function test_legacy_action_helpful_rejection(): void {
		$legacy_actions = [
			'create_page'          => 'wp_insert_post',
			'create_post'          => 'wp_insert_post',
			'update_post'          => 'wp_update_post',
			'add_elementor_widget' => 'Elementor',
			'create_plugin'        => 'create a plugin',
			'update_option'        => 'update_option',
		];

		foreach ( $legacy_actions as $type => $hint ) {
			$action = [
				'type' => $type,
				'params' => [ 'test' => true ],
			];

			$result = $this->dispatcher->dispatch( $action );

			$this->assertTrue( $result->isFailure(), "Legacy action '{$type}' should be rejected" );
			$this->assertStringContainsString(
				'no longer supported',
				$result->getError(),
				"Error should indicate '{$type}' is no longer supported"
			);
			$this->assertStringContainsString(
				'PHP code',
				$result->getError(),
				"Error should suggest using PHP code"
			);
		}
	}

	/**
	 * Test JSON-like data handling
	 */
	public function test_json_data_handling(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '
					$data = [
						"status" => "success",
						"items"  => [
							["id" => 1, "name" => "Item 1"],
							["id" => 2, "name" => "Item 2"],
						],
					];

					echo json_encode($data, JSON_PRETTY_PRINT);
					return $data;
				',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );

		// Verify JSON output
		$output = $result->getOutput();
		$decoded = json_decode( $output, true );
		$this->assertNotNull( $decoded );
		$this->assertEquals( 'success', $decoded['status'] );
		$this->assertCount( 2, $decoded['items'] );

		// Verify return data
		$data = $result->getData();
		$this->assertEquals( 'success', $data['result']['status'] );
	}
}
