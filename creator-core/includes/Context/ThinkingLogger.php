<?php
/**
 * Thinking Logger
 *
 * Logs Creator's thinking process for transparency and debugging.
 * Shows users what Creator is doing at each step of the conversation.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class ThinkingLogger
 *
 * Tracks and logs the AI's reasoning process:
 * - Discovery: Analyzing user request
 * - Analysis: Loading context and data
 * - Planning: Generating proposal
 * - Execution: Running code
 * - Verification: Checking results
 */
class ThinkingLogger {

	/**
	 * Log levels
	 */
	const LEVEL_INFO    = 'info';
	const LEVEL_DEBUG   = 'debug';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';
	const LEVEL_SUCCESS = 'success';

	/**
	 * Thinking phases
	 */
	const PHASE_DISCOVERY    = 'discovery';
	const PHASE_ANALYSIS     = 'analysis';
	const PHASE_PLANNING     = 'planning';
	const PHASE_EXECUTION    = 'execution';
	const PHASE_VERIFICATION = 'verification';

	/**
	 * Log entries
	 *
	 * @var array
	 */
	private array $logs = [];

	/**
	 * Chat ID
	 *
	 * @var int
	 */
	private int $chat_id;

	/**
	 * Message ID (if applicable)
	 *
	 * @var int|null
	 */
	private ?int $message_id = null;

	/**
	 * Start time for elapsed calculation
	 *
	 * @var float
	 */
	private float $start_time;

	/**
	 * Current phase
	 *
	 * @var string
	 */
	private string $current_phase = self::PHASE_DISCOVERY;

	/**
	 * Whether the thinking process is complete
	 *
	 * @var bool
	 */
	private bool $is_complete = false;

	/**
	 * Constructor
	 *
	 * @param int      $chat_id    Chat ID.
	 * @param int|null $message_id Optional message ID.
	 */
	public function __construct( int $chat_id, ?int $message_id = null ) {
		$this->chat_id    = $chat_id;
		$this->message_id = $message_id;
		$this->start_time = microtime( true );
	}

	/**
	 * Set current phase
	 *
	 * @param string $phase Phase name.
	 * @return self
	 */
	public function set_phase( string $phase ): self {
		$this->current_phase = $phase;
		return $this;
	}

	/**
	 * Log a thinking step
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, debug, warning, error, success).
	 * @param array  $data    Optional additional data.
	 * @return self
	 */
	public function log( string $message, string $level = self::LEVEL_INFO, array $data = [] ): self {
		$elapsed_ms = round( ( microtime( true ) - $this->start_time ) * 1000 );

		$entry = [
			'id'         => count( $this->logs ) + 1,
			'timestamp'  => current_time( 'c' ),
			'elapsed_ms' => $elapsed_ms,
			'phase'      => $this->current_phase,
			'level'      => $level,
			'message'    => $message,
			'data'       => $data,
		];

		$this->logs[] = $entry;

		// Store in transient for real-time access
		$this->update_transient();

		return $this;
	}

	/**
	 * Log info message
	 *
	 * @param string $message Message.
	 * @param array  $data    Optional data.
	 * @return self
	 */
	public function info( string $message, array $data = [] ): self {
		return $this->log( $message, self::LEVEL_INFO, $data );
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Message.
	 * @param array  $data    Optional data.
	 * @return self
	 */
	public function debug( string $message, array $data = [] ): self {
		return $this->log( $message, self::LEVEL_DEBUG, $data );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Message.
	 * @param array  $data    Optional data.
	 * @return self
	 */
	public function warning( string $message, array $data = [] ): self {
		return $this->log( $message, self::LEVEL_WARNING, $data );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Message.
	 * @param array  $data    Optional data.
	 * @return self
	 */
	public function error( string $message, array $data = [] ): self {
		return $this->log( $message, self::LEVEL_ERROR, $data );
	}

	/**
	 * Log success message
	 *
	 * @param string $message Message.
	 * @param array  $data    Optional data.
	 * @return self
	 */
	public function success( string $message, array $data = [] ): self {
		return $this->log( $message, self::LEVEL_SUCCESS, $data );
	}

	// =========================================================================
	// PHASE-SPECIFIC LOGGING HELPERS
	// =========================================================================

	/**
	 * Start discovery phase
	 *
	 * @return self
	 */
	public function start_discovery(): self {
		$this->set_phase( self::PHASE_DISCOVERY );
		return $this->info( 'Analyzing user request...' );
	}

	/**
	 * Log conversation history loaded
	 *
	 * @param int $message_count Number of messages loaded.
	 * @return self
	 */
	public function log_history_loaded( int $message_count ): self {
		return $this->info( "Loaded {$message_count} previous messages" );
	}

	/**
	 * Log user profile detected
	 *
	 * @param string $profile Profile level (base, intermediate, advanced).
	 * @return self
	 */
	public function log_user_profile( string $profile ): self {
		$labels = [
			'base'         => 'Beginner',
			'intermediate' => 'Intermediate',
			'advanced'     => 'Developer',
		];
		$label = $labels[ $profile ] ?? ucfirst( $profile );
		return $this->info( "User skill level: {$label}" );
	}

	/**
	 * Log file attachments received
	 *
	 * @param int $count Number of attachments.
	 * @return self
	 */
	public function log_attachments( int $count ): self {
		if ( $count > 0 ) {
			return $this->info( "Received {$count} file attachment(s)" );
		}
		return $this;
	}

	/**
	 * Start analysis phase
	 *
	 * @return self
	 */
	public function start_analysis(): self {
		$this->set_phase( self::PHASE_ANALYSIS );
		return $this->info( 'Loading context data...' );
	}

	/**
	 * Log plugin details loading
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return self
	 */
	public function log_plugin_loading( string $plugin_slug ): self {
		return $this->info( "Loading plugin details: {$plugin_slug}" );
	}

	/**
	 * Log context token count
	 *
	 * @param int $tokens Token count.
	 * @return self
	 */
	public function log_token_count( int $tokens ): self {
		$formatted = number_format( $tokens );
		return $this->debug( "Context size: ~{$formatted} tokens" );
	}

	/**
	 * Log cache hit/miss
	 *
	 * @param string $item      Cache item name.
	 * @param bool   $cache_hit Whether it was a cache hit.
	 * @return self
	 */
	public function log_cache( string $item, bool $cache_hit ): self {
		$status = $cache_hit ? 'Cache hit' : 'Cache miss';
		return $this->debug( "{$status}: {$item}" );
	}

	/**
	 * Log context request
	 *
	 * @param string $type   Request type (get_plugin_details, get_acf_details, etc.).
	 * @param string $target Target identifier.
	 * @return self
	 */
	public function log_context_request( string $type, string $target ): self {
		$labels = [
			'get_plugin_details'   => 'Plugin',
			'get_acf_details'      => 'ACF group',
			'get_cpt_details'      => 'CPT',
			'get_taxonomy_details' => 'Taxonomy',
			'get_wp_functions'     => 'Functions',
		];
		$label = $labels[ $type ] ?? $type;
		return $this->info( "Loading {$label} details: {$target}" );
	}

	/**
	 * Start planning phase
	 *
	 * @return self
	 */
	public function start_planning(): self {
		$this->set_phase( self::PHASE_PLANNING );
		return $this->info( 'Generating proposal...' );
	}

	/**
	 * Log AI provider call
	 *
	 * @param string $provider Provider name (gemini, claude).
	 * @return self
	 */
	public function log_ai_call( string $provider ): self {
		$provider_label = ucfirst( $provider );
		return $this->info( "Calling {$provider_label} AI..." );
	}

	/**
	 * Log plan generated
	 *
	 * @param int $step_count   Number of steps.
	 * @param int $est_credits  Estimated credits.
	 * @return self
	 */
	public function log_plan_generated( int $step_count, int $est_credits = 0 ): self {
		$msg = "Plan: {$step_count} step(s) identified";
		if ( $est_credits > 0 ) {
			$msg .= " (~{$est_credits} credits)";
		}
		return $this->info( $msg );
	}

	/**
	 * Log phase detected
	 *
	 * @param string $phase      Detected phase.
	 * @param float  $confidence Confidence score.
	 * @return self
	 */
	public function log_phase_detected( string $phase, float $confidence = 0.0 ): self {
		$phase_label = ucfirst( $phase );
		$msg         = "Phase detected: {$phase_label}";
		if ( $confidence > 0 ) {
			$pct = round( $confidence * 100 );
			$msg .= " ({$pct}% confidence)";
		}
		return $this->debug( $msg );
	}

	/**
	 * Start execution phase
	 *
	 * @return self
	 */
	public function start_execution(): self {
		$this->set_phase( self::PHASE_EXECUTION );
		return $this->info( 'Starting code execution...' );
	}

	/**
	 * Log security check
	 *
	 * @param bool   $passed        Whether check passed.
	 * @param string $details       Optional details.
	 * @return self
	 */
	public function log_security_check( bool $passed, string $details = '' ): self {
		if ( $passed ) {
			$msg = 'Security check: PASS';
			if ( $details ) {
				$msg .= " ({$details})";
			}
			return $this->success( $msg );
		} else {
			return $this->error( "Security check: FAIL - {$details}" );
		}
	}

	/**
	 * Log code generation
	 *
	 * @param int    $line_count Number of lines generated.
	 * @param string $language   Code language.
	 * @return self
	 */
	public function log_code_generated( int $line_count, string $language = 'PHP' ): self {
		return $this->info( "Generated {$line_count} lines of {$language}" );
	}

	/**
	 * Log snapshot creation
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return self
	 */
	public function log_snapshot_created( int $snapshot_id ): self {
		return $this->info( 'Snapshot created for rollback', [ 'snapshot_id' => $snapshot_id ] );
	}

	/**
	 * Log code execution start
	 *
	 * @param string $method Execution method (wpcode, direct, fallback).
	 * @return self
	 */
	public function log_execution_start( string $method ): self {
		$labels = [
			'wpcode'   => 'WP Code snippet',
			'direct'   => 'Direct execution',
			'fallback' => 'Fallback file',
		];
		$label = $labels[ $method ] ?? $method;
		return $this->info( "Executing via {$label}..." );
	}

	/**
	 * Start verification phase
	 *
	 * @return self
	 */
	public function start_verification(): self {
		$this->set_phase( self::PHASE_VERIFICATION );
		return $this->info( 'Verifying results...' );
	}

	/**
	 * Log verification result
	 *
	 * @param bool   $success Whether verification passed.
	 * @param string $details Details about what was verified.
	 * @return self
	 */
	public function log_verification_result( bool $success, string $details = '' ): self {
		if ( $success ) {
			return $this->success( "Verified: {$details}" );
		} else {
			return $this->warning( "Verification issue: {$details}" );
		}
	}

	/**
	 * Log retry attempt
	 *
	 * @param int    $attempt     Current attempt number.
	 * @param int    $max_retries Maximum retries.
	 * @param string $reason      Reason for retry.
	 * @return self
	 */
	public function log_retry( int $attempt, int $max_retries, string $reason = '' ): self {
		$msg = "Retry {$attempt}/{$max_retries}";
		if ( $reason ) {
			$msg .= ": {$reason}";
		}
		return $this->warning( $msg );
	}

	/**
	 * Log completion
	 *
	 * Marks the thinking process as complete and updates the transient
	 * so SSE streaming can detect completion.
	 *
	 * @param string $summary Summary of what was accomplished.
	 * @return self
	 */
	public function log_complete( string $summary ): self {
		$this->is_complete = true;
		$this->success( "Complete: {$summary}" );
		// Ensure transient is updated with complete status
		$this->update_transient();
		return $this;
	}

	// =========================================================================
	// STORAGE AND RETRIEVAL
	// =========================================================================

	/**
	 * Get all logs
	 *
	 * When called on a new instance (SSE polling), reads from transient.
	 * Supports filtering by after_index for incremental updates.
	 *
	 * @param int $after_index Only return logs with index > this value.
	 * @return array
	 */
	public function get_logs( int $after_index = 0 ): array {
		// If we have local logs, use them
		$logs = $this->logs;

		// If no local logs, try to read from transient (SSE polling scenario)
		if ( empty( $logs ) ) {
			$transient = get_transient( "creator_thinking_{$this->chat_id}" );
			if ( $transient && isset( $transient['logs'] ) ) {
				$logs = $transient['logs'];
			} elseif ( is_array( $transient ) && ! isset( $transient['logs'] ) ) {
				// Legacy format: transient contains just logs array
				$logs = $transient;
			}
		}

		// Filter by after_index if specified
		if ( $after_index > 0 ) {
			$logs = array_filter( $logs, fn( $log ) => ( $log['id'] ?? 0 ) > $after_index );
			$logs = array_values( $logs ); // Re-index array
		}

		// Add index for SSE tracking
		return array_map( function ( $log, $key ) {
			$log['index'] = $log['id'] ?? $key;
			return $log;
		}, $logs, array_keys( $logs ) );
	}

	/**
	 * Get thinking status
	 *
	 * Returns the current status of the thinking process.
	 * Used by SSE streaming to detect completion.
	 *
	 * @return array Status with 'complete' boolean.
	 */
	public function get_status(): array {
		// Check local status first
		if ( $this->is_complete ) {
			return [ 'complete' => true ];
		}

		// Read from transient (SSE polling scenario)
		$transient = get_transient( "creator_thinking_{$this->chat_id}" );
		if ( $transient && isset( $transient['complete'] ) ) {
			return [ 'complete' => (bool) $transient['complete'] ];
		}

		return [ 'complete' => false ];
	}

	/**
	 * Get logs for specific phase
	 *
	 * @param string $phase Phase name.
	 * @return array
	 */
	public function get_logs_by_phase( string $phase ): array {
		return array_filter( $this->logs, fn( $log ) => $log['phase'] === $phase );
	}

	/**
	 * Get total elapsed time
	 *
	 * @return int Milliseconds elapsed.
	 */
	public function get_elapsed_time(): int {
		return round( ( microtime( true ) - $this->start_time ) * 1000 );
	}

	/**
	 * Get summary stats
	 *
	 * @return array
	 */
	public function get_summary(): array {
		$phases      = [];
		$error_count = 0;

		foreach ( $this->logs as $log ) {
			$phases[ $log['phase'] ] = ( $phases[ $log['phase'] ] ?? 0 ) + 1;
			if ( $log['level'] === self::LEVEL_ERROR ) {
				$error_count++;
			}
		}

		return [
			'total_logs'   => count( $this->logs ),
			'elapsed_ms'   => $this->get_elapsed_time(),
			'phases'       => $phases,
			'error_count'  => $error_count,
			'has_errors'   => $error_count > 0,
		];
	}

	/**
	 * Maximum logs to store in transient
	 */
	const MAX_TRANSIENT_LOGS = 100;

	/**
	 * Update transient for real-time access
	 *
	 * Limits stored logs to prevent oversized transients with long sessions.
	 * Also stores the completion status for SSE polling.
	 *
	 * @return void
	 */
	private function update_transient(): void {
		$key  = "creator_thinking_{$this->chat_id}";
		$logs = $this->logs;

		// Limit to last MAX_TRANSIENT_LOGS to prevent oversized transients
		if ( count( $logs ) > self::MAX_TRANSIENT_LOGS ) {
			$logs = array_slice( $logs, -self::MAX_TRANSIENT_LOGS );
		}

		// Store logs with completion status for SSE streaming
		$data = [
			'logs'     => $logs,
			'complete' => $this->is_complete,
		];

		set_transient( $key, $data, 300 ); // 5 minutes
	}

	/**
	 * Save to database
	 *
	 * @return bool
	 */
	public function save_to_database(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'creator_thinking_logs';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			// Table doesn't exist, use transient only
			return true;
		}

		$result = $wpdb->insert(
			$table,
			[
				'chat_id'    => $this->chat_id,
				'message_id' => $this->message_id,
				'logs'       => wp_json_encode( $this->logs ),
				'summary'    => wp_json_encode( $this->get_summary() ),
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Get thinking logs from transient
	 *
	 * @param int $chat_id Chat ID.
	 * @return array|null Array of logs, or null if not found.
	 */
	public static function get_from_transient( int $chat_id ): ?array {
		$key       = "creator_thinking_{$chat_id}";
		$transient = get_transient( $key );

		if ( ! $transient ) {
			return null;
		}

		// New format: { logs: [...], complete: bool }
		if ( isset( $transient['logs'] ) ) {
			return $transient['logs'];
		}

		// Legacy format: just the logs array
		if ( is_array( $transient ) ) {
			return $transient;
		}

		return null;
	}

	/**
	 * Get thinking logs from database
	 *
	 * @param int $chat_id    Chat ID.
	 * @param int $message_id Optional message ID.
	 * @return array|null
	 */
	public static function get_from_database( int $chat_id, ?int $message_id = null ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'creator_thinking_logs';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return null;
		}

		$where = $wpdb->prepare( 'chat_id = %d', $chat_id );
		if ( $message_id ) {
			$where .= $wpdb->prepare( ' AND message_id = %d', $message_id );
		}

		$row = $wpdb->get_row(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 1",
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return [
			'id'         => (int) $row['id'],
			'chat_id'    => (int) $row['chat_id'],
			'message_id' => $row['message_id'] ? (int) $row['message_id'] : null,
			'logs'       => json_decode( $row['logs'], true ),
			'summary'    => json_decode( $row['summary'], true ),
			'created_at' => $row['created_at'],
		];
	}

	/**
	 * Clear transient
	 *
	 * @param int $chat_id Chat ID.
	 * @return bool
	 */
	public static function clear_transient( int $chat_id ): bool {
		return delete_transient( "creator_thinking_{$chat_id}" );
	}
}
