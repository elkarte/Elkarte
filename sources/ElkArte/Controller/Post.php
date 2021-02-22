<?php

/**
 * The job of this file is to handle everything related to posting replies,
 * new topics, quotes, and modifications to existing posts.  It also handles
 * quoting posts by way of javascript.
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

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\DataValidator;
use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\ControllerRedirectException;
use ElkArte\Exceptions\Exception;
use ElkArte\Notifications;
use ElkArte\NotificationsTask;
use ElkArte\User;
use ElkArte\Util;

/**
 * Everything related to posting new replies and topics and modifications of them
 */
class Post extends AbstractController
{
	/** @var null|ErrorContext The post (messages) errors object */
	protected $_post_errors = null;

	/** @var null|\ElkArte\Themes\TemplateLayers The template layers object */
	protected $_template_layers = null;

	/** @var array An array of attributes of the topic (if not new) */
	protected $_topic_attributes = array();

	/** @var string The message subject */
	protected $_form_subject = '';

	/** @var string The message */
	protected $_form_message = '';

	/** @var \BBC\PreparseCode */
	protected $preparse;

	/**
	 * Sets up common stuff for all or most of the actions.
	 */
	public function pre_dispatch()
	{
		$this->_post_errors = ErrorContext::context('post', 1);
		$this->_template_layers = theme()->getLayers();

		$this->preparse = PreparseCode::instance($this->user->name);

		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');
	}

	/**
	 * Dispatch to the right action method for the request.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// Figure out the right action to do.
		// hint: I'm post controller. :P
		$this->action_post();
	}

	/**
	 * Handles showing the post screen, loading the post to be modified, loading any post quoted, previews,
	 * display of errors and polls.
	 *
	 * What it does:
	 *
	 * - Validates that we're posting in a board.
	 * - Find the topic id if a message id is passed, else assume it's a new message
	 * - Get the response prefix in the default forum language.
	 * - Triggers events associated with posting.
	 *    - prepare_post, prepare_context, prepare_modifying, prepare_editing,
	 *    - prepare_posting, post_errors, finalize_post_form
	 * - Additionally handles previews of posts.
	 * - Requires different permissions depending on the actions, but most notably post_new, post_reply_own, and post_reply_any.
	 * - Shows options for the editing and posting of calendar events and attachments, and as the posting of polls (using modules).
	 * - Accessed from ?action=post or called from action_post2 in the event of errors or preview from quick reply.
	 *
	 * @uses the Post template and language file, main sub template.
	 * @uses Errors language
	 */
	public function action_post()
	{
		global $context;

		// Initilaize the post area
		$this->_beforePreparePost();

		// Trigger the prepare_post event
		$this->_events->trigger('prepare_post', array('topic_attributes' => &$this->_topic_attributes));

		// Sets post form options / checkbox / etc
		$this->_beforePrepareContext();

		// Trigger the prepare_context event
		try
		{
			$this->_events->trigger('prepare_context', array('id_member_poster' => $this->_topic_attributes['id_member']));
		}
		catch (ControllerRedirectException $e)
		{
			return $e->doRedirect($this);
		}

		// Load up the message details if this is an existing msg
		$this->_generatingMessage();

		// Trigger post_errors event
		$this->_events->trigger('post_errors');

		$this->_preparingPage();

		// Needed for the editor and message icons.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Now create the editor.
		$editorOptions = array(
			'id' => 'message',
			'value' => $context['message'],
			'labels' => array(
				'post_button' => $context['submit_label'],
			),
			// add height and width for the editor
			'height' => '275px',
			'width' => '100%',
			// We do XML preview here.
			'preview_type' => 2
		);

		// Trigger the finalize_post_form event
		$this->_events->trigger('finalize_post_form', array('destination' => &$context['destination'], 'page_title' => &$context['page_title'], 'show_additional_options' => &$context['show_additional_options'], 'editorOptions' => &$editorOptions));

		// Initialize the editor
		create_control_richedit($editorOptions);

		$this->_finalizePage();
	}

	/**
	 * Load language files, templates and prepare posting basics
	 */
	protected function _beforePreparePost()
	{
		global $context;

		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Post');
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Errors');

		$context['robot_no_index'] = true;
		$this->_template_layers->add('postarea');
		$this->_topic_attributes = array(
			'locked' => false,
			'notify' => false,
			'is_sticky' => false,
			'id_last_msg' => 0,
			'id_member' => 0,
			'id_first_msg' => 0,
			'subject' => '',
			'last_post_time' => 0
		);
	}

	/**
	 * Does some basic checking and if everything is valid will
	 * load $context with needed post form options
	 *
	 * - Ensures we have a topic id
	 * - Checks if a topic is locked
	 * - Determines if this msg will be pre approved or member requires approval
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _beforePrepareContext()
	{
		global $topic, $modSettings, $board, $context;

		// You must be posting to *some* board.
		if (empty($board) && !$context['make_event'])
		{
			throw new Exception('no_board', false);
		}

		// All those wonderful modifiers and attachments
		$this->_template_layers->add('additional_options', 200);

		if (isset($this->_req->query->xml))
		{
			$context['sub_template'] = 'post';

			// Just in case of an earlier error...
			$context['preview_message'] = '';
			$context['preview_subject'] = '';
		}

		// No message is complete without a topic.
		if (empty($topic) && !empty($_REQUEST['msg']))
		{
			$topic = associatedTopic((int) $_REQUEST['msg']);
			if (empty($topic))
			{
				$this->_req->clearValue('msg', 'both');
			}
		}

		// Check if it's locked. It isn't locked if no topic is specified.
		if (!empty($topic))
		{
			$this->_topic_attributes = topicUserAttributes($topic, $this->user->id);
			$context['notify'] = $this->_topic_attributes['notify'];
			$context['topic_last_message'] = $this->_topic_attributes['id_last_msg'];
			$msg = $this->_req->getRequest('msg', 'intval', 0);

			if (empty($msg))
			{
				if ($this->user->is_guest && !allowedTo('post_reply_any')
					&& (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_replies_any')))
				{
					is_not_guest();
				}

				// By default the reply will be approved...
				$context['becomes_approved'] = true;
				if ($this->_topic_attributes['id_member'] != $this->user->id)
				{
					if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any')
						&& !allowedTo('post_reply_any'))
					{
						$context['becomes_approved'] = false;
					}
					else
					{
						isAllowedTo('post_reply_any');
					}
				}
				elseif (!allowedTo('post_reply_any'))
				{
					if ($modSettings['postmod_active'])
					{
						if (allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
						{
							$context['becomes_approved'] = false;
						}
						// Guests do not have post_unapproved_replies_own permission, so it's always post_unapproved_replies_any
						elseif ($this->user->is_guest && allowedTo('post_unapproved_replies_any'))
						{
							$context['becomes_approved'] = false;
						}
						else
						{
							isAllowedTo('post_reply_own');
						}
					}
					else
					{
						isAllowedTo('post_reply_own');
					}
				}
			}
			else
			{
				$context['becomes_approved'] = true;
			}

			$context['can_lock'] = allowedTo('lock_any') || ($this->user->id == $this->_topic_attributes['id_member'] && allowedTo('lock_own'));
			$context['can_sticky'] = allowedTo('make_sticky');
			$context['notify'] = !empty($context['notify']);
			$context['sticky'] = isset($_REQUEST['sticky']) ? !empty($_REQUEST['sticky']) : $this->_topic_attributes['is_sticky'];
		}
		else
		{
			$this->_topic_attributes['id_member'] = 0;
			$context['becomes_approved'] = true;
			if (empty($context['make_event']) || !empty($board))
			{
				if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
				{
					$context['becomes_approved'] = false;
				}
				else
				{
					isAllowedTo('post_new');
				}
			}

			$this->_topic_attributes['locked'] = 0;

			// @todo These won't work if you're making an event.
			$context['can_lock'] = allowedTo(array('lock_any', 'lock_own'));
			$context['can_sticky'] = allowedTo('make_sticky');

			$context['notify'] = !empty($context['notify']);
			$context['sticky'] = !empty($_REQUEST['sticky']);
		}

		// @todo These won't work if you're posting an event!
		$context['can_notify'] = allowedTo('mark_any_notify');
		$context['can_move'] = allowedTo('move_any');
		$context['move'] = !empty($_REQUEST['move']);
		$context['announce'] = !empty($_REQUEST['announce']);
		$context['id_draft'] = $this->_req->getPost('id_draft', 'intval', 0);

		// You can only announce topics that will get approved...
		$context['can_announce'] = allowedTo('announce_topic') && $context['becomes_approved'];
		$context['locked'] = !empty($this->_topic_attributes['locked']) || !empty($_REQUEST['lock']);
		$context['can_quote'] = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		// Generally don't show the approval box... (Assume we want things approved)
		$context['show_approval'] = allowedTo('approve_posts') && $context['becomes_approved'] ? 2 : (allowedTo('approve_posts') ? 1 : 0);

		// Don't allow a post if it's locked and you aren't all powerful.
		if ($this->_topic_attributes['locked'] && !allowedTo('moderate_board'))
		{
			throw new Exception('topic_locked', false);
		}
	}

	/**
	 * Get the message setup for ...
	 *
	 * - Sets up the form for preview / modify / new message status.  Items
	 * such as icons, text, etc
	 * - Look if a new topic was posted while working on this prose
	 * - Shows the message preview if requested
	 * - triggers prepare_modifying, prepare_editing, prepare_posting
	 */
	protected function _generatingMessage()
	{
		global $txt, $topic, $modSettings, $context, $options;

		// Convert / Clean the input elements
		$msg = $this->_req->getRequest('msg', 'intval', null);
		$last_msg = $this->_req->getRequest('last_msg', 'intval', null);
		$message = $this->_req->getPost('message', 'trim', null);
		$subject = $this->_req->getPost('subject', 'trim', '');

		// See if any new replies have come along.
		if (empty($msg) && !empty($topic))
		{
			if (empty($options['no_new_reply_warning']) && isset($last_msg) && $context['topic_last_message'] > $last_msg)
			{
				$context['new_replies'] = countMessagesSince($topic, (int) $_REQUEST['last_msg'], false, $modSettings['postmod_active'] && !allowedTo('approve_posts'));

				if (!empty($context['new_replies']))
				{
					if ($context['new_replies'] == 1)
					{
						$txt['error_new_replies'] = isset($_GET['last_msg']) ? $txt['error_new_reply_reading'] : $txt['error_new_reply'];
					}
					else
					{
						$txt['error_new_replies'] = sprintf(isset($_GET['last_msg']) ? $txt['error_new_replies_reading'] : $txt['error_new_replies'], $context['new_replies']);
					}

					$this->_post_errors->addError('new_replies', 0);

					$modSettings['topicSummaryPosts'] = $context['new_replies'] > $modSettings['topicSummaryPosts'] ? max($modSettings['topicSummaryPosts'], 5) : $modSettings['topicSummaryPosts'];
				}
			}
		}

		// Get a response prefix (like 'Re:') in the default forum language.
		$context['response_prefix'] = response_prefix();
		$context['destination'] = 'post2;start=' . $this->_req->getRequest('start', 'intval', 0);

		// Previewing, modifying, or posting?
		// Do we have a body, but an error happened.
		if (isset($message) || $this->_post_errors->hasErrors())
		{
			$this->_previewPost($msg, $topic, $message, $subject);
		}
		// Editing a message...
		elseif (isset($msg) && !empty($topic))
		{
			$this->_editPost($msg, $topic);
		}
		// Posting...
		else
		{
			$this->_makePost($topic, $subject);
		}

		// Check whether this is a really old post being bumped...
		if (!empty($topic) && !empty($modSettings['oldTopicDays']) && $this->_topic_attributes['last_post_time'] + $modSettings['oldTopicDays'] * 86400 < time() && empty($this->_topic_attributes['is_sticky']) && !isset($_REQUEST['subject']))
		{
			$this->_post_errors->addError(array('old_topic', array($modSettings['oldTopicDays'])), 0);
		}
	}

	/**
	 * Preview a post,
	 * - From pressing preview
	 * - When errors are generated when trying to post.
	 *
	 * @param int $msg
	 * @param int $topic
	 * @param string $message
	 * @param string $subject
	 */
	private function _previewPost($msg, $topic, $message, $subject)
	{
		global $txt, $modSettings, $context;

		$xml = $this->_req->getRequest('xml', 'isset', false);
		$preview = $this->_req->getPost('preview', 'isset', false);
		$ns = $this->_req->getPost('ns', 'isset', false);
		$notify = $this->_req->getPost('notify', 'isset', false);
		$quote = $this->_req->getRequest('quote', 'intval', 0);
		$followup = $this->_req->getPost('followup', 'intval', 0);
		$not_approved = $this->_req->getPost('not_approved', 'empty', true);
		$last_msg = $this->_req->getRequest('last_msg', 'intval', null);
		$icon = $this->_req->getPost('icon', 'trim', 'xx');
		$msg_id = 0;

		// Validate inputs.
		if (!$this->_post_errors->hasErrors())
		{
			// This means they didn't click Post and get an error.
			$really_previewing = true;
		}
		else
		{
			if (!isset($message))
			{
				$message = '';
			}

			// They are previewing if they asked to preview (i.e. came from quick reply).
			$really_previewing = !empty($preview) || isset($xml);
		}

		// Trigger the prepare_modifying event
		$this->_events->trigger('prepare_modifying', [
				'post_errors' => $this->_post_errors,
				'really_previewing' => &$really_previewing]
		);

		// In order to keep the approval status flowing through, we have to pass it through the form...
		$context['becomes_approved'] = $not_approved;
		$context['show_approval'] = isset($this->_req->post->approve) ? ($this->_req->post->approve ? 2 : 1) : 0;
		$context['can_announce'] &= $context['becomes_approved'];

		// Set up the inputs for the form.
		$this->_form_subject = strtr(Util::htmlspecialchars($subject), array("\r" => '', "\n" => '', "\t" => ''));
		$this->_form_message = Util::htmlspecialchars($message, ENT_QUOTES, 'UTF-8', true);

		// Make sure the subject isn't too long - taking into account special characters.
		if (Util::strlen($this->_form_subject) > 100)
		{
			$this->_form_subject = Util::substr($this->_form_subject, 0, 100);
		}

		// Are you... a guest?
		if ($this->user->is_guest)
		{
			$context['name'] = $this->_req->getPost('guestname', 'Util::htmlspecialchars', '');
			$context['email'] = $this->_req->getPost('email', 'Util::htmlspecialchars', '');
			$this->user->name = $context['name'];
		}

		// Only show the preview stuff if they hit Preview.
		if ($really_previewing)
		{
			$this->_setupPreviewContext($ns, $xml);
		}

		// Set up the checkboxes.
		$context['notify'] = $notify;
		$context['use_smileys'] = $ns;
		$context['icon'] = preg_replace('~[\./\\\\*\':"<>]~', '', $icon);

		// Set the destination action for submission.
		$context['destination'] .= isset($msg) ? ';msg=' . $msg . ';' . $context['session_var'] . '=' . $context['session_id'] : '';
		$context['submit_label'] = isset($msg) ? $txt['save'] : $txt['post'];

		// Previewing an edit? Modifying an existing message?
		if (isset($msg) && !empty($topic))
		{
			// Get the existing message.
			$message = messageDetails($msg, $topic);

			// The message they were trying to edit was most likely deleted.
			if ($message === false)
			{
				throw new Exception('no_message', false);
			}

			$errors = checkMessagePermissions($message['message']);
			if (!empty($errors))
			{
				foreach ($errors as $error)
				{
					$this->_post_errors->addError($error);
				}
			}

			prepareMessageContext($message);
		}
		elseif (isset($last_msg))
		{
			// @todo: sort out what kind of combinations are actually possible
			// Posting a quoted reply?
			if ((!empty($topic) && !empty($quote))
				|| (!empty($modSettings['enableFollowup']) && !empty($followup)))
			{
				$msg_id = !empty($quote) ? $quote : $followup;
				$case = 2;
			}
			// Posting a reply without a quote?
			elseif (!empty($topic) && empty($quote))
			{
				$this->_topic_attributes['subject'] = $subject;
				$case = 3;
			}
			else
			{
				$case = 4;
			}

			list ($this->_form_subject,) = getFormMsgSubject($case, $topic, $this->_topic_attributes['subject'], $msg_id);
		}

		// No check is needed, since nothing is really posted.
		checkSubmitOnce('free');
	}

	/**
	 * Going back to a message to make changes, like damn I should watch what
	 * my fingers are typing.
	 *
	 * @param int $msg
	 * @param int $topic
	 */
	private function _editPost($msg, $topic)
	{
		global $txt, $context;

		$message = getFormMsgSubject(1, $topic, '', $msg);

		// The message they were trying to edit was most likely deleted.
		if ($message === false)
		{
			throw new Exception('no_message', false);
		}

		// Trigger the prepare_editing event
		$this->_events->trigger('prepare_editing', array('topic' => $topic, 'message' => &$message));

		if (!empty($message['errors']))
		{
			foreach ($message['errors'] as $error)
			{
				$this->_post_errors->addError($error);
			}
		}

		// Get the stuff ready for the form.
		$this->_form_subject = censor($message['message']['subject']);
		$this->_form_message = censor($this->preparse->un_preparsecode($message['message']['body']));

		// Check the boxes that should be checked.
		$context['use_smileys'] = !empty($message['message']['smileys_enabled']);
		$context['icon'] = $message['message']['icon'];

		// Set the destination.
		$context['destination'] .= ';msg=' . $msg . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['submit_label'] = $txt['save'];

	}

	/**
	 * Think you are done and ready to make a post
	 *
	 * @param int $topic
	 * @param string $subject
	 */
	private function _makePost($topic, $subject)
	{
		global $context, $txt, $modSettings;

		$quote = $this->_req->getRequest('quote', 'intval', 0);
		$followup = $this->_req->getPost('followup', 'intval', 0);

		// By default....
		$context['use_smileys'] = true;
		$context['icon'] = 'xx';
		$msg_id = 0;

		if ($this->user->is_guest)
		{
			$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
			$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
		}

		// Trigger the prepare_posting event
		$this->_events->trigger('prepare_posting');

		$context['submit_label'] = $txt['post'];

		// @todo: sort out what kind of combinations are actually possible
		// Posting a quoted reply?
		if ((!empty($topic) && $quote !== 0)
			|| (!empty($modSettings['enableFollowup']) && $followup !== 0))
		{
			$case = 2;
			$msg_id = !empty($quote) ? $quote : $followup;
		}
		// Posting a reply without a quote?
		elseif (!empty($topic) && $quote === 0)
		{
			$case = 3;
		}
		else
		{
			$this->_topic_attributes['subject'] = $subject;
			$case = 4;
		}

		list ($this->_form_subject, $this->_form_message) = getFormMsgSubject($case, $topic, $this->_topic_attributes['subject'], $msg_id);
	}

	/**
	 * Loads up the context global with a preview of the post
	 *
	 * @param bool $ns no smiley flag
	 * @param bool $xml xml request
	 */
	private function _setupPreviewContext($ns, $xml)
	{
		global $txt, $modSettings, $context;

			// Set up the preview message and subject
			$context['preview_message'] = $this->_form_message;
			$this->preparse->preparsecode($this->_form_message, true);

			// Do all bulletin board code thing on the message
			$bbc_parser = ParserWrapper::instance();
			$this->preparse->preparsecode($context['preview_message']);
			$context['preview_message'] = $bbc_parser->parseMessage($context['preview_message'], $ns);
			$context['preview_message'] = censor($context['preview_message']);

			// Don't forget the subject
			$context['preview_subject'] = censor($this->_form_subject);

			// Any errors we should tell them about?
			if ($this->_form_subject === '')
			{
				$this->_post_errors->addError('no_subject');
				$context['preview_subject'] = '<em>' . $txt['no_subject'] . '</em>';
			}

			if ($context['preview_message'] === '')
			{
				$this->_post_errors->addError('no_message');
			}
			elseif (!empty($modSettings['max_messageLength']) && Util::strlen($this->_form_message) > $modSettings['max_messageLength'])
			{
				$this->_post_errors->addError(array('long_message', array($modSettings['max_messageLength'])));
			}

			// Protect any CDATA blocks.
			if ($xml === true)
			{
				$context['preview_message'] = strtr($context['preview_message'], array(']]>' => ']]]]><![CDATA[>'));
			}
	}

	protected function _preparingPage()
	{
		global $txt, $topic, $modSettings, $board, $context;

		// Any errors occurred?
		$context['post_error'] = array(
			'errors' => $this->_post_errors->prepareErrors(),
			'type' => $this->_post_errors->getErrorType() == 0 ? 'minor' : 'serious',
			'title' => $this->_post_errors->getErrorType() == 0 ? $txt['warning_while_submitting'] : $txt['error_while_submitting'],
		);

		// What are you doing? Posting, modifying, previewing, new post, or reply...
		if (empty($context['page_title']))
		{
			if (isset($_REQUEST['msg']))
			{
				$context['page_title'] = $txt['modify_msg'];
			}
			elseif (isset($_REQUEST['subject'], $context['preview_subject']))
			{
				$context['page_title'] = $txt['post_reply'];
			}
			elseif (empty($topic))
			{
				$context['page_title'] = $txt['start_new_topic'];
			}
			else
			{
				$context['page_title'] = $txt['post_reply'];
			}
		}

		// Update the topic summary, needed to show new posts in a preview
		if (!empty($topic) && !empty($modSettings['topicSummaryPosts']))
		{
			$only_approved = $modSettings['postmod_active'] && !allowedTo('approve_posts');

			if (isset($_REQUEST['xml']))
			{
				$limit = empty($context['new_replies']) ? 0 : (int) $context['new_replies'];
			}
			else
			{
				$limit = $modSettings['topicSummaryPosts'];
			}

			$before = isset($_REQUEST['msg']) ? array('before' => (int) $_REQUEST['msg']) : array();

			$counter = 0;
			$context['previous_posts'] = empty($limit) ? array() : selectMessages($topic, 0, $limit, $before, $only_approved);
			foreach ($context['previous_posts'] as &$post)
			{
				$post['is_new'] = !empty($context['new_replies']);
				$post['counter'] = $counter++;
				$post['is_ignored'] = !empty($modSettings['enable_buddylist']) && in_array($post['id_poster'], $this->user->ignoreusers);

				if (!empty($context['new_replies']))
				{
					$context['new_replies']--;
				}
			}
		}

		// Just ajax previewing then lets stop now
		if (isset($_REQUEST['xml']))
		{
			obExit();
		}

		$context['subject'] = addcslashes($this->_form_subject, '"');
		$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $this->_form_message);

		require_once(SUBSDIR . '/MessageIcons.subs.php');
		// Message icons - customized or not, retrieve them...
		$context['icons'] = getMessageIcons($board);

		$context['icon_url'] = '';

		if (!empty($context['icons']))
		{
			$context['icons'][count($context['icons']) - 1]['is_last'] = true;
			$context['icons'][0]['selected'] = true;

			// $context['icon'] is set when editing a message
			if (!isset($context['icon']))
			{
				$context['icon'] = $context['icons'][0]['value'];
			}
			$found = false;
			foreach ($context['icons'] as $icon)
			{
				if ($icon['value'] === $context['icon'])
				{
					$found = true;
					$context['icon_url'] = $icon['url'];
					break;
				}
			}
			// Fail safe
			if (!$found)
			{
				$context['icon'] = $context['icons'][0]['value'];
				$context['icon_url'] = $context['icons'][0]['url'];
			}
		}

		$context['show_additional_options'] = !empty($_POST['additional_options']) || isset($_GET['additionalOptions']);
	}

	protected function _finalizePage()
	{
		global $txt, $scripturl, $topic, $context;

		// Build the link tree.
		if (empty($topic))
		{
			$context['linktree'][] = array(
				'name' => '<em>' . $txt['start_new_topic'] . '</em>'
			);
		}
		else
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'],
				'name' => $this->_form_subject,
				'extra_before' => '<span><strong class="nav">' . $context['page_title'] . ' ( </strong></span>',
				'extra_after' => '<span><strong class="nav"> )</strong></span>'
			);
		}

		$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
		$context['is_new_topic'] = empty($topic);
		$context['is_new_post'] = !isset($_REQUEST['msg']);
		$context['is_first_post'] = $context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == $this->_topic_attributes['id_first_msg']);
		$context['current_action'] = 'post';

		// Register this form in the session variables.
		checkSubmitOnce('register');

		// Finally, load the template.
		if (!isset($_REQUEST['xml']))
		{
			theme()->getTemplates()->load('Post');
			$context['sub_template'] = 'post_page';
		}
	}

	/**
	 * Posts or saves the message composed with Post().
	 *
	 * What it does:
	 *
	 * - Requires various permissions depending on the action.
	 * - Handles attachment, post, and calendar saving.
	 * - Sends off notifications, and allows for announcements and moderation.
	 * accessed from ?action=post2.
	 * - Triggers events associated with the actual posting
	 *   - prepare_save_post, save_replying, save_new_topic, save_modify
	 *   - before_save_post, pre_save_post, after_save_post
	 */
	public function action_post2()
	{
		global $board, $topic, $txt, $modSettings, $context;
		global $board_info, $options;

		// Sneaking off, are we?
		if (empty($_POST) && empty($topic))
		{
			if (empty($_SERVER['CONTENT_LENGTH']))
			{
				redirectexit('action=post;board=' . $board . '.0');
			}
			else
			{
				throw new Exception('post_upload_error', false);
			}
		}
		elseif (empty($_POST) && !empty($topic))
		{
			redirectexit('action=post;topic=' . $topic . '.0');
		}

		// No need!
		$context['robot_no_index'] = true;

		// We are now in post2 action
		$context['current_action'] = 'post2';

		// If the session has timed out, let the user re-submit their form.
		if (checkSession('post', '', false) != '')
		{
			$this->_post_errors->addError('session_timeout');

			// Disable the preview so that any potentially malicious code is not executed
			$_REQUEST['preview'] = false;

			return $this->action_post();
		}

		$topic_info = array();

		// Previewing? Go back to start.
		if (isset($_REQUEST['preview']))
		{
			return $this->action_post();
		}

		require_once(SUBSDIR . '/Boards.subs.php');
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Post');

		// Trigger the prepare_save_post event
		$this->_events->trigger('prepare_save_post', array('topic_info' => &$topic_info));

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// If this isn't a new topic load the topic info that we need.
		if (!empty($topic))
		{
			$topic_info = getTopicInfo($topic);

			// Though the topic should be there, it might have vanished.
			if (empty($topic_info))
			{
				throw new Exception('topic_doesnt_exist');
			}

			// Did this topic suddenly move? Just checking...
			if ($topic_info['id_board'] != $board)
			{
				throw new Exception('not_a_topic');
			}
		}

		// Replying to a topic?
		if (!empty($topic) && !isset($_REQUEST['msg']))
		{
			// Don't allow a post if it's locked.
			if ($topic_info['locked'] != 0 && !allowedTo('moderate_board'))
			{
				throw new Exception('topic_locked', false);
			}

			// Do the permissions and approval stuff...
			$becomesApproved = true;
			if ($topic_info['id_member_started'] != $this->user->id)
			{
				if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
				{
					$becomesApproved = false;
				}
				else
				{
					isAllowedTo('post_reply_any');
				}
			}
			elseif (!allowedTo('post_reply_any'))
			{
				if ($modSettings['postmod_active'])
				{
					if (allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
					{
						$becomesApproved = false;
					}
					// Guests do not have post_unapproved_replies_own permission, so it's always post_unapproved_replies_any
					elseif ($this->user->is_guest && allowedTo('post_unapproved_replies_any'))
					{
						$becomesApproved = false;
					}
					else
					{
						isAllowedTo('post_reply_own');
					}
				}
			}

			if (isset($_POST['lock']))
			{
				$_POST['lock'] = $this->_checkLocked($_POST['lock'], $topic_info);
			}

			// So you wanna (un)sticky this...let's see.
			if (isset($_POST['sticky']) && ($_POST['sticky'] == $topic_info['is_sticky'] || !allowedTo('make_sticky')))
			{
				unset($_POST['sticky']);
			}

			// Trigger the save_replying event
			$this->_events->trigger('save_replying', array('topic_info' => &$topic_info));

			// If the number of replies has changed, if the setting is enabled, go back to action_post() - which handles the error.
			if (empty($options['no_new_reply_warning']) && isset($_POST['last_msg']) && $topic_info['id_last_msg'] > $_POST['last_msg'])
			{
				theme()->addInlineJavascript('
					$(function() {
						$("html,body").scrollTop($(\'.category_header:visible:first\').offset().top);
					});'
				);

				return $this->action_post();
			}

			$posterIsGuest = $this->user->is_guest;
		}
		// Posting a new topic.
		elseif (empty($topic))
		{
			// Now don't be silly, new topics will get their own id_msg soon enough.
			unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);

			// Do like, the permissions, for safety and stuff...
			$becomesApproved = true;
			if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
			{
				$becomesApproved = false;
			}
			else
			{
				isAllowedTo('post_new');
			}

			// Trigger teh save new topic event
			$this->_events->trigger('save_new_topic', array('becomesApproved' => &$becomesApproved));

			if (isset($_POST['lock']))
			{
				$_POST['lock'] = $this->_checkLocked($_POST['lock']);
			}

			if (isset($_POST['sticky']) && (empty($_POST['sticky']) || !allowedTo('make_sticky')))
			{
				unset($_POST['sticky']);
			}

			$posterIsGuest = $this->user->is_guest;
		}
		// Modifying an existing message?
		elseif (isset($_REQUEST['msg']) && !empty($topic))
		{
			$_REQUEST['msg'] = (int) $_REQUEST['msg'];

			$msgInfo = basicMessageInfo($_REQUEST['msg'], true);

			if (empty($msgInfo))
			{
				throw new Exception('cant_find_messages', false);
			}

			// Trigger teh save_modify event
			$this->_events->trigger('save_modify', array('msgInfo' => &$msgInfo));

			if (!empty($topic_info['locked']) && !allowedTo('moderate_board'))
			{
				throw new Exception('topic_locked', false);
			}

			if (isset($_POST['lock']))
			{
				$_POST['lock'] = $this->_checkLocked($_POST['lock'], $topic_info);
			}

			// Change the sticky status of this topic?
			if (isset($_POST['sticky']) && (!allowedTo('make_sticky') || $_POST['sticky'] == $topic_info['is_sticky']))
			{
				unset($_POST['sticky']);
			}

			if ($msgInfo['id_member'] == $this->user->id && !allowedTo('modify_any'))
			{
				if ((!$modSettings['postmod_active'] || $msgInfo['approved']) && !empty($modSettings['edit_disable_time']) && $msgInfo['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				{
					throw new Exception('modify_post_time_passed', false);
				}
				elseif ($topic_info['id_member_started'] == $this->user->id && !allowedTo('modify_own'))
				{
					isAllowedTo('modify_replies');
				}
				else
				{
					isAllowedTo('modify_own');
				}
			}
			elseif ($topic_info['id_member_started'] == $this->user->id && !allowedTo('modify_any'))
			{
				isAllowedTo('modify_replies');

				// If you're modifying a reply, I say it better be logged...
				$moderationAction = true;
			}
			else
			{
				isAllowedTo('modify_any');

				// Log it, assuming you're not modifying your own post.
				if ($msgInfo['id_member'] != $this->user->id)
				{
					$moderationAction = true;
				}
			}

			$posterIsGuest = empty($msgInfo['id_member']);

			// Can they approve it?
			$can_approve = allowedTo('approve_posts');
			$becomesApproved = $modSettings['postmod_active'] ? ($can_approve && !$msgInfo['approved'] ? (!empty($_REQUEST['approve']) ? 1 : 0) : $msgInfo['approved']) : 1;
			$approve_has_changed = $msgInfo['approved'] != $becomesApproved;

			if (!allowedTo('moderate_forum') || !$posterIsGuest)
			{
				$_POST['guestname'] = $msgInfo['poster_name'];
				$_POST['email'] = $msgInfo['poster_email'];
			}
		}

		// In case we want to override
		if (allowedTo('approve_posts'))
		{
			$becomesApproved = !isset($_REQUEST['approve']) || !empty($_REQUEST['approve']) ? 1 : 0;
			$approve_has_changed = isset($msgInfo['approved']) ? $msgInfo['approved'] != $becomesApproved : false;
		}

		// If the poster is a guest evaluate the legality of name and email.
		if ($posterIsGuest)
		{
			$_POST['guestname'] = !isset($_POST['guestname']) ? '' : Util::htmlspecialchars(trim($_POST['guestname']));
			$_POST['email'] = !isset($_POST['email']) ? '' : Util::htmlspecialchars(trim($_POST['email']));

			if ($_POST['guestname'] == '' || $_POST['guestname'] == '_')
			{
				$this->_post_errors->addError('no_name');
			}

			if (Util::strlen($_POST['guestname']) > 25)
			{
				$this->_post_errors->addError('long_name');
			}

			if (empty($modSettings['guest_post_no_email']))
			{
				// Only check if they changed it!
				if (!isset($msgInfo) || $msgInfo['poster_email'] !== $_POST['email'])
				{
					if (!allowedTo('moderate_forum') && !DataValidator::is_valid($_POST, array('email' => 'valid_email|required'), array('email' => 'trim')))
					{
						empty($_POST['email']) ? $this->_post_errors->addError('no_email') : $this->_post_errors->addError('bad_email');
					}
				}

				// Now make sure this email address is not banned from posting.
				isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));
			}

			// In case they are making multiple posts this visit, help them along by storing their name.
			if (!$this->_post_errors->hasErrors())
			{
				$_SESSION['guest_name'] = $_POST['guestname'];
				$_SESSION['guest_email'] = $_POST['email'];
			}
		}

		// Trigger before_save_post event
		try
		{
			$this->_events->trigger('before_save_post', array('post_errors' => $this->_post_errors, 'topic_info' => $topic_info));
		}
		catch (ControllerRedirectException $e)
		{
			return $e->doRedirect($this);
		}

		// Check the subject and message.
		if (!isset($_POST['subject']) || Util::htmltrim(Util::htmlspecialchars($_POST['subject'])) === '')
		{
			$this->_post_errors->addError('no_subject');
		}

		if (!isset($_POST['message']) || Util::htmltrim(Util::htmlspecialchars($_POST['message'], ENT_QUOTES)) === '')
		{
			$this->_post_errors->addError('no_message');
		}
		elseif (!empty($modSettings['max_messageLength']) && Util::strlen($_POST['message']) > $modSettings['max_messageLength'])
		{
			$this->_post_errors->addError(array('long_message', array($modSettings['max_messageLength'])));
		}
		else
		{
			// Prepare the message a bit for some additional testing.
			$_POST['message'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true);

			// Preparse code. (Zef)
			if ($this->user->is_guest)
			{
				$this->user->name = $_POST['guestname'];
			}

			$this->preparse->preparsecode($_POST['message']);

			$bbc_parser = ParserWrapper::instance();

			// Let's see if there's still some content left without the tags.
			if (Util::htmltrim(strip_tags($bbc_parser->parseMessage($_POST['message'], false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($_POST['message'], '[html]') === false))
			{
				$this->_post_errors->addError('no_message');
			}
		}

		if ($posterIsGuest)
		{
			// If user is a guest, make sure the chosen name isn't taken.
			require_once(SUBSDIR . '/Members.subs.php');
			if (isReservedName($_POST['guestname'], 0, true, false) && (!isset($msgInfo['poster_name']) || $_POST['guestname'] !== $msgInfo['poster_name']))
			{
				$this->_post_errors->addError('bad_name');
			}
		}
		// If the user isn't a guest, get his or her name and email.
		elseif (!isset($_REQUEST['msg']))
		{
			$_POST['guestname'] = $this->user->username;
			$_POST['email'] = $this->user->email;
		}

		// Posting somewhere else? Are we sure you can?
		if (!empty($_REQUEST['post_in_board']))
		{
			$new_board = (int) $_REQUEST['post_in_board'];
			if (!allowedTo('post_new', $new_board))
			{
				$post_in_board = boardInfo($new_board);

				if (!empty($post_in_board))
				{
					$this->_post_errors->addError(array('post_new_board', array($post_in_board['name'])));
				}
				else
				{
					$this->_post_errors->addError('post_new');
				}
			}
		}

		// Any mistakes?
		if ($this->_post_errors->hasErrors())
		{
			theme()->addInlineJavascript('
				$(function() {
					$("html,body").scrollTop($(\'.category_header:visible:first\').offset().top);
				});'
			);

			$_REQUEST['preview'] = false;

			return $this->action_post();
		}

		// Make sure the user isn't spamming the board.
		if (!isset($_REQUEST['msg']))
		{
			spamProtection('post');
		}

		// At about this point, we're posting and that's that.
		ignore_user_abort(true);
		detectServer()->setTimeLimit(300);

		// Add special html entities to the subject, name, and email.
		$_POST['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
		$_POST['guestname'] = htmlspecialchars($_POST['guestname'], ENT_COMPAT, 'UTF-8');
		$_POST['email'] = htmlspecialchars($_POST['email'], ENT_COMPAT, 'UTF-8');

		// At this point, we want to make sure the subject isn't too long.
		if (Util::strlen($_POST['subject']) > 100)
		{
			$_POST['subject'] = Util::substr($_POST['subject'], 0, 100);
		}

		// Creating a new topic?
		$newTopic = empty($_REQUEST['msg']) && empty($topic);

		// Collect all parameters for the creation or modification of a post.
		$msgOptions = array(
			'id' => empty($_REQUEST['msg']) ? 0 : (int) $_REQUEST['msg'],
			'subject' => $_POST['subject'],
			'body' => $_POST['message'],
			'icon' => preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']),
			'smileys_enabled' => !isset($_POST['ns']),
			'approved' => $becomesApproved,
		);

		$topicOptions = array(
			'id' => empty($topic) ? 0 : $topic,
			'board' => $board,
			'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
			'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
			'mark_as_read' => true,
			'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
		);

		$posterOptions = array(
			'id' => $this->user->id,
			'name' => $_POST['guestname'],
			'email' => $_POST['email'],
			'update_post_count' => $this->user->is_guest === false && !isset($_REQUEST['msg']) && $board_info['posts_count'],
		);

		// Trigger the pre_save_post event
		$this->_events->trigger('pre_save_post', array('msgOptions' => &$msgOptions, 'topicOptions' => &$topicOptions, 'posterOptions' => &$posterOptions));

		// This is an already existing message. Edit it.
		if (!empty($_REQUEST['msg']))
		{
			$posterOptions['id_starter'] = isset($msgInfo['id_member']) ? $msgInfo['id_member'] : $user_info['id'];

			// Have admins allowed people to hide their screwups?
			if (time() - $msgInfo['poster_time'] > $modSettings['edit_wait_time'] || $this->user->id != $msgInfo['id_member'])
			{
				$msgOptions['modify_time'] = time();
				$msgOptions['modify_name'] = $this->user->name;
			}

			// This will save some time...
			if (empty($approve_has_changed))
			{
				unset($msgOptions['approved']);
			}

			modifyPost($msgOptions, $topicOptions, $posterOptions);
		}
		// This is a new topic or an already existing one. Save it.
		else
		{
			// We also have to fake the board:
			// if it's valid and it's not the current, let's forget about the "current" and load the new one
			if (!empty($new_board) && $board !== $new_board)
			{
				$board = $new_board;
				loadBoard();

				// Some details changed
				$topicOptions['board'] = $board;
				$topicOptions['is_approved'] = !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']);
				$posterOptions['update_post_count'] = $this->user->is_guest === false && !isset($_REQUEST['msg']) && $board_info['posts_count'];
			}

			createPost($msgOptions, $topicOptions, $posterOptions);

			if (isset($topicOptions['id']))
			{
				$topic = $topicOptions['id'];
			}
		}

		// Trigger the after_save_post event
		$this->_events->trigger('after_save_post', array('board' => $board, 'topic' => $topic, 'msgOptions' => $msgOptions, 'topicOptions' => $topicOptions, 'becomesApproved' => $becomesApproved, 'posterOptions' => $posterOptions));

		// Marking boards as read.
		// (You just posted and they will be unread.)
		if ($this->user->is_guest === false)
		{
			$board_list = !empty($board_info['parent_boards']) ? array_keys($board_info['parent_boards']) : array();

			// Returning to the topic?
			if (!empty($_REQUEST['goback']))
			{
				$board_list[] = $board;
			}

			if (!empty($board_list))
			{
				markBoardsRead($board_list, false, false);
			}
		}

		// Turn notification on or off.
		if (!empty($_POST['notify']) && allowedTo('mark_any_notify'))
		{
			setTopicNotification($this->user->id, $topic, true);
		}
		elseif (!$newTopic)
		{
			setTopicNotification($this->user->id, $topic, false);
		}

		// Log an act of moderation - modifying.
		if (!empty($moderationAction))
		{
			logAction('modify', array('topic' => $topic, 'message' => (int) $_REQUEST['msg'], 'member' => $msgInfo['id_member'], 'board' => $board));
		}

		if (isset($_POST['lock']) && $_POST['lock'] != 2)
		{
			logAction(empty($_POST['lock']) ? 'unlock' : 'lock', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));
		}

		if (isset($_POST['sticky']))
		{
			logAction(empty($_POST['sticky']) ? 'unsticky' : 'sticky', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));
		}

		// Notify any members who have notification turned on for this topic/board - only do this if it's going to be approved(!)
		if ($becomesApproved)
		{
			require_once(SUBSDIR . '/Notification.subs.php');
			if ($newTopic)
			{
				$notifyData = array(
					'body' => $_POST['message'],
					'subject' => $_POST['subject'],
					'name' => $this->user->name,
					'poster' => $this->user->id,
					'msg' => $msgOptions['id'],
					'board' => $board,
					'topic' => $topic,
					'signature' => User::$settings->signature(''),
				);
				sendBoardNotifications($notifyData);
			}
			elseif (empty($_REQUEST['msg']))
			{
				// Only send it to everyone if the topic is approved, otherwise just to the topic starter if they want it.
				if ($topic_info['approved'])
				{
					sendNotifications($topic, 'reply');
				}
				else
				{
					sendNotifications($topic, 'reply', array(), $topic_info['id_member_started']);
				}
			}
		}

		if ($board_info['num_topics'] == 0)
		{
			Cache::instance()->remove('board-' . $board);
		}

		if (!empty($_POST['announce_topic']))
		{
			redirectexit('action=announce;sa=selectgroup;topic=' . $topic . (!empty($_POST['move']) && allowedTo('move_any') ? ';move' : '') . (empty($_REQUEST['goback']) ? '' : ';goback'));
		}

		if (!empty($_POST['move']) && allowedTo('move_any'))
		{
			redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
		}

		// Return to post if the mod is on.
		if (isset($_REQUEST['msg']) && !empty($_REQUEST['goback']))
		{
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		}
		elseif (!empty($_REQUEST['goback']))
		{
			redirectexit('topic=' . $topic . '.new#new');
		}
		// Dut-dut-duh-duh-DUH-duh-dut-duh-duh!  *dances to the Final Fantasy Fanfare...*
		else
		{
			redirectexit('board=' . $board . '.0');
		}
	}

	/**
	 * Toggle a post lock status
	 *
	 * @param int|null $lock
	 * @param string|null $topic_info
	 *
	 * @return int|null
	 */
	protected function _checkLocked($lock, $topic_info = null)
	{
		// A new topic
		if ($topic_info === null)
		{
			// New topics are by default not locked.
			if (empty($lock))
			{
				return null;
			}
			// Besides, you need permission.
			elseif (!allowedTo(array('lock_any', 'lock_own')))
			{
				return null;
			}
			// A moderator-lock (1) can override a user-lock (2).
			else
			{
				return allowedTo('lock_any') ? 1 : 2;
			}
		}

		// Nothing changes to the lock status.
		if ((empty($lock) && empty($topic_info['locked'])) || (!empty($lock) && !empty($topic_info['locked'])))
		{
			return null;
		}
		// You're simply not allowed to (un)lock this.
		elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $this->user->id != $topic_info['id_member_started']))
		{
			return null;
		}
		// You're only allowed to lock your own topics.
		elseif (!allowedTo('lock_any'))
		{
			// You're not allowed to break a moderator's lock.
			if ($topic_info['locked'] == 1)
			{
				return null;
			}
			// Lock it with a soft lock or unlock it.
			else
			{
				$lock = empty($lock) ? 0 : 2;
			}
		}
		// You must be the moderator.
		else
		{
			$lock = empty($lock) ? 0 : 1;
		}

		return $lock;
	}

	/**
	 * Loads a post and inserts it into the current editing text box.
	 * Used to quick edit a post as well as to quote a post and place it in the quick reply box
	 * Can be used to quick edit just the subject from the topic listing
	 *
	 * uses the Post language file.
	 * uses special (sadly browser dependent) javascript to parse entities for internationalization reasons.
	 * accessed with ?action=quotefast and ?action=quotefast;modify
	 */
	public function action_quotefast()
	{
		global $context;

		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Post');

		// Where we going if we need to?
		$context['post_box_name'] = isset($_GET['pb']) ? $_GET['pb'] : '';

		$row = quoteMessageInfo((int) $_REQUEST['quote'], isset($_REQUEST['modify']));

		$context['sub_template'] = 'quotefast';
		if (!empty($row))
		{
			$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == $this->user->id) || allowedTo('approve_posts', $row['id_board']);
		}

		if (!empty($can_view_post))
		{
			// Remove special formatting we don't want anymore.
			$row['body'] = $this->preparse->un_preparsecode($row['body']);

			// Censor the message!
			$row['body'] = censor($row['body']);

			$row['body'] = preg_replace('~<br ?/?' . '>~i', "\n", $row['body']);

			// Want to modify a single message by double clicking it?
			if (isset($_REQUEST['modify']))
			{
				$row['subject'] = censor($row['subject']);

				$context['sub_template'] = 'modifyfast';
				$context['message'] = array(
					'id' => $_REQUEST['quote'],
					'body' => $row['body'],
					'subject' => addcslashes($row['subject'], '"'),
				);

				return;
			}

			// Remove any nested quotes.
			$row['body'] = removeNestedQuotes($row['body']);

			// Add a quote string on the front and end.
			$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . "]\n" . $row['body'] . "\n[/quote]";
			$context['quote']['text'] = strtr(un_htmlspecialchars($context['quote']['xml']), array('\'' => '\\\'', '\\' => '\\\\', "\n" => '\\n', '</script>' => '</\' + \'script>'));
			$context['quote']['xml'] = strtr($context['quote']['xml'], array('&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;'));

			$context['quote']['mozilla'] = strtr(Util::htmlspecialchars($context['quote']['text']), array('&quot;' => '"'));
		}
		//@todo Needs a nicer interface.
		// In case our message has been removed in the meantime.
		elseif (isset($_REQUEST['modify']))
		{
			$context['sub_template'] = 'modifyfast';
			$context['message'] = array(
				'id' => 0,
				'body' => '',
				'subject' => '',
			);
		}
		else
		{
			$context['quote'] = array(
				'xml' => '',
				'mozilla' => '',
				'text' => '',
			);
		}
	}

	/**
	 * Used to edit the body or subject of a message inline
	 * called from action=jsmodify from script and topic js
	 */
	public function action_jsmodify()
	{
		global $modSettings, $board, $topic;
		global $context;

		// We have to have a topic!
		if (empty($topic))
		{
			obExit(false);
		}

		checkSession('get');

		$row = getTopicInfoByMsg($topic, empty($_REQUEST['msg']) ? 0 : (int) $_REQUEST['msg']);

		if (empty($row))
		{
			throw new Exception('no_board', false);
		}

		// Change either body or subject requires permissions to modify messages.
		if (isset($_POST['message']) || isset($_POST['subject']) || isset($_REQUEST['icon']))
		{
			if (!empty($row['locked']))
			{
				isAllowedTo('moderate_board');
			}

			if ($row['id_member'] == $this->user->id && !allowedTo('modify_any'))
			{
				if ((!$modSettings['postmod_active'] || $row['approved']) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				{
					throw new Exception('modify_post_time_passed', false);
				}
				elseif ($row['id_member_started'] == $this->user->id && !allowedTo('modify_own'))
				{
					isAllowedTo('modify_replies');
				}
				else
				{
					isAllowedTo('modify_own');
				}
			}
			// Otherwise, they're locked out; someone who can modify the replies is needed.
			elseif ($row['id_member_started'] == $this->user->id && !allowedTo('modify_any'))
			{
				isAllowedTo('modify_replies');
			}
			else
			{
				isAllowedTo('modify_any');
			}

			// Only log this action if it wasn't your message.
			$moderationAction = $row['id_member'] != $this->user->id;
		}

		if (isset($_POST['subject']) && Util::htmltrim(Util::htmlspecialchars($_POST['subject'])) !== '')
		{
			$_POST['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));

			// Maximum number of characters.
			if (Util::strlen($_POST['subject']) > 100)
			{
				$_POST['subject'] = Util::substr($_POST['subject'], 0, 100);
			}
		}
		elseif (isset($_POST['subject']))
		{
			$this->_post_errors->addError('no_subject');
			unset($_POST['subject']);
		}

		if (isset($_POST['message']))
		{
			if (Util::htmltrim(Util::htmlspecialchars($_POST['message'])) === '')
			{
				$this->_post_errors->addError('no_message');
				unset($_POST['message']);
			}
			elseif (!empty($modSettings['max_messageLength']) && Util::strlen($_POST['message']) > $modSettings['max_messageLength'])
			{
				$this->_post_errors->addError(array('long_message', array($modSettings['max_messageLength'])));
				unset($_POST['message']);
			}
			else
			{
				$_POST['message'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true);

				$this->preparse->preparsecode($_POST['message']);
				$bbc_parser = ParserWrapper::instance();

				if (Util::htmltrim(strip_tags($bbc_parser->parseMessage($_POST['message'], false), '<img>')) === '')
				{
					$this->_post_errors->addError('no_message');
					unset($_POST['message']);
				}
			}
		}

		if (isset($_POST['lock']))
		{
			$_POST['lock'] = $this->_checkLocked($_POST['lock'], $row);
		}

		if (isset($_POST['sticky']) && !allowedTo('make_sticky'))
		{
			unset($_POST['sticky']);
		}

		if (!$this->_post_errors->hasErrors())
		{
			if (!empty($modSettings['mentions_enabled']))
			{
				if (!empty($_REQUEST['uid']))
				{
					$query_params = array();
					$query_params['member_ids'] = array_unique(array_map('intval', $_REQUEST['uid']));
					require_once(SUBSDIR . '/Members.subs.php');
					$mentioned_members = membersBy('member_ids', $query_params, true);
					$replacements = 0;
					$actually_mentioned = array();
					foreach ($mentioned_members as $member)
					{
						$_POST['message'] = str_replace('@' . $member['real_name'], '[member=' . $member['id_member'] . ']' . $member['real_name'] . '[/member]', $_POST['message'], $replacements);
						if ($replacements > 0)
						{
							$actually_mentioned[] = $member['id_member'];
						}
					}
				}

				if (!empty($actually_mentioned))
				{
					$notifier = Notifications::instance();
					$notifier->add(new NotificationsTask(
						'Mentionmem',
						$row['id_msg'],
						$row['id_member'],
						array('id_members' => $actually_mentioned, 'status' => $row['approved'] ? 'new' : 'unapproved')
					));
				}
			}

			$msgOptions = array(
				'id' => $row['id_msg'],
				'subject' => isset($_POST['subject']) ? $_POST['subject'] : null,
				'body' => isset($_POST['message']) ? $_POST['message'] : null,
				'icon' => isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : null,
			);

			$topicOptions = array(
				'id' => $topic,
				'board' => $board,
				'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
				'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
				'mark_as_read' => false,
			);

			$posterOptions = array();

			// Only consider marking as editing if they have edited the subject, message or icon.
			if ((isset($_POST['subject']) && $_POST['subject'] !== $row['subject']) || (isset($_POST['message']) && $_POST['message'] !== $row['body']) || (isset($_REQUEST['icon']) && $_REQUEST['icon'] !== $row['icon']))
			{
				// And even then only if the time has passed...
				if (time() - $row['poster_time'] > $modSettings['edit_wait_time'] || $this->user->id != $row['id_member'])
				{
					$msgOptions['modify_time'] = time();
					$msgOptions['modify_name'] = $this->user->name;
				}
			}
			// If nothing was changed there's no need to add an entry to the moderation log.
			else
			{
				$moderationAction = false;
			}

			modifyPost($msgOptions, $topicOptions, $posterOptions);

			// If we didn't change anything this time but had before put back the old info.
			if (!isset($msgOptions['modify_time']) && !empty($row['modified_time']))
			{
				$msgOptions['modify_time'] = $row['modified_time'];
				$msgOptions['modify_name'] = $row['modified_name'];
			}

			// Changing the first subject updates other subjects to 'Re: new_subject'.
			if (isset($_POST['subject']) && isset($_REQUEST['change_all_subjects']) && $row['id_first_msg'] == $row['id_msg'] && !empty($row['num_replies']) && (allowedTo('modify_any') || ($row['id_member_started'] == $this->user->id && allowedTo('modify_replies'))))
			{
				// Get the proper (default language) response prefix first.
				$context['response_prefix'] = response_prefix();

				topicSubject(array('id_topic' => $topic, 'id_first_msg' => $row['id_first_msg']), $_POST['subject'], $context['response_prefix'], true);
			}

			if (!empty($moderationAction))
			{
				logAction('modify', array('topic' => $topic, 'message' => $row['id_msg'], 'member' => $row['id_member'], 'board' => $board));
			}
		}

		if (isset($_REQUEST['xml']))
		{
			$bbc_parser = ParserWrapper::instance();
			$context['sub_template'] = 'modifydone';

			if (!$this->_post_errors->hasErrors() && isset($msgOptions['subject']) && isset($msgOptions['body']))
			{
				$context['message'] = array(
					'id' => $row['id_msg'],
					'modified' => array(
						'time' => isset($msgOptions['modify_time']) ? standardTime($msgOptions['modify_time']) : '',
						'html_time' => isset($msgOptions['modify_time']) ? htmlTime($msgOptions['modify_time']) : '',
						'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
						'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					),
					'subject' => $msgOptions['subject'],
					'first_in_topic' => $row['id_msg'] == $row['id_first_msg'],
					'body' => strtr($msgOptions['body'], array(']]>' => ']]]]><![CDATA[>')),
				);

				$context['message']['subject'] = censor($context['message']['subject']);
				$context['message']['body'] = censor($context['message']['body']);

				$context['message']['body'] = $bbc_parser->parseMessage($context['message']['body'], $row['smileys_enabled']);
			}
			// Topic?
			elseif (!$this->_post_errors->hasErrors())
			{
				$context['sub_template'] = 'modifytopicdone';
				$context['message'] = array(
					'id' => $row['id_msg'],
					'modified' => array(
						'time' => isset($msgOptions['modify_time']) ? standardTime($msgOptions['modify_time']) : '',
						'html_time' => isset($msgOptions['modify_time']) ? htmlTime($msgOptions['modify_time']) : '',
						'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
						'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					),
					'subject' => isset($msgOptions['subject']) ? $msgOptions['subject'] : '',
				);

				$context['message']['subject'] = censor($context['message']['subject']);
			}
			else
			{
				$context['message'] = array(
					'id' => $row['id_msg'],
					'errors' => array(),
					'error_in_subject' => $this->_post_errors->hasError('no_subject'),
					'error_in_body' => $this->_post_errors->hasError('no_message') || $this->_post_errors->hasError('long_message'),
				);
				$context['message']['errors'] = $this->_post_errors->prepareErrors();
			}
		}
		else
		{
			obExit(false);
		}
	}
}
