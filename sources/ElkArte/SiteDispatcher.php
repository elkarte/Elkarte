<?php

/**
 * Primary site dispatch controller, sends the request to the function or method
 * registered to handle it.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Dispatch the request to the function or method registered to handle it.
 *
 * What it does:
 *
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
class SiteDispatcher
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
		'controller' => '\\ElkArte\\Controller\\BoardIndex',
		'function' => 'action_boardindex'
	);

	/**
	 * The instance of the controller
	 * @var \ElkArte\AbstractController
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
	 * Build our nice and cozy err... *cough*
	 *
	 * @var string[]
	 */
	protected $actionArray = array(
		'admin' => array('\\ElkArte\\AdminController\\Admin', 'action_index'),
		'attachapprove' => array('\\ElkArte\\Controller\\ModerateAttachments', 'action_attachapprove'),
		'buddy' => array('\\ElkArte\\Controller\\Members', 'action_buddy'),
		'collapse' => array('\\ElkArte\\Controller\\BoardIndex', 'action_collapse'),
		'deletemsg' => array('\\ElkArte\\Controller\\RemoveTopic', 'action_deletemsg'),
		'dlattach' => array('\\ElkArte\\Controller\\Attachment', 'action_index'),
		'unwatchtopic' => array('\\ElkArte\\Controller\\Notify', 'action_unwatchtopic'),
		'editpoll' => array('\\ElkArte\\Controller\\Poll', 'action_editpoll'),
		'editpoll2' => array('\\ElkArte\\Controller\\Poll', 'action_editpoll2'),
		'forum' => array('\\ElkArte\\Controller\\BoardIndex', 'action_index'),
		'quickhelp' => array('\\ElkArte\\Controller\\Help', 'action_quickhelp'),
		'jsmodify' => array('\\ElkArte\\Controller\\Post', 'action_jsmodify'),
		'jsoption' => array('\\ElkArte\\AdminController\\ManageThemes', 'action_jsoption'),
		'keepalive' => array('\\ElkArte\\Controller\\Auth', 'action_keepalive'),
		'lockvoting' => array('\\ElkArte\\Controller\\Poll', 'action_lockvoting'),
		'login' => array('\\ElkArte\\Controller\\Auth', 'action_login'),
		'login2' => array('\\ElkArte\\Controller\\Auth', 'action_login2'),
		'logout' => array('\\ElkArte\\Controller\\Auth', 'action_logout'),
		'markasread' => array('\\ElkArte\\Controller\\MarkRead', 'action_index'),
		'mergetopics' => array('\\ElkArte\\Controller\\MergeTopics', 'action_index'),
		'moderate' => array('\\ElkArte\\Controller\\ModerationCenter', 'action_index'),
		'movetopic' => array('\\ElkArte\\Controller\\MoveTopic', 'action_movetopic'),
		'movetopic2' => array('\\ElkArte\\Controller\\MoveTopic', 'action_movetopic2'),
		'notify' => array('\\ElkArte\\Controller\\Notify', 'action_notify'),
		'notifyboard' => array('\\ElkArte\\Controller\\Notify', 'action_notifyboard'),
		'openidreturn' => array('\\ElkArte\\Controller\\OpenID', 'action_openidreturn'),
		'xrds' => array('\\ElkArte\\Controller\\OpenID', 'action_xrds'),
		'pm' => array('\\ElkArte\\Controller\\PersonalMessage', 'action_index'),
		'post2' => array('\\ElkArte\\Controller\\Post', 'action_post2'),
		'quotefast' => array('\\ElkArte\\Controller\\Post', 'action_quotefast'),
		'quickmod' => array('\\ElkArte\\Controller\\MessageIndex', 'action_quickmod'),
		'quickmod2' => array('\\ElkArte\\Controller\\Display', 'action_quickmod2'),
		'removetopic2' => array('\\ElkArte\\Controller\\RemoveTopic', 'action_removetopic2'),
		'reporttm' => array('\\ElkArte\\Controller\\Emailuser', 'action_reporttm'),
		'restoretopic' => array('\\ElkArte\\Controller\\RemoveTopic', 'action_restoretopic'),
		'splittopics' => array('\\ElkArte\\Controller\\SplitTopics', 'action_splittopics'),
		'theme' => array('\\ElkArte\\AdminController\\ManageThemes', 'action_thememain'),
		'trackip' => array('\\ElkArte\\Controller\\ProfileHistory', 'action_trackip'),
		'unreadreplies' => array('\\ElkArte\\Controller\\Unread', 'action_unreadreplies'),
		'viewprofile' => array('\\ElkArte\\Controller\\Profile', 'action_index'),
		'viewquery' => array('\\ElkArte\\AdminController\\AdminDebug', 'action_viewquery'),
		'.xml' => array('\\ElkArte\\Controller\\News', 'action_showfeed'),
		'xmlhttp' => array('\\ElkArte\\Controller\\Xml', 'action_index'),
		'xmlpreview' => array('\\ElkArte\\Controller\\XmlPreview', 'action_index'),
	);

	/**
	 * @return string[]
	 */
	protected function getFrontPage()
	{
		global $modSettings;

		if (
			!empty($modSettings['front_page'])
			&& is_callable(array($modSettings['front_page'], 'frontPageHook'))
		) {
			$modSettings['default_forum_action'] = ['action' => 'forum'];
			call_user_func_array(array($modSettings['front_page'], 'frontPageHook'), array(&$this->_default_action));
		}
		else
		{
			$modSettings['default_forum_action'] = [];
		}
		return $this->_default_action;
	}

	/**
	 * Passes the \ElkArte\User::$info variable to the controller
	 * @param \ElkArte\ValuesContainer $user
	 */
	public function setUser($user)
	{
		$this->_controller->setUser($user);
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
			&& !in_array($this->action, array(
				'login', 'login2', 'register', 'reminder',
				'help', 'quickhelp', 'mailq', 'openidreturn'
			));
	}

	/**
	 * Create an instance and initialize it.
	 *
	 * This does all the work to figure out which controller and method need
	 * to be called.
	 *
	 * @param \ElkArte\HttpReq $_req
	 */
	public function __construct(HttpReq $_req)
	{
		global $context;

		$context['current_action'] = $this->action = $_req->getQuery('action', 'trim|strval', '');
		$this->area = $_req->getQuery('area', 'trim|strval', '');
		$context['current_subaction'] = $this->subAction = $_req->getQuery('sa', 'trim|strval', '');
		$this->_default_action = $this->getFrontPage();
		$this->determineDefaultAction();

		if (empty($this->_controller_name))
		{
			$this->determineAction();
		}

		// Initialize this controller with its event manager
		$this->_controller = new $this->_controller_name(new EventManager());
	}

	/**
	 * Finds out if the current action is one of those without
	 * an "action" parameter in the URL
	 */
	protected function determineDefaultAction()
	{
		global $board, $topic;

		if (empty($this->action))
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
				$this->_controller_name = '\\ElkArte\\Controller\\MessageIndex';
				$this->_function_name = 'action_messageindex';
			}
			// board=b;topic=t topic display
			else
			{
				$this->_controller_name = '\\ElkArte\\Controller\\Display';
				$this->_function_name = 'action_display';
			}
		}
	}

	/**
	 * Compares the $_GET['action'] with array or naming patterns to find
	 * a suitable controller.
	 *
	 * @event integrate_actions Allows to extend or change $actionArray, allowed actions,
	 * through the hook
	 */
	protected function determineAction()
	{
		// Allow to extend or change $actionArray through a hook
		// Format:
		// $_GET['action'] => array($class, $method)
		call_integration_hook('integrate_actions', array(&$this->actionArray));

		// Is it in the action list?
		if (isset($this->actionArray[$this->action]))
		{
			$this->_controller_name = $this->actionArray[$this->action][0];

			// If the method is coded in, use it
			if (!empty($this->actionArray[$this->action][1]))
				$this->_function_name = $this->actionArray[$this->action][1];
			// Otherwise fall back to naming patterns
			elseif (!empty($this->subAction) && preg_match('~^\w+$~', $this->subAction))
				$this->_function_name = 'action_' . $this->subAction;
			else
				$this->_function_name = 'action_index';
		}
		// Fall back to naming patterns.
		// addons can use any of them, and it should Just Work (tm).
		elseif (preg_match('~^[a-zA-Z_\\-]+\d*$~', $this->action))
		{
			// action=gallery => Gallery.controller.php
			// sa=upload => action_upload()
			$this->_controller_name = '\\ElkArte\\Controller\\' . ucfirst($this->action);
			if (isset($this->subAction) && preg_match('~^\w+$~', $this->subAction) && empty($this->area))
				$this->_function_name = 'action_' . $this->subAction;
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
	 * Relay control to the respective function or method.
	 *
	 * What it does:
	 *
	 * - Calls generic pre (_before) hook based on the controllers class name.
	 *   - e.g. integrate_action_draft_before will be called before \ElkArte\Controller\Draft
	 * - Calls the controllers pre_dispatch method
	 * - Calls the controllers selected method
	 * - Calls generic post (_after) hook based on the controllers class name.
	 *   - e.g. integrate_action_draft_after will be called after \ElkArte\Controller\Draft
	 *
	 * @event integrate_action_xyz_before
	 * @event integrate_action_xyz_after
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
		global $maintenance;

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only login
			if ($this->action == 'login2' || $this->action == 'logout')
			{
				$this->_controller_name = '\\ElkArte\\Controller\\Auth';
				$this->_function_name = 'action_' . $this->action;
			}
			// "maintenance mode" page
			else
			{
				$this->_controller_name = '\\ElkArte\\Controller\\Auth';
				$this->_function_name = 'action_maintenance_mode';
			}

			// re-initialize the controller and the event manager
			$this->_controller = new $this->_controller_name(new EventManager());
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif ($this->restrictedGuestAccess())
		{
			$this->_controller_name = '\\ElkArte\\Controller\\Auth';
			$this->_function_name = 'action_kickguest';

			// re-initialize... you got the drift
			$this->_controller = new $this->_controller_name(new EventManager());
		}

		return $this->_controller->needTheme($this->_function_name);
	}

	/**
	 * If the current controller wants to track access and stats.
	 */
	public function trackStats($action = '')
	{
		return $this->_controller->trackStats($this->_function_name);
	}

	/**
	 * @return \ElkArte\AbstractController
	 */
	public function getController()
	{
		return $this->_controller;
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
			$action = strtolower(str_replace('ElkArte\\Controller\\', '', trim($this->_controller_name, '\\')));
			$action = substr($action, -1) == 2 ? substr($action, 0, -1) : $action;
		}

		return isset($action) ? $action : $this->action;
	}
}
