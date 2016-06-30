<?php

/**
 * Primary site dispatch controller, sends the request to the function or method
 * registered to handle it.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 1
 *
 */

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
	 * Every time we don't know what to do, we'll do this :P
	 *
	 * @var string[]
	 */
	protected $_default_action = array(
		'controller' => 'BoardIndex_Controller',
		'function' => 'action_boardindex'
	);

	/**
	 * The instance of the controller
	 * @var Action_Controller
	 */
	protected $_controller;

	/**
	 * @var string
	 */
	protected $action;

	/**
	 * @var string
	 */
	protected $area;

	/**
	 * @var string
	 */
	protected $subAction;

	/**
	 * @return string[]
	 */
	protected function getFrontPage()
	{
		global $modSettings;

		if (
			!empty($modSettings['front_page'])
			&& is_callable(array($modSettings['front_page'], 'frontPageHook'))
			&& is_callable(array($modSettings['front_page'], 'canFrontPage'))
			&& call_user_func(array($modSettings['front_page'], 'canFrontPage'))
		) {
			call_user_func(array($modSettings['front_page'], 'frontPageHook'), &$this->_default_action);
		}
		return $this->_default_action;
	}

	/**
	 * Determine if guest access is restricted, and, if so,
	 * only allow the listed actions
	 *
	 * @return boolean
	 */
	protected function restrictedGuestAccess()
	{
		global $modSettings, $user_info;

		return
			empty($modSettings['allow_guestAccess'])
			&& $user_info['is_guest']
			&& (!in_array($this->action, array(
				'login', 'login2', 'register', 'reminder',
				'help', 'quickhelp', 'mailq', 'openidreturn'
			);
	}

	/**
	 * Create an instance and initialize it.
	 *
	 * This does all the work to figure out which controller and method need
	 * to be called.
	 *
	 * @param HttpReq $_req
	 */
	public function __construct(HttpReq $_req)
	{
		global $modSettings;

		$this->_noActionActions($action, !empty($modSettings['allow_guestAccess']));
		// A safer way to work with our form globals
		$_req = HttpReq::instance();
		$this->action = $_req->getQuery('action', 'trim|strval', '');
		$this->area = $_req->getQuery('area', 'trim|strval', '');
		$this->subAction = $_req->getQuery('sa', 'trim|strval', '');
		$this->_default_action = $this->getFrontPage();

		// Now this return won't be cool, but lets do it
		if (empty($this->_controller_name))
		{
			$this->_namingPatterns($action, $subaction, $area);
		}

		// Initialize this controller with its event manager
		$this->_controller = new $this->_controller_name(new Event_Manager());
	}

	/**
	 * Finds out if the current action is one of those without an "action"
	 * parameter in the URL
	 *
	 * @param string $action
	 * @param bool $allow_guestAccess
	 */
	protected function _noActionActions($action, $allow_guestAccess = true)
	{
		global $maintenance, $user_info, $board, $topic;

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login
			if ($action == 'login2' || $action == 'logout')
			{
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = $action == 'login2' ? 'action_login2' : 'action_logout';
			}
			// "maintenance mode" page
			else
			{
				$this->_controller_name = 'Auth_Controller';
				$this->_function_name = 'action_maintenance_mode';
			}
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif ($this->restrictedGuestAccess())
		{
			$this->_controller_name = 'Auth_Controller';
			$this->_function_name = 'action_kickguest';
		}
		elseif (empty($action))
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
	}

	/**
	 * Compares the $_GET['action'] with array or naming patterns to find
	 * a suitable controller.
	 *
	 * @param string $action
	 * @param string $subaction
	 * @param string $area
	 */
	protected function _namingPatterns($action, $subaction, $area)
	{
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
			'keepalive' => array('Auth_Controller', 'action_keepalive'),
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

		// Allow to extend or change $actionArray through a hook
		call_integration_hook('integrate_actions', array(&$actionArray));

		// Is it in core legacy actions?
		if (isset($actionArray[$action]))
		{
			$this->_controller_name = $actionArray[$action][0];

			// If the method is coded in, use it
			if (!empty($actionArray[$action][1]))
				$this->_function_name = $actionArray[$action][1];
			// Otherwise fall back to naming patterns
			elseif (!empty($subaction) && preg_match('~^\w+$~', $subaction))
				$this->_function_name = 'action_' . $subaction;
			else
				$this->_function_name = 'action_index';
		}
		// Fall back to naming patterns.
		// addons can use any of them, and it should Just Work (tm).
		elseif (preg_match('~^[a-zA-Z_\\-]+\d*$~', $action))
		{
			// action=gallery => Gallery.controller.php
			// sa=upload => action_upload()
			$this->_controller_name = ucfirst($action) . '_Controller';
			if (isset($subaction) && preg_match('~^\w+$~', $subaction) && empty($area))
				$this->_function_name = 'action_' . $subaction;
			else
				$this->_function_name = 'action_index';
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

		// 3, 2, ... and go
		if (is_callable(array($this->_controller_name, $this->_function_name)))
		{
			return;
		}
		elseif (is_callable(array($this->_controller_name, 'action_index')))
		{
			$this->_function_name = 'action_index';
		}
		// This should never happen, that's why its here :P
		else
		{
			$this->_controller_name = $this->_default_action['controller'];
			$this->_function_name = $this->_default_action['function'];
		}
	}

	/**
	 * Backward compatibility function.
	 * Determine the current action from $_GET
	 * @deprecated since 1.1
	 */
	protected function _getAction()
	{
		return isset($_GET['action']) ? $_GET['action'] : '';
	}

	/**
	 * Backward compatibility function.
	 * Determine the current subaction from $_GET
	 * @deprecated since 1.1
	 */
	protected function _getSubAction()
	{
		return isset($_GET['sa']) ? $_GET['sa'] : '';
	}

	/**
	 * Backward compatibility function.
	 * Determine the current area from $_GET
	 * @deprecated since 1.1
	 */
	protected function _getArea()
	{
		return isset($_GET['area']) ? $_GET['area'] : '';
	}

	/**
	 * Relay control to the respective function or method.
	 */
	public function dispatch()
	{
		// Fetch controllers generic hook name from the action controller
		$hook = $this->_controller->getHook();

		// Call the controllers pre dispatch method
		$this->_controller->pre_dispatch();

		// Call integrate_action_XYZ_before -> XYZ_controller -> integrate_action_XYZ_after
		call_integration_hook('integrate_action_' . $hook . '_before', array($this->_function_name));

		$result = $this->_controller->{$this->_function_name}();

		call_integration_hook('integrate_action_' . $hook . '_after', array($this->_function_name));

		return $result;
	}

	/**
	 * If the current controller needs to load all the security framework.
	 */
	public function needSecurity()
	{
		return $this->_controller->needSecurity($this->_function_name);
	}

	/**
	 * If the current controller needs to load the theme.
	 */
	public function needTheme()
	{
		return $this->_controller->needTheme($this->_function_name);
	}

	/**
	 * If the current controller wants to track access and stats.
	 */
	public function trackStats()
	{
		return $this->_controller->trackStats($this->_function_name);
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
			$action = strtolower(str_replace('_Controller', '', $this->_controller_name));
			$action = substr($action, -1) == 2 ? substr($action, 0, -1) : $action;
		}

		return isset($action) ? $action : '';
	}
}