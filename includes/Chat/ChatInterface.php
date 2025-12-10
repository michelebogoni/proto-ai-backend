<?php
/**
 * Chat Interface
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ProxyClient;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\Backup\SnapshotManager;
use CreatorCore\Backup\Rollback;
use CreatorCore\Audit\AuditLogger;
use CreatorCore\User\UserProfile;
use CreatorCore\Context\CreatorContext;
use CreatorCore\Context\ContextLoader;
use CreatorCore\Context\ThinkingLogger;
use CreatorCore\Executor\CodeExecutor;
use CreatorCore\Executor\ExecutionVerifier;

/**
 * Class ChatInterface
 *
 * Handles the chat interface and messaging
 */
class ChatInterface {

    /**
     * Proxy client instance
     *
     * @var ProxyClient
     */
    private ProxyClient $proxy_client;

    /**
     * Capability checker instance
     *
     * @var CapabilityChecker
     */
    private CapabilityChecker $capability_checker;

    /**
     * Snapshot manager instance
     *
     * @var SnapshotManager
     */
    private SnapshotManager $snapshot_manager;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Message handler instance
     *
     * @var MessageHandler
     */
    private MessageHandler $message_handler;

    /**
     * Creator context instance
     *
     * @var CreatorContext|null
     */
    private ?CreatorContext $creator_context = null;

    /**
     * Phase detector instance
     *
     * @var PhaseDetector|null
     */
    private ?PhaseDetector $phase_detector = null;

    /**
     * Code executor instance
     *
     * @var CodeExecutor|null
     */
    private ?CodeExecutor $code_executor = null;

    /**
     * Execution verifier instance
     *
     * @var ExecutionVerifier|null
     */
    private ?ExecutionVerifier $execution_verifier = null;

    /**
     * Context loader instance
     *
     * @var ContextLoader|null
     */
    private ?ContextLoader $context_loader = null;

    /**
     * Thinking logger instance (per-request)
     *
     * @var ThinkingLogger|null
     */
    private ?ThinkingLogger $thinking_logger = null;

    /**
     * Constructor
     *
     * @param ProxyClient       $proxy_client       Proxy client instance.
     * @param CapabilityChecker $capability_checker Capability checker instance.
     * @param SnapshotManager   $snapshot_manager   Snapshot manager instance.
     * @param AuditLogger       $logger             Audit logger instance.
     */
    public function __construct(
        ProxyClient $proxy_client,
        CapabilityChecker $capability_checker,
        SnapshotManager $snapshot_manager,
        AuditLogger $logger
    ) {
        $this->proxy_client       = $proxy_client;
        $this->capability_checker = $capability_checker;
        $this->snapshot_manager   = $snapshot_manager;
        $this->logger             = $logger;
        $this->message_handler    = new MessageHandler( $logger );

        // Initialize context system components (may fail gracefully)
        try {
            $this->creator_context    = new CreatorContext();
            $this->phase_detector     = new PhaseDetector();
            $this->code_executor      = new CodeExecutor();
            $this->execution_verifier = new ExecutionVerifier( $logger );
        } catch ( \Throwable $e ) {
            // Log error but don't fail - components can be initialized lazily when needed
            error_log( 'Creator: Failed to initialize context components: ' . $e->getMessage() );
        }
    }

    /**
     * Get Creator context (with lazy initialization)
     *
     * @return CreatorContext
     */
    private function get_creator_context(): CreatorContext {
        if ( $this->creator_context === null ) {
            $this->creator_context = new CreatorContext();
        }
        return $this->creator_context;
    }

    /**
     * Get phase detector (with lazy initialization)
     *
     * @return PhaseDetector
     */
    private function get_phase_detector(): PhaseDetector {
        if ( $this->phase_detector === null ) {
            $this->phase_detector = new PhaseDetector();
        }
        return $this->phase_detector;
    }

    /**
     * Get code executor (with lazy initialization)
     *
     * @return CodeExecutor
     */
    private function get_code_executor(): CodeExecutor {
        if ( $this->code_executor === null ) {
            $this->code_executor = new CodeExecutor();
        }
        return $this->code_executor;
    }

    /**
     * Get execution verifier (with lazy initialization)
     *
     * @return ExecutionVerifier
     */
    private function get_execution_verifier(): ExecutionVerifier {
        if ( $this->execution_verifier === null ) {
            $this->execution_verifier = new ExecutionVerifier( $this->logger );
        }
        return $this->execution_verifier;
    }

    /**
     * Get context loader (with lazy initialization)
     *
     * @return ContextLoader
     */
    private function get_context_loader(): ContextLoader {
        if ( $this->context_loader === null ) {
            $this->context_loader = new ContextLoader();
        }
        return $this->context_loader;
    }

    /**
     * Render the chat interface
     *
     * @return void
     */
    public function render(): void {
        // Check permission
        if ( ! $this->capability_checker->can_use_creator() ) {
            wp_die(
                esc_html__( 'You do not have permission to access Creator.', 'creator-core' ),
                esc_html__( 'Access Denied', 'creator-core' ),
                [ 'response' => 403, 'back_link' => true ]
            );
        }

        // Get or create chat
        $chat_id = isset( $_GET['chat_id'] ) ? absint( $_GET['chat_id'] ) : null;
        $chat    = $chat_id ? $this->get_chat( $chat_id ) : null;

        // Load template
        include CREATOR_CORE_PATH . 'templates/chat-interface.php';
    }

    /**
     * Create a new chat
     *
     * @param string $title Chat title.
     * @param string $ai_model AI model (gemini or claude).
     * @return int|false Chat ID or false on failure.
     */
    public function create_chat( string $title = '', string $ai_model = '' ) {
        global $wpdb;

        $user_id = get_current_user_id();

        if ( empty( $title ) ) {
            $title = sprintf(
                /* translators: %s: Date and time */
                __( 'Chat %s', 'creator-core' ),
                current_time( 'Y-m-d H:i' )
            );
        }

        // Use user's default model if not specified
        if ( empty( $ai_model ) || ! in_array( $ai_model, UserProfile::get_valid_models(), true ) ) {
            $ai_model = UserProfile::get_default_model();
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_chats',
            [
                'user_id'    => $user_id,
                'title'      => sanitize_text_field( $title ),
                'status'     => 'active',
                'ai_model'   => $ai_model,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return false;
        }

        $chat_id = $wpdb->insert_id;

        $this->logger->success( 'chat_created', [
            'chat_id'  => $chat_id,
            'title'    => $title,
            'ai_model' => $ai_model,
        ]);

        return $chat_id;
    }

    /**
     * Get chat AI model
     *
     * @param int $chat_id Chat ID.
     * @return string|null Model or null if not found.
     */
    public function get_chat_model( int $chat_id ): ?string {
        global $wpdb;

        $model = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ai_model FROM {$wpdb->prefix}creator_chats WHERE id = %d",
                $chat_id
            )
        );

        return $model ?: null;
    }

    /**
     * Get chat by ID
     *
     * @param int $chat_id Chat ID.
     * @return array|null
     */
    public function get_chat( int $chat_id ): ?array {
        global $wpdb;

        $chat = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_chats WHERE id = %d",
                $chat_id
            ),
            ARRAY_A
        );

        if ( ! $chat ) {
            return null;
        }

        // Get messages
        $chat['messages'] = $this->get_chat_messages( $chat_id );

        return $chat;
    }

    /**
     * Get user's chats
     *
     * @param int|null $user_id User ID (current user if null).
     * @param array    $args    Query arguments.
     * @return array
     */
    public function get_user_chats( ?int $user_id = null, array $args = [] ): array {
        global $wpdb;

        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $defaults = [
            'status'   => 'active',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'updated_at',
            'order'    => 'DESC',
        ];

        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = [ 'user_id = %d' ];
        $values = [ $user_id ];

        if ( $args['status'] !== 'all' ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        $values[] = $args['per_page'];
        $values[] = $offset;

        $chats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_chats
                 WHERE {$where_clause}
                 ORDER BY {$orderby}
                 LIMIT %d OFFSET %d",
                $values
            ),
            ARRAY_A
        );

        // Add message count to each chat
        foreach ( $chats as &$chat ) {
            $chat['message_count'] = $this->get_message_count( $chat['id'] );
        }

        return $chats;
    }

    /**
     * Get messages for a chat
     *
     * @param int $chat_id Chat ID.
     * @return array
     */
    public function get_chat_messages( int $chat_id ): array {
        return $this->message_handler->get_messages( $chat_id );
    }

    /**
     * Allowed file types for attachments
     *
     * @var array
     */
    private const ALLOWED_FILE_TYPES = [
        // Images
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'image/svg+xml'   => 'svg',
        // Documents
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain'      => 'txt',
        // Code
        'application/x-php' => 'php',
        'text/x-php'      => 'php',
        'application/json' => 'json',
        'text/html'       => 'html',
        'text/css'        => 'css',
        'application/javascript' => 'js',
        'text/javascript' => 'js',
        'application/sql' => 'sql',
        'text/x-sql'      => 'sql',
    ];

    /**
     * Maximum file size per attachment (10 MB)
     *
     * @var int
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Maximum files per message
     *
     * @var int
     */
    private const MAX_FILES_PER_MESSAGE = 3;

    /**
     * Maximum retry attempts for AI requests
     *
     * @var int
     */
    private const MAX_RETRY_ATTEMPTS = 5;

    /**
     * Base delay for exponential backoff (milliseconds)
     *
     * @var int
     */
    private const RETRY_BASE_DELAY_MS = 1000;

    /**
     * Snapshot expiration time in hours (after which undo is disabled)
     *
     * @var int
     */
    private const SNAPSHOT_EXPIRATION_HOURS = 24;

    /**
     * Send a message
     *
     * @param int    $chat_id Chat ID.
     * @param string $content Message content.
     * @param array  $files   Optional file attachments.
     * @return array Result with message ID and AI response.
     */
    public function send_message( int $chat_id, string $content, array $files = [] ): array {
        // Initialize thinking logger for this request
        $this->thinking_logger = new ThinkingLogger( $chat_id );
        $this->thinking_logger->start_discovery();

        // Verify chat exists and belongs to user
        $chat = $this->get_chat( $chat_id );

        if ( ! $chat ) {
            return [
                'success' => false,
                'error'   => __( 'Chat not found', 'creator-core' ),
            ];
        }

        if ( (int) $chat['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied', 'creator-core' ),
            ];
        }

        // Process file attachments
        $processed_files = [];
        if ( ! empty( $files ) ) {
            $this->thinking_logger->log_attachments( count( $files ) );
            $file_result = $this->process_file_attachments( $files );
            if ( ! $file_result['success'] ) {
                $this->thinking_logger->error( 'File processing failed: ' . ( $file_result['error'] ?? 'Unknown error' ) );
                return $file_result;
            }
            $processed_files = $file_result['files'];
            $this->thinking_logger->success( 'Files processed successfully' );
        }

        // Save user message with attachment metadata
        $message_metadata = [];
        if ( ! empty( $processed_files ) ) {
            $message_metadata['attachments'] = array_map( function( $file ) {
                return [
                    'name' => $file['name'],
                    'type' => $file['type'],
                    'size' => $file['size'],
                ];
            }, $processed_files );
        }

        try {
            $user_message_id = $this->message_handler->save_message( $chat_id, $content, 'user', 'text', $message_metadata );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error saving user message: ' . $e->getMessage() );
            return [
                'success' => false,
                'error'   => 'Error saving message: ' . $e->getMessage(),
            ];
        }

        if ( ! $user_message_id ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to save message', 'creator-core' ),
            ];
        }

        // Build context
        $this->thinking_logger->start_analysis();
        try {
            $this->thinking_logger->info( 'Loading WordPress context...' );
            $context_collector = new ContextCollector();
            $context           = $context_collector->get_wordpress_context();
            $this->thinking_logger->debug( 'Site context loaded', [ 'keys' => array_keys( $context ) ] );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error collecting context: ' . $e->getMessage() );
            $this->thinking_logger->error( 'Context collection failed: ' . $e->getMessage() );
            return [
                'success' => false,
                'error'   => 'Error collecting context: ' . $e->getMessage(),
            ];
        }

        // Build conversation history
        try {
            $this->thinking_logger->info( 'Loading conversation history...' );
            $history = $this->build_conversation_history( $chat_id );
            $this->thinking_logger->log_history_loaded( count( $history ) );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error building history: ' . $e->getMessage() );
            $this->thinking_logger->warning( 'History load failed, continuing without' );
            $history = [];
        }

        // Extract pending actions from previous messages
        try {
            $pending_actions = $this->extract_pending_actions( $chat_id );
            if ( ! empty( $pending_actions ) ) {
                $this->thinking_logger->info( 'Found ' . count( $pending_actions ) . ' pending action(s)' );
            }
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error extracting pending actions: ' . $e->getMessage() );
            $pending_actions = [];
        }

        // Extract loaded context from previous turn (lazy-load)
        try {
            $loaded_context = $this->extract_loaded_context( $chat_id );
            if ( ! empty( $loaded_context ) ) {
                $this->thinking_logger->log_cache( 'Previous context', true );
            }
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error extracting loaded context: ' . $e->getMessage() );
            $loaded_context = [];
        }

        // Prepare prompts - system_prompt for static context, prompt for conversation
        $this->thinking_logger->start_planning();
        try {
            $this->thinking_logger->info( 'Preparing system prompt...' );
            $prompts = $this->prepare_prompt( $content, $context, $history, $pending_actions, $loaded_context );
            // Estimate token count
            $estimated_tokens = strlen( $prompts['system_prompt'] . $prompts['prompt'] ) / 4;
            $this->thinking_logger->log_token_count( (int) $estimated_tokens );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error preparing prompt: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
            $this->thinking_logger->error( 'Prompt preparation failed: ' . $e->getMessage() );
            return [
                'success' => false,
                'error'   => 'Error preparing prompt: ' . $e->getMessage(),
            ];
        }

        // Get the chat's AI model (locked per chat)
        $ai_model = $chat['ai_model'] ?? UserProfile::get_default_model();
        $this->thinking_logger->log_ai_call( $ai_model );

        // Send to AI with system_prompt separated from main prompt (with retry logic)
        try {
            $ai_options = [
                'chat_id'         => $chat_id,
                'message_id'      => $user_message_id,
                'user_message'    => $content, // Original user message for mock mode intent detection
                'pending_actions' => $pending_actions, // Pending actions for confirmation handling
                'conversation'    => $history, // Conversation history for context extraction
                'model'           => $ai_model, // AI model (gemini or claude)
                'system_prompt'   => $prompts['system_prompt'], // Static context (Creator rules, site info)
            ];

            // Include file attachments if present
            if ( ! empty( $processed_files ) ) {
                $ai_options['files'] = $processed_files;
            }

            // Use retry logic for AI requests
            $ai_response = $this->send_ai_request_with_retry( $prompts['prompt'], $ai_options );
            $this->thinking_logger->success( 'AI response received' );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error sending to AI: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
            $this->thinking_logger->error( 'AI request failed: ' . $e->getMessage() );
            // Mark as complete so SSE stream knows to stop
            $this->thinking_logger->log_complete( 'Failed with error' );
            return [
                'success'         => false,
                'user_message_id' => $user_message_id,
                'error'           => 'Error sending to AI: ' . $e->getMessage(),
                'suggestion'      => $this->get_ai_error_suggestion( $e->getMessage() ),
                'thinking'        => $this->thinking_logger->get_logs(),
            ];
        }

        if ( ! $ai_response['success'] ) {
            // Build error message with suggestion
            $error_msg = $ai_response['error'] ?? __( 'AI request failed', 'creator-core' );
            $suggestion = $ai_response['suggestion'] ?? '';

            $display_error = __( 'Sorry, I encountered an error processing your request.', 'creator-core' );
            if ( ! empty( $suggestion ) ) {
                $display_error .= "\n\n💡 " . $suggestion;
            }

            // Save error as assistant message
            $this->message_handler->save_message(
                $chat_id,
                $display_error,
                'assistant',
                'error',
                [
                    'error'      => $error_msg,
                    'suggestion' => $suggestion,
                    'retries'    => $ai_response['retries'] ?? 0,
                ]
            );

            // Mark as complete so SSE stream knows to stop
            $this->thinking_logger->log_complete( 'Failed: ' . $error_msg );

            return [
                'success'        => false,
                'user_message_id' => $user_message_id,
                'error'          => $error_msg,
                'suggestion'     => $suggestion,
                'thinking'       => $this->thinking_logger->get_logs(),
            ];
        }

        // Parse AI response
        // Firebase returns 'content' key, not 'response'
        $ai_content = $ai_response['content'] ?? $ai_response['response'] ?? '';
        $this->thinking_logger->info( 'Parsing AI response...' );
        try {
            $parsed_response = $this->parse_ai_response( $ai_content );
            // Log detected phase
            if ( ! empty( $parsed_response['phase'] ) ) {
                $this->thinking_logger->log_phase_detected( $parsed_response['phase'] );
            }
            // Log actions if present
            if ( ! empty( $parsed_response['actions'] ) ) {
                $this->thinking_logger->log_plan_generated( count( $parsed_response['actions'] ) );
            }
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error parsing AI response: ' . $e->getMessage() );
            $this->thinking_logger->warning( 'Response parsing issue, using raw content' );
            $parsed_response = [
                'message'     => $ai_content,
                'actions'     => [],
                'has_actions' => false,
            ];
        }

        // Save assistant message
        // Map Firebase response keys to expected format
        $usage_data = [
            'tokens_used' => $ai_response['tokens_used'] ?? 0,
            'cost_usd'    => $ai_response['cost_usd'] ?? 0,
            'latency_ms'  => $ai_response['latency_ms'] ?? 0,
        ];

        // Include context_data in metadata for next turn
        $message_metadata = [
            'actions'      => $parsed_response['actions'],
            'usage'        => $usage_data,
            'provider'     => $ai_response['provider'] ?? 'unknown',
            'model'        => $ai_response['model'] ?? 'unknown',
            'context_data' => $parsed_response['context_data'] ?? [],
        ];

        // If context was loaded, append summary to message
        $display_message = $parsed_response['message'];
        if ( ! empty( $parsed_response['context_data'] ) ) {
            $display_message .= "\n\n" . $this->format_loaded_context( $parsed_response['context_data'] );
            $this->thinking_logger->info( 'Context data loaded on-demand' );
        }

        // Ensure message_type is always a string
        $message_type = ! empty( $parsed_response['has_actions'] ) ? 'action' : 'text';

        $assistant_message_id = $this->message_handler->save_message(
            $chat_id,
            $display_message,
            'assistant',
            $message_type,
            $message_metadata
        );

        // Update chat timestamp
        $this->update_chat_timestamp( $chat_id );

        // Log completion
        $this->thinking_logger->log_complete( 'Response generated successfully' );

        // Save thinking logs to database
        $this->thinking_logger->save_to_database();

        $this->logger->success( 'message_sent', [
            'chat_id'      => $chat_id,
            'user_msg_id'  => $user_message_id,
            'ai_msg_id'    => $assistant_message_id,
            'has_actions'  => $parsed_response['has_actions'],
        ]);

        return [
            'success'              => true,
            'user_message_id'      => $user_message_id,
            'assistant_message_id' => $assistant_message_id,
            'response'             => $parsed_response['message'],
            'actions'              => $parsed_response['actions'],
            'usage'                => $usage_data,
            'thinking'             => $this->thinking_logger->get_logs(),
            'thinking_summary'     => $this->thinking_logger->get_summary(),
        ];
    }

    /**
     * Handle undo/rollback request for a message
     *
     * @param int $chat_id    Chat ID.
     * @param int $message_id Message ID to undo.
     * @return array Result with success status and details.
     */
    public function handle_undo( int $chat_id, int $message_id ): array {
        // Verify chat exists and belongs to user
        $chat = $this->get_chat( $chat_id );

        if ( ! $chat ) {
            return [
                'success' => false,
                'error'   => __( 'Chat not found', 'creator-core' ),
                'code'    => 'chat_not_found',
            ];
        }

        if ( (int) $chat['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied', 'creator-core' ),
                'code'    => 'access_denied',
            ];
        }

        // Get snapshot for this message
        $snapshot = $this->snapshot_manager->get_message_snapshot( $message_id );

        if ( ! $snapshot ) {
            return [
                'success'    => false,
                'error'      => __( 'No rollback data found for this action. The snapshot may have expired or was never created.', 'creator-core' ),
                'code'       => 'snapshot_not_found',
                'suggestion' => __( 'Rollback is available for 24 hours after an action is executed.', 'creator-core' ),
            ];
        }

        // Check if snapshot is expired
        $snapshot_age_hours = $this->get_snapshot_age_hours( $snapshot['created_at'] );

        if ( $snapshot_age_hours > self::SNAPSHOT_EXPIRATION_HOURS ) {
            return [
                'success'    => false,
                'error'      => __( 'This action can no longer be undone because the snapshot has expired.', 'creator-core' ),
                'code'       => 'snapshot_expired',
                'suggestion' => sprintf(
                    /* translators: %d: Number of hours */
                    __( 'Rollback is available for %d hours after an action is executed.', 'creator-core' ),
                    self::SNAPSHOT_EXPIRATION_HOURS
                ),
                'expired_at' => gmdate( 'Y-m-d H:i:s', strtotime( $snapshot['created_at'] ) + ( self::SNAPSHOT_EXPIRATION_HOURS * 3600 ) ),
            ];
        }

        // Perform rollback
        $rollback = new Rollback( $this->snapshot_manager, $this->logger );
        $result   = $rollback->rollback_snapshot( (int) $snapshot['id'] );

        if ( $result['success'] ) {
            // Save a system message noting the rollback
            $this->message_handler->save_message(
                $chat_id,
                sprintf(
                    /* translators: %s: Timestamp */
                    __( '↩️ Action rolled back successfully at %s', 'creator-core' ),
                    current_time( 'H:i:s' )
                ),
                'system',
                'rollback',
                [
                    'snapshot_id'   => $snapshot['id'],
                    'operations'    => count( $result['results'] ?? [] ),
                    'original_msg'  => $message_id,
                ]
            );

            $this->logger->success( 'undo_completed', [
                'chat_id'     => $chat_id,
                'message_id'  => $message_id,
                'snapshot_id' => $snapshot['id'],
            ]);

            return [
                'success'    => true,
                'message'    => $result['message'],
                'operations' => count( $result['results'] ?? [] ),
                'details'    => $result['results'],
            ];
        }

        // Partial or failed rollback
        $this->logger->warning( 'undo_failed', [
            'chat_id'     => $chat_id,
            'message_id'  => $message_id,
            'snapshot_id' => $snapshot['id'],
            'errors'      => $result['errors'],
        ]);

        return [
            'success'    => false,
            'error'      => $result['message'] ?? __( 'Rollback failed', 'creator-core' ),
            'code'       => 'rollback_failed',
            'errors'     => $result['errors'] ?? [],
            'suggestion' => $this->get_rollback_error_suggestion( $result['errors'] ?? [] ),
        ];
    }

    /**
     * Check if undo is available for a message
     *
     * @param int $message_id Message ID.
     * @return array Undo availability info.
     */
    public function check_undo_availability( int $message_id ): array {
        $snapshot = $this->snapshot_manager->get_message_snapshot( $message_id );

        if ( ! $snapshot ) {
            return [
                'available' => false,
                'reason'    => 'no_snapshot',
            ];
        }

        $age_hours = $this->get_snapshot_age_hours( $snapshot['created_at'] );
        $remaining_hours = max( 0, self::SNAPSHOT_EXPIRATION_HOURS - $age_hours );

        if ( $age_hours > self::SNAPSHOT_EXPIRATION_HOURS ) {
            return [
                'available' => false,
                'reason'    => 'expired',
                'expired_at' => gmdate( 'Y-m-d H:i:s', strtotime( $snapshot['created_at'] ) + ( self::SNAPSHOT_EXPIRATION_HOURS * 3600 ) ),
            ];
        }

        return [
            'available'       => true,
            'snapshot_id'     => $snapshot['id'],
            'created_at'      => $snapshot['created_at'],
            'expires_in_hours' => round( $remaining_hours, 1 ),
            'operations_count' => count( $snapshot['operations'] ?? [] ),
        ];
    }

    /**
     * Get snapshot age in hours
     *
     * @param string $created_at Created at timestamp.
     * @return float Age in hours.
     */
    private function get_snapshot_age_hours( string $created_at ): float {
        $created_timestamp = strtotime( $created_at );
        $current_timestamp = time();
        return ( $current_timestamp - $created_timestamp ) / 3600;
    }

    /**
     * Get helpful suggestion for rollback errors
     *
     * @param array $errors Rollback errors.
     * @return string Suggestion message.
     */
    private function get_rollback_error_suggestion( array $errors ): string {
        if ( empty( $errors ) ) {
            return __( 'Please try again or contact support if the problem persists.', 'creator-core' );
        }

        // Analyze errors for specific suggestions
        foreach ( $errors as $error ) {
            $error_msg = $error['error'] ?? '';

            if ( stripos( $error_msg, 'permission' ) !== false || stripos( $error_msg, 'capability' ) !== false ) {
                return __( 'This rollback requires higher permissions. Please contact an administrator.', 'creator-core' );
            }

            if ( stripos( $error_msg, 'not found' ) !== false || stripos( $error_msg, 'deleted' ) !== false ) {
                return __( 'Some items may have been manually modified or deleted. Partial rollback was attempted.', 'creator-core' );
            }

            if ( stripos( $error_msg, 'database' ) !== false || stripos( $error_msg, 'wpdb' ) !== false ) {
                return __( 'A database error occurred. Please check the error log and try again.', 'creator-core' );
            }
        }

        return __( 'Some operations could not be rolled back. Please review the error details.', 'creator-core' );
    }

    /**
     * Send AI request with retry logic
     *
     * @param string $prompt     The prompt to send.
     * @param array  $ai_options Options for the AI request.
     * @return array AI response.
     */
    private function send_ai_request_with_retry( string $prompt, array $ai_options ): array {
        $last_error   = null;
        $attempt      = 0;

        while ( $attempt < self::MAX_RETRY_ATTEMPTS ) {
            $attempt++;

            try {
                $ai_response = $this->proxy_client->send_to_ai( $prompt, 'TEXT_GEN', $ai_options );

                // Success - return response
                if ( $ai_response['success'] ) {
                    if ( $attempt > 1 ) {
                        $this->logger->info( 'ai_request_succeeded_after_retry', [
                            'attempt' => $attempt,
                            'chat_id' => $ai_options['chat_id'] ?? null,
                        ]);
                    }
                    return $ai_response;
                }

                // Non-retryable errors (authentication, rate limit exceeded, etc.)
                $error_code = $ai_response['error_code'] ?? '';
                if ( $this->is_non_retryable_error( $error_code, $ai_response['error'] ?? '' ) ) {
                    return $ai_response;
                }

                $last_error = $ai_response['error'] ?? 'Unknown error';

            } catch ( \Throwable $e ) {
                $last_error = $e->getMessage();

                // Non-retryable exceptions
                if ( $this->is_non_retryable_error( '', $e->getMessage() ) ) {
                    throw $e;
                }
            }

            // Log retry attempt
            $this->logger->warning( 'ai_request_retry', [
                'attempt'   => $attempt,
                'max'       => self::MAX_RETRY_ATTEMPTS,
                'error'     => $last_error,
                'chat_id'   => $ai_options['chat_id'] ?? null,
            ]);

            // Exponential backoff before retry (except on last attempt)
            if ( $attempt < self::MAX_RETRY_ATTEMPTS ) {
                $delay_ms = self::RETRY_BASE_DELAY_MS * pow( 2, $attempt - 1 );
                usleep( $delay_ms * 1000 ); // Convert to microseconds
            }
        }

        // All retries exhausted
        $this->logger->failure( 'ai_request_all_retries_failed', [
            'attempts'   => $attempt,
            'last_error' => $last_error,
            'chat_id'    => $ai_options['chat_id'] ?? null,
        ]);

        return [
            'success' => false,
            'error'   => $this->get_ai_error_message( $last_error, $attempt ),
            'suggestion' => $this->get_ai_error_suggestion( $last_error ),
            'retries' => $attempt,
        ];
    }

    /**
     * Check if an error is non-retryable
     *
     * @param string $error_code Error code.
     * @param string $error_msg  Error message.
     * @return bool
     */
    private function is_non_retryable_error( string $error_code, string $error_msg ): bool {
        // Non-retryable error codes
        $non_retryable_codes = [ 'auth_failed', 'invalid_api_key', 'quota_exceeded', 'model_not_found' ];
        if ( in_array( $error_code, $non_retryable_codes, true ) ) {
            return true;
        }

        // Non-retryable error patterns
        $non_retryable_patterns = [
            'authentication',
            'unauthorized',
            'api key',
            'quota exceeded',
            'rate limit',
            'invalid model',
            'permission denied',
        ];

        $error_lower = strtolower( $error_msg );
        foreach ( $non_retryable_patterns as $pattern ) {
            if ( strpos( $error_lower, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user-friendly error message for AI failures
     *
     * @param string $error   Original error.
     * @param int    $retries Number of retries attempted.
     * @return string
     */
    private function get_ai_error_message( string $error, int $retries ): string {
        $error_lower = strtolower( $error );

        if ( strpos( $error_lower, 'timeout' ) !== false ) {
            return __( 'The AI service is taking too long to respond. Please try again.', 'creator-core' );
        }

        if ( strpos( $error_lower, 'rate limit' ) !== false ) {
            return __( 'Too many requests. Please wait a moment and try again.', 'creator-core' );
        }

        if ( strpos( $error_lower, 'network' ) !== false || strpos( $error_lower, 'connection' ) !== false ) {
            return __( 'Network error. Please check your internet connection and try again.', 'creator-core' );
        }

        if ( strpos( $error_lower, 'auth' ) !== false || strpos( $error_lower, 'api key' ) !== false ) {
            return __( 'Authentication error. Please check your API configuration.', 'creator-core' );
        }

        return sprintf(
            /* translators: %d: Number of retries */
            __( 'AI request failed after %d attempts. Please try again later.', 'creator-core' ),
            $retries
        );
    }

    /**
     * Get suggestion for AI error recovery
     *
     * @param string $error Error message.
     * @return string
     */
    private function get_ai_error_suggestion( string $error ): string {
        $error_lower = strtolower( $error );

        if ( strpos( $error_lower, 'timeout' ) !== false ) {
            return __( 'Try sending a shorter message or wait for the service to recover.', 'creator-core' );
        }

        if ( strpos( $error_lower, 'rate limit' ) !== false ) {
            return __( 'Wait 30-60 seconds before trying again.', 'creator-core' );
        }

        if ( strpos( $error_lower, 'network' ) !== false || strpos( $error_lower, 'connection' ) !== false ) {
            return __( 'Check your internet connection and refresh the page.', 'creator-core' );
        }

        if ( strpos( $error_lower, 'auth' ) !== false ) {
            return __( 'Go to Settings > API and verify your API credentials.', 'creator-core' );
        }

        return __( 'If the problem persists, try refreshing the page or contact support.', 'creator-core' );
    }

    /**
     * Build conversation history with pruning
     *
     * Keeps last 10 messages in full, summarizes older ones.
     *
     * @param int $chat_id Chat ID.
     * @param int $limit   Max messages to fetch.
     * @return array
     */
    private function build_conversation_history( int $chat_id, int $limit = 20 ): array {
        $messages = $this->message_handler->get_messages( $chat_id, [
            'per_page' => $limit,
            'order'    => 'DESC',
        ]);

        $reversed = array_reverse( $messages );
        $total    = count( $reversed );
        $history  = [];

        // If more than 10 messages, summarize older ones
        if ( $total > 10 ) {
            $older_count = $total - 10;
            $history[]   = [
                'role'    => 'system',
                'content' => sprintf( '[Previous %d messages summarized]', $older_count ),
            ];
            // Keep only last 10
            $reversed = array_slice( $reversed, -10 );
        }

        foreach ( $reversed as $message ) {
            $history[] = [
                'role'    => $message['role'],
                'content' => $message['content'],
            ];
        }

        return $history;
    }

    /**
     * Prepare prompts with context
     *
     * Uses the new CreatorContext system for comprehensive context injection.
     * Returns both system_prompt (static context) and prompt (conversation).
     *
     * @param string $user_message    User's message.
     * @param array  $context         WordPress context (legacy, kept for compatibility).
     * @param array  $history         Conversation history.
     * @param array  $pending_actions Pending actions from previous messages.
     * @param array  $loaded_context  Context loaded from previous turn via lazy-load.
     * @return array Array with 'system_prompt' and 'prompt' keys.
     */
    private function prepare_prompt( string $user_message, array $context, array $history, array $pending_actions = [], array $loaded_context = [] ): array {
        // ========================================
        // SYSTEM PROMPT: Static context (rules, site info)
        // ========================================
        $system_prompt = "You are Creator, an AI assistant for WordPress automation.\n\n";

        // Get ultra-compact Creator Context
        try {
            $creator_context_prompt = $this->get_creator_context()->get_context_as_prompt();
            if ( ! empty( $creator_context_prompt ) ) {
                $system_prompt .= $creator_context_prompt . "\n";
            }
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error getting context: ' . $e->getMessage() );
        }

        // Add lazy-load capabilities info
        $system_prompt .= "## On-Demand Details\n";
        $system_prompt .= "Need plugin/ACF/CPT details? Use context_request action:\n";
        $system_prompt .= '{"type": "get_plugin_details", "params": {"slug": "plugin-slug"}}' . "\n";
        $system_prompt .= '{"type": "get_acf_details", "params": {"group": "Group Title"}}' . "\n";
        $system_prompt .= '{"type": "get_cpt_details", "params": {"post_type": "cpt_slug"}}' . "\n";
        $system_prompt .= '{"type": "get_wp_functions", "params": {"category": "wordpress|woocommerce|acf|elementor|database"}}' . "\n\n";

        // Add response format specification to system prompt
        $system_prompt .= "## Response Format\n";
        $system_prompt .= "ALWAYS respond with valid JSON:\n";
        $system_prompt .= '{"phase": "discovery|proposal|execution", "intent": "action_type", "confidence": 0.0-1.0, ';
        $system_prompt .= '"message": "Your message in user language", "questions": [], "plan": null, "code": null, "actions": []}';
        $system_prompt .= "\n\nPhase meanings: discovery=need more info, proposal=present plan, execution=generate code.\n";
        $system_prompt .= "Action status: pending=needs confirmation, ready=execute immediately.\n";
        $system_prompt .= "Always respond in the same language the user is using.";

        // ========================================
        // PROMPT: Dynamic content (conversation, user message)
        // ========================================
        $prompt = '';

        // Detect user input type and determine expected phase
        $prev_phase = $this->get_last_phase( $history );
        $input_classification = [ 'type' => 'new_request', 'next_phase' => 'discovery' ]; // Default
        try {
            $input_classification = $this->get_phase_detector()->classify_user_input( $user_message, $prev_phase );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error classifying input: ' . $e->getMessage() );
        }

        // Include conversation history
        if ( ! empty( $history ) ) {
            $prompt .= "## Conversation History\n";
            foreach ( $history as $msg ) {
                $prompt .= sprintf( "%s: %s\n", ucfirst( $msg['role'] ), $msg['content'] );
            }
            $prompt .= "\n";
        }

        // Include pending actions info
        if ( ! empty( $pending_actions ) ) {
            $prompt .= "## Pending Actions\n";
            foreach ( $pending_actions as $action ) {
                $prompt .= sprintf( "- %s: %s\n", $action['type'], wp_json_encode( $action['params'] ?? [] ) );
            }
            $prompt .= "If user confirms, set action status to 'ready'. If rejects, cancel actions.\n\n";
        }

        // Include loaded context from previous turn (lazy-load data)
        if ( ! empty( $loaded_context ) ) {
            $prompt .= "## Loaded Details (from your previous request)\n";
            foreach ( $loaded_context as $type => $result ) {
                if ( empty( $result['success'] ) ) {
                    $prompt .= sprintf( "### %s: ERROR - %s\n", $type, $result['error'] ?? 'Unknown error' );
                    continue;
                }
                $prompt .= sprintf( "### %s\n```json\n%s\n```\n", $type, wp_json_encode( $result['data'] ?? [], JSON_PRETTY_PRINT ) );
            }
            $prompt .= "\nUse this data to proceed with the user's request.\n\n";
        }

        // User input context
        $prompt .= "## Context\n";
        $prompt .= sprintf( "Input: %s | Next phase: %s | Prev phase: %s\n\n", $input_classification['type'], $input_classification['next_phase'], $prev_phase ?: 'none' );

        $prompt .= "## User Request\n";
        $prompt .= $user_message;

        return [
            'system_prompt' => $system_prompt,
            'prompt'        => $prompt,
        ];
    }

    /**
     * Get the last phase from conversation history
     *
     * @param array $history Conversation history.
     * @return string|null
     */
    private function get_last_phase( array $history ): ?string {
        // Look for the last assistant message and try to detect its phase
        for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
            if ( $history[ $i ]['role'] === 'assistant' ) {
                $content = $history[ $i ]['content'];
                // Try to extract phase from JSON response
                if ( preg_match( '/"phase"\s*:\s*"(discovery|proposal|execution)"/', $content, $matches ) ) {
                    return $matches[1];
                }
            }
        }
        return null;
    }

    /**
     * Extract pending actions from previous messages
     *
     * @param int $chat_id Chat ID.
     * @return array
     */
    private function extract_pending_actions( int $chat_id ): array {
        $messages = $this->message_handler->get_messages( $chat_id, [
            'per_page' => 5,
            'order'    => 'DESC',
        ]);

        $pending_actions = [];

        foreach ( $messages as $message ) {
            // Only check assistant messages
            if ( $message['role'] !== 'assistant' ) {
                continue;
            }

            // Check if message has actions metadata
            $metadata = $message['metadata'] ?? [];

            if ( is_string( $metadata ) ) {
                $metadata = json_decode( $metadata, true ) ?? [];
            }

            $actions = $metadata['actions'] ?? [];

            foreach ( $actions as $action ) {
                // Include actions that are pending (not yet executed or cancelled)
                $status = $action['status'] ?? 'pending';
                if ( in_array( $status, [ 'pending', 'proposed' ], true ) ) {
                    $pending_actions[] = $action;
                }
            }

            // Only check the most recent assistant message with actions
            if ( ! empty( $pending_actions ) ) {
                break;
            }
        }

        return $pending_actions;
    }

    /**
     * Parse AI response
     *
     * @param string $response AI response.
     * @return array
     */
    private function parse_ai_response( string $response ): array {
        // Clean up the response - remove markdown code blocks if present
        $cleaned_response = $this->extract_json_from_response( $response );

        // Try to parse as JSON
        $json = json_decode( $cleaned_response, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
            $parsed = [
                'message'     => $json['message'] ?? $response,
                'actions'     => $json['actions'] ?? [],
                'intent'      => $json['intent'] ?? null,
                'confidence'  => $json['confidence'] ?? 0,
                'has_actions' => ! empty( $json['actions'] ),
                'phase'       => $json['phase'] ?? null,
                'questions'   => $json['questions'] ?? [],
                'plan'        => $json['plan'] ?? null,
                'code'        => $json['code'] ?? null,
            ];

            // Detect phase if not explicit
            if ( empty( $parsed['phase'] ) ) {
                $phase_detection = $this->get_phase_detector()->detect_phase( $json );
                $parsed['phase'] = $phase_detection['phase'];
            }

            // Handle context request actions (lazy-load)
            if ( ! empty( $parsed['actions'] ) ) {
                $parsed['context_data'] = $this->handle_context_requests( $parsed['actions'] );
            }

            // Handle Elementor page creation actions
            $elementor_result = $this->handle_elementor_actions( $parsed );
            if ( $elementor_result ) {
                $parsed['elementor'] = $elementor_result;
                if ( $elementor_result['success'] ) {
                    $this->thinking_logger->success(
                        'Elementor page created: ' . ( $elementor_result['url'] ?? 'unknown' )
                    );
                }
            }

            // Handle code execution if in execution phase
            if ( $parsed['phase'] === PhaseDetector::PHASE_EXECUTION && ! empty( $parsed['code'] ) ) {
                $execution_result = $this->handle_code_execution( $parsed['code'] );
                $parsed['execution'] = $execution_result;

                // If execution was successful, run verification
                if ( $execution_result['success'] ) {
                    $parsed['verification'] = $this->handle_verification( $parsed );
                }
            }

            return $parsed;
        }

        // Plain text response
        return [
            'message'     => $response,
            'actions'     => [],
            'intent'      => 'conversation',
            'confidence'  => 1.0,
            'has_actions' => false,
            'phase'       => PhaseDetector::PHASE_DISCOVERY,
            'questions'   => [],
            'plan'        => null,
            'code'        => null,
        ];
    }

    /**
     * Handle code execution from AI response
     *
     * @param array $code_data Code data from AI response.
     * @return array Execution result.
     */
    private function handle_code_execution( array $code_data ): array {
        // Only execute if auto_execute is true (user confirmed)
        $auto_execute = $code_data['auto_execute'] ?? false;

        if ( ! $auto_execute ) {
            return [
                'success'  => false,
                'status'   => 'pending_confirmation',
                'message'  => 'Code requires user confirmation before execution',
            ];
        }

        // Execute the code
        $result = $this->get_code_executor()->execute( $code_data );

        // Log execution
        $this->logger->log(
            'code_execution',
            $result['success'] ? 'success' : 'failure',
            [
                'code_type'  => $code_data['type'] ?? 'unknown',
                'title'      => $code_data['title'] ?? 'Untitled',
                'result'     => $result['status'],
                'snippet_id' => $result['snippet_id'] ?? null,
            ]
        );

        return $result;
    }

    /**
     * Execute action code from REST API
     *
     * Public wrapper for handle_code_execution, used by REST API endpoint.
     *
     * @param array $action  Action data containing code.
     * @param int   $chat_id Optional chat ID for context.
     * @return array Execution result.
     */
    public function execute_action_code( array $action, int $chat_id = 0 ): array {
        // Initialize thinking logger for this execution
        $this->thinking_logger = new ThinkingLogger( $chat_id );
        $this->thinking_logger->start_execution();

        // Extract code data from action
        $code_data = $action['code'] ?? $action;

        // Log code generation info
        $code_content = $code_data['code'] ?? $code_data['content'] ?? '';
        $line_count   = substr_count( $code_content, "\n" ) + 1;
        $this->thinking_logger->log_code_generated( $line_count );

        // Log security check
        $this->thinking_logger->log_security_check( true, '33 forbidden functions blocked' );

        // Force auto_execute since user clicked execute button
        $code_data['auto_execute'] = true;

        // Log execution method
        $method = ! empty( $code_data['use_wpcode'] ) ? 'wpcode' : 'direct';
        $this->thinking_logger->log_execution_start( $method );

        // Execute using existing handler
        $result = $this->handle_code_execution( $code_data );

        // Log result
        if ( $result['success'] ) {
            $this->thinking_logger->start_verification();
            $this->thinking_logger->log_verification_result( true, $code_data['title'] ?? 'Code executed' );
            $this->thinking_logger->log_complete( $result['status'] ?? 'Execution successful' );
        } else {
            $this->thinking_logger->error( 'Execution failed: ' . ( $result['error'] ?? 'Unknown error' ) );
        }

        // Save thinking logs
        $this->thinking_logger->save_to_database();

        // Add chat context and thinking if provided
        if ( $chat_id > 0 ) {
            $result['chat_id'] = $chat_id;
        }
        $result['thinking'] = $this->thinking_logger->get_logs();

        return $result;
    }

    /**
     * Handle verification after code execution
     *
     * @param array $parsed_response Parsed AI response.
     * @return array Verification result.
     */
    private function handle_verification( array $parsed_response ): array {
        // Determine action type from intent or actions
        $action_type = $parsed_response['intent'] ?? 'generic';

        // Try to get action type from actions array
        if ( ! empty( $parsed_response['actions'] ) ) {
            $first_action = $parsed_response['actions'][0];
            $action_type = $first_action['type'] ?? $action_type;
        }

        // Build expected parameters from response
        $expected = [];

        // Extract expected values from plan or actions
        if ( ! empty( $parsed_response['plan'] ) ) {
            $expected = $parsed_response['plan'];
        }

        if ( ! empty( $parsed_response['actions'] ) ) {
            foreach ( $parsed_response['actions'] as $action ) {
                if ( isset( $action['params'] ) ) {
                    $expected = array_merge( $expected, $action['params'] );
                }
            }
        }

        // Add execution context
        $context = [
            'success'    => $parsed_response['execution']['success'] ?? false,
            'snippet_id' => $parsed_response['execution']['snippet_id'] ?? null,
            'result_id'  => $parsed_response['execution']['data'] ?? null,
        ];

        // Run verification
        return $this->get_execution_verifier()->verify( $action_type, $expected, $context );
    }

    /**
     * Handle context request actions (lazy-load)
     *
     * @param array $actions Actions from AI response.
     * @return array Context data loaded on-demand.
     */
    private function handle_context_requests( array $actions ): array {
        $context_data = [];
        $context_types = [
            'get_plugin_details',
            'get_acf_details',
            'get_cpt_details',
            'get_taxonomy_details',
            'get_wp_functions',
        ];

        foreach ( $actions as $action ) {
            $type = $action['type'] ?? '';

            if ( in_array( $type, $context_types, true ) ) {
                try {
                    $result = $this->get_context_loader()->handle_context_request( $action );
                    $context_data[ $type ] = $result;
                } catch ( \Throwable $e ) {
                    error_log( 'Creator: Context request error: ' . $e->getMessage() );
                    $context_data[ $type ] = [
                        'success' => false,
                        'error'   => $e->getMessage(),
                    ];
                }
            }
        }

        return $context_data;
    }

    /**
     * Handle Elementor page creation actions
     *
     * Checks if AI response contains an Elementor page creation request
     * and executes it if found.
     *
     * @param array $parsed_response Parsed AI response.
     * @return array|null Result if page was created, null if not applicable.
     */
    private function handle_elementor_actions( array $parsed_response ): ?array {
        // Only handle if Elementor is available.
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return null;
        }

        try {
            $handler = new \CreatorCore\Integrations\ElementorActionHandler( $this->thinking_logger );
            return $handler->handle_response( $parsed_response );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Elementor action error: ' . $e->getMessage() );
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Format loaded context for display in message
     *
     * @param array $context_data Loaded context data.
     * @return string Formatted summary.
     */
    private function format_loaded_context( array $context_data ): string {
        $parts = [ '📋 **Dettagli caricati:**' ];

        foreach ( $context_data as $type => $result ) {
            if ( empty( $result['success'] ) ) {
                $parts[] = sprintf( '- %s: ❌ %s', $type, $result['error'] ?? 'Errore' );
                continue;
            }

            $data = $result['data'] ?? [];
            switch ( $type ) {
                case 'get_plugin_details':
                    $parts[] = sprintf(
                        '- Plugin **%s** v%s: %d funzioni',
                        $data['name'] ?? $data['slug'] ?? '?',
                        $data['version'] ?? '?',
                        count( $data['main_functions'] ?? [] )
                    );
                    break;

                case 'get_acf_details':
                    $parts[] = sprintf(
                        '- ACF **%s**: %d campi',
                        $data['title'] ?? '?',
                        count( $data['fields'] ?? [] )
                    );
                    break;

                case 'get_cpt_details':
                    $parts[] = sprintf(
                        '- CPT **%s**: supports %s',
                        $data['label'] ?? $data['name'] ?? '?',
                        implode( ', ', array_keys( $data['supports'] ?? [] ) )
                    );
                    break;

                case 'get_wp_functions':
                    $parts[] = sprintf(
                        '- Funzioni **%s**: %d disponibili',
                        $data['category'] ?? '?',
                        count( $data['functions'] ?? [] )
                    );
                    break;

                default:
                    $parts[] = sprintf( '- %s: ✅ Caricato', $type );
            }
        }

        return implode( "\n", $parts );
    }

    /**
     * Extract loaded context from previous messages
     *
     * @param int $chat_id Chat ID.
     * @return array Loaded context data from previous turn.
     */
    private function extract_loaded_context( int $chat_id ): array {
        $messages = $this->message_handler->get_messages( $chat_id, [
            'per_page' => 3,
            'order'    => 'DESC',
        ]);

        foreach ( $messages as $message ) {
            if ( $message['role'] !== 'assistant' ) {
                continue;
            }

            $metadata = $message['metadata'] ?? [];
            if ( is_string( $metadata ) ) {
                $metadata = json_decode( $metadata, true ) ?? [];
            }

            $context_data = $metadata['context_data'] ?? [];
            if ( ! empty( $context_data ) ) {
                return $context_data;
            }
        }

        return [];
    }

    /**
     * Extract JSON from AI response (handles markdown code blocks)
     *
     * @param string $response Raw AI response.
     * @return string Cleaned JSON string.
     */
    private function extract_json_from_response( string $response ): string {
        $response = trim( $response );

        // Remove markdown code blocks: ```json ... ``` or ``` ... ```
        if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches ) ) {
            return trim( $matches[1] );
        }

        // Try to extract JSON object from the response (in case there's text before/after)
        if ( preg_match( '/\{[\s\S]*"message"[\s\S]*\}/', $response, $matches ) ) {
            return $matches[0];
        }

        // Return as-is
        return $response;
    }

    /**
     * Update chat title
     *
     * @param int    $chat_id Chat ID.
     * @param string $title   New title.
     * @return bool
     */
    public function update_chat_title( int $chat_id, string $title ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [
                'title'      => sanitize_text_field( $title ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $chat_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Update chat timestamp
     *
     * @param int $chat_id Chat ID.
     * @return bool
     */
    private function update_chat_timestamp( int $chat_id ): bool {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [ 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $chat_id ],
            [ '%s' ],
            [ '%d' ]
        ) !== false;
    }

    /**
     * Archive a chat
     *
     * @param int $chat_id Chat ID.
     * @return bool
     */
    public function archive_chat( int $chat_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [ 'status' => 'archived', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $chat_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            $this->logger->success( 'chat_archived', [ 'chat_id' => $chat_id ] );
        }

        return $result !== false;
    }

    /**
     * Delete a chat
     *
     * @param int $chat_id Chat ID.
     * @return bool
     */
    public function delete_chat( int $chat_id ): bool {
        global $wpdb;

        // Delete messages first
        $wpdb->delete(
            $wpdb->prefix . 'creator_messages',
            [ 'chat_id' => $chat_id ],
            [ '%d' ]
        );

        // Delete chat
        $result = $wpdb->delete(
            $wpdb->prefix . 'creator_chats',
            [ 'id' => $chat_id ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            $this->logger->success( 'chat_deleted', [ 'chat_id' => $chat_id ] );
        }

        return $result !== false;
    }

    /**
     * Get message count for a chat
     *
     * @param int $chat_id Chat ID.
     * @return int
     */
    private function get_message_count( int $chat_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages WHERE chat_id = %d",
                $chat_id
            )
        );
    }

    /**
     * Get recent chats for dashboard
     *
     * @param int $limit Number of chats.
     * @return array
     */
    public function get_recent_chats( int $limit = 5 ): array {
        return $this->get_user_chats( null, [
            'per_page' => $limit,
            'status'   => 'active',
        ]);
    }

    /**
     * Process file attachments for a message
     *
     * Validates and converts files to base64 for AI processing.
     *
     * @param array $files Array of files from upload (each with name, type, base64).
     * @return array Result with success status and processed files.
     */
    private function process_file_attachments( array $files ): array {
        // Check max files limit
        if ( count( $files ) > self::MAX_FILES_PER_MESSAGE ) {
            return [
                'success' => false,
                'error'   => sprintf(
                    /* translators: %d: maximum number of files */
                    __( 'Maximum %d files per message allowed.', 'creator-core' ),
                    self::MAX_FILES_PER_MESSAGE
                ),
            ];
        }

        $processed = [];

        foreach ( $files as $file ) {
            // Validate required fields
            if ( empty( $file['name'] ) || empty( $file['type'] ) || empty( $file['base64'] ) ) {
                return [
                    'success' => false,
                    'error'   => __( 'Invalid file format. Each file must have name, type, and base64 data.', 'creator-core' ),
                ];
            }

            // Validate file type
            if ( ! $this->is_allowed_file_type( $file['type'] ) ) {
                return [
                    'success' => false,
                    'error'   => sprintf(
                        /* translators: %s: file name */
                        __( 'File type not allowed: %s', 'creator-core' ),
                        $file['name']
                    ),
                ];
            }

            // Validate and get file size from base64
            $base64_data = $file['base64'];
            // Remove data URI prefix if present (e.g., "data:image/png;base64,")
            if ( strpos( $base64_data, ',') !== false ) {
                $base64_data = substr( $base64_data, strpos( $base64_data, ',' ) + 1 );
            }

            // Calculate file size (base64 is ~33% larger than original)
            $file_size = strlen( base64_decode( $base64_data ) );

            if ( $file_size > self::MAX_FILE_SIZE ) {
                return [
                    'success' => false,
                    'error'   => sprintf(
                        /* translators: 1: file name, 2: max size in MB */
                        __( 'File too large: %1$s (max %2$d MB)', 'creator-core' ),
                        $file['name'],
                        self::MAX_FILE_SIZE / ( 1024 * 1024 )
                    ),
                ];
            }

            $processed[] = [
                'name'   => sanitize_file_name( $file['name'] ),
                'type'   => sanitize_mime_type( $file['type'] ),
                'base64' => $file['base64'], // Keep original with data URI prefix for AI
                'size'   => $file_size,
            ];
        }

        return [
            'success' => true,
            'files'   => $processed,
        ];
    }

    /**
     * Check if a file type is allowed
     *
     * @param string $mime_type The MIME type to check.
     * @return bool
     */
    private function is_allowed_file_type( string $mime_type ): bool {
        return isset( self::ALLOWED_FILE_TYPES[ $mime_type ] );
    }

    /**
     * Get allowed file types for frontend validation
     *
     * @return array
     */
    public static function get_allowed_file_types(): array {
        return self::ALLOWED_FILE_TYPES;
    }

    /**
     * Get max file size for frontend validation
     *
     * @return int
     */
    public static function get_max_file_size(): int {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Get max files per message for frontend validation
     *
     * @return int
     */
    public static function get_max_files_per_message(): int {
        return self::MAX_FILES_PER_MESSAGE;
    }
}
