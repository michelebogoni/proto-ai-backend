<?php
/**
 * Admin Dashboard Template
 *
 * @package CreatorCore
 * @var array $data Dashboard data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap creator-dashboard">
    <h1 class="creator-title">
        <span class="dashicons dashicons-superhero-alt"></span>
        <?php esc_html_e( 'Creator Dashboard', 'creator-core' ); ?>
    </h1>

    <?php settings_errors( 'creator_settings' ); ?>

    <div class="creator-dashboard-header">
        <div class="creator-quick-actions">
            <?php foreach ( $data['quick_actions'] as $action ) : ?>
                <a href="<?php echo esc_url( $action['url'] ); ?>" class="creator-quick-action">
                    <span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>"></span>
                    <span><?php echo esc_html( $action['label'] ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ( $data['license_status']['valid'] ) : ?>
            <div class="creator-license-badge valid">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( ucfirst( $data['license_status']['plan'] ) ); ?>
            </div>
        <?php else : ?>
            <div class="creator-license-badge invalid">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e( 'License Required', 'creator-core' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-settings' ) ); ?>">
                    <?php esc_html_e( 'Activate', 'creator-core' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="creator-dashboard-grid">
        <!-- Recent Chats -->
        <div class="creator-card creator-recent-chats">
            <div class="creator-card-header">
                <h2><?php esc_html_e( 'Recent Chats', 'creator-core' ); ?></h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-chat' ) ); ?>" class="creator-btn creator-btn-primary creator-btn-sm">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'New Chat', 'creator-core' ); ?>
                </a>
            </div>
            <div class="creator-card-body">
                <?php if ( ! empty( $data['recent_chats'] ) ) : ?>
                    <ul class="creator-chat-list">
                        <?php foreach ( $data['recent_chats'] as $chat ) : ?>
                            <li class="creator-chat-item">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-chat&chat_id=' . $chat['id'] ) ); ?>">
                                    <span class="creator-chat-title"><?php echo esc_html( $chat['title'] ); ?></span>
                                    <span class="creator-chat-meta">
                                        <?php echo esc_html( $chat['message_count'] ); ?> <?php esc_html_e( 'messages', 'creator-core' ); ?>
                                        &bull;
                                        <?php echo esc_html( human_time_diff( strtotime( $chat['updated_at'] ) ) ); ?> <?php esc_html_e( 'ago', 'creator-core' ); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="creator-empty-state">
                        <span class="dashicons dashicons-format-chat"></span>
                        <p><?php esc_html_e( 'No chats yet. Start a new conversation!', 'creator-core' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-chat' ) ); ?>" class="creator-btn creator-btn-primary">
                            <?php esc_html_e( 'Start Chat', 'creator-core' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="creator-card creator-stats">
            <div class="creator-card-header">
                <h2><?php esc_html_e( 'Quick Stats', 'creator-core' ); ?></h2>
            </div>
            <div class="creator-card-body">
                <div class="creator-stats-grid">
                    <div class="creator-stat-item">
                        <span class="creator-stat-value"><?php echo esc_html( $data['stats']['tokens_formatted'] ); ?></span>
                        <span class="creator-stat-label"><?php esc_html_e( 'Tokens Used', 'creator-core' ); ?></span>
                    </div>
                    <div class="creator-stat-item">
                        <span class="creator-stat-value"><?php echo esc_html( $data['stats']['actions_completed'] ); ?></span>
                        <span class="creator-stat-label"><?php esc_html_e( 'Actions Completed', 'creator-core' ); ?></span>
                    </div>
                    <div class="creator-stat-item">
                        <span class="creator-stat-value"><?php echo esc_html( $data['stats']['backup_size'] ); ?></span>
                        <span class="creator-stat-label"><?php esc_html_e( 'Backup Size', 'creator-core' ); ?></span>
                    </div>
                    <div class="creator-stat-item">
                        <span class="creator-stat-value"><?php echo esc_html( $data['stats']['last_action_time'] ); ?></span>
                        <span class="creator-stat-label"><?php esc_html_e( 'Last Action', 'creator-core' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Integrations -->
        <div class="creator-card creator-integrations">
            <div class="creator-card-header">
                <h2><?php esc_html_e( 'Active Integrations', 'creator-core' ); ?></h2>
            </div>
            <div class="creator-card-body">
                <ul class="creator-integration-list">
                    <?php foreach ( $data['integrations'] as $key => $integration ) : ?>
                        <li class="creator-integration-item <?php echo $integration['active'] ? 'active' : 'inactive'; ?>">
                            <span class="creator-integration-status">
                                <?php if ( $integration['active'] ) : ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-marker"></span>
                                <?php endif; ?>
                            </span>
                            <span class="creator-integration-name"><?php echo esc_html( $integration['name'] ); ?></span>
                            <?php if ( $integration['active'] && $integration['version'] ) : ?>
                                <span class="creator-integration-version">v<?php echo esc_html( $integration['version'] ); ?></span>
                            <?php elseif ( ! $integration['installed'] ) : ?>
                                <span class="creator-integration-action"><?php esc_html_e( 'Not installed', 'creator-core' ); ?></span>
                            <?php elseif ( ! $integration['active'] ) : ?>
                                <span class="creator-integration-action"><?php esc_html_e( 'Inactive', 'creator-core' ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="creator-card creator-activity">
            <div class="creator-card-header">
                <h2><?php esc_html_e( 'Recent Activity', 'creator-core' ); ?></h2>
            </div>
            <div class="creator-card-body">
                <?php if ( ! empty( $data['recent_activity'] ) ) : ?>
                    <ul class="creator-activity-list">
                        <?php foreach ( array_slice( $data['recent_activity'], 0, 5 ) as $activity ) : ?>
                            <li class="creator-activity-item <?php echo esc_attr( $activity['status'] ); ?>">
                                <span class="creator-activity-icon">
                                    <?php if ( $activity['status'] === 'success' ) : ?>
                                        <span class="dashicons dashicons-yes"></span>
                                    <?php elseif ( $activity['status'] === 'failure' ) : ?>
                                        <span class="dashicons dashicons-no"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-warning"></span>
                                    <?php endif; ?>
                                </span>
                                <span class="creator-activity-action"><?php echo esc_html( $activity['action'] ); ?></span>
                                <span class="creator-activity-time"><?php echo esc_html( human_time_diff( strtotime( $activity['created_at'] ) ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="creator-empty-text"><?php esc_html_e( 'No recent activity', 'creator-core' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
