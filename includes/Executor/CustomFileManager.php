<?php
/**
 * Custom File Manager
 *
 * Manages custom code files as fallback when WP Code is not available.
 * Handles PHP, CSS, and JavaScript code with a manifest-based tracking system.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class CustomFileManager
 *
 * Manages custom code files:
 * - codice-custom.php  (PHP functions, hooks)
 * - codice-custom.css  (CSS rules)
 * - codice-custom.js   (JavaScript)
 * - codice-manifest.json (registry of all modifications)
 */
class CustomFileManager {

	/**
	 * Code types
	 */
	public const TYPE_PHP  = 'php';
	public const TYPE_CSS  = 'css';
	public const TYPE_JS   = 'js';
	public const TYPE_HTML = 'html';

	/**
	 * File paths relative to plugin directory
	 */
	private const FILE_PHP      = 'custom-code/codice-custom.php';
	private const FILE_CSS      = 'custom-code/codice-custom.css';
	private const FILE_JS       = 'custom-code/codice-custom.js';
	private const FILE_MANIFEST = 'custom-code/codice-manifest.json';

	/**
	 * Audit logger instance
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $logger;

	/**
	 * Base directory for custom files
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Constructor
	 *
	 * @param AuditLogger|null $logger Audit logger instance.
	 */
	public function __construct( ?AuditLogger $logger = null ) {
		$this->logger   = $logger ?? new AuditLogger();
		$this->base_dir = CREATOR_CORE_PATH;
	}

	/**
	 * Write code to appropriate custom file
	 *
	 * @param string $code        Code to write.
	 * @param string $type        Code type (php, css, js).
	 * @param string $title       Title/description of the code block.
	 * @param string $description Optional description.
	 * @return array Result with success status and modification info.
	 */
	public function write_code( string $code, string $type, string $title, string $description = '' ): array {
		// Ensure custom-code directory exists
		$dir_result = $this->ensure_directory_exists();
		if ( ! $dir_result['success'] ) {
			return $dir_result;
		}

		// Get file path for this type
		$file_path = $this->get_file_path( $type );
		if ( ! $file_path ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Unsupported code type: %s', $type ),
			];
		}

		// Generate unique modification ID
		$mod_id = $this->generate_modification_id();

		// Prepare code block with markers
		$code_block = $this->wrap_code_block( $code, $type, $mod_id, $title, $description );

		// Initialize file if it doesn't exist
		$this->initialize_file_if_needed( $file_path, $type );

		// Read current file content
		$current_content = '';
		if ( file_exists( $file_path ) ) {
			$current_content = file_get_contents( $file_path );
		}

		// Find insertion point (before closing marker if exists)
		$new_content = $this->insert_code_block( $current_content, $code_block, $type );

		// Write to file
		$write_result = file_put_contents( $file_path, $new_content );
		if ( $write_result === false ) {
			return [
				'success' => false,
				'error'   => 'Failed to write to custom file',
			];
		}

		// Register in manifest
		$manifest_entry = $this->register_modification( [
			'id'          => $mod_id,
			'type'        => $type,
			'file'        => basename( $file_path ),
			'title'       => $title,
			'description' => $description,
			'code_hash'   => md5( $code ),
			'code_length' => strlen( $code ),
			'timestamp'   => current_time( 'c' ),
			'user_id'     => get_current_user_id(),
			'user_login'  => wp_get_current_user()->user_login,
		] );

		$this->logger->success( 'custom_code_written', [
			'mod_id' => $mod_id,
			'type'   => $type,
			'file'   => basename( $file_path ),
		] );

		return [
			'success'         => true,
			'modification_id' => $mod_id,
			'file'            => $file_path,
			'type'            => $type,
			'rollback_method' => sprintf( 'Remove modification %s from %s', $mod_id, basename( $file_path ) ),
			'manifest_entry'  => $manifest_entry,
		];
	}

	/**
	 * Remove a code modification by ID
	 *
	 * @param string $mod_id Modification ID to remove.
	 * @return array Result with success status.
	 */
	public function remove_modification( string $mod_id ): array {
		// Get modification info from manifest
		$manifest = $this->get_manifest();
		$mod_info = null;

		foreach ( $manifest['modifications'] as $mod ) {
			if ( $mod['id'] === $mod_id ) {
				$mod_info = $mod;
				break;
			}
		}

		if ( ! $mod_info ) {
			return [
				'success' => false,
				'error'   => sprintf( 'Modification %s not found in manifest', $mod_id ),
			];
		}

		// Get file path
		$file_path = $this->get_file_path( $mod_info['type'] );
		if ( ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'error'   => 'Custom code file not found',
			];
		}

		// Read current content
		$content = file_get_contents( $file_path );

		// Remove the code block with this modification ID
		$pattern = $this->get_block_pattern( $mod_id, $mod_info['type'] );
		$new_content = preg_replace( $pattern, '', $content );

		if ( $new_content === $content ) {
			return [
				'success' => false,
				'error'   => 'Could not find modification block in file',
			];
		}

		// Write back
		file_put_contents( $file_path, $new_content );

		// Remove from manifest
		$this->unregister_modification( $mod_id );

		$this->logger->success( 'custom_code_removed', [
			'mod_id' => $mod_id,
			'type'   => $mod_info['type'],
		] );

		return [
			'success' => true,
			'message' => sprintf( 'Modification %s removed successfully', $mod_id ),
		];
	}

	/**
	 * Detect code type from content
	 *
	 * @param string $code Code to analyze.
	 * @return string Detected type (php, css, js, html).
	 */
	public function detect_code_type( string $code ): string {
		$code = trim( $code );

		// Check for explicit PHP opening tag
		if ( preg_match( '/^<\?php/i', $code ) ) {
			return self::TYPE_PHP;
		}

		// Check for CSS patterns
		if ( preg_match( '/^\s*(@import|@media|@keyframes|@font-face|\*|body|html|\.[\w-]+|#[\w-]+|\[[\w-]+\])\s*\{/m', $code ) ) {
			return self::TYPE_CSS;
		}

		// Check for CSS property patterns (more permissive)
		if ( preg_match( '/\{\s*(color|background|margin|padding|font|display|position|width|height|border)\s*:/im', $code ) ) {
			return self::TYPE_CSS;
		}

		// Check for style tag
		if ( preg_match( '/^<style/i', $code ) ) {
			return self::TYPE_CSS;
		}

		// Check for JavaScript patterns
		if ( preg_match( '/^(const|let|var|function|class|import|export|document\.|window\.|jQuery|\$\(|addEventListener)/m', $code ) ) {
			return self::TYPE_JS;
		}

		// Check for arrow functions or modern JS
		if ( preg_match( '/=>\s*\{|=>\s*[^{]/', $code ) ) {
			return self::TYPE_JS;
		}

		// Check for script tag
		if ( preg_match( '/^<script/i', $code ) ) {
			return self::TYPE_JS;
		}

		// Check for HTML patterns
		if ( preg_match( '/^<!DOCTYPE|^<html|^<head|^<body|^<div|^<section|^<header|^<footer/im', $code ) ) {
			return self::TYPE_HTML;
		}

		// Check for PHP functions/patterns (without opening tag)
		if ( preg_match( '/\b(function\s+\w+|add_action|add_filter|register_post_type|wp_insert_post|get_option|update_option)\s*\(/i', $code ) ) {
			return self::TYPE_PHP;
		}

		// Default to PHP for WordPress context
		return self::TYPE_PHP;
	}

	/**
	 * Get all modifications from manifest
	 *
	 * @return array Manifest data.
	 */
	public function get_manifest(): array {
		$manifest_path = $this->base_dir . self::FILE_MANIFEST;

		if ( ! file_exists( $manifest_path ) ) {
			return [
				'version'       => '1.0',
				'created_at'    => current_time( 'c' ),
				'modifications' => [],
			];
		}

		$content = file_get_contents( $manifest_path );
		$manifest = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $manifest ) ) {
			return [
				'version'       => '1.0',
				'created_at'    => current_time( 'c' ),
				'modifications' => [],
			];
		}

		return $manifest;
	}

	/**
	 * Get modifications by type
	 *
	 * @param string $type Code type to filter by.
	 * @return array Filtered modifications.
	 */
	public function get_modifications_by_type( string $type ): array {
		$manifest = $this->get_manifest();

		return array_filter( $manifest['modifications'], function( $mod ) use ( $type ) {
			return $mod['type'] === $type;
		} );
	}

	/**
	 * Check if custom files system is initialized
	 *
	 * @return bool
	 */
	public function is_initialized(): bool {
		$dir = $this->base_dir . 'custom-code';
		return is_dir( $dir ) && is_writable( $dir );
	}

	/**
	 * Initialize the custom files system
	 *
	 * @return array Result with success status.
	 */
	public function initialize(): array {
		$result = $this->ensure_directory_exists();
		if ( ! $result['success'] ) {
			return $result;
		}

		// Create initial files
		$this->initialize_file_if_needed( $this->get_file_path( self::TYPE_PHP ), self::TYPE_PHP );
		$this->initialize_file_if_needed( $this->get_file_path( self::TYPE_CSS ), self::TYPE_CSS );
		$this->initialize_file_if_needed( $this->get_file_path( self::TYPE_JS ), self::TYPE_JS );

		// Create manifest
		$this->save_manifest( [
			'version'       => '1.0',
			'created_at'    => current_time( 'c' ),
			'modifications' => [],
		] );

		return [
			'success' => true,
			'message' => 'Custom files system initialized',
		];
	}

	/**
	 * Get the file path for a code type
	 *
	 * @param string $type Code type.
	 * @return string|null File path or null if invalid type.
	 */
	private function get_file_path( string $type ): ?string {
		$paths = [
			self::TYPE_PHP => $this->base_dir . self::FILE_PHP,
			self::TYPE_CSS => $this->base_dir . self::FILE_CSS,
			self::TYPE_JS  => $this->base_dir . self::FILE_JS,
		];

		return $paths[ $type ] ?? null;
	}

	/**
	 * Ensure the custom-code directory exists
	 *
	 * @return array Result with success status.
	 */
	private function ensure_directory_exists(): array {
		$dir = $this->base_dir . 'custom-code';

		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return [
					'success' => false,
					'error'   => 'Failed to create custom-code directory',
				];
			}

			// Create .htaccess to protect directory (allow PHP execution)
			$htaccess = "# Creator Custom Code Directory\n";
			$htaccess .= "# Allow PHP execution\n";
			$htaccess .= "<FilesMatch \"\.php$\">\n";
			$htaccess .= "    SetHandler application/x-httpd-php\n";
			$htaccess .= "</FilesMatch>\n";
			file_put_contents( $dir . '/.htaccess', $htaccess );
			chmod( $dir . '/.htaccess', 0644 );

			// Create index.php for security
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
			chmod( $dir . '/index.php', 0644 );

			// Create .gitignore to exclude generated files from version control
			$gitignore = "# Creator Custom Code - Auto-generated files\n";
			$gitignore .= "# These files are managed by Creator and should not be version controlled\n";
			$gitignore .= "codice-custom.php\n";
			$gitignore .= "codice-custom.css\n";
			$gitignore .= "codice-custom.js\n";
			$gitignore .= "codice-manifest.json\n";
			file_put_contents( $dir . '/.gitignore', $gitignore );
			chmod( $dir . '/.gitignore', 0644 );
		}

		if ( ! is_writable( $dir ) ) {
			return [
				'success' => false,
				'error'   => 'Custom-code directory is not writable',
			];
		}

		return [ 'success' => true ];
	}

	/**
	 * Initialize a custom code file if it doesn't exist
	 *
	 * @param string $file_path File path.
	 * @param string $type      Code type.
	 * @return void
	 */
	private function initialize_file_if_needed( string $file_path, string $type ): void {
		if ( file_exists( $file_path ) ) {
			return;
		}

		$header = $this->get_file_header( $type );
		file_put_contents( $file_path, $header );
		chmod( $file_path, 0644 );
	}

	/**
	 * Get initial file header for a code type
	 *
	 * @param string $type Code type.
	 * @return string File header content.
	 */
	private function get_file_header( string $type ): string {
		$timestamp = current_time( 'c' );

		switch ( $type ) {
			case self::TYPE_PHP:
				return <<<PHP
<?php
/**
 * Creator Custom PHP Code
 *
 * This file contains PHP code generated by Creator AI.
 * Each code block is marked with a unique ID for tracking and rollback.
 *
 * WARNING: Do not manually edit the marker comments.
 *
 * @package CreatorCore
 * @since   1.0.0
 * @created {$timestamp}
 */

defined( 'ABSPATH' ) || exit;

// === CREATOR CODE BLOCKS START ===


// === CREATOR CODE BLOCKS END ===
PHP;

			case self::TYPE_CSS:
				return <<<CSS
/**
 * Creator Custom CSS
 *
 * This file contains CSS code generated by Creator AI.
 * Each code block is marked with a unique ID for tracking and rollback.
 *
 * WARNING: Do not manually edit the marker comments.
 *
 * @package CreatorCore
 * @since   1.0.0
 * @created {$timestamp}
 */

/* === CREATOR CODE BLOCKS START === */


/* === CREATOR CODE BLOCKS END === */
CSS;

			case self::TYPE_JS:
				return <<<JS
/**
 * Creator Custom JavaScript
 *
 * This file contains JavaScript code generated by Creator AI.
 * Each code block is marked with a unique ID for tracking and rollback.
 *
 * WARNING: Do not manually edit the marker comments.
 *
 * @package CreatorCore
 * @since   1.0.0
 * @created {$timestamp}
 */

(function() {
'use strict';

// === CREATOR CODE BLOCKS START ===


// === CREATOR CODE BLOCKS END ===

})();
JS;

			default:
				return '';
		}
	}

	/**
	 * Wrap code block with markers
	 *
	 * @param string $code        Code content.
	 * @param string $type        Code type.
	 * @param string $mod_id      Modification ID.
	 * @param string $title       Block title.
	 * @param string $description Block description.
	 * @return string Wrapped code block.
	 */
	private function wrap_code_block( string $code, string $type, string $mod_id, string $title, string $description ): string {
		$timestamp = current_time( 'c' );
		$user = wp_get_current_user()->user_login;

		// Clean the code
		$code = $this->clean_code( $code, $type );

		switch ( $type ) {
			case self::TYPE_PHP:
				return <<<PHP

// === CREATOR MODIFICATION [{$mod_id}] ===
// Title: {$title}
// Description: {$description}
// Created: {$timestamp}
// User: {$user}
{$code}
// === END CREATOR MODIFICATION [{$mod_id}] ===

PHP;

			case self::TYPE_CSS:
				return <<<CSS

/* === CREATOR MODIFICATION [{$mod_id}] ===
 * Title: {$title}
 * Description: {$description}
 * Created: {$timestamp}
 * User: {$user}
 */
{$code}
/* === END CREATOR MODIFICATION [{$mod_id}] === */

CSS;

			case self::TYPE_JS:
				return <<<JS

// === CREATOR MODIFICATION [{$mod_id}] ===
// Title: {$title}
// Description: {$description}
// Created: {$timestamp}
// User: {$user}
{$code}
// === END CREATOR MODIFICATION [{$mod_id}] ===

JS;

			default:
				return $code;
		}
	}

	/**
	 * Clean code before insertion
	 *
	 * @param string $code Code to clean.
	 * @param string $type Code type.
	 * @return string Cleaned code.
	 */
	private function clean_code( string $code, string $type ): string {
		$code = trim( $code );

		if ( $type === self::TYPE_PHP ) {
			// Remove opening/closing PHP tags
			$code = preg_replace( '/^<\?php\s*/i', '', $code );
			$code = preg_replace( '/\?>\s*$/', '', $code );
		}

		if ( $type === self::TYPE_CSS ) {
			// Remove style tags
			$code = preg_replace( '/^<style[^>]*>\s*/i', '', $code );
			$code = preg_replace( '/\s*<\/style>\s*$/i', '', $code );
		}

		if ( $type === self::TYPE_JS ) {
			// Remove script tags
			$code = preg_replace( '/^<script[^>]*>\s*/i', '', $code );
			$code = preg_replace( '/\s*<\/script>\s*$/i', '', $code );
		}

		return trim( $code );
	}

	/**
	 * Insert code block into file content
	 *
	 * @param string $content    Current file content.
	 * @param string $code_block Code block to insert.
	 * @param string $type       Code type.
	 * @return string Updated content.
	 */
	private function insert_code_block( string $content, string $code_block, string $type ): string {
		// Find the end marker and insert before it
		$end_marker = $this->get_end_marker( $type );

		if ( strpos( $content, $end_marker ) !== false ) {
			return str_replace( $end_marker, $code_block . $end_marker, $content );
		}

		// If no end marker, append to file
		return $content . "\n" . $code_block;
	}

	/**
	 * Get end marker for code type
	 *
	 * @param string $type Code type.
	 * @return string End marker.
	 */
	private function get_end_marker( string $type ): string {
		switch ( $type ) {
			case self::TYPE_PHP:
			case self::TYPE_JS:
				return '// === CREATOR CODE BLOCKS END ===';
			case self::TYPE_CSS:
				return '/* === CREATOR CODE BLOCKS END === */';
			default:
				return '';
		}
	}

	/**
	 * Get regex pattern to match a code block
	 *
	 * @param string $mod_id Modification ID.
	 * @param string $type   Code type.
	 * @return string Regex pattern.
	 */
	private function get_block_pattern( string $mod_id, string $type ): string {
		$escaped_id = preg_quote( $mod_id, '/' );

		switch ( $type ) {
			case self::TYPE_PHP:
			case self::TYPE_JS:
				return '/\n?\/\/ === CREATOR MODIFICATION \[' . $escaped_id . '\] ===.*?\/\/ === END CREATOR MODIFICATION \[' . $escaped_id . '\] ===\n?/s';
			case self::TYPE_CSS:
				return '/\n?\/\* === CREATOR MODIFICATION \[' . $escaped_id . '\] ===.*?\/\* === END CREATOR MODIFICATION \[' . $escaped_id . '\] === \*\/\n?/s';
			default:
				return '';
		}
	}

	/**
	 * Generate unique modification ID
	 *
	 * @return string Unique ID.
	 */
	private function generate_modification_id(): string {
		return 'mod_' . wp_generate_uuid4();
	}

	/**
	 * Register modification in manifest
	 *
	 * @param array $entry Modification entry.
	 * @return array The entry with added fields.
	 */
	private function register_modification( array $entry ): array {
		$manifest = $this->get_manifest();
		$manifest['modifications'][] = $entry;
		$manifest['updated_at'] = current_time( 'c' );

		$this->save_manifest( $manifest );

		return $entry;
	}

	/**
	 * Unregister modification from manifest
	 *
	 * @param string $mod_id Modification ID.
	 * @return bool Success status.
	 */
	private function unregister_modification( string $mod_id ): bool {
		$manifest = $this->get_manifest();

		$manifest['modifications'] = array_values( array_filter(
			$manifest['modifications'],
			function( $mod ) use ( $mod_id ) {
				return $mod['id'] !== $mod_id;
			}
		) );

		$manifest['updated_at'] = current_time( 'c' );

		return $this->save_manifest( $manifest );
	}

	/**
	 * Save manifest to file
	 *
	 * @param array $manifest Manifest data.
	 * @return bool Success status.
	 */
	private function save_manifest( array $manifest ): bool {
		$manifest_path = $this->base_dir . self::FILE_MANIFEST;

		$this->ensure_directory_exists();

		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		$result = file_put_contents( $manifest_path, $json );
		if ( $result !== false ) {
			chmod( $manifest_path, 0644 );
		}

		return $result !== false;
	}

	/**
	 * Get the URL for a custom code file (for enqueuing)
	 *
	 * @param string $type Code type.
	 * @return string|null URL or null if not exists.
	 */
	public function get_file_url( string $type ): ?string {
		$file_path = $this->get_file_path( $type );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		$relative = str_replace( CREATOR_CORE_PATH, '', $file_path );
		return CREATOR_CORE_URL . $relative;
	}

	/**
	 * Check if a custom code file has any modifications
	 *
	 * @param string $type Code type.
	 * @return bool
	 */
	public function has_modifications( string $type ): bool {
		$mods = $this->get_modifications_by_type( $type );
		return ! empty( $mods );
	}

	/**
	 * Get current file content for snapshot (before state)
	 *
	 * @param string $type Code type.
	 * @return array File state including path, content, and exists flag.
	 */
	public function get_file_state( string $type ): array {
		$file_path = $this->get_file_path( $type );

		if ( ! $file_path ) {
			return [
				'exists'  => false,
				'path'    => null,
				'content' => null,
			];
		}

		$exists = file_exists( $file_path );

		return [
			'exists'  => $exists,
			'path'    => $file_path,
			'content' => $exists ? file_get_contents( $file_path ) : null,
			'type'    => $type,
		];
	}

	/**
	 * Get manifest file state for snapshot
	 *
	 * @return array Manifest state.
	 */
	public function get_manifest_state(): array {
		$manifest_path = $this->base_dir . self::FILE_MANIFEST;
		$exists = file_exists( $manifest_path );

		return [
			'exists'  => $exists,
			'path'    => $manifest_path,
			'content' => $exists ? file_get_contents( $manifest_path ) : null,
		];
	}

	/**
	 * Restore file from snapshot state
	 *
	 * @param array $state File state from get_file_state().
	 * @return bool Success status.
	 */
	public function restore_file_state( array $state ): bool {
		if ( empty( $state['path'] ) ) {
			return false;
		}

		// If file didn't exist before, delete it
		if ( ! $state['exists'] ) {
			if ( file_exists( $state['path'] ) ) {
				return unlink( $state['path'] );
			}
			return true;
		}

		// Restore content
		if ( $state['content'] !== null ) {
			$result = file_put_contents( $state['path'], $state['content'] );
			if ( $result !== false ) {
				chmod( $state['path'], 0644 );
			}
			return $result !== false;
		}

		return false;
	}
}
