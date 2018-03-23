<?php

namespace ElkArte\sources\subs\MessagesCallback;

use \ElkArte\sources\subs\MessagesCallback\BodyParser\BodyParserInterface;
use \ElkArte\ValuesContainer;

class DisplayRenderer extends Renderer
{
	const BEFORE_PREPARE_HOOK = 'integrate_before_prepare_display_context';
	const CONTEXT_HOOK = 'integrate_prepare_display_context';

	protected function _setupPermissions()
	{
		global $context, $modSettings, $user_info;

		// Are you allowed to remove at least a single reply?
		$context['can_remove_post'] |= allowedTo('delete_own') && (empty($modSettings['edit_disable_time']) || $this->_this_message['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()) && $this->_this_message['id_member'] == $user_info['id'];

		// Have you liked this post, can you?
		$this->_this_message['you_liked'] = !empty($context['likes'][$this->_this_message['id_msg']]['member'])
			&& isset($context['likes'][$this->_this_message['id_msg']]['member'][$user_info['id']]);
		$this->_this_message['use_likes'] = allowedTo('like_posts') && empty($context['is_locked'])
			&& ($this->_this_message['id_member'] != $user_info['id'] || !empty($modSettings['likeAllowSelf']))
			&& (empty($modSettings['likeMinPosts']) ? true : $modSettings['likeMinPosts'] <= $user_info['posts']);
		$this->_this_message['like_count'] = !empty($context['likes'][$this->_this_message['id_msg']]['count']) ? $context['likes'][$this->_this_message['id_msg']]['count'] : 0;
	}

	protected function _adjustAllMembers()
	{
		global $memberContext, $settings, $context;

		$id_member = $this->_this_message[$this->_idx_mapper->id_member];

		$memberContext[$id_member]['ip'] = $this->_this_message['poster_ip'] ?? '';
		$memberContext[$id_member]['show_profile_buttons'] = $settings['show_profile_buttons'] && (!empty($memberContext[$id_member]['can_view_profile']) || (!empty($memberContext[$id_member]['website']['url']) && !isset($context['disabled_fields']['website'])) || (in_array($memberContext[$id_member]['show_email'], array('yes', 'yes_permission_override', 'no_through_forum'))) || $context['can_send_pm']);

		$context['id_msg'] = $this->_this_message['id_msg'] ?? '';
	}

	protected function _buildOutputArray()
	{
		global $scripturl, $topic, $context, $modSettings, $user_info, $txt;

		require_once(SUBSDIR . '/Attachments.subs.php');

		$output = parent::_buildOutputArray();
		$output += array(
			'attachment' => loadAttachmentContext($this->_this_message['id_msg']),
			'href' => $scripturl . '?topic=' . $topic . '.msg' . $this->_this_message['id_msg'] . '#msg' . $this->_this_message['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $topic . '.msg' . $this->_this_message['id_msg'] . '#msg' . $this->_this_message['id_msg'] . '" rel="nofollow">' . $this->_this_message['subject'] . '</a>',
			'icon' => $this->_this_message['icon'],
			'icon_url' => $this->_options->icon_sources->{$this->_this_message['icon']},
			'modified' => array(
				'time' => standardTime($this->_this_message['modified_time']),
				'html_time' => htmlTime($this->_this_message['modified_time']),
				'timestamp' => forum_time(true, $this->_this_message['modified_time']),
				'name' => $this->_this_message['modified_name']
			),
			'new' => empty($this->_this_message['is_read']),
			'approved' => $this->_this_message['approved'],
			'first_new' => isset($context['start_from']) && $context['start_from'] == $this->_counter,
			'is_ignored' => !empty($modSettings['enable_buddylist']) && in_array($this->_this_message['id_member'], $context['user']['ignoreusers']),
			'is_message_author' => $this->_this_message['id_member'] == $user_info['id'],
			'can_approve' => !$this->_this_message['approved'] && $context['can_approve'],
			'can_unapprove' => !empty($modSettings['postmod_active']) && $context['can_approve'] && $this->_this_message['approved'],
			'can_modify' => (!$context['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $context['user']['started']) || (allowedTo('modify_own') && $this->_this_message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || !$this->_this_message['approved'] || $this->_this_message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time()))),
			'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']) || (allowedTo('delete_own') && $this->_this_message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $this->_this_message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
			'can_like' => $this->_this_message['use_likes'] && !$this->_this_message['you_liked'],
			'can_unlike' => $this->_this_message['use_likes'] && $this->_this_message['you_liked'],
			'like_counter' => $this->_this_message['like_count'],
			'likes_enabled' => !empty($modSettings['likes_enabled']) && ($this->_this_message['use_likes'] || ($this->_this_message['like_count'] != 0)),
			'classes' => array(),
		);

		if (!empty($output['modified']['name']))
			$output['modified']['last_edit_text'] = sprintf($txt['last_edit_by'], $output['modified']['time'], $output['modified']['name'], standardTime($output['modified']['timestamp']));

		if (!empty($output['member']['karma']['allow']))
		{
			$output['member']['karma'] += array(
				'applaud_url' => $scripturl . '?action=karma;sa=applaud;uid=' . $output['member']['id'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';m=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'smite_url' => $scripturl . '?action=karma;sa=smite;uid=' . $output['member']['id'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';m=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id']
			);
		}

		return $output;
	}
}