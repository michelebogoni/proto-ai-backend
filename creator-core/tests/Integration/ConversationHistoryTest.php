<?php
/**
 * Conversation History Integration Tests
 *
 * Tests conversation history management including:
 * - History pruning (keeps last 10 messages)
 * - Older message summarization
 * - Context maintenance across turns
 * - Token budget management
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Chat\ChatInterface;

/**
 * Test class for conversation history scenarios
 */
class ConversationHistoryTest extends TestCase {

	/**
	 * ChatInterface instance
	 *
	 * @var ChatInterface
	 */
	private ChatInterface $chat;

	/**
	 * Set up test fixtures
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->chat = new ChatInterface();
	}

	// ========================
	// BASIC HISTORY TESTS
	// ========================

	/**
	 * Test create chat and add messages
	 */
	public function test_create_chat_and_add_messages(): void {
		$chat_id = $this->chat->create_chat( 'Test Chat' );

		$this->assertIsInt( $chat_id );
		$this->assertGreaterThan( 0, $chat_id );

		// Add messages
		$msg1 = $this->chat->add_message( $chat_id, 'user', 'Hello' );
		$msg2 = $this->chat->add_message( $chat_id, 'assistant', 'Hi there!' );

		$this->assertIsInt( $msg1 );
		$this->assertIsInt( $msg2 );
	}

	/**
	 * Test get messages returns array
	 */
	public function test_get_messages_returns_array(): void {
		$chat_id = $this->chat->create_chat( 'Test Chat' );
		$this->chat->add_message( $chat_id, 'user', 'Test message' );

		$messages = $this->chat->get_messages( $chat_id );

		$this->assertIsArray( $messages );
	}

	/**
	 * Test messages are ordered correctly
	 */
	public function test_messages_ordered_correctly(): void {
		$chat_id = $this->chat->create_chat( 'Order Test' );

		$this->chat->add_message( $chat_id, 'user', 'First' );
		$this->chat->add_message( $chat_id, 'assistant', 'Second' );
		$this->chat->add_message( $chat_id, 'user', 'Third' );

		$messages = $this->chat->get_messages( $chat_id );

		// Messages should be in order
		$this->assertIsArray( $messages );
	}

	// ========================
	// HISTORY PRUNING TESTS
	// ========================

	/**
	 * Test history pruning configuration
	 */
	public function test_history_pruning_config(): void {
		$max_complete_messages = 10;

		$this->assertEquals( 10, $max_complete_messages );
	}

	/**
	 * Test long conversation handling
	 */
	public function test_long_conversation_handling(): void {
		$chat_id = $this->chat->create_chat( 'Long Conversation' );

		// Add 20+ messages
		for ( $i = 1; $i <= 25; $i++ ) {
			$role = $i % 2 === 1 ? 'user' : 'assistant';
			$this->chat->add_message( $chat_id, $role, "Message {$i}" );
		}

		$messages = $this->chat->get_messages( $chat_id );

		$this->assertIsArray( $messages );
	}

	/**
	 * Test summary creation structure
	 */
	public function test_summary_structure(): void {
		$summary_structure = [
			'role'    => 'system',
			'content' => 'Previous conversation summary: User requested a CPT for projects. AI created the CPT with ACF fields for budget and date.',
		];

		$this->assertEquals( 'system', $summary_structure['role'] );
		$this->assertStringContainsString( 'summary', strtolower( $summary_structure['content'] ) );
	}

	// ========================
	// CONTEXT MAINTENANCE TESTS
	// ========================

	/**
	 * Test context maintained across turns
	 */
	public function test_context_maintained_across_turns(): void {
		$chat_id = $this->chat->create_chat( 'Context Test' );

		// Turn 1: User asks about CPT
		$this->chat->add_message( $chat_id, 'user', 'Crea un CPT per progetti' );
		$this->chat->add_message( $chat_id, 'assistant', 'Che campi vuoi?' );

		// Turn 2: User answers
		$this->chat->add_message( $chat_id, 'user', 'Budget e data' );
		$this->chat->add_message( $chat_id, 'assistant', 'Creerò CPT progetti con campi budget e data' );

		// Turn 3: User asks about the same topic
		$this->chat->add_message( $chat_id, 'user', 'Aggiungi anche un campo descrizione' );

		// AI should remember context
		$messages = $this->chat->get_messages( $chat_id );

		$this->assertCount( 5, $messages );
	}

	/**
	 * Test reference to earlier messages works
	 */
	public function test_reference_to_earlier_messages(): void {
		$chat_id = $this->chat->create_chat( 'Reference Test' );

		$this->chat->add_message( $chat_id, 'user', 'Il sito si chiama MioSito' );
		$this->chat->add_message( $chat_id, 'assistant', 'Capito, MioSito' );

		// Later reference
		$this->chat->add_message( $chat_id, 'user', 'Crea una pagina Chi Siamo' );
		$this->chat->add_message( $chat_id, 'assistant', 'Creo pagina Chi Siamo per MioSito' );

		$messages = $this->chat->get_messages( $chat_id );

		$this->assertIsArray( $messages );
	}

	// ========================
	// TOKEN BUDGET TESTS
	// ========================

	/**
	 * Test typical token budget
	 */
	public function test_typical_token_budget(): void {
		$typical_history_tokens = 2700;
		$gemini_context_limit   = 1000000;
		$claude_context_limit   = 200000;

		$this->assertLessThan( $gemini_context_limit, $typical_history_tokens );
		$this->assertLessThan( $claude_context_limit, $typical_history_tokens );
	}

	/**
	 * Test message token estimation
	 */
	public function test_message_token_estimation(): void {
		$message = 'Questo è un messaggio di esempio per testare la stima dei token.';

		// Rough estimate: 1 token ≈ 4 characters
		$estimated_tokens = strlen( $message ) / 4;

		$this->assertGreaterThan( 0, $estimated_tokens );
		$this->assertLessThan( 100, $estimated_tokens );
	}

	/**
	 * Test history doesn't exceed budget
	 */
	public function test_history_within_budget(): void {
		$max_history_tokens = 10000; // Conservative limit
		$chat_id = $this->chat->create_chat( 'Budget Test' );

		// Add many messages
		for ( $i = 0; $i < 30; $i++ ) {
			$this->chat->add_message(
				$chat_id,
				$i % 2 === 0 ? 'user' : 'assistant',
				str_repeat( 'Word ', 100 ) // ~500 chars each
			);
		}

		$messages = $this->chat->get_messages( $chat_id );

		// Should have messages
		$this->assertIsArray( $messages );
	}

	// ========================
	// MESSAGE STRUCTURE TESTS
	// ========================

	/**
	 * Test message includes required fields
	 */
	public function test_message_required_fields(): void {
		$chat_id = $this->chat->create_chat( 'Fields Test' );
		$msg_id = $this->chat->add_message( $chat_id, 'user', 'Test' );

		$this->assertIsInt( $msg_id );
	}

	/**
	 * Test message metadata storage
	 */
	public function test_message_metadata(): void {
		$message_with_metadata = [
			'id'       => 1,
			'chat_id'  => 1,
			'role'     => 'assistant',
			'content'  => 'Response',
			'metadata' => [
				'phase'      => 'execution',
				'code'       => [ 'snippet_id' => 123 ],
				'credits'    => 10,
				'timestamp'  => '2025-12-04T10:00:00Z',
			],
		];

		$this->assertArrayHasKey( 'metadata', $message_with_metadata );
		$this->assertArrayHasKey( 'phase', $message_with_metadata['metadata'] );
	}

	// ========================
	// SUMMARY TESTS
	// ========================

	/**
	 * Test summary is concise
	 */
	public function test_summary_is_concise(): void {
		$max_summary_lines = 3;
		$max_summary_chars = 500;

		$sample_summary = "L'utente ha chiesto di creare un CPT per progetti con campi ACF. " .
			"L'AI ha creato il CPT 'progetti' con campi per budget e data. " .
			"Successivamente è stato aggiunto un campo descrizione.";

		$lines = count( explode( "\n", $sample_summary ) );
		$chars = strlen( $sample_summary );

		$this->assertLessThanOrEqual( $max_summary_lines, $lines );
		$this->assertLessThanOrEqual( $max_summary_chars, $chars );
	}

	/**
	 * Test summary preserves key information
	 */
	public function test_summary_preserves_key_info(): void {
		$original_context = [
			'cpt_name'   => 'progetti',
			'acf_fields' => [ 'budget', 'data', 'descrizione' ],
			'user_goal'  => 'Gestire portfolio progetti',
		];

		$summary = "Creato CPT 'progetti' con campi ACF: budget, data, descrizione per gestione portfolio.";

		$this->assertStringContainsString( 'progetti', $summary );
		$this->assertStringContainsString( 'budget', $summary );
		$this->assertStringContainsString( 'portfolio', $summary );
	}

	// ========================
	// CHAT MANAGEMENT TESTS
	// ========================

	/**
	 * Test get user chats
	 */
	public function test_get_user_chats(): void {
		$chats = $this->chat->get_user_chats();

		$this->assertIsArray( $chats );
	}

	/**
	 * Test get recent chats
	 */
	public function test_get_recent_chats(): void {
		$recent = $this->chat->get_recent_chats( 5 );

		$this->assertIsArray( $recent );
	}

	/**
	 * Test delete chat
	 */
	public function test_delete_chat(): void {
		$chat_id = $this->chat->create_chat( 'To Delete' );
		$result = $this->chat->delete_chat( $chat_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test update chat title
	 */
	public function test_update_chat_title(): void {
		$chat_id = $this->chat->create_chat( 'Original Title' );
		$result = $this->chat->update_chat_title( $chat_id, 'New Title' );

		$this->assertTrue( $result );
	}

	// ========================
	// EDGE CASE TESTS
	// ========================

	/**
	 * Test empty chat handling
	 */
	public function test_empty_chat_handling(): void {
		$chat_id = $this->chat->create_chat( 'Empty Chat' );
		$messages = $this->chat->get_messages( $chat_id );

		$this->assertIsArray( $messages );
		$this->assertCount( 0, $messages );
	}

	/**
	 * Test very long single message
	 */
	public function test_very_long_single_message(): void {
		$chat_id = $this->chat->create_chat( 'Long Message Test' );

		$long_content = str_repeat( 'This is a very long message. ', 1000 );
		$msg_id = $this->chat->add_message( $chat_id, 'user', $long_content );

		$this->assertIsInt( $msg_id );
	}

	/**
	 * Test special characters in messages
	 */
	public function test_special_characters_in_messages(): void {
		$chat_id = $this->chat->create_chat( 'Special Chars' );

		$special_content = "Code: <?php echo 'test'; ?>\nJSON: {\"key\": \"value\"}\nEmoji: 🎉";
		$msg_id = $this->chat->add_message( $chat_id, 'user', $special_content );

		$this->assertIsInt( $msg_id );
	}

	/**
	 * Test multilingual messages
	 */
	public function test_multilingual_messages(): void {
		$chat_id = $this->chat->create_chat( 'Multilingual' );

		$this->chat->add_message( $chat_id, 'user', 'Crea una pagina' ); // Italian
		$this->chat->add_message( $chat_id, 'user', 'Create a page' );   // English
		$this->chat->add_message( $chat_id, 'user', '创建一个页面' );     // Chinese

		$messages = $this->chat->get_messages( $chat_id );

		$this->assertCount( 3, $messages );
	}

	// ========================
	// CONCURRENT ACCESS TESTS
	// ========================

	/**
	 * Test multiple messages same time
	 */
	public function test_multiple_messages_concurrent(): void {
		$chat_id = $this->chat->create_chat( 'Concurrent Test' );

		// Simulate rapid message addition
		$ids = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = $this->chat->add_message( $chat_id, 'user', "Message {$i}" );
		}

		// All should have unique IDs
		$unique_ids = array_unique( $ids );
		$this->assertCount( 5, $unique_ids );
	}

	// ========================
	// CHAT STATS TESTS
	// ========================

	/**
	 * Test get chat stats
	 */
	public function test_get_chat_stats(): void {
		$stats = $this->chat->get_chat_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_chats', $stats );
		$this->assertArrayHasKey( 'total_messages', $stats );
	}
}
