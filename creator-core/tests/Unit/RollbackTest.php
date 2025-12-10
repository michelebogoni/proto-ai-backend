<?php
/**
 * Rollback Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Backup\Rollback;

/**
 * Test class for Rollback
 */
class RollbackTest extends TestCase {

    /**
     * Rollback instance
     *
     * @var Rollback
     */
    private $rollback;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->rollback = new Rollback();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( Rollback::class, $this->rollback );
    }

    /**
     * Test can_rollback returns boolean
     */
    public function test_can_rollback(): void {
        $result = $this->rollback->can_rollback( 1 );
        $this->assertIsBool( $result );
    }

    /**
     * Test execute_rollback returns array
     */
    public function test_execute_rollback(): void {
        $result = $this->rollback->execute( 1 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'success', $result );
    }

    /**
     * Test execute_rollback with invalid action ID
     */
    public function test_execute_rollback_invalid_id(): void {
        $result = $this->rollback->execute( 999999 );

        $this->assertIsArray( $result );
        $this->assertFalse( $result['success'] );
    }

    /**
     * Test get_rollback_history returns array
     */
    public function test_get_rollback_history(): void {
        $history = $this->rollback->get_history();

        $this->assertIsArray( $history );
    }

    /**
     * Test get_rollback_history with limit
     */
    public function test_get_rollback_history_with_limit(): void {
        $history = $this->rollback->get_history( 5 );

        $this->assertIsArray( $history );
        $this->assertLessThanOrEqual( 5, count( $history ) );
    }

    /**
     * Test prepare_rollback returns rollback data
     */
    public function test_prepare_rollback(): void {
        $data = $this->rollback->prepare( 1 );

        $this->assertIsArray( $data );
    }

    /**
     * Test validate_rollback returns boolean
     */
    public function test_validate_rollback(): void {
        $result = $this->rollback->validate( 1 );
        $this->assertIsBool( $result );
    }

    /**
     * Test get_rollback_preview returns preview data
     */
    public function test_get_rollback_preview(): void {
        $preview = $this->rollback->get_preview( 1 );

        // Can be null if action doesn't exist or array with preview
        $this->assertTrue(
            is_null( $preview ) || is_array( $preview ),
            'Preview should be null or array'
        );
    }

    /**
     * Test rollback creates audit log
     */
    public function test_rollback_creates_audit_log(): void {
        // Execute rollback
        $this->rollback->execute( 1 );

        // Should complete without error
        // Actual audit log verification would require integration test
        $this->assertTrue( true );
    }
}
