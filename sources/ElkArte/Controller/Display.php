<?php

/**
 * This controls topic display, with all related functions, its is the forum
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

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\MembersList;
use ElkArte\MessagesCallback\BodyParser\Normal;
use ElkArte\MessagesCallback\DisplayRenderer;
use ElkArte\MessagesDelete;
use ElkArte\MessageTopicIcons;
use ElkArte\User;
use ElkArte\ValuesContainer;

/**
 * This controller is the most important and probably most accessed of all.
 * It controls topic display, with all related.
 */
class Display extends AbstractController
{
	/** @var null|object The template layers object */
	protected $_template_layers;

	/** @var int The message id when in the form msg123 */
	protected $_virtual_msg = 0;

	/** @var int Show signatures? */
	protected $_show_signatures = 0;

	/** @var int|string Start viewing the topics from ... (page, all, other) */
	protected $_start;

	/** @var bool if to include unapproved posts in the count  */
	protected $includeUnapproved;

	/** @var array data returned from getTopicInfo() */
	protected $topicinfo;

	/** @var int number of messages to show per topic page */
	protected $messages_per_page;

	/** @var int message number to start the listing from */
	protected $start_from;

	/**
	 * Default action handler for this controller, if its called directly
	 */
	public function action_index()
	{
		// what to do... display things!
		$this->action_display();
	}

	/**
	 * The central part of the board - topic display.
	 *
	 * What it does:
	 *
	 * - This function loads the posts in a topic, so they can be displayed.
	 * - It requires a topic, and can go to the previous or next topic from it.
	 * - It jumps to the correct post depending on a number/time/IS_MSG passed.
	 * - It depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
	 * - It is accessed by ?topic=id_topic.START.
	 *
	 * @uses the main sub template of the Display template.
	 */
	public function action_display()
	{
		global $txt, $modSettings, $context, $settings, $options, $topic, $board;
		global $messages_request;

		$this->_events->trigger('pre_load', array('_REQUEST' => &$_REQUEST, 'topic' => $topic, 'board' => &$board));

		// What are you gonna display if these are empty?!
		if (empty($topic))
		{
			throw new Exception('no_board', false);
		}

		// And the topic functions
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// link prefetch is slower for the server, and it makes it impossible to know if they read it.
		stop_prefetching();

		// How much are we sticking on each page?
		$this->setMessagesPerPage();
		$this->includeUnapproved = $this->getIncludeUnapproved();
		$this->_start = $this->_req->getQuery('start');

		// Find the previous or next topic.  Make a fuss if there are no more.
		$this->getPreviousNextTopic();

		// Add 1 to the number of views of this topic (except for robots).
		$this->increaseTopicViews($topic);

		// Time to load the topic information
		$this->loadTopicInfo($topic, $board);

		// Is this a moved topic that we are redirecting to or coming from?
		$this->handleRedirection();

		// Trigger the topicinfo event for display
		$this->_events->trigger('topicinfo', array('topicinfo' => &$this->topicinfo, 'includeUnapproved' => $this->includeUnapproved));

		// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
		$total_visible_posts = $this->getVisiblePosts($this->topicinfo['num_replies']);

		// The start isn't a number; it's information about what to do, where to go.
		$this->makeStartAdjustments($total_visible_posts);

		// Censor the title...
		$this->topicinfo['subject'] = censor($this->topicinfo['subject']);

		// Allow addons access to the topicinfo array
		call_integration_hook('integrate_display_topic', array($this->topicinfo));

		// If all is set, figure out what needs to be done
		$can_show_all = $this->getCanShowAll($total_visible_posts);
		$this->setupShowAll($can_show_all, $total_visible_posts);

		// Time to place all the particulars into context for the template
		$this->setMessageContext();

		// Calculate the fastest way to get the messages!
		$ascending = true;
		$start = $this->_start;
		$limit = $this->messages_per_page;
		$firstIndex = 0;

		if ($start >= $total_visible_posts / 2 && $this->messages_per_page !== -1)
		{
			$ascending = false;
			$limit = $total_visible_posts <= $start + $limit ? $total_visible_posts - $start : $limit;
			$start = $total_visible_posts <= $start + $limit ? 0 : $total_visible_posts - $start - $limit;
			$firstIndex = $limit - 1;
		}

		// Taking care of member specific settings
		$limit_settings = array(
			'messages_per_page' => $this->messages_per_page,
			'start' => $start,
			'offset' => $limit,
		);

		// Get each post and poster in this topic.
		$topic_details = getTopicsPostsAndPoster($this->topicinfo['id_topic'], $limit_settings, $ascending);
		$messages = $topic_details['messages'];

		// Add the viewing member so their information is available for use in QR
		$posters = array_unique($topic_details['all_posters'] + [-1 => $this->user->id]);
		$all_posters = $topic_details['all_posters'];
		unset($topic_details);

		// Default this topic to not marked for notifications... of course...
		$context['is_marked_notify'] = false;

		$messages_request = false;
		$context['first_message'] = 0;
		$context['first_new_message'] = false;

		call_integration_hook('integrate_display_message_list', array(&$messages, &$posters));

		// If there _are_ messages here... (probably an error otherwise :!)
		if (!empty($messages))
		{
			// Mark the board as read or not ... calls updateReadNotificationsFor() sets $context['is_marked_notify']
			$this->markRead($messages, $board);

			$msg_parameters = array(
				'message_list' => $messages,
				'new_from' => $this->topicinfo['new_from'],
			);
			$msg_selects = array();
			$msg_tables = array();
			call_integration_hook('integrate_message_query', array(&$msg_selects, &$msg_tables, &$msg_parameters));

			MembersList::loadGuest();

			// What?  It's not like it *couldn't* be only guests in this topic...
			if (!empty($posters))
			{
				MembersList::load($posters);
			}

			// Load in the likes for this group of messages
			// If using quick reply, load the user into context for the poster area
			$this->prepareQuickReply();

			$messages_request = loadMessageRequest($msg_selects, $msg_tables, $msg_parameters);

			// Go to the last message if the given time is beyond the time of the last message.
			if ($this->start_from >= $this->topicinfo['num_replies'])
			{
				$this->start_from = $this->topicinfo['num_replies'];
				$context['start_from'] = $this->start_from;
			}

			// Since the anchor information is needed on the top of the page we load these variables beforehand.
			$context['first_message'] = $messages[$firstIndex] ?? $messages[0];
			$context['first_new_message'] = (int) $this->_start === (int) $this->start_from;
		}

		// Are we showing the signatures?
		$this->setSignatureShowStatus();

		// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
		// This will be called from the template.
		$bodyParser = new Normal(array(), false);
		$opt = new ValuesContainer([
			'icon_sources' => new MessageTopicIcons(!empty($modSettings['messageIconChecks_enable']), $settings['theme_dir']),
			'show_signatures' => $this->_show_signatures,
		]);
		$renderer = new DisplayRenderer($messages_request, $this->user, $bodyParser, $opt);

		$context['get_message'] = array($renderer, 'getContext');

		// Now set all the wonderful, wonderful permissions... like moderation ones...
		$this->setTopicCanPermissions();

		// Load up the Quick ModifyTopic and Quick Reply scripts
		loadJavascriptFile('topic.js');

		// Create the editor for the QR area
		$editorOptions = array(
			'id' => 'message',
			'value' => '',
			'labels' => array(
				'post_button' => $txt['post'],
			),
			// add height and width for the editor
			'height' => '250px',
			'width' => '100%',
			// We do XML preview here.
			'preview_type' => 1,
		);

		// Load the template basics now as template_layers is requested by the prepare_context event
		theme()->getTemplates()->load('Display');
		$this->_template_layers = theme()->getLayers();
		$this->_template_layers->addEnd('messages_informations');
		$context['sub_template'] = 'messages';

		// Trigger the prepare_context event for modules that have tied in to it
		$this->_events->trigger('prepare_context', array('editorOptions' => &$editorOptions, 'use_quick_reply' => !empty($options['display_quick_reply'])));

		// Load up the "double post" sequencing magic.
		if (!empty($options['display_quick_reply']))
		{
			checkSubmitOnce('register');
			$context['name'] = $_SESSION['guest_name'] ?? '';
			$context['email'] = $_SESSION['guest_email'] ?? '';
			if (!empty($options['use_editor_quick_reply']) && $context['can_reply'])
			{
				// Needed for the editor and message icons.
				require_once(SUBSDIR . '/Editor.subs.php');

				create_control_richedit($editorOptions);
			}
		}

		theme()->addJavascriptVar(array('notification_topic_notice' => $context['is_marked_notify'] ? $txt['notification_disable_topic'] : $txt['notification_enable_topic']), true);

		if ($context['can_send_topic'])
		{
			theme()->addJavascriptVar(array(
				'sendtopic_cancel' => $txt['modify_cancel'],
				'sendtopic_back' => $txt['back'],
				'sendtopic_close' => $txt['find_close'],
				'sendtopic_error' => $txt['send_error_occurred'],
				'required_field' => $txt['require_field']), true);
		}

		// Build the common to all buttons like Reply Notify Mark ....
		$this->buildNormalButtons();

		// Build specialized buttons, like moderation
		$this->buildModerationButtons();

		// Let's get nosey, who is viewing this topic?
		if (!empty($settings['display_who_viewing']))
		{
			require_once(SUBSDIR . '/Who.subs.php');
			formatViewers($this->topicinfo['id_topic'], 'topic');
		}

		// Did we report a post to a moderator just now?
		if (isset($this->_req->query->reportsent))
		{
			$this->_template_layers->add('report_sent');
		}

		// Quick reply & modify enabled?
		if ($context['can_reply'] && !empty($options['display_quick_reply']))
		{
			loadJavascriptFile('mentioning.js');
			$this->_template_layers->addBefore('quickreply', 'messages_informations');
		}

		// All of our buttons and indexes
		$this->_template_layers->add('pages_and_buttons');
	}

	/**
	 * Sets the message per page
	 */
	public function setMessagesPerPage()
	{
		global $modSettings, $options;

		$this->messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? (int) $options['messages_per_page'] : (int) $modSettings['defaultMaxMessages'];
	}

	/**
	 * Returns IF we are counting unapproved posts
	 *
	 * @return bool
	 */
	public function getIncludeUnapproved()
	{
		global $modSettings;

		return !$modSettings['postmod_active'] || allowedTo('approve_posts');
	}

	/**
	 * Return if we allow showing ALL messages for a topic vs pagination
	 *
	 * @param int $total_visible_posts
	 * @return bool
	 */
	public function getCanShowAll($total_visible_posts)
	{
		global $modSettings;

		return !empty($modSettings['enableAllMessages'])
			&& $total_visible_posts > $this->messages_per_page
			&& $total_visible_posts < $modSettings['enableAllMessages'];
	}

	/**
	 * If show all is requested, and allowed, setup to do just that
	 *
	 * @param bool $can_show_all
	 * @param int $total_visible_posts
	 * @return void
	 */
	public function setupShowAll($can_show_all, $total_visible_posts)
	{
		global $scripturl, $topic, $context;

		$all_requested = $this->_req->getQuery('all', 'trim', null);
		if (isset($all_requested))
		{
			// If all is set, but not allowed... just unset it.
			if (!$can_show_all)
			{
				unset($all_requested);
			}
			else
			{
				// Otherwise, it must be allowed... so pretend start was -1.
				$this->_start = -1;
			}
		}

		// Construct the page index, allowing for the .START method...
		$context['page_index'] = constructPageIndex($scripturl . '?topic=' . $topic . '.%1$d', $this->_start, $total_visible_posts, $this->messages_per_page, true, array('all' => $can_show_all, 'all_selected' => isset($all_requested)));
		$context['start'] = $this->_start;

		// Figure out all the link to the next/prev
		$context['links'] += array(
			'prev' => $this->_start >= $this->messages_per_page ? $scripturl . '?topic=' . $topic . '.' . ($this->_start - $this->messages_per_page) : '',
			'next' => $this->_start + $this->messages_per_page < $total_visible_posts ? $scripturl . '?topic=' . $topic . '.' . ($this->_start + $this->messages_per_page) : '',
		);

		// If they are viewing all the posts, show all the posts, otherwise limit the number.
		if ($can_show_all && isset($all_requested))
		{
			// No limit! (actually, there is a limit, but...)
			$this->messages_per_page = -1;

			// Set start back to 0...
			$this->_start = 0;
		}
	}

	/**
	 * Returns the previous or next topic based on the get/query value
	 * @return void
	 */
	public function getPreviousNextTopic()
	{
		global $board_info, $topic, $board, $context;

		$prev_next = $this->_req->getQuery('prev_next', 'trim');

		// Find the previous or next topic.  Make a fuss if there are no more.
		if ($prev_next === 'prev' || $prev_next === 'next')
		{
			// No use in calculating the next topic if there's only one.
			if ($board_info['num_topics'] > 1)
			{
				$topic = $prev_next === 'prev'
					? previousTopic($topic, $board, $this->user->id, $this->getIncludeUnapproved())
					: nextTopic($topic, $board, $this->user->id, $this->getIncludeUnapproved());

				$context['current_topic'] = $topic;
			}

			// Go to the newest message on this topic.
			$this->_start = 'new';
		}
	}

	/**
	 * Add one for the stats
	 * @param $topic
	 */
	public function increaseTopicViews($topic)
	{
		if ($this->user->possibly_robot === false
			&& (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] !== $topic))
		{
			increaseViewCounter($topic);
			$_SESSION['last_read_topic'] = $topic;
		}
	}

	/**
	 * Fetch all the topic information.  Provides addons a hook to add additional tables/selects
	 *
	 * @param int $topic
	 * @param int $board
	 * @throws \ElkArte\Exceptions\Exception on invalid topic value
	 */
	public function loadTopicInfo($topic, $board)
	{
		$topic_selects = [];
		$topic_tables = [];
		$topic_parameters = [
			'topic' => $topic,
			'member' => $this->user->id,
			'board' => (int) $board,
		];

		// Allow addons to add additional details to the topic query
		call_integration_hook('integrate_topic_query', array(&$topic_selects, &$topic_tables, &$topic_parameters));

		// Load the topic details
		$this->topicinfo = getTopicInfo($topic_parameters, 'all', $topic_selects, $topic_tables);

		// Nothing??
		if (empty($this->topicinfo))
		{
			throw new Exception('not_a_topic', false);
		}
	}

	/**
	 * Sometimes topics have been moved, this will direct the user to the right spot
	 */
	public function handleRedirection()
	{
		global $context;

		// Need to send the user to the new location?
		if (!empty($this->topicinfo['id_redirect_topic']) && !isset($this->_req->query->noredir))
		{
			markTopicsRead(array($this->user->id, $this->topicinfo['id_topic'], $this->topicinfo['id_last_msg'], 0), $this->topicinfo['new_from'] !== 0);
			redirectexit('topic=' . $this->topicinfo['id_redirect_topic'] . '.0;redirfrom=' . $this->topicinfo['id_topic']);
		}

		// Or are we here because we were redirected?
		if (isset($this->_req->query->redirfrom))
		{
			$redirfrom = $this->_req->getQuery('redirfrom', 'intval');
			$redir_topics = topicsList(array($redirfrom));
			if (!empty($redir_topics[$redirfrom]))
			{
				$context['topic_redirected_from'] = $redir_topics[$redirfrom];
				$context['topic_redirected_from']['redir_href'] = getUrl('topic', ['topic' => $context['topic_redirected_from']['id_topic'], 'start' => '0', 'subject' => $context['topic_redirected_from']['subject'], 'noredir']);
			}
		}
	}

	/**
	 * Number of posts that this user can see.  Will included unapproved for those with access
	 *
	 * @param int $num_replies
	 * @return int
	 */
	public function getVisiblePosts($num_replies)
	{
		if (!$this->includeUnapproved && $this->topicinfo['unapproved_posts'] && $this->user->is_guest === false)
		{
			$myUnapprovedPosts = unapprovedPosts($this->topicinfo['id_topic'], $this->user->id);

			return $num_replies + $myUnapprovedPosts + ($this->topicinfo['approved'] ? 1 : 0);
		}

		if ($this->user->is_guest)
		{
			return $num_replies + ($this->topicinfo['approved'] ? 1 : 0);
		}

		return $num_replies + $this->topicinfo['unapproved_posts'] + ($this->topicinfo['approved'] ? 1 : 0);
	}

	/**
	 * The start value from get can contain all manner of information on what to do.
	 * This converts new, from, msg into something useful, most times.
	 *
	 * @param int $total_visible_posts
	 */
	public function makeStartAdjustments($total_visible_posts)
	{
		global $modSettings;

		$start = $this->_start;
		if (!is_numeric($start))
		{
			// Redirect to the page and post with new messages
			if ($start === 'new')
			{
				// Guests automatically go to the last post.
				if ($this->user->is_guest)
				{
					$start = $total_visible_posts - 1;
				}
				else
				{
					// Fall through to the next if statement.
					$start = 'msg' . $this->topicinfo['new_from'];
				}
			}

			// Start from a certain time index, not a message.
			if (strpos($start, 'from') === 0)
			{
				$timestamp = (int) substr($start, 4);
				if ($timestamp === 0)
				{
					$start = 0;
				}
				else
				{
					// Find the number of messages posted before said time...
					$start = countNewPosts($this->topicinfo['id_topic'], $this->topicinfo, $timestamp);
				}
			}
			// Link to a message...
			elseif (strpos($start, 'msg') === 0)
			{
				$this->_virtual_msg = (int) substr($start, 3);
				if (!$this->topicinfo['unapproved_posts'] && $this->_virtual_msg >= $this->topicinfo['id_last_msg'])
				{
					$start = $total_visible_posts - 1;
				}
				elseif (!$this->topicinfo['unapproved_posts'] && $this->_virtual_msg <= $this->topicinfo['id_first_msg'])
				{
					$start = 0;
				}
				else
				{
					$only_approved = $modSettings['postmod_active'] && $this->topicinfo['unapproved_posts'] && !allowedTo('approve_posts');
					$start = countMessagesBefore($this->topicinfo['id_topic'], $this->_virtual_msg, false, $only_approved, $this->user->is_guest === false);
				}
			}
		}

		$this->start_from = $start;
		$this->_start = $start;
	}

	/**
	 * Sets all we know about a message into $context for template consumption.
	 * Note: After this processes, some amount of additional context is still added, read
	 * the code.
	 */
	public function setMessageContext()
	{
		global $context, $modSettings, $txt, $board_info;

		// Going to allow this to be indexed by Mr. Robot?
		$context['robot_no_index'] = $this->setRobotNoIndex();

		// Some basics for the template
		$context['num_replies'] = $this->topicinfo['num_replies'];
		$context['topic_first_message'] = $this->topicinfo['id_first_msg'];
		$context['topic_last_message'] = $this->topicinfo['id_last_msg'];
		$context['topic_unwatched'] = $this->topicinfo['unwatched'] ?? 0;
		$context['start_from'] = $this->start_from;

		// Did this user start the topic or not?
		$context['user']['started'] = $this->didThisUserStart();
		$context['topic_starter_id'] = $this->topicinfo['id_member_started'];

		// Add up unapproved replies to get real number of replies...
		$context['real_num_replies'] = $this->topicinfo['num_replies'];
		if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
		{
			$context['real_num_replies'] += $this->topicinfo['unapproved_posts'] - ($this->topicinfo['approved'] ? 0 : 1);
		}

		// When was the last time this topic was replied to?  Should we warn them about it?
		$context['oldTopicError'] = $this->warnOldTopic();

		// Are we showing signatures - or disabled fields?
		$context['signature_enabled'] = strpos($modSettings['signature_settings'], '1') === 0;
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];

		// Page title
		$context['page_title'] = $this->topicinfo['subject'];

		// Create a previous next string if the selected theme has it as a selected option.
		if ($modSettings['enablePreviousNext'])
		{
			$context['links'] += array(
				'go_prev' => getUrl('topic', ['topic' => $this->topicinfo['id_topic'], 'start' => '0', 'subject' => $this->topicinfo['subject'], 'prev_next' => 'prev']) . '#new',
				'go_next' => getUrl('topic', ['topic' => $this->topicinfo['id_topic'], 'start' => '0', 'subject' => $this->topicinfo['subject'], 'prev_next' => 'next']) . '#new'
			);
		}

		// Build the jump to box
		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => htmlspecialchars(strtr(strip_tags($board_info['name']), array('&amp;' => '&')), ENT_COMPAT),
			'child_level' => $board_info['child_level'],
		);

		// Build a list of this board's moderators.
		$context['moderators'] = &$board_info['moderators'];
		$context['link_moderators'] = [];

		// Information about the current topic...
		$context['is_locked'] = $this->topicinfo['locked'];
		$context['is_sticky'] = $this->topicinfo['is_sticky'];
		$context['is_very_hot'] = $this->topicinfo['num_replies'] >= $modSettings['hotTopicVeryPosts'];
		$context['is_hot'] = $this->topicinfo['num_replies'] >= $modSettings['hotTopicPosts'];
		$context['is_approved'] = $this->topicinfo['approved'];

		// Set the class of the current topic,  Hot, not so hot, locked, sticky
		determineTopicClass($context);

		// Set the topic's information for the template.
		$context['subject'] = $this->topicinfo['subject'];
		$context['num_views'] = $this->topicinfo['num_views'];
		$context['num_views_text'] = (int) $this->topicinfo['num_views'] === 1 ? $txt['read_one_time'] : sprintf($txt['read_many_times'], $this->topicinfo['num_views']);
		$context['mark_unread_time'] = !empty($this->_virtual_msg) ? $this->_virtual_msg : $this->topicinfo['new_from'];

		// Set a canonical URL for this page.
		$context['canonical_url'] = getUrl('topic', ['topic' => $this->topicinfo['id_topic'], 'start' => $context['start'], 'subject' => $this->topicinfo['subject']]);

		// For quick reply we need a response prefix in the default forum language.
		$context['response_prefix'] = response_prefix();

		$context['messages_per_page'] = $this->messages_per_page;

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => getUrl('topic', ['topic' => $this->topicinfo['id_topic'], 'start' => '0', 'subject' => $this->topicinfo['subject']]),
			'name' => $this->topicinfo['subject'],
		);
	}

	/**
	 * Sets if this is a page that we do or do not want bots to index
	 *
	 * @return bool
	 */
	public function setRobotNoIndex()
	{
		// Let's do some work on what to search index.
		if (count((array) $this->_req->query) > 2)
		{
			foreach (['topic', 'board', 'start', session_name()] as $key)
			{
				if (!isset($this->_req->query->$key))
				{
					return true;
				}
			}
		}

		return !empty($this->_start)
			&& (!is_numeric($this->_start) || $this->_start % $this->messages_per_page !== 0);
	}

	/**
	 * Return if the current user started this topic, as that may provide them additional permissions.
	 *
	 * @return bool
	 */
	public function didThisUserStart()
	{
		return ((int) $this->user->id === (int) $this->topicinfo['id_member_started']) && !$this->user->is_guest;
	}

	/**
	 * They Hey bub, what's with the necro-bump message
	 *
	 * @return bool
	 */
	public function warnOldTopic()
	{
		global $modSettings;

		if (!empty($modSettings['oldTopicDays']))
		{
			$mgsOptions = basicMessageInfo($this->topicinfo['id_last_msg'], true);

			return $mgsOptions['poster_time'] + $modSettings['oldTopicDays'] * 86400 < time()
				&& empty($this->topicinfo['is_sticky']);
		}

		return false;
	}

	/**
	 * Keeps track of where the user is in reading this topic.
	 *
	 * @param array $messages
	 * @param int $board
	 */
	private function markRead($messages, $board)
	{
		global $modSettings;

		// Guests can't mark topics read or for notifications, just can't sorry.
		if ($this->user->is_guest === false && !empty($messages))
		{
			$boardseen = isset($this->_req->query->boardseen);

			$mark_at_msg = max($messages);
			if ($mark_at_msg >= $this->topicinfo['id_last_msg'])
			{
				$mark_at_msg = $modSettings['maxMsgID'];
			}

			if ($mark_at_msg >= $this->topicinfo['new_from'])
			{
				markTopicsRead(array($this->user->id, $this->topicinfo['id_topic'], $mark_at_msg, $this->topicinfo['unwatched']), $this->topicinfo['new_from'] !== 0);
				$numNewTopics = getUnreadCountSince($board, empty($_SESSION['id_msg_last_visit']) ? 0 : $_SESSION['id_msg_last_visit']);

				if (empty($numNewTopics))
				{
					$boardseen = true;
				}
			}

			updateReadNotificationsFor($this->topicinfo['id_topic'], $board);

			// Mark board as seen if we came using last post link from BoardIndex. (or other places...)
			if ($boardseen)
			{
				require_once(SUBSDIR . '/Boards.subs.php');
				markBoardsRead($board, false, false);
			}
		}
	}

	/**
	 * If the QR is on, we need to load the user information into $context, so we
	 * can show the new improved 2.0 QR area
	 */
	public function prepareQuickReply()
	{
		global $options, $context;

		if (empty($options['hide_poster_area']) && $options['display_quick_reply'])
		{
			// First lets load the profile array
			$thisUser = MembersList::get(User::$info->id);
			$thisUser->loadContext();
			$context['thisMember'] = [
				'id' => 'new',
				'is_message_author' => true,
				'member' => $thisUser->toArray()['data']
			];
		}
	}

	/**
	 * Sets if we are showing signatures or not
	 */
	public function setSignatureShowStatus()
	{
		global $modSettings;

		list ($sig_limits) = explode(':', $modSettings['signature_settings']);
		$signature_settings = explode(',', $sig_limits);
		if ($this->user->is_guest)
		{
			$this->_show_signatures = !empty($signature_settings[8]) ? (int) $signature_settings[8] : 0;
		}
		else
		{
			$this->_show_signatures = !empty($signature_settings[9]) ? (int) $signature_settings[9] : 0;
		}
	}

	/**
	 * Loads into context the various message/topic permissions so the template
	 * knows what buttons etc. to show
	 */
	public function setTopicCanPermissions()
	{
		global $modSettings, $context, $settings, $board;

		// First the common ones
		$common_permissions = array(
			'can_approve' => 'approve_posts',
			'can_ban' => 'manage_bans',
			'can_sticky' => 'make_sticky',
			'can_merge' => 'merge_any',
			'can_split' => 'split_any',
			'can_mark_notify' => 'mark_any_notify',
			'can_send_topic' => 'send_topic',
			'can_send_pm' => 'pm_send',
			'can_send_email' => 'send_email_to_members',
			'can_report_moderator' => 'report_any',
			'can_moderate_forum' => 'moderate_forum',
			'can_issue_warning' => 'issue_warning',
			'can_restore_topic' => 'move_any',
			'can_restore_msg' => 'move_any',
		);
		foreach ($common_permissions as $contextual => $perm)
		{
			$context[$contextual] = allowedTo($perm);
		}

		// Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
		$anyown_permissions = array(
			'can_move' => 'move',
			'can_lock' => 'lock',
			'can_delete' => 'remove',
			'can_reply' => 'post_reply',
			'can_reply_unapproved' => 'post_unapproved_replies',
		);
		foreach ($anyown_permissions as $contextual => $perm)
		{
			$context[$contextual] = allowedTo($perm . '_any') || ($this->didThisUserStart() && allowedTo($perm . '_own'));
		}

		// Cleanup all the permissions with extra stuff...
		$context['can_mark_notify'] &= !$context['user']['is_guest'];
		$context['can_reply'] &= empty($this->topicinfo['locked']) || allowedTo('moderate_board');
		$context['can_reply_unapproved'] &= $modSettings['postmod_active'] && (empty($this->topicinfo['locked']) || allowedTo('moderate_board'));
		$context['can_issue_warning'] &= featureEnabled('w') && !empty($modSettings['warning_enable']);

		// Handle approval flags...
		$context['can_reply_approved'] = $context['can_reply'];

		// Guests do not have post_unapproved_replies_own permission, so it's always post_unapproved_replies_any
		if ($this->user->is_guest && allowedTo('post_unapproved_replies_any'))
		{
			$context['can_reply_approved'] = false;
		}

		$context['can_reply'] |= $context['can_reply_unapproved'];
		$context['can_quote'] = $context['can_reply'] && (empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC'])));
		$context['can_mark_unread'] = $this->user->is_guest === false && $settings['show_mark_read'];
		$context['can_unwatch'] = $this->user->is_guest === false && $modSettings['enable_unwatch'];
		$context['can_send_topic'] = (!$modSettings['postmod_active'] || $this->topicinfo['approved']) && allowedTo('send_topic');
		$context['can_print'] = empty($modSettings['disable_print_topic']);

		// Start this off for quick moderation - it will be or'd for each post.
		$context['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && $this->didThisUserStart());

		// Can restore topic?  That's if the topic is in the recycle board and has a previous restore state.
		$context['can_restore_topic'] &= !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board && !empty($this->topicinfo['id_previous_board']);
		$context['can_restore_msg'] &= !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board && !empty($this->topicinfo['id_previous_topic']);
	}

	/**
	 * Loads into $context the normal button array for template use.
	 * Calls integrate_display_buttons hook
	 */
	public function buildNormalButtons()
	{
		global $context, $txt;

		// Build the normal button array.
		$context['normal_buttons'] = array(
			'reply' => array(
				'test' => 'can_reply',
				'text' => 'reply',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'post', 'topic' => $context['current_topic'] . '.' . $context['start'], 'last_msg' => $this->topicinfo['id_last_msg']]),
				'active' => true
			),
			'notify' => array(
				'test' => 'can_mark_notify',
				'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify',
				'lang' => true,
				'custom' => 'onclick="return notifyButton(this);"',
				'url' => getUrl('action', ['action' => 'notify', 'sa' => $context['is_marked_notify'] ? 'off' : 'on', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'mark_unread' => array(
				'test' => 'can_mark_unread',
				'text' => 'mark_unread',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'topic', 't' => $context['mark_unread_time'], 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'unwatch' => array(
				'test' => 'can_unwatch',
				'text' => ($context['topic_unwatched'] ? '' : 'un') . 'watch',
				'lang' => true,
				'custom' => 'onclick="return unwatchButton(this);"',
				'url' => getUrl('action', ['action' => 'unwatchtopic', 'sa' => $context['topic_unwatched'] ? 'off' : 'on', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'send' => array(
				'test' => 'can_send_topic',
				'text' => 'send_topic',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'emailuser', 'sa' => 'sendtopic', 'topic' => $context['current_topic'] . '.0']),
				'custom' => 'onclick="return sendtopicOverlayDiv(this.href, \'' . $txt['send_topic'] . '\');"'
			),
			'print' => array(
				'test' => 'can_print',
				'text' => 'print',
				'lang' => true,
				'custom' => 'rel="nofollow"',
				'class' => 'new_win',
				'url' => getUrl('action', ['action' => 'topic', 'sa' => 'printpage', 'topic' => $context['current_topic'] . '.0'])
			),
		);

		// Allow adding new buttons easily.
		call_integration_hook('integrate_display_buttons');
	}

	/**
	 * Loads into $context the moderation button array for template use.
	 * Call integrate_mod_buttons hook
	 */
	public function buildModerationButtons()
	{
		global $context, $txt;

		// Build the mod button array
		$context['mod_buttons'] = array(
			'move' => array(
				'test' => 'can_move',
				'text' => 'move_topic',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'movetopic', 'current_board' => $context['current_board'], 'topic' => $context['current_topic'] . '.0'])
			),
			'delete' => array(
				'test' => 'can_delete',
				'text' => 'remove_topic',
				'lang' => true,
				'custom' => 'onclick="return confirm(\'' . $txt['are_sure_remove_topic'] . '\');"',
				'url' => getUrl('action', ['action' => 'removetopic2', 'topic' => $context['current_topic'] . '.0', '{session_data}'])
			),
			'lock' => array(
				'test' => 'can_lock',
				'text' => empty($this->topicinfo['locked']) ? 'set_lock' : 'set_unlock',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'topic', 'sa' => 'lock', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'sticky' => array(
				'test' => 'can_sticky',
				'text' => empty($this->topicinfo['is_sticky']) ? 'set_sticky' : 'set_nonsticky',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'topic', 'sa' => 'sticky', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'merge' => array(
				'test' => 'can_merge',
				'text' => 'merge',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'mergetopics', 'board' => $context['current_board'] . '.0', 'from' => $context['current_topic']])
			),
		);

		// Restore topic. eh?  No monkey business.
		if ($context['can_restore_topic'])
		{
			$context['mod_buttons'][] = array(
				'text' => 'restore_topic',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'restoretopic', 'topics' => $context['current_topic'], '{session_data}'])
			);
		}

		// Allow adding new buttons easily.
		call_integration_hook('integrate_mod_buttons');
	}

	/**
	 * If we are in a topic and don't have permission to approve it then duck out now.
	 * This is an abuse of the method, but it's easier that way.
	 *
	 * @param string $action the function name of the current action
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception not_a_topic
	 */
	public function trackStats($action = '')
	{
		global $topic, $board_info;

		if (!empty($topic)
			&& empty($board_info['cur_topic_approved'])
			&& ($this->user->id != $board_info['cur_topic_starter'] || $this->user->is_guest)
			&& !allowedTo('approve_posts'))
		{
			throw new Exception('not_a_topic', false);
		}

		return parent::trackStats($action);
	}

	/**
	 * In-topic quick moderation.
	 *
	 * Accessed by ?action=quickmod2
	 */
	public function action_quickmod2()
	{
		global $topic, $board, $context, $modSettings;

		// Check the session = get or post.
		checkSession('request');

		require_once(SUBSDIR . '/Messages.subs.php');

		if (empty($this->_req->post->msgs))
		{
			redirectexit('topic=' . $topic . '.' . $this->_req->getQuery('start', 'intval'));
		}

		$messages = array_map('intval', $this->_req->post->msgs);

		// We are restoring messages. We handle this in another place.
		if (isset($this->_req->query->restore_selected))
		{
			redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		if (isset($this->_req->query->split_selection))
		{
			$mgsOptions = basicMessageInfo(min($messages), true);

			$_SESSION['split_selection'][$topic] = $messages;
			redirectexit('action=splittopics;sa=selectTopics;topic=' . $topic . '.0;subname_enc=' . urlencode($mgsOptions['subject']) . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		require_once(SUBSDIR . '/Topic.subs.php');
		$topic_info = getTopicInfo($topic);

		// Allowed to delete any message?
		$allowed_all = $this->canDeleteAll($topic_info);

		// Make sure they're allowed to delete their own messages, if not any.
		if (!$allowed_all)
		{
			isAllowedTo('delete_own');
		}

		// Allowed to remove which messages?
		$messages = determineRemovableMessages($topic, $messages, $allowed_all);

		// Get the first message in the topic - because you can't delete that!
		$first_message = (int) $topic_info['id_first_msg'];
		$last_message = (int) $topic_info['id_last_msg'];
		$remover = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);

		// Delete all the messages we know they can delete. ($messages)
		foreach ($messages as $message => $info)
		{
			$message = (int) $message;

			// Just skip the first message - if it's not the last.
			if ($message === $first_message && $message !== $last_message)
			{
				continue;
			}

			// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
			if ($message === $first_message)
			{
				$topicGone = true;
			}

			$remover->removeMessage($message);
		}

		redirectexit(!empty($topicGone) ? 'board=' . $board : 'topic=' . $topic . '.' . (int) $this->_req->query->start);
	}

	/**
	 * Determine if this user can delete all replies in this message
	 *
	 * @param array $topic_info
	 * @return bool
	 */
	public function canDeleteAll($topic_info)
	{
		if (allowedTo('delete_any'))
		{
			return true;
		}

		// Allowed to delete replies to their messages?
		if (allowedTo('delete_replies'))
		{
			return (int) $topic_info['id_member_started'] === (int) $this->user->id;
		}

		return false;
	}
}
