<?php

/**
 * This controls topic display, with all related functions, its is the forum
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * This controller is the most important and probably most accessed of all.
 * It controls topic display, with all related.
 */
class Display extends \ElkArte\AbstractController
{
	/**
	 * The template layers object
	 * @var null|object
	 */
	protected $_template_layers = null;

	/**
	 * The message id when in the form msg123
	 * @var int
	 */
	protected $_virtual_msg = 0;

	/**
	 * The class that takes care of rendering the message icons (\ElkArte\MessageTopicIcons)
	 * @var null|\ElkArte\MessageTopicIcons
	 */
	protected $_icon_sources = null;

	/**
	 * Show signatures?
	 *
	 * @var int
	 */
	protected $_show_signatures = 0;

	/**
	 * Start viewing the topics from ... (page, all, other)
	 * @var int|string
	 */
	private $_start;

	/**
	 * Default action handler for this controller
	 */
	public function action_index()
	{
		// what to do... display things!
		$this->action_display();
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
		global $user_info, $topic, $board_info;

		if (!empty($topic) && empty($board_info['cur_topic_approved']) && !allowedTo('approve_posts') && ($user_info['id'] != $board_info['cur_topic_starter'] || $user_info['is_guest']))
		{
			throw new \ElkArte\Exceptions\Exception('not_a_topic', false);
		}

		return parent::trackStats($action);
	}

	/**
	 * The central part of the board - topic display.
	 *
	 * What it does:
	 *
	 * - This function loads the posts in a topic up so they can be displayed.
	 * - It requires a topic, and can go to the previous or next topic from it.
	 * - It jumps to the correct post depending on a number/time/IS_MSG passed.
	 * - It depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
	 * - It is accessed by ?topic=id_topic.START.
	 *
	 * @uses the main sub template of the Display template.
	 */
	public function action_display()
	{
		global $txt, $modSettings, $context, $settings;
		global $options, $user_info, $board_info, $topic, $board;
		global $attachments, $messages_request;

		$this->_events->trigger('pre_load', array('_REQUEST' => &$_REQUEST, 'topic' => $topic, 'board' => &$board));

		// What are you gonna display if these are empty?!
		if (empty($topic))
			throw new \ElkArte\Exceptions\Exception('no_board', false);

		// Load the template
		theme()->getTemplates()->load('Display');
		$context['sub_template'] = 'messages';

		// And the topic functions
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
		stop_prefetching();

		// How much are we sticking on each page?
		$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$this->_template_layers = theme()->getLayers();
		$this->_template_layers->addEnd('messages_informations');
		$includeUnapproved = !$modSettings['postmod_active'] || allowedTo('approve_posts');

		// Let's do some work on what to search index.
		if (count((array) $this->_req->query) > 2)
		{
			foreach ($this->_req->query as $k => $v)
			{
				if (!in_array($k, array('topic', 'board', 'start', session_name())))
					$context['robot_no_index'] = true;
			}
		}

		$this->_start = $this->_req->getQuery('start');
		if (!empty($this->_start) && (!is_numeric($this->_start) || $this->_start % $context['messages_per_page'] !== 0))
			$context['robot_no_index'] = true;

		// Find the previous or next topic.  Make a fuss if there are no more.
		if ($this->_req->getQuery('prev_next') === 'prev' || $this->_req->getQuery('prev_next') === 'next')
		{
			// No use in calculating the next topic if there's only one.
			if ($board_info['num_topics'] > 1)
			{
				$topic = $this->_req->query->prev_next === 'prev'
					? previousTopic($topic, $board, $user_info['id'], $includeUnapproved)
					: nextTopic($topic, $board, $user_info['id'], $includeUnapproved);
				$context['current_topic'] = $topic;
			}

			// Go to the newest message on this topic.
			$this->_start = 'new';
		}

		// Add 1 to the number of views of this topic (except for robots).
		if (!$user_info['possibly_robot'] && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $topic))
		{
			increaseViewCounter($topic);
			$_SESSION['last_read_topic'] = $topic;
		}

		$topic_selects = array();
		$topic_tables = array();
		$topic_parameters = array(
			'topic' => $topic,
			'member' => $user_info['id'],
			'board' => (int) $board,
		);

		// Allow addons to add additional details to the topic query
		call_integration_hook('integrate_topic_query', array(&$topic_selects, &$topic_tables, &$topic_parameters));

		// Load the topic details
		$topicinfo = getTopicInfo($topic_parameters, 'all', $topic_selects, $topic_tables);
		if (empty($topicinfo))
			throw new \ElkArte\Exceptions\Exception('not_a_topic', false);

		// Is this a moved topic that we are redirecting to?
		if (!empty($topicinfo['id_redirect_topic']) && !isset($this->_req->query->noredir))
		{
			markTopicsRead(array($user_info['id'], $topicinfo['id_topic'], $topicinfo['id_last_msg'], 0), $topicinfo['new_from'] !== 0);
			redirectexit('topic=' . $topicinfo['id_redirect_topic'] . '.0;redirfrom=' . $topicinfo['id_topic']);
		}

		$context['real_num_replies'] = $context['num_replies'] = $topicinfo['num_replies'];
		$context['topic_first_message'] = $topicinfo['id_first_msg'];
		$context['topic_last_message'] = $topicinfo['id_last_msg'];
		$context['topic_unwatched'] = isset($topicinfo['unwatched']) ? $topicinfo['unwatched'] : 0;
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

		// Did this user start the topic or not?
		$context['user']['started'] = $user_info['id'] == $topicinfo['id_member_started'] && !$user_info['is_guest'];
		$context['topic_starter_id'] = $topicinfo['id_member_started'];

		$this->_events->trigger('topicinfo', array('topicinfo' => &$topicinfo, 'includeUnapproved' => $includeUnapproved));

		// Add up unapproved replies to get real number of replies...
		if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
			$context['real_num_replies'] += $topicinfo['unapproved_posts'] - ($topicinfo['approved'] ? 0 : 1);

		// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
		if (!$includeUnapproved && $topicinfo['unapproved_posts'] && !$user_info['is_guest'])
		{
			$myUnapprovedPosts = unapprovedPosts($topicinfo['id_topic'], $user_info['id']);

			$total_visible_posts = $context['num_replies'] + $myUnapprovedPosts + ($topicinfo['approved'] ? 1 : 0);
		}
		elseif ($user_info['is_guest'])
			$total_visible_posts = $context['num_replies'] + ($topicinfo['approved'] ? 1 : 0);
		else
			$total_visible_posts = $context['num_replies'] + $topicinfo['unapproved_posts'] + ($topicinfo['approved'] ? 1 : 0);

		// When was the last time this topic was replied to?  Should we warn them about it?
		if (!empty($modSettings['oldTopicDays']))
		{
			$mgsOptions = basicMessageInfo($topicinfo['id_last_msg'], true);
			$context['oldTopicError'] = $mgsOptions['poster_time'] + $modSettings['oldTopicDays'] * 86400 < time() && empty($topicinfo['is_sticky']);
		}
		else
			$context['oldTopicError'] = false;

		// The start isn't a number; it's information about what to do, where to go.
		if (!is_numeric($this->_start))
		{
			// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
			if ($this->_start === 'new')
			{
				// Guests automatically go to the last post.
				if ($user_info['is_guest'])
				{
					$context['start_from'] = $total_visible_posts - 1;
					$this->_start = $context['start_from'];
				}
				else
				{
					// Fall through to the next if statement.
					$this->_start = 'msg' . $topicinfo['new_from'];
				}
			}

			// Start from a certain time index, not a message.
			if (substr($this->_start, 0, 4) === 'from')
			{
				$timestamp = (int) substr($this->_start, 4);
				if ($timestamp === 0)
					$this->_start = 0;
				else
				{
					// Find the number of messages posted before said time...
					$context['start_from'] = countNewPosts($topicinfo['id_topic'], $topicinfo, $timestamp);
					$this->_start = $context['start_from'];
				}
			}
			// Link to a message...
			elseif (substr($this->_start, 0, 3) === 'msg')
			{
				$this->_virtual_msg = (int) substr($this->_start, 3);
				if (!$topicinfo['unapproved_posts'] && $this->_virtual_msg >= $topicinfo['id_last_msg'])
					$context['start_from'] = $total_visible_posts - 1;
				elseif (!$topicinfo['unapproved_posts'] && $this->_virtual_msg <= $topicinfo['id_first_msg'])
					$context['start_from'] = 0;
				else
				{
					$only_approved = $modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts');
					$context['start_from'] = countMessagesBefore($topicinfo['id_topic'], $this->_virtual_msg, false, $only_approved, !$user_info['is_guest']);
				}

				// We need to reverse the start as well in this case.
				$this->_start = $context['start_from'];
			}
		}

		// Create a previous next string if the selected theme has it as a selected option.
		if ($modSettings['enablePreviousNext'])
		{
			$context['links'] += array(
				'go_prev' => getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => '0', 'subject' => $topicinfo['subject'], 'prev_next' => 'prev']) . '#new',
				'go_next' => getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => '0', 'subject' => $topicinfo['subject'], 'prev_next' => 'next']) . '#new'
			);
		}

		// Check if spellchecking is both enabled and actually working. (for quick reply.)
		$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
		if ($context['show_spellchecking'])
			loadJavascriptFile('spellcheck.js', array('defer' => true));

		// Are we showing signatures - or disabled fields?
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

		// Censor the title...
		$topicinfo['subject'] = censor($topicinfo['subject']);
		$context['page_title'] = $topicinfo['subject'];

		// Allow addons access to the topicinfo array
		call_integration_hook('integrate_display_topic', array($topicinfo));

		// Default this topic to not marked for notifications... of course...
		$context['is_marked_notify'] = false;

		// Did we report a post to a moderator just now?
		$context['report_sent'] = isset($this->_req->query->reportsent);
		if ($context['report_sent'])
			$this->_template_layers->add('report_sent');

		// Let's get nosey, who is viewing this topic?
		if (!empty($settings['display_who_viewing']))
		{
			require_once(SUBSDIR . '/Who.subs.php');
			formatViewers($topicinfo['id_topic'], 'topic');
		}

		// If all is set, but not allowed... just unset it.
		$can_show_all = !empty($modSettings['enableAllMessages']) && $total_visible_posts > $context['messages_per_page'] && $total_visible_posts < $modSettings['enableAllMessages'];
		if (isset($this->_req->query->all) && !$can_show_all)
			unset($this->_req->query->all);
		// Otherwise, it must be allowed... so pretend start was -1.
		elseif (isset($this->_req->query->all))
			$this->_start = -1;

		// Construct the page index, allowing for the .START method...
		$context['page_index'] = constructPageIndex(getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => '%1$d', 'subject' => $topicinfo['subject']]), $this->_start, $total_visible_posts, $context['messages_per_page'], true, array('all' => $can_show_all, 'all_selected' => isset($this->_req->query->all)));
		$context['start'] = $this->_start;

		// This is information about which page is current, and which page we're on - in case you don't like
		// the constructed page index. (again, wireless..)
		$context['page_info'] = array(
			'current_page' => $this->_start / $context['messages_per_page'] + 1,
			'num_pages' => floor(($total_visible_posts - 1) / $context['messages_per_page']) + 1,
		);

		// Figure out all the link to the next/prev
		$context['links'] += array(
			'prev' => $this->_start >= $context['messages_per_page'] ? getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => $this->_start - $context['messages_per_page'], 'subject' => $topicinfo['subject']]) : '',
			'next' => $this->_start + $context['messages_per_page'] < $total_visible_posts ? getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => $this->_start + $context['messages_per_page'], 'subject' => $topicinfo['subject']]) : '',
		);

		// If they are viewing all the posts, show all the posts, otherwise limit the number.
		if ($can_show_all && isset($this->_req->query->all))
		{
			// No limit! (actually, there is a limit, but...)
			$context['messages_per_page'] = -1;

			// Set start back to 0...
			$this->_start = 0;
		}

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => '0', 'subject' => $topicinfo['subject']]),
			'name' => $topicinfo['subject'],
		);

		// Build a list of this board's moderators.
		$context['moderators'] = &$board_info['moderators'];
		$context['link_moderators'] = array();

		// Information about the current topic...
		$context['is_locked'] = $topicinfo['locked'];
		$context['is_sticky'] = $topicinfo['is_sticky'];
		$context['is_very_hot'] = $topicinfo['num_replies'] >= $modSettings['hotTopicVeryPosts'];
		$context['is_hot'] = $topicinfo['num_replies'] >= $modSettings['hotTopicPosts'];
		$context['is_approved'] = $topicinfo['approved'];

		determineTopicClass($context);

		// Set the topic's information for the template.
		$context['subject'] = $topicinfo['subject'];
		$context['num_views'] = $topicinfo['num_views'];
		$context['num_views_text'] = $context['num_views'] == 1 ? $txt['read_one_time'] : sprintf($txt['read_many_times'], $context['num_views']);
		$context['mark_unread_time'] = !empty($this->_virtual_msg) ? $this->_virtual_msg : $topicinfo['new_from'];

		// Set a canonical URL for this page.
		$context['canonical_url'] = getUrl('topic', ['topic' => $topicinfo['id_topic'], 'start' => $context['start'], 'subject' => $topicinfo['subject']]);

		// For quick reply we need a response prefix in the default forum language.
		$context['response_prefix'] = response_prefix();

		// Calculate the fastest way to get the messages!
		$ascending = true;
		$start = $this->_start;
		$limit = $context['messages_per_page'];
		$firstIndex = 0;
		if ($start >= $total_visible_posts / 2 && $context['messages_per_page'] != -1)
		{
			$ascending = !$ascending;
			$limit = $total_visible_posts <= $start + $limit ? $total_visible_posts - $start : $limit;
			$start = $total_visible_posts <= $start + $limit ? 0 : $total_visible_posts - $start - $limit;
			$firstIndex = $limit - 1;
		}

		// Taking care of member specific settings
		$limit_settings = array(
			'messages_per_page' => $context['messages_per_page'],
			'start' => $start,
			'offset' => $limit,
		);

		// Get each post and poster in this topic.
		$topic_details = getTopicsPostsAndPoster($topicinfo['id_topic'], $limit_settings, $ascending);
		$messages = $topic_details['messages'];
		$posters = array_unique($topic_details['all_posters']);
		$all_posters = $topic_details['all_posters'];
		unset($topic_details);

		call_integration_hook('integrate_display_message_list', array(&$messages, &$posters));

		// Guests can't mark topics read or for notifications, just can't sorry.
		if (!$user_info['is_guest'] && !empty($messages))
		{
			$boardseen = isset($this->_req->query->boardseen);

			$mark_at_msg = max($messages);
			if ($mark_at_msg >= $topicinfo['id_last_msg'])
				$mark_at_msg = $modSettings['maxMsgID'];
			if ($mark_at_msg >= $topicinfo['new_from'])
			{
				markTopicsRead(array($user_info['id'], $topicinfo['id_topic'], $mark_at_msg, $topicinfo['unwatched']), $topicinfo['new_from'] !== 0);
				$numNewTopics = getUnreadCountSince($board, empty($_SESSION['id_msg_last_visit']) ? 0 : $_SESSION['id_msg_last_visit']);

				if (empty($numNewTopics))
					$boardseen = true;
			}

			updateReadNotificationsFor($topicinfo['id_topic'], $board);

			// Mark board as seen if we came using last post link from BoardIndex. (or other places...)
			if ($boardseen)
			{
				require_once(SUBSDIR . '/Boards.subs.php');
				markBoardsRead($board, false, false);
			}
		}

		$attachments = array();

		// If there _are_ messages here... (probably an error otherwise :!)
		if (!empty($messages))
		{
			require_once(SUBSDIR . '/Attachments.subs.php');

			// Fetch attachments.
			if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
				$attachments = getAttachments($messages, $includeUnapproved, 'filter_accessible_attachment', $all_posters);

			$msg_parameters = array(
				'message_list' => $messages,
				'new_from' => $topicinfo['new_from'],
			);
			$msg_selects = array();
			$msg_tables = array();
			call_integration_hook('integrate_message_query', array(&$msg_selects, &$msg_tables, &$msg_parameters));

			// What?  It's not like it *couldn't* be only guests in this topic...
			if (!empty($posters))
			{
				\ElkArte\MembersList::load($posters);
			}

			// Load in the likes for this group of messages
			if (!empty($modSettings['likes_enabled']))
			{
				require_once(SUBSDIR . '/Likes.subs.php');
				$context['likes'] = loadLikes($messages, true);

				// ajax controller for likes
				loadJavascriptFile('like_posts.js', array('defer' => true));
				theme()->addJavascriptVar(array(
					'likemsg_are_you_sure' => JavaScriptEscape($txt['likemsg_are_you_sure']),
				));
				theme()->getTemplates()->loadLanguageFile('Errors');

				// Initiate likes and the tooltips for likes
				theme()->addInlineJavascript('
				$(function() {
					var likePostInstance = likePosts.prototype.init({
						oTxt: ({
							btnText : ' . JavaScriptEscape($txt['ok_uppercase']) . ',
							likeHeadingError : ' . JavaScriptEscape($txt['like_heading_error']) . ',
							error_occurred : ' . JavaScriptEscape($txt['error_occurred']) . '
						}),
					});

					$(".like_button, .unlike_button, .likes_button").SiteTooltip({
						hoverIntent: {
							sensitivity: 10,
							interval: 150,
							timeout: 50
						}
					});
				});', true);
			}

			$messages_request = loadMessageRequest($msg_selects, $msg_tables, $msg_parameters);

			// Go to the last message if the given time is beyond the time of the last message.
			if (isset($context['start_from']) && $context['start_from'] >= $topicinfo['num_replies'])
				$context['start_from'] = $topicinfo['num_replies'];

			// Since the anchor information is needed on the top of the page we load these variables beforehand.
			$context['first_message'] = isset($messages[$firstIndex]) ? $messages[$firstIndex] : $messages[0];
			$context['first_new_message'] = isset($context['start_from']) && $this->_start == $context['start_from'];
		}
		else
		{
			$messages_request = false;
			$context['first_message'] = 0;
			$context['first_new_message'] = false;
		}

		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => htmlspecialchars(strtr(strip_tags($board_info['name']), array('&amp;' => '&')), ENT_COMPAT, 'UTF-8'),
			'child_level' => $board_info['child_level'],
		);
		list ($sig_limits) = explode(':', $modSettings['signature_settings']);
		$signature_settings = explode(',', $sig_limits);

		$this->_icon_sources = new \ElkArte\MessageTopicIcons(!empty($modSettings['messageIconChecks_enable']), $settings['theme_dir']);
		if ($user_info['is_guest'])
		{
			$this->_show_signatures = !empty($signature_settings[8]) ? (int) $signature_settings[8] : 0;
		}
		else
		{
			$this->_show_signatures = !empty($signature_settings[9]) ? (int) $signature_settings[9] : 0;
		}

		// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
		// This will be called from the template.
		$bodyParser = new \ElkArte\MessagesCallback\BodyParser\Normal(array(), false);
		$opt = new \ElkArte\ValuesContainer([
			'icon_sources' => $this->_icon_sources,
			'show_signatures' => $this->_show_signatures,
		]);
		$renderer = new \ElkArte\MessagesCallback\DisplayRenderer($messages_request, $bodyParser, $opt);

		$context['get_message'] = array($renderer, 'getContext');

		// Now set all the wonderful, wonderful permissions... like moderation ones...
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
			$context[$contextual] = allowedTo($perm);

		// Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
		$anyown_permissions = array(
			'can_move' => 'move',
			'can_lock' => 'lock',
			'can_delete' => 'remove',
			'can_reply' => 'post_reply',
			'can_reply_unapproved' => 'post_unapproved_replies',
		);
		foreach ($anyown_permissions as $contextual => $perm)
			$context[$contextual] = allowedTo($perm . '_any') || ($context['user']['started'] && allowedTo($perm . '_own'));

		// Cleanup all the permissions with extra stuff...
		$context['can_mark_notify'] &= !$context['user']['is_guest'];
		$context['can_reply'] &= empty($topicinfo['locked']) || allowedTo('moderate_board');
		$context['can_reply_unapproved'] &= $modSettings['postmod_active'] && (empty($topicinfo['locked']) || allowedTo('moderate_board'));
		$context['can_issue_warning'] &= featureEnabled('w') && !empty($modSettings['warning_enable']);

		// Handle approval flags...
		$context['can_reply_approved'] = $context['can_reply'];

		// Guests do not have post_unapproved_replies_own permission, so it's always post_unapproved_replies_any
		if ($user_info['is_guest'] && allowedTo('post_unapproved_replies_any'))
		{
			$context['can_reply_approved'] = false;
		}

		$context['can_reply'] |= $context['can_reply_unapproved'];
		$context['can_quote'] = $context['can_reply'] && (empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC'])));
		$context['can_mark_unread'] = !$user_info['is_guest'] && $settings['show_mark_read'];
		$context['can_unwatch'] = !$user_info['is_guest'] && $modSettings['enable_unwatch'];
		$context['can_send_topic'] = (!$modSettings['postmod_active'] || $topicinfo['approved']) && allowedTo('send_topic');
		$context['can_print'] = empty($modSettings['disable_print_topic']);

		// Start this off for quick moderation - it will be or'd for each post.
		$context['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']);

		// Can restore topic?  That's if the topic is in the recycle board and has a previous restore state.
		$context['can_restore_topic'] &= !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board && !empty($topicinfo['id_previous_board']);
		$context['can_restore_msg'] &= !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board && !empty($topicinfo['id_previous_topic']);

		// Load up the Quick ModifyTopic and Quick Reply scripts
		loadJavascriptFile('topic.js');

		// Auto video embedding enabled?
		if (!empty($modSettings['enableVideoEmbeding']))
		{
			theme()->addInlineJavascript('
		$(function() {
			$().linkifyvideo(oEmbedtext);
		});');
		}

		// Now create the editor.
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
			'preview_type' => 0,
		);

		// Trigger the prepare_context event for modules that have tied in to it
		$this->_events->trigger('prepare_context', array('editorOptions' => &$editorOptions, 'use_quick_reply' => !empty($options['display_quick_reply'])));

		// Load up the "double post" sequencing magic.
		if (!empty($options['display_quick_reply']))
		{
			checkSubmitOnce('register');
			$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
			$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
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

		// Build the normal button array.
		$context['normal_buttons'] = array(
			'reply' => array(
				'test' => 'can_reply',
				'text' => 'reply',
				'image' => 'reply.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'post', 'topic' => $context['current_topic'] . '.' . $context['start'], 'last_msg' => $context['topic_last_message']]),
				'active' => true
			),
			'notify' => array(
				'test' => 'can_mark_notify',
				'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify',
				'image' => ($context['is_marked_notify'] ? 'un' : '') . 'notify.png',
				'lang' => true,
				'custom' => 'onclick="return notifyButton(this);"',
				'url' => getUrl('action', ['action' => 'notify', 'sa' => $context['is_marked_notify'] ? 'off' : 'on', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'mark_unread' => array(
				'test' => 'can_mark_unread',
				'text' => 'mark_unread',
				'image' => 'markunread.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'topic', 't' => $context['mark_unread_time'], 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'unwatch' => array(
				'test' => 'can_unwatch',
				'text' => ($context['topic_unwatched'] ? '' : 'un') . 'watch',
				'image' => ($context['topic_unwatched'] ? '' : 'un') . 'watched.png',
				'lang' => true,
				'custom' => 'onclick="return unwatchButton(this);"',
				'url' => getUrl('action', ['action' => 'unwatchtopic', 'sa' => $context['topic_unwatched'] ? 'off' : 'on', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'send' => array(
				'test' => 'can_send_topic',
				'text' => 'send_topic',
				'image' => 'sendtopic.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'emailuser', 'sa' => 'sendtopic', 'topic' => $context['current_topic'] . '.0']),
				'custom' => 'onclick="return sendtopicOverlayDiv(this.href, \'' . $txt['send_topic'] . '\');"'
			),
			'print' => array(
				'test' => 'can_print',
				'text' => 'print',
				'image' => 'print.png',
				'lang' => true,
				'custom' => 'rel="nofollow"',
				'class' => 'new_win',
				'url' => getUrl('action', ['action' => 'topic', 'sa' => 'printpage', 'topic' => $context['current_topic'] . '.0'])
			),
		);

		// Build the mod button array
		$context['mod_buttons'] = array(
			'move' => array(
				'test' => 'can_move',
				'text' => 'move_topic',
				'image' => 'admin_move.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'movetopic', 'current_board' => $context['current_board'], 'topic' => $context['current_topic'] . '.0'])
			),
			'delete' => array(
				'test' => 'can_delete',
				'text' => 'remove_topic',
				'image' => 'admin_rem.png',
				'lang' => true,
				'custom' => 'onclick="return confirm(\'' . $txt['are_sure_remove_topic'] . '\');"',
				'url' => getUrl('action', ['action' => 'removetopic2', 'topic' => $context['current_topic'] . '.0', '{session_data}'])
			),
			'lock' => array(
				'test' => 'can_lock',
				'text' => empty($context['is_locked']) ? 'set_lock' : 'set_unlock',
				'image' => 'admin_lock.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'topic', 'sa' => 'lock', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'sticky' => array(
				'test' => 'can_sticky',
				'text' => empty($context['is_sticky']) ? 'set_sticky' : 'set_nonsticky',
				'image' => 'admin_sticky.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'topic', 'sa' => 'sticky', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
			),
			'merge' => array(
				'test' => 'can_merge',
				'text' => 'merge',
				'image' => 'merge.png',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'mergetopics', 'board' => $context['current_board'] . '.0', 'from' => $context['current_topic']])
			),
		);

		// Restore topic. eh?  No monkey business.
		if ($context['can_restore_topic'])
		{
			$context['mod_buttons'][] = array(
				'text' => 'restore_topic',
				'image' => '',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'restoretopic', 'topics' => $context['current_topic'], '{session_data}'])
			);
		}

		// Quick reply & modify enabled?
		if ($context['can_reply'] && !empty($options['display_quick_reply']))
			$this->_template_layers->add('quickreply');

		$this->_template_layers->add('pages_and_buttons');

		// Allow adding new buttons easily.
		call_integration_hook('integrate_display_buttons');
		call_integration_hook('integrate_mod_buttons');
	}

	/**
	 * In-topic quick moderation.
	 *
	 * Accessed by ?action=quickmod2
	 */
	public function action_quickmod2()
	{
		global $topic, $board, $user_info, $context, $modSettings;

		// Check the session = get or post.
		checkSession('request');

		require_once(SUBSDIR . '/Messages.subs.php');

		if (empty($this->_req->post->msgs))
			redirectexit('topic=' . $topic . '.' . $this->_req->getQuery('start', 'intval'));

		$messages = array_map('intval', $this->_req->post->msgs);

		// We are restoring messages. We handle this in another place.
		if (isset($this->_req->query->restore_selected))
			redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_var'] . '=' . $context['session_id']);

		if (isset($this->_req->query->split_selection))
		{
			$mgsOptions = basicMessageInfo(min($messages), true);

			$_SESSION['split_selection'][$topic] = $messages;
			redirectexit('action=splittopics;sa=selectTopics;topic=' . $topic . '.0;subname_enc=' . urlencode($mgsOptions['subject']) . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		require_once(SUBSDIR . '/Topic.subs.php');
		$topic_info = getTopicInfo($topic);

		// Allowed to delete any message?
		if (allowedTo('delete_any'))
			$allowed_all = true;
		// Allowed to delete replies to their messages?
		elseif (allowedTo('delete_replies'))
			$allowed_all = $topic_info['id_member_started'] == $user_info['id'];
		else
			$allowed_all = false;

		// Make sure they're allowed to delete their own messages, if not any.
		if (!$allowed_all)
			isAllowedTo('delete_own');

		// Allowed to remove which messages?
		$messages = determineRemovableMessages($topic, $messages, $allowed_all);

		// Get the first message in the topic - because you can't delete that!
		$first_message = $topic_info['id_first_msg'];
		$last_message = $topic_info['id_last_msg'];
		$remover = new \ElkArte\MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);

		// Delete all the messages we know they can delete. ($messages)
		foreach ($messages as $message => $info)
		{
			// Just skip the first message - if it's not the last.
			if ($message == $first_message && $message != $last_message)
				continue;
			// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
			elseif ($message == $first_message)
				$topicGone = true;

			$remover->removeMessage($message);
		}

		redirectexit(!empty($topicGone) ? 'board=' . $board : 'topic=' . $topic . '.' . (int) $this->_req->query->start);
	}
}
