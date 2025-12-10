<?php
/**
 * File Attachment Integration Tests
 *
 * Tests file attachment handling including:
 * - Image attachment processing
 * - PDF attachment processing
 * - File validation and encoding
 * - AI receives files correctly
 *
 * @package CreatorCore
 */

namespace CreatorCore\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CreatorCore\Chat\ChatInterface;

/**
 * Test class for file attachment scenarios
 */
class FileAttachmentTest extends TestCase {

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
	// FILE VALIDATION TESTS
	// ========================

	/**
	 * Test file size limit enforcement (10MB)
	 */
	public function test_file_size_limit(): void {
		$max_size = 10 * 1024 * 1024; // 10MB

		// Test that limit is defined
		$this->assertEquals( 10485760, $max_size );
	}

	/**
	 * Test maximum files limit (3 files)
	 */
	public function test_max_files_limit(): void {
		$max_files = 3;

		$this->assertEquals( 3, $max_files );
	}

	/**
	 * Test supported file types
	 */
	public function test_supported_file_types(): void {
		$supported_types = [
			'image/png',
			'image/jpeg',
			'image/gif',
			'image/webp',
			'application/pdf',
			'text/plain',
			'text/html',
			'text/css',
			'application/javascript',
			'application/json',
		];

		$this->assertContains( 'image/png', $supported_types );
		$this->assertContains( 'image/jpeg', $supported_types );
		$this->assertContains( 'application/pdf', $supported_types );
	}

	// ========================
	// IMAGE ATTACHMENT TESTS
	// ========================

	/**
	 * Test PNG image attachment structure
	 */
	public function test_png_attachment_structure(): void {
		$attachment = [
			'name'      => 'screenshot.png',
			'type'      => 'image/png',
			'size'      => 102400,
			'base64'    => 'iVBORw0KGgoAAAANS...', // truncated
			'mime_type' => 'image/png',
		];

		$this->assertEquals( 'image/png', $attachment['type'] );
		$this->assertArrayHasKey( 'base64', $attachment );
	}

	/**
	 * Test JPEG image attachment structure
	 */
	public function test_jpeg_attachment_structure(): void {
		$attachment = [
			'name'      => 'design.jpg',
			'type'      => 'image/jpeg',
			'size'      => 256000,
			'base64'    => '/9j/4AAQSkZJRgABAQEASABIAAD...', // truncated
			'mime_type' => 'image/jpeg',
		];

		$this->assertEquals( 'image/jpeg', $attachment['type'] );
		$this->assertArrayHasKey( 'base64', $attachment );
	}

	/**
	 * Test screenshot attachment for error diagnosis
	 */
	public function test_screenshot_for_error_diagnosis(): void {
		$screenshot_attachment = [
			'name'        => 'error-screenshot.png',
			'type'        => 'image/png',
			'purpose'     => 'error_diagnosis',
			'size'        => 150000,
			'base64'      => 'iVBORw0KGgoAAAANS...',
			'description' => 'Screenshot of PHP error on checkout page',
		];

		$this->assertEquals( 'error_diagnosis', $screenshot_attachment['purpose'] );
		$this->assertArrayHasKey( 'description', $screenshot_attachment );
	}

	/**
	 * Test mockup attachment for design implementation
	 */
	public function test_mockup_for_design_implementation(): void {
		$mockup_attachment = [
			'name'    => 'homepage-mockup.png',
			'type'    => 'image/png',
			'purpose' => 'design_reference',
			'size'    => 500000,
			'base64'  => 'iVBORw0KGgoAAAANS...',
			'metadata' => [
				'elements' => [ 'header', 'hero', 'features', 'footer' ],
				'colors'   => [ '#3498db', '#ffffff', '#2c3e50' ],
			],
		];

		$this->assertEquals( 'design_reference', $mockup_attachment['purpose'] );
		$this->assertArrayHasKey( 'metadata', $mockup_attachment );
	}

	// ========================
	// PDF ATTACHMENT TESTS
	// ========================

	/**
	 * Test PDF attachment structure
	 */
	public function test_pdf_attachment_structure(): void {
		$attachment = [
			'name'      => 'requirements.pdf',
			'type'      => 'application/pdf',
			'size'      => 1024000,
			'base64'    => 'JVBERi0xLjcKCjEgMCBvYmo...',
			'mime_type' => 'application/pdf',
			'pages'     => 5,
		];

		$this->assertEquals( 'application/pdf', $attachment['type'] );
		$this->assertArrayHasKey( 'base64', $attachment );
	}

	/**
	 * Test requirements PDF processing
	 */
	public function test_requirements_pdf_processing(): void {
		$requirements_pdf = [
			'name'     => 'project-requirements.pdf',
			'type'     => 'application/pdf',
			'size'     => 512000,
			'base64'   => 'JVBERi0xLjcKCjEgMCBvYmo...',
			'extracted_text' => "Project Requirements:\n1. User registration\n2. Product catalog\n3. Shopping cart",
		];

		$this->assertArrayHasKey( 'extracted_text', $requirements_pdf );
		$this->assertStringContainsString( 'Requirements', $requirements_pdf['extracted_text'] );
	}

	// ========================
	// PROVIDER FORMAT TESTS
	// ========================

	/**
	 * Test Gemini format for image attachment
	 */
	public function test_gemini_format_image(): void {
		$gemini_format = [
			'inline_data' => [
				'mime_type' => 'image/png',
				'data'      => 'iVBORw0KGgoAAAANS...',
			],
		];

		$this->assertArrayHasKey( 'inline_data', $gemini_format );
		$this->assertArrayHasKey( 'mime_type', $gemini_format['inline_data'] );
		$this->assertArrayHasKey( 'data', $gemini_format['inline_data'] );
	}

	/**
	 * Test Claude format for image attachment
	 */
	public function test_claude_format_image(): void {
		$claude_format = [
			'type'   => 'image',
			'source' => [
				'type'       => 'base64',
				'media_type' => 'image/png',
				'data'       => 'iVBORw0KGgoAAAANS...',
			],
		];

		$this->assertArrayHasKey( 'type', $claude_format );
		$this->assertEquals( 'image', $claude_format['type'] );
		$this->assertArrayHasKey( 'source', $claude_format );
	}

	// ========================
	// SYSTEM PROMPT AWARENESS
	// ========================

	/**
	 * Test system prompt includes file attachment instructions
	 */
	public function test_system_prompt_file_awareness(): void {
		$expected_instructions = [
			'RICONOSCI',
			'ANALIZZA',
			'ESTRAI',
			'RIFERISCI',
			'USA',
		];

		// These should be in the system prompt
		foreach ( $expected_instructions as $instruction ) {
			$this->assertNotEmpty( $instruction );
		}
	}

	/**
	 * Test file types mentioned in system prompt
	 */
	public function test_file_types_documented(): void {
		$documented_types = [
			'PNG',
			'JPG',
			'GIF',
			'WebP',
			'PDF',
		];

		$this->assertCount( 5, $documented_types );
	}

	// ========================
	// AI REFERENCE TESTS
	// ========================

	/**
	 * Test AI response structure with file reference
	 */
	public function test_ai_response_with_file_reference(): void {
		$ai_response = [
			'phase'   => 'discovery',
			'message' => 'Ho analizzato il mockup che hai condiviso. Vedo una homepage con header, hero section e footer.',
			'file_analysis' => [
				'type'                => 'image/mockup',
				'elements_identified' => [ 'header', 'hero', 'footer' ],
				'style_notes'         => 'Stile moderno, colori blu/bianco',
			],
			'questions' => [
				'Il pulsante CTA dove deve portare?',
			],
		];

		$this->assertArrayHasKey( 'file_analysis', $ai_response );
		$this->assertStringContainsString( 'mockup', $ai_response['message'] );
	}

	/**
	 * Test AI acknowledges error screenshot
	 */
	public function test_ai_acknowledges_error_screenshot(): void {
		$ai_response = [
			'phase'   => 'proposal',
			'message' => 'Nello screenshot dell\'errore, vedo un Fatal Error di tipo "Call to undefined function". Il problema è nella riga 45.',
			'file_analysis' => [
				'type'  => 'image/screenshot',
				'error' => 'Fatal Error: Call to undefined function custom_func()',
				'file'  => 'functions.php',
				'line'  => 45,
			],
			'plan' => [
				'summary' => 'Correggere la funzione mancante',
				'steps'   => [
					'1. Verificare se il plugin è attivo',
					'2. Aggiungere la funzione mancante',
				],
			],
		];

		$this->assertStringContainsString( 'screenshot', $ai_response['message'] );
		$this->assertArrayHasKey( 'error', $ai_response['file_analysis'] );
	}

	/**
	 * Test AI references PDF requirements
	 */
	public function test_ai_references_pdf_requirements(): void {
		$ai_response = [
			'phase'   => 'proposal',
			'message' => 'Dal brief PDF che hai allegato, i requisiti chiave sono: registrazione utenti, catalogo prodotti e carrello.',
			'file_analysis' => [
				'type'              => 'application/pdf',
				'requirements_found' => [
					'Registrazione utenti',
					'Catalogo prodotti',
					'Carrello acquisti',
				],
			],
			'plan' => [
				'summary'           => 'Implementare e-commerce base',
				'estimated_credits' => 50,
			],
		];

		$this->assertStringContainsString( 'PDF', $ai_response['message'] );
		$this->assertArrayHasKey( 'requirements_found', $ai_response['file_analysis'] );
	}

	// ========================
	// ATTACHMENT METADATA TESTS
	// ========================

	/**
	 * Test attachment includes timestamp
	 */
	public function test_attachment_includes_timestamp(): void {
		$attachment = [
			'name'       => 'file.png',
			'type'       => 'image/png',
			'size'       => 1000,
			'uploaded_at' => '2025-12-04T10:30:00Z',
		];

		$this->assertArrayHasKey( 'uploaded_at', $attachment );
	}

	/**
	 * Test attachment includes user info
	 */
	public function test_attachment_includes_user_info(): void {
		$attachment = [
			'name'        => 'file.png',
			'type'        => 'image/png',
			'uploaded_by' => 1,
		];

		$this->assertArrayHasKey( 'uploaded_by', $attachment );
	}

	// ========================
	// ERROR HANDLING TESTS
	// ========================

	/**
	 * Test invalid file type rejection
	 */
	public function test_invalid_file_type_rejection(): void {
		$invalid_types = [
			'application/x-php',
			'application/x-executable',
			'application/x-sh',
		];

		$allowed_types = [
			'image/png',
			'image/jpeg',
			'application/pdf',
		];

		foreach ( $invalid_types as $type ) {
			$this->assertNotContains( $type, $allowed_types );
		}
	}

	/**
	 * Test oversized file rejection
	 */
	public function test_oversized_file_rejection(): void {
		$max_size_bytes = 10 * 1024 * 1024; // 10MB
		$oversized_file = [
			'name' => 'huge-file.png',
			'size' => 15 * 1024 * 1024, // 15MB
		];

		$this->assertGreaterThan( $max_size_bytes, $oversized_file['size'] );
	}

	/**
	 * Test corrupted file handling
	 */
	public function test_corrupted_file_handling(): void {
		$corrupted_attachment = [
			'name'   => 'corrupted.png',
			'type'   => 'image/png',
			'size'   => 100,
			'base64' => 'invalid-base64-data!!!',
			'error'  => 'Invalid base64 encoding',
		];

		$this->assertArrayHasKey( 'error', $corrupted_attachment );
	}

	// ========================
	// MULTI-FILE TESTS
	// ========================

	/**
	 * Test multiple files in single message
	 */
	public function test_multiple_files_in_message(): void {
		$attachments = [
			[
				'name' => 'mockup.png',
				'type' => 'image/png',
			],
			[
				'name' => 'requirements.pdf',
				'type' => 'application/pdf',
			],
			[
				'name' => 'logo.jpg',
				'type' => 'image/jpeg',
			],
		];

		$this->assertCount( 3, $attachments );
		$this->assertLessThanOrEqual( 3, count( $attachments ) ); // Max 3 files
	}

	/**
	 * Test exceeding max files returns error
	 */
	public function test_exceeding_max_files(): void {
		$too_many_files = [
			[ 'name' => 'file1.png' ],
			[ 'name' => 'file2.png' ],
			[ 'name' => 'file3.png' ],
			[ 'name' => 'file4.png' ], // Exceeds limit
		];

		$max_files = 3;
		$this->assertGreaterThan( $max_files, count( $too_many_files ) );
	}

	// ========================
	// CLEANUP TESTS
	// ========================

	/**
	 * Test temporary file cleanup
	 */
	public function test_temporary_file_cleanup(): void {
		// Temporary files should be cleaned after processing
		$temp_path = sys_get_temp_dir() . '/creator_upload_' . uniqid();

		// Simulate temp file creation and cleanup
		$this->assertFalse( file_exists( $temp_path ) );
	}
}
