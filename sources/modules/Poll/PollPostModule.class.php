<?php

/**
 * This file contains several functions for retrieving and manipulating polls.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Class Poll_Post_Module
 *
 * This class contains all matter of things related to creating polls
 */
class Poll_Post_Module extends ElkArte\sources\modules\Abstract_Module
{
	protected static $_make_poll = false;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $context, $modSettings;

		$context['can_add_poll'] = false;
		$context['make_poll'] = false;

		$return = array();
		if (!empty($modSettings['pollMode']))
		{
			$return = array(
				array('prepare_post', array('Poll_Post_Module', 'prepare_post'), array('topic', 'topic_attributes')),
				array('prepare_context', array('Poll_Post_Module', 'prepare_context'), array('topic_attributes', 'topic', 'board')),
				array('finalize_post_form', array('Poll_Post_Module', 'finalize_post_form'), array('destination', 'page_title', 'template_layers')),
			);
		}

		// Posting a poll?
		self::$_make_poll = isset($_REQUEST['poll']);

		if (self::$_make_poll)
		{
			return array_merge($return, array(
				array('before_save_post', array('Poll_Post_Module', 'before_save_post'), array()),
				array('save_replying', array('Poll_Post_Module', 'save_replying'), array()),
				array('pre_save_post', array('Poll_Post_Module', 'pre_save_post'), array('topicOptions')),
			));
		}
		else
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
		if (isset($_REQUEST['poll']) && !empty($topic) && !isset($_REQUEST['msg']))
		{
			$this->_unset_poll();
		}
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
	 * @throws Elk_Exception
	 */
	public function prepare_context($topic_attributes, $topic, $board)
	{
		global $context, $user_info, $txt;

		if (!empty($topic))
		{
			// If this topic already has a poll, they sure can't add another.
			if (isset($_REQUEST['poll']) && $topic_attributes['id_poll'] > 0)
			{
				return $this->_unset_poll();
			}

			// It's a new reply
			if (empty($_REQUEST['msg']))
				$context['can_add_poll'] = false;
			else
				$context['can_add_poll'] = (allowedTo('poll_add_any') || (!empty($_REQUEST['msg']) && $topic_attributes['id_first_msg'] == $_REQUEST['msg'] && allowedTo('poll_add_own'))) && $topic_attributes['id_poll'] <= 0;
		}
		else
		{
			$context['can_add_poll'] = allowedTo('poll_add_any') || allowedTo('poll_add_own');
		}

		if ($context['can_add_poll'])
		{
			addJavascriptVar(array(
				'poll_remove' => $txt['poll_remove'],
				'poll_add' => $txt['add_poll']), true);
		}

		// Check the users permissions - is the user allowed to add or post a poll?
		if (self::$_make_poll)
		{
			// New topic, new poll.
			if (empty($topic))
				isAllowedTo('poll_post');
			// This is an old topic - but it is yours!  Can you add to it?
			elseif ($user_info['id'] == $topic_attributes['id_member'] && !allowedTo('poll_add_any'))
				isAllowedTo('poll_add_own');
			// If you're not the owner, can you add to any poll?
			else
				isAllowedTo('poll_add_any');
			$context['can_moderate_poll'] = true;

			require_once(SUBSDIR . '/Members.subs.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

			// Set up the poll options.
			$context['poll'] = array(
				'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
				'hide_results' => empty($_POST['poll_hide']) ? 0 : (int) $_POST['poll_hide'],
				'expiration' => !isset($_POST['poll_expire']) ? '' : (int) $_POST['poll_expire'],
				'change_vote' => isset($_POST['poll_change_vote']),
				'guest_vote' => isset($_POST['poll_guest_vote']),
				'guest_vote_allowed' => in_array(-1, $allowedVoteGroups['allowed']),
			);

			$this->_preparePollContext();
		}

		return true;
	}

	/**
	 * Prepares the post form for a poll
	 *
	 * @param string $destination
	 * @param string $page_title
	 * @param Template_Layers $template_layers
	 * @throws Elk_Exception
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
			loadTemplate('Poll');
			$template_layers->add('poll_edit');

			// Are we starting a poll? if set the poll icon as selected if its available
			for ($i = 0, $n = count($context['icons']); $i < $n; $i++)
			{
				if ($context['icons'][$i]['value'] == 'poll')
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
	public function save_replying(&$topic_info)
	{
		// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
		if (isset($_REQUEST['poll']) && $topic_info['id_poll'] > 0)
			$this->_unset_poll();
	}

	/**
	 * Checks the poll conditions before we go to save
	 *
	 * @param ErrorContext $post_errors
	 * @param array        $topic_info
	 *
	 * @throws Elk_Exception no_access
	 */
	public function before_save_post($post_errors, $topic_info)
	{
		global $user_info;

		// Validate the poll...
		if (!empty($topic_info) && !isset($_REQUEST['msg']))
			throw new Elk_Exception('no_access', false);

		// This is a new topic... so it's a new poll.
		if (empty($topic_info))
			isAllowedTo('poll_post');
		// Can you add to your own topics?
		elseif ($user_info['id'] == $topic_info['id_member_started'] && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_own');
		// Can you add polls to any topic, then?
		else
			isAllowedTo('poll_add_any');

		if (!isset($_POST['question']) || trim($_POST['question']) == '')
			$post_errors->addError('no_question');

		$_POST['options'] = empty($_POST['options']) ? array() : htmltrim__recursive($_POST['options']);

		// Get rid of empty ones.
		foreach ($_POST['options'] as $k => $option)
		{
			if ($option == '')
				unset($_POST['options'][$k], $_POST['options'][$k]);
		}

		// What are you going to vote between with one choice?!?
		if (count($_POST['options']) < 2)
			$post_errors->addError('poll_few');
		elseif (count($_POST['options']) > 256)
			$post_errors->addError('poll_many');
	}

	/**
	 * Create the poll!
	 *
	 * @param array $topicOptions
	 * @throws Elk_Exception
	 */
	public function pre_save_post(&$topicOptions)
	{
		// Make the poll...
		if (self::$_make_poll)
			$id_poll = $this->_createPoll($_POST, $_POST['guestname']);
		else
			$id_poll = 0;

		$topicOptions['poll'] = self::$_make_poll ? $id_poll : null;
	}

	/**
	 * Loads in context stuff related to polls
	 */
	protected function _preparePollContext()
	{
		global $context;

		$context['poll']['question'] = isset($_REQUEST['question']) ? Util::htmlspecialchars(trim($_REQUEST['question'])) : '';

		$context['choices'] = $context['poll']['choices'] = array();
		// @deprecated since 1.1 - backward compatibility with 1.0
		$context['choices'] &= $context['poll']['choices'];
		$choice_id = 0;

		$_POST['options'] = empty($_POST['options']) ? array() : htmlspecialchars__recursive($_POST['options']);
		foreach ($_POST['options'] as $option)
		{
			if (trim($option) == '')
				continue;

			$context['poll']['choices'][] = array(
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => $option,
				'is_last' => false
			);
		}

		// One empty option for those with js disabled...I know are few... :P
		$context['poll']['choices'][] = array(
			'id' => $choice_id++,
			'number' => $choice_id,
			'label' => '',
			'is_last' => false
		);

		if (count($context['poll']['choices']) < 2)
		{
			$context['poll']['choices'][] = array(
				'id' => $choice_id++,
				'number' => $choice_id,
				'label' => '',
				'is_last' => false
			);
		}

		$context['last_choice_id'] = $choice_id;
		$context['poll']['choices'][count($context['poll']['choices']) - 1]['is_last'] = true;
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
	 * Creates a poll based on an array (of POST'ed data)
	 *
	 * @param mixed[] $options
	 * @param string  $user_name The username of the member that creates the poll
	 *
	 * @return int - the id of the newly created poll
	 * @throws Elk_Exception poll_range_error
	 */
	protected function _createPoll($options, $user_name)
	{
		global $user_info, $board;

		// Make sure that the user has not entered a ridiculous number of options..
		if (empty($options['poll_max_votes']) || $options['poll_max_votes'] <= 0)
			$poll_max_votes = 1;
		elseif ($options['poll_max_votes'] > count($options['options']))
			$poll_max_votes = count($options['options']);
		else
			$poll_max_votes = (int) $options['poll_max_votes'];

		$poll_expire = (int) $options['poll_expire'];
		$poll_expire = $poll_expire > 9999 ? 9999 : ($poll_expire < 0 ? 0 : $poll_expire);

		// Just set it to zero if it's not there..
		if (isset($options['poll_hide']))
			$poll_hide = (int) $options['poll_hide'];
		else
			$poll_hide = 0;

		$poll_change_vote = isset($options['poll_change_vote']) ? 1 : 0;
		$poll_guest_vote = isset($options['poll_guest_vote']) ? 1 : 0;

		// Make sure guests are actually allowed to vote generally.
		if ($poll_guest_vote)
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

			if (!in_array(-1, $allowedVoteGroups['allowed']))
				$poll_guest_vote = 0;
		}

		// If the user tries to set the poll too far in advance, don't let them.
		if (!empty($poll_expire) && $poll_expire < 1)
			// @todo this fatal error should not be here
			throw new Elk_Exception('poll_range_error', false);
		// Don't allow them to select option 2 for hidden results if it's not time limited.
		elseif (empty($poll_expire) && $poll_hide == 2)
			$poll_hide = 1;

		// Clean up the question and answers.
		$question = htmlspecialchars($options['question'], ENT_COMPAT, 'UTF-8');
		$question = Util::substr($question, 0, 255);
		$question = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $question);
		$poll_options = htmlspecialchars__recursive($options['options']);

		// Finally, make the poll.
		require_once(SUBSDIR . '/Poll.subs.php');
		$id_poll = createPoll(
			$question,
			$user_info['id'],
			$user_name,
			$poll_max_votes,
			$poll_hide,
			$poll_expire,
			$poll_change_vote,
			$poll_guest_vote,
			$poll_options
		);

		return $id_poll;
	}
}
