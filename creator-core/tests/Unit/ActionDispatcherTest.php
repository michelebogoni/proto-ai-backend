<?php
/**
 * ActionDispatcher Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\ActionDispatcher;
use CreatorCore\Executor\ActionResult;
use CreatorCore\Executor\Handlers\ExecutePHPHandler;

/**
 * Test class for ActionDispatcher
 */
class ActionDispatcherTest extends TestCase {

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
	 * Test dispatcher dispatches execute_code to PHP handler
	 */
	public function test_dispatch_execute_code(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code'        => 'echo "Hello";',
				'description' => 'Say hello',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertInstanceOf( ActionResult::class, $result );
		$this->assertTrue( $result->isSuccess() );
		$this->assertEquals( 'Hello', $result->getOutput() );
	}

	/**
	 * Test dispatcher rejects legacy action: create_page
	 */
	public function test_dispatch_rejects_create_page(): void {
		$action = [
			'type'   => 'create_page',
			'params' => [
				'title'   => 'Test Page',
				'content' => 'Test content',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'no longer supported', $result->getError() );
	}

	/**
	 * Test dispatcher rejects legacy action: create_post
	 */
	public function test_dispatch_rejects_create_post(): void {
		$action = [
			'type'   => 'create_post',
			'params' => [
				'title' => 'Test Post',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'no longer supported', $result->getError() );
	}

	/**
	 * Test dispatcher rejects legacy action: add_elementor_widget
	 */
	public function test_dispatch_rejects_elementor_widget(): void {
		$action = [
			'type'   => 'add_elementor_widget',
			'params' => [
				'widget_type' => 'heading',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'no longer supported', $result->getError() );
	}

	/**
	 * Test dispatcher rejects unknown action type
	 */
	public function test_dispatch_rejects_unknown_type(): void {
		$action = [
			'type' => 'unknown_action',
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'Unknown action type', $result->getError() );
	}

	/**
	 * Test dispatcher handles action without explicit type but with code
	 */
	public function test_dispatch_handles_code_without_type(): void {
		$action = [
			'details' => [
				'code' => 'echo "Works";',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertEquals( 'Works', $result->getOutput() );
	}

	/**
	 * Test getPhpHandler returns handler instance
	 */
	public function test_get_php_handler(): void {
		$handler = $this->dispatcher->getPhpHandler();

		$this->assertInstanceOf( ExecutePHPHandler::class, $handler );
	}

	/**
	 * Test dispatchBatch executes multiple actions
	 */
	public function test_dispatch_batch(): void {
		$actions = [
			[
				'type'    => 'execute_code',
				'details' => [ 'code' => 'echo "First";' ],
			],
			[
				'type'    => 'execute_code',
				'details' => [ 'code' => 'echo "Second";' ],
			],
		];

		$results = $this->dispatcher->dispatchBatch( $actions );

		$this->assertCount( 2, $results );
		$this->assertTrue( $results[0]->isSuccess() );
		$this->assertTrue( $results[1]->isSuccess() );
		$this->assertEquals( 'First', $results[0]->getOutput() );
		$this->assertEquals( 'Second', $results[1]->getOutput() );
	}

	/**
	 * Test dispatchBatch stops on first failure by default
	 */
	public function test_dispatch_batch_stops_on_failure(): void {
		$actions = [
			[
				'type'    => 'execute_code',
				'details' => [ 'code' => 'throw new Exception("Error");' ],
			],
			[
				'type'    => 'execute_code',
				'details' => [ 'code' => 'echo "Should not run";' ],
			],
		];

		$results = $this->dispatcher->dispatchBatch( $actions );

		// Should only have one result due to stop-on-failure
		$this->assertCount( 1, $results );
		$this->assertTrue( $results[0]->isFailure() );
	}

	/**
	 * Test custom handler can be injected
	 */
	public function test_custom_handler_injection(): void {
		$mockHandler = $this->createMock( ExecutePHPHandler::class );
		$mockHandler->method( 'supports' )->willReturn( true );
		$mockHandler->method( 'handle' )->willReturn( ActionResult::success( [ 'custom' => true ] ) );

		$dispatcher = new ActionDispatcher( $mockHandler );
		$result = $dispatcher->dispatch( [ 'type' => 'execute_code', 'details' => [ 'code' => 'test' ] ] );

		$this->assertTrue( $result->isSuccess() );
		$this->assertEquals( [ 'custom' => true ], $result->getData() );
	}

	/**
	 * Test dispatcher handles complex PHP code
	 */
	public function test_dispatch_complex_code(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '
					$data = ["a" => 1, "b" => 2];
					$sum = array_sum($data);
					echo "Sum: " . $sum;
					return $sum;
				',
			],
		];

		$result = $this->dispatcher->dispatch( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertStringContainsString( 'Sum: 3', $result->getOutput() );
		$this->assertEquals( 3, $result->getData()['result'] );
	}
}
