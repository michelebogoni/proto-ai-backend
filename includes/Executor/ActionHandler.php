<?php
/**
 * Action Handler Interface
 *
 * Defines the contract for all action handlers in the Universal PHP Engine.
 *
 * @package CreatorCore
 * @since 2.0.0
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ActionHandler
 *
 * All action handlers must implement this interface.
 * With the Universal PHP Engine pattern, we primarily use ExecutePHPHandler,
 * but this interface allows for future extensibility.
 */
interface ActionHandler {

	/**
	 * Handle an action
	 *
	 * @param array $action Action data containing type, details, etc.
	 * @return ActionResult Result of the action execution.
	 */
	public function handle( array $action ): ActionResult;

	/**
	 * Check if this handler can process the given action
	 *
	 * @param array $action Action data.
	 * @return bool True if this handler can process the action.
	 */
	public function supports( array $action ): bool;
}
