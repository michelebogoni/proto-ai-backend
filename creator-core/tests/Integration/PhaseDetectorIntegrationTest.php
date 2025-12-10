<?php
/**
 * Phase Detector Integration Tests
 *
 * Tests the complete phase detection flow including:
 * - Discovery phase detection
 * - Proposal phase detection
 * - Execution phase detection
 * - User input classification
 * - Phase transitions
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Chat\PhaseDetector;

/**
 * Test class for PhaseDetector integration scenarios
 */
class PhaseDetectorIntegrationTest extends TestCase {

	/**
	 * PhaseDetector instance
	 *
	 * @var PhaseDetector
	 */
	private PhaseDetector $detector;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->detector = new PhaseDetector();
	}

	// ========================
	// DISCOVERY PHASE TESTS
	// ========================

	/**
	 * Test detection of explicit discovery phase response
	 */
	public function test_detect_explicit_discovery_phase(): void {
		$response = [
			'phase'     => 'discovery',
			'message'   => 'Ho bisogno di alcune informazioni prima di procedere.',
			'questions' => [
				'Dove vuoi che appaia questo elemento?',
				'Quale stile preferisci?',
			],
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $result['phase'] );
		$this->assertEquals( 1.0, $result['confidence'] );
		$this->assertFalse( $result['is_final'] );
	}

	/**
	 * Test detection of implicit discovery phase (questions in message)
	 */
	public function test_detect_implicit_discovery_from_questions(): void {
		$response = [
			'message'   => 'Prima di procedere, ho alcune domande:',
			'questions' => [
				'Cosa vuoi creare?',
				'Come deve funzionare?',
			],
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $result['phase'] );
		$this->assertGreaterThan( 0.5, $result['confidence'] );
	}

	/**
	 * Test detection of discovery phase from message with question marks
	 */
	public function test_detect_discovery_from_question_marks(): void {
		$response = [
			'message' => 'Capisco che vuoi creare una pagina. Dove la vuoi inserire? Nel menu principale o come sottopagina?',
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $result['phase'] );
	}

	/**
	 * Test AI asks clarifying questions scenario
	 */
	public function test_scenario_ai_asks_clarifying_questions(): void {
		// User provides vague request
		$user_input = 'Crea una sezione contatti';

		$input_class = $this->detector->classify_user_input( $user_input );
		$this->assertEquals( PhaseDetector::INPUT_COMMAND, $input_class['type'] );
		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $input_class['next_phase'] );

		// AI responds with questions
		$ai_response = [
			'phase'     => 'discovery',
			'message'   => 'Perfetto! Voglio capire meglio cosa ti serve.',
			'questions' => [
				'Vuoi un form di contatto o solo le informazioni?',
				'Quali campi vuoi nel form (email, telefono, messaggio)?',
			],
		];

		$phase_result = $this->detector->detect_phase( $ai_response );
		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $phase_result['phase'] );
	}

	// ========================
	// PROPOSAL PHASE TESTS
	// ========================

	/**
	 * Test detection of explicit proposal phase
	 */
	public function test_detect_explicit_proposal_phase(): void {
		$response = [
			'phase'   => 'proposal',
			'message' => 'Ecco il piano che propongo:',
			'plan'    => [
				'summary'           => 'Creerò una pagina contatti con form',
				'steps'             => [
					'1. Crea pagina "Contatti"',
					'2. Aggiungi form con Contact Form 7',
					'3. Configura email notifiche',
				],
				'estimated_credits' => 15,
				'risks'             => [],
				'rollback_possible' => true,
			],
			'confirmation_required' => true,
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $result['phase'] );
		$this->assertEquals( 1.0, $result['confidence'] );
	}

	/**
	 * Test detection of implicit proposal phase (has plan)
	 */
	public function test_detect_implicit_proposal_from_plan(): void {
		$response = [
			'message' => 'Ecco cosa farò:',
			'plan'    => [
				'steps'             => [ 'Step 1', 'Step 2' ],
				'estimated_credits' => 10,
			],
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $result['phase'] );
	}

	/**
	 * Test AI proposes plan scenario
	 */
	public function test_scenario_ai_proposes_plan(): void {
		// User answers discovery questions
		$user_input = 'Voglio un form con email, nome e messaggio';

		$input_class = $this->detector->classify_user_input( $user_input, PhaseDetector::PHASE_DISCOVERY );
		$this->assertEquals( PhaseDetector::INPUT_ANSWER, $input_class['type'] );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $input_class['next_phase'] );

		// AI proposes plan
		$ai_response = [
			'phase'   => 'proposal',
			'message' => 'Perfetto! Creerò un form contatti.',
			'plan'    => [
				'summary'           => 'Form contatti con 3 campi',
				'steps'             => [ 'Crea form', 'Aggiungi alla pagina' ],
				'estimated_credits' => 10,
				'rollback_possible' => true,
			],
			'confirmation_required' => true,
		];

		$phase_result = $this->detector->detect_phase( $ai_response );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $phase_result['phase'] );
	}

	// ========================
	// EXECUTION PHASE TESTS
	// ========================

	/**
	 * Test detection of explicit execution phase
	 */
	public function test_detect_explicit_execution_phase(): void {
		$response = [
			'phase'   => 'execution',
			'message' => 'Ho completato l\'operazione.',
			'code'    => [
				'type'     => 'wpcode_snippet',
				'title'    => 'Contact Form Setup',
				'content'  => '<?php // form code',
				'language' => 'php',
			],
			'verification' => [
				'success' => true,
				'checks'  => [
					[ 'name' => 'Form created', 'passed' => true ],
				],
			],
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_EXECUTION, $result['phase'] );
		$this->assertEquals( 1.0, $result['confidence'] );
		$this->assertTrue( $result['is_final'] );
	}

	/**
	 * Test detection of implicit execution phase (has code)
	 */
	public function test_detect_implicit_execution_from_code(): void {
		$response = [
			'message' => 'Ecco il codice:',
			'code'    => [
				'content'  => '<?php echo "Hello";',
				'language' => 'php',
			],
		];

		$result = $this->detector->detect_phase( $response );

		$this->assertEquals( PhaseDetector::PHASE_EXECUTION, $result['phase'] );
	}

	/**
	 * Test AI generates and executes code scenario
	 */
	public function test_scenario_ai_generates_code(): void {
		// User confirms proposal
		$user_input = 'Sì, procedi';

		$input_class = $this->detector->classify_user_input( $user_input, PhaseDetector::PHASE_PROPOSAL );
		$this->assertEquals( PhaseDetector::INPUT_CONFIRMATION, $input_class['type'] );
		$this->assertEquals( PhaseDetector::PHASE_EXECUTION, $input_class['next_phase'] );

		// AI executes and returns code
		$ai_response = [
			'phase'   => 'execution',
			'message' => 'Ho creato il form contatti.',
			'code'    => [
				'type'        => 'wpcode_snippet',
				'title'       => 'Creator: Form Contatti',
				'content'     => '<?php add_shortcode("contact_form", function() { /* ... */ });',
				'language'    => 'php',
				'auto_execute' => true,
			],
			'verification' => [
				'success' => true,
			],
		];

		$phase_result = $this->detector->detect_phase( $ai_response );
		$this->assertEquals( PhaseDetector::PHASE_EXECUTION, $phase_result['phase'] );
		$this->assertTrue( $phase_result['is_final'] );
	}

	// ========================
	// USER INPUT CLASSIFICATION
	// ========================

	/**
	 * Test confirmation patterns in Italian
	 */
	public function test_classify_italian_confirmations(): void {
		$confirmations = [ 'sì', 'si', 'ok', 'va bene', 'procedi', 'confermo', 'fallo', 'esegui' ];

		foreach ( $confirmations as $input ) {
			$result = $this->detector->classify_user_input( $input );
			$this->assertEquals(
				PhaseDetector::INPUT_CONFIRMATION,
				$result['type'],
				"Failed for input: {$input}"
			);
		}
	}

	/**
	 * Test confirmation patterns in English
	 */
	public function test_classify_english_confirmations(): void {
		$confirmations = [ 'yes', 'ok', 'okay', 'go', 'do it', 'proceed' ];

		foreach ( $confirmations as $input ) {
			$result = $this->detector->classify_user_input( $input );
			$this->assertEquals(
				PhaseDetector::INPUT_CONFIRMATION,
				$result['type'],
				"Failed for input: {$input}"
			);
		}
	}

	/**
	 * Test rejection patterns
	 */
	public function test_classify_rejections(): void {
		$rejections = [ 'no', 'annulla', 'cancel', 'stop', 'non voglio' ];

		foreach ( $rejections as $input ) {
			$result = $this->detector->classify_user_input( $input );
			$this->assertEquals(
				PhaseDetector::INPUT_REJECTION,
				$result['type'],
				"Failed for input: {$input}"
			);
		}
	}

	/**
	 * Test modification patterns
	 */
	public function test_classify_modifications(): void {
		$modifications = [ 'modifica il colore', 'cambia il testo', 'ma vorrei anche', 'invece di blu usa rosso' ];

		foreach ( $modifications as $input ) {
			$result = $this->detector->classify_user_input( $input );
			$this->assertEquals(
				PhaseDetector::INPUT_MODIFICATION,
				$result['type'],
				"Failed for input: {$input}"
			);
		}
	}

	/**
	 * Test question patterns
	 */
	public function test_classify_questions(): void {
		$questions = [
			'Come funziona?',
			'Cosa significa CPT?',
			'Puoi spiegarmi meglio?',
		];

		foreach ( $questions as $input ) {
			$result = $this->detector->classify_user_input( $input );
			$this->assertEquals(
				PhaseDetector::INPUT_QUESTION,
				$result['type'],
				"Failed for input: {$input}"
			);
		}
	}

	/**
	 * Test command patterns
	 */
	public function test_classify_commands(): void {
		$commands = [
			'crea una pagina',
			'aggiungi un menu',
			'installa il plugin SEO',
			'voglio una galleria',
		];

		foreach ( $commands as $input ) {
			$result = $this->detector->classify_user_input( $input );
			$this->assertEquals(
				PhaseDetector::INPUT_COMMAND,
				$result['type'],
				"Failed for input: {$input}"
			);
		}
	}

	// ========================
	// PHASE TRANSITIONS
	// ========================

	/**
	 * Test valid phase transitions
	 */
	public function test_valid_phase_transitions(): void {
		// Discovery -> Proposal (valid)
		$this->assertTrue(
			$this->detector->is_valid_transition(
				PhaseDetector::PHASE_DISCOVERY,
				PhaseDetector::PHASE_PROPOSAL
			)
		);

		// Proposal -> Execution (valid)
		$this->assertTrue(
			$this->detector->is_valid_transition(
				PhaseDetector::PHASE_PROPOSAL,
				PhaseDetector::PHASE_EXECUTION
			)
		);

		// Execution -> Discovery (valid - new request cycle)
		$this->assertTrue(
			$this->detector->is_valid_transition(
				PhaseDetector::PHASE_EXECUTION,
				PhaseDetector::PHASE_DISCOVERY
			)
		);
	}

	/**
	 * Test staying in same phase is valid
	 */
	public function test_same_phase_transition_valid(): void {
		$phases = [
			PhaseDetector::PHASE_DISCOVERY,
			PhaseDetector::PHASE_PROPOSAL,
			PhaseDetector::PHASE_EXECUTION,
		];

		foreach ( $phases as $phase ) {
			$this->assertTrue(
				$this->detector->is_valid_transition( $phase, $phase ),
				"Same phase transition should be valid for: {$phase}"
			);
		}
	}

	/**
	 * Test get next expected phase
	 */
	public function test_get_next_phase(): void {
		$this->assertEquals(
			PhaseDetector::PHASE_PROPOSAL,
			$this->detector->get_next_phase( PhaseDetector::PHASE_DISCOVERY )
		);

		$this->assertEquals(
			PhaseDetector::PHASE_EXECUTION,
			$this->detector->get_next_phase( PhaseDetector::PHASE_PROPOSAL )
		);

		$this->assertEquals(
			PhaseDetector::PHASE_DISCOVERY,
			$this->detector->get_next_phase( PhaseDetector::PHASE_EXECUTION )
		);
	}

	// ========================
	// COMPLETE FLOW TESTS
	// ========================

	/**
	 * Test complete conversation flow: Discovery -> Proposal -> Execution
	 */
	public function test_complete_conversation_flow(): void {
		// Step 1: User makes initial request
		$user_request = 'Crea un CPT per i miei progetti';
		$input_class = $this->detector->classify_user_input( $user_request );
		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $input_class['next_phase'] );

		// Step 2: AI asks clarifying questions (Discovery)
		$ai_discovery = [
			'phase'     => 'discovery',
			'message'   => 'Capisco! Ho alcune domande:',
			'questions' => [
				'Il CPT deve essere gerarchico?',
				'Quali campi vuoi (titolo, data, budget)?',
			],
		];
		$phase = $this->detector->detect_phase( $ai_discovery );
		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $phase['phase'] );

		// Step 3: User answers
		$user_answer = 'No gerarchico, voglio titolo e budget';
		$input_class = $this->detector->classify_user_input( $user_answer, PhaseDetector::PHASE_DISCOVERY );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $input_class['next_phase'] );

		// Step 4: AI proposes plan (Proposal)
		$ai_proposal = [
			'phase'                 => 'proposal',
			'message'               => 'Ecco il piano:',
			'plan'                  => [
				'summary'           => 'CPT Progetti con campi ACF',
				'steps'             => [ 'Registra CPT', 'Aggiungi campi ACF' ],
				'estimated_credits' => 15,
			],
			'confirmation_required' => true,
		];
		$phase = $this->detector->detect_phase( $ai_proposal );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $phase['phase'] );

		// Step 5: User confirms
		$user_confirm = 'Procedi';
		$input_class = $this->detector->classify_user_input( $user_confirm, PhaseDetector::PHASE_PROPOSAL );
		$this->assertEquals( PhaseDetector::PHASE_EXECUTION, $input_class['next_phase'] );

		// Step 6: AI executes (Execution)
		$ai_execution = [
			'phase'        => 'execution',
			'message'      => 'Ho creato il CPT!',
			'code'         => [
				'type'    => 'wpcode_snippet',
				'content' => '<?php register_post_type("progetti", [...])',
			],
			'verification' => [
				'success' => true,
				'checks'  => [
					[ 'name' => 'CPT registrato', 'passed' => true ],
				],
			],
		];
		$phase = $this->detector->detect_phase( $ai_execution );
		$this->assertEquals( PhaseDetector::PHASE_EXECUTION, $phase['phase'] );
		$this->assertTrue( $phase['is_final'] );
	}

	/**
	 * Test flow with user rejection and restart
	 */
	public function test_flow_with_rejection(): void {
		// AI proposes plan
		$ai_proposal = [
			'phase' => 'proposal',
			'plan'  => [ 'steps' => [ 'Step 1' ] ],
		];
		$phase = $this->detector->detect_phase( $ai_proposal );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $phase['phase'] );

		// User rejects
		$user_reject = 'No, annulla';
		$input_class = $this->detector->classify_user_input( $user_reject, PhaseDetector::PHASE_PROPOSAL );
		$this->assertEquals( PhaseDetector::INPUT_REJECTION, $input_class['type'] );
		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $input_class['next_phase'] );
	}

	/**
	 * Test flow with user modification request
	 */
	public function test_flow_with_modification(): void {
		// AI proposes plan
		$ai_proposal = [
			'phase' => 'proposal',
			'plan'  => [ 'steps' => [ 'Crea pagina blu' ] ],
		];
		$phase = $this->detector->detect_phase( $ai_proposal );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $phase['phase'] );

		// User requests modification
		$user_modify = 'Cambia il colore in rosso';
		$input_class = $this->detector->classify_user_input( $user_modify, PhaseDetector::PHASE_PROPOSAL );
		$this->assertEquals( PhaseDetector::INPUT_MODIFICATION, $input_class['type'] );
		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $input_class['next_phase'] );
	}

	// ========================
	// JSON PARSING TESTS
	// ========================

	/**
	 * Test detection from raw JSON string
	 */
	public function test_detect_from_json_string(): void {
		$json_response = '{"phase": "discovery", "questions": ["Domanda 1?"]}';

		$result = $this->detector->detect_phase( $json_response );

		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $result['phase'] );
	}

	/**
	 * Test detection from markdown code block JSON
	 */
	public function test_detect_from_markdown_json(): void {
		$markdown_response = <<<'JSON'
```json
{
    "phase": "proposal",
    "plan": {
        "steps": ["Step 1", "Step 2"]
    }
}
```
JSON;

		$result = $this->detector->detect_phase( $markdown_response );

		$this->assertEquals( PhaseDetector::PHASE_PROPOSAL, $result['phase'] );
	}

	/**
	 * Test detection from plain text (no JSON)
	 */
	public function test_detect_from_plain_text(): void {
		$plain_response = 'Certo! Posso aiutarti a creare una pagina. Cosa vuoi inserire nella pagina?';

		$result = $this->detector->detect_phase( $plain_response );

		// Should detect discovery due to question mark
		$this->assertEquals( PhaseDetector::PHASE_DISCOVERY, $result['phase'] );
	}
}
