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

class Members_Controller
{
	/**
	 * This simple function adds/removes the passed user from the current users buddy list.
	 * Requires profile_identity_own permission.
	 * Called by ?action=buddy;u=x;session_id=y.
	 * Subactions: sa=add and sa=remove. (@todo refactor subactions)
	 * Redirects to ?action=profile;u=x.
	 */
	function action_buddy()
	{
		global $user_info;

		checkSession('get');

		isAllowedTo('profile_identity_own');
		is_not_guest();

		if (empty($_REQUEST['u']))
			fatal_lang_error('no_access', false);
		$_REQUEST['u'] = (int) $_REQUEST['u'];

		// Remove if it's already there...
		if (in_array($_REQUEST['u'], $user_info['buddies']))
			$user_info['buddies'] = array_diff($user_info['buddies'], array($_REQUEST['u']));
		// ...or add if it's not and if it's not you.
		elseif ($user_info['id'] != $_REQUEST['u'])
			$user_info['buddies'][] = (int) $_REQUEST['u'];

		// Update the settings.
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Redirect back to the profile
		redirectexit('action=profile;u=' . $_REQUEST['u']);
	}

	/**
	 * Outputs each member name on its own line.
	 * This function is used by javascript to find members matching the request.
	 * Accessed by action=requestmembers.
	 */
	function action_requestmembers()
	{
		global $user_info, $txt;

		$db = database();

		checkSession('get');

		$_REQUEST['search'] = Util::htmlspecialchars($_REQUEST['search']) . '*';
		$_REQUEST['search'] = trim(Util::strtolower($_REQUEST['search']));
		$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

		if (function_exists('iconv'))
			header('Content-Type: text/plain; charset=UTF-8');

		$request = $db->query('', '
			SELECT real_name
			FROM {db_prefix}members
			WHERE real_name LIKE {string:search}' . (isset($_REQUEST['buddies']) ? '
				AND id_member IN ({array_int:buddy_list})' : '') . '
				AND is_activated IN (1, 11)
			LIMIT ' . (Util::strlen($_REQUEST['search']) <= 2 ? '100' : '800'),
			array(
				'buddy_list' => $user_info['buddies'],
				'search' => $_REQUEST['search'],
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

			if (preg_match('~&#\d+;~', $row['real_name']) != 0)
				$row['real_name'] = preg_replace_callback('~&#(\d+);~', 'fixchar__callback', $row['real_name']);

			echo $row['real_name'], "\n";
		}
		$db->free_result($request);

		obExit(false);
	}

	/**
	 * Called by index.php?action=findmember.
	 * This function result is used as a popup for searching members.
	 * @uses sub template find_members of the Members template.
	 */
	function action_findmember()
	{
		global $context, $scripturl, $user_info;

		checkSession('get');

		// Load members template
		loadTemplate('Members');
		$context['template_layers'] = array();
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