<?php
/**
 * Plugin Documentation Repository
 *
 * Centralized repository for plugin documentation.
 * Uses Firebase Firestore as primary storage with local fallback.
 * Implements lazy-loading: docs are fetched and cached on first request.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ProxyClient;

/**
 * Class PluginDocsRepository
 *
 * Manages plugin documentation with:
 * - Firebase Firestore as central repository
 * - Local wp_options cache as fallback
 * - AI-powered documentation research for cache misses
 */
class PluginDocsRepository {

	/**
	 * Local cache option name
	 */
	private const CACHE_OPTION = 'creator_plugin_docs_cache';

	/**
	 * Cache TTL in seconds (30 days)
	 */
	private const CACHE_TTL = 2592000;

	/**
	 * Firestore collection name
	 */
	private const FIRESTORE_COLLECTION = 'plugin_docs_cache';

	/**
	 * Proxy client instance
	 *
	 * @var ProxyClient|null
	 */
	private ?ProxyClient $proxy_client = null;

	/**
	 * Local cache
	 *
	 * @var array|null
	 */
	private ?array $local_cache = null;

	/**
	 * Get documentation for a specific plugin version
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array Documentation data or empty array.
	 */
	public function get_plugin_docs( string $plugin_slug, string $plugin_version ): array {
		// Normalize inputs
		$plugin_slug    = sanitize_title( $plugin_slug );
		$plugin_version = sanitize_text_field( $plugin_version );

		// Try local cache first
		$cached = $this->get_from_local_cache( $plugin_slug, $plugin_version );
		if ( $cached ) {
			$this->increment_cache_hits( $plugin_slug, $plugin_version );
			return $cached;
		}

		// Try Firestore
		$firestore_data = $this->get_from_firestore( $plugin_slug, $plugin_version );
		if ( $firestore_data ) {
			// Save to local cache
			$this->save_to_local_cache( $plugin_slug, $plugin_version, $firestore_data );
			return $firestore_data;
		}

		// Cache miss - trigger AI research
		$researched = $this->research_plugin_docs( $plugin_slug, $plugin_version );
		if ( $researched ) {
			// Save to both Firestore and local cache
			$this->save_to_firestore( $plugin_slug, $plugin_version, $researched );
			$this->save_to_local_cache( $plugin_slug, $plugin_version, $researched );
			return $researched;
		}

		// Return basic info if research fails
		return $this->get_basic_plugin_info( $plugin_slug, $plugin_version );
	}

	/**
	 * Get documentation from local cache
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array|null
	 */
	private function get_from_local_cache( string $plugin_slug, string $plugin_version ): ?array {
		$cache = $this->get_local_cache();

		$cache_key = $this->get_cache_key( $plugin_slug, $plugin_version );

		if ( ! isset( $cache[ $cache_key ] ) ) {
			return null;
		}

		$entry = $cache[ $cache_key ];

		// Check if expired
		$cached_at = strtotime( $entry['cached_at'] ?? '0' );
		if ( time() - $cached_at > self::CACHE_TTL ) {
			return null; // Expired
		}

		return $entry['data'] ?? null;
	}

	/**
	 * Save to local cache
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @param array  $data           Documentation data.
	 * @return void
	 */
	private function save_to_local_cache( string $plugin_slug, string $plugin_version, array $data ): void {
		$cache     = $this->get_local_cache();
		$cache_key = $this->get_cache_key( $plugin_slug, $plugin_version );

		$cache[ $cache_key ] = [
			'plugin_slug'    => $plugin_slug,
			'plugin_version' => $plugin_version,
			'data'           => $data,
			'cached_at'      => current_time( 'c' ),
			'cache_hits'     => 0,
		];

		$this->local_cache = $cache;
		update_option( self::CACHE_OPTION, $cache, false );
	}

	/**
	 * Get documentation from Firestore
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array|null
	 */
	private function get_from_firestore( string $plugin_slug, string $plugin_version ): ?array {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return null;
		}

		try {
			$response = $proxy->send_request( 'GET', '/api/plugin-docs/' . $plugin_slug . '/' . $plugin_version );

			if ( $response['success'] && ! empty( $response['data'] ) ) {
				return $response['data'];
			}
		} catch ( \Exception $e ) {
			// Log error but continue
			error_log( 'Creator: Firestore docs fetch failed: ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Save to Firestore
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @param array  $data           Documentation data.
	 * @return bool
	 */
	private function save_to_firestore( string $plugin_slug, string $plugin_version, array $data ): bool {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return false;
		}

		try {
			$response = $proxy->send_request( 'POST', '/api/plugin-docs', [
				'plugin_slug'    => $plugin_slug,
				'plugin_version' => $plugin_version,
				'data'           => $data,
				'cached_at'      => current_time( 'c' ),
				'cached_by'      => get_current_user_id(),
			]);

			return $response['success'] ?? false;
		} catch ( \Exception $e ) {
			error_log( 'Creator: Firestore docs save failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Research plugin documentation using Firebase Cloud Function
	 *
	 * Calls the /api/plugin-docs/research endpoint which uses AI to find
	 * official documentation for the plugin. Results are cached in Firestore.
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @param string $plugin_name    Optional plugin name.
	 * @param string $plugin_uri     Optional plugin URI.
	 * @return array|null
	 */
	private function research_plugin_docs( string $plugin_slug, string $plugin_version, string $plugin_name = '', string $plugin_uri = '' ): ?array {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return null;
		}

		try {
			// Call the Firebase research endpoint
			$response = $proxy->send_request( 'POST', '/api/plugin-docs/research', [
				'plugin_slug'    => $plugin_slug,
				'plugin_version' => $plugin_version,
				'plugin_name'    => $plugin_name,
				'plugin_uri'     => $plugin_uri,
			]);

			if ( $response['success'] && ! empty( $response['data'] ) ) {
				$data = $response['data'];

				return [
					'docs_url'       => sanitize_url( $data['docs_url'] ?? '' ),
					'functions_url'  => sanitize_url( $data['functions_url'] ?? '' ),
					'main_functions' => array_map( 'sanitize_text_field', $data['main_functions'] ?? [] ),
					'api_reference'  => sanitize_url( $data['api_reference'] ?? '' ),
					'version_notes'  => array_map( 'sanitize_text_field', $data['version_notes'] ?? [] ),
					'source'         => $response['source'] ?? 'ai_research',
					'researched_at'  => current_time( 'c' ),
				];
			}
		} catch ( \Exception $e ) {
			error_log( 'Creator: Plugin docs research failed: ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Get basic plugin info as fallback
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array
	 */
	private function get_basic_plugin_info( string $plugin_slug, string $plugin_version ): array {
		return [
			'docs_url'       => sprintf( 'https://wordpress.org/plugins/%s/', $plugin_slug ),
			'main_functions' => [],
			'api_reference'  => '',
			'version_notes'  => [],
			'source'         => 'fallback',
		];
	}

	/**
	 * Get local cache
	 *
	 * @return array
	 */
	private function get_local_cache(): array {
		if ( $this->local_cache === null ) {
			$this->local_cache = get_option( self::CACHE_OPTION, [] );
		}

		return is_array( $this->local_cache ) ? $this->local_cache : [];
	}

	/**
	 * Get cache key
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return string
	 */
	private function get_cache_key( string $plugin_slug, string $plugin_version ): string {
		return $plugin_slug . ':' . $plugin_version;
	}

	/**
	 * Increment cache hits counter
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return void
	 */
	private function increment_cache_hits( string $plugin_slug, string $plugin_version ): void {
		$cache     = $this->get_local_cache();
		$cache_key = $this->get_cache_key( $plugin_slug, $plugin_version );

		if ( isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ]['cache_hits'] = ( $cache[ $cache_key ]['cache_hits'] ?? 0 ) + 1;
			$this->local_cache                 = $cache;

			// Don't save every hit - save periodically
			if ( $cache[ $cache_key ]['cache_hits'] % 10 === 0 ) {
				update_option( self::CACHE_OPTION, $cache, false );
			}
		}
	}

	/**
	 * Get proxy client instance
	 *
	 * @return ProxyClient|null
	 */
	private function get_proxy_client(): ?ProxyClient {
		if ( $this->proxy_client === null ) {
			if ( class_exists( '\CreatorCore\Integrations\ProxyClient' ) ) {
				$this->proxy_client = new ProxyClient();
			}
		}

		return $this->proxy_client;
	}

	/**
	 * Clear local cache
	 *
	 * @return bool
	 */
	public function clear_local_cache(): bool {
		$this->local_cache = [];
		return delete_option( self::CACHE_OPTION );
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public function get_cache_stats(): array {
		$cache = $this->get_local_cache();

		$total_entries = count( $cache );
		$total_hits    = 0;
		$oldest        = null;
		$newest        = null;

		foreach ( $cache as $entry ) {
			$total_hits += $entry['cache_hits'] ?? 0;

			$cached_at = $entry['cached_at'] ?? null;
			if ( $cached_at ) {
				if ( $oldest === null || $cached_at < $oldest ) {
					$oldest = $cached_at;
				}
				if ( $newest === null || $cached_at > $newest ) {
					$newest = $cached_at;
				}
			}
		}

		return [
			'total_entries' => $total_entries,
			'total_hits'    => $total_hits,
			'oldest_entry'  => $oldest,
			'newest_entry'  => $newest,
			'cache_size_kb' => round( strlen( serialize( $cache ) ) / 1024, 2 ),
		];
	}

	/**
	 * Get all cached plugins
	 *
	 * @return array
	 */
	public function get_cached_plugins(): array {
		$cache   = $this->get_local_cache();
		$plugins = [];

		foreach ( $cache as $key => $entry ) {
			$plugins[] = [
				'slug'       => $entry['plugin_slug'] ?? '',
				'version'    => $entry['plugin_version'] ?? '',
				'cached_at'  => $entry['cached_at'] ?? '',
				'cache_hits' => $entry['cache_hits'] ?? 0,
				'source'     => $entry['data']['source'] ?? 'unknown',
			];
		}

		return $plugins;
	}

	/**
	 * Prefetch documentation for multiple plugins
	 *
	 * @param array $plugins Array of ['slug' => string, 'version' => string].
	 * @return int Number of plugins fetched.
	 */
	public function prefetch_plugins( array $plugins ): int {
		$fetched = 0;

		foreach ( $plugins as $plugin ) {
			if ( empty( $plugin['slug'] ) || empty( $plugin['version'] ) ) {
				continue;
			}

			// Check if already cached
			$cached = $this->get_from_local_cache( $plugin['slug'], $plugin['version'] );
			if ( $cached ) {
				continue;
			}

			// Fetch and cache
			$docs = $this->get_plugin_docs( $plugin['slug'], $plugin['version'] );
			if ( ! empty( $docs ) && $docs['source'] !== 'fallback' ) {
				$fetched++;
			}
		}

		return $fetched;
	}

	/**
	 * Sync plugin docs from Firestore to local cache
	 *
	 * Pulls latest plugin documentation from the centralized Firestore
	 * repository and saves it to the local wp_options cache as fallback.
	 *
	 * @param array $plugin_slugs Optional array of plugin slugs to sync.
	 * @param int   $limit        Maximum number of entries to sync.
	 * @return array Sync result with count and list of synced plugins.
	 */
	public function sync_from_firestore( array $plugin_slugs = [], int $limit = 100 ): array {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return [
				'success'      => false,
				'error'        => 'Proxy client not available',
				'synced_count' => 0,
				'plugins'      => [],
			];
		}

		try {
			// Get last sync timestamp
			$last_sync = get_option( 'creator_plugin_docs_last_sync', '' );

			// Call the Firebase sync endpoint
			$response = $proxy->send_request( 'POST', '/api/plugin-docs/sync', [
				'plugin_slugs'    => $plugin_slugs,
				'since_timestamp' => $last_sync,
				'limit'           => $limit,
			]);

			if ( ! $response['success'] || empty( $response['data'] ) ) {
				return [
					'success'      => false,
					'error'        => $response['error'] ?? 'Sync request failed',
					'synced_count' => 0,
					'plugins'      => [],
				];
			}

			$plugins = $response['data']['plugins'] ?? [];
			$synced  = [];

			foreach ( $plugins as $plugin ) {
				if ( empty( $plugin['plugin_slug'] ) || empty( $plugin['plugin_version'] ) ) {
					continue;
				}

				// Save to local cache
				$this->save_to_local_cache(
					$plugin['plugin_slug'],
					$plugin['plugin_version'],
					[
						'docs_url'       => $plugin['docs_url'] ?? '',
						'main_functions' => $plugin['main_functions'] ?? [],
						'api_reference'  => $plugin['api_reference'] ?? '',
						'version_notes'  => $plugin['version_notes'] ?? [],
						'source'         => 'firestore_sync',
					]
				);

				$synced[] = [
					'slug'    => $plugin['plugin_slug'],
					'version' => $plugin['plugin_version'],
				];
			}

			// Update last sync timestamp
			update_option( 'creator_plugin_docs_last_sync', current_time( 'c' ), false );

			return [
				'success'      => true,
				'synced_count' => count( $synced ),
				'plugins'      => $synced,
			];
		} catch ( \Exception $e ) {
			error_log( 'Creator: Firestore sync failed: ' . $e->getMessage() );

			return [
				'success'      => false,
				'error'        => $e->getMessage(),
				'synced_count' => 0,
				'plugins'      => [],
			];
		}
	}

	/**
	 * Get plugin documentation with additional plugin info
	 *
	 * Enhanced version that includes plugin name and URI for better AI research.
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @param string $plugin_name    Plugin name.
	 * @param string $plugin_uri     Plugin URI.
	 * @return array Documentation data or empty array.
	 */
	public function get_plugin_docs_with_info( string $plugin_slug, string $plugin_version, string $plugin_name = '', string $plugin_uri = '' ): array {
		// Normalize inputs
		$plugin_slug    = sanitize_title( $plugin_slug );
		$plugin_version = sanitize_text_field( $plugin_version );

		// Try local cache first
		$cached = $this->get_from_local_cache( $plugin_slug, $plugin_version );
		if ( $cached ) {
			$this->increment_cache_hits( $plugin_slug, $plugin_version );
			return $cached;
		}

		// Try Firestore
		$firestore_data = $this->get_from_firestore( $plugin_slug, $plugin_version );
		if ( $firestore_data ) {
			$this->save_to_local_cache( $plugin_slug, $plugin_version, $firestore_data );
			return $firestore_data;
		}

		// Cache miss - trigger AI research with additional info
		$researched = $this->research_plugin_docs( $plugin_slug, $plugin_version, $plugin_name, $plugin_uri );
		if ( $researched ) {
			$this->save_to_local_cache( $plugin_slug, $plugin_version, $researched );
			return $researched;
		}

		// Return basic info if research fails
		return $this->get_basic_plugin_info( $plugin_slug, $plugin_version );
	}

	/**
	 * Get Firestore stats from the centralized repository
	 *
	 * @return array Stats including total entries, cache hit rate, etc.
	 */
	public function get_firestore_stats(): array {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return [
				'success' => false,
				'error'   => 'Proxy client not available',
			];
		}

		try {
			$response = $proxy->send_request( 'GET', '/api/plugin-docs/stats' );

			if ( $response['success'] && ! empty( $response['data'] ) ) {
				return [
					'success' => true,
					'data'    => $response['data'],
				];
			}

			return [
				'success' => false,
				'error'   => $response['error'] ?? 'Stats request failed',
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}
}
