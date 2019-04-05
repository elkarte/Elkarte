<?php

/**
 * Handles all the mentions actions so members are notified of mentionable actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions;

/**
 * Takes care of validating and inserting mention notifications in the database
 *
 * @package Mentions
 */
class Mentioning extends \ElkArte\AbstractModel
{
	/**
	 * Value assumed by a new mention
	 *
	 * @const int
	 */
	const MNEW = 0;

	/**
	 * Value assumed by a mention that has been read
	 *
	 * @const int
	 */
	const READ = 1;

	/**
	 * Value assumed by a mention that has been deleted
	 *
	 * @const int
	 */
	const DELETED = 2;

	/**
	 * Value assumed by an unapproved mention
	 *
	 * @const int
	 */
	const UNAPPROVED = 3;

	/**
	 * Will hold all available mention types
	 *
	 * @var array
	 */
	protected $_known_mentions = array();

	/**
	 * Will hold all available mention status
	 * 'new' => 0, 'read' => 1, 'deleted' => 2, 'unapproved' => 3,
	 *
	 * @var array
	 */
	protected $_known_status = array();

	/**
	 * Holds the instance of the data validation class
	 *
	 * @var object
	 */
	protected $_validator = null;

	/**
	 * Holds the passed data for this instance, is passed through the validator
	 *
	 * @var array
	 */
	protected $_data = null;

	/**
	 * Mentioning constructor.
	 *
	 * @param \ElkArte\Database\QueryInterface $db
	 * @param \ElkArte\DataValidator $validator
	 * @param string $enabled_mentions
	 */
	public function __construct($db, $validator, $enabled_mentions = '')
	{
		$this->_known_status = array(
			'new' => self::MNEW,
			'read' => self::READ,
			'deleted' => self::DELETED,
			'unapproved' => self::UNAPPROVED,
		);

		$this->_validator = $validator;

		$this->_known_mentions = array_filter(array_unique(explode(',', $enabled_mentions)));

		parent::__construct($db);
	}

	/**
	 * Inserts a new mention.
	 *
	 * @param ElkArte\Mentions\MentionType\\ElkArte\Mentions\MentionType\MentionTypeInterface $mention_obj The object that knows how to store
	 *  the mention in the database
	 * @param mixed[] $data must contain uid, type and msg at a minimum
	 *
	 * @return int[]
	 */
	public function create($mention_obj, $data)
	{
		global $user_info;

		$this->_data = $this->_prepareData($data);

		// Common checks to determine if we can go on
		if (!$this->_isValid())
			return array();

		// Cleanup, validate and remove the invalid values (0 and $user_info['id'])
		$id_targets = array_diff(array_map('intval', array_unique($this->_validator->uid)), array(0, $user_info['id']));

		if (empty($id_targets))
			return array();

		$mention_obj->setDb($this->_db);
		$actually_mentioned = $mention_obj->insert($user_info['id'], $id_targets, $this->_validator->msg, $this->_validator->log_time, $this->_data['status']);

		// Update the member mention count
		foreach ($actually_mentioned as $id_target)
			$this->_updateMenuCount($this->_data['status'], $id_target);

		return $actually_mentioned;
	}

	/**
	 * Prepares the data send through Mentioning::create to be ready for the
	 * actual insert.
	 *
	 * @param mixed[] $data must contain uid, type and msg at a minimum
	 *
	 * @return array|mixed[]
	 */
	protected function _prepareData($data)
	{
		if (isset($data['id_member']))
		{
			$_data = array(
				'uid' => is_array($data['id_member']) ? $data['id_member'] : array($data['id_member']),
				'type' => $data['type'],
				'msg' => $data['id_msg'],
				'status' => isset($data['status']) && isset($this->_known_status[$data['status']]) ? $this->_known_status[$data['status']] : 0,
			);

			if (isset($data['id_member_from']))
				$_data['id_member_from'] = $data['id_member_from'];

			if (isset($data['log_time']))
				$_data['log_time'] = $data['log_time'];
		}
		else
			$_data = $data;

		return $_data;
	}

	/**
	 * Did you read the mention? Then let's move it to the graveyard.
	 * Used in Display.controller.php, it may be merged to action_updatestatus
	 * though that would require to add an optional parameter to avoid the redirect
	 *
	 * @param int|int[] $mention_id
	 * @return bool if successfully changed or not
	 */
	public function markread($mention_id)
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
	public function updateStatus($items, $mark)
	{
		// Make sure its all good
		$own_id = $this->_getAccessible((array) $items, $mark);

		if (!empty($own_id))
		{
			switch ($mark)
			{
				case 'read':
				case 'readall':
					return $this->_changeStatus($own_id, 'read');
					break;
				case 'unread':
					return $this->_changeStatus($own_id, 'new');
					break;
				case 'delete':
					return $this->_changeStatus($own_id, 'deleted');
					break;
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
	protected function _getAccessible($mention_ids, $action)
	{
		require_once(SUBSDIR . '/Mentions.subs.php');
		$sanitization = array(
			'id_mention' => 'intval',
			'mark' => 'trim',
		);
		$validation = array(
			'id_mention' => 'validate_ownmention',
			'mark' => 'contains[read,unread,delete,readall]',
		);

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		$own = array();
		foreach ($mention_ids as $id)
		{
			if ($this->_validator->validate(array('id_mention' => $id, 'mark' => $action)))
				$own[] = $id;
		}

		return $own;
	}

	/**
	 * Check if the user can do what he is supposed to do, and validates the input.
	 */
	protected function _isValid()
	{
		$sanitization = array(
			'type' => 'trim',
			'msg' => 'intval',
		);
		$validation = array(
			'type' => 'required|contains[' . implode(',', $this->_known_mentions) . ']',
			'uid' => 'isarray',
		);

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
			return false;

		// If everything is fine, let's prepare for the fun!
		theme()->getTemplates()->loadLanguageFile('Mentions');

		return true;
	}

	/**
	 * Changes a specific mention status for a member.
	 *
	 * - Can be used to mark as read, new, deleted, etc
	 * - note that delete is a "soft-delete" because otherwise anyway we have to remember
	 * - when a user was already mentioned for a certain message (e.g. in case of editing)
	 *
	 * @package Mentions
	 * @param int|int[] $id_mentions the mention id in the db
	 * @param string $status status to update, 'new', 'read', 'deleted', 'unapproved'
	 * @return bool if successfully changed or not
	 */
	protected function _changeStatus($id_mentions, $status = 'read')
	{
		global $user_info;

		$this->_db->query('', '
			UPDATE {db_prefix}log_mentions
			SET status = {int:status}
			WHERE id_mention IN ({array_int:id_mentions})',
			array(
				'id_mentions' => (array) $id_mentions,
				'status' => $this->_known_status[$status],
			)
		);
		$success = $this->_db->affected_rows() != 0;

		// Update the top level mentions count
		if ($success)
		{
			$this->_updateMenuCount($this->_known_status[$status], $user_info['id']);
		}

		return $success;
	}

	/**
	 * Updates the mention count as a result of an action, read, new, delete, etc
	 *
	 * @package Mentions
	 * @param int $status
	 * @param int $member_id
	 */
	protected function _updateMenuCount($status, $member_id)
	{
		require_once(SUBSDIR . '/Members.subs.php');

		// If its new add to our menu count
		if ($status === 0)
			updateMemberData($member_id, array('mentions' => '+'));
		// Mark as read we decrease the count
		elseif ($status === 1)
			updateMemberData($member_id, array('mentions' => '-'));
		// Deleting or un-approving may have been read or not, so a count is required
		else
			countUserMentions(false, '', $member_id);
	}
}
