<?php

/**
 * Handles mentioning of members whose are watching a topic.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType\Event;

use ElkArte\DataValidator;
use ElkArte\Mentions\Mentioning;
use ElkArte\Mentions\MentionType\AbstractEventBoardAccess;
use ElkArte\Notifications\Notifications;
use ElkArte\Notifications\NotificationsTask;

/**
 * Class Watchedtopic
 *
 * Handles mentioning of members who are watching topics
 */
class Watchedtopic extends AbstractEventBoardAccess
{
	/** {@inheritDoc} */
	protected static $_type = 'watchedtopic';

	/**
	 * {@inheritDoc}
	 */
	public static function getEvents($controller)
	{
		$methods = [
			'post' => [
				'after_save_post' => ['msgOptions', 'becomesApproved', 'posterOptions']
			],
			'display' => ['prepare_context' => ['virtual_msg']],
		];

		return $methods[$controller] ?? [];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getModules($modules)
	{
		$modules['mentions'] = ['post', 'display'];

		return $modules;
	}

	/**
	 * Listener attached to the prepare_context event of the Display controller
	 * used to mark a mention as read.
	 *
	 * @param int $virtual_msg
	 */
	public function display_prepare_context($virtual_msg)
	{
		global $modSettings;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$mentions = new Mentioning(database(), $this->user, new DataValidator(), $modSettings['enabled_mentions']);
			$mentions->markread((int) $_REQUEST['item']);
		}
	}

	/**
	 * Listener attached to the after_save_post event of the Post controller.
	 *
	 * @param array $msgOptions
	 * @param bool $becomesApproved
	 * @param array $posterOptions
	 */
	public function post_after_save_post($msgOptions, $becomesApproved, $posterOptions)
	{
		$status = $becomesApproved ? 'new' : 'unapproved';
		$this->_sendNotification($msgOptions, $status, $posterOptions);
	}

	/**
	 * Checks if a new approved post has been made, on a watched topic
	 * and if so notifies those topic watchers
	 *
	 * @param array $msgOptions The message options array
	 * @param string $status
	 * @param array $posterOptions
	 */
	protected function _sendNotification($msgOptions, $status, $posterOptions)
	{
		$quoted_names = $this->_findTopicWatchers($msgOptions['id']);

		if (!empty($quoted_names))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members_id = membersBy([['or' => 'member_names']], ['member_names' => $quoted_names, 'limit' => count($quoted_names)]);
		}

		if (!empty($members_id))
		{
			// If the message was edited, attribute the quote to the starter not the modifier to prevent
			// the sending of another notification
			$modified = (isset($posterOptions['id_starter']));

			$notifier = Notifications::instance();
			$notifier->add(new NotificationsTask(
				'watchedtopic',
				$msgOptions['id'],
				$modified ? $posterOptions['id_starter'] : $posterOptions['id'],
				['id_members' => $members_id, 'notifier_data' => $posterOptions, 'status' => $status, 'subject' =>  $msgOptions['subject']]
			));
		}
	}

	/**
	 * Finds members who have subscribed to a specific topic
	 *
	 * @return string[]|bool An array of member ids
	 */
	protected function _findTopicWatchers($text)
	{
		if (strpos($text, '[quote ') !== false)
		{
			$quoted = [];
			$blocks = explode("\[quote", $text);

			$skip_next = false;
			foreach ($blocks as $block)
			{
				if (empty($block))
				{
					continue;
				}

				if (!$skip_next)
				{
					preg_match('~author=(.*?)(]|date=|link=)~', $block, $match);

					if (!empty($match[1]))
					{
						$quoted[] = trim($match[1]);
					}
				}

				$skip_next = strpos($block, '[/quote]') === false;
			}

			return array_unique($quoted);
		}

		return false;
	}
}
