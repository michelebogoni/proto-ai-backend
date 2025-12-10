<?php
/**
 * Plugin Detector Item Template
 *
 * @package CreatorCore
 * @var string $key Plugin key
 * @var array $plugin Plugin status data
 */

defined( 'ABSPATH' ) || exit;

$plugin_detector = new \CreatorCore\Integrations\PluginDetector();
?>
<div class="creator-plugin-item <?php echo $plugin['active'] ? 'active' : 'inactive'; ?>" data-plugin-key="<?php echo esc_attr( $key ); ?>">
    <div class="creator-plugin-status">
        <?php if ( $plugin['active'] && $plugin['compatible'] ) : ?>
            <span class="dashicons dashicons-yes-alt status-ok"></span>
        <?php elseif ( $plugin['installed'] && ! $plugin['active'] ) : ?>
            <span class="dashicons dashicons-warning status-warning"></span>
        <?php else : ?>
            <span class="dashicons dashicons-marker status-inactive"></span>
        <?php endif; ?>
    </div>

    <div class="creator-plugin-info">
        <span class="creator-plugin-name"><?php echo esc_html( $plugin['name'] ); ?></span>
        <?php if ( $plugin['version'] ) : ?>
            <span class="creator-plugin-version">v<?php echo esc_html( $plugin['version'] ); ?></span>
        <?php endif; ?>

        <span class="creator-plugin-badge optional"><?php esc_html_e( 'Suggested', 'creator-core' ); ?></span>
        <?php if ( ! empty( $plugin['benefit'] ) ) : ?>
            <span class="creator-plugin-benefit"><?php echo esc_html( $plugin['benefit'] ); ?></span>
        <?php endif; ?>
    </div>

    <div class="creator-plugin-actions">
        <?php if ( $plugin['active'] && $plugin['compatible'] ) : ?>
            <span class="creator-plugin-status-text installed">
                <?php esc_html_e( 'Installed', 'creator-core' ); ?>
            </span>
        <?php elseif ( $plugin['installed'] && ! $plugin['active'] ) : ?>
            <button type="button"
                class="creator-btn creator-btn-sm creator-btn-primary creator-activate-plugin"
                data-plugin="<?php echo esc_attr( $plugin_detector->get_integration_info( $key )['slug'] ?? '' ); ?>">
                <?php esc_html_e( 'Activate', 'creator-core' ); ?>
            </button>
        <?php elseif ( ! $plugin['installed'] ) : ?>
            <button type="button" class="creator-btn creator-btn-sm creator-btn-link creator-skip-plugin">
                <?php esc_html_e( 'Skip', 'creator-core' ); ?>
            </button>
            <a href="<?php echo esc_url( $plugin_detector->get_install_url( $key ) ); ?>"
               class="creator-btn creator-btn-sm creator-btn-primary"
               target="_blank">
                <?php esc_html_e( 'Install', 'creator-core' ); ?>
            </a>
        <?php endif; ?>

        <?php if ( $plugin['installed'] && ! $plugin['compatible'] ) : ?>
            <span class="creator-plugin-warning">
                <?php
                printf(
                    /* translators: %s: Minimum required version */
                    esc_html__( 'Requires v%s+', 'creator-core' ),
                    esc_html( $plugin['min_version'] )
                );
                ?>
            </span>
        <?php endif; ?>
    </div>
</div>
