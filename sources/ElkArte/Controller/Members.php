<?php

/**
 * This file processes the add/remove buddy actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Notifications\Notifications;
use ElkArte\Notifications\NotificationsTask;

/**
 * Members Controller class.
 * Allows for the adding or removing of buddies
 */
class Members extends AbstractController
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
		$action = new Action('members');
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
		global $modSettings;

		checkSession('get');
		is_not_guest();

		// Who's going to be your buddy
		$user = $this->_req->getQuery('u', 'intval', '');

		// You have to give a user
		if (empty($user))
		{
			throw new Exception('no_access', false);
		}

		call_integration_hook('integrate_add_buddies', array($this->user->id, &$user));

		// Add if it's not there (and not you).
		if (!in_array($user, $this->user->buddies) && $this->user->id != $user)
		{
			$this->user->buddies[] = $user;

			// Do we want a mention for our newly added buddy?
			if (!empty($modSettings['mentions_enabled']))
			{
				$notifier = Notifications::instance();
				$notifier->add(new NotificationsTask(
					'buddy',
					$user,
					$this->user->id,
					array('id_members' => array($user))
				));
			}
		}

		// Update the settings.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($this->user->id, array('buddy_list' => implode(',', $this->user->buddies)));

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
		checkSession('get');
		is_not_guest();

		call_integration_hook('integrate_remove_buddy', array($this->user->id));

		// Yeah, they are no longer cool
		$user = $this->_req->getQuery('u', 'intval', '');

		// You have to give a user
		if (empty($user))
		{
			throw new Exception('no_access', false);
		}

		// Remove this user, assuming we can find them
		if (in_array($user, $this->user->buddies))
		{
			$this->user->buddies = array_diff($this->user->buddies, array($user));
		}

		// Update the settings.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($this->user->id, array('buddy_list' => implode(',', $this->user->buddies)));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $user);
	}
}
