<?php
/**
 * SnapshotManager Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Backup\SnapshotManager;

/**
 * Test class for SnapshotManager
 */
class SnapshotManagerTest extends TestCase {

    /**
     * SnapshotManager instance
     *
     * @var SnapshotManager
     */
    private $manager;

    /**
     * Test backup directory
     *
     * @var string
     */
    private $test_backup_dir;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();

        $this->test_backup_dir = sys_get_temp_dir() . '/creator-test-backups';
        if ( ! is_dir( $this->test_backup_dir ) ) {
            mkdir( $this->test_backup_dir, 0755, true );
        }

        $this->manager = new SnapshotManager();
    }

    /**
     * Tear down test fixtures
     */
    protected function tearDown(): void {
        parent::tearDown();

        // Clean up test directory
        if ( is_dir( $this->test_backup_dir ) ) {
            $this->recursive_delete( $this->test_backup_dir );
        }
    }

    /**
     * Helper to recursively delete directory
     */
    private function recursive_delete( $dir ): void {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ( $object !== '.' && $object !== '..' ) {
                    if ( is_dir( $dir . '/' . $object ) ) {
                        $this->recursive_delete( $dir . '/' . $object );
                    } else {
                        unlink( $dir . '/' . $object );
                    }
                }
            }
            rmdir( $dir );
        }
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( SnapshotManager::class, $this->manager );
    }

    /**
     * Test create_snapshot creates valid snapshot
     */
    public function test_create_snapshot(): void {
        $data = [
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
            'post_status' => 'publish',
        ];

        $snapshot_id = $this->manager->create_snapshot( 'post', 1, $data );

        $this->assertIsString( $snapshot_id );
        $this->assertNotEmpty( $snapshot_id );
    }

    /**
     * Test create_snapshot for different types
     */
    public function test_create_snapshot_types(): void {
        $types = [ 'post', 'page', 'option', 'meta' ];

        foreach ( $types as $type ) {
            $snapshot_id = $this->manager->create_snapshot(
                $type,
                rand( 1, 100 ),
                [ 'key' => 'value' ]
            );

            $this->assertIsString( $snapshot_id, "Failed for type: $type" );
        }
    }

    /**
     * Test get_snapshot retrieves correct data
     */
    public function test_get_snapshot(): void {
        $data = [
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
        ];

        $snapshot_id = $this->manager->create_snapshot( 'post', 1, $data );
        $snapshot = $this->manager->get_snapshot( $snapshot_id );

        $this->assertIsArray( $snapshot );
        $this->assertArrayHasKey( 'type', $snapshot );
        $this->assertArrayHasKey( 'target_id', $snapshot );
        $this->assertArrayHasKey( 'data', $snapshot );
    }

    /**
     * Test get_snapshot returns null for invalid ID
     */
    public function test_get_snapshot_invalid_id(): void {
        $snapshot = $this->manager->get_snapshot( 'invalid-id-12345' );
        $this->assertNull( $snapshot );
    }

    /**
     * Test delete_snapshot removes snapshot
     */
    public function test_delete_snapshot(): void {
        $snapshot_id = $this->manager->create_snapshot( 'post', 1, [ 'test' => 'data' ] );
        $result = $this->manager->delete_snapshot( $snapshot_id );

        $this->assertTrue( $result );
    }

    /**
     * Test list_snapshots returns array
     */
    public function test_list_snapshots(): void {
        // Create a few snapshots
        $this->manager->create_snapshot( 'post', 1, [ 'data' => 1 ] );
        $this->manager->create_snapshot( 'post', 2, [ 'data' => 2 ] );

        $snapshots = $this->manager->list_snapshots();

        $this->assertIsArray( $snapshots );
    }

    /**
     * Test get_snapshots_for_target
     */
    public function test_get_snapshots_for_target(): void {
        $target_id = 42;

        $this->manager->create_snapshot( 'post', $target_id, [ 'v' => 1 ] );
        $this->manager->create_snapshot( 'post', $target_id, [ 'v' => 2 ] );

        $snapshots = $this->manager->get_snapshots_for_target( 'post', $target_id );

        $this->assertIsArray( $snapshots );
    }

    /**
     * Test cleanup_old_snapshots
     */
    public function test_cleanup_old_snapshots(): void {
        // Create some snapshots
        for ( $i = 0; $i < 5; $i++ ) {
            $this->manager->create_snapshot( 'post', $i, [ 'data' => $i ] );
        }

        // Run cleanup (should not fail)
        $deleted = $this->manager->cleanup_old_snapshots( 30 );

        $this->assertIsInt( $deleted );
        $this->assertGreaterThanOrEqual( 0, $deleted );
    }

    /**
     * Test snapshot data integrity
     */
    public function test_snapshot_data_integrity(): void {
        $original_data = [
            'post_title' => 'Test with "quotes" and special chars: <>&',
            'post_content' => "Multi\nline\ncontent",
            'post_meta' => [
                'key1' => 'value1',
                'nested' => [ 'a' => 'b' ],
            ],
        ];

        $snapshot_id = $this->manager->create_snapshot( 'post', 1, $original_data );
        $snapshot = $this->manager->get_snapshot( $snapshot_id );

        $this->assertEquals( $original_data, $snapshot['data'] );
    }
}
