<?php

/**
 * This file is exclusively for generating reports to help assist forum
 * administrators keep track of their site configuration and state. 
 * 
 * The core report generation is done in two areas. Firstly, a report "generator"
 * will fill context with relevant data. Secondly, the choice of sub-template will
 * determine how this data is shown to the user
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * "Report" Functions are responsible for generating data for reporting.
 * 
 * - They are all called from action_index.
 * - Never access the context directly, but use the data handling functions to do so.
 */
class Reports_Controller extends Action_Controller
{
	/**
	 * Handling function for generating reports.
	 * 
	 * What it does:
	 * 
	 * - Requires the admin_forum permission.
	 * - Loads the Reports template and language files.
	 * - Decides which type of report to generate, if this isn't passed
	 * through the querystring it will set the report_type sub-template to
	 * force the user to choose which type.
	 * - When generating a report chooses which sub_template to use.
	 * - Depends on the cal_enabled setting, and many of the other cal_ settings.
	 * - Will call the relevant report generation function.
	 * - If generating report will call finishTables before returning.
	 * - Accessed through ?action=admin;area=reports.
	 *
	 * @event integrate_report_types
	 * @event integrate_report_buttons
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context, $scripturl;

		// Only admins, only EVER admins!
		isAllowedTo('admin_forum');

		// Let's get our things running...
		loadTemplate('Reports');
		loadLanguage('Reports');

		$context['page_title'] = $txt['generate_reports'];

		// These are the types of reports which exist - and the functions to generate them.
		$context['report_types'] = array(
			'boards' => 'action_boards',
			'board_perms' => 'action_board_perms',
			'member_groups' => 'action_member_groups',
			'group_perms' => 'action_group_perms',
			'staff' => 'action_staff',
		);

		call_integration_hook('integrate_report_types');

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['generate_reports'],
			'help' => '',
			'description' => $txt['generate_reports_desc'],
		);

		$is_first = 0;
		foreach ($context['report_types'] as $k => $temp)
			$context['report_types'][$k] = array(
				'id' => $k,
				'title' => isset($txt['gr_type_' . $k]) ? $txt['gr_type_' . $k] : $k,
				'description' => isset($txt['gr_type_desc_' . $k]) ? $txt['gr_type_desc_' . $k] : null,
				'function' => $temp,
				'is_first' => $is_first++ == 0,
			);

		$report_type = !empty($this->_req->post->rt) ? $this->_req->post->rt : (!empty($this->_req->query->rt) ? $this->_req->query->rt : null);
		
		// If they haven't chosen a report type which is valid, send them off to the report type chooser!
		if (empty($report_type) || !isset($context['report_types'][$report_type]))
		{
			$context['sub_template'] = 'report_type';
			return;
		}

		$context['report_type'] = $report_type;
		$context['sub_template'] = 'generate_report';

		// What are valid templates for showing reports?
		$reportTemplates = array(
			'main' => array(
				'layers' => null,
			),
			'print' => array(
				'layers' => array('print'),
			),
		);

		// Specific template? Use that instead of main!
		$set_template = isset($this->_req->query->st) ? $this->_req->query->st : null;
		if (isset($set_template) && isset($reportTemplates[$set_template]))
		{
			$context['sub_template'] = $set_template;

			// Are we disabling the other layers - print friendly for example?
			if ($reportTemplates[$set_template]['layers'] !== null)
			{
				$template_layers = Template_Layers::getInstance();
				$template_layers->removeAll();
				foreach ($reportTemplates[$set_template]['layers'] as $layer)
					$template_layers->add($layer);
			}
		}

		// Make the page title more descriptive.
		$context['page_title'] .= ' - ' . (isset($txt['gr_type_' . $context['report_type']]) ? $txt['gr_type_' . $context['report_type']] : $context['report_type']);

		// Build the reports button array.
		$context['report_buttons'] = array(
			'generate_reports' => array(
				'text' => 'generate_reports',
				'image' => 'print.png',
				'lang' => true,
				'url' => $scripturl . '?action=admin;area=reports',
				'active' => true,
			),
			'print' => array(
				'text' => 'print',
				'image' => 'print.png',
				'lang' => true,
				'url' => $scripturl . '?action=admin;area=reports;rt=' . $context['report_type'] . ';st=print',
				'custom' => 'target="_blank"',
			),
		);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_report_buttons');

		// Now generate the data.
		$this->{$context['report_types'][$context['report_type']]['function']}();

		// Finish the tables before exiting - this is to help the templates a little more.
		finishTables();
	}

	/**
	 * Standard report about what settings the boards have.
	 * 
	 * - Functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * - They are all called from action_index.
	 * - Never access the context directly, but use the data handling
	 * functions to do so.
	 */
	public function action_boards()
	{
		global $context, $txt, $modSettings;

		// Load the permission profiles.
		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Reports.subs.php');

		loadLanguage('ManagePermissions');
		loadPermissionProfiles();

		// Get every moderator.
		$moderators = allBoardModerators();

		$boards_moderated = array();
		foreach ($moderators as $id_board => $rows)
		{
			foreach ($rows as $row)
			{
				$boards_moderated[$id_board][] = $row['real_name'];
			}
		}

		// Get all the possible membergroups!
		$all_groups = getBasicMembergroupData(array('all'), array(), null, false);
		$groups = array(-1 => $txt['guest_title'], 0 => $txt['full_member']);
		foreach ($all_groups as $row)
			$groups[$row['id']] = empty($row['online_color']) ? $row['name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['name'] . '</span>';

		// All the fields we'll show.
		$boardSettings = array(
			'category' => $txt['board_category'],
			'parent' => $txt['board_parent'],
			'num_topics' => $txt['board_num_topics'],
			'num_posts' => $txt['board_num_posts'],
			'count_posts' => $txt['board_count_posts'],
			'theme' => $txt['board_theme'],
			'override_theme' => $txt['board_override_theme'],
			'profile' => $txt['board_profile'],
			'moderators' => $txt['board_moderators'],
			'groups' => $txt['board_groups'],
		);

		if (!empty($modSettings['deny_boards_access']))
			$boardSettings['disallowed_groups'] = $txt['board_disallowed_groups'];

		// Do it in columns, it's just easier.
		setKeys('cols');

		// Go through each board!
		$boards = reportsBoardsList();

		foreach ($boards as $row)
		{
			// Each board has it's own table.
			newTable($row['name'], '', 'left', 'auto', 'left', 200, 'left');

			// First off, add in the side key.
			addData($boardSettings);

			// Format the profile name.
			$profile_name = $context['profiles'][$row['id_profile']]['name'];

			// Create the main data array.
			$boardData = array(
				'category' => $row['cat_name'],
				'parent' => $row['parent_name'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'count_posts' => empty($row['count_posts']) ? $txt['yes'] : $txt['no'],
				'theme' => $row['theme_name'],
				'profile' => $profile_name,
				'override_theme' => $row['override_theme'] ? $txt['yes'] : $txt['no'],
				'moderators' => empty($boards_moderated[$row['id_board']]) ? $txt['none'] : implode(', ', $boards_moderated[$row['id_board']]),
			);

			// Work out the membergroups who can and cannot access it (but only if enabled).
			$allowedGroups = explode(',', $row['member_groups']);
			foreach ($allowedGroups as $key => $group)
			{
				if (isset($groups[$group]))
					$allowedGroups[$key] = $groups[$group];
				else
					unset($allowedGroups[$key]);
			}

			$boardData['groups'] = implode(', ', $allowedGroups);

			if (!empty($modSettings['deny_boards_access']))
			{
				$disallowedGroups = explode(',', $row['deny_member_groups']);
				foreach ($disallowedGroups as $key => $group)
				{
					if (isset($groups[$group]))
						$disallowedGroups[$key] = $groups[$group];
					else
						unset($disallowedGroups[$key]);
				}

				$boardData['disallowed_groups'] = implode(', ', $disallowedGroups);
			}

			// Next add the main data.
			addData($boardData);
		}
	}

	/**
	 * Generate a report on the current permissions by board and membergroup.
	 * 
	 * - Functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * - They are all called from action_index.
	 * - Never access the context directly, but use the data handling
	 * functions to do so.
	 */
	public function action_board_perms()
	{
		global $txt;

		// Get as much memory as possible as this can be big.
		detectServer()->setMemoryLimit('256M');

		// Boards, first.
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// Lets get started
		$query_boards = array();

		if (isset($this->_req->post->boards))
		{
			if (!is_array($this->_req->post->boards))
				$query_boards['boards'] = array_map('intval', explode(',', $this->_req->post->boards));
			else
				$query_boards['boards'] = array_map('intval', $this->_req->post->boards);
		}
		else
			$query_boards = 'all';

		// Fetch the board names and profiles.
		// This returns id_board, name, id_profile keys
		$boards = fetchBoardsInfo($query_boards, array('sort_by' => 'id_board', 'selects' => 'permissions'));
		$profiles = array();
		foreach ($boards as $b)
			$profiles[] = $b['id_profile'];

		// Groups, next.
		$query_groups = array();
		if (isset($this->_req->post->groups))
		{
			if (!is_array($this->_req->post->groups))
				$query_groups = array_map('intval', explode(',', $this->_req->post->groups));
			else
				$query_groups = array_map('intval', $this->_req->post->groups);

			$group_clause = 'id_group IN ({array_int:groups})';
		}
		else
			$group_clause = '1=1';

		// Get all the possible membergroups, except admin!
		require_once(SUBSDIR . '/Reports.subs.php');
		$all_groups = allMembergroups($group_clause, $query_groups);

		if (empty($query_groups) || in_array(-1, $query_groups) || in_array(0, $query_groups))
			$member_groups = array('col' => '', -1 => $txt['membergroups_guests'], 0 => $txt['membergroups_members']) + $all_groups;
		else
			$member_groups = array('col' => '') + $all_groups;

		// Make sure that every group is represented - plus in rows!
		setKeys('rows', $member_groups);

		// Permissions, last!
		$boardPermissions = boardPermissions($profiles, $group_clause, $query_groups);
		$permissions = array();
		$board_permissions = array();

		foreach ($boardPermissions as $row)
		{
			foreach ($boards as $id => $board)
				if ($board['id_profile'] == $row['id_profile'])
					$board_permissions[$id][$row['id_group']][$row['permission']] = $row['add_deny'];

			// Make sure we get every permission.
			if (!isset($permissions[$row['permission']]))
			{
				// This will be reused on other boards.
				$permissions[$row['permission']] = array(
					'title' => isset($txt['board_perms_name_' . $row['permission']]) ? $txt['board_perms_name_' . $row['permission']] : $row['permission'],
				);
			}
		}

		// Now cycle through the board permissions array... lots to do ;)
		foreach ($board_permissions as $board => $groups)
		{
			// Create the table for this board first.
			newTable($boards[$board]['name'], 'x', 'all', 'auto', 'center', 200, 'left');

			// Add the header row - shows all the membergroups.
			addData($member_groups);

			// Add the separator.
			addSeparator($txt['board_perms_permission']);

			// Here cycle through all the detected permissions.
			foreach ($permissions as $ID_PERM => $perm_info)
			{
				// Default data for this row.
				$curData = array('col' => $perm_info['title']);

				// Now cycle each membergroup in this set of permissions.
				foreach ($member_groups as $id_group => $name)
				{
					// Don't overwrite the key column!
					if ($id_group === 'col')
						continue;

					$group_permissions = isset($groups[$id_group]) ? $groups[$id_group] : array();

					// Do we have any data for this group?
					if (isset($group_permissions[$ID_PERM]))
					{
						// Set the data for this group to be the local permission.
						$curData[$id_group] = $group_permissions[$ID_PERM];
					}
					// Otherwise means it's set to disallow..
					else
						$curData[$id_group] = 'x';

					// Now actually make the data for the group look right.
					if (empty($curData[$id_group]))
						$curData[$id_group] = '<span class="alert">' . $txt['board_perms_deny'] . '</span>';
					elseif ($curData[$id_group] == 1)
						$curData[$id_group] = '<span class="success">' . $txt['board_perms_allow'] . '</span>';
					else
						$curData[$id_group] = 'x';
				}

				// Now add the data for this permission.
				addData($curData);
			}
		}
	}

	/**
	 * Show what the membergroups are made of.
	 * 
	 * - Functions ending with "Report" are responsible for generating data for reporting.
	 * they are all called from action_index.
	 * - Never access the context directly, but use the data handling functions to do so.
	 */
	public function action_member_groups()
	{
		global $txt, $settings, $modSettings;

		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Reports.subs.php');

		// Fetch all the board names.
		$raw_boards = fetchBoardsInfo('all', array('selects' => 'reports'));
		$boards = array();
		foreach ($raw_boards as $row)
		{
			if (trim($row['member_groups']) == '')
				$groups = array(1);
			else
				$groups = array_merge(array(1), explode(',', $row['member_groups']));

			if (trim($row['deny_member_groups']) == '')
				$denyGroups = array();
			else
				$denyGroups = explode(',', $row['deny_member_groups']);

			$boards[$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'profile' => $row['id_profile'],
				'groups' => $groups,
				'deny_groups' => $denyGroups,
			);
		}

		// Standard settings.
		$mgSettings = array(
			'name' => '',
			'#sep#1' => $txt['member_group_settings'],
			'color' => $txt['member_group_color'],
			'min_posts' => $txt['member_group_min_posts'],
			'max_messages' => $txt['member_group_max_messages'],
			'icons' => $txt['member_group_icons'],
			'#sep#2' => $txt['member_group_access'],
		);

		// Add on the boards!
		foreach ($boards as $board)
			$mgSettings['board_' . $board['id']] = $board['name'];

		// Add all the membergroup settings, plus we'll be adding in columns!
		setKeys('cols', $mgSettings);

		// Only one table this time!
		newTable($txt['gr_type_member_groups'], '-', 'all', 'auto', 'center', 200, 'left');

		// Get the shaded column in.
		addData($mgSettings);

		// Now start cycling the membergroups!
		$rows = allMembergroupsBoardAccess();
		foreach ($rows as $row)
		{
			$row['icons'] = explode('#', $row['icons']);

			$group = array(
				'name' => $row['group_name'],
				'color' => empty($row['online_color']) ? '-' : '<span style="color: ' . $row['online_color'] . ';">' . $row['online_color'] . '</span>',
				'min_posts' => $row['min_posts'] == -1 ? 'N/A' : $row['min_posts'],
				'max_messages' => $row['max_messages'],
				'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $row['icons'][1] . '" alt="*" />', $row['icons'][0]) : '',
			);

			// Board permissions.
			foreach ($boards as $board)
				$group['board_' . $board['id']] = in_array($row['id_group'], $board['groups']) ? '<span class="success">' . $txt['board_perms_allow'] . '</span>' : (!empty($modSettings['deny_boards_access']) && in_array($row['id_group'], $board['deny_groups']) ? '<span class="error">' . $txt['board_perms_deny'] . '</span>' : 'x');

			addData($group);
		}
	}

	/**
	 * Show the large variety of group permissions assigned to each membergroup.
	 * 
	 * - Functions ending with "Report" are responsible for generating data for reporting.
	 * they are all called from action_index.
	 * - Never access the context directly, but use the data handling
	 * functions to do so.
	 */
	public function action_group_perms()
	{
		global $txt;

		if (isset($this->_req->post->groups))
		{
			if (!is_array($this->_req->post->groups))
				$this->_req->post->groups = explode(',', $this->_req->post->groups);

			$query_groups = array_diff(array_map('intval', $this->_req->post->groups), array(3));
			$group_clause = 'id_group IN ({array_int:groups})';
		}
		else
		{
			$query_groups = array();
			$group_clause = 'id_group != {int:moderator_group}';
		}

		// Get all the possible membergroups, except admin!
		require_once(SUBSDIR . '/Reports.subs.php');
		$all_groups = allMembergroups($group_clause, $query_groups);

		if (!isset($this->_req->post->groups) || in_array(-1, $this->_req->post->groups) || in_array(0, $this->_req->post->groups))
			$groups = array('col' => '', -1 => $txt['membergroups_guests'], 0 => $txt['membergroups_members']) + $all_groups;
		else
			$groups = array('col' => '') + $all_groups;

		// Make sure that every group is represented!
		setKeys('rows', $groups);

		// Create the table first.
		newTable($txt['gr_type_group_perms'], '-', 'all', 'auto', 'center', 200, 'left');

		// Show all the groups
		addData($groups);

		// Add a separator
		addSeparator($txt['board_perms_permission']);

		// Now the big permission fetch!
		$perms = boardPermissionsByGroup($group_clause, isset($this->_req->post->groups) ? $this->_req->post->groups : array());
		$lastPermission = null;
		$curData = array();
		foreach ($perms as $row)
		{
			// If this is a new permission flush the last row.
			if ($row['permission'] != $lastPermission)
			{
				// Send the data!
				if ($lastPermission !== null)
					addData($curData);

				// Add the permission name in the left column.
				$curData = array('col' => isset($txt['group_perms_name_' . $row['permission']]) ? $txt['group_perms_name_' . $row['permission']] : $row['permission']);

				$lastPermission = $row['permission'];
			}

			// Good stuff - add the permission to the list!
			if ($row['add_deny'])
				$curData[$row['id_group']] = '<span class="success">' . $txt['board_perms_allow'] . '</span>';
			else
				$curData[$row['id_group']] = '<span class="alert">' . $txt['board_perms_deny'] . '</span>';
		}

		// Flush the last data!
		addData($curData);
	}

	/**
	 * Report for showing all the forum staff members - quite a feat!
	 * 
	 * - Functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * - They are all called from action_index.
	 * - Never access the context directly, but use the data handling
	 * functions to do so.
	 */
	public function action_staff()
	{
		global $txt;

		require_once(SUBSDIR . '/Members.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// Fetch all the board names.
		$boards = fetchBoardsInfo('all');
		$moderators = allBoardModerators(true);
		$boards_moderated = array();

		foreach ($moderators as $id_member => $rows)
			foreach ($rows as $row)
				$boards_moderated[$id_member][] = $row['id_board'];

		// Get a list of global moderators (i.e. members with moderation powers).
		$global_mods = array_intersect(membersAllowedTo('moderate_board', 0), membersAllowedTo('approve_posts', 0), membersAllowedTo('remove_any', 0), membersAllowedTo('modify_any', 0));

		// How about anyone else who is special?
		$allStaff = array_merge(membersAllowedTo('admin_forum'), membersAllowedTo('manage_membergroups'), membersAllowedTo('manage_permissions'), array_keys($moderators), $global_mods);

		// Make sure everyone is there once - no admin less important than any other!
		$allStaff = array_unique($allStaff);

		// This is a bit of a cop out - but we're protecting their forum, really!
		if (count($allStaff) > 300)
			throw new Elk_Exception('report_error_too_many_staff');

		// Get all the possible membergroups!
		$all_groups = getBasicMembergroupData(array('all'), array(), null, false);
		$groups = array(0 => $txt['full_member']);
		foreach ($all_groups as $row)
			$groups[$row['id']] = empty($row['online_color']) ? $row['name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['name'] . '</span>';

		// All the fields we'll show.
		$staffSettings = array(
			'position' => $txt['report_staff_position'],
			'moderates' => $txt['report_staff_moderates'],
			'posts' => $txt['report_staff_posts'],
			'last_login' => $txt['report_staff_last_login'],
		);

		// Do it in columns, it's just easier.
		setKeys('cols');

		// Get the latest activated member's display name.
		$result = getBasicMemberData($allStaff, array('moderation' => true, 'sort' => 'real_name'));
		foreach ($result as $row)
		{
			// Each member gets their own table!.
			newTable($row['real_name'], '', 'left', 'auto', 'left', 200, 'center');

			// First off, add in the side key.
			addData($staffSettings);

			// Create the main data array.
			$staffData = array(
				'position' => isset($groups[$row['id_group']]) ? $groups[$row['id_group']] : $groups[0],
				'posts' => $row['posts'],
				'last_login' => standardTime($row['last_login']),
				'moderates' => array(),
			);

			// What do they moderate?
			if (in_array($row['id_member'], $global_mods))
				$staffData['moderates'] = '<em>' . $txt['report_staff_all_boards'] . '</em>';
			elseif (isset($boards_moderated[$row['id_member']]))
			{
				// Get the names
				foreach ($boards_moderated[$row['id_member']] as $board)
					if (isset($boards[$board]))
						$staffData['moderates'][] = $boards[$board]['name'];

				$staffData['moderates'] = implode(', ', $staffData['moderates']);
			}
			else
				$staffData['moderates'] = '<em>' . $txt['report_staff_no_boards'] . '</em>';

			// Next add the main data.
			addData($staffData);
		}
	}
}

/**
 * This function creates a new table of data, most functions will only use it once.
 * 
 * What it does:
 * 
 * - The core of this file, it creates a new, but empty, table of data in
 * context, ready for filling using addData().
 * - Fills the context variable current_table with the ID of the table created.
 * - Keeps track of the current table count using context variable table_count.
 *
 * @param string $title = '' Title to be displayed with this data table.
 * @param string $default_value = '' Value to be displayed if a key is missing from a row.
 * @param string $shading = 'all' Should the left, top or both (all) parts of the table beshaded?
 * @param string $width_normal = 'auto' width of an unshaded column (auto means not defined).
 * @param string $align_normal = 'center' alignment of data in an unshaded column.
 * @param string $width_shaded = 'auto' width of a shaded column (auto means not defined).
 * @param string $align_shaded = 'auto' alignment of data in a shaded column.
 */
function newTable($title = '', $default_value = '', $shading = 'all', $width_normal = 'auto', $align_normal = 'center', $width_shaded = 'auto', $align_shaded = 'auto')
{
	global $context;

	// Set the table count if needed.
	if (empty($context['table_count']))
		$context['table_count'] = 0;

	// Create the table!
	$context['tables'][$context['table_count']] = array(
		'title' => $title,
		'default_value' => $default_value,
		'shading' => array(
			'left' => $shading === 'all' || $shading === 'left',
			'top' => $shading === 'all' || $shading === 'top',
		),
		'width' => array(
			'normal' => $width_normal,
			'shaded' => $width_shaded,
		),
		'align' => array(
			'normal' => $align_normal,
			'shaded' => $align_shaded,
		),
		'data' => array(),
	);

	$context['current_table'] = $context['table_count'];

	// Increment the count...
	$context['table_count']++;
}

/**
 * Adds an array of data into an existing table.
 * 
 * What it does:
 * 
 * - If there are no existing tables, will create one with default attributes.
 * - If custom_table isn't specified, it will use the last table created,
 * - If it is specified and doesn't exist the function will return false.
 * - If a set of keys have been specified, the function will check each
 * required key is present in the incoming data. If this data is missing
 * the current tables default value will be used.
 * - If any key in the incoming data begins with '#sep#', the function
 * will add a separator across the table at this point.
 * once the incoming data has been sanitized, it is added to the table.
 *
 * @param mixed[] $inc_data
 * @param int|null $custom_table = null
 */
function addData($inc_data, $custom_table = null)
{
	global $context;

	// No tables? Create one even though we are probably already in a bad state!
	if (empty($context['table_count']))
		newTable();

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$custom_table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// If we have keys, sanitise the data...
	$data = array();
	if (!empty($context['keys']))
	{
		// Basically, check every key exists!
		foreach ($context['keys'] as $key => $dummy)
		{
			$data[$key] = array(
				'v' => empty($inc_data[$key]) ? $context['tables'][$table]['default_value'] : $inc_data[$key],
			);
			// Special "hack" the adding separators when doing data by column.
			if (substr($key, 0, 5) === '#sep#')
				$data[$key]['separator'] = true;
		}
	}
	else
	{
		$data = $inc_data;
		foreach ($data as $key => $value)
		{
			$data[$key] = array(
				'v' => $value,
			);

			if (substr($key, 0, 5) === '#sep#')
				$data[$key]['separator'] = true;
		}
	}

	// Is it by row?
	if (empty($context['key_method']) || $context['key_method'] === 'rows')
	{
		// Add the data!
		$context['tables'][$table]['data'][] = $data;
	}
	// Otherwise, tricky!
	else
	{
		foreach ($data as $key => $item)
			$context['tables'][$table]['data'][$key][] = $item;
	}
}

/**
 * Add a separator row, only really used when adding data by rows.
 *
 * @param string $title = ''
 * @param string|null $custom_table = null
 *
 * @return null|false false if there are no tables
 */
function addSeparator($title = '', $custom_table = null)
{
	global $context;

	// No tables - return?
	if (empty($context['table_count']))
		return false;

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$custom_table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// Plumb in the separator
	$context['tables'][$table]['data'][] = array(
		0 => array(
			'separator' => true,
			'v' => $title
		)
	);
}

/**
 * This does the necessary count of table data before displaying them.
 * 
 * - Is (unfortunately) required to create some useful variables for templates.
 * - Foreach data table created, it will count the number of rows and
 * columns in the table.
 * - Will also create a max_width variable for the table, to give an
 * estimate width for the whole table * * if it can.
 */
function finishTables()
{
	global $context;

	if (empty($context['tables']))
		return;

	// Loop through each table counting up some basic values, to help with the templating.
	foreach ($context['tables'] as $id => $table)
	{
		$context['tables'][$id]['id'] = $id;
		$context['tables'][$id]['row_count'] = count($table['data']);
		$curElement = current($table['data']);
		$context['tables'][$id]['column_count'] = count($curElement);

		// Work out the rough width - for templates like the print template. Without this we might get funny tables.
		if ($table['shading']['left'] && $table['width']['shaded'] !== 'auto' && $table['width']['normal'] !== 'auto')
			$context['tables'][$id]['max_width'] = $table['width']['shaded'] + ($context['tables'][$id]['column_count'] - 1) * $table['width']['normal'];
		elseif ($table['width']['normal'] !== 'auto')
			$context['tables'][$id]['max_width'] = $context['tables'][$id]['column_count'] * $table['width']['normal'];
		else
			$context['tables'][$id]['max_width'] = 'auto';
	}
}

/**
 * Set the keys in use by the tables - these ensure entries MUST exist if the data isn't sent
 * 
 * What it does:
 * 
 * - Sets the current set of "keys" expected in each data array passed to
 * addData.
 * - It also sets the way we are adding data to the data table.
 * - Method specifies whether the data passed to addData represents a new
 * column, or a new row.
 * - Keys is an array whose keys are the keys for data being passed to addData().
 * - If reverse is set to true, then the values of the variable "keys"
 * are used as opposed to the keys(!
 *
 * @param string $method = 'rows' rows or cols
 * @param mixed[] $keys = array()
 * @param bool $reverse = false
 */
function setKeys($method = 'rows', $keys = array(), $reverse = false)
{
	global $context;

	// Do we want to use the keys of the keys as the keys? :P
	if ($reverse)
		$context['keys'] = array_flip($keys);
	else
		$context['keys'] = $keys;

	// Rows or columns?
	$context['key_method'] = $method === 'rows' ? 'rows' : 'cols';
}