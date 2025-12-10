<?php
/**
 * Chat Interface Template
 *
 * @package CreatorCore
 * @var array|null $chat Current chat data
 * @var int|null $chat_id Current chat ID
 */

defined( 'ABSPATH' ) || exit;

// Get model info for the toggle
$models_info = \CreatorCore\User\UserProfile::get_models_info();
$default_model = \CreatorCore\User\UserProfile::get_default_model();
$current_model = $chat['ai_model'] ?? $default_model;
$is_new_chat = empty( $chat_id );
?>
<div class="wrap creator-chat-wrap">
    <div class="creator-chat-container" data-chat-id="<?php echo esc_attr( $chat_id ?? '' ); ?>" data-model="<?php echo esc_attr( $current_model ); ?>">
        <!-- Chat Header -->
        <div class="creator-chat-header">
            <div class="creator-chat-header-left">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-dashboard' ) ); ?>" class="creator-back-btn">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
                <h1 class="creator-chat-title">
                    <?php if ( $chat ) : ?>
                        <span class="title-text"><?php echo esc_html( $chat['title'] ); ?></span>
                        <button type="button" class="creator-edit-title" title="<?php esc_attr_e( 'Edit title', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    <?php else : ?>
                        <?php esc_html_e( 'New Chat', 'creator-core' ); ?>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="creator-chat-header-right">
                <?php if ( $is_new_chat ) : ?>
                    <!-- Model Toggle for New Chat -->
                    <div class="creator-model-toggle" title="<?php esc_attr_e( 'Select AI Model', 'creator-core' ); ?>">
                        <?php foreach ( $models_info as $model_key => $model_info ) : ?>
                            <label class="creator-model-toggle-option <?php echo $current_model === $model_key ? 'active' : ''; ?>">
                                <input type="radio" name="chat_model" value="<?php echo esc_attr( $model_key ); ?>"
                                       <?php checked( $current_model, $model_key ); ?>>
                                <span class="creator-model-toggle-icon"><?php echo esc_html( $model_info['icon'] ); ?></span>
                                <span class="creator-model-toggle-label"><?php echo esc_html( $model_info['label'] ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <!-- Show current model badge for existing chats -->
                    <div class="creator-model-badge" title="<?php echo esc_attr( $models_info[ $current_model ]['label'] ?? '' ); ?>">
                        <span class="creator-model-badge-icon"><?php echo esc_html( $models_info[ $current_model ]['icon'] ?? '' ); ?></span>
                        <span class="creator-model-badge-label"><?php echo esc_html( $models_info[ $current_model ]['label'] ?? '' ); ?></span>
                    </div>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-settings' ) ); ?>" class="creator-header-btn" title="<?php esc_attr_e( 'Settings', 'creator-core' ); ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                </a>
                <button type="button" class="creator-header-btn creator-help-btn" title="<?php esc_attr_e( 'Help', 'creator-core' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </button>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="creator-chat-messages" id="creator-messages">
            <?php if ( $chat && ! empty( $chat['messages'] ) ) : ?>
                <?php foreach ( $chat['messages'] as $message ) : ?>
                    <div class="creator-message creator-message-<?php echo esc_attr( $message['role'] ); ?>" data-message-id="<?php echo esc_attr( $message['id'] ); ?>">
                        <div class="creator-message-avatar">
                            <?php if ( $message['role'] === 'user' ) : ?>
                                <?php echo get_avatar( get_current_user_id(), 32 ); ?>
                            <?php else : ?>
                                <span class="creator-ai-avatar">
                                    <span class="dashicons dashicons-superhero-alt"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="creator-message-content">
                            <div class="creator-message-header">
                                <span class="creator-message-sender">
                                    <?php echo $message['role'] === 'user' ? esc_html( wp_get_current_user()->display_name ) : esc_html__( 'Creator', 'creator-core' ); ?>
                                </span>
                                <span class="creator-message-time"><?php echo esc_html( human_time_diff( strtotime( $message['created_at'] ) ) ); ?></span>
                            </div>
                            <div class="creator-message-body">
                                <?php echo wp_kses_post( $message['content'] ); ?>
                            </div>

                            <?php if ( ! empty( $message['actions'] ) ) : ?>
                                <div class="creator-message-actions">
                                    <?php foreach ( $message['actions'] as $action ) : ?>
                                        <?php include CREATOR_CORE_PATH . 'templates/action-card.php'; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="creator-welcome-message">
                    <div class="creator-welcome-icon">
                        <span class="dashicons dashicons-superhero-alt"></span>
                    </div>
                    <h2><?php esc_html_e( 'Welcome to Creator!', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'Your AI-powered WordPress development agent. I can create custom plugins, analyze and debug code, manage files, access the database, and build content. What would you like to create today?', 'creator-core' ); ?></p>

                    <div class="creator-capability-tabs">
                        <button type="button" class="creator-tab active" data-tab="content"><?php esc_html_e( 'Content', 'creator-core' ); ?></button>
                        <button type="button" class="creator-tab" data-tab="development"><?php esc_html_e( 'Development', 'creator-core' ); ?></button>
                        <button type="button" class="creator-tab" data-tab="debug"><?php esc_html_e( 'Debug', 'creator-core' ); ?></button>
                    </div>

                    <div class="creator-suggestions" data-tab-content="content">
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Create a new About page', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-welcome-add-page"></span>
                            <?php esc_html_e( 'Create page', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Write a blog post about', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <?php esc_html_e( 'Write post', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Help me optimize SEO', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Optimize SEO', 'creator-core' ); ?>
                        </button>
                    </div>

                    <div class="creator-suggestions" data-tab-content="development" style="display: none;">
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Create a custom plugin that', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <?php esc_html_e( 'Create plugin', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Analyze the code of my active theme', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-code-standards"></span>
                            <?php esc_html_e( 'Analyze theme', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Show me the database structure', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-database"></span>
                            <?php esc_html_e( 'Database info', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Read the functions.php of my theme', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-media-code"></span>
                            <?php esc_html_e( 'Read files', 'creator-core' ); ?>
                        </button>
                    </div>

                    <div class="creator-suggestions" data-tab-content="debug" style="display: none;">
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Check the WordPress debug log', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e( 'Debug log', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Analyze my plugins for security issues', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-shield"></span>
                            <?php esc_html_e( 'Security check', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Help me fix this error:', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-sos"></span>
                            <?php esc_html_e( 'Fix error', 'creator-core' ); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Chat Input -->
        <div class="creator-chat-input-container">
            <form id="creator-chat-form" class="creator-chat-form">
                <?php wp_nonce_field( 'creator_chat_nonce', 'creator_nonce' ); ?>
                <input type="hidden" name="chat_id" id="creator-chat-id" value="<?php echo esc_attr( $chat_id ?? '' ); ?>">

                <!-- File Attachment Preview -->
                <div id="creator-attachment-preview" class="creator-attachment-preview" style="display: none;">
                    <div class="creator-attachment-list"></div>
                </div>

                <div class="creator-input-wrapper">
                    <!-- Attachment Button -->
                    <button type="button" class="creator-attach-btn" id="creator-attach-btn" title="<?php esc_attr_e( 'Attach files', 'creator-core' ); ?>">
                        <span class="dashicons dashicons-paperclip"></span>
                    </button>
                    <input
                        type="file"
                        id="creator-file-input"
                        multiple
                        accept="image/*,.pdf,.docx,.xlsx,.txt,.php,.json,.html,.css,.js,.sql"
                        style="display: none;"
                    />

                    <textarea
                        id="creator-message-input"
                        name="message"
                        placeholder="<?php esc_attr_e( 'Type your message...', 'creator-core' ); ?>"
                        rows="1"
                        class="creator-message-input"
                    ></textarea>

                    <div class="creator-input-actions">
                        <button type="submit" class="creator-send-btn" id="creator-send-btn">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <span class="sr-only"><?php esc_html_e( 'Send', 'creator-core' ); ?></span>
                        </button>
                    </div>
                </div>

                <div class="creator-input-info">
                    <span class="creator-attachment-info" style="display: none;">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php
                        printf(
                            /* translators: 1: max files, 2: max size in MB */
                            esc_html__( 'Max %1$d files, %2$d MB each', 'creator-core' ),
                            \CreatorCore\Chat\ChatInterface::get_max_files_per_message(),
                            \CreatorCore\Chat\ChatInterface::get_max_file_size() / ( 1024 * 1024 )
                        );
                        ?>
                    </span>
                    <span class="creator-typing-indicator" style="display: none;">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <?php esc_html_e( 'Creator is typing...', 'creator-core' ); ?>
                    </span>
                </div>
            </form>
        </div>
    </div>
</div>
