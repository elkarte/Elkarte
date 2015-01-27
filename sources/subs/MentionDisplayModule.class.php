<?php

/**
 * This file contains the integration of mentions into Display_Controller.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

class Mention_Display_Module
{
	protected static $_enabled = false;
	protected $_actually_mentioned = array();

	public static function hooks()
	{
		global $modSettings, $context;

		// Posting an event?
		self::$_enabled = !empty($modSettings['mentions_enabled']);

		if (self::$_enabled)
		{
			$context['mentions_enabled'] = true;

			return array(
				array('prepare_context', array('Mention_Post_Module', 'prepare_context'), array('virtual_msg')),
			);
		}
		else
			return array();
	}

	public function prepare_context($virtual_msg)
	{
		global $options;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$loader = new Controller_Loader('Mentions');
			$mentions = $loader->initDispatch();
			$mentions->setData(array(
				'id_mention' => $_REQUEST['item'],
				'mark' => $_REQUEST['mark'],
			));
			$mentions->action_markread();
		}

		// Just using the plain text quick reply and not the editor
		if (empty($options['use_editor_quick_reply']))
			loadJavascriptFile(array('jquery.atwho.js', 'jquery.caret.min.js', 'mentioning.js'));

		loadCSSFile('jquery.atwho.css');

		addInlineJavascript('
		$(document).ready(function () {
			for (var i = 0, count = all_elk_mentions.length; i < count; i++)
				all_elk_mentions[i].oMention = new elk_mentions(all_elk_mentions[i].oOptions);
		});');
	}
}
