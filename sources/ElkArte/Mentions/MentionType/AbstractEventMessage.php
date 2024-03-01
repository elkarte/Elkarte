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

use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;
use ElkArte\UserInfo;

/**
 * Class AbstractEventMessage
 */
abstract class AbstractEventMessage implements EventInterface
{
	/** @var string The identifier of the mention (the name that is stored in the db) */
	protected static $_type = '';

	/** @var HttpReq The post/get object */
	protected $_request;

	/** @var ValuesContainer The current user object */
	protected $user;

	/**
	 * AbstractEventMessage constructor
	 *
	 * @param HttpReq $http_req
	 * @param UserInfo $user
	 */
	public function __construct(HttpReq $http_req, UserInfo $user)
	{
		$this->_request = $http_req;
		$this->user = $user;
	}

	/**
	 * This static function is used to find the events to attach to a controller.
	 *
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
	 * {@inheritDoc}
	 */
	public static function getModules($modules)
	{
		return $modules;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function isNotAllowed($method)
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function canUse()
	{
		return true;
	}

	/**
	 * Does the replacement of some placeholders with the corresponding text/link/url.
	 *
	 * @param string[] $row A text string on which replacements are done
	 * @return string the input string with the placeholders replaced
	 */
	protected function _replaceMsg($row)
	{
		global $txt, $scripturl, $context;

		return str_replace(
			[
				'{msg_link}',
				'{msg_url}',
				'{subject}',
			],
			[
				'<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_target'] . ';mentionread;mark=read;' . $context['session_var'] . '=' . $context['session_id'] . ';item=' . $row['id_mention'] . '#msg' . $row['id_target'] . '">' . $row['subject'] . '</a>',
				$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_target'] . ';mentionread;' . $context['session_var'] . '=' . $context['session_id'] . 'item=' . $row['id_mention'] . '#msg' . $row['id_target'],
				$row['subject'],
			],
			$txt['mention_' . $row['mention_type']]);
	}
}
