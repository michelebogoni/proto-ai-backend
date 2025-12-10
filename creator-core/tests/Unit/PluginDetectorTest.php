<?php
/**
 * PluginDetector Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Integrations\PluginDetector;

/**
 * Test class for PluginDetector
 */
class PluginDetectorTest extends TestCase {

    /**
     * PluginDetector instance
     *
     * @var PluginDetector
     */
    private $detector;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->detector = new PluginDetector();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( PluginDetector::class, $this->detector );
    }

    /**
     * Test detect_all returns array
     */
    public function test_detect_all(): void {
        $plugins = $this->detector->detect_all();

        $this->assertIsArray( $plugins );
    }

    /**
     * Test detect_all contains expected plugins
     */
    public function test_detect_all_contains_expected_plugins(): void {
        $plugins = $this->detector->detect_all();

        $expected = [
            'elementor',
            'acf',
            'rank_math',
            'woocommerce',
            'wp_code',
            'litespeed',
        ];

        foreach ( $expected as $plugin ) {
            $this->assertArrayHasKey( $plugin, $plugins );
        }
    }

    /**
     * Test plugin detection structure
     */
    public function test_plugin_detection_structure(): void {
        $plugins = $this->detector->detect_all();

        foreach ( $plugins as $slug => $info ) {
            $this->assertIsArray( $info, "Plugin $slug info should be array" );
            $this->assertArrayHasKey( 'installed', $info );
            $this->assertArrayHasKey( 'active', $info );
            $this->assertIsBool( $info['installed'] );
            $this->assertIsBool( $info['active'] );
        }
    }

    /**
     * Test is_plugin_active
     */
    public function test_is_plugin_active(): void {
        $result = $this->detector->is_plugin_active( 'elementor' );
        $this->assertIsBool( $result );
    }

    /**
     * Test is_plugin_installed
     */
    public function test_is_plugin_installed(): void {
        $result = $this->detector->is_plugin_installed( 'elementor' );
        $this->assertIsBool( $result );
    }

    /**
     * Test get_plugin_version
     */
    public function test_get_plugin_version(): void {
        $version = $this->detector->get_plugin_version( 'elementor' );

        // Version can be null if not installed, or string if installed
        $this->assertTrue(
            is_null( $version ) || is_string( $version ),
            'Version should be null or string'
        );
    }

    /**
     * Test get_required_plugins returns array
     */
    public function test_get_required_plugins(): void {
        $required = $this->detector->get_required_plugins();

        $this->assertIsArray( $required );
    }

    /**
     * Test get_optional_plugins returns array
     */
    public function test_get_optional_plugins(): void {
        $optional = $this->detector->get_optional_plugins();

        $this->assertIsArray( $optional );
    }

    /**
     * Test get_missing_required_plugins returns array
     */
    public function test_get_missing_required_plugins(): void {
        $missing = $this->detector->get_missing_required_plugins();

        $this->assertIsArray( $missing );
    }

    /**
     * Test cache functionality
     */
    public function test_cache_functionality(): void {
        // First detection
        $plugins1 = $this->detector->detect_all();

        // Second detection should use cache
        $plugins2 = $this->detector->detect_all();

        $this->assertEquals( $plugins1, $plugins2 );
    }

    /**
     * Test clear_cache
     */
    public function test_clear_cache(): void {
        $this->detector->detect_all();
        $this->detector->clear_cache();

        // Should work without errors
        $plugins = $this->detector->detect_all();
        $this->assertIsArray( $plugins );
    }

    /**
     * Test get_integration_status returns detailed status
     */
    public function test_get_integration_status(): void {
        $status = $this->detector->get_integration_status();

        $this->assertIsArray( $status );
        $this->assertArrayHasKey( 'total_detected', $status );
        $this->assertArrayHasKey( 'active_count', $status );
        $this->assertArrayHasKey( 'plugins', $status );
    }
}
