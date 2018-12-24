<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\MessagesCallback;

use \ElkArte\MessagesCallback\BodyParser\BodyParserInterface;
use \ElkArte\ValuesContainer;

/**
 * SearchRenderer
 *
 * Used by the \ElkArte\Controller\Search to prepare the search results.
 */
class SearchRenderer extends Renderer
{
	const BEFORE_PREPARE_HOOK = 'integrate_before_prepare_search_context';
	const CONTEXT_HOOK = 'integrate_prepare_search_context';

	/**
	 * 
	 * @var mixed[]
	 */
	protected $_participants = [];

	/**
	 * {@inheritdoc }
	 */
	public function __construct($request, BodyParserInterface $bodyParser, ValuesContainer $opt = null)
	{
		parent::__construct($request, $bodyParser, $opt);

		require_once(SUBSDIR . '/Attachments.subs.php');
	}

	/**
	 * @param mixed[] $participants
	 */
	public function setParticipants($participants)
	{
		$this->_participants = $participants;
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _setupPermissions()
	{
		global $txt;

		$this->_this_message['first_subject'] = $this->_this_message['first_subject'] != '' ? $this->_this_message['first_subject'] : $txt['no_subject'];
		$this->_this_message['last_subject'] = $this->_this_message['last_subject'] != '' ? $this->_this_message['last_subject'] : $txt['no_subject'];
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _adjustMemberContext($member_context)
	{
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _adjustAllMembers($member_context)
	{
		$member = \ElkArte\MembersList::get($this->_this_message['id_member']);
		$member['ip'] = $this->_this_message['poster_ip'];

		$this->_this_message['first_subject'] = censor($this->_this_message['first_subject']);
		$this->_this_message['last_subject'] = censor($this->_this_message['last_subject']);
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _buildOutputArray()
	{
		global $modSettings, $context, $options, $user_info, $txt;

		// Make sure we don't end up with a practically empty message body.
		$this->_this_message['body'] = preg_replace('~^(?:&nbsp;)+$~', '', $this->_this_message['body']);

		// Do we have quote tag enabled?
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		$output_pre = \ElkArte\TopicUtil::prepareContext(array($this->_this_message))[$this->_this_message['id_topic']];

		$output = array_merge($context['topics'][$this->_this_message['id_msg']], $output_pre);

		$output['posted_in'] = !empty($this->_participants[$this->_this_message['id_topic']]);
		$output['tests'] = array(
			'can_reply' => in_array($this->_this_message['id_board'], $this->_options['boards_can']['post_reply_any']) || in_array(0, $this->_options['boards_can']['post_reply_any']),
			'can_quote' => (in_array($this->_this_message['id_board'], $this->_options['boards_can']['post_reply_any']) || in_array(0, $this->_options['boards_can']['post_reply_any'])) && $quote_enabled,
			'can_mark_notify' => in_array($this->_this_message['id_board'], $this->_options['boards_can']['mark_any_notify']) || in_array(0, $this->_options['boards_can']['mark_any_notify']) && !$context['user']['is_guest'],
		);
		$href = getUrl('board', ['board' => $this->_this_message['id_board'], 'start' => '0', 'name' => $this->_this_message['bname']]);
		$output['board'] = array(
			'id' => $this->_this_message['id_board'],
			'name' => $this->_this_message['bname'],
			'href' => $href,
			'link' => '<a href="' . $href . '0">' . $this->_this_message['bname'] . '</a>'
		);
		$output['category'] = array(
			'id' => $this->_this_message['id_cat'],
			'name' => $this->_this_message['cat_name'],
			'href' => getUrl('action', $modSettings['default_forum_action']) . '#c' . $this->_this_message['id_cat'],
			'link' => '<a href="' . getUrl('action', $modSettings['default_forum_action']) . '#c' . $this->_this_message['id_cat'] . '">' . $this->_this_message['cat_name'] . '</a>'
		);

		determineTopicClass($output);

		if ($output['posted_in'])
		{
			$output['class'] = 'my_' . $output['class'];
		}

		$body_highlighted = $this->_this_message['body'];
		$subject_highlighted = $this->_this_message['subject'];

		if (!empty($options['display_quick_mod']))
		{
			$started = $output['first_post']['member']['id'] == $user_info['id'];

			$output['quick_mod'] = array(
				'lock' => in_array(0, $this->_options['boards_can']['lock_any']) || in_array($output['board']['id'], $this->_options['boards_can']['lock_any']) || ($started && (in_array(0, $this->_options['boards_can']['lock_own']) || in_array($output['board']['id'], $this->_options['boards_can']['lock_own']))),
				'sticky' => (in_array(0, $this->_options['boards_can']['make_sticky']) || in_array($output['board']['id'], $this->_options['boards_can']['make_sticky'])),
				'move' => in_array(0, $this->_options['boards_can']['move_any']) || in_array($output['board']['id'], $this->_options['boards_can']['move_any']) || ($started && (in_array(0, $this->_options['boards_can']['move_own']) || in_array($output['board']['id'], $this->_options['boards_can']['move_own']))),
				'remove' => in_array(0, $this->_options['boards_can']['remove_any']) || in_array($output['board']['id'], $this->_options['boards_can']['remove_any']) || ($started && (in_array(0, $this->_options['boards_can']['remove_own']) || in_array($output['board']['id'], $this->_options['boards_can']['remove_own']))),
			);

			$context['can_lock'] |= $output['quick_mod']['lock'];
			$context['can_sticky'] |= $output['quick_mod']['sticky'];
			$context['can_move'] |= $output['quick_mod']['move'];
			$context['can_remove'] |= $output['quick_mod']['remove'];
			$context['can_merge'] |= in_array($output['board']['id'], $this->_options['boards_can']['merge_any']);
			$context['can_markread'] = $context['user']['is_logged'];

			$context['qmod_actions'] = array('remove', 'lock', 'sticky', 'move', 'markread');
			call_integration_hook('integrate_quick_mod_actions_search');
		}

		foreach ($this->_bodyParser->getSearchArray() as $query)
		{
		
			// Fix the international characters in the keyword too.
			$query = un_htmlspecialchars($query);
			$query = trim($query, '\*+');
			$query = strtr(\ElkArte\Util::htmlspecialchars($query), array('\\\'' => '\''));

			$body_highlighted = preg_replace_callback('/((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '/') . ')/iu', array($this, '_highlighted_callback'), $body_highlighted);
			$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $subject_highlighted);
		}

		$member = \ElkArte\MembersList::get($this->_this_message['id_member']);
		$output['matches'][] = array(
			'id' => $this->_this_message['id_msg'],
			'attachment' => loadAttachmentContext($this->_this_message['id_msg']),
			'alternate' => $this->_counter % 2,
			'member' => $member,
			'icon' => $this->_this_message['icon'],
			'icon_url' => $this->_options->icon_sources->{$this->_this_message['icon']},
			'subject' => $this->_this_message['subject'],
			'subject_highlighted' => $subject_highlighted,
			'time' => standardTime($this->_this_message['poster_time']),
			'html_time' => htmlTime($this->_this_message['poster_time']),
			'timestamp' => forum_time(true, $this->_this_message['poster_time']),
			'counter' => $this->_counter,
			'modified' => array(
				'time' => standardTime($this->_this_message['modified_time']),
				'html_time' => htmlTime($this->_this_message['modified_time']),
				'timestamp' => forum_time(true, $this->_this_message['modified_time']),
				'name' => $this->_this_message['modified_name']
			),
			'body' => $this->_this_message['body'],
			'body_highlighted' => $body_highlighted,
			'start' => 'msg' . $this->_this_message['id_msg']
		);

		if (!$context['compact'])
		{
			$output['buttons'] = array(
				// Can we request notification of topics?
				'notify' => array(
					'href' => getUrl('action', ['action' => 'notify', 'topic' => $output['id'] . '.msg' . $this->_this_message['id_msg']]),
					'text' => $txt['notify'],
					'test' => 'can_mark_notify',
				),
				// If they *can* reply?
				'reply' => array(
					'href' => getUrl('action', ['action' => 'post', 'topic' => $output['id'] . '.msg' . $this->_this_message['id_msg']]),
					'text' => $txt['reply'],
					'test' => 'can_reply',
				),
				// If they *can* quote?
				'quote' => array(
					'href' => getUrl('action', ['action' => 'post', 'topic' => $output['id'] . '.msg' . $this->_this_message['id_msg'], 'quote' => $this->_this_message['id_msg']]),
					'text' => $txt['quote'],
					'test' => 'can_quote',
				),
			);
		}

		return $output;
	}

	/**
	 * Used to highlight body text with strings that match the search term
	 *
	 * Callback function used in $body_highlighted
	 *
	 * @param string[] $matches
	 *
	 * @return string
	 */
	private function _highlighted_callback($matches)
	{
		return isset($matches[2]) && $matches[2] == $matches[1] ? stripslashes($matches[1]) : '<span class="highlight">' . $matches[1] . '</span>';
	}
}
