<?php

/**
 * Abstract class that handles checks for board access level
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\sources\subs\MentionType;

/**
 * Class Mention_BoardAccess_Abstract
 *
 * @package ElkArte\sources\subs\MentionType
 */
abstract class Mention_BoardAccess_Abstract extends Mention_Message_Abstract
{
	/**
	 * {@inheritdoc}
	 */
	public function view($type, &$mentions)
	{
		$boards = array();
		$unset_keys = array();

		foreach ($mentions as $key => $row)
		{
			// To ensure it is not done twice
			if (empty(static::$_type) || $row['mention_type'] != static::$_type)
				continue;

			// These things are associated to messages and require permission checks
			if (empty($row['id_board']))
				$unset_keys[] = $key;
			else
				$boards[$key] = $row['id_board'];

			$mentions[$key]['message'] = $this->_replaceMsg($row);
		}

		if (!empty($boards))
			return $this->_validateAccess($boards, $mentions, $unset_keys);
		else
			return false;
	}

	/**
	 * Verifies that the current user can access the boards where the messages
	 * are in.
	 *
	 * @param int[] $boards Array of board ids
	 * @param mixed[] $mentions
	 * @param int[] $unset_keys Array of board ids
	 */
	protected function _validateAccess($boards, &$mentions, $unset_keys)
	{
		global $user_info, $modSettings;

		// Do the permissions checks and replace inappropriate messages
		require_once(SUBSDIR . '/Boards.subs.php');
		// @todo find a better place?
		theme()->getTemplates()->loadLanguageFile('Mentions');

		$removed = false;
		$accessibleBoards = accessibleBoards($boards);

		foreach ($boards as $key => $board)
		{
			// You can't see the board where this mention is, so we drop it from the results
			if (!in_array($board, $accessibleBoards))
			{
				$unset_keys[] = $key;
			}
		}

		// If some of these mentions are no longer visible, we need to do some maintenance
		if (!empty($unset_keys))
		{
			$removed = true;
			foreach ($unset_keys as  $key)
				unset($mentions[$key]);

			if (!empty($modSettings['user_access_mentions']))
				$modSettings['user_access_mentions'] = \Util::unserialize($modSettings['user_access_mentions']);
			else
				$modSettings['user_access_mentions'] = array();

			$modSettings['user_access_mentions'][$user_info['id']] = 0;
			updateSettings(array('user_access_mentions' => serialize($modSettings['user_access_mentions'])));
			scheduleTaskImmediate('user_access_mentions');
		}

		return $removed;
	}
}