<?php
/**
 * CapabilityChecker Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Permission\CapabilityChecker;

/**
 * Test class for CapabilityChecker
 */
class CapabilityCheckerTest extends TestCase {

    /**
     * CapabilityChecker instance
     *
     * @var CapabilityChecker
     */
    private $checker;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->checker = new CapabilityChecker();
    }

    /**
     * Test can_use_creator returns true for administrators
     */
    public function test_can_use_creator_for_admin(): void {
        $result = $this->checker->can_use_creator();
        $this->assertTrue( $result );
    }

    /**
     * Test can_manage_settings returns true for administrators
     */
    public function test_can_manage_settings(): void {
        $result = $this->checker->can_manage_settings();
        $this->assertTrue( $result );
    }

    /**
     * Test check_operation_requirements with allowed operation
     */
    public function test_check_operation_requirements_allowed(): void {
        $result = $this->checker->check_operation_requirements( 'create_post' );
        $this->assertTrue( $result['allowed'] );
        $this->assertEmpty( $result['missing'] );
    }

    /**
     * Test get_all_operations returns array
     */
    public function test_get_all_operations(): void {
        $operations = $this->checker->get_all_operations();

        $this->assertIsArray( $operations );
        $this->assertNotEmpty( $operations );
    }

    /**
     * Test default operations exist
     */
    public function test_default_operations_exist(): void {
        $operations = $this->checker->get_all_operations();

        $expected_operations = [
            'create_post',
            'update_post',
            'create_page',
            'update_page',
            'upload_media',
        ];

        foreach ( $expected_operations as $operation ) {
            $this->assertContains( $operation, $operations );
        }
    }

    /**
     * Test get_operation_capabilities returns correct capabilities
     */
    public function test_get_operation_capabilities(): void {
        $caps = $this->checker->get_operation_capabilities( 'create_post' );

        $this->assertIsArray( $caps );
        $this->assertContains( 'edit_posts', $caps );
        $this->assertContains( 'publish_posts', $caps );
    }

    /**
     * Test can_manage_backups returns true for administrators
     */
    public function test_can_manage_backups(): void {
        $result = $this->checker->can_manage_backups();
        $this->assertTrue( $result );
    }

    /**
     * Test can_view_audit returns true for administrators
     */
    public function test_can_view_audit(): void {
        $result = $this->checker->can_view_audit();
        $this->assertTrue( $result );
    }

    /**
     * Test check_permission returns true for allowed operations
     */
    public function test_check_permission_allowed(): void {
        $result = $this->checker->check_permission( 'create_post' );
        $this->assertTrue( $result );
    }

    /**
     * Test get_user_operations returns array of available operations
     */
    public function test_get_user_operations(): void {
        $operations = $this->checker->get_user_operations();

        $this->assertIsArray( $operations );
        // For admin, should have access to multiple operations
        $this->assertNotEmpty( $operations );
    }
}
