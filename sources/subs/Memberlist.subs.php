<?php

/**
 * Handle memberlist functions
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

use BBC\ParserWrapper;
use ElkArte\MembersList;
use Elkarte\Util;

/**
 * Reads the custom profile fields table and gets all items that were defined
 * as being shown on the memberlist
 *  - Loads the fields in to $context['custom_profile_fields']
 *  - Defines the sort querys for the custom columns
 *  - Defines additional query parameters and joins needed to the memberlist
 */
function ml_CustomProfile()
{
	global $context;

	$db = database();

	$context['custom_profile_fields'] = array();

	// Find any custom profile fields that are to be shown for the memberlist?
	$db->fetchQuery('
		SELECT 
			col_name, field_name, field_desc, field_type, bbc, enclose, vieworder, default_value, field_options
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			AND show_memberlist = {int:show}
			AND private < {int:private_level}
		ORDER BY vieworder',
		array(
			'active' => 1,
			'show' => 1,
			'private_level' => 2,
		)
	)->fetch_callback(
		function ($row) {
			global $context;

			// Avoid collisions
			$curField = 'cust_' . $row['col_name'];

			// Load the standard column info
			$context['custom_profile_fields']['columns'][$curField] = array(
				'label' => $row['field_name'],
				'class' => $row['field_name'],
				'type' => $row['field_type'],
				'bbc' => !empty($row['bbc']),
				'enclose' => $row['enclose'],
				'default_value' => $row['default_value'],
				'field_options' => explode(',', $row['field_options']),
			);

			// Have they selected to sort on a custom column? .., then we build the query
			if (isset($_REQUEST['sort']) && $_REQUEST['sort'] === $curField)
			{
				// Build the sort queries.
				if ($row['field_type'] != 'check')
				{
					$context['custom_profile_fields']['columns'][$curField]['sort'] = array(
						'down' => 'LENGTH(cfd' . $curField . '.value) > 0 ASC, COALESCE(cfd' . $curField . '.value, 1=1) DESC, cfd' . $curField . '.value DESC',
						'up' => 'LENGTH(cfd' . $curField . '.value) > 0 DESC, COALESCE(cfd' . $curField . '.value, 1=1) ASC, cfd' . $curField . '.value ASC'
					);
				}
				else
				{
					$context['custom_profile_fields']['columns'][$curField]['sort'] = array(
						'down' => 'cfd' . $curField . '.value DESC',
						'up' => 'cfd' . $curField . '.value ASC'
					);
				}

				// Build the join and parameters for the sort query
				$context['custom_profile_fields']['join'] = 'LEFT JOIN {db_prefix}custom_fields_data AS cfd' . $curField . ' ON (cfd' . $curField . '.variable = {string:cfd' . $curField . '} AND cfd' . $curField . '.id_member = mem.id_member)';
				$context['custom_profile_fields']['parameters']['cfd' . $curField] = $row['col_name'];
			}
		}
	);

	return !empty($context['custom_profile_fields']);
}

/**
 * Sets scope pointers based on the current active memberlist
 *   - Sets pointers on a $cache_step_size pitch, always including the last record
 *   - Pointers are later used to limit the member data retrieval
 *
 * @param int $cache_step_size
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 */
function ml_memberCache($cache_step_size)
{
	// Get hold of our database
	$db = database();

	// Get all of the activated members
	$request = $db->query('', '
		SELECT 
			real_name
		FROM {db_prefix}members
		WHERE is_activated = {int:is_activated}
		ORDER BY real_name',
		array(
			'is_activated' => 1,
		)
	);

	$memberlist_cache = array(
		'last_update' => time(),
		'num_members' => $request->num_rows(),
		'index' => array(),
	);

	// Get/Set our pointers in this list, used to later help limit our query
	for ($i = 0, $n = $request->num_rows(); $i < $n; $i += $cache_step_size)
	{
		$request->data_seek($i);
		list ($memberlist_cache['index'][$i]) = $request->fetch_row();
	}

	// Set the last one
	$request->data_seek($memberlist_cache['num_members'] - 1);
	list ($memberlist_cache['index'][$i]) = $request->fetch_row();
	$request->free_result();

	// Now we've got the cache...store it.
	updateSettings(array('memberlist_cache' => serialize($memberlist_cache)));

	return $memberlist_cache;
}

/**
 * Counts the number of active members in the system
 */
function ml_memberCount()
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}members
		WHERE is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
		)
	);
	list ($num_members) = $request->fetch_row();
	$request->free_result();

	return $num_members;
}

/**
 * Get all all the members who's name starts below a given letter
 *
 * @param string $start single letter to start with
 *
 * @return string
 * @throws \ElkArte\Exceptions\Exception
 */
function ml_alphaStart($start)
{
	$db = database();

	$request = $db->fetchQuery('
		SELECT 
			COUNT(*)
		FROM {db_prefix}members
		WHERE LOWER(SUBSTRING(real_name, 1, 1)) < {string:first_letter}
			AND is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
			'first_letter' => $start,
		)
	);
	list ($start) = $request->fetch_row();
	$request->free_result();

	return $start;
}

/**
 * Primary query for the memberlist display, runs the query based on the users
 * sort and start selections.
 *
 * @param mixed[] $query_parameters
 * @param string $where
 * @param int $limit
 * @param string $sort
 * @throws \ElkArte\Exceptions\Exception
 */
function ml_selectMembers($query_parameters, $where = '', $limit = 0, $sort = '')
{
	global $context, $modSettings;

	$db = database();

	// Select the members from the database.
	$request = $db->query('', '
		SELECT 
			mem.id_member
		FROM {db_prefix}members AS mem' . ($sort === 'online' ? '
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
	$request->free_result();
}

/**
 * Primary query for the memberlist display, runs the query based on the users
 * sort and start selections.
 *  - Uses printMemberListRows to load the query results in to context
 *
 * @param mixed[] $query_parameters
 * @param string|string[]|null $customJoin
 * @param string $where
 * @param int $limit
 * @return int
 * @throws \ElkArte\Exceptions\Exception
 */
function ml_searchMembers($query_parameters, $customJoin = '', $where = '', $limit = 0)
{
	global $modSettings;

	$db = database();

	// Get the number of results
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)
			' . (empty($customJoin) ? '' : implode('
			', $customJoin)) . '
		WHERE (' . $where . ')
			AND mem.is_activated = {int:is_activated}',
		$query_parameters
	);
	list ($numResults) = $request->fetch_row();
	$request->free_result();

	// Select the members from the database.
	$request = $db->query('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)
			' . (empty($customJoin) ? '' : implode('
			', $customJoin)) . '
		WHERE (' . $where . ')
			AND mem.is_activated = {int:is_activated}
		ORDER BY {raw:sort}
		LIMIT ' . $limit . ', ' . $modSettings['defaultMaxMembers'],
		$query_parameters
	);

	// Place everything context so the template can use it
	printMemberListRows($request);
	$request->free_result();

	return $numResults;
}

/**
 * Finds custom profile fields that were defined as searchable
 */
function ml_findSearchableCustomFields()
{
	global $context;

	$db = database();

	$context['custom_search_fields'] = array();
	$db->fetchQuery('
		SELECT 
			col_name, field_name, field_desc
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			' . (allowedTo('admin_forum') ? '' : ' AND private < {int:private_level}') . '
			AND can_search = {int:can_search}
			AND (field_type IN ({string:field_type_text}, {string:field_type_textarea}, {string:field_type_select}))',
		array(
			'active' => 1,
			'can_search' => 1,
			'private_level' => 2,
			'field_type_text' => 'text',
			'field_type_textarea' => 'textarea',
			'field_type_select' => 'select',
		)
	)->fetch_callback(
		function ($row) {
			global $context;

			$context['custom_search_fields'][$row['col_name']] = array(
				'colname' => $row['col_name'],
				'name' => $row['field_name'],
				'desc' => $row['field_desc'],
			);
		}
	);
}

/**
 * Retrieves results of the request passed to it
 * Puts results of request into the context for the sub template.
 *
 * @param resource $request
 * @throws \ElkArte\Exceptions\Exception
 */
function printMemberListRows($request)
{
	global $txt, $context, $scripturl, $settings;

	$db = database();

	// Get the max post number for the bar graph
	$result = $db->query('', '
		SELECT 
			MAX(posts)
		FROM {db_prefix}members',
		array()
	);
	list ($most_posts) = $result->fetch_row();
	$result->free_result();

	// Avoid division by zero...
	if ($most_posts == 0)
	{
		$most_posts = 1;
	}

	$members = array();
	while (($row = $request->fetch_assoc($request)))
	{
		$members[] = $row['id_member'];
	}

	// Load all the members for display.
	MembersList::load($members);

	$bbc_parser = ParserWrapper::instance();

	$context['members'] = array();
	foreach ($members as $member)
	{
		$member_context = MembersList::get($member);
		$member_context->loadContext(true);
		if ($member_context->isEmpty())
		{
			continue;
		}

		$context['members'][$member] = $member_context;
		$context['members'][$member]['post_percent'] = round(($context['members'][$member]['real_posts'] * 100) / $most_posts);
		$context['members'][$member]['registered_date'] = Util::strftime('%Y-%m-%d', $context['members'][$member]['registered_timestamp']);
		$context['members'][$member]['real_name'] = $context['members'][$member]['link'];
		$context['members'][$member]['avatar'] = '<a href="' . $context['members'][$member]['href'] . '">' . $context['members'][$member]['avatar']['image'] . '</a>';
		$context['members'][$member]['email_address'] = $context['members'][$member]['email'];
		$context['members'][$member]['website_url'] = $context['members'][$member]['website']['url'] != '' ? '<a href="' . $context['members'][$member]['website']['url'] . '" target="_blank" rel="noopener noreferrer" class="new_win"><i class="icon i-website" title="' . $context['members'][$member]['website']['title'] . '" title="' . $context['members'][$member]['website']['title'] . '"></i></a>' : '';
		$context['members'][$member]['id_group'] = empty($context['members'][$member]['group']) ? $context['members'][$member]['post_group'] : $context['members'][$member]['group'];
		$context['members'][$member]['date_registered'] = $context['members'][$member]['registered'];

		$member_options = $context['members'][$member]['options'];
		// Take care of the custom fields if any are being displayed
		if (!empty($context['custom_profile_fields']['columns']))
		{
			foreach ($context['custom_profile_fields']['columns'] as $key => $column)
			{
				$curField = substr($key, 5);

				// Does this member even have it filled out?
				if (!isset($member_options[$curField]) && $context['custom_profile_fields']['columns'][$key]['default_value'] === '')
				{
					$member_options[$curField] = '';
					continue;
				}
				// Otherwise use the default value
				if (!isset($member_options[$curField]))
				{
					$member_options[$curField] = $context['custom_profile_fields']['columns'][$key]['default_value'];
					$member_options[$curField . '_key'] = $curField . '_0';
				}

				// Should it be enclosed for display?
				if (!empty($column['enclose']) && !empty($member_options[$curField]))
				{
					$replacements = array(
						'{SCRIPTURL}' => $scripturl,
						'{IMAGES_URL}' => $settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
						'{INPUT}' => $member_options[$curField],
					);
					if (in_array($column['type'], array('radio', 'select')))
					{
						$replacements['{KEY}'] = $member_options[$curField . '_key'];
					}
					$member_options[$curField] = strtr($column['enclose'], $replacements);
				}

				// Anything else to make it look "nice"
				if ($column['bbc'])
				{
					$member_options[$curField] = strip_tags($bbc_parser->parseCustomFields($member_options[$curField]));
				}
				elseif ($column['type'] === 'check')
				{
					$member_options[$curField] = $member_options[$curField] == 0 ? $txt['no'] : $txt['yes'];
				}
			}
		}
		$context['members'][$member]['options'] = $member_options;
	}
}
