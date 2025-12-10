<?php
/**
 * ProxyClient Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Integrations\ProxyClient;

/**
 * Test class for ProxyClient
 */
class ProxyClientTest extends TestCase {

    /**
     * ProxyClient instance
     *
     * @var ProxyClient
     */
    private $client;

    /**
     * Admin license key for testing
     *
     * @var string
     */
    private const ADMIN_LICENSE = 'CREATOR-ADMIN-7f3d9c2e1a8b4f6d';

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->client = new ProxyClient();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( ProxyClient::class, $this->client );
    }

    /**
     * Test admin license validation
     */
    public function test_validate_admin_license(): void {
        $result = $this->client->validate_license( self::ADMIN_LICENSE );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 'admin', $result['plan'] );
        $this->assertTrue( $result['admin_mode'] );
        $this->assertArrayHasKey( 'site_token', $result );
    }

    /**
     * Test get_proxy_url returns correct URL
     */
    public function test_get_proxy_url(): void {
        $url = $this->client->get_proxy_url();

        $this->assertEquals( CREATOR_PROXY_URL, $url );
    }

    /**
     * Test set_proxy_url changes URL
     */
    public function test_set_proxy_url(): void {
        $new_url = 'https://new-proxy.example.com';
        $this->client->set_proxy_url( $new_url );

        $this->assertEquals( $new_url, $this->client->get_proxy_url() );
    }

    /**
     * Test check_connection returns expected structure
     */
    public function test_check_connection_structure(): void {
        $result = $this->client->check_connection();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'connected', $result );
        $this->assertArrayHasKey( 'proxy_url', $result );
    }

    /**
     * Test get_usage_stats returns expected structure
     */
    public function test_get_usage_stats_structure(): void {
        $result = $this->client->get_usage_stats();

        $this->assertIsArray( $result );
        // Will have either error (if not authenticated) or stats
        $this->assertTrue(
            isset( $result['error'] ) || isset( $result['tokens_used'] )
        );
    }

    /**
     * Test clear_authentication removes credentials
     */
    public function test_clear_authentication(): void {
        // First validate a license
        $this->client->validate_license( self::ADMIN_LICENSE );

        // Then clear
        $this->client->clear_authentication();

        // Check that credentials are cleared
        $this->assertFalse( get_option( 'creator_site_token' ) );
        $this->assertFalse( get_option( 'creator_license_validated' ) );
    }

    /**
     * Test send_to_ai requires authentication
     */
    public function test_send_to_ai_requires_auth(): void {
        // Clear any existing auth
        $this->client->clear_authentication();

        $result = $this->client->send_to_ai( 'Test prompt' );

        $this->assertIsArray( $result );
        $this->assertFalse( $result['success'] );
        $this->assertArrayHasKey( 'error', $result );
    }
}
