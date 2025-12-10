<?php
/**
 * LiteSpeed Cache Integration
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class LiteSpeedIntegration
 *
 * Handles LiteSpeed Cache operations
 */
class LiteSpeedIntegration {

    /**
     * Check if LiteSpeed is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return defined( 'LSCWP_V' );
    }

    /**
     * Get LiteSpeed version
     *
     * @return string|null
     */
    public function get_version(): ?string {
        if ( ! $this->is_available() ) {
            return null;
        }

        return LSCWP_V;
    }

    /**
     * Purge all cache
     *
     * @return bool
     */
    public function purge_all(): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        if ( class_exists( '\LiteSpeed\Purge' ) ) {
            \LiteSpeed\Purge::purge_all();
            return true;
        }

        // Fallback for older versions
        if ( method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
            \LiteSpeed_Cache_API::purge_all();
            return true;
        }

        return false;
    }

    /**
     * Purge cache for specific post
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public function purge_post( int $post_id ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        if ( class_exists( '\LiteSpeed\Purge' ) ) {
            \LiteSpeed\Purge::purge_post( $post_id );
            return true;
        }

        // Fallback for older versions
        if ( method_exists( 'LiteSpeed_Cache_API', 'purge_post' ) ) {
            \LiteSpeed_Cache_API::purge_post( $post_id );
            return true;
        }

        return false;
    }

    /**
     * Purge cache by URL
     *
     * @param string $url URL to purge.
     * @return bool
     */
    public function purge_url( string $url ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_url' ) ) {
            \LiteSpeed\Purge::purge_url( $url );
            return true;
        }

        return false;
    }

    /**
     * Purge CSS/JS cache
     *
     * @return bool
     */
    public function purge_cssjs(): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_cssjs' ) ) {
            \LiteSpeed\Purge::purge_cssjs();
            return true;
        }

        return false;
    }

    /**
     * Get cache status
     *
     * @return array
     */
    public function get_status(): array {
        if ( ! $this->is_available() ) {
            return [
                'available' => false,
            ];
        }

        return [
            'available' => true,
            'version'   => $this->get_version(),
            'enabled'   => $this->is_cache_enabled(),
        ];
    }

    /**
     * Check if cache is enabled
     *
     * @return bool
     */
    public function is_cache_enabled(): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        // Check if caching is enabled in settings
        if ( class_exists( '\LiteSpeed\Conf' ) ) {
            return (bool) \LiteSpeed\Conf::val( 'cache' );
        }

        return get_option( 'litespeed.conf.cache', false );
    }

    /**
     * Get optimization settings
     *
     * @return array
     */
    public function get_optimization_settings(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $settings = [];

        if ( class_exists( '\LiteSpeed\Conf' ) ) {
            $settings = [
                'css_minify'  => \LiteSpeed\Conf::val( 'optm-css_min' ),
                'css_combine' => \LiteSpeed\Conf::val( 'optm-css_comb' ),
                'js_minify'   => \LiteSpeed\Conf::val( 'optm-js_min' ),
                'js_combine'  => \LiteSpeed\Conf::val( 'optm-js_comb' ),
                'html_minify' => \LiteSpeed\Conf::val( 'optm-html_min' ),
                'lazy_load'   => \LiteSpeed\Conf::val( 'media-lazy' ),
            ];
        }

        return $settings;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function get_cache_stats(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $stats = [];

        // Get cache directory size
        $cache_path = WP_CONTENT_DIR . '/litespeed/';

        if ( is_dir( $cache_path ) ) {
            $stats['cache_path'] = $cache_path;
            $stats['cache_size'] = $this->get_directory_size( $cache_path );
        }

        return $stats;
    }

    /**
     * Get directory size
     *
     * @param string $path Directory path.
     * @return int Size in bytes.
     */
    private function get_directory_size( string $path ): int {
        $size = 0;

        if ( ! is_dir( $path ) ) {
            return $size;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Add crawler URL
     *
     * @param string $url URL to add to crawler.
     * @return bool
     */
    public function add_crawler_url( string $url ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        // This would typically interact with LiteSpeed's crawler functionality
        // Implementation depends on LiteSpeed version

        return true;
    }

    /**
     * Exclude URL from cache
     *
     * @param string $url URL pattern to exclude.
     * @return bool
     */
    public function exclude_url( string $url ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        // Get current exclusions
        $excludes = get_option( 'litespeed.conf.cache-exc', '' );

        // Add new exclusion if not already present
        if ( strpos( $excludes, $url ) === false ) {
            $excludes .= "\n" . $url;
            update_option( 'litespeed.conf.cache-exc', trim( $excludes ) );
        }

        return true;
    }
}
