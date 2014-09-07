<?php

/**
 * This file contains the functions for displaying and searching in the
 * members list.
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
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Memberlist Controller
 */
class Memberlist_Controller extends Action_Controller
{
	/**
	 * Sets up the context for showing a listing of registered members.
	 * For the handlers in this file, it requires the view_mlist permission.
	 * Accessed by ?action_memberlist.
	 * @uses Memberlist template, main sub-template.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $scripturl, $txt, $modSettings, $context;

		// Make sure they can view the memberlist.
		isAllowedTo('view_mlist');

		loadTemplate('Memberlist');
		$context['sub_template'] = 'memberlist';
		Template_Layers::getInstance()->add('mlsearch');

		$context['listing_by'] = !empty($_GET['sa']) ? $_GET['sa'] : 'all';

		// $subActions array format:
		// 'subaction' => array('label', 'function', 'is_selected')
		$subActions = array(
			'all' => array($txt['view_all_members'], 'action_mlall', $context['listing_by'] == 'all'),
			'search' => array($txt['mlist_search'], 'action_mlsearch', $context['listing_by'] == 'search'),
		);

		// Set up the sort links.
		$context['sort_links'] = array();
		foreach ($subActions as $act => $text)
		{
			$context['sort_links'][] = array(
				'label' => $text[0],
				'action' => $act,
				'selected' => $text[2],
			);
		}

		$context['num_members'] = $modSettings['totalMembers'];

		// Set up the standard columns...
		$context['columns'] = array(
			'online' => array(
				'label' => $txt['status'],
				'class' => 'status',
				'sort' => array(
					'down' => allowedTo('moderate_forum') ? 'IFNULL(lo.log_time, 1) ASC, real_name ASC' : 'CASE WHEN mem.show_online THEN IFNULL(lo.log_time, 1) ELSE 1 END ASC, real_name ASC',
					'up' => allowedTo('moderate_forum') ? 'IFNULL(lo.log_time, 1) DESC, real_name DESC' : 'CASE WHEN mem.show_online THEN IFNULL(lo.log_time, 1) ELSE 1 END DESC, real_name DESC'
				),
			),
			'real_name' => array(
				'label' => $txt['username'],
				'class' => 'username',
				'sort' => array(
					'down' => 'mem.real_name DESC',
					'up' => 'mem.real_name ASC'
				),
			),
			'email_address' => array(
				'label' => $txt['email'],
				'class' => 'email',
				'sort' => array(
					'down' => allowedTo('moderate_forum') ? 'mem.email_address DESC' : 'mem.hide_email DESC, mem.email_address DESC',
					'up' => allowedTo('moderate_forum') ? 'mem.email_address ASC' : 'mem.hide_email ASC, mem.email_address ASC'
				),
			),
			'website_url' => array(
				'label' => $txt['website'],
				'class' => 'website',
				'link_with' => 'website',
				'sort' => array(
					'down' => 'LENGTH(mem.website_url) > 0 ASC, IFNULL(mem.website_url, 1=1) DESC, mem.website_url DESC',
					'up' => 'LENGTH(mem.website_url) > 0 DESC, IFNULL(mem.website_url, 1=1) ASC, mem.website_url ASC'
				),
			),
			'id_group' => array(
				'label' => $txt['position'],
				'class' => 'group',
				'sort' => array(
					'down' => 'IFNULL(mg.group_name, 1=1) DESC, mg.group_name DESC',
					'up' => 'IFNULL(mg.group_name, 1=1) ASC, mg.group_name ASC'
				),
			),
			'date_registered' => array(
				'label' => $txt['date_registered'],
				'class' => 'date_registered',
				'sort' => array(
					'down' => 'mem.date_registered DESC',
					'up' => 'mem.date_registered ASC'
				),
			),
			'posts' => array(
				'label' => $txt['posts'],
				'class' => 'posts',
				'default_sort_rev' => true,
				'sort' => array(
					'down' => 'mem.posts DESC',
					'up' => 'mem.posts ASC'
				),
			)
		);

		// Add in any custom profile columns
		require_once(SUBSDIR . '/Memberlist.subs.php');
		if (ml_CustomProfile())
			$context['columns'] += $context['custom_profile_fields']['columns'];

		// The template may appreciate how many columns it needs to display
		$context['colspan'] = 0;
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();
		foreach ($context['columns'] as $key => $column)
		{
			if (isset($context['disabled_fields'][$key]) || (isset($column['link_with']) && isset($context['disabled_fields'][$column['link_with']])))
			{
				unset($context['columns'][$key]);
				continue;
			}

			$context['colspan'] += isset($column['colspan']) ? $column['colspan'] : 1;
		}

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=memberlist',
			'name' => $txt['members_list']
		);

		$context['can_send_pm'] = allowedTo('pm_send');

		// Build the memberlist button array.
		$context['memberlist_buttons'] = array(
			'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.png', 'lang' => true, 'url' => $scripturl . '?action=memberlist;sa=all', 'active' => true),
		);

		// Are there custom fields they can search?
		ml_findSearchableCustomFields();

		// These are all the possible fields.
		$context['search_fields'] = array(
			'name' => $txt['mlist_search_name'],
			'email' => $txt['mlist_search_email'],
			'website' => $txt['mlist_search_website'],
			'group' => $txt['mlist_search_group'],
		);

		foreach ($context['custom_search_fields'] as $field)
			$context['search_fields']['cust_' . $field['colname']] = sprintf($txt['mlist_search_by'], $field['name']);

		// What do we search for by default?
		$context['search_defaults'] = array('name', 'email');

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_memberlist_buttons');

		if (!allowedTo('send_email_to_members'))
			unset($context['columns']['email_address']);
		if (isset($context['disabled_fields']['website']))
			unset($context['columns']['website']);
		if (isset($context['disabled_fields']['posts']))
			unset($context['columns']['posts']);

		// Jump to the sub action.
		if (isset($subActions[$context['listing_by']]))
			$this->{$subActions[$context['listing_by']][1]}();
		else
			$this->{$subActions['all'][1]}();
	}

	/**
	 * List all members, page by page, with sorting.
	 * Called from MemberList().
	 * Can be passed a sort parameter, to order the display of members.
	 * Calls printMemberListRows to retrieve the results of the query.
	 */
	public function action_mlall()
	{
		global $txt, $scripturl, $modSettings, $context;

		// The chunk size for the cached index.
		$cache_step_size = 500;

		require_once(SUBSDIR . '/Memberlist.subs.php');

		// Only use caching if:
		// 1. there are at least 2k members,
		// 2. the default sorting method (real_name) is being used,
		// 3. the page shown is high enough to make a DB filesort unprofitable.
		$use_cache = $modSettings['totalMembers'] > 2000 && (!isset($_REQUEST['sort']) || $_REQUEST['sort'] === 'real_name') && isset($_REQUEST['start']) && $_REQUEST['start'] > $cache_step_size;
		if ($use_cache)
		{
			// Maybe there's something cached already.
			if (!empty($modSettings['memberlist_cache']))
				$memberlist_cache = @unserialize($modSettings['memberlist_cache']);

			// The chunk size for the cached index.
			$cache_step_size = 500;

			// Only update the cache if something changed or no cache existed yet.
			if (empty($memberlist_cache) || empty($modSettings['memberlist_updated']) || $memberlist_cache['last_update'] < $modSettings['memberlist_updated'])
				$memberlist_cache = ml_memberCache($cache_step_size);

			$context['num_members'] = $memberlist_cache['num_members'];
		}
		// Without cache we need an extra query to get the amount of members.
		else
			$context['num_members'] = ml_memberCount();

		// Set defaults for sort (real_name) and start. (0)
		if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
			$_REQUEST['sort'] = 'real_name';

		if (!is_numeric($_REQUEST['start']))
		{
			if (preg_match('~^[^\'\\\\/]~u', Util::strtolower($_REQUEST['start']), $match) === 0)
				fatal_error('Hacker?', false);

			$_REQUEST['start'] = ml_alphaStart($match[0]);
		}

		// Build out the letter selection link bar
		$context['letter_links'] = '';
		for ($i = 97; $i < 123; $i++)
			$context['letter_links'] .= '<a href="' . $scripturl . '?action=memberlist;sa=all;start=' . chr($i) . '#letter' . chr($i) . '">' . chr($i - 32) . '</a> ';

		// Sort out the column information.
		foreach ($context['columns'] as $col => $column_details)
		{
			$context['columns'][$col]['href'] = $scripturl . '?action=memberlist;sort=' . $col . ';start=0';

			if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
				$context['columns'][$col]['href'] .= ';desc';

			$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
			$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
			if ($context['columns'][$col]['selected'])
				$context['columns'][$col]['class'] .= ' selected';
		}

		// Are we sorting the results
		$context['sort_by'] = $_REQUEST['sort'];
		$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

		// Construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=memberlist;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $context['num_members'], $modSettings['defaultMaxMembers']);

		// Send the data to the template.
		$context['start'] = $_REQUEST['start'] + 1;
		$context['end'] = min($_REQUEST['start'] + $modSettings['defaultMaxMembers'], $context['num_members']);
		$context['can_moderate_forum'] = allowedTo('moderate_forum');
		$context['page_title'] = sprintf($txt['viewing_members'], $context['start'], $context['end']);
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=memberlist;sort=' . $_REQUEST['sort'] . ';start=' . $_REQUEST['start'],
			'name' => &$context['page_title'],
			'extra_after' => ' (' . sprintf($txt['of_total_members'], $context['num_members']) . ')'
		);

		$limit = $_REQUEST['start'];
		$where = '';
		$query_parameters = array(
			'regular_id_group' => 0,
			'is_activated' => 1,
			'sort' => $context['columns'][$_REQUEST['sort']]['sort'][$context['sort_direction']],
		);

		// Using cache allows to narrow down the list to be retrieved.
		if ($use_cache && $_REQUEST['sort'] === 'real_name' && !isset($_REQUEST['desc']))
		{
			$first_offset = $_REQUEST['start'] - ($_REQUEST['start'] % $cache_step_size);
			$second_offset = ceil(($_REQUEST['start'] + $modSettings['defaultMaxMembers']) / $cache_step_size) * $cache_step_size;

			$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
			$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
			$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
			$limit -= $first_offset;
		}
		// Reverse sorting is a bit more complicated...
		elseif ($use_cache && $_REQUEST['sort'] === 'real_name')
		{
			$first_offset = floor(($memberlist_cache['num_members'] - $modSettings['defaultMaxMembers'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;
			if ($first_offset < 0)
				$first_offset = 0;
			$second_offset = ceil(($memberlist_cache['num_members'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;

			$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
			$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
			$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
			$limit = $second_offset - ($memberlist_cache['num_members'] - $_REQUEST['start']) - ($second_offset > $memberlist_cache['num_members'] ? $cache_step_size - ($memberlist_cache['num_members'] % $cache_step_size) : 0);
		}

		// Add custom fields parameters too.
		if (!empty($context['custom_profile_fields']['parameters']))
			$query_parameters += $context['custom_profile_fields']['parameters'];

		// Select the members from the database.
		ml_selectMembers($query_parameters, $where, $limit, $_REQUEST['sort']);

		// Add anchors at the start of each letter.
		if ($_REQUEST['sort'] === 'real_name')
		{
			$last_letter = '';
			foreach ($context['members'] as $i => $dummy)
			{
				$this_letter = Util::strtolower(Util::substr($context['members'][$i]['name'], 0, 1));

				if ($this_letter != $last_letter && preg_match('~[a-z]~', $this_letter) === 1)
				{
					$context['members'][$i]['sort_letter'] = htmlspecialchars($this_letter, ENT_COMPAT, 'UTF-8');
					$last_letter = $this_letter;
				}
			}
		}
	}

	/**
	 * Search for members, or display search results.
	 * If variable $_REQUEST['search'] is empty displays search dialog box, using the search sub-template.
	 * Calls printMemberListRows to retrieve the results of the query.
	 */
	public function action_mlsearch()
	{
		global $txt, $scripturl, $context, $modSettings;

		$context['page_title'] = $txt['mlist_search'];
		$context['can_moderate_forum'] = allowedTo('moderate_forum');

		// Are there custom fields they can search?
		ml_findSearchableCustomFields();

		// They're searching..
		if (isset($_REQUEST['search']) && isset($_REQUEST['fields']))
		{
			$search = Util::htmlspecialchars(trim(isset($_GET['search']) ? $_GET['search'] : $_POST['search']), ENT_QUOTES);
			$input_fields = isset($_GET['fields']) ? explode(',', $_GET['fields']) : $_POST['fields'];

			$context['old_search'] = $_REQUEST['search'];
			$context['old_search_value'] = urlencode($_REQUEST['search']);

			// No fields?  Use default...
			if (empty($input_fields))
				$input_fields = array('name');

			// Set defaults for how the results are sorted
			if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
				$_REQUEST['sort'] = 'real_name';

			// Build the column link / sort information.
			foreach ($context['columns'] as $col => $column_details)
			{
				$context['columns'][$col]['href'] = $scripturl . '?action=memberlist;sa=search;start=0;sort=' . $col;

				if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
					$context['columns'][$col]['href'] .= ';desc';

				$context['columns'][$col]['href'] .= ';search=' . $search . ';fields=' . implode(',', $input_fields);

				$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
				$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
			}

			// set up some things for use in the template
			$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';
			$context['sort_by'] = $_REQUEST['sort'];

			$query_parameters = array(
				'regular_id_group' => 0,
				'is_activated' => 1,
				'blank_string' => '',
				'search' => '%' . strtr($search, array('_' => '\\_', '%' => '\\%', '*' => '%')) . '%',
				'sort' => $context['columns'][$_REQUEST['sort']]['sort'][$context['sort_direction']],
			);

			// Search for a name
			if (in_array('name', $input_fields))
				$fields = allowedTo('moderate_forum') ? array('member_name', 'real_name') : array('real_name');
			else
				$fields = array();

			// Search for websites.
			if (in_array('website', $input_fields))
				$fields += array(7 => 'website_title', 'website_url');

			// Search for groups.
			if (in_array('group', $input_fields))
				$fields += array(9 => 'IFNULL(group_name, {string:blank_string})');

			// Search for an email address?
			if (in_array('email', $input_fields))
			{
				$fields += array(2 => allowedTo('moderate_forum') ? 'email_address' : '(hide_email = 0 AND email_address');
				$condition = allowedTo('moderate_forum') ? '' : ')';
			}
			else
				$condition = '';

			if (defined('DB_CASE_SENSITIVE'))
			{
				foreach ($fields as $key => $field)
					$fields[$key] = 'LOWER(' . $field . ')';
			}

			$customJoin = array();
			$customCount = 10;
			$validFields = array();

			// Any custom fields to search for - these being tricky?
			foreach ($input_fields as $field)
			{
				$curField = substr($field, 5);
				if (substr($field, 0, 5) === 'cust_' && isset($context['custom_search_fields'][$curField]))
				{
					$customJoin[] = 'LEFT JOIN {db_prefix}custom_fields_data AS cfd' . $curField . ' ON (cfd' . $curField . '.variable = {string:cfd' . $curField . '} AND cfd' . $curField . '.id_member = mem.id_member)';
					$query_parameters['cfd' . $curField] = $curField;
					$fields += array($customCount++ => 'IFNULL(cfd' . $curField . '.value, {string:blank_string})');
					$validFields[] = $field;
				}
			}

			if (empty($fields))
				redirectexit('action=memberlist');

			$query = $search == '' ? '= {string:blank_string}' : (defined('DB_CASE_SENSITIVE') ? 'LIKE LOWER({string:search})' : 'LIKE {string:search}');
			$where = implode(' ' . $query . ' OR ', $fields) . ' ' . $query . $condition;

			// Find the members from the database.
			$numResults = ml_searchMembers($query_parameters, $customJoin, $where, $_REQUEST['start']);
			$context['page_index'] = constructPageIndex($scripturl . '?action=memberlist;sa=search;search=' . $search . ';fields=' . implode(',', $validFields), $_REQUEST['start'], $numResults, $modSettings['defaultMaxMembers']);
		}
		else
			redirectexit('action=memberlist');

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=memberlist;sa=search',
			'name' => &$context['page_title']
		);

		// Highlight the correct button, too!
		unset($context['memberlist_buttons']['view_all_members']['active']);
	}
}