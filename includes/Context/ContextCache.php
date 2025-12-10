<?php
/**
 * Context Cache Manager
 *
 * Implements a two-level caching system for Creator Context:
 * - Level 1: WordPress transients (fast, 5-minute TTL)
 * - Level 2: WordPress options (persistent, hash-validated)
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContextCache
 *
 * Provides high-performance caching for Creator Context with automatic
 * invalidation when system configuration changes.
 */
class ContextCache {

	/**
	 * Transient key for fast cache
	 */
	const TRANSIENT_KEY = 'creator_context_cache';

	/**
	 * Transient TTL in seconds (5 minutes)
	 */
	const TRANSIENT_TTL = 300;

	/**
	 * Option key for persistent cache
	 */
	const OPTION_KEY = 'creator_context_persistent';

	/**
	 * Hash key for invalidation tracking
	 */
	const HASH_KEY = 'creator_context_hash';

	/**
	 * Prompt cache key (ultra-compact version)
	 */
	const PROMPT_CACHE_KEY = 'creator_context_prompt';

	/**
	 * Prompt cache TTL (1 minute - shorter for dynamic content)
	 */
	const PROMPT_CACHE_TTL = 60;

	/**
	 * Get cached context with two-level lookup
	 *
	 * @param callable $generator Function to generate context if cache miss.
	 * @return array Context data.
	 */
	public function get( callable $generator ): array {
		// Level 1: Try transient (fastest)
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached && $this->is_valid( $cached ) ) {
			return $cached;
		}

		// Level 2: Try persistent option
		$persistent = get_option( self::OPTION_KEY );
		if ( false !== $persistent && $this->is_valid( $persistent ) ) {
			// Refresh transient from persistent
			set_transient( self::TRANSIENT_KEY, $persistent, self::TRANSIENT_TTL );
			return $persistent;
		}

		// Cache miss - generate fresh context
		$context = $generator();

		// Store in both levels
		$this->set( $context );

		return $context;
	}

	/**
	 * Get cached prompt string
	 *
	 * @param callable $generator Function to generate prompt if cache miss.
	 * @return string Prompt string.
	 */
	public function get_prompt( callable $generator ): string {
		$cached = get_transient( self::PROMPT_CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$prompt = $generator();
		set_transient( self::PROMPT_CACHE_KEY, $prompt, self::PROMPT_CACHE_TTL );

		return $prompt;
	}

	/**
	 * Store context in both cache levels
	 *
	 * @param array $context Context data.
	 * @return void
	 */
	public function set( array $context ): void {
		$hash = $this->generate_hash();
		$context['_cache_hash'] = $hash;
		$context['_cache_time'] = time();

		// Level 1: Transient (fast access)
		set_transient( self::TRANSIENT_KEY, $context, self::TRANSIENT_TTL );

		// Level 2: Persistent option
		update_option( self::OPTION_KEY, $context, false );

		// Store current hash for validation
		update_option( self::HASH_KEY, $hash, false );
	}

	/**
	 * Invalidate all cached context
	 *
	 * @return void
	 */
	public function invalidate(): void {
		delete_transient( self::TRANSIENT_KEY );
		delete_transient( self::PROMPT_CACHE_KEY );
		delete_option( self::OPTION_KEY );
		delete_option( self::HASH_KEY );
	}

	/**
	 * Invalidate only transient cache (soft refresh)
	 *
	 * @return void
	 */
	public function soft_invalidate(): void {
		delete_transient( self::TRANSIENT_KEY );
		delete_transient( self::PROMPT_CACHE_KEY );
	}

	/**
	 * Check if cached context is valid
	 *
	 * @param mixed $cached Cached data.
	 * @return bool
	 */
	private function is_valid( $cached ): bool {
		if ( ! is_array( $cached ) ) {
			return false;
		}

		$cached_hash = $cached['_cache_hash'] ?? '';
		$current_hash = $this->get_current_hash();

		return $cached_hash === $current_hash;
	}

	/**
	 * Get current system hash (from option or generate)
	 *
	 * @return string
	 */
	private function get_current_hash(): string {
		$stored = get_option( self::HASH_KEY );
		if ( $stored ) {
			return $stored;
		}
		return $this->generate_hash();
	}

	/**
	 * Generate system hash for change detection
	 *
	 * @return string MD5 hash of system state.
	 */
	public function generate_hash(): string {
		global $wp_version;

		$components = [
			'wp'      => $wp_version,
			'php'     => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'plugins' => $this->get_plugins_hash(),
			'theme'   => get_stylesheet(),
		];

		return md5( wp_json_encode( $components ) );
	}

	/**
	 * Get hash of active plugins
	 *
	 * @return string
	 */
	private function get_plugins_hash(): string {
		$active = get_option( 'active_plugins', [] );
		sort( $active );
		return md5( implode( '|', $active ) );
	}

	/**
	 * Register invalidation hooks
	 *
	 * Call this on plugin init to auto-invalidate cache on system changes.
	 *
	 * @return void
	 */
	public function register_invalidation_hooks(): void {
		// Plugin changes
		add_action( 'activated_plugin', [ $this, 'on_plugin_change' ] );
		add_action( 'deactivated_plugin', [ $this, 'on_plugin_change' ] );
		add_action( 'upgrader_process_complete', [ $this, 'on_plugin_change' ] );

		// Theme changes
		add_action( 'switch_theme', [ $this, 'on_theme_change' ] );
		add_action( 'after_switch_theme', [ $this, 'on_theme_change' ] );

		// ACF changes (if ACF is active)
		add_action( 'acf/update_field_group', [ $this, 'soft_invalidate' ] );
		add_action( 'acf/delete_field_group', [ $this, 'soft_invalidate' ] );

		// CPT/Taxonomy registration (rare, but important)
		add_action( 'registered_post_type', [ $this, 'soft_invalidate' ] );
		add_action( 'registered_taxonomy', [ $this, 'soft_invalidate' ] );

		// WordPress core upgrade
		add_action( 'upgrader_process_complete', [ $this, 'on_core_upgrade' ], 10, 2 );
	}

	/**
	 * Handle plugin activation/deactivation
	 *
	 * @return void
	 */
	public function on_plugin_change(): void {
		// Full invalidate - plugins affect context significantly
		$this->invalidate();

		// Update hash
		update_option( self::HASH_KEY, $this->generate_hash(), false );
	}

	/**
	 * Handle theme switch
	 *
	 * @return void
	 */
	public function on_theme_change(): void {
		$this->invalidate();
		update_option( self::HASH_KEY, $this->generate_hash(), false );
	}

	/**
	 * Handle WordPress core upgrade
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $hook_extra Upgrade info.
	 * @return void
	 */
	public function on_core_upgrade( $upgrader, $hook_extra ): void {
		if ( isset( $hook_extra['type'] ) && 'core' === $hook_extra['type'] ) {
			$this->invalidate();
			update_option( self::HASH_KEY, $this->generate_hash(), false );
		}
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Stats array.
	 */
	public function get_stats(): array {
		$transient = get_transient( self::TRANSIENT_KEY );
		$persistent = get_option( self::OPTION_KEY );

		return [
			'transient_exists'  => false !== $transient,
			'persistent_exists' => false !== $persistent,
			'current_hash'      => $this->generate_hash(),
			'stored_hash'       => get_option( self::HASH_KEY, 'none' ),
			'transient_valid'   => false !== $transient && $this->is_valid( $transient ),
			'persistent_valid'  => false !== $persistent && $this->is_valid( $persistent ),
			'cache_time'        => $persistent['_cache_time'] ?? null,
			'cache_age_seconds' => isset( $persistent['_cache_time'] ) ? time() - $persistent['_cache_time'] : null,
		];
	}

	/**
	 * Warm the cache (pre-generate context)
	 *
	 * @param callable $generator Context generator function.
	 * @return void
	 */
	public function warm( callable $generator ): void {
		// Only warm if cache is invalid or missing
		$transient = get_transient( self::TRANSIENT_KEY );
		if ( false !== $transient && $this->is_valid( $transient ) ) {
			return; // Already warm
		}

		$context = $generator();
		$this->set( $context );
	}

	/**
	 * Schedule async cache warming (for admin pages)
	 *
	 * @param callable $generator Context generator function.
	 * @return void
	 */
	public function schedule_warm( callable $generator ): void {
		if ( ! wp_next_scheduled( 'creator_warm_context_cache' ) ) {
			wp_schedule_single_event( time() + 5, 'creator_warm_context_cache' );
		}

		// Store generator reference for the scheduled event
		add_action( 'creator_warm_context_cache', function() use ( $generator ) {
			$this->warm( $generator );
		});
	}
}
