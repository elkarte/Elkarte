<?php

/**
 * Primary site dispatch controller, sends the request to the function or method
 * registered to handle it.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Dispatch the request to the function or method registered to handle it.
 *
 * What it does:
 * - Try first the critical functionality (maintenance, no guest access)
 * - Then, in order:
 *     * forum's main actions: board index, message index, display topic
 *       the current/legacy file/functions registered by ElkArte core
 * - Fall back to naming patterns:
 *     * filename=[action].php function=[sa]
 *     * filename=[action].controller.php method=action_[sa]
 *     * filename=[action]-Controller.php method=action_[sa]
 * - An addon files to handle custom actions will be called if they follow
 * any of these patterns.
 */
class Site_Dispatcher
{
	/**
	 * Function or method to call
	 * @var string
	 */
	protected $_function_name;

	/**
	 * Class name, for object oriented controllers
	 * @var string
	 */
	protected $_controller_name;

	/**
	 * The default action data (controller and function)
	 * @var string[]
	 */
	protected $_default_action;

	/**
	 * Create an instance and initialize it.
	 *
	 * This does all the work to figure out which controller and method need
	 * to be called.
	 */
	public function __construct()
	{
		global $board, $topic, $modSettings, $user_info, $maintenance;

		// Default action of the forum: board index
		// Every time we don't know what to do, we'll do this :P
		$this->_default_action = array(
			'controller' => 'BoardIndex_Controller',
			'function' => 'action_boardindex'
		);

		// Reminder: hooks need to account for multiple addons setting this hook.
		call_integration_hook('integrate_action_frontpage', array(&$this->_default_action));

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login
			if (isset($_GET['action']) && ($_GET['action'] == 'login2' || $_GET['action'] == 'logout'))
			{
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = $_GET['action'] == 'login2' ? 'action_login2' : 'action_logout';
			}
			// "maintenance mode" page
			else
			{
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = 'action_maintenance_mode';
			}
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (!isset($_GET['action']) || !in_array($_GET['action'], array('login', 'login2', 'register', 'reminder', 'help', 'quickhelp', 'mailq', 'openidreturn'))))
		{
			$this->_controller_name = 'Auth_Controller';
			$this->_function_name = 'action_kickguest';
		}
		elseif (empty($_GET['action']))
		{
			// Home page: board index
			if (empty($board) && empty($topic))
			{
				// Was it, wasn't it....
				if (empty($this->_function_name))
				{
					$this->_controller_name = $this->_default_action['controller'];
					$this->_function_name = $this->_default_action['function'];
				}
			}
			// ?board=b message index
			elseif (empty($topic))
			{
				$this->_controller_name = 'MessageIndex_Controller';
				$this->_function_name = 'action_messageindex';
			}
			// board=b;topic=t topic display
			else
			{
				$this->_controller_name = 'Display_Controller';
				$this->_function_name = 'action_display';
			}
		}

		// Now this return won't be cool, but lets do it
		if (!empty($this->_controller_name) && !empty($this->_function_name))
			return;

		// Start with our nice and cozy err... *cough*
		// Format:
		// $_GET['action'] => array($class, $method)
		$actionArray = array(
			'attachapprove' => array('ModerateAttachments_Controller', 'action_attachapprove'),
			'buddy' => array('Members_Controller', 'action_buddy'),
			'collapse' => array('BoardIndex_Controller', 'action_collapse'),
			'deletemsg' => array('RemoveTopic_Controller', 'action_deletemsg'),
			// @todo: move this to attachment action also
			'dlattach' => array('Attachment_Controller', 'action_index'),
			'unwatchtopic' => array('Notify_Controller', 'action_unwatchtopic'),
			'editpoll' => array('Poll_Controller', 'action_editpoll'),
			'editpoll2' => array('Poll_Controller', 'action_editpoll2'),
			'quickhelp' => array('Help_Controller', 'action_quickhelp'),
			'jsmodify' => array('Post_Controller', 'action_jsmodify'),
			'jsoption' => array('ManageThemes_Controller', 'action_jsoption'),
			'lockvoting' => array('Poll_Controller', 'action_lockvoting'),
			'login' => array('Auth_Controller', 'action_login'),
			'login2' => array('Auth_Controller', 'action_login2'),
			'logout' => array('Auth_Controller', 'action_logout'),
			'markasread' => array('MarkRead_Controller', 'action_index'),
			'mergetopics' => array('MergeTopics_Controller', 'action_index'),
			'moderate' => array('ModerationCenter_Controller', 'action_index'),
			'movetopic' => array('MoveTopic_Controller', 'action_movetopic'),
			'movetopic2' => array('MoveTopic_Controller', 'action_movetopic2'),
			'notify' => array('Notify_Controller', 'action_notify'),
			'notifyboard' => array('Notify_Controller', 'action_notifyboard'),
			'openidreturn' => array('OpenID_Controller', 'action_openidreturn'),
			'xrds' => array('OpenID_Controller', 'action_xrds'),
			'pm' => array('PersonalMessage_Controller', 'action_index'),
			'post2' => array('Post_Controller', 'action_post2'),
			'quotefast' => array('Post_Controller', 'action_quotefast'),
			'quickmod' => array('MessageIndex_Controller', 'action_quickmod'),
			'quickmod2' => array('Display_Controller', 'action_quickmod2'),
			'removetopic2' => array('RemoveTopic_Controller', 'action_removetopic2'),
			'reporttm' => array('Emailuser_Controller', 'action_reporttm'),
			'restoretopic' => array('RemoveTopic_Controller', 'action_restoretopic'),
			'spellcheck' => array('Post_Controller', 'action_spellcheck'),
			'splittopics' => array('SplitTopics_Controller', 'action_splittopics'),
			'theme' => array('ManageThemes_Controller', 'action_thememain'),
			'trackip' => array('ProfileHistory_Controller', 'action_trackip'),
			'unreadreplies' => array('Unread_Controller', 'action_unreadreplies'),
			'viewprofile' => array('Profile_Controller', 'action_index'),
			'viewquery' => array('AdminDebug_Controller', 'action_viewquery'),
			'viewadminfile' => array('AdminDebug_Controller', 'action_viewadminfile'),
			'.xml' => array('News_Controller', 'action_showfeed'),
			'xmlhttp' => array('Xml_Controller', 'action_index'),
			'xmlpreview' => array('XmlPreview_Controller', 'action_index'),
		);

		$adminActions = array('admin', 'jsoption', 'theme', 'viewadminfile', 'viewquery');

		// Allow to extend or change $actionArray through a hook
		call_integration_hook('integrate_actions', array(&$actionArray, &$adminActions));

		// Is it in core legacy actions?
		if (isset($actionArray[$_GET['action']]))
		{
			$this->_controller_name = $actionArray[$_GET['action']][0];

			// If the method is coded in, use it
			if (!empty($actionArray[$_GET['action']][1]))
				$this->_function_name = $actionArray[$_GET['action']][1];
			// Otherwise fall back to naming patterns
			elseif (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']))
				$this->_function_name = 'action_' . $_GET['sa'];
			else
				$this->_function_name = 'action_index';
		}
		// Fall back to naming patterns.
		// addons can use any of them, and it should Just Work (tm).
		elseif (preg_match('~^[a-zA-Z_\\-]+\d*$~', $_GET['action']))
		{
			// Admin files have their own place
			$path = in_array($_GET['action'], $adminActions) ? ADMINDIR : CONTROLLERDIR;

			// action=gallery => Gallery.controller.php
			// sa=upload => action_upload()
			if (file_exists($path . '/' . ucfirst($_GET['action']) . '.controller.php'))
			{
				$this->_controller_name = ucfirst($_GET['action']) . '_Controller';
				if (isset($_GET['sa']) && preg_match('~^\w+$~', $_GET['sa']) && !isset($_GET['area']))
					$this->_function_name = 'action_' . $_GET['sa'];
				else
					$this->_function_name = 'action_index';
			}
		}

		// The file and function weren't found yet?
		if (empty($this->_controller_name) || empty($this->_function_name))
		{
			// We still haven't found what we're looking for...
			$this->_controller_name = $this->_default_action['controller'];
			$this->_function_name = $this->_default_action['function'];
		}

		if (isset($_REQUEST['api']))
			$this->_function_name .= '_api';
	}

	/**
	 * Relay control to the respective function or method.
	 */
	public function dispatch()
	{
		if (!empty($this->_controller_name))
		{
			// 3, 2, ... and go
			if (is_callable(array($this->_controller_name, $this->_function_name)))
				$method = $this->_function_name;
			elseif (is_callable(array($this->_controller_name, 'action_index')))
				$method = 'action_index';
			// This should never happen, that's why its here :P
			else
			{
				$this->_controller_name = $this->_default_action['controller'];
				$this->_function_name = $this->_default_action['function'];

				return $this->dispatch();
			}

			// Initialize this controller with its own event manager
			$controller = new $this->_controller_name(new Event_Manager());

			// Fetch controllers generic hook name from the action controller
			$hook = $controller->getHook();

			// Call the controllers pre dispatch method
			$controller->pre_dispatch();

			// Call integrate_action_XYZ_before -> XYZ_controller -> integrate_action_XYZ_after
			call_integration_hook('integrate_action_' . $hook . '_before', array($this->_function_name));

			$result = $controller->$method();

			call_integration_hook('integrate_action_' . $hook . '_after', array($this->_function_name));

			return $result;
		}
		// Things went pretty bad, huh?
		else
		{
			// default action :P
			$this->_controller_name = $this->_default_action['controller'];
			$this->_function_name = $this->_default_action['function'];

			return $this->dispatch();
		}
	}

	/**
	 * Returns the current action for the system
	 *
	 * @return string
	 */
	public function site_action()
	{
		if (!empty($this->_controller_name))
		{
			$action  = strtolower(str_replace('_Controller', '', $this->_controller_name));
			$action = substr($action, -1) == 2 ? substr($action, 0, -1) : $action;
		}

		return isset($action) ? $action : '';
	}
}