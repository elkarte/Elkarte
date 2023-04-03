<?php

/**
 * Integration system for attachments into Diplay controller
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

namespace ElkArte\Modules\Attachments;

use ElkArte\AttachmentsDisplay;
use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Attachments\Display
 */
class Display extends AbstractModule
{
	/** @var int The mode of attachments (disabled/enabled/show only). */
	protected static $attach_level = 0;

	/** @var The good old attachments array */
	protected static $attachments = null;

	/** @var bool If unapproved posts/attachments should be shown */
	protected static $includeUnapproved = false;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['attachmentEnable']))
		{
			require_once(SUBSDIR . '/Attachments.subs.php');

			self::$attach_level = (int) $modSettings['attachmentEnable'];
			self::$includeUnapproved = !$modSettings['postmod_active'] || allowedTo('approve_posts');

			add_integration_function('integrate_display_message_list', '\\ElkArte\\Modules\\Attachments\\Display::integrate_display_message_list', '', false);
			add_integration_function('integrate_prepare_display_context', '\\ElkArte\\Modules\\Attachments\\Display::integrate_prepare_display_context', '', false);
// 			return array(
// 				array('prepare_context', array('\\ElkArte\\Modules\\Attachments\\Display', 'prepare_context'), array('post_errors')),
// 			);
		}

		return array();
	}

	/**
	 * Loads up all the attachments.  Called from integrate_display_message_list
	 */
	public static function integrate_display_message_list(&$messages, &$posters)
	{
		self::$attachments = new AttachmentsDisplay($messages, $posters, self::$includeUnapproved);
	}

	/**
	 * Shows the attachments for the current message
	 */
	public static function integrate_prepare_display_context(&$output, &$this_message, $counter)
	{
		[$output['attachment'], $output['ila']] = self::$attachments->loadAttachmentContext($this_message['id_msg']);
	}
}
