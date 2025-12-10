<?php
/**
 * Plugin Generator
 *
 * @package CreatorCore
 */

namespace CreatorCore\Development;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class PluginGenerator
 *
 * Generates and installs WordPress plugins programmatically
 */
class PluginGenerator {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * File system manager instance
     *
     * @var FileSystemManager
     */
    private FileSystemManager $filesystem;

    /**
     * Constructor
     *
     * @param AuditLogger|null       $logger     Audit logger instance.
     * @param FileSystemManager|null $filesystem File system manager instance.
     */
    public function __construct( ?AuditLogger $logger = null, ?FileSystemManager $filesystem = null ) {
        $this->logger     = $logger ?? new AuditLogger();
        $this->filesystem = $filesystem ?? new FileSystemManager( $this->logger );
    }

    /**
     * Create a new plugin
     *
     * @param array $config Plugin configuration.
     * @return array Result with success status and plugin info.
     */
    public function create_plugin( array $config ): array {
        $defaults = [
            'name'        => 'My Custom Plugin',
            'slug'        => '',
            'description' => 'A custom WordPress plugin created by Creator',
            'version'     => '1.0.0',
            'author'      => 'Creator AI',
            'author_uri'  => '',
            'license'     => 'GPL v2 or later',
            'text_domain' => '',
            'namespace'   => '',
            'features'    => [],
            'files'       => [],
        ];

        $config = wp_parse_args( $config, $defaults );

        // Generate slug from name if not provided
        if ( empty( $config['slug'] ) ) {
            $config['slug'] = sanitize_title( $config['name'] );
        }

        // Generate text domain from slug if not provided
        if ( empty( $config['text_domain'] ) ) {
            $config['text_domain'] = $config['slug'];
        }

        // Generate namespace from slug if not provided
        if ( empty( $config['namespace'] ) ) {
            $config['namespace'] = str_replace( '-', '', ucwords( $config['slug'], '-' ) );
        }

        // Check if plugin already exists
        $plugin_dir = WP_PLUGIN_DIR . '/' . $config['slug'];
        if ( is_dir( $plugin_dir ) ) {
            return [
                'success' => false,
                'error'   => sprintf(
                    /* translators: %s: Plugin slug */
                    __( 'Plugin "%s" already exists', 'creator-core' ),
                    $config['slug']
                ),
            ];
        }

        // Create plugin directory
        $dir_result = $this->filesystem->create_directory( $plugin_dir );
        if ( ! $dir_result['success'] ) {
            return $dir_result;
        }

        // Generate main plugin file
        $main_file_content = $this->generate_main_file( $config );
        $main_file_path    = $plugin_dir . '/' . $config['slug'] . '.php';

        $write_result = $this->filesystem->write_file( $main_file_path, $main_file_content, false );
        if ( ! $write_result['success'] ) {
            return $write_result;
        }

        // Create additional structure
        $this->create_plugin_structure( $plugin_dir, $config );

        // Create custom files if provided
        if ( ! empty( $config['files'] ) ) {
            foreach ( $config['files'] as $file ) {
                $file_path = $plugin_dir . '/' . ltrim( $file['path'], '/' );
                $this->filesystem->write_file( $file_path, $file['content'], false );
            }
        }

        $this->logger->info( 'plugin_created', [
            'plugin_slug' => $config['slug'],
            'plugin_name' => $config['name'],
            'plugin_dir'  => $plugin_dir,
        ]);

        return [
            'success'     => true,
            'plugin_slug' => $config['slug'],
            'plugin_name' => $config['name'],
            'plugin_dir'  => $plugin_dir,
            'main_file'   => $main_file_path,
            'message'     => sprintf(
                /* translators: %s: Plugin name */
                __( 'Plugin "%s" created successfully', 'creator-core' ),
                $config['name']
            ),
        ];
    }

    /**
     * Generate main plugin file content
     *
     * @param array $config Plugin configuration.
     * @return string PHP file content.
     */
    private function generate_main_file( array $config ): string {
        $header = <<<PHP
<?php
/**
 * Plugin Name: {$config['name']}
 * Plugin URI: {$config['author_uri']}
 * Description: {$config['description']}
 * Version: {$config['version']}
 * Author: {$config['author']}
 * Author URI: {$config['author_uri']}
 * License: {$config['license']}
 * Text Domain: {$config['text_domain']}
 * Domain Path: /languages
 *
 * @package {$config['namespace']}
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( '{$this->get_constant_prefix($config['slug'])}_VERSION', '{$config['version']}' );
define( '{$this->get_constant_prefix($config['slug'])}_PATH', plugin_dir_path( __FILE__ ) );
define( '{$this->get_constant_prefix($config['slug'])}_URL', plugin_dir_url( __FILE__ ) );

PHP;

        // Add feature-specific code
        $features_code = $this->generate_features_code( $config );

        $main_class = <<<PHP

/**
 * Main plugin class
 */
class {$config['namespace']} {

    /**
     * Instance
     *
     * @var {$config['namespace']}|null
     */
    private static ?{$config['namespace']} \$instance = null;

    /**
     * Get instance
     *
     * @return {$config['namespace']}
     */
    public static function instance(): {$config['namespace']} {
        if ( self::\$instance === null ) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        \$this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action( 'init', [ \$this, 'load_textdomain' ] );
        add_action( 'admin_init', [ \$this, 'admin_init' ] );
        add_action( 'wp_enqueue_scripts', [ \$this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ \$this, 'admin_enqueue_scripts' ] );
{$features_code}
    }

    /**
     * Load text domain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( '{$config['text_domain']}', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Admin init
     */
    public function admin_init(): void {
        // Admin initialization code
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts(): void {
        // Enqueue frontend scripts and styles
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts(): void {
        // Enqueue admin scripts and styles
    }
}

// Initialize plugin
function {$config['slug']}_init(): {$config['namespace']} {
    return {$config['namespace']}::instance();
}
add_action( 'plugins_loaded', '{$config['slug']}_init' );

/**
 * Activation hook
 */
function {$config['slug']}_activate(): void {
    // Activation code
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, '{$config['slug']}_activate' );

/**
 * Deactivation hook
 */
function {$config['slug']}_deactivate(): void {
    // Deactivation code
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, '{$config['slug']}_deactivate' );

PHP;

        return $header . $main_class;
    }

    /**
     * Generate features-specific code
     *
     * @param array $config Plugin configuration.
     * @return string PHP code for features.
     */
    private function generate_features_code( array $config ): string {
        $code = '';

        if ( in_array( 'shortcode', $config['features'], true ) ) {
            $code .= "        add_shortcode( '{$config['slug']}', [ \$this, 'render_shortcode' ] );\n";
        }

        if ( in_array( 'widget', $config['features'], true ) ) {
            $code .= "        add_action( 'widgets_init', [ \$this, 'register_widgets' ] );\n";
        }

        if ( in_array( 'rest_api', $config['features'], true ) ) {
            $code .= "        add_action( 'rest_api_init', [ \$this, 'register_rest_routes' ] );\n";
        }

        if ( in_array( 'admin_menu', $config['features'], true ) ) {
            $code .= "        add_action( 'admin_menu', [ \$this, 'add_admin_menu' ] );\n";
        }

        if ( in_array( 'settings', $config['features'], true ) ) {
            $code .= "        add_action( 'admin_init', [ \$this, 'register_settings' ] );\n";
        }

        if ( in_array( 'cpt', $config['features'], true ) ) {
            $code .= "        add_action( 'init', [ \$this, 'register_post_types' ] );\n";
        }

        if ( in_array( 'taxonomy', $config['features'], true ) ) {
            $code .= "        add_action( 'init', [ \$this, 'register_taxonomies' ] );\n";
        }

        if ( in_array( 'ajax', $config['features'], true ) ) {
            $code .= "        add_action( 'wp_ajax_{$config['slug']}_action', [ \$this, 'handle_ajax' ] );\n";
            $code .= "        add_action( 'wp_ajax_nopriv_{$config['slug']}_action', [ \$this, 'handle_ajax' ] );\n";
        }

        if ( in_array( 'cron', $config['features'], true ) ) {
            $code .= "        add_action( '{$config['slug']}_cron_hook', [ \$this, 'run_cron' ] );\n";
        }

        return $code;
    }

    /**
     * Create plugin directory structure
     *
     * @param string $plugin_dir Plugin directory.
     * @param array  $config     Plugin configuration.
     */
    private function create_plugin_structure( string $plugin_dir, array $config ): void {
        // Create standard directories
        $directories = [
            'includes',
            'assets/css',
            'assets/js',
            'assets/images',
            'templates',
            'languages',
        ];

        if ( in_array( 'admin_menu', $config['features'], true ) || in_array( 'settings', $config['features'], true ) ) {
            $directories[] = 'admin';
            $directories[] = 'admin/views';
        }

        if ( in_array( 'rest_api', $config['features'], true ) ) {
            $directories[] = 'includes/API';
        }

        foreach ( $directories as $dir ) {
            $this->filesystem->create_directory( $plugin_dir . '/' . $dir );
        }

        // Create index.php for security
        $index_content = "<?php\n// Silence is golden.\n";
        $this->create_index_files( $plugin_dir, $index_content );

        // Create readme.txt
        $readme = $this->generate_readme( $config );
        $this->filesystem->write_file( $plugin_dir . '/readme.txt', $readme, false );

        // Create uninstall.php
        $uninstall = $this->generate_uninstall( $config );
        $this->filesystem->write_file( $plugin_dir . '/uninstall.php', $uninstall, false );
    }

    /**
     * Create index.php files in directories for security
     *
     * @param string $base_dir   Base directory.
     * @param string $content    Content for index.php.
     */
    private function create_index_files( string $base_dir, string $content ): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $base_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            if ( $item->isDir() ) {
                $index_path = $item->getPathname() . '/index.php';
                if ( ! file_exists( $index_path ) ) {
                    $this->filesystem->write_file( $index_path, $content, false );
                }
            }
        }

        // Also create in root
        $this->filesystem->write_file( $base_dir . '/index.php', $content, false );
    }

    /**
     * Generate readme.txt content
     *
     * @param array $config Plugin configuration.
     * @return string
     */
    private function generate_readme( array $config ): string {
        return <<<README
=== {$config['name']} ===
Contributors: {$config['author']}
Tags: wordpress, plugin
Requires at least: 5.8
Tested up to: 6.4
Stable tag: {$config['version']}
License: {$config['license']}
License URI: https://www.gnu.org/licenses/gpl-2.0.html

{$config['description']}

== Description ==

{$config['description']}

This plugin was created by Creator AI for WordPress.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/{$config['slug']}` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->{$config['name']} screen to configure the plugin.

== Changelog ==

= {$config['version']} =
* Initial release

README;
    }

    /**
     * Generate uninstall.php content
     *
     * @param array $config Plugin configuration.
     * @return string
     */
    private function generate_uninstall( array $config ): string {
        return <<<PHP
<?php
/**
 * Uninstall handler for {$config['name']}
 *
 * @package {$config['namespace']}
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( '{$config['slug']}_settings' );
delete_option( '{$config['slug']}_version' );

// Delete transients
delete_transient( '{$config['slug']}_cache' );

// Clean up any custom tables if needed
// global \$wpdb;
// \$wpdb->query( "DROP TABLE IF EXISTS {\$wpdb->prefix}{$config['slug']}_table" );

PHP;
    }

    /**
     * Get constant prefix from slug
     *
     * @param string $slug Plugin slug.
     * @return string
     */
    private function get_constant_prefix( string $slug ): string {
        return strtoupper( str_replace( '-', '_', $slug ) );
    }

    /**
     * Install and activate a plugin
     *
     * @param string $plugin_slug Plugin slug.
     * @return array Result with success status.
     */
    public function activate_plugin( string $plugin_slug ): array {
        $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';

        if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
            return [
                'success' => false,
                'error'   => __( 'Plugin file not found', 'creator-core' ),
            ];
        }

        // Include plugin.php for activation function
        if ( ! function_exists( 'activate_plugin' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        $this->logger->info( 'plugin_activated', [
            'plugin_slug' => $plugin_slug,
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: Plugin slug */
                __( 'Plugin "%s" activated successfully', 'creator-core' ),
                $plugin_slug
            ),
        ];
    }

    /**
     * Deactivate a plugin
     *
     * @param string $plugin_slug Plugin slug.
     * @return array Result with success status.
     */
    public function deactivate_plugin( string $plugin_slug ): array {
        $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';

        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins( $plugin_file );

        $this->logger->info( 'plugin_deactivated', [
            'plugin_slug' => $plugin_slug,
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: Plugin slug */
                __( 'Plugin "%s" deactivated successfully', 'creator-core' ),
                $plugin_slug
            ),
        ];
    }

    /**
     * Delete a plugin
     *
     * @param string $plugin_slug Plugin slug.
     * @param bool   $backup      Create backup before deletion.
     * @return array Result with success status.
     */
    public function delete_plugin( string $plugin_slug, bool $backup = true ): array {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if ( ! is_dir( $plugin_dir ) ) {
            return [
                'success' => false,
                'error'   => __( 'Plugin not found', 'creator-core' ),
            ];
        }

        // Deactivate first
        $this->deactivate_plugin( $plugin_slug );

        // Create backup if requested
        if ( $backup ) {
            $backup_result = $this->backup_plugin( $plugin_slug );
            if ( ! $backup_result['success'] ) {
                return [
                    'success' => false,
                    'error'   => __( 'Failed to create backup before deletion', 'creator-core' ),
                ];
            }
        }

        // Delete plugin directory
        if ( ! function_exists( 'delete_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
        $result      = delete_plugins( [ $plugin_file ] );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'error'   => $result->get_error_message(),
            ];
        }

        $this->logger->info( 'plugin_deleted', [
            'plugin_slug' => $plugin_slug,
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: Plugin slug */
                __( 'Plugin "%s" deleted successfully', 'creator-core' ),
                $plugin_slug
            ),
        ];
    }

    /**
     * Backup a plugin
     *
     * @param string $plugin_slug Plugin slug.
     * @return array Result with backup path.
     */
    public function backup_plugin( string $plugin_slug ): array {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if ( ! is_dir( $plugin_dir ) ) {
            return [
                'success' => false,
                'error'   => __( 'Plugin not found', 'creator-core' ),
            ];
        }

        $backup_dir  = WP_CONTENT_DIR . '/creator-backups/plugins';
        $backup_name = $plugin_slug . '_' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
        $backup_path = $backup_dir . '/' . $backup_name;

        if ( ! is_dir( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
        }

        // Create ZIP archive
        $zip = new \ZipArchive();
        if ( $zip->open( $backup_path, \ZipArchive::CREATE ) !== true ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to create backup archive', 'creator-core' ),
            ];
        }

        $this->add_directory_to_zip( $zip, $plugin_dir, $plugin_slug );
        $zip->close();

        $this->logger->info( 'plugin_backed_up', [
            'plugin_slug' => $plugin_slug,
            'backup_path' => $backup_path,
        ]);

        return [
            'success'     => true,
            'backup_path' => $backup_path,
            'plugin_slug' => $plugin_slug,
        ];
    }

    /**
     * Add directory contents to ZIP archive
     *
     * @param \ZipArchive $zip       ZIP archive.
     * @param string      $directory Directory to add.
     * @param string      $base      Base name in archive.
     */
    private function add_directory_to_zip( \ZipArchive $zip, string $directory, string $base ): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $item ) {
            $path_in_zip = $base . '/' . $iterator->getSubPathname();

            if ( $item->isDir() ) {
                $zip->addEmptyDir( $path_in_zip );
            } else {
                $zip->addFile( $item->getPathname(), $path_in_zip );
            }
        }
    }

    /**
     * Add file to plugin
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $file_path   Relative path within plugin.
     * @param string $content     File content.
     * @return array Result with success status.
     */
    public function add_plugin_file( string $plugin_slug, string $file_path, string $content ): array {
        $plugin_dir   = WP_PLUGIN_DIR . '/' . $plugin_slug;
        $full_path    = $plugin_dir . '/' . ltrim( $file_path, '/' );

        if ( ! is_dir( $plugin_dir ) ) {
            return [
                'success' => false,
                'error'   => __( 'Plugin not found', 'creator-core' ),
            ];
        }

        return $this->filesystem->write_file( $full_path, $content );
    }

    /**
     * Get plugin info
     *
     * @param string $plugin_slug Plugin slug.
     * @return array Plugin information.
     */
    public function get_plugin_info( string $plugin_slug ): array {
        $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        if ( ! file_exists( $plugin_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Plugin not found', 'creator-core' ),
            ];
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $plugin_path );

        return [
            'success' => true,
            'plugin'  => [
                'slug'        => $plugin_slug,
                'name'        => $plugin_data['Name'],
                'version'     => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author'      => $plugin_data['Author'],
                'author_uri'  => $plugin_data['AuthorURI'],
                'plugin_uri'  => $plugin_data['PluginURI'],
                'text_domain' => $plugin_data['TextDomain'],
                'is_active'   => is_plugin_active( $plugin_file ),
                'path'        => $plugin_path,
            ],
        ];
    }

    /**
     * List all plugins (including inactive)
     *
     * @return array List of plugins.
     */
    public function list_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', [] );
        $plugins        = [];

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $plugins[] = [
                'file'        => $plugin_file,
                'slug'        => dirname( $plugin_file ),
                'name'        => $plugin_data['Name'],
                'version'     => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author'      => $plugin_data['Author'],
                'is_active'   => in_array( $plugin_file, $active_plugins, true ),
            ];
        }

        return [
            'success' => true,
            'plugins' => $plugins,
            'count'   => count( $plugins ),
        ];
    }
}
