<?php

/**
 * This file is mainly concerned with minor tasks relating to boards, such as
 * marking them read, collapsing categories, or quick moderation.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

class BoardsTree
{
	protected $cat_tree = [];
	protected $boards = [];
	protected $boardList = [];
	protected $db = null;

	public function __construct(\ElkArte\Database\QueryInterface $db)
	{
		$this->db = $db;

		$this->loadBoardTree();
	}

	/**
	 * Load a lot of useful information regarding the boards and categories.
	 *
	 * - The information retrieved is stored in globals:
	 *   $this->boards:    properties of each board.
	 *   $this->boardList: a list of boards grouped by category ID.
	 *   $this->cat_tree:  properties of each category.
	 *
	 * @param array $query
	 *
	 * @throws \ElkArte\Exceptions\Exception no_valid_parent
	 */
	protected function loadBoardTree($query = array())
	{
		// Addons may want to add their own information to the board table.
		call_integration_hook('integrate_board_tree_query', array(&$query));

		// Getting all the board and category information you'd ever wanted.
		$request = $this->db->query('', '
			SELECT
				COALESCE(b.id_board, 0) AS id_board, b.id_parent, b.name AS board_name, b.description, b.child_level,
				b.board_order, b.count_posts, b.member_groups, b.id_theme, b.override_theme, b.id_profile, b.redirect,
				b.num_posts, b.num_topics, b.deny_member_groups, c.id_cat, c.name AS cat_name, c.cat_order, c.can_collapse' . (!empty($query['select']) ?
				$query['select'] : '') . '
			FROM {db_prefix}categories AS c
				LEFT JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)' . (!empty($query['join']) ?
				$query['join'] : '') . '
			ORDER BY c.cat_order, b.child_level, b.board_order',
			array(
			)
		);
		$this->cat_tree = array();
		$this->boards = array();
		$last_board_order = 0;
		while ($row = $request->fetch_assoc())
		{
			if (!isset($this->cat_tree[$row['id_cat']]))
			{
				$this->cat_tree[$row['id_cat']] = array(
					'node' => array(
						'id' => $row['id_cat'],
						'name' => $row['cat_name'],
						'order' => $row['cat_order'],
						'can_collapse' => $row['can_collapse']
					),
					'is_first' => empty($this->cat_tree),
					'last_board_order' => $last_board_order,
					'children' => array()
				);
				$prevBoard = 0;
				$curLevel = 0;
			}

			if (!empty($row['id_board']))
			{
				if ($row['child_level'] != $curLevel)
					$prevBoard = 0;

				$this->boards[$row['id_board']] = array(
					'id' => $row['id_board'],
					'category' => $row['id_cat'],
					'parent' => $row['id_parent'],
					'level' => $row['child_level'],
					'order' => $row['board_order'],
					'name' => $row['board_name'],
					'member_groups' => explode(',', $row['member_groups']),
					'deny_groups' => explode(',', $row['deny_member_groups']),
					'description' => $row['description'],
					'count_posts' => empty($row['count_posts']),
					'posts' => $row['num_posts'],
					'topics' => $row['num_topics'],
					'theme' => $row['id_theme'],
					'override_theme' => $row['override_theme'],
					'profile' => $row['id_profile'],
					'redirect' => $row['redirect'],
					'prev_board' => $prevBoard
				);
				$prevBoard = $row['id_board'];
				$last_board_order = $row['board_order'];

				if (empty($row['child_level']))
				{
					$this->cat_tree[$row['id_cat']]['children'][$row['id_board']] = array(
						'node' => &$this->boards[$row['id_board']],
						'is_first' => empty($this->cat_tree[$row['id_cat']]['children']),
						'children' => array()
					);
					$this->boards[$row['id_board']]['tree'] = &$this->cat_tree[$row['id_cat']]['children'][$row['id_board']];
				}
				else
				{
					// Parent doesn't exist!
					if (!isset($this->boards[$row['id_parent']]['tree']))
						throw new \ElkArte\Exceptions\Exception('no_valid_parent', false, array($row['board_name']));

					// Wrong childlevel...we can silently fix this...
					if ($this->boards[$row['id_parent']]['tree']['node']['level'] != $row['child_level'] - 1)
					{
						$this->db->query('', '
							UPDATE {db_prefix}boards
							SET child_level = {int:new_child_level}
							WHERE id_board = {int:selected_board}',
							array(
								'new_child_level' => $this->boards[$row['id_parent']]['tree']['node']['level'] + 1,
								'selected_board' => $row['id_board'],
							)
						);
					}

					$this->boards[$row['id_parent']]['tree']['children'][$row['id_board']] = array(
						'node' => &$this->boards[$row['id_board']],
						'is_first' => empty($this->boards[$row['id_parent']]['tree']['children']),
						'children' => array()
					);
					$this->boards[$row['id_board']]['tree'] = &$this->boards[$row['id_parent']]['tree']['children'][$row['id_board']];
				}
			}

			// Let integration easily add data to $this->boards and $this->cat_tree
			call_integration_hook('integrate_board_tree', array($row));
		}
		$request->free_result();

		// Get a list of all the boards in each category (using recursion).
		$this->boardList = array();
		foreach ($this->cat_tree as $catID => $node)
		{
			$this->boardsInCategory($catID);
		}
	}

	/**
	 * Recursively get a list of boards.
	 *
	 * - Used by loadBoardTree
	 *
	 * @param int $catID The category id
	 */
	public function boardsInCategory($catID)
	{
		$this->boardList[$catID] = [];

		if (empty($this->cat_tree[$catID]['children']))
		{
			return;
		}

		foreach ($this->cat_tree[$catID]['children'] as $id => $node)
		{
			$this->boardList[$catID] = array_merge($this->boardList[$catID], $this->allChildsOf($id));
		}
	}

	public function getBoardList()
	{
		return $this->boardList;
	}

	public function getCategories()
	{
		return $this->cat_tree;
	}

	public function getBoards()
	{
		return $this->boards;
	}

	public function getCategoryNodeById($id)
	{
		if (isset($this->cat_tree[$id]))
		{
			return $this->cat_tree[$id];
		}
		else
		{
			throw new \Exception('Category id doesn\'t exist: ' . $id);
		}
	}

	public function getBoardsInCat($id)
	{
		if (isset($this->boardList[$id]))
		{
			return $this->boardList[$id];
		}
		else
		{
			throw new \Exception('Category id doesn\'t exist: ' . $id);
		}
	}

	public function categoryExists($id)
	{
		return isset($this->boardList[$id]);
	}

	public function boardExists($id)
	{
		return isset($this->boards[$id]);
	}

	public function getBoardById($id)
	{
		if (isset($this->boards[$id]))
		{
			return $this->boards[$id];
		}
		else
		{
			throw new \Exception('Board id doesn\'t exist: ' . $id);
		}
	}

	/**
	 * Put all boards in the right order and sorts the records of the boards table.
	 *
	 * - Used by modifyBoard(), deleteBoards(), modifyCategory(), and deleteCategories() functions
	 */
	public function reorderBoards()
	{
		$update_query = '';
		$update_params = [];

		// Set the board order for each category.
		$board_order = 0;
		foreach ($this->cat_tree as $catID => $dummy)
		{
			foreach ($this->boardList[$catID] as $boardID)
			{
				if ($this->boards[$boardID]['order'] != ++$board_order)
				{
					$update_query .= sprintf(
						'
					WHEN {int:selected_board%1$d} THEN {int:new_order%1$d}',
						$boardID
					);

					$update_params = array_merge(
						$update_params,
						[
							'new_order' . $boardID => $board_order,
							'selected_board' . $boardID => $boardID,
						]
					);
				}
			}
		}

		if (empty($update_query))
		{
			return;
		}

		$this->db->query('',
			'UPDATE {db_prefix}boards
				SET
					board_order = CASE id_board ' . $update_query . '
						END',
			$update_params
		);
	}

	/**
	 * Returns whether the sub-board id is actually a child of the parent (recursive).
	 *
	 * @param int $child The ID of the child board
	 * @param int $parent The ID of a parent board
	 *
	 * @return boolean if the specified child board is a child of the specified parent board.
	 */
	public function isChildOf($child, $parent)
	{
		if (empty($this->boards[$child]['parent']))
		{
			return false;
		}

		if ($this->boards[$child]['parent'] == $parent)
		{
			return true;
		}

		return $this->isChildOf($this->boards[$child]['parent'], $parent);
	}

	/**
	 * Fixes the children of a board by setting their child_levels to new values.
	 *
	 * - Used when a board is deleted or moved, to affect its children.
	 *
	 * @param int $parent
	 * @param int $newLevel
	 * @param int $newParent
	 */
	function fixChildren($parent, $newLevel, $newParent)
	{
		// Grab all children of $parent...
		$children = $this->db->fetchQuery('
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_parent = {int:parent_board}',
			array(
				'parent_board' => $parent,
			)
		)->fetch_callback(
			function ($row)
			{
				return $row['id_board'];
			}
		);

		// ...and set it to a new parent and child_level.
		$this->db->query('', '
			UPDATE {db_prefix}boards
			SET id_parent = {int:new_parent}, child_level = {int:new_child_level}
			WHERE id_parent = {int:parent_board}',
			array(
				'new_parent' => $newParent,
				'new_child_level' => $newLevel,
				'parent_board' => $parent,
			)
		);

		// Recursively fix the children of the children.
		foreach ($children as $child)
		{
			$this->fixChildren($child, $newLevel + 1, $child);
		}
	}

	/**
	 * Remove one or more boards.
	 *
	 * - Allows to move the children of the board before deleting it
	 * - if moveChildrenTo is set to null, the sub-boards will be deleted.
	 * - Deletes:
	 *   - all topics that are on the given boards;
	 *   - all information that's associated with the given boards;
	 * - updates the statistics to reflect the new situation.
	 *
	 * @param int[] $boards_to_remove
	 * @param int|null $moveChildrenTo = null
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function deleteBoards($boards_to_remove, $moveChildrenTo = null)
	{
		// No boards to delete? Return!
		if (empty($boards_to_remove))
			return;

		call_integration_hook('integrate_delete_board', array($boards_to_remove, &$moveChildrenTo));

		// If $moveChildrenTo is set to null, include the children in the removal.
		if ($moveChildrenTo === null)
		{
			// Get a list of the sub-boards that will also be removed.
			$child_boards_to_remove = array();
			foreach ($boards_to_remove as $board_to_remove)
			{
				$child_boards_to_remove = array_merge($child_boards_to_remove, $this->allChildsOf($board_to_remove));
			}

			// Merge the children with their parents.
			if (!empty($child_boards_to_remove))
			{
				$boards_to_remove = array_unique(array_merge($boards_to_remove, $child_boards_to_remove));
			}
		}
		// Move the children to a safe home.
		else
		{
			foreach ($boards_to_remove as $id_board)
			{
				// @todo Separate category?
				if ($moveChildrenTo === 0)
				{
					$this->fixChildren($id_board, 0, 0);
				}
				else
				{
					$this->fixChildren($id_board, $this->boards[$moveChildrenTo]['level'] + 1, $moveChildrenTo);
				}
			}
		}

		// Delete ALL topics in the selected boards (done first so topics can't be marooned.)
		$topics = $this->db->fetchQuery('
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		)->fetch_all();

		require_once(SUBSDIR . '/Topic.subs.php');
		removeTopics($topics, false);

		// Delete the board's logs.
		$this->db->query('', '
			DELETE FROM {db_prefix}log_mark_read
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);
		$this->db->query('', '
			DELETE FROM {db_prefix}log_boards
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);
		$this->db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);

		// Delete this board's moderators.
		$this->db->query('', '
			DELETE FROM {db_prefix}moderators
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);

		// Delete any extra events in the calendar.
		$this->db->query('', '
			DELETE FROM {db_prefix}calendar
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);

		// Delete any message icons that only appear on these boards.
		$this->db->query('', '
			DELETE FROM {db_prefix}message_icons
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);

		// Delete the boards.
		$this->db->query('', '
			DELETE FROM {db_prefix}boards
			WHERE id_board IN ({array_int:boards_to_remove})',
			array(
				'boards_to_remove' => $boards_to_remove,
			)
		);

		// Latest message/topic might not be there anymore.
		require_once(SUBSDIR . '/Messages.subs.php');
		updateMessageStats();
		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats();
		updateSettings(array('calendar_updated' => time()));

		// Plus reset the cache to stop people getting odd results.
		updateSettings(array('settings_updated' => time()));

		// Clean the cache as well.
		\ElkArte\Cache\Cache::instance()->clean('data');

		// Let's do some serious logging.
		foreach ($boards_to_remove as $id_board)
		{
			logAction('delete_board', array('boardname' => $this->boards[$id_board]['name']), 'admin');
		}

		$this->reorderBoards();
	}

	public function allChildsOf($board_id)
	{
		if (empty($this->boards[$board_id]['children']))
		{
			return [];
		}

		$boardsList = [];
		foreach ($this->boards[$board_id]['children'] as $id => $node)
		{
			$boardsList[] = $id;
			$boardsList = array_merge($boardsList, $this->allChildsOf($id));
		}

		return $boardsList;
	}
}
