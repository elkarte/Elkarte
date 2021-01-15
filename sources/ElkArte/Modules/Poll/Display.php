<?php

/**
 * This file contains several functions for display polls and polling buttons.
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

use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * Class Poll_Display_Module
 */
class Display extends AbstractModule
{
	/**
	 * If polls are enabled
	 *
	 * @var bool
	 */
	protected static $_enabled = false;

	/**
	 * Poll id to work with
	 *
	 * @var int
	 */
	protected $_id_poll = 0;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		$return = array(
			array('topicinfo', array('\\ElkArte\\Modules\\Poll\\Display', 'topicinfo'), array('topicinfo')),
		);
		self::$_enabled = !empty($modSettings['pollMode']);
		if (self::$_enabled && allowedTo('poll_view'))
		{
			$return[] = array('prepare_context', array('\\ElkArte\\Modules\\Poll\\Display', 'prepare_context'), array('template_layers'));
		}

		return $return;
	}

	/**
	 * Add add/edit poll "permissions" to context
	 *
	 * @param array $topicinfo
	 */
	public function topicinfo($topicinfo)
	{
		global $context;

		$this->_id_poll = $topicinfo['id_poll'];

		$anyown_permissions = array(
			'can_add_poll' => 'poll_add',
			'can_remove_poll' => 'poll_remove',
		);
		foreach ($anyown_permissions as $contextual => $perm)
		{
			$context[$contextual] = allowedTo($perm . '_any') || ($context['user']['started'] && allowedTo($perm . '_own'));
		}

		$context['can_add_poll'] &= self::$_enabled && $topicinfo['id_poll'] <= 0;
		$context['can_remove_poll'] &= self::$_enabled && $topicinfo['id_poll'] > 0;
	}

	/**
	 * Prepare context to display the poll itself and the appropriate poll buttons
	 *
	 * @param \ElkArte\Themes\TemplateLayers $template_layers
	 */
	public function prepare_context($template_layers)
	{
		global $context, $txt;

		// Create the poll info if it exists.
		if ($this->_id_poll > 0)
		{
			$template_layers->add('display_poll');
			require_once(SUBSDIR . '/Poll.subs.php');

			loadPollContext($this->_id_poll);

			// Build the poll moderation button array.
			$context['poll_buttons'] = [
				'vote' => [
					'test' => 'allow_return_vote',
					'text' => 'poll_return_vote',
					'image' => 'poll_options.png',
					'lang' => true,
					'url' => getUrl('action', ['topic' => $context['current_topic'] . '.' . $context['start']])
				],
				'results' => [
					'test' => 'allow_poll_view',
					'text' => 'poll_results',
					'image' => 'poll_results.png',
					'lang' => true,
					'url' => getUrl('action', ['topic' => $context['current_topic'] . '.' . $context['start'] . ';viewresults'])
				],
				'change_vote' => [
					'test' => 'allow_change_vote',
					'text' => 'poll_change_vote',
					'image' => 'poll_change_vote.png',
					'lang' => true,
					'url' => getUrl('action', ['action' => 'poll', 'sa' => 'vote', 'topic' => $context['current_topic'] . '.' . $context['start'], 'poll' => $context['poll']['id'], '{session_data}'])
				],
				'lock' => [
					'test' => 'allow_lock_poll',
					'text' => (!$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'),
					'image' => 'poll_lock.png',
					'lang' => true,
					'url' => getUrl('action', ['action' => 'lockvoting', 'topic' => $context['current_topic'] . '.' . $context['start'], '{session_data}'])
				],
				'edit' => [
					'test' => 'allow_edit_poll',
					'text' => 'poll_edit',
					'image' => 'poll_edit.png',
					'lang' => true,
					'url' => getUrl('action', ['action' => 'editpoll', 'topic' => $context['current_topic'] . '.' . $context['start']])
				],
				'remove_poll' => [
					'test' => 'can_remove_poll',
					'text' => 'poll_remove',
					'image' => 'admin_remove_poll.png',
					'lang' => true,
					'custom' => 'onclick="return confirm(\'' . $txt['poll_remove_warn'] . '\');"',
					'url' => getUrl('action', ['action' => 'poll', 'sa' => 'remove', 'topic' => $context['current_topic'] . '.' . $context['start'] . '{session_data}'])
				],
			];

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_poll_buttons', array(&$context['poll_buttons']));
		}
	}
}
