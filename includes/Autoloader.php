<?php
/**
 * PSR-4 Autoloader for Creator Core
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 *
 * Handles PSR-4 compliant autoloading for the plugin
 */
class Autoloader {

    /**
     * Namespace prefix
     *
     * @var string
     */
    private static string $prefix = 'CreatorCore\\';

    /**
     * Base directory for the namespace prefix
     *
     * @var string
     */
    private static string $base_dir = '';

    /**
     * Register the autoloader
     *
     * @return void
     */
    public static function register(): void {
        self::$base_dir = CREATOR_CORE_PATH . 'includes/';
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Autoload callback
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public static function autoload( string $class ): void {
        // Check if the class uses the namespace prefix
        $len = strlen( self::$prefix );
        if ( strncmp( self::$prefix, $class, $len ) !== 0 ) {
            return;
        }

        // Get the relative class name
        $relative_class = substr( $class, $len );

        // Replace namespace separators with directory separators
        $file = self::$base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // If the file exists, require it
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
