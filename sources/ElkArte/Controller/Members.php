<?php

/**
 * This file processes the add/remove buddy actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Members Controller class.
 * Allows for the adding or removing of buddies
 */
class Members extends \ElkArte\AbstractController
{
	/**
	 * Forwards to an action method.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context;

		// Little short on the list here
		$subActions = array(
			'add' => array($this, 'action_addbuddy', 'permission' => 'profile_identity_own'),
			'remove' => array($this, 'action_removebuddy', 'permission' => 'profile_identity_own'),
		);

		// I don't think we know what to do... throw dies?
		$action = new \ElkArte\Action('members');
		$subAction = $action->initialize($subActions, 'none');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * This simple function adds the passed user from the current users buddy list.
	 *
	 * - Called by ?action=buddy;u=x;session_id=y.
	 * - Redirects to ?action=profile;u=x.
	 */
	public function action_addbuddy()
	{
		global $user_info, $modSettings;

		checkSession('get');
		is_not_guest();

		// Who's going to be your buddy
		$user = $this->_req->getQuery('u', 'intval', '');

		// You have to give a user
		if (empty($user))
			throw new \ElkArte\Exceptions\Exception('no_access', false);

		call_integration_hook('integrate_add_buddies', array($user_info['id'], &$user));

		// Add if it's not there (and not you).
		if (!in_array($user, $user_info['buddies']) && $user_info['id'] != $user)
		{
			$user_info['buddies'][] = $user;

			// Do we want a mention for our newly added buddy?
			if (!empty($modSettings['mentions_enabled']) && !empty($modSettings['mentions_buddy']))
			{
				$notifier = \ElkArte\Notifications::instance();
				$notifier->add(new \ElkArte\NotificationsTask(
					'buddy',
					$user,
					$user_info['id'],
					array('id_members' => array($user))
				));
			}
		}

		// Update the settings.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $user);
	}

	/**
	 * This function removes the passed user from the current users buddy list.
	 *
	 * - Called by ?action=buddy;u=x;session_id=y.
	 * - Redirects to ?action=profile;u=x.
	 */
	public function action_removebuddy()
	{
		global $user_info;

		checkSession('get');
		is_not_guest();

		call_integration_hook('integrate_remove_buddy', array($user_info['id']));

		// Yeah, they are no longer cool
		$user = $this->_req->getQuery('u', 'intval', '');

		// You have to give a user
		if (empty($user))
			throw new \ElkArte\Exceptions\Exception('no_access', false);

		// Remove this user, assuming we can find them
		if (in_array($user, $user_info['buddies']))
			$user_info['buddies'] = array_diff($user_info['buddies'], array($user));

		// Update the settings.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $user);
	}
}
