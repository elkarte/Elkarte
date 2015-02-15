<?php

/**
 * This just provides a common way to replace the mention message
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

abstract class Mention_Message_Abstract implements Mention_Type_Interface
{
	protected $_type = '';

	/**
	 * This static function is used to find the events to attach to a controller.
	 * The implementation of this abstract class is empty because it's
	 * just a dummy to cover mentions that don't need to register anything.
	 *
	 * @param string $controller The name of the controller initializing the system
	 */
	public static function getEvents($controller)
	{
		return array();
	}

	public abstract function view($type, &$mentions);

	protected function _replaceMsg($row)
	{
		global $txt, $scripturl, $context;

		return str_replace(
			array(
				'{msg_link}',
				'{msg_url}',
				'{subject}',
			),
			array(
				'<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_target'] . ';mentionread;mark=read;' . $context['session_var'] . '=' . $context['session_id'] . ';item=' . $row['id_mention'] . '#msg' . $row['id_target'] . '">' . $row['subject'] . '</a>',
				$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_target'] . ';mentionread;' . $context['session_var'] . '=' . $context['session_id'] . 'item=' . $row['id_mention'] . '#msg' . $row['id_target'],
				$row['subject'],
			),
			$txt['mention_' . $row['mention_type']]);
	}
}