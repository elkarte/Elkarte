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
 * Handle online users
 *
 */
if (!defined('ELKARTE'))
	die('No access...');

/**
 * Reads the custom profile fields table and gets all items that were defined
 * as being shown on the memberlist
 *  - Loads the fields in to $context['custom_profile_fields']
 *  - Defines the sort querys for the custom columns
 *  - Defines additional query parameters and joins needed to the memberlist
 *
 */
function ml_CustomProfile()
{
	global $smcFunc, $context;

	$context['custom_profile_fields'] = array();

	// Find any custom profile fields that are to be shown for the memberlist?
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_desc, field_type, bbc, enclose
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			AND show_memberlist = {int:show}
			AND private < {int:private_level}',
		array(
			'active' => 1,
			'show' => 1,
			'private_level' => 2,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Avoid collisions
		$curField = 'cust_' . $row['col_name'];

		// Load the standard column info
		$context['custom_profile_fields']['columns'][$curField] = array(
			'label' => $row['field_name'],
			'type' => $row['field_type'],
			'bbc' => !empty($row['bbc']),
			'enclose' => $row['enclose'],
		);

		// Have they selected to sort on a custom column? .., then we build the query
		if (isset($_REQUEST['sort']) && $_REQUEST['sort'] === $curField)
		{
			// Build the sort queries.
			if ($row['field_type'] != 'check')
				$context['custom_profile_fields']['columns'][$curField]['sort'] = array(
					'down' => 'LENGTH(cfd' . $curField . '.value) > 0 ASC, IFNULL(cfd' . $curField . '.value, 1=1) DESC, cfd' . $curField . '.value DESC',
					'up' => 'LENGTH(cfd' . $curField . '.value) > 0 DESC, IFNULL(cfd' . $curField . '.value, 1=1) ASC, cfd' . $curField . '.value ASC'
				);
			else
				$context['custom_profile_fields']['columns'][$curField]['sort'] = array(
					'down' => 'cfd' . $curField . '.value DESC',
					'up' => 'cfd' . $curField . '.value ASC'
				);

			// Build the join and parameters for the sort query
			$context['custom_profile_fields']['join'] = 'LEFT JOIN {db_prefix}custom_fields_data AS cfd' . $curField . ' ON (cfd' . $curField . '.variable = {string:cfd' . $curField . '} AND cfd' . $curField . '.id_theme = 1 AND cfd' . $curField . '.id_member = mem.id_member)';
			$context['custom_profile_fields']['parameters']['cfd' . $curField] = $row['col_name'];
		}
	}
	$smcFunc['db_free_result']($request);

	return !empty($context['custom_profile_fields']);
}

/**
 * Sets scope pointers based on the current active memberlist
 *   - Sets pointers on a $cache_step_size pitch, always including the last record
 *   - Pointers are later used to limit the member data retrieval
 *
 * @param int $cache_step_size
 */
function ml_memberCache($cache_step_size)
{
	global $smcFunc;

	// Get all of the activated members
	$request = $smcFunc['db_query']('', '
		SELECT real_name
		FROM {db_prefix}members
		WHERE is_activated = {int:is_activated}
		ORDER BY real_name',
		array(
			'is_activated' => 1,
		)
	);

	$memberlist_cache = array(
		'last_update' => time(),
		'num_members' => $smcFunc['db_num_rows']($request),
		'index' => array(),
	);

	// Get/Set our pointers in this list, used to later help limit our query
	for ($i = 0, $n = $smcFunc['db_num_rows']($request); $i < $n; $i += $cache_step_size)
	{
		$smcFunc['db_data_seek']($request, $i);
		list($memberlist_cache['index'][$i]) = $smcFunc['db_fetch_row']($request);
	}

	// Set the last one
	$smcFunc['db_data_seek']($request, $memberlist_cache['num_members'] - 1);
	list ($memberlist_cache['index'][$i]) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Now we've got the cache...store it.
	updateSettings(array('memberlist_cache' => serialize($memberlist_cache)));

	return $memberlist_cache;
}

/**
 * Counts the number of active members in the system
 */
function ml_memberCount()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
		)
	);
	list ($num_members) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_members;
}

/**
 * Get all all the members who's name starts below a given letter
 *
 * @param char $start
 */
function ml_alphaStart($start)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('substring', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE LOWER(SUBSTRING(real_name, 1, 1)) < {string:first_letter}
			AND is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
			'first_letter' => $start,
		)
	);
	list ($start) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $start;
}

/**
 * Primary query for the memberlist display, runs the query based on the users
 * sort and start selections.
 *
 * @param array $query_parameters
 * @param string $where
 * @param int $limit
 * @param string $sort
 */
function ml_selectMembers($query_parameters, $where = '', $limit = 0, $sort = '')
{
	global $context, $modSettings, $smcFunc;

	// Select the members from the database.
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem' . ($sort === 'is_online' ? '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)' : ($sort === 'id_group' ? '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' : '')) . '
			' . (!empty($context['custom_profile_fields']['join']) ? $context['custom_profile_fields']['join'] : '') . '
		WHERE mem.is_activated = {int:is_activated}' . (empty($where) ? '' : '
			AND ' . $where) . '
		ORDER BY {raw:sort}
		LIMIT ' . $limit . ', ' . $modSettings['defaultMaxMembers'],
		$query_parameters
	);

	printMemberListRows($request);
	$smcFunc['db_free_result']($request);
}

/**
 * Primary query for the memberlist display, runs the query based on the users
 * sort and start selections.
 *  - Uses printMemberListRows to load the query results in to context
 *
 * @param array $query_parameters
 * @param string $where
 * @param int $limit
 * @param string $sort
 */
function ml_searchMembers($query_parameters, $customJoin= '', $where = '', $limit = 0)
{
	global $modSettings, $smcFunc;

	// Get the number of results
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
			(empty($customJoin) ? '' : implode('
			', $customJoin)) . '
		WHERE (' . $where . ')
			AND mem.is_activated = {int:is_activated}',
		$query_parameters
	);
	list ($numResults) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Select the members from the database.
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
			(empty($customJoin) ? '' : implode('
			', $customJoin)) . '
		WHERE (' . $where . ')
			AND mem.is_activated = {int:is_activated}
		ORDER BY {raw:sort}
		LIMIT ' . $limit . ', ' . $modSettings['defaultMaxMembers'],
		$query_parameters
	);

	// Place everything context so the template can use it
	printMemberListRows($request);
	$smcFunc['db_free_result']($request);

	return $numResults;
}

/**
 * Finds custom profile fields that were defined as searchable
 */
function ml_findSearchableCustomFields()
{
	global $smcFunc, $context;

	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_desc
			FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			' . (allowedTo('admin_forum') ? '' : ' AND private < {int:private_level}') . '
			AND can_search = {int:can_search}
			AND (field_type = {string:field_type_text} OR field_type = {string:field_type_textarea})',
		array(
			'active' => 1,
			'can_search' => 1,
			'private_level' => 2,
			'field_type_text' => 'text',
			'field_type_textarea' => 'textarea',
		)
	);
	$context['custom_search_fields'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['custom_search_fields'][$row['col_name']] = array(
			'colname' => $row['col_name'],
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Retrieves results of the request passed to it
 * Puts results of request into the context for the sub template.
 *
 * @param resource $request
 */
function printMemberListRows($request)
{
	global $txt, $context, $scripturl, $memberContext, $settings, $smcFunc;

	// Get the max post number for the bar graph
	$result = $smcFunc['db_query']('', '
		SELECT MAX(posts)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($most_posts) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Avoid division by zero...
	if ($most_posts == 0)
		$most_posts = 1;

	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = $row['id_member'];

	// Load all the members for display.
	loadMemberData($members);

	$context['members'] = array();
	foreach ($members as $member)
	{
		if (!loadMemberContext($member))
			continue;

		$context['members'][$member] = $memberContext[$member];
		$context['members'][$member]['post_percent'] = round(($context['members'][$member]['real_posts'] * 100) / $most_posts);
		$context['members'][$member]['registered_date'] = strftime('%Y-%m-%d', $context['members'][$member]['registered_timestamp']);

		// Take care of the custom fields if any are being displayed
		if (!empty($context['custom_profile_fields']['columns']))
		{
			foreach ($context['custom_profile_fields']['columns'] as $key => $column)
			{
				$curField = substr($key, 5);

				// Does this member even have it filled out?
				if (!isset($context['members'][$member]['options'][$curField]))
				{
					$context['members'][$member]['options'][$curField] = '';
					continue;
				}

				// Should it be enclosed for display?
				if (!empty($column['enclose']))
					$context['members'][$member]['options'][$curField] = strtr($column['enclose'], array(
						'{SCRIPTURL}' => $scripturl,
						'{IMAGES_URL}' => $settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
						'{INPUT}' => $context['members'][$member]['options'][$curField],
					));

				// Anything else to make it look "nice"
				if ($column['bbc'])
					$context['members'][$member]['options'][$curField] = strip_tags(parse_bbc($context['members'][$member]['options'][$curField]));
				elseif ($column['type'] === 'check')
					$context['members'][$member]['options'][$curField] = $context['members'][$member]['options'][$curField] == 0 ? $txt['no'] : $txt['yes'];
			}
		}
	}
}