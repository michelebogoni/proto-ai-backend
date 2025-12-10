<?php
/**
 * Setup Wizard Template
 *
 * @package CreatorCore
 * @var string $current_step Current step
 * @var array $steps All steps
 * @var array $step_data Data for current step
 */

defined( 'ABSPATH' ) || exit;

$setup_wizard = new \CreatorCore\Admin\SetupWizard( new \CreatorCore\Integrations\PluginDetector() );
?>
<div class="creator-setup-wizard">
    <div class="creator-setup-header">
        <div class="creator-setup-logo">
            <span class="dashicons dashicons-superhero-alt"></span>
            <span class="creator-setup-title"><?php esc_html_e( 'Creator Core', 'creator-core' ); ?></span>
        </div>
    </div>

    <!-- Progress Steps -->
    <div class="creator-setup-steps">
        <?php foreach ( $data['steps'] as $step_key => $step ) : ?>
            <?php
            $is_current   = $step_key === $data['current_step'];
            $step_number  = $step['order'];
            $current_order = $data['steps'][ $data['current_step'] ]['order'];
            $is_completed = $step_number < $current_order;
            ?>
            <div class="creator-setup-step <?php echo $is_current ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
                <span class="step-number">
                    <?php if ( $is_completed ) : ?>
                        <span class="dashicons dashicons-yes"></span>
                    <?php else : ?>
                        <?php echo esc_html( $step_number ); ?>
                    <?php endif; ?>
                </span>
                <span class="step-name"><?php echo esc_html( $step['name'] ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Step Content -->
    <div class="creator-setup-content">
        <?php
        switch ( $data['current_step'] ) :
            case 'welcome':
                $features = $data['step_data']['features'] ?? [];
                ?>
                <div class="creator-setup-section creator-welcome-section">
                    <div class="creator-welcome-header">
                        <h2><?php esc_html_e( 'Welcome to Creator', 'creator-core' ); ?></h2>
                        <p class="creator-welcome-subtitle"><?php esc_html_e( 'Your AI WordPress Assistant', 'creator-core' ); ?></p>
                    </div>

                    <p class="creator-welcome-intro"><?php esc_html_e( 'Here\'s what you can do with Creator:', 'creator-core' ); ?></p>

                    <!-- Feature Cards -->
                    <div class="creator-feature-cards">
                        <?php foreach ( $features as $key => $feature ) : ?>
                            <div class="creator-feature-card">
                                <div class="creator-feature-icon">
                                    <span class="dashicons dashicons-<?php echo esc_attr( $feature['icon'] ); ?>"></span>
                                </div>
                                <h3><?php echo esc_html( $feature['title'] ); ?></h3>
                                <p><?php echo esc_html( $feature['description'] ); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Warning note with backup checkbox -->
                    <div class="creator-warning-note">
                        <div class="creator-warning-note-header">
                            <span class="dashicons dashicons-warning"></span>
                            <strong><?php esc_html_e( 'Important Warning', 'creator-core' ); ?></strong>
                        </div>
                        <p>
                            <?php esc_html_e( 'Creator has the same capabilities as you do in WordPress. We recommend having daily backups enabled (standard practice). Your responsibility as site owner is to test changes on staging first.', 'creator-core' ); ?>
                        </p>
                        <a href="https://developer.wordpress.org/advanced-administration/security/backup/" target="_blank" rel="noopener noreferrer" class="creator-learn-link">
                            <?php esc_html_e( 'Learn about backup options', 'creator-core' ); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                        <div class="creator-backup-acknowledge">
                            <label class="creator-checkbox-inline">
                                <input type="checkbox" id="backup-acknowledged" name="backup_acknowledged" value="1">
                                <span><?php esc_html_e( 'I have backups enabled or will configure them', 'creator-core' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php
                break;

            case 'overview':
                $suggested = $data['step_data']['suggested_plugins'] ?? [];
                $backup = $data['step_data']['backup'] ?? [];
                ?>
                <div class="creator-setup-section creator-overview-section">
                    <h2><?php esc_html_e( 'Configuration', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Configure Creator settings and optional plugins.', 'creator-core' ); ?></p>

                    <!-- Section A: Suggested Plugins (Optional) -->
                    <?php if ( ! empty( $suggested ) ) : ?>
                        <div class="creator-suggested-plugins">
                            <h3><?php esc_html_e( 'Recommended Plugins (Optional)', 'creator-core' ); ?></h3>
                            <p class="creator-suggested-note">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e( 'These plugins can enhance Creator\'s capabilities, but are NOT required. Creator works with any WordPress setup.', 'creator-core' ); ?>
                            </p>
                            <div class="creator-suggested-list">
                                <?php foreach ( $suggested as $key => $plugin ) : ?>
                                    <div class="creator-suggested-item" data-plugin="<?php echo esc_attr( $key ); ?>">
                                        <div class="creator-suggested-info">
                                            <div class="creator-suggested-header">
                                                <strong><?php echo esc_html( $plugin['name'] ); ?></strong>
                                                <span class="creator-suggested-status">
                                                    <?php if ( $plugin['installed'] ) : ?>
                                                        <span class="status-installed"><?php esc_html_e( 'Installed', 'creator-core' ); ?></span>
                                                    <?php else : ?>
                                                        <span class="status-not-installed"><?php esc_html_e( 'Not Installed', 'creator-core' ); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <p class="creator-suggested-benefit"><?php echo esc_html( $plugin['benefit'] ); ?></p>
                                        </div>
                                        <div class="creator-suggested-actions">
                                            <?php if ( ! $plugin['installed'] ) : ?>
                                                <?php
                                                $slug_parts   = explode( '/', $plugin['key'] );
                                                $install_slug = $slug_parts[0] ?? $key;
                                                ?>
                                                <button type="button" class="creator-btn creator-btn-sm creator-install-plugin" data-plugin="<?php echo esc_attr( $install_slug ); ?>">
                                                    <?php esc_html_e( 'Install', 'creator-core' ); ?>
                                                </button>
                                            <?php elseif ( ! $plugin['active'] ) : ?>
                                                <button type="button" class="creator-btn creator-btn-sm creator-activate-plugin" data-plugin="<?php echo esc_attr( $plugin['key'] ); ?>">
                                                    <?php esc_html_e( 'Activate', 'creator-core' ); ?>
                                                </button>
                                            <?php else : ?>
                                                <span class="creator-status-ok">
                                                    <span class="dashicons dashicons-yes"></span>
                                                    <?php esc_html_e( 'Active', 'creator-core' ); ?>
                                                </span>
                                            <?php endif; ?>
                                            <button type="button" class="creator-btn-link creator-dismiss-suggestion" data-plugin="<?php echo esc_attr( $key ); ?>" title="<?php esc_attr_e( 'Dismiss suggestion', 'creator-core' ); ?>">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Section B: Chat Backup -->
                    <div class="creator-chat-backup">
                        <h3><?php esc_html_e( 'Chat Backup', 'creator-core' ); ?></h3>
                        <p class="creator-backup-description"><?php esc_html_e( 'Configure where chat history is stored and retention limits.', 'creator-core' ); ?></p>

                        <form id="creator-backup-form" class="creator-setup-form">
                            <div class="creator-form-row">
                                <label for="backup-path"><?php esc_html_e( 'Backup Path', 'creator-core' ); ?></label>
                                <input type="text" id="backup-path" name="backup_path"
                                       value="<?php echo esc_attr( $backup['backup_path'] ?? '' ); ?>"
                                       readonly class="creator-input-readonly">
                                <p class="description">
                                    <?php if ( $backup['path_writable'] ?? false ) : ?>
                                        <span class="creator-status-ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Writable', 'creator-core' ); ?></span>
                                    <?php else : ?>
                                        <span class="creator-status-warning"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Not writable', 'creator-core' ); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="creator-form-row-inline">
                                <div class="creator-form-field">
                                    <label for="retention-days"><?php esc_html_e( 'Retention (days)', 'creator-core' ); ?></label>
                                    <input type="number" id="retention-days" name="retention_days"
                                           value="<?php echo esc_attr( $backup['retention_days'] ?? 30 ); ?>"
                                           min="1" max="365" class="creator-input-small">
                                    <p class="description"><?php esc_html_e( 'Chat history older than this will be deleted', 'creator-core' ); ?></p>
                                </div>

                                <div class="creator-form-field">
                                    <label for="max-size-mb"><?php esc_html_e( 'Max Size (MB)', 'creator-core' ); ?></label>
                                    <input type="number" id="max-size-mb" name="max_size_mb"
                                           value="<?php echo esc_attr( $backup['max_size_mb'] ?? 500 ); ?>"
                                           min="50" max="5000" class="creator-input-small">
                                    <p class="description"><?php esc_html_e( 'Oldest chats deleted when exceeded', 'creator-core' ); ?></p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
                break;

            case 'license':
                ?>
                <div class="creator-setup-section">
                    <h2><?php esc_html_e( 'License Activation', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Enter your license key to activate Creator. This connects to our AI services.', 'creator-core' ); ?></p>

                    <form id="creator-license-form" class="creator-setup-form">
                        <div class="creator-form-row">
                            <label for="license-key"><?php esc_html_e( 'License Key', 'creator-core' ); ?></label>
                            <input type="text" id="license-key" name="license_key"
                                   value="<?php echo esc_attr( $data['step_data']['license_key'] ); ?>"
                                   placeholder="CREATOR-XXXX-XXXX-XXXX">

                            <?php if ( $data['step_data']['is_validated'] ) : ?>
                                <span class="creator-status-ok">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e( 'Valid', 'creator-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="creator-form-row">
                            <button type="button" id="validate-license-btn" class="creator-btn creator-btn-secondary">
                                <?php esc_html_e( 'Validate License', 'creator-core' ); ?>
                            </button>
                            <span id="license-status" class="creator-inline-status"></span>
                        </div>
                    </form>
                </div>
                <?php
                break;

            case 'profile':
                $models_info = \CreatorCore\User\UserProfile::get_models_info();
                $current_model = \CreatorCore\User\UserProfile::get_default_model();
                ?>
                <div class="creator-setup-section">
                    <!-- AI Model Selection Section -->
                    <h2><?php esc_html_e( 'Default AI Model', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Choose your preferred AI model. This will be the default for new chats, but you can change it per-chat. Each model automatically falls back to the other if unavailable.', 'creator-core' ); ?></p>

                    <form id="creator-model-form" class="creator-setup-form">
                        <div class="creator-model-selector">
                            <?php foreach ( $models_info as $model_key => $model_info ) : ?>
                                <label class="creator-model-option <?php echo $current_model === $model_key ? 'selected' : ''; ?>">
                                    <input type="radio" name="default_model" value="<?php echo esc_attr( $model_key ); ?>"
                                           <?php checked( $current_model, $model_key ); ?>>

                                    <div class="creator-model-card">
                                        <div class="creator-model-header">
                                            <span class="creator-model-icon"><?php echo esc_html( $model_info['icon'] ); ?></span>
                                            <div class="creator-model-titles">
                                                <span class="creator-model-label"><?php echo esc_html( $model_info['label'] ); ?></span>
                                                <span class="creator-model-provider"><?php echo esc_html( $model_info['provider'] ); ?></span>
                                            </div>
                                        </div>

                                        <div class="creator-model-title">
                                            <?php echo esc_html( $model_info['title'] ); ?>
                                        </div>

                                        <p class="creator-model-description">
                                            <?php echo esc_html( $model_info['description'] ); ?>
                                        </p>

                                        <div class="creator-model-best-for">
                                            <strong><?php esc_html_e( 'Best for:', 'creator-core' ); ?></strong>
                                            <ul>
                                                <?php foreach ( $model_info['best_for'] as $use_case ) : ?>
                                                    <li><?php echo esc_html( $use_case ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <div class="creator-model-fallback">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php echo esc_html( $model_info['fallback'] ); ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <hr class="creator-section-divider">

                    <!-- Competency Level Section -->
                    <h2><?php esc_html_e( 'Your Competency Level', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Help Creator understand your technical background so it can communicate and suggest solutions appropriately.', 'creator-core' ); ?></p>

                    <form id="creator-profile-form" class="creator-setup-form">
                        <div class="creator-profile-selector">
                            <?php foreach ( $data['step_data']['levels'] as $level_key => $level_info ) : ?>
                                <label class="creator-profile-option <?php echo $data['step_data']['current_level'] === $level_key ? 'selected' : ''; ?>">
                                    <input type="radio" name="user_level" value="<?php echo esc_attr( $level_key ); ?>"
                                           <?php checked( $data['step_data']['current_level'], $level_key ); ?>>

                                    <div class="creator-profile-card">
                                        <div class="creator-profile-header">
                                            <span class="creator-profile-label"><?php echo esc_html( $level_info['label'] ); ?></span>
                                            <span class="creator-profile-title"><?php echo esc_html( $level_info['title'] ); ?></span>
                                        </div>

                                        <p class="creator-profile-description">
                                            <?php echo esc_html( $level_info['description'] ); ?>
                                        </p>

                                        <div class="creator-profile-capabilities">
                                            <?php if ( ! empty( $level_info['capabilities']['can'] ) ) : ?>
                                                <div class="creator-caps-can">
                                                    <?php foreach ( $level_info['capabilities']['can'] as $cap ) : ?>
                                                        <span class="creator-cap-item creator-cap-yes">
                                                            <span class="dashicons dashicons-yes"></span>
                                                            <?php echo esc_html( $cap ); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $level_info['capabilities']['cannot'] ) ) : ?>
                                                <div class="creator-caps-cannot">
                                                    <?php foreach ( $level_info['capabilities']['cannot'] as $cap ) : ?>
                                                        <span class="creator-cap-item creator-cap-no">
                                                            <span class="dashicons dashicons-no-alt"></span>
                                                            <?php echo esc_html( $cap ); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="creator-profile-behavior">
                                            <strong><?php esc_html_e( 'Creator will:', 'creator-core' ); ?></strong>
                                            <p><?php echo esc_html( $level_info['behavior'] ); ?></p>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="creator-form-row creator-profile-note">
                            <p class="description">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e( 'You can change this later in Creator Settings. Your choice affects how Creator communicates with you and what solutions it suggests.', 'creator-core' ); ?>
                            </p>
                        </div>
                    </form>
                </div>
                <?php
                break;

            case 'finish':
                ?>
                <div class="creator-setup-section creator-setup-finish">
                    <div class="creator-finish-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h2><?php esc_html_e( 'You\'re All Set!', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Creator is ready to help you build amazing WordPress sites.', 'creator-core' ); ?></p>

                    <div class="creator-finish-features">
                        <h3><?php esc_html_e( 'Active Features', 'creator-core' ); ?></h3>
                        <ul class="creator-feature-list">
                            <?php foreach ( $data['step_data']['features'] as $feature ) : ?>
                                <li>
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $feature ) ) ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="creator-finish-actions">
                        <a href="<?php echo esc_url( $data['step_data']['chat_url'] ); ?>" class="creator-btn creator-btn-primary creator-btn-lg">
                            <span class="dashicons dashicons-format-chat"></span>
                            <?php esc_html_e( 'Start Your First Chat', 'creator-core' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $data['step_data']['dashboard_url'] ); ?>" class="creator-btn creator-btn-outline">
                            <?php esc_html_e( 'Go to Dashboard', 'creator-core' ); ?>
                        </a>
                    </div>
                </div>
                <?php
                break;
        endswitch;
        ?>
    </div>

    <!-- Navigation -->
    <div class="creator-setup-nav">
        <?php $prev_url = $setup_wizard->get_previous_step_url( $data['current_step'] ); ?>
        <?php if ( $prev_url && $data['current_step'] !== 'welcome' ) : ?>
            <a href="<?php echo esc_url( $prev_url ); ?>" class="creator-btn creator-btn-outline">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e( 'Back', 'creator-core' ); ?>
            </a>
        <?php else : ?>
            <div></div>
        <?php endif; ?>

        <?php if ( $data['current_step'] !== 'finish' ) : ?>
            <div class="creator-nav-right">
                <?php if ( $data['current_step'] === 'welcome' ) : ?>
                    <button type="button" id="continue-from-welcome-btn" class="creator-btn creator-btn-primary">
                        <?php esc_html_e( 'Continue to Setup', 'creator-core' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                <?php elseif ( $data['current_step'] === 'profile' ) : ?>
                    <button type="button" id="next-step-btn" class="creator-btn creator-btn-primary">
                        <?php esc_html_e( 'Complete Setup', 'creator-core' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                <?php else : ?>
                    <a href="<?php echo esc_url( $setup_wizard->get_next_step_url( $data['current_step'] ) ); ?>" id="next-step-btn" class="creator-btn creator-btn-primary">
                        <?php esc_html_e( 'Continue', 'creator-core' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    var creatorSetupData = {
        currentStep: '<?php echo esc_js( $data['current_step'] ); ?>',
        nextUrl: '<?php echo esc_js( $setup_wizard->get_next_step_url( $data['current_step'] ) ); ?>'
    };
    console.log('Creator Setup Data:', creatorSetupData);
</script>
