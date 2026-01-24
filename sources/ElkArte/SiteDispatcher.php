<?php

/**
 * Primary site dispatch controller sends the request to the function or method
 * registered to handle it.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte;

use ElkArte\AdminController\Admin;
use ElkArte\AdminController\AdminDebug;
use ElkArte\AdminController\ManageThemes;
use ElkArte\Controller\Attachment;
use ElkArte\Controller\Auth;
use ElkArte\Controller\BoardIndex;
use ElkArte\Controller\Display;
use ElkArte\Controller\Emailmoderator;
use ElkArte\Controller\Help;
use ElkArte\Controller\Members;
use ElkArte\Controller\MergeTopics;
use ElkArte\Controller\MessageIndex;
use ElkArte\Controller\ModerateAttachments;
use ElkArte\Controller\ModerationCenter;
use ElkArte\Controller\MoveTopic;
use ElkArte\Controller\News;
use ElkArte\Controller\Notify;
use ElkArte\PersonalMessage\PersonalMessage;
use ElkArte\Controller\Poll;
use ElkArte\Controller\Post;
use ElkArte\Controller\RemoveTopic;
use ElkArte\Controller\SplitTopics;
use ElkArte\Controller\Unread;
use ElkArte\Controller\Xml;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Profile\Profile;
use ElkArte\Profile\ProfileHistory;
use ElkArte\Profile\ProfileInfo;

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
 * - An addon files to handle custom actions will be called if they follow any of these patterns.
 */
class SiteDispatcher
{
	/** @var string Function or method to call */
	protected $_function_name;

	/** @var string Class name for object-oriented controllers */
	protected $_controller_name;

	/** @var AbstractController The instance of the controller */
	protected $_controller;

	/** @var string */
	protected $action;

	/** @var string */
	protected $area;

	/** @var string */
	protected $subAction;

	/** The default action data (controller and function). Every time we don't know what to do, we'll do this :P */
	protected $_default_action = [
		'controller' => BoardIndex::class,
		'function' => 'action_boardindex'
	];

	/** @var string[] Build our nice and cozy err... *cough* */
	protected $actionArray = [
		'admin' => [Admin::class, 'action_index'],
		'attachapprove' => [ModerateAttachments::class, 'action_attachapprove'],
		'buddy' => [Members::class, 'action_buddy'],
		'collapse' => [BoardIndex::class, 'action_collapse'],
		'deletemsg' => [RemoveTopic::class, 'action_deletemsg'],
		'dlattach' => [Attachment::class, 'action_index'],
		'unwatchtopic' => [Notify::class, 'action_unwatchtopic'],
		'editpoll' => [Poll::class, 'action_editpoll'],
		'editpoll2' => [Poll::class, 'action_editpoll2'],
		'forum' => [BoardIndex::class, 'action_index'],
		'quickhelp' => [Help::class, 'action_quickhelp'],
		'jsmodify' => [Post::class, 'action_jsmodify'],
		'jsoption' => [ManageThemes::class, 'action_jsoption'],
		'keepalive' => [Auth::class, 'action_keepalive'],
		'lockvoting' => [Poll::class, 'action_lockvoting'],
		'login' => [Auth::class, 'action_login'],
		'login2' => [Auth::class, 'action_login2'],
		'logout' => [Auth::class, 'action_logout'],
		'mergetopics' => [MergeTopics::class, 'action_index'],
		'moderate' => [ModerationCenter::class, 'action_index'],
		'movetopic' => [MoveTopic::class, 'action_movetopic'],
		'movetopic2' => [MoveTopic::class, 'action_movetopic2'],
		'notifyboard' => [Notify::class, 'action_notifyboard'],
		'pm' => [PersonalMessage::class, 'action_index'],
		'post2' => [Post::class, 'action_post2'],
		'profile' => [Profile::class, 'action_index'],
		'profileinfo' => [ProfileInfo::class, 'action_index'],
		'quotefast' => [Post::class, 'action_quotefast'],
		'quickmod' => [MessageIndex::class, 'action_quickmod'],
		'quickmod2' => [Display::class, 'action_quickmod2'],
		'removetopic2' => [RemoveTopic::class, 'action_removetopic2'],
		'reporttm' => [Emailmoderator::class, 'action_reporttm'],
		'restoretopic' => [RemoveTopic::class, 'action_restoretopic'],
		'splittopics' => [SplitTopics::class, 'action_splittopics'],
		'trackip' => [ProfileHistory::class, 'action_trackip'],
		'unreadreplies' => [Unread::class, 'action_unreadreplies'],
		'viewprofile' => [Profile::class, 'action_index'],
		'viewquery' => [AdminDebug::class, 'action_viewquery'],
		'.xml' => [News::class, 'action_showfeed'],
		'xmlhttp' => [Xml::class, 'action_index'],
	];

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
	 * Retrieves the front page action based on the mod settings
	 *
	 * @return array The default action for the front page
	 */
	protected function getFrontPage(): array
	{
		global $modSettings;

		if (!empty($modSettings['front_page'])
			&& class_exists($modSettings['front_page'])
			&& in_array('frontPageHook', get_class_methods($modSettings['front_page'])))
		{
			$modSettings['default_forum_action'] = ['action' => 'forum'];
			call_user_func_array([$modSettings['front_page'], 'frontPageHook'], [&$this->_default_action]);
		}
		else
		{
			$modSettings['default_forum_action'] = [];
		}

		return $this->_default_action;
	}

	/**
	 * Finds out if the current action is one of those without an "action" parameter in the URL
	 */
	protected function determineDefaultAction(): void
	{
		global $board, $topic;

		if (empty($this->action))
		{
			// Home page: board index
			if (empty($board) && empty($topic))
			{
				// Was it, wasn't it...
				if (empty($this->_function_name))
				{
					$this->_controller_name = $this->_default_action['controller'];
					$this->_function_name = $this->_default_action['function'];
				}
			}
			// ?board=b message index
			elseif (empty($topic))
			{
				$this->_controller_name = MessageIndex::class;
				$this->_function_name = 'action_messageindex';
			}
			// board=b;topic=t topic display
			else
			{
				$this->_controller_name = Display::class;
				$this->_function_name = 'action_display';
			}
		}
	}

	/**
	 * Determines the controller and function names based on the current action.
	 * Allows extending or changing the action array through a hook.
	 * If the controller class does not exist, sets the default controller and function names.
	 */
	protected function determineAction(): void
	{
		// Allow extending or changing $actionArray through a hook
		// Format: $_GET['action'] => array($class, $method)
		call_integration_hook('integrate_actions', [&$this->actionArray]);

		// Is it in the action list?
		if (isset($this->actionArray[$this->action]))
		{
			$this->setActionAndControllerFromActionArray();
		}
		// Fall back to naming patterns, Addons can use any of them, and it should Just Work (tm).
		elseif (preg_match('~^[a-zA-Z_\\-]+\d*$~', $this->action))
		{
			$this->setActionAndControllerFromNamingPatterns();
		}

		// The file and function weren't found yet?  Then set a default!
		$this->setDefaultActionAndControllerIfEmpty();

		$this->handleApiCall();

		// Ensure both the controller and action exist and are callable
		if ($this->checkIfControllerExists())
		{
			return;
		}

		// This should never happen, that's why it's here :P
		$this->setDefaultActionAndController();
	}

	/**
	 * If the current action is in the action array, sets the controller and function names
	 * based on the action array value.
	 *
	 * What it does:
	 *   - If the method is specified in the action array, uses it as the function name.
	 *   - If the subAction is specified and matches the pattern, appends it to "action_" as the function name.
	 *   - If none of the above conditions are met, sets the function name to "action_index".
	 */
	protected function setActionAndControllerFromActionArray(): void
	{
		$this->_controller_name = $this->actionArray[$this->action][0];
		$this->_function_name = 'action_index';

		// If the method is coded in, use it
		if (!empty($this->actionArray[$this->action][1]))
		{
			$this->_function_name = $this->actionArray[$this->action][1];
		}
		// Otherwise fall back to naming patterns
		elseif (!empty($this->subAction) && preg_match('~^\w+$~', $this->subAction))
		{
			$this->_function_name = 'action_' . $this->subAction;
		}
	}

	/**
	 * Sets the action and controller names based on naming patterns.
	 *
	 * What it does:
	 * - The action name is used to determine the controller class, action=gallery => Gallery.php
	 * - The subAction is used to determine the action function, sa=upload => action_upload()
	 * - If the subAction is not set or the area is set, the action function will default to 'action_index'
	 * - controller classes must be in the Controller directory or in the Addons directory
	 * - action functions must be:
	 *      - In the Controller directory
	 *      - Or in the Addons directory as a subdirectory named the same as the action
	 */
	protected function setActionAndControllerFromNamingPatterns(): void
	{
		// Try the main ElkArte controller directory first
		$this->_controller_name = '\\ElkArte\\Controller\\' . ucfirst($this->action);

		if (!class_exists($this->_controller_name))
		{
			// Try the Addons directory
			$this->_controller_name = '\\Addons\\' . ucfirst($this->action) . '\\' . ucfirst($this->action);
		}

		// Now try to find the action function
		if ($this->subAction !== null && empty($this->area) && preg_match('~^\w+$~', $this->subAction))
		{
			$this->_function_name = 'action_' . $this->subAction;
		}
		else
		{
			$this->_function_name = 'action_index';
		}
	}

	/**
	 * Sets the default action and controller if they are empty.
	 * If either the controller or the function name is empty, this method calls the setDefaultActionAndController method.
	 */
	protected function setDefaultActionAndControllerIfEmpty(): void
	{
		if (empty($this->_controller_name) || empty($this->_function_name))
		{
			$this->setDefaultActionAndController();
		}
	}

	/**
	 * Sets the default action and controller names.
	 *
	 * What it does:
	 *  - This method is used to set the default action and controller names
	 *  - When the current action does not have an "action" parameter in the URL.
	 *  - It assigns the values from the `_default_action` property to the `_controller_name` and `_function_name` properties.
	 */
	protected function setDefaultActionAndController(): void
	{
		$this->_controller_name = $this->_default_action['controller'];
		$this->_function_name = $this->_default_action['function'];
	}

	/**
	 * Determines if the current API call should be handled separately.
	 *
	 * What it does:
	 *  - If the 'api' parameter is set in the request and its value is empty, it appends
	 * the '_api' suffix to the current function name.
	 *  - This needs to be reviewed; all api calls really should be qualified as
	 * JSON, XML, HTML, etc.
	 */
	protected function handleApiCall(): void
	{
		if (!isset($_REQUEST['api']))
		{
			return;
		}

		if ($_REQUEST['api'] !== '')
		{
			return;
		}

		$this->_function_name .= '_api';
	}

	/**
	 * Checks if the specified controller and its requested method exist and can be called.
	 *
	 * What it does:
	 *  - The method verifies whether the controller's class exists and determines if the
	 * specified function is callable within that class.
	 *  - If the requested sa is not "action_index" and the controller is an AbstractController,
	 * the method will revert to "action_index" so the sa is dispatched by the class to
	 * ensure permissions etc. are checked.
	 *
	 * @return bool Returns true if the controller and method exist and are callable, otherwise false.
	 */
	protected function checkIfControllerExists(): bool
	{
		// 3, 2, ... and go
		if (class_exists($this->_controller_name))
		{
			// Maybe the default requires an abstract method
			if ($this->_function_name !== 'action_index'
				&& !isset($this->actionArray[$this->action])
				&& in_array('action_index', get_class_methods($this->_controller_name), true)
				&& is_subclass_of($this->_controller_name, AbstractController::class))
			{
				// Calling a sa directly on an abstract class? This should be dispatched by the
				// class itself e.g., $action->dispatch($subAction) to ensure permissions
				// etc. are checked.
				$this->_function_name = 'action_index';
				return true;
			}

			// Method requested is in the list of its callable methods
			if (in_array($this->_function_name, get_class_methods($this->_controller_name), true))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Passes the \ElkArte\User::$info variable to the controller
	 *
	 * @param ValuesContainer $user
	 */
	public function setUser($user): void
	{
		$this->_controller->setUser($user);
	}

	/**
	 * Relay control to the respective function or method.
	 *
	 * What it does:
	 *
	 * - Calls a generic pre (_before) integration hook based on the controllers class name.
	 *   - e.g., integrate_action_draft_before will be called before \ElkArte\Controller\Draft
	 * - Calls the controllers pre_dispatch method, provides increased flexibility over simple _constructor
	 * - Calls the controllers selected method
	 * - Calls generic post (_after) integration hook based on the controllers class name.
	 *   - e.g., integrate_action_draft_after will be called after \ElkArte\Controller\Draft assuming it returns
	 * normally from the controller (e.g., no fatal error, no redirect)
	 *
	 * @event integrate_action_xyz_before
	 * @event integrate_action_xyz_after
	 */
	public function dispatch()
	{
		// Fetch controllers generic hook name from the action controller
		$hook = $this->_controller->getHook();

		// Call the controllers pre-dispatch method
		$this->_controller->pre_dispatch();

		// Call integrate_action_XYZ_before then XYZ_controller_>123 then integrate_action_XYZ_after
		call_integration_hook('integrate_action_' . $hook . '_before', [$this->_function_name]);

		$result = $this->_controller->{$this->_function_name}();

		// Remember kids, if your controller bails, you will not get here
		call_integration_hook('integrate_action_' . $hook . '_after', [$this->_function_name]);

		return $result;
	}

	/**
	 * If the current controller needs to load all the security framework.
	 */
	public function needSecurity(): bool
	{
		return $this->_controller->needSecurity($this->_function_name);
	}

	/**
	 * If the current controller needs to load the theme.
	 */
	public function needTheme(): bool
	{
		global $maintenance;

		// Maintenance mode: you're out of here unless you're admin
		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			// You can only log in
			if ($this->action === 'login2' || $this->action === 'logout')
			{
				$this->_controller_name = Auth::class;
				$this->_function_name = 'action_' . $this->action;
			}
			// "maintenance mode" page
			else
			{
				$this->_controller_name = Auth::class;
				$this->_function_name = 'action_maintenance_mode';
			}

			// re-initialize the controller and the event manager
			$this->_controller = new $this->_controller_name(new EventManager());
		}
		// If guest access is disallowed, a guest is kicked out... politely. :P
		elseif ($this->restrictedGuestAccess())
		{
			$this->_controller_name = Auth::class;
			$this->_function_name = 'action_kickguest';

			// re-initialize... you got the drift
			$this->_controller = new $this->_controller_name(new EventManager());
		}

		return $this->_controller->needTheme($this->_function_name);
	}

	/**
	 * Determine if guest access is restricted, and, if so,
	 * only allow the listed actions
	 *
	 * @return bool
	 */
	protected function restrictedGuestAccess(): bool
	{
		global $modSettings;

		return empty($modSettings['allow_guestAccess'])
			&& User::$info->is_guest
			&& !in_array($this->action, ['login', 'login2', 'register', 'reminder', 'help', 'quickhelp', 'mailq']);
	}

	/**
	 * If the current controller wants to track access and stats.
	 */
	public function trackStats($action = ''): bool
	{
		return $this->_controller->trackStats($this->_function_name);
	}

	/**
	 * @return AbstractController
	 */
	public function getController(): AbstractController
	{
		return $this->_controller;
	}

	/**
	 * Returns the current action for the system
	 *
	 * @return string
	 */
	public function site_action(): string
	{
		if (!empty($this->_controller_name))
		{
			$action = strtolower(ltrim(strrchr($this->_controller_name, "\\"), "\\"));
			$action = str_ends_with($action, "2") ? substr($action, 0, -1) : $action;
		}

		return $action ?? $this->action;
	}
}
