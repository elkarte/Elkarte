<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
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

namespace ElkArte\MessagesCallback;

use ElkArte\MembersList;
use ElkArte\MessagesCallback\BodyParser\BodyParserInterface;
use ElkArte\TopicUtil;
use ElkArte\Util;
use ElkArte\ValuesContainer;

/**
 * SearchRenderer
 *
 * Used by the \ElkArte\Controller\Search to prepare the search results.
 */
class SearchRenderer extends Renderer
{
	public const BEFORE_PREPARE_HOOK = 'integrate_before_prepare_search_context';
	public const CONTEXT_HOOK = 'integrate_prepare_search_context';

	/** @var array */
	protected $_participants = [];

	/**
	 * {@inheritdoc }
	 */
	public function __construct($request, $user, BodyParserInterface $bodyParser, ValuesContainer $opt = null)
	{
		parent::__construct($request, $user, $bodyParser, $opt);

		require_once(SUBSDIR . '/Attachments.subs.php');
	}

	/**
	 * @param array $participants
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

		$this->_this_message['first_subject'] = $this->_this_message['first_subject'] !== '' ? $this->_this_message['first_subject'] : $txt['no_subject'];
		$this->_this_message['last_subject'] = $this->_this_message['last_subject'] !== '' ? $this->_this_message['last_subject'] : $txt['no_subject'];
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
		$member = MembersList::get($this->_this_message['id_member']);
		$member['ip'] = $this->_this_message['poster_ip'];

		$this->_this_message['first_subject'] = censor($this->_this_message['first_subject']);
		$this->_this_message['last_subject'] = censor($this->_this_message['last_subject']);
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _buildOutputArray()
	{
		global $modSettings, $context, $options;

		// Make sure we don't end up with a practically empty message body.
		$this->_this_message['body'] = preg_replace('~^(?:&nbsp;)+$~', '', $this->_this_message['body']);

		// Do we have quote tag enabled?
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		$output_pre = TopicUtil::prepareContext([$this->_this_message])[$this->_this_message['id_topic']];

		$output = array_merge($context['topics'][$this->_this_message['id_msg']], $output_pre);
		$output['posted_in'] = !empty($this->_participants[$this->_this_message['id_topic']]);
		$href = getUrl('board', ['board' => $this->_this_message['id_board'], 'start' => '0', 'name' => $this->_this_message['bname']]);

		$output['board'] = [
			'id' => $this->_this_message['id_board'],
			'name' => $this->_this_message['bname'],
			'href' => $href,
			'link' => '<a href="' . $href . '0">' . $this->_this_message['bname'] . '</a>'
		];

		$output['category'] = [
			'id' => $this->_this_message['id_cat'],
			'name' => $this->_this_message['cat_name'],
			'href' => getUrl('action', $modSettings['default_forum_action']) . '#c' . $this->_this_message['id_cat'],
			'link' => '<a href="' . getUrl('action', $modSettings['default_forum_action']) . '#c' . $this->_this_message['id_cat'] . '">' . $this->_this_message['cat_name'] . '</a>'
		];

		determineTopicClass($output);

		if ($output['posted_in'])
		{
			$output['class'] = 'my_' . $output['class'];
		}

		$body_highlighted = $this->_this_message['body'];
		$subject_highlighted = $this->_this_message['subject'];

		if (!empty($options['display_quick_mod']))
		{
			$started = (int) $output['first_post']['member']['id'] === $this->user->id;

			$output['quick_mod'] = [
				'lock' => $this->_canLock($output, $started),
				'sticky' => $this->_canSticky($output),
				'move' => $this->_canMove($output, $started),
				'remove' => $this->_canRemove($output, $started),
			];

			$context['can_lock'] |= $output['quick_mod']['lock'];
			$context['can_sticky'] |= $output['quick_mod']['sticky'];
			$context['can_move'] |= $output['quick_mod']['move'];
			$context['can_remove'] |= $output['quick_mod']['remove'];
			$context['can_merge'] |= in_array($output['board']['id'], $this->_options['boards_can']['merge_any']);
			$context['can_markread'] = $context['user']['is_logged'];

			$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'];
			if ($context['can_quick_mod'])
			{
				$context['qmod_actions'] = ['remove', 'lock', 'sticky', 'move', 'markread'];
				call_integration_hook('integrate_quick_mod_actions_search');
			}
		}

		foreach ($this->_bodyParser->getSearchArray() as $query)
		{
			// Fix the international characters in the keyword too.
			$query = un_htmlspecialchars($query);
			$query = trim($query, '\*+');
			$query = strtr(Util::htmlspecialchars($query), ['\\\'' => '\'']);

			$search_highlight = preg_quote(strtr($query, ['\'' => '&#039;']), '/');
			$body_highlighted = preg_replace_callback('/((<[^>]*)|(\b' . $search_highlight . '\b)|' . $search_highlight . ')/iu',
				function ($matches) {
					return $this->_highlighted_callback($matches);
				}, $body_highlighted);
			$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $subject_highlighted);
		}

		$member = MembersList::get($this->_this_message['id_member']);
		$output['matches'][] = [
			'id' => $this->_this_message['id_msg'],
			'attachment' => [],
			'member' => $member,
			'icon' => $this->_options->icon_sources->getIconName($this->_this_message['icon']),
			'icon_url' => $this->_options->icon_sources->getIconURL($this->_this_message['icon']),
			'subject' => $this->_this_message['subject'],
			'subject_highlighted' => $subject_highlighted,
			'time' => standardTime($this->_this_message['poster_time']),
			'html_time' => htmlTime($this->_this_message['poster_time']),
			'timestamp' => forum_time(true, $this->_this_message['poster_time']),
			'counter' => $this->_counter,
			'modified' => [
				'time' => standardTime($this->_this_message['modified_time']),
				'html_time' => htmlTime($this->_this_message['modified_time']),
				'timestamp' => forum_time(true, $this->_this_message['modified_time']),
				'name' => $this->_this_message['modified_name']
			],
			'body' => $this->_this_message['body'],
			'body_highlighted' => $body_highlighted,
			'start' => 'msg' . $this->_this_message['id_msg'],
		];

		$output['buttons'] = $this->_buildSearchButtons($output, $quote_enabled);

		return $output;
	}

	/**
	 * Generates a PM button array suitable for consumption by template_button_strip
	 *
	 * @param array $output
	 * @param bool $quote_enabled
	 * @return array
	 */
	protected function _buildSearchButtons($output, $quote_enabled)
	{
		global $context;

		$searchButtons = [];

		if (!$context['compact'])
		{
			$searchButtons = [
				// If they can moderate
				'inline_mod_check' => [
					'class' => 'inline_mod_check',
					'value' => $output['id'],
					'checkbox' => 'always',
					'name' => 'topics',
					'enabled' => $context['can_quick_mod'],
				],
				// Can we request notification of topics?
				'notify' => [
					'url' => getUrl('action', ['action' => 'notify', 'topic' => $output['id'] . '.msg' . $this->_this_message['id_msg']]),
					'text' => 'notify',
					'icon' => 'envelope',
					'enabled' => in_array($output['board']['id'], $this->_options['boards_can']['mark_any_notify']) || in_array(0, $this->_options['boards_can']['mark_any_notify']) && !$context['user']['is_guest'],
				],
				// If they *can* reply?
				'reply' => [
					'url' => getUrl('action', ['action' => 'post', 'topic' => $output['id'] . '.msg' . $this->_this_message['id_msg']]),
					'text' => 'reply',
					'icon' => 'modify',
					'enabled' => in_array($output['board']['id'], $this->_options['boards_can']['post_reply_any']) || in_array(0, $this->_options['boards_can']['post_reply_any']),
				],
				// If they *can* quote?
				'quote' => [
					'url' => getUrl('action', ['action' => 'post', 'topic' => $output['id'] . '.msg' . $this->_this_message['id_msg'], 'quote' => $this->_this_message['id_msg']]),
					'text' => 'quote',
					'icon' => 'quote',
					'enabled' => (in_array($output['board']['id'], $this->_options['boards_can']['post_reply_any']) || in_array(0, $this->_options['boards_can']['post_reply_any'])) && $quote_enabled,
				],
			];
		}

		// Drop any non-enabled ones
		return array_filter($searchButtons, static function ($button) {
			return !isset($button['enabled']) || $button['enabled'] !== false;
		});
	}

	/**
	 * Used to highlight body text with strings that match the search term
	 *
	 * Callback function used in $body_highlighted.
	 * match[2] would contain terms that start with <
	 * match[1] would be a word in a word, and could be just the word
	 * match[3] would be the search term as a full word
	 *
	 * @param string[] $matches
	 *
	 * @return string
	 */
	private function _highlighted_callback($matches)
	{
		if (isset($matches[2]) && $matches[2] === $matches[1])
		{
			return stripslashes($matches[1]);
		}

		if (isset($matches[3]))
		{
			return '<span class="highlight">' . $matches[3] . '</span>';
		}

		return '<span class="highlight_sub">' . $matches[1] . '</span>';
	}

	/**
	 * Can the item be locked
	 *
	 * @param array $output
	 * @param bool $started
	 * @return bool
	 */
	private function _canLock($output, $started)
	{
		return in_array(0, $this->_options['boards_can']['lock_any'])
			|| in_array($output['board']['id'], $this->_options['boards_can']['lock_any'])
			|| ($started && (in_array(0, $this->_options['boards_can']['lock_own'])
					|| in_array($output['board']['id'], $this->_options['boards_can']['lock_own'])));
	}

	/**
	 * Can the item be pinned
	 *
	 * @param array $output
	 * @return bool
	 */
	private function _canSticky($output)
	{
		return in_array(0, $this->_options['boards_can']['make_sticky'])
			|| in_array($output['board']['id'], $this->_options['boards_can']['make_sticky']);
	}

	/**
	 * Can the item be moved
	 *
	 * @param array $output
	 * @param bool $started
	 * @return bool
	 */
	private function _canMove($output, $started)
	{
		return in_array(0, $this->_options['boards_can']['move_any'])
			|| in_array($output['board']['id'], $this->_options['boards_can']['move_any'])
			|| ($started && (in_array(0, $this->_options['boards_can']['move_own'])
					|| in_array($output['board']['id'], $this->_options['boards_can']['move_own'])));

	}

	/**
	 * Can the item be removed
	 *
	 * @param array $output
	 * @param bool $started
	 * @return bool
	 */
	private function _canRemove($output, $started)
	{
		return in_array(0, $this->_options['boards_can']['remove_any'])
			|| in_array($output['board']['id'], $this->_options['boards_can']['remove_any'])
			|| ($started && (in_array(0, $this->_options['boards_can']['remove_own'])
					|| in_array($output['board']['id'], $this->_options['boards_can']['remove_own'])));

	}
}
