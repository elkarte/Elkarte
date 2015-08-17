<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

class Poll_Display_Module implements ElkArte\sources\modules\Module_Interface
{
	protected static $_enabled = false;

	protected $_id_poll = 0;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $modSettings;

		$return = array(
			array('topicinfo', array('Poll_Display_Module', 'topicinfo'), array('topicinfo')),
		);
		self::$_enabled = !empty($modSettings['pollMode']);
		if (self::$_enabled && allowedTo('poll_view'))
		{
			$return[] = array('prepare_context', array('Poll_Display_Module', 'prepare_context'), array('template_layers'));
		}

		return $return;
	}

	public function topicinfo($topicinfo)
	{
		global $context;

		// @deprecated since 1.1 - $context['is_poll'] is not used anywhere.
		$context['is_poll'] = $topicinfo['id_poll'] > 0 && self::$_enabled && allowedTo('poll_view');

		$this->_id_poll = $topicinfo['id_poll'];

		$anyown_permissions = array(
			'can_add_poll' => 'poll_add',
			'can_remove_poll' => 'poll_remove',
		);
		foreach ($anyown_permissions as $contextual => $perm)
			$context[$contextual] = allowedTo($perm . '_any') || ($context['user']['started'] && allowedTo($perm . '_own'));

		$context['can_add_poll'] &= self::$_enabled && $topicinfo['id_poll'] <= 0;
		$context['can_remove_poll'] &= self::$_enabled && $topicinfo['id_poll'] > 0;
	}

	public function prepare_context($template_layers)
	{
		global $context, $scripturl, $txt;

		// Create the poll info if it exists.
		if ($context['is_poll'])
		{
			$template_layers->add('display_poll');
			require_once(SUBSDIR . '/Poll.subs.php');

			loadPollContext($this->_id_poll);

			// Build the poll moderation button array.
			$context['poll_buttons'] = array(
				'vote' => array('test' => 'allow_return_vote', 'text' => 'poll_return_vote', 'image' => 'poll_options.png', 'lang' => true, 'url' => $scripturl . '?topic=' . $context['current_topic'] . '.' . $context['start']),
				'results' => array('test' => 'allow_poll_view', 'text' => 'poll_results', 'image' => 'poll_results.png', 'lang' => true, 'url' => $scripturl . '?topic=' . $context['current_topic'] . '.' . $context['start'] . ';viewresults'),
				'change_vote' => array('test' => 'allow_change_vote', 'text' => 'poll_change_vote', 'image' => 'poll_change_vote.png', 'lang' => true, 'url' => $scripturl . '?action=poll;sa=vote;topic=' . $context['current_topic'] . '.' . $context['start'] . ';poll=' . $context['poll']['id'] . ';' . $context['session_var'] . '=' . $context['session_id']),
				'lock' => array('test' => 'allow_lock_poll', 'text' => (!$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'), 'image' => 'poll_lock.png', 'lang' => true, 'url' => $scripturl . '?action=lockvoting;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
				'edit' => array('test' => 'allow_edit_poll', 'text' => 'poll_edit', 'image' => 'poll_edit.png', 'lang' => true, 'url' => $scripturl . '?action=editpoll;topic=' . $context['current_topic'] . '.' . $context['start']),
				'remove_poll' => array('test' => 'can_remove_poll', 'text' => 'poll_remove', 'image' => 'admin_remove_poll.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . $txt['poll_remove_warn'] . '\');"', 'url' => $scripturl . '?action=poll;sa=remove;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			);

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_poll_buttons', array(&$context['poll_buttons']));
		}
	}
}