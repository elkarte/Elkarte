<?php

/**
 * Common methods shared by any type of mention so far.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType;

use ElkArte\HttpReq;
use ElkArte\UserInfo;

/**
 * Class AbstractEventMessage
 */
abstract class AbstractEventMessage implements EventInterface
{
	/** @var string The identifier of the mention (the name that is stored in the db) */
	protected static $_type = '';

	/** @var \ElkArte\HttpReq The post/get object */
	protected $_request;

	/** @var \ElkArte\ValuesContainer The current user object */
	protected $user;

	/**
	 * AbstractEventMessage constructor
	 *
	 * @param \ElkArte\HttpReq $http_req
	 * @param \ElkArte\UserInfo $user
	 */
	public function __construct(HttpReq $http_req, UserInfo $user)
	{
		$this->_request = $http_req;
		$this->user = $user;
	}

	/**
	 * This static function is used to find the events to attach to a controller.
	 * The implementation of this abstract class is empty because it's
	 * just a dummy to cover mentions that don't need to register anything.
	 *
	 * @param string $controller The name of the controller initializing the system
	 *
	 * @return array
	 */
	public static function getEvents($controller)
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getModules($modules)
	{
		return $modules;
	}

	/**
	 * Does the replacement of some placeholders with the corresponding
	 * text/link/url.
	 *
	 * @param string[] $row A text string on which replacements are done
	 * @return string the input string with the placeholders replaced
	 */
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
