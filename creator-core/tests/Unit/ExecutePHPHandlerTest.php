<?php
/**
 * ExecutePHPHandler Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\Handlers\ExecutePHPHandler;
use CreatorCore\Executor\ActionResult;

/**
 * Test class for ExecutePHPHandler
 */
class ExecutePHPHandlerTest extends TestCase {

	/**
	 * Handler instance
	 *
	 * @var ExecutePHPHandler
	 */
	private ExecutePHPHandler $handler;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->handler = new ExecutePHPHandler();
	}

	/**
	 * Test handler supports execute_code type
	 */
	public function test_supports_execute_code_type(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'echo "test";',
			],
		];

		$this->assertTrue( $this->handler->supports( $action ) );
	}

	/**
	 * Test handler supports action with code in details
	 */
	public function test_supports_action_with_code(): void {
		$action = [
			'details' => [
				'code' => 'echo "test";',
			],
		];

		$this->assertTrue( $this->handler->supports( $action ) );
	}

	/**
	 * Test handler does not support empty action
	 */
	public function test_does_not_support_empty_action(): void {
		$action = [
			'type' => 'execute_code',
		];

		$this->assertFalse( $this->handler->supports( $action ) );
	}

	/**
	 * Test handle returns failure for empty code
	 */
	public function test_handle_fails_for_empty_code(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertInstanceOf( ActionResult::class, $result );
		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'No code provided', $result->getError() );
	}

	/**
	 * Test handle blocks forbidden function: exec
	 */
	public function test_handle_blocks_exec(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'exec("ls -la");',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'restricted functions', $result->getError() );
	}

	/**
	 * Test handle blocks forbidden function: shell_exec
	 */
	public function test_handle_blocks_shell_exec(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '$output = shell_exec("whoami");',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'restricted functions', $result->getError() );
	}

	/**
	 * Test handle blocks forbidden function: system
	 */
	public function test_handle_blocks_system(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'system("cat /etc/passwd");',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'restricted functions', $result->getError() );
	}

	/**
	 * Test handle blocks backtick execution
	 */
	public function test_handle_blocks_backticks(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '$output = `ls -la`;',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'restricted functions', $result->getError() );
	}

	/**
	 * Test handle blocks eval (nested)
	 */
	public function test_handle_blocks_nested_eval(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'eval("echo 1;");',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'restricted functions', $result->getError() );
	}

	/**
	 * Test handle executes safe code successfully
	 */
	public function test_handle_executes_safe_code(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code'        => 'echo "Hello World";',
				'description' => 'Test code',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertEquals( 'Hello World', $result->getOutput() );
	}

	/**
	 * Test handle captures return value
	 */
	public function test_handle_captures_return_value(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'return 42;',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isSuccess() );
		$data = $result->getData();
		$this->assertEquals( 42, $data['result'] );
	}

	/**
	 * Test handle captures echo output
	 */
	public function test_handle_captures_echo_output(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'echo "Line 1\n"; echo "Line 2";',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertStringContainsString( 'Line 1', $result->getOutput() );
		$this->assertStringContainsString( 'Line 2', $result->getOutput() );
	}

	/**
	 * Test handle catches PHP errors
	 */
	public function test_handle_catches_exceptions(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => 'throw new Exception("Test error");',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'Test error', $result->getError() );
	}

	/**
	 * Test handle removes PHP opening tag
	 */
	public function test_handle_removes_php_tag(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '<?php echo "test";',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isSuccess() );
		$this->assertEquals( 'test', $result->getOutput() );
	}

	/**
	 * Test handle blocks dangerous SQL
	 */
	public function test_handle_blocks_dangerous_sql(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code' => '$query = "DROP TABLE users";',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isFailure() );
		$this->assertStringContainsString( 'restricted functions', $result->getError() );
	}

	/**
	 * Test handle includes description in result
	 */
	public function test_handle_includes_description(): void {
		$action = [
			'type'    => 'execute_code',
			'details' => [
				'code'        => 'echo "test";',
				'description' => 'My test operation',
			],
		];

		$result = $this->handler->handle( $action );

		$this->assertTrue( $result->isSuccess() );
		$data = $result->getData();
		$this->assertEquals( 'My test operation', $data['description'] );
	}
}
