<?php
/**
 * Phase Detector
 *
 * Detects the current phase of AI response and manages phase transitions.
 * Phases: DISCOVERY, PROPOSAL, EXECUTION
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Class PhaseDetector
 *
 * Detects and manages AI conversation phases:
 * - DISCOVERY: AI is asking clarifying questions
 * - PROPOSAL: AI is proposing a plan and waiting for confirmation
 * - EXECUTION: AI has generated code to execute
 */
class PhaseDetector {

	/**
	 * Phase constants
	 */
	public const PHASE_DISCOVERY  = 'discovery';
	public const PHASE_PROPOSAL   = 'proposal';
	public const PHASE_EXECUTION  = 'execution';
	public const PHASE_UNKNOWN    = 'unknown';

	/**
	 * User input types
	 */
	public const INPUT_QUESTION       = 'question';
	public const INPUT_COMMAND        = 'command';
	public const INPUT_CONFIRMATION   = 'confirmation';
	public const INPUT_REJECTION      = 'rejection';
	public const INPUT_MODIFICATION   = 'modification';
	public const INPUT_ANSWER         = 'answer';

	/**
	 * Detect phase from AI response
	 *
	 * @param array|string $response AI response (parsed JSON or raw string).
	 * @return array Phase detection result.
	 */
	public function detect_phase( $response ): array {
		// If response is a string, try to parse it as JSON
		if ( is_string( $response ) ) {
			$response = $this->parse_response( $response );
		}

		// If we have explicit phase in response, use it
		if ( isset( $response['phase'] ) && in_array( $response['phase'], $this->get_valid_phases(), true ) ) {
			return $this->create_detection_result(
				$response['phase'],
				1.0, // High confidence when explicit
				'Explicit phase declaration in response'
			);
		}

		// Detect based on response structure
		return $this->detect_from_structure( $response );
	}

	/**
	 * Classify user input to determine appropriate response
	 *
	 * @param string $input      User input.
	 * @param string $prev_phase Previous phase in conversation.
	 * @return array Classification result.
	 */
	public function classify_user_input( string $input, string $prev_phase = '' ): array {
		$input_lower = strtolower( trim( $input ) );

		// Check for confirmation patterns
		if ( $this->is_confirmation( $input_lower ) ) {
			return [
				'type'       => self::INPUT_CONFIRMATION,
				'confidence' => 0.9,
				'next_phase' => self::PHASE_EXECUTION,
			];
		}

		// Check for rejection patterns
		if ( $this->is_rejection( $input_lower ) ) {
			return [
				'type'       => self::INPUT_REJECTION,
				'confidence' => 0.9,
				'next_phase' => self::PHASE_DISCOVERY,
			];
		}

		// Check for modification patterns
		if ( $this->is_modification( $input_lower ) ) {
			return [
				'type'       => self::INPUT_MODIFICATION,
				'confidence' => 0.8,
				'next_phase' => self::PHASE_PROPOSAL,
			];
		}

		// Check if user is asking a question
		if ( $this->is_question( $input_lower ) ) {
			return [
				'type'       => self::INPUT_QUESTION,
				'confidence' => 0.7,
				'next_phase' => self::PHASE_DISCOVERY,
			];
		}

		// Check if user is giving a command/request
		if ( $this->is_command( $input_lower ) ) {
			return [
				'type'       => self::INPUT_COMMAND,
				'confidence' => 0.7,
				'next_phase' => self::PHASE_DISCOVERY, // Start with discovery to understand
			];
		}

		// Default: treat as answer to previous questions
		if ( $prev_phase === self::PHASE_DISCOVERY ) {
			return [
				'type'       => self::INPUT_ANSWER,
				'confidence' => 0.6,
				'next_phase' => self::PHASE_PROPOSAL, // Move to proposal after getting answers
			];
		}

		return [
			'type'       => self::INPUT_COMMAND,
			'confidence' => 0.5,
			'next_phase' => self::PHASE_DISCOVERY,
		];
	}

	/**
	 * Detect phase from response structure
	 *
	 * @param array $response Parsed response.
	 * @return array Detection result.
	 */
	private function detect_from_structure( array $response ): array {
		$indicators = [
			self::PHASE_DISCOVERY => 0,
			self::PHASE_PROPOSAL  => 0,
			self::PHASE_EXECUTION => 0,
		];

		// Check for questions (discovery indicator)
		if ( isset( $response['questions'] ) && ! empty( $response['questions'] ) ) {
			$indicators[ self::PHASE_DISCOVERY ] += 3;
		}

		// Check message content for question patterns
		$message = $response['message'] ?? '';
		if ( $this->contains_questions( $message ) ) {
			$indicators[ self::PHASE_DISCOVERY ] += 2;
		}

		// Check for plan (proposal indicator)
		if ( isset( $response['plan'] ) && ! empty( $response['plan'] ) ) {
			$indicators[ self::PHASE_PROPOSAL ] += 3;
		}

		// Check for confirmation_required flag
		if ( isset( $response['confirmation_required'] ) && $response['confirmation_required'] ) {
			$indicators[ self::PHASE_PROPOSAL ] += 2;
		}

		// Check for estimated_credits (proposal indicator)
		if ( isset( $response['plan']['estimated_credits'] ) ) {
			$indicators[ self::PHASE_PROPOSAL ] += 1;
		}

		// Check for code (execution indicator)
		if ( isset( $response['code'] ) && ! empty( $response['code'] ) ) {
			$indicators[ self::PHASE_EXECUTION ] += 3;
		}

		// Check for actions with 'ready' status (execution indicator)
		if ( isset( $response['actions'] ) && is_array( $response['actions'] ) ) {
			foreach ( $response['actions'] as $action ) {
				if ( isset( $action['status'] ) && $action['status'] === 'ready' ) {
					$indicators[ self::PHASE_EXECUTION ] += 2;
				}
				if ( isset( $action['status'] ) && $action['status'] === 'pending' ) {
					$indicators[ self::PHASE_PROPOSAL ] += 1;
				}
			}
		}

		// Check for verification results (execution completed)
		if ( isset( $response['verification'] ) ) {
			$indicators[ self::PHASE_EXECUTION ] += 2;
		}

		// Determine winner
		arsort( $indicators );
		$phases = array_keys( $indicators );
		$scores = array_values( $indicators );

		$detected_phase = $phases[0];
		$confidence     = $scores[0] > 0 ? min( $scores[0] / 5, 1.0 ) : 0.3;

		// If no clear indicators, default to discovery
		if ( $scores[0] === 0 ) {
			$detected_phase = self::PHASE_DISCOVERY;
			$confidence     = 0.3;
		}

		return $this->create_detection_result(
			$detected_phase,
			$confidence,
			$this->get_detection_reason( $detected_phase, $response )
		);
	}

	/**
	 * Check if message contains questions
	 *
	 * @param string $message Message content.
	 * @return bool
	 */
	private function contains_questions( string $message ): bool {
		// Check for question marks
		if ( substr_count( $message, '?' ) >= 1 ) {
			return true;
		}

		// Check for question patterns in Italian and English
		$patterns = [
			'/\b(cosa|come|quando|dove|perché|chi|quale|quanto)\b/i',
			'/\b(what|how|when|where|why|who|which)\b.*\?/i',
			'/\bvuoi che\b/i',
			'/\bposso\b.*\?/i',
			'/\bdevo\b.*\?/i',
			'/\bpreferisci\b/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if input is a confirmation
	 *
	 * @param string $input User input (lowercase).
	 * @return bool
	 */
	private function is_confirmation( string $input ): bool {
		$confirmations = [
			'sì', 'si', 'yes', 'ok', 'okay', 'va bene', 'procedi', 'confermo',
			'conferma', 'go', 'do it', 'proceed', 'fallo', 'vai', 'esegui',
			'perfetto', 'great', 'sounds good', 'let\'s do it', 'avanti',
			'approvato', 'approved', 'conferma tutto', 'tutto ok',
		];

		foreach ( $confirmations as $conf ) {
			if ( $input === $conf || strpos( $input, $conf ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if input is a rejection
	 *
	 * @param string $input User input (lowercase).
	 * @return bool
	 */
	private function is_rejection( string $input ): bool {
		$rejections = [
			'no', 'non', 'nope', 'annulla', 'cancel', 'stop', 'ferma',
			'non voglio', 'i don\'t want', 'abort', 'blocca', 'cancella',
			'no grazie', 'no thanks', 'lascia stare', 'forget it',
		];

		foreach ( $rejections as $rej ) {
			if ( $input === $rej || strpos( $input, $rej ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if input is a modification request
	 *
	 * @param string $input User input (lowercase).
	 * @return bool
	 */
	private function is_modification( string $input ): bool {
		$modifications = [
			'modifica', 'cambia', 'change', 'modify', 'ma', 'però', 'but',
			'invece', 'instead', 'vorrei', 'i would like', 'preferisco',
			'preferirei', 'i prefer', 'puoi', 'can you', 'e se', 'what if',
			'aggiungi', 'add', 'rimuovi', 'remove', 'togli',
		];

		foreach ( $modifications as $mod ) {
			if ( strpos( $input, $mod ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if input is a question
	 *
	 * @param string $input User input (lowercase).
	 * @return bool
	 */
	private function is_question( string $input ): bool {
		// Contains question mark
		if ( strpos( $input, '?' ) !== false ) {
			return true;
		}

		// Starts with question words
		$question_starters = [
			'come', 'cosa', 'quando', 'dove', 'perché', 'chi', 'quale', 'quanto',
			'how', 'what', 'when', 'where', 'why', 'who', 'which',
			'puoi spiegarmi', 'can you explain', 'mi spieghi',
		];

		foreach ( $question_starters as $starter ) {
			if ( strpos( $input, $starter ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if input is a command/request
	 *
	 * @param string $input User input (lowercase).
	 * @return bool
	 */
	private function is_command( string $input ): bool {
		$command_patterns = [
			'crea', 'create', 'aggiungi', 'add', 'rimuovi', 'remove', 'delete',
			'elimina', 'modifica', 'modify', 'update', 'aggiorna', 'cambia',
			'change', 'imposta', 'set', 'configura', 'configure', 'installa',
			'install', 'attiva', 'activate', 'disattiva', 'deactivate',
			'voglio', 'i want', 'ho bisogno', 'i need', 'fammi', 'make me',
		];

		foreach ( $command_patterns as $cmd ) {
			if ( strpos( $input, $cmd ) !== false ) {
				return true;
			}
		}

		// Check for imperative verb patterns
		if ( preg_match( '/^[a-z]+[aei]( |$)/i', $input ) ) { // Italian imperative
			return true;
		}

		return false;
	}

	/**
	 * Parse response string to array
	 *
	 * @param string $response Raw response.
	 * @return array
	 */
	private function parse_response( string $response ): array {
		$response = trim( $response );

		// Remove markdown code blocks
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches ) ) {
			$response = trim( $matches[1] );
		}

		$parsed = json_decode( $response, true );

		if ( json_last_error() === JSON_ERROR_NONE && is_array( $parsed ) ) {
			return $parsed;
		}

		return [ 'message' => $response ];
	}

	/**
	 * Get valid phases
	 *
	 * @return array
	 */
	private function get_valid_phases(): array {
		return [
			self::PHASE_DISCOVERY,
			self::PHASE_PROPOSAL,
			self::PHASE_EXECUTION,
		];
	}

	/**
	 * Get detection reason
	 *
	 * @param string $phase    Detected phase.
	 * @param array  $response Response data.
	 * @return string
	 */
	private function get_detection_reason( string $phase, array $response ): string {
		switch ( $phase ) {
			case self::PHASE_DISCOVERY:
				if ( isset( $response['questions'] ) ) {
					return 'Response contains explicit questions';
				}
				return 'Response appears to be asking for clarification';

			case self::PHASE_PROPOSAL:
				if ( isset( $response['plan'] ) ) {
					return 'Response contains execution plan';
				}
				if ( isset( $response['confirmation_required'] ) ) {
					return 'Response requires user confirmation';
				}
				return 'Response appears to be proposing a solution';

			case self::PHASE_EXECUTION:
				if ( isset( $response['code'] ) ) {
					return 'Response contains executable code';
				}
				if ( isset( $response['verification'] ) ) {
					return 'Response contains execution verification';
				}
				return 'Response appears to be executing or reporting results';

			default:
				return 'Unable to determine phase';
		}
	}

	/**
	 * Create detection result
	 *
	 * @param string $phase      Detected phase.
	 * @param float  $confidence Confidence score 0-1.
	 * @param string $reason     Detection reason.
	 * @return array
	 */
	private function create_detection_result( string $phase, float $confidence, string $reason ): array {
		return [
			'phase'      => $phase,
			'confidence' => $confidence,
			'reason'     => $reason,
			'is_final'   => $phase === self::PHASE_EXECUTION,
		];
	}

	/**
	 * Get next expected phase based on current
	 *
	 * @param string $current_phase Current phase.
	 * @return string
	 */
	public function get_next_phase( string $current_phase ): string {
		$transitions = [
			self::PHASE_DISCOVERY => self::PHASE_PROPOSAL,
			self::PHASE_PROPOSAL  => self::PHASE_EXECUTION,
			self::PHASE_EXECUTION => self::PHASE_DISCOVERY, // Cycle back for new requests
		];

		return $transitions[ $current_phase ] ?? self::PHASE_DISCOVERY;
	}

	/**
	 * Check if phase transition is valid
	 *
	 * @param string $from_phase Current phase.
	 * @param string $to_phase   Target phase.
	 * @return bool
	 */
	public function is_valid_transition( string $from_phase, string $to_phase ): bool {
		$valid_transitions = [
			self::PHASE_DISCOVERY => [ self::PHASE_DISCOVERY, self::PHASE_PROPOSAL ],
			self::PHASE_PROPOSAL  => [ self::PHASE_DISCOVERY, self::PHASE_PROPOSAL, self::PHASE_EXECUTION ],
			self::PHASE_EXECUTION => [ self::PHASE_DISCOVERY, self::PHASE_EXECUTION ],
		];

		if ( ! isset( $valid_transitions[ $from_phase ] ) ) {
			return true; // Allow if unknown
		}

		return in_array( $to_phase, $valid_transitions[ $from_phase ], true );
	}
}
