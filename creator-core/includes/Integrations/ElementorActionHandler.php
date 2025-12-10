<?php
/**
 * Elementor Action Handler
 *
 * Handles Elementor page creation actions from AI responses.
 * Provides detection, extraction, and execution of page specifications.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ThinkingLogger;

/**
 * Class ElementorActionHandler
 *
 * Bridges AI responses with ElementorPageBuilder for automatic page creation.
 */
class ElementorActionHandler {

	/**
	 * Action type for Elementor page creation
	 */
	const ACTION_TYPE = 'create_elementor_page';

	/**
	 * Thinking logger instance
	 *
	 * @var ThinkingLogger|null
	 */
	private ?ThinkingLogger $logger;

	/**
	 * Constructor
	 *
	 * @param ThinkingLogger|null $logger Optional thinking logger.
	 */
	public function __construct( ?ThinkingLogger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Check if an AI response contains an Elementor page creation request
	 *
	 * Looks for:
	 * 1. An action with type 'create_elementor_page'
	 * 2. JSON with 'title' and 'sections' keys (implicit page spec)
	 *
	 * @param array $parsed_response Parsed AI response.
	 * @return bool
	 */
	public function should_create_page( array $parsed_response ): bool {
		// Check for explicit action type.
		if ( ! empty( $parsed_response['actions'] ) ) {
			foreach ( $parsed_response['actions'] as $action ) {
				if ( ( $action['type'] ?? '' ) === self::ACTION_TYPE ) {
					return true;
				}
			}
		}

		// Check for implicit page spec in response (has elementor_page key).
		if ( ! empty( $parsed_response['elementor_page'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Extract page specification from AI response
	 *
	 * Supports multiple formats:
	 * - Action with type 'create_elementor_page' and spec in params
	 * - Direct 'elementor_page' key in response
	 * - JSON embedded in response text
	 *
	 * @param array $parsed_response Parsed AI response.
	 * @return array|null Page specification or null if not found.
	 */
	public function extract_specification( array $parsed_response ): ?array {
		// Extract from action.
		if ( ! empty( $parsed_response['actions'] ) ) {
			foreach ( $parsed_response['actions'] as $action ) {
				if ( ( $action['type'] ?? '' ) === self::ACTION_TYPE ) {
					$spec = $action['params'] ?? $action['spec'] ?? $action['page'] ?? null;
					if ( $spec && $this->is_valid_spec( $spec ) ) {
						return $spec;
					}
				}
			}
		}

		// Extract from elementor_page key.
		if ( ! empty( $parsed_response['elementor_page'] ) ) {
			$spec = $parsed_response['elementor_page'];
			if ( $this->is_valid_spec( $spec ) ) {
				return $spec;
			}
		}

		// Try to extract from message text (fallback for non-structured responses).
		if ( ! empty( $parsed_response['message'] ) ) {
			$spec = $this->extract_spec_from_text( $parsed_response['message'] );
			if ( $spec && $this->is_valid_spec( $spec ) ) {
				return $spec;
			}
		}

		return null;
	}

	/**
	 * Execute page creation from specification
	 *
	 * @param array $spec Page specification.
	 * @return array Result with success status, page_id, url, etc.
	 */
	public function execute( array $spec ): array {
		$this->log( 'Executing Elementor page creation...', 'info' );

		try {
			// Check Elementor availability.
			if ( ! class_exists( '\Elementor\Plugin' ) ) {
				$this->log( 'Elementor not available', 'error' );
				return [
					'success' => false,
					'error'   => 'Elementor is not installed or activated',
				];
			}

			// Create builder instance.
			$builder = new ElementorPageBuilder( $this->logger );

			// Generate page.
			$result = $builder->generate_page_from_freeform_spec( $spec );

			$this->log( 'Page created successfully: ' . $result['url'], 'success' );

			return [
				'success'     => true,
				'page_id'     => $result['page_id'],
				'url'         => $result['url'],
				'edit_url'    => $result['edit_url'],
				'snapshot_id' => $result['snapshot_id'] ?? null,
				'fallbacks'   => [
					'count' => $builder->get_fallback_count(),
					'types' => $builder->get_unknown_widget_types(),
				],
			];
		} catch ( \Exception $e ) {
			$this->log( 'Page creation failed: ' . $e->getMessage(), 'error' );

			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Handle Elementor action from AI response
	 *
	 * Main entry point for ChatInterface integration.
	 *
	 * @param array $parsed_response Parsed AI response.
	 * @return array|null Result if page was created, null if not applicable.
	 */
	public function handle_response( array $parsed_response ): ?array {
		if ( ! $this->should_create_page( $parsed_response ) ) {
			return null;
		}

		$spec = $this->extract_specification( $parsed_response );
		if ( ! $spec ) {
			$this->log( 'Could not extract valid page specification', 'warning' );
			return [
				'success' => false,
				'error'   => 'Could not extract valid page specification from response',
			];
		}

		return $this->execute( $spec );
	}

	/**
	 * Check if a specification is valid (has required fields)
	 *
	 * @param mixed $spec Specification to validate.
	 * @return bool
	 */
	private function is_valid_spec( $spec ): bool {
		if ( ! is_array( $spec ) ) {
			return false;
		}

		// Must have title.
		if ( empty( $spec['title'] ) ) {
			return false;
		}

		// Must have sections, layout, or content.
		$has_content = ! empty( $spec['sections'] ) ||
		               ! empty( $spec['layout'] ) ||
		               ! empty( $spec['content'] );

		return $has_content;
	}

	/**
	 * Extract page specification from text (JSON embedded in message)
	 *
	 * @param string $text Message text.
	 * @return array|null Extracted specification or null.
	 */
	private function extract_spec_from_text( string $text ): ?array {
		// Look for JSON in code blocks.
		if ( preg_match( '/```json\s*([\s\S]*?)\s*```/', $text, $matches ) ) {
			$json = json_decode( $matches[1], true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
				return $json;
			}
		}

		// Look for JSON object with title and sections.
		if ( preg_match( '/\{[^{}]*"title"[^{}]*"sections"[^{}]*\}/s', $text, $matches ) ) {
			// This is a simple pattern - might need recursive matching for nested objects.
			$json = json_decode( $matches[0], true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
				return $json;
			}
		}

		return null;
	}

	/**
	 * Log message
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level.
	 */
	private function log( string $message, string $level = 'info' ): void {
		if ( $this->logger ) {
			$this->logger->log( $message, $level );
		}
	}

	/**
	 * Get supported action types for this handler
	 *
	 * @return array
	 */
	public static function get_supported_action_types(): array {
		return [ self::ACTION_TYPE ];
	}
}
