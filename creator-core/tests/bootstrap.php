<?php
/**
 * PHPUnit bootstrap file for Creator Core tests
 *
 * @package CreatorCore
 */

// Composer autoloader.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
}

// Define constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );
}

if ( ! defined( 'CREATOR_CORE_VERSION' ) ) {
    define( 'CREATOR_CORE_VERSION', '1.0.0' );
}

if ( ! defined( 'CREATOR_CORE_FILE' ) ) {
    define( 'CREATOR_CORE_FILE', dirname( __DIR__ ) . '/creator-core.php' );
}

if ( ! defined( 'CREATOR_CORE_PATH' ) ) {
    define( 'CREATOR_CORE_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'CREATOR_CORE_URL' ) ) {
    define( 'CREATOR_CORE_URL', 'http://example.com/wp-content/plugins/creator-core/' );
}

if ( ! defined( 'CREATOR_PROXY_URL' ) ) {
    define( 'CREATOR_PROXY_URL', 'https://creator-ai-proxy.firebaseapp.com/api/' );
}

// Mock mode has been removed - tests run against real functionality

// WordPress test stubs.
require_once __DIR__ . '/stubs/wordpress-stubs.php';

// Load the autoloader.
require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
\CreatorCore\Autoloader::register();
