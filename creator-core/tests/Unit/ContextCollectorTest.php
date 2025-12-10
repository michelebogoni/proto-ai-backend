<?php
/**
 * ContextCollector Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Chat\ContextCollector;

/**
 * Test class for ContextCollector
 */
class ContextCollectorTest extends TestCase {

    /**
     * ContextCollector instance
     *
     * @var ContextCollector
     */
    private $collector;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->collector = new ContextCollector();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( ContextCollector::class, $this->collector );
    }

    /**
     * Test collect returns array with required keys
     */
    public function test_collect_returns_required_keys(): void {
        $context = $this->collector->collect();

        $this->assertIsArray( $context );
        $this->assertArrayHasKey( 'site_info', $context );
        $this->assertArrayHasKey( 'user_info', $context );
        $this->assertArrayHasKey( 'plugins', $context );
    }

    /**
     * Test site_info contains required fields
     */
    public function test_site_info_structure(): void {
        $context = $this->collector->collect();
        $site_info = $context['site_info'];

        $this->assertArrayHasKey( 'name', $site_info );
        $this->assertArrayHasKey( 'url', $site_info );
        $this->assertArrayHasKey( 'admin_email', $site_info );
    }

    /**
     * Test user_info contains required fields
     */
    public function test_user_info_structure(): void {
        $context = $this->collector->collect();
        $user_info = $context['user_info'];

        $this->assertArrayHasKey( 'id', $user_info );
        $this->assertArrayHasKey( 'role', $user_info );
    }

    /**
     * Test plugins contains known plugin checks
     */
    public function test_plugins_structure(): void {
        $context = $this->collector->collect();
        $plugins = $context['plugins'];

        $this->assertIsArray( $plugins );

        // Check for expected plugin keys
        $expected_plugins = [
            'elementor',
            'acf',
            'rank_math',
            'woocommerce',
            'wp_code',
            'litespeed',
        ];

        foreach ( $expected_plugins as $plugin ) {
            $this->assertArrayHasKey( $plugin, $plugins );
        }
    }

    /**
     * Test get_site_info returns array
     */
    public function test_get_site_info(): void {
        $site_info = $this->collector->get_site_info();

        $this->assertIsArray( $site_info );
        $this->assertArrayHasKey( 'name', $site_info );
    }

    /**
     * Test get_user_info returns array
     */
    public function test_get_user_info(): void {
        $user_info = $this->collector->get_user_info();

        $this->assertIsArray( $user_info );
        $this->assertArrayHasKey( 'id', $user_info );
    }

    /**
     * Test get_active_plugins returns array
     */
    public function test_get_active_plugins(): void {
        $plugins = $this->collector->get_active_plugins();

        $this->assertIsArray( $plugins );
    }

    /**
     * Test get_recent_posts returns array
     */
    public function test_get_recent_posts(): void {
        $posts = $this->collector->get_recent_posts( 5 );

        $this->assertIsArray( $posts );
    }

    /**
     * Test collect_for_action returns action-specific context
     */
    public function test_collect_for_action(): void {
        $context = $this->collector->collect_for_action( 'create_post' );

        $this->assertIsArray( $context );
        $this->assertArrayHasKey( 'action', $context );
        $this->assertEquals( 'create_post', $context['action'] );
    }

    /**
     * Test collect caching
     */
    public function test_collect_caching(): void {
        // First call
        $context1 = $this->collector->collect();

        // Second call should return cached version
        $context2 = $this->collector->collect();

        $this->assertEquals( $context1, $context2 );
    }

    /**
     * Test refresh clears cache
     */
    public function test_refresh_clears_cache(): void {
        // First collect
        $this->collector->collect();

        // Refresh
        $this->collector->refresh();

        // Should work without errors
        $context = $this->collector->collect();
        $this->assertIsArray( $context );
    }
}
