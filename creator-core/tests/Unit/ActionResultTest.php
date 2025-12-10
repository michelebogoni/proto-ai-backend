<?php
/**
 * ActionResult Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Executor\ActionResult;

/**
 * Test class for ActionResult value object
 */
class ActionResultTest extends TestCase {

	/**
	 * Test success factory method
	 */
	public function test_success_creates_successful_result(): void {
		$data   = [ 'message' => 'Test success', 'result' => 42 ];
		$output = 'Test output';

		$result = ActionResult::success( $data, $output );

		$this->assertTrue( $result->isSuccess() );
		$this->assertFalse( $result->isFailure() );
		$this->assertEquals( $data, $result->getData() );
		$this->assertEquals( $output, $result->getOutput() );
		$this->assertNull( $result->getError() );
	}

	/**
	 * Test fail factory method
	 */
	public function test_fail_creates_failure_result(): void {
		$error  = 'Something went wrong';
		$output = 'Partial output';

		$result = ActionResult::fail( $error, $output );

		$this->assertFalse( $result->isSuccess() );
		$this->assertTrue( $result->isFailure() );
		$this->assertNull( $result->getData() );
		$this->assertEquals( $error, $result->getError() );
		$this->assertEquals( $output, $result->getOutput() );
	}

	/**
	 * Test success with empty data
	 */
	public function test_success_with_empty_data(): void {
		$result = ActionResult::success();

		$this->assertTrue( $result->isSuccess() );
		$this->assertEquals( [], $result->getData() );
		$this->assertEquals( '', $result->getOutput() );
	}

	/**
	 * Test toArray includes all fields
	 */
	public function test_to_array_success(): void {
		$data   = [ 'key' => 'value' ];
		$output = 'test output';
		$result = ActionResult::success( $data, $output );

		$array = $result->toArray();

		$this->assertArrayHasKey( 'success', $array );
		$this->assertArrayHasKey( 'timestamp', $array );
		$this->assertArrayHasKey( 'data', $array );
		$this->assertArrayHasKey( 'output', $array );
		$this->assertTrue( $array['success'] );
		$this->assertEquals( $data, $array['data'] );
	}

	/**
	 * Test toArray for failure includes error
	 */
	public function test_to_array_failure(): void {
		$error  = 'Test error';
		$result = ActionResult::fail( $error );

		$array = $result->toArray();

		$this->assertArrayHasKey( 'success', $array );
		$this->assertArrayHasKey( 'error', $array );
		$this->assertFalse( $array['success'] );
		$this->assertEquals( $error, $array['error'] );
	}

	/**
	 * Test timestamp is set
	 */
	public function test_timestamp_is_set(): void {
		$result = ActionResult::success();

		$this->assertNotEmpty( $result->getTimestamp() );
	}

	/**
	 * Test toJson returns valid JSON
	 */
	public function test_to_json_returns_valid_json(): void {
		$result = ActionResult::success( [ 'test' => 'data' ] );

		$json = $result->toJson();

		$this->assertJson( $json );
		$decoded = json_decode( $json, true );
		$this->assertTrue( $decoded['success'] );
	}
}
