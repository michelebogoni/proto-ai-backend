<?php
/**
 * Action Dispatcher
 *
 * Routes actions to the appropriate handler.
 * With the Universal PHP Engine pattern, this is simplified to primarily
 * route everything through ExecutePHPHandler.
 *
 * @package CreatorCore
 * @since 2.0.0
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Executor\Handlers\ExecutePHPHandler;

/**
 * Class ActionDispatcher
 *
 * Simplified dispatcher for the Universal PHP Engine architecture.
 * Routes all code execution actions to ExecutePHPHandler.
 *
 * Legacy action types are rejected - the AI must generate PHP code.
 */
class ActionDispatcher {

	/**
	 * PHP execution handler
	 *
	 * @var ExecutePHPHandler
	 */
	private ExecutePHPHandler $php_handler;

	/**
	 * Constructor
	 *
	 * @param ExecutePHPHandler|null $php_handler Optional handler instance.
	 */
	public function __construct( ?ExecutePHPHandler $php_handler = null ) {
		$this->php_handler = $php_handler ?? new ExecutePHPHandler();
	}

	/**
	 * Dispatch an action to the appropriate handler
	 *
	 * @param array $action Action data from AI response.
	 * @return ActionResult
	 */
	public function dispatch( array $action ): ActionResult {
		$type = $action['type'] ?? $action['action'] ?? '';

		// Universal PHP Engine: We only support 'execute_code'
		if ( $this->php_handler->supports( $action ) ) {
			return $this->php_handler->handle( $action );
		}

		// Reject legacy action types
		$legacy_types = [
			'create_page',
			'create_post',
			'update_post',
			'delete_post',
			'create_plugin',
			'activate_plugin',
			'add_elementor_widget',
			'create_elementor_page',
			'update_elementor_page',
			'db_query',
			'db_insert',
			'db_update',
			'update_option',
			'update_meta',
		];

		if ( in_array( $type, $legacy_types, true ) ) {
			return ActionResult::fail(
				sprintf(
					"Legacy action type '%s' is no longer supported. Creator now uses the Universal PHP Engine. " .
					'The AI should generate executable PHP code instead.',
					$type
				)
			);
		}

		// Unknown action type
		return ActionResult::fail(
			sprintf(
				"Unknown action type: '%s'. Expected 'execute_code' with PHP code in details.code.",
				$type
			)
		);
	}

	/**
	 * Dispatch multiple actions sequentially
	 *
	 * @param array $actions Array of action data.
	 * @return array Array of ActionResult objects.
	 */
	public function dispatchBatch( array $actions ): array {
		$results = [];

		foreach ( $actions as $index => $action ) {
			$result = $this->dispatch( $action );
			$results[ $index ] = $result;

			// Stop on first failure if configured
			if ( $result->isFailure() && apply_filters( 'creator_stop_on_first_failure', true ) ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Get the PHP handler instance
	 *
	 * @return ExecutePHPHandler
	 */
	public function getPhpHandler(): ExecutePHPHandler {
		return $this->php_handler;
	}
}
