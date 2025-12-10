<?php
/**
 * Execution Verifier
 *
 * Automatically verifies that executed code achieved the expected result.
 * Provides verification methods for different action types.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class ExecutionVerifier
 *
 * Verifies execution results for various action types:
 * - Custom Post Types
 * - ACF Field Groups
 * - Posts/Pages
 * - Options
 * - Taxonomies
 * - WP Code Snippets
 */
class ExecutionVerifier {

	/**
	 * Verification result statuses
	 */
	public const RESULT_PASSED  = 'passed';
	public const RESULT_FAILED  = 'failed';
	public const RESULT_WARNING = 'warning';
	public const RESULT_SKIPPED = 'skipped';

	/**
	 * Audit logger instance
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $logger;

	/**
	 * Constructor
	 *
	 * @param AuditLogger|null $logger Audit logger instance.
	 */
	public function __construct( ?AuditLogger $logger = null ) {
		$this->logger = $logger ?? new AuditLogger();
	}

	/**
	 * Verify execution result based on action type
	 *
	 * @param string $action_type Action type.
	 * @param array  $expected    Expected result parameters.
	 * @param array  $context     Execution context.
	 * @return array Verification result.
	 */
	public function verify( string $action_type, array $expected, array $context = [] ): array {
		$method = 'verify_' . $action_type;

		if ( method_exists( $this, $method ) ) {
			$result = $this->$method( $expected, $context );
		} else {
			$result = $this->verify_generic( $action_type, $expected, $context );
		}

		// Log verification result
		$this->logger->log(
			'execution_verified',
			$result['success'] ? 'success' : 'warning',
			[
				'action_type' => $action_type,
				'result'      => $result['status'],
				'checks'      => count( $result['checks'] ?? [] ),
				'warnings'    => count( $result['warnings'] ?? [] ),
			]
		);

		return $result;
	}

	/**
	 * Verify Custom Post Type creation
	 *
	 * @param array $expected Expected CPT parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_create_post_type( array $expected, array $context ): array {
		$checks   = [];
		$warnings = [];
		$cpt_name = $expected['post_type'] ?? '';

		if ( empty( $cpt_name ) ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'No post type name provided' ] );
		}

		// Check 1: Post type exists
		$cpt_object = get_post_type_object( $cpt_name );
		$checks[]   = $this->create_check(
			'Post type registered',
			$cpt_object !== null,
			$cpt_object ? 'Post type "' . $cpt_name . '" is registered' : 'Post type not found'
		);

		if ( ! $cpt_object ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'Post type was not registered' ] );
		}

		// Check 2: Labels are set
		if ( isset( $expected['labels'] ) ) {
			$label_match = $cpt_object->label === ( $expected['labels']['name'] ?? '' );
			$checks[]    = $this->create_check(
				'Labels configured',
				$label_match,
				'Label: "' . $cpt_object->label . '"'
			);
		}

		// Check 3: Public visibility
		if ( isset( $expected['public'] ) ) {
			$public_match = $cpt_object->public === $expected['public'];
			$checks[]     = $this->create_check(
				'Public visibility',
				$public_match,
				$cpt_object->public ? 'Post type is public' : 'Post type is private'
			);
		}

		// Check 4: Admin menu visibility
		if ( $cpt_object->show_ui ) {
			$menu_visible = $this->is_cpt_in_admin_menu( $cpt_name );
			$checks[]     = $this->create_check(
				'Admin menu item',
				$menu_visible,
				$menu_visible ? 'Visible in admin menu' : 'Not visible in admin menu yet (may require refresh)'
			);

			if ( ! $menu_visible ) {
				$warnings[] = 'Admin menu may not show until page refresh';
			}
		}

		// Check 5: Supports
		if ( isset( $expected['supports'] ) && is_array( $expected['supports'] ) ) {
			$supports = get_all_post_type_supports( $cpt_name );
			foreach ( $expected['supports'] as $feature ) {
				$has_support = isset( $supports[ $feature ] );
				$checks[]    = $this->create_check(
					'Supports: ' . $feature,
					$has_support,
					$has_support ? 'Feature enabled' : 'Feature not enabled'
				);
			}
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Verify ACF field group creation
	 *
	 * @param array $expected Expected field group parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_create_acf_group( array $expected, array $context ): array {
		$checks   = [];
		$warnings = [];

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $this->create_result( false, self::RESULT_SKIPPED, $checks, [ 'ACF is not active' ] );
		}

		$group_key   = $expected['key'] ?? '';
		$group_title = $expected['title'] ?? '';

		// Check 1: Field group exists
		$group = null;
		if ( $group_key ) {
			$group = acf_get_field_group( $group_key );
		} elseif ( $group_title ) {
			$groups = acf_get_field_groups();
			foreach ( $groups as $g ) {
				if ( $g['title'] === $group_title ) {
					$group = $g;
					break;
				}
			}
		}

		$checks[] = $this->create_check(
			'Field group exists',
			$group !== null,
			$group ? 'Field group "' . ( $group['title'] ?? '' ) . '" found' : 'Field group not found'
		);

		if ( ! $group ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'Field group was not created' ] );
		}

		// Check 2: Field group is active
		$is_active = $group['active'] ?? true;
		$checks[]  = $this->create_check(
			'Field group active',
			$is_active,
			$is_active ? 'Field group is active' : 'Field group is inactive'
		);

		// Check 3: Fields registered
		if ( isset( $expected['fields'] ) && is_array( $expected['fields'] ) ) {
			$fields        = acf_get_fields( $group['key'] );
			$fields_count  = is_array( $fields ) ? count( $fields ) : 0;
			$expected_count = count( $expected['fields'] );

			$checks[] = $this->create_check(
				'Fields registered',
				$fields_count >= $expected_count,
				sprintf( '%d of %d fields registered', $fields_count, $expected_count )
			);

			// Check each expected field
			foreach ( $expected['fields'] as $expected_field ) {
				$field_name = $expected_field['name'] ?? '';
				$found      = false;

				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( $field['name'] === $field_name ) {
							$found = true;
							break;
						}
					}
				}

				$checks[] = $this->create_check(
					'Field: ' . $field_name,
					$found,
					$found ? 'Field exists' : 'Field not found'
				);
			}
		}

		// Check 4: Location rules
		if ( isset( $expected['location'] ) && ! empty( $group['location'] ) ) {
			$checks[] = $this->create_check(
				'Location rules set',
				true,
				'Location rules configured'
			);
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Verify post/page creation
	 *
	 * @param array $expected Expected post parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_create_post( array $expected, array $context ): array {
		$checks   = [];
		$warnings = [];
		$post_id  = $expected['post_id'] ?? $context['result_id'] ?? 0;

		if ( ! $post_id ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'No post ID provided or returned' ] );
		}

		$post = get_post( $post_id );

		// Check 1: Post exists
		$checks[] = $this->create_check(
			'Post exists',
			$post !== null,
			$post ? 'Post ID ' . $post_id . ' exists' : 'Post not found'
		);

		if ( ! $post ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'Post was not created' ] );
		}

		// Check 2: Post type correct
		if ( isset( $expected['post_type'] ) ) {
			$type_match = $post->post_type === $expected['post_type'];
			$checks[]   = $this->create_check(
				'Post type',
				$type_match,
				'Type: ' . $post->post_type
			);
		}

		// Check 3: Post status
		if ( isset( $expected['post_status'] ) ) {
			$status_match = $post->post_status === $expected['post_status'];
			$checks[]     = $this->create_check(
				'Post status',
				$status_match,
				'Status: ' . $post->post_status
			);
		}

		// Check 4: Title
		if ( isset( $expected['post_title'] ) ) {
			$title_match = $post->post_title === $expected['post_title'];
			$checks[]    = $this->create_check(
				'Post title',
				$title_match,
				'Title: "' . $post->post_title . '"'
			);
		}

		// Check 5: Meta fields
		if ( isset( $expected['meta'] ) && is_array( $expected['meta'] ) ) {
			foreach ( $expected['meta'] as $key => $value ) {
				$actual   = get_post_meta( $post_id, $key, true );
				$matches  = $actual == $value; // Loose comparison for different types
				$checks[] = $this->create_check(
					'Meta: ' . $key,
					$matches,
					$matches ? 'Value matches' : 'Value mismatch'
				);
			}
		}

		// Check 6: Permalink accessible (for published posts)
		if ( $post->post_status === 'publish' ) {
			$permalink = get_permalink( $post_id );
			$checks[]  = $this->create_check(
				'Permalink generated',
				! empty( $permalink ),
				$permalink ?: 'No permalink'
			);
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Verify option update
	 *
	 * @param array $expected Expected option parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_update_option( array $expected, array $context ): array {
		$checks      = [];
		$warnings    = [];
		$option_name = $expected['option_name'] ?? '';

		if ( empty( $option_name ) ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'No option name provided' ] );
		}

		// Check 1: Option exists
		$value   = get_option( $option_name );
		$exists  = $value !== false || get_option( $option_name, 'DOES_NOT_EXIST' ) !== 'DOES_NOT_EXIST';
		$checks[] = $this->create_check(
			'Option exists',
			$exists,
			$exists ? 'Option "' . $option_name . '" exists' : 'Option not found'
		);

		// Check 2: Value matches (if expected value provided)
		if ( isset( $expected['option_value'] ) ) {
			$matches  = $value == $expected['option_value']; // Loose comparison
			$checks[] = $this->create_check(
				'Value correct',
				$matches,
				$matches ? 'Value matches expected' : 'Value does not match'
			);
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Verify taxonomy creation
	 *
	 * @param array $expected Expected taxonomy parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_create_taxonomy( array $expected, array $context ): array {
		$checks    = [];
		$warnings  = [];
		$tax_name  = $expected['taxonomy'] ?? '';

		if ( empty( $tax_name ) ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'No taxonomy name provided' ] );
		}

		// Check 1: Taxonomy exists
		$taxonomy = get_taxonomy( $tax_name );
		$checks[] = $this->create_check(
			'Taxonomy registered',
			$taxonomy !== false,
			$taxonomy ? 'Taxonomy "' . $tax_name . '" exists' : 'Taxonomy not found'
		);

		if ( ! $taxonomy ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'Taxonomy was not registered' ] );
		}

		// Check 2: Object types
		if ( isset( $expected['object_types'] ) ) {
			$types_set = ! empty( $taxonomy->object_type );
			$checks[]  = $this->create_check(
				'Object types assigned',
				$types_set,
				'Types: ' . implode( ', ', $taxonomy->object_type )
			);
		}

		// Check 3: Hierarchical
		if ( isset( $expected['hierarchical'] ) ) {
			$matches  = $taxonomy->hierarchical === $expected['hierarchical'];
			$checks[] = $this->create_check(
				'Hierarchical setting',
				$matches,
				$taxonomy->hierarchical ? 'Hierarchical (like categories)' : 'Flat (like tags)'
			);
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Verify WP Code snippet execution
	 *
	 * @param array $expected Expected snippet parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_wpcode_snippet( array $expected, array $context ): array {
		$checks     = [];
		$warnings   = [];
		$snippet_id = $expected['snippet_id'] ?? $context['snippet_id'] ?? 0;

		if ( ! $snippet_id ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'No snippet ID provided' ] );
		}

		$snippet = get_post( $snippet_id );

		// Check 1: Snippet exists
		$checks[] = $this->create_check(
			'Snippet exists',
			$snippet !== null && $snippet->post_type === 'wpcode',
			$snippet ? 'Snippet ID ' . $snippet_id . ' exists' : 'Snippet not found'
		);

		if ( ! $snippet ) {
			return $this->create_result( false, self::RESULT_FAILED, $checks, [ 'Snippet was not created' ] );
		}

		// Check 2: Status
		$expected_status = $expected['status'] ?? 'publish';
		$status_match    = $snippet->post_status === $expected_status;
		$checks[]        = $this->create_check(
			'Snippet status',
			$status_match,
			'Status: ' . $snippet->post_status . ( $snippet->post_status === 'publish' ? ' (active)' : ' (inactive)' )
		);

		// Check 3: No PHP errors in error log (recent)
		$has_errors = $this->check_recent_php_errors( $snippet_id );
		$checks[]   = $this->create_check(
			'No PHP errors',
			! $has_errors,
			$has_errors ? 'PHP errors detected after execution' : 'No PHP errors detected'
		);

		if ( $has_errors ) {
			$warnings[] = 'Check PHP error log for details';
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Verify SQL execution
	 *
	 * @param array $expected Expected SQL parameters.
	 * @param array $context  Execution context.
	 * @return array Verification result.
	 */
	private function verify_execute_sql( array $expected, array $context ): array {
		global $wpdb;

		$checks   = [];
		$warnings = [];

		// Check 1: No SQL errors
		$last_error = $wpdb->last_error;
		$checks[]   = $this->create_check(
			'No SQL errors',
			empty( $last_error ),
			empty( $last_error ) ? 'Query executed without errors' : 'Error: ' . $last_error
		);

		// Check 2: Affected rows (if expected)
		if ( isset( $expected['affected_rows'] ) ) {
			$actual   = $wpdb->rows_affected;
			$matches  = $actual === $expected['affected_rows'];
			$checks[] = $this->create_check(
				'Affected rows',
				$matches,
				sprintf( '%d rows affected (expected %d)', $actual, $expected['affected_rows'] )
			);
		}

		// Check 3: Result count (for SELECT queries)
		if ( isset( $expected['result_count'] ) && isset( $context['results'] ) ) {
			$actual   = is_array( $context['results'] ) ? count( $context['results'] ) : 0;
			$matches  = $actual === $expected['result_count'];
			$checks[] = $this->create_check(
				'Result count',
				$matches,
				sprintf( '%d results (expected %d)', $actual, $expected['result_count'] )
			);
		}

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Generic verification for unknown action types
	 *
	 * @param string $action_type Action type.
	 * @param array  $expected    Expected parameters.
	 * @param array  $context     Execution context.
	 * @return array Verification result.
	 */
	private function verify_generic( string $action_type, array $expected, array $context ): array {
		$checks   = [];
		$warnings = [ 'No specific verification available for action type: ' . $action_type ];

		// Basic checks
		$checks[] = $this->create_check(
			'Execution completed',
			isset( $context['success'] ) ? $context['success'] : true,
			'Action was executed'
		);

		// Check for errors in context
		$has_errors = isset( $context['errors'] ) && ! empty( $context['errors'] );
		$checks[]   = $this->create_check(
			'No execution errors',
			! $has_errors,
			$has_errors ? 'Errors detected' : 'No errors'
		);

		$all_passed = ! in_array( false, array_column( $checks, 'passed' ), true );

		return $this->create_result( $all_passed, $all_passed ? self::RESULT_PASSED : self::RESULT_WARNING, $checks, $warnings );
	}

	/**
	 * Check if CPT is visible in admin menu
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	private function is_cpt_in_admin_menu( string $post_type ): bool {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return false;
		}

		foreach ( $menu as $item ) {
			if ( isset( $item[5] ) && strpos( $item[5], 'menu-posts-' . $post_type ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check for recent PHP errors (simple check)
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	private function check_recent_php_errors( int $snippet_id ): bool {
		// This is a simplified check - in production you'd want to
		// check the actual error log or use a more sophisticated method
		$last_error = error_get_last();

		if ( $last_error && $last_error['type'] === E_ERROR ) {
			return true;
		}

		return false;
	}

	/**
	 * Create a verification check result
	 *
	 * @param string $name    Check name.
	 * @param bool   $passed  Whether check passed.
	 * @param string $message Check message.
	 * @return array
	 */
	private function create_check( string $name, bool $passed, string $message ): array {
		return [
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		];
	}

	/**
	 * Create verification result
	 *
	 * @param bool   $success  Overall success.
	 * @param string $status   Status code.
	 * @param array  $checks   Individual checks.
	 * @param array  $warnings Warnings.
	 * @return array
	 */
	private function create_result( bool $success, string $status, array $checks, array $warnings = [] ): array {
		return [
			'success'        => $success,
			'status'         => $status,
			'checks'         => $checks,
			'warnings'       => $warnings,
			'checks_passed'  => count( array_filter( array_column( $checks, 'passed' ) ) ),
			'checks_total'   => count( $checks ),
			'verified_at'    => current_time( 'c' ),
		];
	}
}
