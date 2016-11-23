<?php

/**
 * This file contains the functions to add, modify, remove, collapse and expand categories.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Edit the position and properties of a category.
 * general function to modify the settings and position of a category.
 * used by ManageBoards.controller.php to change the settings of a category.
 *
 * @param int $category_id
 * @param mixed[] $catOptions
 */
function modifyCategory($category_id, $catOptions)
{
	$db = database();

	$catUpdates = array();
	$catParameters = array();

	$cat_id = $category_id;
	call_integration_hook('integrate_pre_modify_category', array($cat_id, &$catOptions));

	// Wanna change the categories position?
	if (isset($catOptions['move_after']))
	{
		// Store all categories in the proper order.
		$cats = array();
		$cat_order = array();

		// Setting 'move_after' to '0' moves the category to the top.
		if ($catOptions['move_after'] == 0)
			$cats[] = $category_id;

		// Grab the categories sorted by cat_order.
		$request = $db->query('', '
			SELECT id_cat, cat_order
			FROM {db_prefix}categories
			ORDER BY cat_order',
			array(
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['id_cat'] != $category_id)
				$cats[] = $row['id_cat'];
			if ($row['id_cat'] == $catOptions['move_after'])
				$cats[] = $category_id;
			$cat_order[$row['id_cat']] = $row['cat_order'];
		}
		$db->free_result($request);

		// Set the new order for the categories.
		foreach ($cats as $index => $cat)
			if ($index != $cat_order[$cat])
				$db->query('', '
					UPDATE {db_prefix}categories
					SET cat_order = {int:new_order}
					WHERE id_cat = {int:current_category}',
					array(
						'new_order' => $index,
						'current_category' => $cat,
					)
				);

		// If the category order changed, so did the board order.
		require_once(SUBSDIR . '/Boards.subs.php');
		reorderBoards();
	}

	if (isset($catOptions['cat_name']))
	{
		$catUpdates[] = 'name = {string:cat_name}';
		$catParameters['cat_name'] = $catOptions['cat_name'];
	}

	// Can a user collapse this category or is it too important?
	if (isset($catOptions['is_collapsible']))
	{
		$catUpdates[] = 'can_collapse = {int:is_collapsible}';
		$catParameters['is_collapsible'] = $catOptions['is_collapsible'] ? 1 : 0;
	}

	$cat_id = $category_id;
	call_integration_hook('integrate_modify_category', array($cat_id, &$catUpdates, &$catParameters));

	// Do the updates (if any).
	if (!empty($catUpdates))
	{
		$db->query('', '
			UPDATE {db_prefix}categories
			SET
				' . implode(',
				', $catUpdates) . '
			WHERE id_cat = {int:current_category}',
			array_merge($catParameters, array(
				'current_category' => $category_id,
			))
		);

		if (empty($catOptions['dont_log']))
			logAction('edit_cat', array('catname' => isset($catOptions['cat_name']) ? $catOptions['cat_name'] : $category_id), 'admin');
	}
}

/**
 * Create a new category.
 * general function to create a new category and set its position.
 * allows (almost) the same options as the modifyCat() function.
 * returns the ID of the newly created category.
 *
 * @param mixed[] $catOptions
 */
function createCategory($catOptions)
{
	$db = database();

	// Check required values.
	if (!isset($catOptions['cat_name']) || trim($catOptions['cat_name']) == '')
		trigger_error('createCategory(): A category name is required', E_USER_ERROR);

	// Set default values.
	if (!isset($catOptions['move_after']))
		$catOptions['move_after'] = 0;
	if (!isset($catOptions['is_collapsible']))
		$catOptions['is_collapsible'] = true;
	// Don't log an edit right after.
	$catOptions['dont_log'] = true;

	$cat_columns = array(
		'name' => 'string-48',
	);
	$cat_parameters = array(
		$catOptions['cat_name'],
	);

	call_integration_hook('integrate_create_category', array(&$catOptions, &$cat_columns, &$cat_parameters));

	// Add the category to the database.
	$db->insert('',
		'{db_prefix}categories',
		$cat_columns,
		$cat_parameters,
		array('id_cat')
	);

	// Grab the new category ID.
	$category_id = $db->insert_id('{db_prefix}categories', 'id_cat');

	// Set the given properties to the newly created category.
	modifyCategory($category_id, $catOptions);

	logAction('add_cat', array('catname' => $catOptions['cat_name']), 'admin');

	// Return the database ID of the category.
	return $category_id;
}

/**
 * Remove one or more categories.
 * general function to delete one or more categories.
 * allows to move all boards in the categories to a different category before deleting them.
 * if moveBoardsTo is set to null, all boards inside the given categories will be deleted.
 * deletes all information that's associated with the given categories.
 * updates the statistics to reflect the new situation.
 *
 * @param int[] $categories
 * @param integer|null $moveBoardsTo = null
 */
function deleteCategories($categories, $moveBoardsTo = null)
{
	global $cat_tree;

	$db = database();

	require_once(SUBSDIR . '/Boards.subs.php');

	getBoardTree();

	call_integration_hook('integrate_delete_category', array($categories, &$moveBoardsTo));

	// With no category set to move the boards to, delete them all.
	if ($moveBoardsTo === null)
	{
		$boards_inside = array_keys(fetchBoardsInfo(array('categories' => $categories)));

		if (!empty($boards_inside))
			deleteBoards($boards_inside, null);
	}
	// Make sure the safe category is really safe.
	elseif (in_array($moveBoardsTo, $categories))
		trigger_error('deleteCategories(): You cannot move the boards to a category that\'s being deleted', E_USER_ERROR);
	// Move the boards inside the categories to a safe category.
	else
		$db->query('', '
			UPDATE {db_prefix}boards
			SET id_cat = {int:new_parent_cat}
			WHERE id_cat IN ({array_int:category_list})',
			array(
				'category_list' => $categories,
				'new_parent_cat' => $moveBoardsTo,
			)
		);

	// No one will ever be able to collapse these categories anymore.
	$db->query('', '
		DELETE FROM {db_prefix}collapsed_categories
		WHERE id_cat IN ({array_int:category_list})',
		array(
			'category_list' => $categories,
		)
	);

	// Do the deletion of the category itself
	$db->query('', '
		DELETE FROM {db_prefix}categories
		WHERE id_cat IN ({array_int:category_list})',
		array(
			'category_list' => $categories,
		)
	);

	// Log what we've done.
	foreach ($categories as $category)
		logAction('delete_cat', array('catname' => $cat_tree[$category]['node']['name']), 'admin');

	// Get all boards back into the right order.
	reorderBoards();
}

/**
 * Collapse, expand or toggle one or more categories for one or more members.
 * if members is null, the category is collapsed/expanded for all members.
 * allows three changes to the status: 'expand', 'collapse' and 'toggle'.
 * if check_collapsable is set, only category allowed to be collapsed, will be collapsed.
 *
 * @param int[] $categories
 * @param string $new_status
 * @param int[]|null $members = null
 * @param bool $check_collapsable = true
 */
function collapseCategories($categories, $new_status, $members = null, $check_collapsable = true)
{
	$db = database();

	// Collapse or expand the categories.
	if ($new_status === 'collapse' || $new_status === 'expand')
	{
		$db->query('', '
			DELETE FROM {db_prefix}collapsed_categories
			WHERE id_cat IN ({array_int:category_list})' . ($members === null ? '' : '
				AND id_member IN ({array_int:member_list})'),
			array(
				'category_list' => $categories,
				'member_list' => $members,
			)
		);

		if ($new_status === 'collapse')
			$db->query('', '
				INSERT INTO {db_prefix}collapsed_categories
					(id_cat, id_member)
				SELECT c.id_cat, mem.id_member
				FROM {db_prefix}categories AS c
					INNER JOIN {db_prefix}members AS mem ON (' . ($members === null ? '1=1' : '
						mem.id_member IN ({array_int:member_list})') . ')
				WHERE c.id_cat IN ({array_int:category_list})' . ($check_collapsable ? '
					AND c.can_collapse = {int:is_collapsible}' : ''),
				array(
					'member_list' => $members,
					'category_list' => $categories,
					'is_collapsible' => 1,
				)
			);
	}

	// Toggle the categories: collapsed get expanded and expanded get collapsed.
	elseif ($new_status === 'toggle')
	{
		// Get the current state of the categories.
		$updates = array(
			'insert' => array(),
			'remove' => array(),
		);
		$db->fetchQueryCallback('
			SELECT mem.id_member, c.id_cat, COALESCE(cc.id_cat, 0) AS is_collapsed, c.can_collapse
			FROM {db_prefix}members AS mem
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat IN ({array_int:category_list}))
				LEFT JOIN {db_prefix}collapsed_categories AS cc ON (cc.id_cat = c.id_cat AND cc.id_member = mem.id_member)
			' . ($members === null ? '' : '
				WHERE mem.id_member IN ({array_int:member_list})'),
			array(
				'category_list' => $categories,
				'member_list' => $members,
			),
			function($row) use (&$updates, $check_collapsable)
			{
				if (empty($row['is_collapsed']) && (!empty($row['can_collapse']) || !$check_collapsable))
					$updates['insert'][] = array($row['id_member'], $row['id_cat']);
				elseif (!empty($row['is_collapsed']))
					$updates['remove'][] = '(id_member = ' . $row['id_member'] . ' AND id_cat = ' . $row['id_cat'] . ')';
			}
		);

		// Collapse the ones that were originally expanded...
		if (!empty($updates['insert']))
			$db->insert('replace',
				'{db_prefix}collapsed_categories',
				array(
					'id_cat' => 'int', 'id_member' => 'int',
				),
				$updates['insert'],
				array('id_cat', 'id_member')
			);

		// And expand the ones that were originally collapsed.
		if (!empty($updates['remove']))
			$db->query('', '
				DELETE FROM {db_prefix}collapsed_categories
				WHERE ' . implode(' OR ', $updates['remove']),
				array(
				)
			);
	}
}

/**
 * Return the name of the given category
 *
 * @param int $id_cat
 * @return string
 */
function categoryName($id_cat)
{
	$db = database();

	$request = $db->query('', '
		SELECT name
		FROM {db_prefix}categories
		WHERE id_cat = {int:id_cat}
		LIMIT 1',
		array(
			'id_cat' => $id_cat,
		)
	);
	list ($name) = $db->fetch_row($request);
	$db->free_result($request);

	return $name;
}