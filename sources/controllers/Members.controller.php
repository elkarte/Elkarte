<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file processes actions on members.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Members Controller
 */
class Members_Controller
{
	/**
	 * This simple function adds/removes the passed user from the current users buddy list.
	 * Requires profile_identity_own permission.
	 * Called by ?action=buddy;u=x;session_id=y.
	 * Subactions: sa=add and sa=remove. (@todo refactor subactions)
	 * Redirects to ?action=profile;u=x.
	 */
	public function action_buddy()
	{
		global $user_info;

		checkSession('get');

		isAllowedTo('profile_identity_own');
		is_not_guest();

		// You have to give a user
		if (empty($_REQUEST['u']))
			fatal_lang_error('no_access', false);

		// Always an int
		$user = (int) $_REQUEST['u'];

		// Remove this user if it's already in your buddies...
		if (in_array($user, $user_info['buddies']))
			$user_info['buddies'] = array_diff($user_info['buddies'], array($user));
		// ...or add if it's not there (and not you).
		elseif ($user_info['id'] != $user)
			$user_info['buddies'][] = $user;

		// Update the settings.
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $user);
	}

	/**
	 * Called by index.php?action=findmember.
	 * This function result is used as a popup for searching members.
	 * @uses sub template find_members of the Members template.
	 */
	public function action_findmember()
	{
		global $context, $scripturl, $user_info;

		checkSession('get');

		// Load members template
		loadTemplate('Members');
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
			$_REQUEST['search'] = Util::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);

			$context['results'] = findMembers(array($_REQUEST['search']), true, $context['buddy_search']);
			$total_results = count($context['results']);
			$_REQUEST['start'] = (int) $_REQUEST['start'];
			$context['page_index'] = constructPageIndex($scripturl . '?action=findmember;search=' . $context['last_search'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';input=' . $context['input_box_name'] . ($context['quote_results'] ? ';quote=1' : '') . ($context['buddy_search'] ? ';buddies' : ''), $_REQUEST['start'], $total_results, 7);

			// Determine the navigation context (especially useful for the wireless template).
			$base_url = $scripturl . '?action=findmember;search=' . urlencode($context['last_search']) . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']) . ';' . $context['session_var'] . '=' . $context['session_id'];
			$context['links'] = array(
				'first' => $_REQUEST['start'] >= 7 ? $base_url . ';start=0' : '',
				'prev' => $_REQUEST['start'] >= 7 ? $base_url . ';start=' . ($_REQUEST['start'] - 7) : '',
				'next' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . ($_REQUEST['start'] + 7) : '',
				'last' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . (floor(($total_results - 1) / 7) * 7) : '',
				'up' => $scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']),
			);
			$context['page_info'] = array(
				'current_page' => $_REQUEST['start'] / 7 + 1,
				'num_pages' => floor(($total_results - 1) / 7) + 1
			);

			$context['results'] = array_slice($context['results'], $_REQUEST['start'], 7);
		}
		else
			$context['links']['up'] = $scripturl . '?action=pm;sa=send' . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']);
	}
}