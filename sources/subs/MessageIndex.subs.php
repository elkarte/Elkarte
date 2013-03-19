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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Generates the query to determine the list of available boards for a user
 * Executes the query and returns the list
 *
 * @param array $boardListOptions
 * @param boolean $simple if true a simple array is returned containing some basic
 *                informations regarding the board (id_board, board_name, child_level, id_cat, cat_name)
 *                if false the boards are returned in an array subdivided by categories including also
 *                additional data like the number of boards
 * @return type
 */
function getBoardList($boardListOptions = array(), $simple = false)
{
	global $smcFunc, $user_info;

	if (isset($boardListOptions['excluded_boards']) && isset($boardListOptions['included_boards']))
		trigger_error('getBoardList(): Setting both excluded_boards and included_boards is not allowed.', E_USER_ERROR);

	$where = array();
	$select = '';
	$where_parameters = array();
	if (isset($boardListOptions['excluded_boards']))
	{
		$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
		$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
	}

	if (isset($boardListOptions['included_boards']))
	{
		$where[] = 'b.id_board IN ({array_int:included_boards})';
		$where_parameters['included_boards'] = $boardListOptions['included_boards'];
	}

	if (isset($boardListOptions['access']))
	{
		$select .= ',
			FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access,
			FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access';
		$where_parameters['current_group'] = $boardListOptions['access'];
	}

	if (isset($boardListOptions['ignore']))
	{
		$select .= ',' . (!empty($boardListOptions['ignore']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored';
		$where_parameters['included_boards'] = $boardListOptions['ignore'];
	}

	if (!empty($boardListOptions['ignore_boards']))
		$where[] = '{query_wanna_see_board}';

	elseif (!empty($boardListOptions['use_permissions']))
		$where[] = '{query_see_board}';

	if (!empty($boardListOptions['not_redirection']))
	{
		$where[] = 'b.redirect = {string:blank_redirect}';
		$where_parameters['blank_redirect'] = '';
	}

	$request = $smcFunc['db_query']('messageindex_fetch_boards', '
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level' . $select . '
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)),
		$where_parameters
	);

	if ($simple)
	{
		$return_value = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$return_value[$row['id_board']] = array(
				'id_cat' => $row['id_cat'],
				'cat_name' => $row['cat_name'],
				'id_board' => $row['id_board'],
				'board_name' => $row['board_name'],
				'child_level' => $row['child_level'],
			);
		}
	}
	else
	{
		$return_value = array(
			'num_boards' => $smcFunc['db_num_rows']($request),
			'boards_check_all' => true,
			'categories' => array(),
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// This category hasn't been set up yet..
			if (!isset($return_value['categories'][$row['id_cat']]))
				$return_value['categories'][$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => array(),
				);

			$return_value['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'allow' => false,
				'deny' => false,
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			);

			// Do we want access informations?
			if (!empty($boardListOptions['access']))
				$return_value['categories'][$row['id_cat']]['boards'][$row['id_board']] += array(
					'allow' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
					'deny' => !(empty($row['cannot_access']) || $row['cannot_access'] == 'f'),
				);

			// If is_ignored is set, it means we could have to deselect a board
			if (isset($row['is_ignored']))
			{
				$return_value['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] = $row['is_ignored'];

				// If a board wasn't checked that probably should have been ensure the board selection is selected, yo!
				if (!empty($return_value['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected']) && (empty($modSettings['recycle_enable']) || $row['id_board'] != $modSettings['recycle_board']))
					$return_value['boards_check_all'] = false;
			}
		}
	}

	$smcFunc['db_free_result']($request);

	return $return_value;
}