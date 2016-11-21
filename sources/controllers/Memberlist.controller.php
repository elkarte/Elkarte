<?php

/**
 * This file contains the functions for displaying and searching in the
 * members list.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Memberlist Controller class
 */
class Memberlist_Controller extends Action_Controller
{
	/**
	 * The fields that we can search
	 * @var array
	 */
	public $_search_fields;

	/**
	 * Entry point function, called before all others
	 */
	public function pre_dispatch()
	{
		global $context, $txt;

		// These are all the possible fields.
		$this->_search_fields = array(
			'name' => $txt['mlist_search_name'],
			'email' => $txt['mlist_search_email'],
			'website' => $txt['mlist_search_website'],
			'group' => $txt['mlist_search_group'],
		);

		// Are there custom fields they can search?
		require_once(SUBSDIR . '/Memberlist.subs.php');
		ml_findSearchableCustomFields();

		// These are handy later
		$context['old_search_value'] = '';
		$context['in_search'] = !empty($this->_req->post->search);

		foreach ($context['custom_search_fields'] as $field)
			$this->_search_fields['cust_' . $field['colname']] = sprintf($txt['mlist_search_by'], $field['name']);
	}

	/**
	 * Sets up the context for showing a listing of registered members.
	 * For the handlers in this file, it requires the view_mlist permission.
	 *
	 * - Accessed by ?action_memberlist.
	 *
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

		$context['listing_by'] = $this->_req->getQuery('sa', 'trim', 'all');

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
			'avatar' => array(
				'label' => '',
				'class' => 'avatar',
			),
			'real_name' => array(
				'label' => $txt['username'],
				'class' => 'username',
				'sort' => array(
					'down' => 'mem.real_name DESC',
					'up' => 'mem.real_name ASC'
				),
			),
			'online' => array(
				'label' => $txt['status'],
				'class' => 'status',
				'sort' => array(
					'down' => allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) ASC, real_name ASC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END ASC, real_name ASC',
					'up' => allowedTo('moderate_forum') ? 'COALESCE(lo.log_time, 1) DESC, real_name DESC' : 'CASE WHEN mem.show_online THEN COALESCE(lo.log_time, 1) ELSE 1 END DESC, real_name DESC'
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
					'down' => 'LENGTH(mem.website_url) > 0 ASC, COALESCE(mem.website_url, 1=1) DESC, mem.website_url DESC',
					'up' => 'LENGTH(mem.website_url) > 0 DESC, COALESCE(mem.website_url, 1=1) ASC, mem.website_url ASC'
				),
			),
			'id_group' => array(
				'label' => $txt['position'],
				'class' => 'group',
				'sort' => array(
					'down' => 'COALESCE(mg.group_name, 1=1) DESC, mg.group_name DESC',
					'up' => 'COALESCE(mg.group_name, 1=1) ASC, mg.group_name ASC'
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
		$context['can_send_email'] = allowedTo('send_email_to_members');

		// Build the memberlist button array.
		if ($context['in_search'])
		{
			$context['memberlist_buttons'] = array(
				'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.png', 'lang' => true, 'url' => $scripturl . '?action=memberlist;sa=all', 'active' => true),
			);
		}
		else
			$context['memberlist_buttons'] = array();

		// Make fields available to the template
		$context['search_fields'] = $this->_search_fields;

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
	 *
	 * - Called from MemberList().
	 * - Can be passed a sort parameter, to order the display of members.
	 * - Calls printMemberListRows to retrieve the results of the query.
	 */
	public function action_mlall()
	{
		global $txt, $scripturl, $modSettings, $context;

		// The chunk size for the cached index.
		$cache_step_size = 500;
		$memberlist_cache = '';

		require_once(SUBSDIR . '/Memberlist.subs.php');

		// Some handy short cuts
		$start = $this->_req->getQuery('start', '', null);
		$desc = $this->_req->getQuery('desc', '', null);
		$sort = $this->_req->getQuery('sort', '', null);

		// Only use caching if:
		// 1. there are at least 2k members,
		// 2. the default sorting method (real_name) is being used,
		// 3. the page shown is high enough to make a DB file sort unprofitable.
		$use_cache = $modSettings['totalMembers'] > 2000
			&& (!isset($sort) || $sort === 'real_name')
			&& isset($start)
			&& $start > $cache_step_size;

		if ($use_cache)
		{
			// Maybe there's something cached already.
			if (!empty($modSettings['memberlist_cache']))
				$memberlist_cache = Util::unserialize($modSettings['memberlist_cache']);

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

		// Set defaults for sort (real_name)
		if (!isset($sort) || !isset($context['columns'][$sort]['sort']))
			$sort = 'real_name';

		// Looking at a specific rolodex letter?
		if (!is_numeric($start))
		{
			if (preg_match('~^[^\'\\\\/]~u', Util::strtolower($start), $match) === 0)
				Errors::instance()->fatal_error('Hacker?', false);

			$start = ml_alphaStart($match[0]);
		}

		// Build out the letter selection link bar
		$context['letter_links'] = '';
		for ($i = 97; $i < 123; $i++)
			$context['letter_links'] .= '<a href="' . $scripturl . '?action=memberlist;sa=all;start=' . chr($i) . '#letter' . chr($i) . '">' . chr($i - 32) . '</a> ';

		// Sort out the column information.
		foreach ($context['columns'] as $col => $column_details)
		{
			$context['columns'][$col]['href'] = $scripturl . '?action=memberlist;sort=' . $col . ';start=0';

			if ((!isset($desc) && $col == $sort)
				|| ($col != $sort && !empty($column_details['default_sort_rev'])))
			{
				$context['columns'][$col]['href'] .= ';desc';
			}

			$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
			$context['columns'][$col]['selected'] = $sort == $col;
			if ($context['columns'][$col]['selected'])
				$context['columns'][$col]['class'] .= ' selected';
		}

		// Are we sorting the results
		$context['sort_by'] = $sort;
		$context['sort_direction'] = !isset($desc) ? 'up' : 'down';

		// Construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=memberlist;sort=' . $sort . (isset($desc) ? ';desc' : ''), $start, $context['num_members'], $modSettings['defaultMaxMembers']);

		// Send the data to the template.
		$context['start'] = $start + 1;
		$context['end'] = min($start + $modSettings['defaultMaxMembers'], $context['num_members']);
		$context['can_moderate_forum'] = allowedTo('moderate_forum');
		$context['page_title'] = sprintf($txt['viewing_members'], $context['start'], $context['end']);
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=memberlist;sort=' . $sort . ';start=' . $start,
			'name' => &$context['page_title'],
			'extra_after' => ' (' . sprintf($txt['of_total_members'], $context['num_members']) . ')'
		);

		$limit = $start;
		$where = '';
		$query_parameters = array(
			'regular_id_group' => 0,
			'is_activated' => 1,
			'sort' => $context['columns'][$sort]['sort'][$context['sort_direction']],
		);

		// Using cache allows to narrow down the list to be retrieved.
		if ($use_cache && $sort === 'real_name' && !isset($desc))
		{
			$first_offset = $start - ($start % $cache_step_size);
			$second_offset = ceil(($start + $modSettings['defaultMaxMembers']) / $cache_step_size) * $cache_step_size;
			$second_offset = min(max(array_keys($memberlist_cache['index'])), $second_offset);

			$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
			$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
			$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
			$limit -= $first_offset;
		}
		// Reverse sorting is a bit more complicated...
		elseif ($use_cache && $sort === 'real_name')
		{
			$first_offset = floor(($memberlist_cache['num_members'] - $modSettings['defaultMaxMembers'] - $start) / $cache_step_size) * $cache_step_size;
			if ($first_offset < 0)
				$first_offset = 0;
			$second_offset = ceil(($memberlist_cache['num_members'] - $start) / $cache_step_size) * $cache_step_size;

			$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
			$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
			$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
			$limit = $second_offset - ($memberlist_cache['num_members'] - $start) - ($second_offset > $memberlist_cache['num_members'] ? $cache_step_size - ($memberlist_cache['num_members'] % $cache_step_size) : 0);
		}

		// Add custom fields parameters too.
		if (!empty($context['custom_profile_fields']['parameters']))
			$query_parameters += $context['custom_profile_fields']['parameters'];

		// Select the members from the database.
		ml_selectMembers($query_parameters, $where, $limit, $sort);

		// Add anchors at the start of each letter.
		if ($sort === 'real_name')
		{
			$last_letter = '';
			foreach ($context['members'] as $i => $dummy)
			{
				$this_letter = Util::strtolower(Util::substr($context['members'][$i]['name'], 0, 1));

				if ($this_letter != $last_letter && preg_match('~[a-z]~', $this_letter) === 1)
				{
					$context['members'][$i]['sort_letter'] = Util::htmlspecialchars($this_letter);
					$last_letter = $this_letter;
				}
			}
		}
	}

	/**
	 * Search for members, or display search results.
	 *
	 * - If variable $_REQUEST['search'] is empty displays search dialog box,
	 * using the search sub-template.
	 * - Calls printMemberListRows to retrieve the results of the query.
	 */
	public function action_mlsearch()
	{
		global $txt, $scripturl, $context, $modSettings;

		$context['page_title'] = $txt['mlist_search'];
		$context['can_moderate_forum'] = allowedTo('moderate_forum');

		// They're searching..
		if (isset($this->_req->query->search, $this->_req->query->fields)
			|| isset($this->_req->post->search, $this->_req->post->fields))
		{
			// Some handy short cuts
			$start = $this->_req->getQuery('start', '', null);
			$desc = $this->_req->getQuery('desc', '', null);
			$sort = $this->_req->getQuery('sort', '', null);
			$search = Util::htmlspecialchars(trim(isset($this->_req->query->search) ? $this->_req->query->search : $this->_req->post->search), ENT_QUOTES);
			$input_fields = isset($this->_req->query->fields) ? explode(',', $this->_req->query->fields) : $this->_req->post->fields;

			$fields_key = array_keys($this->_search_fields);
			$context['search_defaults'] = array();
			foreach ($input_fields as $val)
			{
				if (in_array($val, $fields_key))
					$context['search_defaults'] = $input_fields;
			}
			$context['old_search_value'] = $search;

			// No fields?  Use default...
			if (empty($input_fields))
				$input_fields = array('name');

			// Set defaults for how the results are sorted
			if (!isset($sort) || !isset($context['columns'][$sort]))
				$sort = 'real_name';

			// Build the column link / sort information.
			foreach ($context['columns'] as $col => $column_details)
			{
				$context['columns'][$col]['href'] = $scripturl . '?action=memberlist;sa=search;start=0;sort=' . $col;

				if ((!isset($desc) && $col == $sort) || ($col != $sort && !empty($column_details['default_sort_rev'])))
					$context['columns'][$col]['href'] .= ';desc';

				$context['columns'][$col]['href'] .= ';search=' . $search . ';fields=' . implode(',', $input_fields);
				$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
				$context['columns'][$col]['selected'] = $sort == $col;
			}

			// set up some things for use in the template
			$context['sort_direction'] = !isset($desc) ? 'up' : 'down';
			$context['sort_by'] = $sort;
			$context['memberlist_buttons'] = array(
				'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.png', 'lang' => true, 'url' => $scripturl . '?action=memberlist;sa=all', 'active' => true),
			);

			$query_parameters = array(
				'regular_id_group' => 0,
				'is_activated' => 1,
				'blank_string' => '',
				'search' => '%' . strtr($search, array('_' => '\\_', '%' => '\\%', '*' => '%')) . '%',
				'sort' => $context['columns'][$sort]['sort'][$context['sort_direction']],
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
				$fields += array(9 => 'COALESCE(group_name, {string:blank_string})');

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
			$validFields = isset($input_fields) ? $input_fields : array();

			// Any custom fields to search for - these being tricky?
			foreach ($input_fields as $field)
			{
				$curField = substr($field, 5);
				if (substr($field, 0, 5) === 'cust_' && isset($context['custom_search_fields'][$curField]))
				{
					$customJoin[] = 'LEFT JOIN {db_prefix}custom_fields_data AS cfd' . $field . ' ON (cfd' . $field . '.variable = {string:cfd' . $field . '} AND cfd' . $field . '.id_member = mem.id_member)';
					$query_parameters['cfd' . $field] = $curField;
					$fields += array($customCount++ => 'COALESCE(cfd' . $field . '.value, {string:blank_string})');
					$validFields[] = $field;
				}
			}
			$field = $sort;
			$curField = substr($field, 5);
			if (substr($field, 0, 5) === 'cust_' && isset($context['custom_search_fields'][$curField]))
			{
				$customJoin[] = 'LEFT JOIN {db_prefix}custom_fields_data AS cfd' . $field . ' ON (cfd' . $field . '.variable = {string:cfd' . $field . '} AND cfd' . $field . '.id_member = mem.id_member)';
				$query_parameters['cfd' . $field] = $curField;
				$validFields[] = $field;
			}

			if (empty($fields))
				redirectexit('action=memberlist');

			$validFields = array_unique($validFields);
			$query = $search == '' ? '= {string:blank_string}' : (defined('DB_CASE_SENSITIVE') ? 'LIKE LOWER({string:search})' : 'LIKE {string:search}');
			$where = implode(' ' . $query . ' OR ', $fields) . ' ' . $query . $condition;

			// Find the members from the database.
			$numResults = ml_searchMembers($query_parameters, array_unique($customJoin), $where, $start);
			$context['letter_links'] = '';
			$context['page_index'] = constructPageIndex($scripturl . '?action=memberlist;sa=search;search=' . $search . ';fields=' . implode(',', $validFields), $start, $numResults, $modSettings['defaultMaxMembers']);
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