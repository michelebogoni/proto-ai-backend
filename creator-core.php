<?php
/**
 * Plugin Name: Creator
 * Plugin URI: https://github.com/michelebogoni/creator-core-plugin
 * Description: AI-powered WordPress development agent - Create plugins, analyze code, debug issues, and automate WordPress development with full site access
 * Version: 1.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Aloud Marketing
 * Author URI: https://aloudmarketing.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: creator-core
 * Domain Path: /languages
 *
 * @package CreatorCore
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'CREATOR_CORE_VERSION', '1.2.0' );
define( 'CREATOR_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CREATOR_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'CREATOR_CORE_BASENAME', plugin_basename( __FILE__ ) );
define( 'CREATOR_CORE_FILE', __FILE__ );

// Proxy configuration
define( 'CREATOR_PROXY_URL', 'https://creator-ai-proxy.firebaseapp.com' );

// Debug mode
define( 'CREATOR_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );

// Minimum requirements
define( 'CREATOR_MIN_PHP_VERSION', '7.4' );
define( 'CREATOR_MIN_WP_VERSION', '5.8' );

// Show setup reminder notice if wizard not completed
add_action( 'admin_notices', function() {
    if ( get_option( 'creator_setup_completed' ) ) {
        return;
    }
    $page = isset( $_GET['page'] ) ? (string) $_GET['page'] : '';
    if ( $page !== '' && strpos( $page, 'creator-' ) === 0 ) {
        return;
    }
    $url = admin_url( 'admin.php?page=creator-setup' );
    echo '<div class="notice notice-info"><p><strong>Creator:</strong> ';
    echo 'Please complete the <a href="' . esc_url( $url ) . '">setup wizard</a> to get started.</p></div>';
});

/**
 * Check minimum requirements before loading the plugin
 *
 * @return bool
 */
function creator_core_check_requirements(): bool {
    $errors = [];

    // Check PHP version
    if ( version_compare( PHP_VERSION, CREATOR_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            __( 'Creator Core requires PHP %1$s or higher. You are running PHP %2$s.', 'creator-core' ),
            CREATOR_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Check WordPress version
    global $wp_version;
    if ( version_compare( $wp_version, CREATOR_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Required WordPress version, 2: Current WordPress version */
            __( 'Creator Core requires WordPress %1$s or higher. You are running WordPress %2$s.', 'creator-core' ),
            CREATOR_MIN_WP_VERSION,
            $wp_version
        );
    }

    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', function() use ( $errors ) {
            foreach ( $errors as $error ) {
                printf(
                    '<div class="notice notice-error"><p><strong>Creator Core:</strong> %s</p></div>',
                    esc_html( $error )
                );
            }
        });
        return false;
    }

    return true;
}

/**
 * Initialize the plugin
 */
function creator_core_init(): void {
    // Check requirements
    if ( ! creator_core_check_requirements() ) {
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'creator-core', false, dirname( CREATOR_CORE_BASENAME ) . '/languages' );

    // Load autoloader
    require_once CREATOR_CORE_PATH . 'includes/Autoloader.php';
    \CreatorCore\Autoloader::register();

    // Run database migrations if needed
    require_once CREATOR_CORE_PATH . 'database/migrations.php';
    $migrations = new \CreatorCore\Database\Migrations();
    if ( $migrations->needs_migration() ) {
        $migrations->run();
    }

    // Initialize the plugin
    require_once CREATOR_CORE_PATH . 'includes/Loader.php';
    $loader = new \CreatorCore\Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'creator_core_init' );

/**
 * Plugin activation hook
 */
function creator_core_activate(): void {
    // Check requirements on activation
    if ( ! creator_core_check_requirements() ) {
        deactivate_plugins( CREATOR_CORE_BASENAME );
        wp_die(
            esc_html__( 'Creator Core cannot be activated. Please check system requirements.', 'creator-core' ),
            esc_html__( 'Plugin Activation Error', 'creator-core' ),
            [ 'back_link' => true ]
        );
    }

    // Load autoloader for activation
    require_once CREATOR_CORE_PATH . 'includes/Autoloader.php';
    \CreatorCore\Autoloader::register();

    // Run activation tasks
    require_once CREATOR_CORE_PATH . 'includes/Activator.php';
    \CreatorCore\Activator::activate();
}
register_activation_hook( __FILE__, 'creator_core_activate' );

/**
 * Redirect to setup wizard after plugin activation
 * Follows official WordPress documentation pattern
 *
 * @see https://developer.wordpress.org/reference/functions/register_activation_hook/
 */
function creator_core_activation_redirect() {
    // Check if redirect flag is set
    if ( is_admin() && 'yes' === get_option( 'creator_do_activation_redirect' ) ) {
        // Delete option immediately to prevent future redirects
        delete_option( 'creator_do_activation_redirect' );

        // Don't redirect during bulk activation
        if ( isset( $_GET['activate-multi'] ) ) {
            return;
        }

        // Don't redirect if setup already completed
        if ( get_option( 'creator_setup_completed' ) ) {
            return;
        }

        // Redirect to setup wizard
        wp_safe_redirect( admin_url( 'admin.php?page=creator-setup' ) );
        exit;
    }
}
add_action( 'admin_init', 'creator_core_activation_redirect' );

/**
 * Show admin notice with setup wizard link if redirect didn't work
 * This is a fallback mechanism
 */
function creator_core_activation_notice() {
    // Only show if setup not completed and we're not on setup page
    if ( get_option( 'creator_setup_completed' ) ) {
        return;
    }

    // Don't show on setup page
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'creator-setup' ) {
        return;
    }

    // Only show to admins
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $setup_url = admin_url( 'admin.php?page=creator-setup' );
    ?>
    <div class="notice notice-info is-dismissible">
        <p>
            <strong><?php esc_html_e( 'Creator', 'creator-core' ); ?>:</strong>
            <?php esc_html_e( 'Thank you for installing Creator! Please complete the setup wizard to get started.', 'creator-core' ); ?>
            <a href="<?php echo esc_url( $setup_url ); ?>" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e( 'Start Setup Wizard', 'creator-core' ); ?>
            </a>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'creator_core_activation_notice' );

/**
 * JavaScript fallback for activation redirect
 * Uses the redirect option as indicator (if it exists, PHP redirect didn't work)
 */
function creator_core_activation_redirect_js() {
    // Only on plugins page
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'plugins' ) {
        return;
    }

    // Check if redirect option still exists (means PHP redirect didn't happen)
    $should_redirect = get_option( 'creator_do_activation_redirect' );
    if ( ! $should_redirect ) {
        return;
    }

    // Delete the option
    delete_option( 'creator_do_activation_redirect' );

    // Don't redirect if setup completed
    if ( get_option( 'creator_setup_completed' ) ) {
        return;
    }

    $setup_url = admin_url( 'admin.php?page=creator-setup' );
    ?>
    <script type="text/javascript">
        (function() {
            window.location.href = '<?php echo esc_js( $setup_url ); ?>';
        })();
    </script>
    <?php
}
add_action( 'admin_footer', 'creator_core_activation_redirect_js' );

/**
 * Plugin deactivation hook
 */
function creator_core_deactivate(): void {
    require_once CREATOR_CORE_PATH . 'includes/Autoloader.php';
    \CreatorCore\Autoloader::register();

    require_once CREATOR_CORE_PATH . 'includes/Deactivator.php';
    \CreatorCore\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'creator_core_deactivate' );

/**
 * Plugin uninstall hook is handled in uninstall.php
 */
