<?php

/**
 * Handles all the mentions actions so members are notified of mentionable actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Mentions;

use ElkArte\AbstractModel;
use ElkArte\Database\QueryInterface;
use ElkArte\Helper\DataValidator;
use ElkArte\Languages\Txt;
use ElkArte\Mentions\MentionType\NotificationInterface;

/**
 * Takes care of validating and inserting mention notifications in the database
 *
 * @package Mentions
 */
class Mentioning extends AbstractModel
{
	/** @const int Value assumed by a new mention */
	public const MNEW = 0;

	/** @const int Value assumed by a mention that has been read */
	public const READ = 1;

	/** @const int Value assumed by a mention that has been deleted */
	public const DELETED = 2;

	/** @const int Value assumed by an unapproved mention */
	public const UNAPPROVED = 3;

	/** @var array Will hold all available mention types */
	protected $_known_mentions = [];

	/** @var array Will hold all available mention status constants
	 * 'new' => 0, 'read' => 1, 'deleted' => 2, 'unapproved' => 3 */
	protected $_known_status = [];

	/** @var array Holds the passed data for this instance, is passed through the validator */
	protected $_data;

	/**
	 * Mentioning constructor.
	 *
	 * @param QueryInterface $db
	 * @param DataValidator $_validator
	 * @param string $enabled_mentions
	 */
	public function __construct($db, $user, protected $_validator, $enabled_mentions = '')
	{
		$this->_known_status = [
			'new' => self::MNEW,
			'read' => self::READ,
			'deleted' => self::DELETED,
			'unapproved' => self::UNAPPROVED,
		];

		$this->_known_mentions = array_filter(array_unique(explode(',', $enabled_mentions)));

		parent::__construct($db, $user);
	}

	/**
	 * Inserts a new mention.
	 *
	 * @param NotificationInterface $mention_obj The object that knows how to store the mention in the database
	 * @param array $data must contain uid, type, and msg at a minimum
	 *
	 * @return int[]
	 */
	public function create($mention_obj, $data): array
	{
		$this->_data = $this->_prepareData($data);

		// Common checks to determine if we can go on
		if (!$this->_isValid())
		{
			return [];
		}

		// Cleanup, validate and remove the invalid values (0 and $this->_data['id_member_from'])
		$id_targets = array_diff(array_map('intval', array_unique($this->_validator->uid)), [0, $this->_data['id_member_from']]);

		if (empty($id_targets))
		{
			return [];
		}

		$actually_mentioned = $mention_obj->insert($this->_data['id_member_from'], $id_targets, $this->_validator->msg, $this->_validator->log_time, $this->_data['status']);

		// Update the member mention count
		foreach ($actually_mentioned as $id_target)
		{
			$this->_updateMenuCount($this->_data['status'], $id_target);
		}

		return $actually_mentioned;
	}

	/**
	 * Prepares the data sent to Mentioning::create to be ready for the actual insert.
	 *
	 * @param array $data must contain uid, type, and msg at a minimum
	 *
	 * @return array
	 */
	protected function _prepareData($data): array
	{
		if (isset($data['id_member']))
		{
			$_data = [
				'uid' => is_array($data['id_member']) ? $data['id_member'] : [$data['id_member']],
				'type' => $data['type'],
				'msg' => $data['id_msg'],
				'status' => isset($data['status'], $this->_known_status[$data['status']]) ? $this->_known_status[$data['status']] : 0,
			];

			if (isset($data['id_member_from']))
			{
				$_data['id_member_from'] = $data['id_member_from'];
			}

			if (isset($data['log_time']))
			{
				$_data['log_time'] = $data['log_time'];
			}
		}
		else
		{
			$_data = $data;
		}

		return $_data;
	}

	/**
	 * Check if the user can do what he is supposed to do, and validates the input.
	 *
	 * @return bool
	 */
	protected function _isValid(): bool
	{
		$sanitization = [
			'type' => 'trim',
			'msg' => 'intval',
		];

		$validation = [
			'type' => 'required|contains[' . implode(',', $this->_known_mentions) . ']',
			'uid' => 'isarray',
		];

		// Any optional fields we need to check?
		if (isset($this->_data['id_member_from']))
		{
			$sanitization['id_member_from'] = 'intval';
			$validation['id_member_from'] = 'required|notequal[0]';
		}

		if (isset($this->_data['log_time']))
		{
			$sanitization['log_time'] = 'intval';
			$validation['log_time'] = 'required|notequal[0]';
		}

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		if (!$this->_validator->validate($this->_data))
		{
			return false;
		}

		// If everything is fine, let's prepare for the fun!
		Txt::load('Mentions');

		return true;
	}

	/**
	 * Updates the mention count as a result of an action, read, new, delete, etc.
	 *
	 * @param int $status
	 * @param int $member_id
	 * @package Mentions
	 */
	protected function _updateMenuCount($status, $member_id): void
	{
		require_once(SUBSDIR . '/Members.subs.php');

		// If its new add to our menu count
		if ($status === 0)
		{
			updateMemberData($member_id, ['mentions' => '+']);
		}
		// Mark as read we decrease the count
		elseif ($status === 1)
		{
			updateMemberData($member_id, ['mentions' => '-']);
		}
		// Deleting or unapproving may have been read or not, so a count is required
		else
		{
			countUserMentions(false, '', $member_id);
		}
	}

	/**
	 * Did you read the mention? Then let's move it to the graveyard.
	 * Used in Display.controller.php, it may be merged to action_updatestatus
	 * though that would require adding an optional parameter to avoid the redirect
	 *
	 * @param int|int[] $mention_id
	 * @return bool if successfully changed or not
	 */
	public function markread($mention_id): bool
	{
		return $this->updateStatus($mention_id, 'readall');
	}

	/**
	 * Updating the status from the listing?
	 *
	 * @param int|int[] $items
	 * @param string $mark
	 * @return bool if successfully changed or not
	 */
	public function updateStatus($items, $mark): bool
	{
		// Make sure it is all good
		$own_id = $this->_getAccessible((array) $items, $mark);

		if (!empty($own_id))
		{
			switch ($mark)
			{
				case 'read':
				case 'readall':
					return $this->_changeStatus($own_id);
				case 'unread':
					return $this->_changeStatus($own_id, 'new');
				case 'delete':
					return $this->_changeStatus($own_id, 'deleted');
			}
		}

		return false;
	}

	/**
	 * Of the passed IDs returns those accessible to the user.
	 *
	 * @param int[] $mention_ids
	 * @param string $action
	 * @return int[]
	 */
	protected function _getAccessible($mention_ids, $action): array
	{
		require_once(SUBSDIR . '/Mentions.subs.php');
		$sanitization = [
			'id_mention' => 'intval',
			'mark' => 'trim',
		];
		$validation = [
			'id_mention' => 'validate_own_mention',
			'mark' => 'contains[read,unread,delete,readall]',
		];

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		$own = [];
		foreach ($mention_ids as $id)
		{
			if ($this->_validator->validate(['id_mention' => $id, 'mark' => $action]))
			{
				$own[] = $id;
			}
		}

		return $own;
	}

	/**
	 * Changes a specific mention status for a member.
	 *
	 * - Can be used to mark as read, new, deleted, etc.
	 * - Note that delete is a "soft-delete" because otherwise anyway we have to remember
	 * - When a user was already mentioned for a certain message (e.g., in case of editing)
	 *
	 * @param int|int[] $id_mentions the mention(s) id in the db
	 * @param string $status status to update, 'new', 'read', 'deleted', 'unapproved'
	 * @return bool if successfully changed or not
	 * @package Mentions
	 */
	protected function _changeStatus($id_mentions, $status = 'read'): bool
	{
		require_once(SUBSDIR . '/Mentions.subs.php');

		$user_id = is_int($this->user) ? $this->user : $this->user->id;
		$success = changeStatus($id_mentions, $user_id, $this->_known_status[$status], false);

		// Update the top level mentions count
		if ($success)
		{
			$this->_updateMenuCount($this->_known_status[$status], $user_id);
		}

		return $success;
	}
}
