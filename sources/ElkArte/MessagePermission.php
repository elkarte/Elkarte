<?php

/**
 * Class MessagePermission
 *
 * This class is responsible for checking if a user has permission to modify or delete messages.
 * It provides methods to check if a user can modify or delete, and throws exceptions if the user
 * does not have permission.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte;

use ElkArte\Exceptions\Exception;
use ElkArte\Helper\ValuesContainer;

class MessagePermission
{
	/** @var UserInfo The user info object */
	protected UserInfo $user;

	/** @var int The ID of the user for whom permissions are being checked */
	protected int $userId;

	/** @var ValuesContainer The modSettings container */
	protected ValuesContainer $modSettings;

	/**
	 * MessagePermission constructor.
	 */
	public function __construct()
	{
		global $modSettings;

		$this->user = User::$info;
		$this->userId = (int) (User::$info->id ?? 0);
		$this->modSettings = $modSettings instanceof ValuesContainer ? $modSettings : new ValuesContainer($modSettings ?? []);
	}

	/**
	 * Determines if the user can modify a message.
	 *
	 * @param array $message The message data
	 * @param int $topicStarterId The ID of the topic starter
	 * @param bool $locked Whether the topic is locked
	 * @return bool True if the user can modify the message, false otherwise
	 */
	public function canModify(array $message, int $topicStarterId, bool $locked = false): bool
	{
		if ($locked && !$this->isAllowed('moderate_board'))
		{
			return false;
		}

		$decision = $this->modifyDecision($message, $topicStarterId);

		return $decision['allowed'] && !$decision['time_passed'];
	}

	/**
	 * Checks if the user can modify a message and throws an exception if not.
	 *
	 * @param array $message The message data
	 * @param int $topicStarterId The ID of the topic starter
	 * @param bool $locked Whether the topic is locked
	 * @return bool True if the user can modify the message, false otherwise
	 * @throws Exception If the user does not have permission to modify the message
	 */
	public function checkModify(array $message, int $topicStarterId, bool $locked = false): bool
	{
		if ($locked && !$this->isAllowed('moderate_board'))
		{
			$this->assertAllowed('moderate_board');
		}

		$decision = $this->modifyDecision($message, $topicStarterId);

		if ($decision['time_passed'])
		{
			$this->throwException('modify_post_time_passed');
		}

		if (!$decision['allowed'])
		{
			$this->assertAllowed($decision['permission']);
		}

		return $decision['moderation'];
	}

	/**
	 * Determines if the user can delete a message.
	 *
	 * @param array $message The message data
	 * @param int $topicStarterId The ID of the topic starter
	 * @param int|null $board The board ID, if applicable
	 * @param bool $checkApproval Whether to check for approval requirements
	 * @return bool True if the user can delete the message, false otherwise
	 */
	public function canDelete(array $message, int $topicStarterId, ?int $board = null, bool $checkApproval = true): bool
	{
		$decision = $this->deleteDecision($message, $topicStarterId, $board, $checkApproval);

		return $decision['allowed'] && !$decision['time_passed'] && !$decision['needs_approval'];
	}

	/**
	 * Determines if the user can remove a topic.
	 *
	 * @param array $message The message data
	 * @param int|null $board The board ID, if applicable
	 * @return bool True if the user can remove the topic, false otherwise
	 */
	public function canRemoveTopic(array $message, ?int $board = null): bool
	{
		if ((int) ($message['id_first_msg'] ?? 0) !== (int) ($message['id_msg'] ?? 0))
		{
			return false;
		}

		if ($this->isAllowed('remove_any', $board))
		{
			return true;
		}

		return (int) ($message['id_member'] ?? 0) === $this->userId && $this->isAllowed('remove_own', $board);
	}

	/**
	 * Checks if the user can delete a message and throws an exception if not.
	 *
	 * @param array $message The message data
	 * @param int $topicStarterId The ID of the topic starter
	 * @param int|null $board The board ID, if applicable
	 * @param bool $throwOnUnapproved Whether to throw an exception if the message is unapproved
	 * @return bool|string True if the user can delete the message, 'topic' if the entire topic can be removed, or 'exit' if unapproved and not throwing
	 * @throws Exception If the user does not have permission to delete the message
	 */
	public function checkDelete(array $message, int $topicStarterId, ?int $board = null, bool $throwOnUnapproved = true): bool|string
	{
		$decision = $this->deleteDecision($message, $topicStarterId, $board, true);

		if ($decision['time_passed'])
		{
			$this->throwException('modify_post_time_passed');
		}

		if (!$decision['allowed'])
		{
			$this->assertAllowed($decision['permission'], $board);
		}

		if ($decision['needs_approval'])
		{
			if (!$throwOnUnapproved)
			{
				return 'exit';
			}

			$this->assertAllowed('approve_posts', $board);
		}

		if ($decision['remove_permission'] !== null)
		{
			$this->assertAllowed($decision['remove_permission'], $board);
		}

		if ($decision['del_first_post'])
		{
			$this->throwException('delFirstPost');
		}

		return $decision['remove_topic'] ? 'topic' : true;
	}

	/**
	 * Determines the modification decision for a message.
	 *
	 * @param array $message The message data
	 * @param int $topicStarterId The ID of the topic starter
	 * @return array An array containing the modification decision details
	 */
	private function modifyDecision(array $message, int $topicStarterId): array
	{
		$isOwnMessage = (int) ($message['id_member'] ?? 0) === $this->userId;
		$isTopicStarter = $topicStarterId === $this->userId;
		$canModifyAny = $this->isAllowed('modify_any');

		if ($isOwnMessage && !$canModifyAny)
		{
			if ($this->isEditTimeExpired($message))
			{
				return $this->modifyResult(false, null, true, false);
			}

			if ($isTopicStarter && !$this->isAllowed('modify_own'))
			{
				return $this->modifyResult($this->isAllowed('modify_replies'), 'modify_replies', false, false);
			}

			return $this->modifyResult($this->isAllowed('modify_own'), 'modify_own', false, false);
		}

		if ($isTopicStarter && !$canModifyAny)
		{
			return $this->modifyResult($this->isAllowed('modify_replies'), 'modify_replies', false, true);
		}

		return $this->modifyResult($canModifyAny, 'modify_any', false, !$isOwnMessage);
	}

	/**
	 * Constructs the result array for modification decisions.
	 *
	 * @param bool $allowed Whether the modification is allowed
	 * @param string|null $permission The permission required for modification
	 * @param bool $timePassed Whether the time limit for modification has passed
	 * @param bool $moderation Whether moderation is required for modification
	 * @return array An array containing the modification decision details
	 */
	private function modifyResult(bool $allowed, ?string $permission, bool $timePassed, bool $moderation): array
	{
		return [
			'allowed' => $allowed,
			'permission' => $permission,
			'time_passed' => $timePassed,
			'moderation' => $moderation,
		];
	}

	/**
	 * Determines the deletion decision for a message.
	 *
	 * @param array $message The message data
	 * @param int $topicStarterId The ID of the topic starter
	 * @param int|null $board The board ID, if applicable
	 * @param bool $checkApproval Whether to check for approval requirements
	 * @return array An array containing the deletion decision details
	 */
	private function deleteDecision(array $message, int $topicStarterId, ?int $board, bool $checkApproval): array
	{
		$isOwnMessage = (int) ($message['id_member'] ?? 0) === $this->userId;
		$isTopicStarter = $topicStarterId === $this->userId;
		$canDeleteAny = $this->isAllowed('delete_any', $board);
		$canDeleteOwn = $this->isAllowed('delete_own', $board);
		$canDeleteReplies = $this->isAllowed('delete_replies', $board);

		$allowed = $canDeleteAny;
		$permission = null;
		$timePassed = false;

		if (!$canDeleteAny)
		{
			if ($isOwnMessage)
			{
				if (!$canDeleteOwn)
				{
					$permission = $isTopicStarter ? 'delete_replies' : 'delete_own';
					$allowed = $isTopicStarter && $canDeleteReplies;
				}
				elseif ($isTopicStarter && $canDeleteReplies)
				{
					$allowed = true;
				}
				else
				{
					$timePassed = $this->isDeleteTimeExpired($message);
					$allowed = !$timePassed;
				}
			}
			elseif ($isTopicStarter)
			{
				$permission = 'delete_replies';
				$allowed = $canDeleteReplies;
			}
			else
			{
				$permission = 'delete_any';
			}
		}

		$needsApproval = $checkApproval
			&& $allowed
			&& !empty($this->modSettings['postmod_active'])
			&& empty($message['approved'])
			&& !$isOwnMessage
			&& !$canDeleteAny
			&& !$this->isAllowed('approve_posts', $board);

		$removePermission = null;
		$removeTopic = false;
		$delFirstPost = false;

		if ($allowed && !$needsApproval && (int) ($message['id_first_msg'] ?? 0) === (int) ($message['id_msg'] ?? 0))
		{
			$canRemoveAny = $this->isAllowed('remove_any', $board);

			if (!$canRemoveAny)
			{
				if (!$isOwnMessage)
				{
					$removePermission = 'remove_any';
				}
				elseif (!$this->isAllowed('remove_own', $board))
				{
					$removePermission = 'remove_own';
				}
			}

			$removeTopic = empty($message['num_replies']);
			$delFirstPost = !$removeTopic;
		}

		return [
			'allowed' => $allowed,
			'permission' => $permission,
			'time_passed' => $timePassed,
			'needs_approval' => $needsApproval,
			'remove_permission' => $removePermission,
			'remove_topic' => $removeTopic,
			'del_first_post' => $delFirstPost,
		];
	}

	/**
	 * Determines if the edit time for a message has expired.
	 *
	 * @param array $message The message data
	 * @return bool True if the edit time has expired, false otherwise
	 */
	private function isEditTimeExpired(array $message): bool
	{
		$editDisableTime = (int) ($this->modSettings['edit_disable_time'] ?? 0);

		if ($editDisableTime <= 0 || (!empty($this->modSettings['postmod_active']) && empty($message['approved'])))
		{
			return false;
		}

		return (int) ($message['poster_time'] ?? 0) + ($editDisableTime + 5) * 60 < time();
	}

	/**
	 * Determines if the delete time for a message has expired.
	 *
	 * @param array $message The message data
	 * @return bool True if the delete time has expired, false otherwise
	 */
	private function isDeleteTimeExpired(array $message): bool
	{
		$editDisableTime = (int) ($this->modSettings['edit_disable_time'] ?? 0);

		if ($editDisableTime <= 0)
		{
			return false;
		}

		return (int) ($message['poster_time'] ?? 0) + $editDisableTime * 60 < time();
	}

	/**
	 * Checks if a user has a specific permission.
	 *
	 * @param string $permission The permission name
	 * @param int|null $board The board ID, if applicable
	 * @return bool True if allowed, false otherwise
	 */
	protected function isAllowed(string $permission, ?int $board = null): bool
	{
		return allowedTo($permission, $board);
	}

	/**
	 * Asserts that a user has a specific permission, throwing an exception if not.
	 *
	 * @param string $permission The permission name
	 * @param int|null $board The board ID, if applicable
	 * @throws Exception
	 */
	protected function assertAllowed(string $permission, ?int $board = null): void
	{
		if ($board === null)
		{
			isAllowedTo($permission);

			return;
		}

		if (!$this->isAllowed($permission, $board))
		{
			$this->throwException('cannot_' . $permission);
		}
	}

	/**
	 * Throws an exception with the given message.
	 *
	 * @param string $message The exception message
	 * @throws Exception
	 */
	protected function throwException(string $message): void
	{
		throw new Exception($message, false);
	}
}
