<?php

/**
 * This file contains several functions for retrieving and manipulating polls.
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

namespace ElkArte\Modules\Poll;

use ElkArte\Errors\ErrorContext;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Modules\AbstractModule;
use ElkArte\Themes\TemplateLayers;

/**
 * Class Poll_Post_Module
 *
 * This class contains all matter of things related to creating polls
 */
class Post extends AbstractModule
{
	protected static $_make_poll = false;

	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $context, $modSettings;

		$context['can_add_poll'] = false;
		$context['make_poll'] = false;

		$return = [];
		if (!empty($modSettings['pollMode']))
		{
			$return = [
				['prepare_post', [Post::class, 'prepare_post'], ['topic', 'topic_attributes']],
				['prepare_context', [Post::class, 'prepare_context'], ['topic_attributes', 'topic', 'board']],
				['finalize_post_form', [Post::class, 'finalize_post_form'], ['destination', 'page_title', 'template_layers']],
			];
		}

		// Posting a poll?
		self::$_make_poll = isset($_REQUEST['poll']);
		if (self::$_make_poll)
		{
			return array_merge($return, [
				['before_save_post', [Post::class, 'before_save_post'], []],
				['save_replying', [Post::class, 'save_replying'], []],
				['pre_save_post', [Post::class, 'pre_save_post'], ['topicOptions']],
			]);
		}

		return $return;
	}

	/**
	 * Validates post data to ensure no one tried to reply with a poll
	 *
	 * @param int $topic
	 * @param array $topic_attributes
	 */
	public function prepare_post($topic, &$topic_attributes)
	{
		$topic_attributes['id_poll'] = 0;

		// You can't reply with a poll... hacker.
		if (!isset($_REQUEST['poll']))
		{
			return;
		}

		if (empty($topic))
		{
			return;
		}

		if (isset($_REQUEST['msg']))
		{
			return;
		}

		$this->_unset_poll();
	}

	/**
	 * Helper function to remove a poll, either by user choice or by catching naughty users
	 */
	protected function _unset_poll()
	{
		self::$_make_poll = false;

		// deprecated since 1.1 - to be removed when sure it doesn't affect anything else
		unset($_REQUEST['poll']);

		return true;
	}

	/**
	 * Sets up poll options in context for use in the template
	 *
	 * What it does:
	 *
	 * - Validates the topic can have a poll added
	 * - Validates the poster can add a poll or not
	 * - Prepares options so a poll can be added (or not) given the above results
	 *
	 * @param array $topic_attributes
	 * @param int $topic
	 * @param int $board
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function prepare_context($topic_attributes, $topic, $board)
	{
		global $context, $txt;

		if (!empty($topic))
		{
			$msg = $this->_req->getRequest('msg', 'intval');

			// If this topic already has a poll, they sure can't add another.
			if ($msg !== null && $topic_attributes['id_poll'] > 0)
			{
				return $this->_unset_poll();
			}

			// It's a new reply
			if (empty($_REQUEST['msg']))
			{
				$context['can_add_poll'] = false;
			}
			else
			{
				$context['can_add_poll'] = (allowedTo('poll_add_any') || ($topic_attributes['id_first_msg'] === $msg && allowedTo('poll_add_own'))) && $topic_attributes['id_poll'] <= 0;
			}
		}
		else
		{
			$context['can_add_poll'] = allowedTo('poll_add_any') || allowedTo('poll_add_own');
		}

		if ($context['can_add_poll'])
		{
			theme()->addJavascriptVar([
				'poll_remove' => $txt['poll_remove'],
				'poll_add' => $txt['add_poll']], true);
		}

		// Check the users permissions - is the user allowed to add or post a poll?
		if (self::$_make_poll)
		{
			// New topic, new poll.
			if (empty($topic))
			{
				isAllowedTo('poll_post');
			}
			// This is an old topic - but it is yours!  Can you add to it?
			elseif ($this->user->id === $topic_attributes['id_member'] && !allowedTo('poll_add_any'))
			{
				isAllowedTo('poll_add_own');
			}
			// If you're not the owner, can you add to any poll?
			else
			{
				isAllowedTo('poll_add_any');
			}

			$context['can_moderate_poll'] = true;

			require_once(SUBSDIR . '/Members.subs.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

			// Clean / Set options
			$poll_max_votes = $this->_req->getPost('poll_max_votes', 'intval', 1);
			$poll_change_vote = $this->_req->getPost('poll_change_vote');
			$poll_guest_vote = $this->_req->getPost('poll_guest_vote');

			// Set up the poll options.
			$context['poll'] = [
				'max_votes' => max(1, $poll_max_votes),
				'hide_results' => $this->_req->getPost('poll_hide', 'intval', 0),
				'expiration' => $this->_req->getPost('poll_expire', 'intval', ''),
				'change_vote' => isset($poll_change_vote),
				'guest_vote' => isset($poll_guest_vote),
				'guest_vote_allowed' => in_array(-1, $allowedVoteGroups['allowed'], true),
			];

			$this->_preparePollContext();
		}

		return true;
	}

	/**
	 * Loads in context stuff related to polls
	 */
	protected function _preparePollContext()
	{
		global $context;

		$context['poll']['question'] = isset($_REQUEST['question']) ? Util::htmlspecialchars(trim($_REQUEST['question'])) : '';

		$context['poll']['choices'] = [];
		$choice_id = 0;

		$_POST['options'] = empty($_POST['options']) ? [] : Util::htmlspecialchars__recursive($_POST['options']);
		foreach ($_POST['options'] as $option)
		{
			if (trim($option) === '')
			{
				continue;
			}

			$context['poll']['choices'][] = [
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => $option,
				'is_last' => false
			];
		}

		// One empty option for those with js disabled...I know are few... :P
		$context['poll']['choices'][] = [
			'id' => $choice_id++,
			'number' => $choice_id,
			'label' => '',
			'is_last' => false
		];

		if (count($context['poll']['choices']) < 2)
		{
			$context['poll']['choices'][] = [
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => '',
				'is_last' => false
			];
		}

		$context['last_choice_id'] = $choice_id;
		$context['poll']['choices'][count($context['poll']['choices']) - 1]['is_last'] = true;
	}

	/**
	 * Prepares the post form for a poll
	 *
	 * @param string $destination
	 * @param string $page_title
	 * @param TemplateLayers $template_layers
	 */
	public function finalize_post_form(&$destination, &$page_title, $template_layers)
	{
		global $txt, $context;

		// There may be situations where the module is started but the poll is not to be created (cheating)
		if (self::$_make_poll)
		{
			$destination .= ';poll';
			$page_title = $txt['new_poll'];
			$context['make_poll'] = true;

			theme()->getLayers()->removeAll();
			theme()->getTemplates()->load('Poll');
			$template_layers->add('poll_edit');

			// Are we starting a poll? if set the poll icon as selected if available
			foreach ($context['icons'] as $i => $iValue)
			{
				if ($iValue['value'] === 'poll')
				{
					$context['icons'][$i]['selected'] = true;
					$context['icon'] = 'poll';
					$context['icon_url'] = $context['icons'][$i]['url'];
					break;
				}
			}
		}
	}

	/**
	 * Verify that there is only one poll per topic
	 *
	 * @param int $topic_info
	 */
	public function save_replying($topic_info)
	{
		// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
		if (!isset($_REQUEST['poll']))
		{
			return;
		}

		if ($topic_info['id_poll'] <= 0)
		{
			return;
		}

		$this->_unset_poll();
	}

	/**
	 * Checks the poll conditions before we go to save
	 *
	 * @param ErrorContext $post_errors
	 * @param array $topic_info
	 *
	 * @throws Exception no_access
	 */
	public function before_save_post($post_errors, $topic_info)
	{
		// Validate the poll...
		if (!empty($topic_info) && !isset($_REQUEST['msg']))
		{
			throw new Exception('no_access', false);
		}

		// This is a new topic... so it's a new poll.
		if (empty($topic_info))
		{
			isAllowedTo('poll_post');
		}
		// Can you add to your own topics?
		elseif ($this->user->id == $topic_info['id_member_started'] && !allowedTo('poll_add_any'))
		{
			isAllowedTo('poll_add_own');
		}
		// Can you add polls to any topic, then?
		else
		{
			isAllowedTo('poll_add_any');
		}

		if (!isset($_POST['question']) || trim($_POST['question']) === '')
		{
			$post_errors->addError('no_question');
		}

		$_POST['options'] = empty($_POST['options']) ? [] : Util::htmltrim__recursive($_POST['options']);

		// Get rid of empty ones.
		foreach ($_POST['options'] as $k => $option)
		{
			if ($option === '')
			{
				unset($_POST['options'][$k], $_POST['options'][$k]);
			}
		}

		// What are you going to vote between with one choice?!?
		if (count($_POST['options']) < 2)
		{
			$post_errors->addError('poll_few');
		}
		elseif (count($_POST['options']) > 256)
		{
			$post_errors->addError('poll_many');
		}
	}

	/**
	 * Create the poll!
	 *
	 * @param array $topicOptions
	 * @throws Exception
	 */
	public function pre_save_post(&$topicOptions)
	{
		$id_poll = self::$_make_poll ? $this->_createPoll($_POST, $_POST['guestname']) : 0;

		$topicOptions['poll'] = self::$_make_poll ? $id_poll : null;
	}

	/**
	 * Creates a poll based on an array (of POST'ed data)
	 *
	 * @param array $options
	 * @param string $user_name The username of the member that creates the poll
	 *
	 * @return int - the id of the newly created poll
	 * @throws Exception poll_range_error
	 */
	protected function _createPoll($options, $user_name)
	{
		global $board;

		// Make sure that the user has not entered a ridiculous number of options..
		if (empty($options['poll_max_votes']) || $options['poll_max_votes'] <= 0)
		{
			$poll_max_votes = 1;
		}
		elseif ($options['poll_max_votes'] > count($options['options']))
		{
			$poll_max_votes = count($options['options']);
		}
		else
		{
			$poll_max_votes = (int) $options['poll_max_votes'];
		}

		$poll_expire = (int) $options['poll_expire'];
		$poll_expire = $poll_expire > 9999 ? 9999 : (max($poll_expire, 0));

		$poll_hide = isset($options['poll_hide']) ? (int) $options['poll_hide'] : 0;

		$poll_change_vote = isset($options['poll_change_vote']) ? 1 : 0;
		$poll_guest_vote = isset($options['poll_guest_vote']) ? 1 : 0;

		// Make sure guests are actually allowed to vote generally.
		if ($poll_guest_vote !== 0)
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

			if (!in_array(-1, $allowedVoteGroups['allowed']))
			{
				$poll_guest_vote = 0;
			}
		}

		// If the user tries to set the poll too far in advance, don't let them.
		if (!empty($poll_expire) && $poll_expire < 1)
		{
			// @todo this fatal error should not be here
			throw new Exception('poll_range_error', false);
		}

		// Don't allow them to select option 2 for hidden results if it's not time limited.
		if (empty($poll_expire) && $poll_hide === 2)
		{
			$poll_hide = 1;
		}

		// Clean up the question and answers.
		$question = htmlspecialchars($options['question'], ENT_COMPAT, 'UTF-8');
		$question = Util::substr($question, 0, 255);
		$question = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $question);

		$poll_options = Util::htmlspecialchars__recursive($options['options']);

		// Finally, make the poll.
		require_once(SUBSDIR . '/Poll.subs.php');

		return createPoll(
			$question,
			$this->user->id,
			$user_name,
			$poll_max_votes,
			$poll_hide,
			$poll_expire,
			$poll_change_vote,
			$poll_guest_vote,
			$poll_options
		);
	}
}
