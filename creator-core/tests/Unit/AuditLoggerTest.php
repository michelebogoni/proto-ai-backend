<?php
/**
 * AuditLogger Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Audit\AuditLogger;

/**
 * Test class for AuditLogger
 */
class AuditLoggerTest extends TestCase {

    /**
     * AuditLogger instance
     *
     * @var AuditLogger
     */
    private $logger;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->logger = new AuditLogger();
    }

    /**
     * Test log method creates entry
     */
    public function test_log_creates_entry(): void {
        $result = $this->logger->log(
            'test_action',
            'Test description',
            [ 'key' => 'value' ]
        );

        $this->assertTrue( $result );
    }

    /**
     * Test log with different log levels
     */
    public function test_log_with_levels(): void {
        $levels = [ 'info', 'warning', 'error', 'debug' ];

        foreach ( $levels as $level ) {
            $result = $this->logger->log(
                'test_action',
                'Test ' . $level,
                [],
                $level
            );
            $this->assertTrue( $result, "Failed for level: $level" );
        }
    }

    /**
     * Test log action convenience method
     */
    public function test_log_action(): void {
        $result = $this->logger->log_action(
            'create_post',
            1,
            [ 'post_title' => 'Test' ]
        );

        $this->assertTrue( $result );
    }

    /**
     * Test log error convenience method
     */
    public function test_log_error(): void {
        $result = $this->logger->log_error(
            'Test error message',
            [ 'error_code' => 500 ]
        );

        $this->assertTrue( $result );
    }

    /**
     * Test log API call method
     */
    public function test_log_api_call(): void {
        $result = $this->logger->log_api_call(
            'chat',
            [ 'message' => 'test' ],
            [ 'response' => 'ok' ],
            0.5
        );

        $this->assertTrue( $result );
    }

    /**
     * Test data is serialized correctly
     */
    public function test_data_serialization(): void {
        $complex_data = [
            'nested' => [
                'key' => 'value',
                'array' => [ 1, 2, 3 ],
            ],
            'unicode' => 'Test émoji 🎉',
        ];

        $result = $this->logger->log(
            'complex_data',
            'Test with complex data',
            $complex_data
        );

        $this->assertTrue( $result );
    }
}
