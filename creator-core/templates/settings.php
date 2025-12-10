<?php
/**
 * Settings Page Template
 *
 * @package CreatorCore
 * @var array $data Settings data
 */

defined( 'ABSPATH' ) || exit;

$settings_page = new \CreatorCore\Admin\Settings(
    new \CreatorCore\Integrations\ProxyClient(),
    new \CreatorCore\Integrations\PluginDetector()
);
?>
<div class="wrap creator-settings">
    <h1><?php esc_html_e( 'Creator Settings', 'creator-core' ); ?></h1>

    <?php settings_errors( 'creator_settings' ); ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'creator_save_settings', 'creator_settings_nonce' ); ?>

        <div class="creator-settings-tabs">
            <nav class="creator-tabs-nav">
                <a href="#api" class="creator-tab active" data-tab="api"><?php esc_html_e( 'API Configuration', 'creator-core' ); ?></a>
                <a href="#profile" class="creator-tab" data-tab="profile"><?php esc_html_e( 'Your Profile', 'creator-core' ); ?></a>
                <a href="#context" class="creator-tab" data-tab="context"><?php esc_html_e( 'AI Context', 'creator-core' ); ?></a>
                <a href="#backup" class="creator-tab" data-tab="backup"><?php esc_html_e( 'Backup Settings', 'creator-core' ); ?></a>
                <a href="#permissions" class="creator-tab" data-tab="permissions"><?php esc_html_e( 'Permissions', 'creator-core' ); ?></a>
                <a href="#advanced" class="creator-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'creator-core' ); ?></a>
            </nav>

            <!-- API Configuration -->
            <div id="api" class="creator-tab-content active">
                <h2><?php esc_html_e( 'API Configuration', 'creator-core' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="creator_license_key"><?php esc_html_e( 'License Key', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="creator_license_key" name="creator_license_key"
                                   value="<?php echo esc_attr( $data['settings']['license_key'] ); ?>"
                                   class="regular-text" placeholder="CREATOR-XXXX-XXXX-XXXX">
                            <button type="button" id="validate-license" class="button">
                                <?php esc_html_e( 'Validate', 'creator-core' ); ?>
                            </button>
                            <span id="license-validation-status"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Connection Status', 'creator-core' ); ?></th>
                        <td>
                            <?php if ( $data['connection']['connected'] ) : ?>
                                <span class="creator-status-badge success">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e( 'Connected', 'creator-core' ); ?>
                                </span>
                                <?php if ( ! empty( $data['connection']['admin_mode'] ) ) : ?>
                                    <span class="creator-status-badge info">
                                        <?php esc_html_e( 'Admin License', 'creator-core' ); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="creator-status-badge error">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php esc_html_e( 'Not Connected', 'creator-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Profile Settings -->
            <div id="profile" class="creator-tab-content">
                <!-- Default AI Model Section -->
                <h2><?php esc_html_e( 'Default AI Model', 'creator-core' ); ?></h2>
                <p><?php esc_html_e( 'Choose your preferred AI model. This will be the default for new chats, but you can change it per-chat.', 'creator-core' ); ?></p>

                <?php
                $models_info = \CreatorCore\User\UserProfile::get_models_info();
                $current_model = \CreatorCore\User\UserProfile::get_default_model();
                ?>

                <div class="creator-model-selector-horizontal">
                    <?php foreach ( $models_info as $model_key => $model_info ) : ?>
                        <label class="creator-model-option-card <?php echo $current_model === $model_key ? 'selected' : ''; ?>">
                            <input type="radio" name="creator_default_model" value="<?php echo esc_attr( $model_key ); ?>"
                                   <?php checked( $current_model, $model_key ); ?>>

                            <div class="creator-model-card-content">
                                <div class="creator-model-card-header">
                                    <span class="creator-model-icon"><?php echo esc_html( $model_info['icon'] ); ?></span>
                                    <div class="creator-model-info">
                                        <span class="creator-model-name"><?php echo esc_html( $model_info['label'] ); ?></span>
                                        <span class="creator-model-provider-badge"><?php echo esc_html( $model_info['provider'] ); ?></span>
                                    </div>
                                </div>

                                <p class="creator-model-description">
                                    <?php echo esc_html( $model_info['description'] ); ?>
                                </p>

                                <div class="creator-model-fallback-info">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php echo esc_html( $model_info['fallback'] ); ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <hr class="creator-section-divider">

                <!-- Competency Level Section -->
                <h2><?php esc_html_e( 'Your Competency Level', 'creator-core' ); ?></h2>
                <p><?php esc_html_e( 'This setting helps Creator communicate with you appropriately and suggest solutions that match your skill level.', 'creator-core' ); ?></p>

                <div class="creator-profile-options-horizontal">
                    <?php foreach ( $data['user_profile']['levels'] as $level_key => $level_info ) : ?>
                        <div class="creator-profile-option-card <?php echo $data['user_profile']['current_level'] === $level_key ? 'selected' : ''; ?>">
                            <label>
                                <input type="radio" name="creator_user_level" value="<?php echo esc_attr( $level_key ); ?>"
                                       <?php checked( $data['user_profile']['current_level'], $level_key ); ?>>

                                <div class="creator-profile-card-content">
                                    <div class="creator-profile-card-header">
                                        <span class="creator-profile-level-badge level-<?php echo esc_attr( $level_key ); ?>">
                                            <?php echo esc_html( $level_info['label'] ); ?>
                                        </span>
                                        <span class="creator-profile-level-title"><?php echo esc_html( $level_info['title'] ); ?></span>
                                    </div>

                                    <p class="creator-profile-description">
                                        <?php echo esc_html( $level_info['description'] ); ?>
                                    </p>

                                    <div class="creator-profile-caps">
                                        <?php if ( ! empty( $level_info['capabilities']['can'] ) ) : ?>
                                            <?php foreach ( $level_info['capabilities']['can'] as $cap ) : ?>
                                                <span class="creator-cap-badge cap-yes">
                                                    <span class="dashicons dashicons-yes-alt"></span>
                                                    <?php echo esc_html( $cap ); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $level_info['capabilities']['cannot'] ) ) : ?>
                                            <?php foreach ( $level_info['capabilities']['cannot'] as $cap ) : ?>
                                                <span class="creator-cap-badge cap-no">
                                                    <span class="dashicons dashicons-dismiss"></span>
                                                    <?php echo esc_html( $cap ); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="creator-profile-behavior-info">
                                        <strong><?php esc_html_e( 'Creator will:', 'creator-core' ); ?></strong>
                                        <?php echo esc_html( $level_info['behavior'] ); ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="creator-profile-actions">
                    <button type="button" id="save-profile-btn" class="button button-primary">
                        <?php esc_html_e( 'Update Profile', 'creator-core' ); ?>
                    </button>
                    <span id="profile-status"></span>
                </div>
            </div>

            <!-- AI Context -->
            <div id="context" class="creator-tab-content">
                <h2><?php esc_html_e( 'AI Context', 'creator-core' ); ?></h2>
                <p><?php esc_html_e( 'Creator generates a context document that helps the AI understand your WordPress site, installed plugins, custom post types, and more.', 'creator-core' ); ?></p>

                <?php $context_status = isset( $data['context_status'] ) ? $data['context_status'] : [
                    'has_context'   => false,
                    'generated_at'  => null,
                    'is_stale'      => false,
                    'plugins_count' => 0,
                    'cpts_count'    => 0,
                    'acf_groups'    => 0,
                    'sitemap_count' => 0,
                ]; ?>

                <div class="creator-context-status-card">
                    <div class="creator-context-header">
                        <h3>
                            <?php if ( $context_status['has_context'] ) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php esc_html_e( 'Context Generated', 'creator-core' ); ?>
                            <?php else : ?>
                                <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                                <?php esc_html_e( 'No Context', 'creator-core' ); ?>
                            <?php endif; ?>
                        </h3>
                        <?php if ( $context_status['is_stale'] ) : ?>
                            <span class="creator-status-badge warning">
                                <?php esc_html_e( 'Needs Refresh', 'creator-core' ); ?>
                            </span>
                        <?php elseif ( $context_status['has_context'] ) : ?>
                            <span class="creator-status-badge success">
                                <?php esc_html_e( 'Up to Date', 'creator-core' ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $context_status['has_context'] ) : ?>
                        <table class="form-table creator-context-stats">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Generated At', 'creator-core' ); ?></th>
                                <td>
                                    <?php
                                    if ( $context_status['generated_at'] ) {
                                        echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $context_status['generated_at'] ) ) );
                                    } else {
                                        esc_html_e( 'Unknown', 'creator-core' );
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Detected Plugins', 'creator-core' ); ?></th>
                                <td><strong><?php echo esc_html( $context_status['plugins_count'] ); ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Custom Post Types', 'creator-core' ); ?></th>
                                <td><strong><?php echo esc_html( $context_status['cpts_count'] ); ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'ACF Field Groups', 'creator-core' ); ?></th>
                                <td><strong><?php echo esc_html( $context_status['acf_groups'] ); ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Sitemap Entries', 'creator-core' ); ?></th>
                                <td><strong><?php echo esc_html( $context_status['sitemap_count'] ); ?></strong></td>
                            </tr>
                        </table>
                    <?php else : ?>
                        <p class="creator-context-empty">
                            <?php esc_html_e( 'No context has been generated yet. Click the button below to generate it now.', 'creator-core' ); ?>
                        </p>
                    <?php endif; ?>

                    <div class="creator-context-actions">
                        <button type="button" id="refresh-context-btn" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php
                            if ( $context_status['has_context'] ) {
                                esc_html_e( 'Refresh Context', 'creator-core' );
                            } else {
                                esc_html_e( 'Generate Context', 'creator-core' );
                            }
                            ?>
                        </button>
                        <span id="context-status"></span>
                    </div>
                </div>

                <div class="creator-context-info">
                    <h3><?php esc_html_e( 'What\'s included in the context?', 'creator-core' ); ?></h3>
                    <ul>
                        <li><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Active plugins with documentation and capabilities', 'creator-core' ); ?></li>
                        <li><span class="dashicons dashicons-admin-post"></span> <?php esc_html_e( 'Custom post types and their configurations', 'creator-core' ); ?></li>
                        <li><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Taxonomies (categories, tags, custom)', 'creator-core' ); ?></li>
                        <li><span class="dashicons dashicons-forms"></span> <?php esc_html_e( 'ACF field groups and field definitions', 'creator-core' ); ?></li>
                        <li><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'Site structure and page hierarchy', 'creator-core' ); ?></li>
                        <li><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'System information (WP version, PHP version)', 'creator-core' ); ?></li>
                    </ul>

                    <p class="description">
                        <?php esc_html_e( 'The context is automatically refreshed when you activate/deactivate plugins, change themes, or update your user profile.', 'creator-core' ); ?>
                    </p>
                </div>
            </div>

            <!-- Backup Settings -->
            <div id="backup" class="creator-tab-content">
                <h2><?php esc_html_e( 'Backup Settings', 'creator-core' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="creator_backup_retention"><?php esc_html_e( 'Retention Period', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="creator_backup_retention" name="creator_backup_retention"
                                   value="<?php echo esc_attr( $data['settings']['backup_retention'] ); ?>"
                                   min="1" max="365" class="small-text"> <?php esc_html_e( 'days', 'creator-core' ); ?>
                            <p class="description"><?php esc_html_e( 'How long to keep backup snapshots', 'creator-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="creator_max_backup_size_mb"><?php esc_html_e( 'Maximum Size', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="creator_max_backup_size_mb" name="creator_max_backup_size_mb"
                                   value="<?php echo esc_attr( $data['settings']['max_backup_size_mb'] ); ?>"
                                   min="50" max="5000" class="small-text"> MB
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Current Usage', 'creator-core' ); ?></th>
                        <td>
                            <strong><?php echo esc_html( $data['backup_stats']['total_size_mb'] ); ?> MB</strong>
                            (<?php echo esc_html( $data['backup_stats']['total_snapshots'] ); ?> <?php esc_html_e( 'snapshots', 'creator-core' ); ?>)
                            <br>
                            <button type="button" id="cleanup-backups" class="button">
                                <?php esc_html_e( 'Cleanup Old Backups', 'creator-core' ); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Permissions -->
            <div id="permissions" class="creator-tab-content">
                <h2><?php esc_html_e( 'User Permissions', 'creator-core' ); ?></h2>

                <p><?php esc_html_e( 'Select which roles can use Creator:', 'creator-core' ); ?></p>

                <table class="form-table">
                    <?php foreach ( $data['roles'] as $role_slug => $role ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $role['name'] ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="creator_allowed_roles[]"
                                           value="<?php echo esc_attr( $role_slug ); ?>"
                                           <?php checked( $role['enabled'] ); ?>
                                           <?php disabled( $role_slug === 'administrator' ); ?>>
                                    <?php esc_html_e( 'Can use Creator', 'creator-core' ); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Advanced -->
            <div id="advanced" class="creator-tab-content">
                <h2><?php esc_html_e( 'Advanced Settings', 'creator-core' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Debug Mode', 'creator-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="creator_debug_mode" value="1"
                                       <?php checked( $data['settings']['debug_mode'] ); ?>>
                                <?php esc_html_e( 'Enable debug mode', 'creator-core' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Shows additional debugging information', 'creator-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="creator_log_level"><?php esc_html_e( 'Log Level', 'creator-core' ); ?></label>
                        </th>
                        <td>
                            <select id="creator_log_level" name="creator_log_level">
                                <?php foreach ( $settings_page->get_log_levels() as $level => $label ) : ?>
                                    <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $data['settings']['log_level'], $level ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Clear Cache', 'creator-core' ); ?></th>
                        <td>
                            <button type="button" id="clear-cache" class="button">
                                <?php esc_html_e( 'Clear Cache', 'creator-core' ); ?>
                            </button>
                            <span id="cache-status"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Uninstall', 'creator-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="creator_delete_data_on_uninstall" value="1"
                                       <?php checked( $data['settings']['delete_data_on_uninstall'] ); ?>>
                                <?php esc_html_e( 'Delete all data when uninstalling', 'creator-core' ); ?>
                            </label>
                            <p class="description creator-warning">
                                <?php esc_html_e( 'Warning: This will permanently delete all Creator data including chats and backups.', 'creator-core' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button( __( 'Save Settings', 'creator-core' ) ); ?>
    </form>
</div>
