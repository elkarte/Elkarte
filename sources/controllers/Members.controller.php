<?php

/**
 * This file processes the add/remove buddy actions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Members Controller, allows for the adding or removing of buddies
 */
class Members_Controller extends Action_Controller
{
	/**
	 * Forwards to an action method.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context;

		require_once(SUBSDIR . '/Action.class.php');

		// Little short on the list here
		$subActions = array(
			'add' => array($this, 'action_addbuddy', 'permission' => 'profile_identity_own'),
			'remove' => array($this, 'action_removebuddy', 'permission' => 'profile_identity_own'),
		);

		// I don't think we know what to do... throw dies?
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'none';
		$context['sub_action'] = $subAction;

		$action = new Action();
		$action->initialize($subActions, 'add');
		$action->dispatch($subAction);
	}

	/**
	 * This simple function adds the passed user from the current users buddy list.
	 *
	 * Called by ?action=buddy;u=x;session_id=y.
	 * Redirects to ?action=profile;u=x.
	 */
	public function action_addbuddy()
	{
		global $user_info, $modSettings;

		checkSession('get');
		is_not_guest();

		// You have to give a user
		if (empty($_REQUEST['u']))
			fatal_lang_error('no_access', false);

		// Always an int
		$user = (int) $_REQUEST['u'];

		call_integration_hook('integrate_add_buddies', array($user_info['id'], &$user));

		// Add if it's not there (and not you).
		if (!in_array($user, $user_info['buddies']) && $user_info['id'] != $user)
		{
			$user_info['buddies'][] = $user;

			// Do we want a mention for our newly added buddy?
			if (!empty($modSettings['mentions_enabled']) && !empty($modSettings['mentions_buddy']))
			{
				require_once(CONTROLLERDIR . '/Mentions.controller.php');
				$mentions = new Mentions_Controller();

				// Set a mention for our buddy.
				$mentions->setData(array(
					'id_member' => $user,
					'type' => 'buddy',
					'id_msg' => 0,
					));
				$mentions->action_add();
			}
		}

		// Update the settings.
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $user);
	}

	/**
	 * This function removes the passed user from the current users buddy list.
	 *
	 * Called by ?action=buddy;u=x;session_id=y.
	 * Redirects to ?action=profile;u=x.
	 */
	public function action_removebuddy()
	{
		global $user_info;

		checkSession('get');
		is_not_guest();

		call_integration_hook('integrate_remove_buddy', array($user_info['id']));

		// You have to give a user
		if (empty($_REQUEST['u']))
			fatal_lang_error('no_access', false);

		// Always an int
		$user = (int) $_REQUEST['u'];

		// Remove this user, assuming we can find them
		if (in_array($user, $user_info['buddies']))
			$user_info['buddies'] = array_diff($user_info['buddies'], array($user));

		// Update the settings.
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $user);
	}

	/**
	 * Called by index.php?action=findmember.
	 * This function result is used as a popup for searching members.
	 *
	 * @todo This function is "deprecated" and will be remove from 1.1
	 * @depreciated
	 * @uses sub template find_members of the Members template.
	 */
	public function action_findmember()
	{
		global $context, $scripturl, $user_info, $settings;

		checkSession('get');

		// Load members template
		loadTemplate('Members');
		loadTemplate('index');
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'find_members';

		if (isset($_REQUEST['search']))
			$context['last_search'] = Util::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);
		else
			$_REQUEST['start'] = 0;

		// Allow the user to pass the input to be added to to the box.
		$context['input_box_name'] = isset($_REQUEST['input']) && preg_match('~^[\w-]+$~', $_REQUEST['input']) === 1 ? $_REQUEST['input'] : 'to';

		// Take the delimiter over GET in case it's \n or something.
		$context['delimiter'] = isset($_REQUEST['delim']) ? ($_REQUEST['delim'] == 'LB' ? "\n" : $_REQUEST['delim']) : ', ';
		$context['quote_results'] = !empty($_REQUEST['quote']);

		// List all the results.
		$context['results'] = array();

		// Some buddy related settings ;)
		$context['show_buddies'] = !empty($user_info['buddies']);
		$context['buddy_search'] = isset($_REQUEST['buddies']);

		// If the user has done a search, well - search.
		if (isset($_REQUEST['search']))
		{
			require_once(SUBSDIR . '/Auth.subs.php');
			$_REQUEST['search'] = Util::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);

			$context['results'] = findMembers(array($_REQUEST['search']), true, $context['buddy_search']);
			$total_results = count($context['results']);
			$_REQUEST['start'] = (int) $_REQUEST['start'];

			// This is a bit hacky, but its defined in index template, and this is a popup
			$settings['page_index_template'] = array(
				'base_link' => '<li class="linavPages"><a class="navPages" href="{base_link}" role="menuitem">%2$s</a></li>',
				'previous_page' => '<span class="previous_page" role="menuitem">{prev_txt}</span>',
				'current_page' => '<li class="linavPages"><strong class="current_page" role="menuitem">%1$s</strong></li>',
				'next_page' => '<span class="next_page" role="menuitem">{next_txt}</span>',
				'expand_pages' => '<li class="linavPages expand_pages" role="menuitem" {custom}> <a href="#">...</a> </li>',
				'all' => '<li class="linavPages all_pages" role="menuitem">{all_txt}</li>',
			);
			$context['page_index'] = constructPageIndex($scripturl . '?action=findmember;search=' . $context['last_search'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';input=' . $context['input_box_name'] . ($context['quote_results'] ? ';quote=1' : '') . ($context['buddy_search'] ? ';buddies' : ''), $_REQUEST['start'], $total_results, 7);

			// Determine the navigation context (especially useful for the wireless template).
			$base_url = $scripturl . '?action=findmember;search=' . urlencode($context['last_search']) . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']) . ';' . $context['session_var'] . '=' . $context['session_id'];
			$context['links'] += array(
				'prev' => $_REQUEST['start'] >= 7 ? $base_url . ';start=' . ($_REQUEST['start'] - 7) : '',
				'next' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . ($_REQUEST['start'] + 7) : '',
			);

			$context['page_info'] = array(
				'current_page' => $_REQUEST['start'] / 7 + 1,
				'num_pages' => floor(($total_results - 1) / 7) + 1
			);

			$context['results'] = array_slice($context['results'], $_REQUEST['start'], 7);
		}
	}
}