<?php
/**
 * ChatInterface Unit Tests
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CreatorCore\Chat\ChatInterface;

/**
 * Test class for ChatInterface
 */
class ChatInterfaceTest extends TestCase {

    /**
     * ChatInterface instance
     *
     * @var ChatInterface
     */
    private $chat;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->chat = new ChatInterface();
    }

    /**
     * Test constructor initializes correctly
     */
    public function test_constructor(): void {
        $this->assertInstanceOf( ChatInterface::class, $this->chat );
    }

    /**
     * Test create_chat returns chat ID
     */
    public function test_create_chat(): void {
        $chat_id = $this->chat->create_chat( 'Test Chat' );

        $this->assertIsInt( $chat_id );
        $this->assertGreaterThan( 0, $chat_id );
    }

    /**
     * Test create_chat with empty title
     */
    public function test_create_chat_empty_title(): void {
        $chat_id = $this->chat->create_chat( '' );

        // Should still create chat with default title
        $this->assertIsInt( $chat_id );
    }

    /**
     * Test get_chat returns chat data
     */
    public function test_get_chat(): void {
        $chat_id = $this->chat->create_chat( 'Test Chat' );
        $chat = $this->chat->get_chat( $chat_id );

        $this->assertIsArray( $chat );
        $this->assertArrayHasKey( 'id', $chat );
        $this->assertArrayHasKey( 'title', $chat );
    }

    /**
     * Test get_chat returns null for invalid ID
     */
    public function test_get_chat_invalid_id(): void {
        $chat = $this->chat->get_chat( 999999 );
        $this->assertNull( $chat );
    }

    /**
     * Test update_chat_title
     */
    public function test_update_chat_title(): void {
        $chat_id = $this->chat->create_chat( 'Original Title' );
        $result = $this->chat->update_chat_title( $chat_id, 'Updated Title' );

        $this->assertTrue( $result );
    }

    /**
     * Test delete_chat
     */
    public function test_delete_chat(): void {
        $chat_id = $this->chat->create_chat( 'Test Chat' );
        $result = $this->chat->delete_chat( $chat_id );

        $this->assertTrue( $result );
    }

    /**
     * Test get_user_chats returns array
     */
    public function test_get_user_chats(): void {
        $chats = $this->chat->get_user_chats();

        $this->assertIsArray( $chats );
    }

    /**
     * Test get_recent_chats returns array
     */
    public function test_get_recent_chats(): void {
        $chats = $this->chat->get_recent_chats( 5 );

        $this->assertIsArray( $chats );
    }

    /**
     * Test add_message to chat
     */
    public function test_add_message(): void {
        $chat_id = $this->chat->create_chat( 'Test Chat' );
        $message_id = $this->chat->add_message( $chat_id, 'user', 'Test message' );

        $this->assertIsInt( $message_id );
        $this->assertGreaterThan( 0, $message_id );
    }

    /**
     * Test add_message with different roles
     */
    public function test_add_message_roles(): void {
        $chat_id = $this->chat->create_chat( 'Test Chat' );

        $user_message = $this->chat->add_message( $chat_id, 'user', 'User message' );
        $assistant_message = $this->chat->add_message( $chat_id, 'assistant', 'AI response' );

        $this->assertIsInt( $user_message );
        $this->assertIsInt( $assistant_message );
    }

    /**
     * Test get_messages returns array
     */
    public function test_get_messages(): void {
        $chat_id = $this->chat->create_chat( 'Test Chat' );
        $this->chat->add_message( $chat_id, 'user', 'Test message' );

        $messages = $this->chat->get_messages( $chat_id );

        $this->assertIsArray( $messages );
    }

    /**
     * Test get_chat_stats returns stats
     */
    public function test_get_chat_stats(): void {
        $stats = $this->chat->get_chat_stats();

        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_chats', $stats );
        $this->assertArrayHasKey( 'total_messages', $stats );
    }
}
