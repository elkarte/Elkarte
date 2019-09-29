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

/**
 * DisplayRenderer
 * The class prepares the details of a message so that they can be used
 * to display it in the template.
 */
class DisplayRenderer extends Renderer
{
	const BEFORE_PREPARE_HOOK = 'integrate_before_prepare_display_context';
	const CONTEXT_HOOK = 'integrate_prepare_display_context';

	/**
	 * {@inheritdoc }
	 */
	protected function _setupPermissions()
	{
		global $context, $modSettings;

		// Are you allowed to remove at least a single reply?
		$context['can_remove_post'] |= allowedTo('delete_own') && (empty($modSettings['edit_disable_time']) || $this->_this_message['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()) && $this->_this_message['id_member'] == $this->user->id;

		// Have you liked this post, can you?
		$this->_this_message['you_liked'] = !empty($context['likes'][$this->_this_message['id_msg']]['member'])
			&& isset($context['likes'][$this->_this_message['id_msg']]['member'][$this->user->id]);
		$this->_this_message['use_likes'] = allowedTo('like_posts') && empty($context['is_locked'])
			&& ($this->_this_message['id_member'] != $this->user->id || !empty($modSettings['likeAllowSelf']))
			&& (empty($modSettings['likeMinPosts']) ? true : $modSettings['likeMinPosts'] <= $this->user->posts);
		$this->_this_message['like_count'] = !empty($context['likes'][$this->_this_message['id_msg']]['count']) ? $context['likes'][$this->_this_message['id_msg']]['count'] : 0;
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _adjustAllMembers($member_context)
	{
		global $settings, $context;

		$id_member = $this->_this_message[$this->_idx_mapper->id_member];
		$this_member = MembersList::get($id_member);
		$this_member->loadContext();

		$this_member['ip'] = $this->_this_message['poster_ip'] ?? '';
		$this_member['show_profile_buttons'] = $settings['show_profile_buttons'] && (!empty($this_member['can_view_profile']) || (!empty($this_member['website']['url']) && !isset($context['disabled_fields']['website'])) || (in_array($this_member['show_email'], array('yes', 'yes_permission_override', 'no_through_forum'))) || $context['can_send_pm']);

		$context['id_msg'] = $this->_this_message['id_msg'] ?? '';
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _buildOutputArray()
	{
		global $topic, $context, $modSettings, $txt;

		require_once(SUBSDIR . '/Attachments.subs.php');

		$output = parent::_buildOutputArray();
		$href = getUrl('topic', ['topic' => $topic, 'start' => 'msg' . $this->_this_message['id_msg'], 'subject' => $this->_this_message['subject']]) . '#msg' . $this->_this_message['id_msg'];
		$output += array(
			'attachment' => loadAttachmentContext($this->_this_message['id_msg']),
			'href' => $href,
			'link' => '<a href="' . $href . '" rel="nofollow">' . $this->_this_message['subject'] . '</a>',
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
			'is_message_author' => $this->_this_message['id_member'] == $this->user->id,
			'can_approve' => !$this->_this_message['approved'] && $context['can_approve'],
			'can_unapprove' => !empty($modSettings['postmod_active']) && $context['can_approve'] && $this->_this_message['approved'],
			'can_modify' => (!$context['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $context['user']['started']) || (allowedTo('modify_own') && $this->_this_message['id_member'] == $this->user->id && (empty($modSettings['edit_disable_time']) || !$this->_this_message['approved'] || $this->_this_message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time()))),
			'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']) || (allowedTo('delete_own') && $this->_this_message['id_member'] == $this->user->id && (empty($modSettings['edit_disable_time']) || $this->_this_message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
			'can_like' => $this->_this_message['use_likes'] && !$this->_this_message['you_liked'],
			'can_unlike' => $this->_this_message['use_likes'] && $this->_this_message['you_liked'],
			'like_counter' => $this->_this_message['like_count'],
			'likes_enabled' => !empty($modSettings['likes_enabled']) && ($this->_this_message['use_likes'] || ($this->_this_message['like_count'] != 0)),
			'classes' => array(),
		);

		if (!empty($output['modified']['name']))
		{
			$output['modified']['last_edit_text'] = sprintf($txt['last_edit_by'], $output['modified']['time'], $output['modified']['name'], standardTime($output['modified']['timestamp']));
		}

		if (!empty($output['member']['karma']['allow']))
		{
			$output['member']['karma'] += array(
				'applaud_url' => getUrl('action', ['action' => 'karma', 'sa' => 'applaud', 'uid' => $output['member']['id'], 'topic' => $context['current_topic'] . '.' . $context['start'], 'm' => $output['id'], '{session_data}']),
				'smite_url' => getUrl('action', ['action' => 'karma', 'sa' => 'smite', 'uid' => $output['member']['id'], 'topic' => $context['current_topic'] . '.' . $context['start'], 'm' => $output['id'], '{session_data}'])
			);
		}

		return $output;
	}
}
