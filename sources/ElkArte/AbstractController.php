<?php

/**
 * Abstract base class for controllers. Holds action_index and pre_dispatch
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;

/**
 * AbstractController class
 *
 * This class serves as a base class for all controllers in the application.
 * It provides common functionality and methods that can be used by its subclasses.
 *
 *  - Requires a default action handler, action_index().
 *  - Provides a constructor that loads in HttpReq.  Controllers should use a pre_dispatch class which is called
 *    by the dispatcher before any other action method.
 */
abstract class AbstractController
{
	/** @var object The event manager. */
	protected $_events;

	/** @var string The current hook. */
	protected $_hook = '';

	/** @var HttpReq Holds instance of \ElkArte\Helper\HttpReq object */
	protected $_req;

	/** @var ValuesContainer Holds instance of \ElkArte\User::$info object */
	protected $user;

	/**
	 * Constructor for the class.
	 *
	 * @param object $eventManager The event manager object.
	 */
	public function __construct($eventManager)
	{
		// Dependency injection will come later
		$this->_req = HttpReq::instance();

		$this->_events = $eventManager;
	}

	/**
	 * Default action handler.
	 *
	 * What it does:
	 *
	 * - This will be called by the dispatcher in many cases.
	 * - It may set up a menu, sub-dispatch at its turn to the method matching ?sa= parameter
	 * or simply forward the request to a known default method.
	 */
	abstract public function action_index();

	/**
	 * Called before any other action method in this class.
	 *
	 * What it does:
	 *
	 * - Allows for initializations, such as default values or loading templates or language files.
	 */
	public function pre_dispatch()
	{
		// By default, do nothing.
		// Sub-classes may implement their prerequisite loading,
		// such as load the template, load the language(s) file(s)
	}

	/**
	 * Standard method to add an "home" button when using a custom action as forum index.
	 *
	 * @param array $buttons
	 */
	public static function addForumButton(&$buttons)
	{
		global $scripturl, $txt, $modSettings;

		$buttons = array_merge([
			'base' => [
				'title' => $txt['home'],
				'href' => $scripturl,
				'data-icon' => 'i-home',
				'show' => true,
				'action_hook' => true,
			]], $buttons);

		$buttons['home']['href'] = getUrl('action', $modSettings['default_forum_action']);
		$buttons['home']['data-icon'] = 'i-comment-blank';
	}

	/**
	 * Standard method to tweak the current action when using a custom action as forum index.
	 *
	 * @param string $current_action
	 */
	public static function fixCurrentAction(&$current_action)
	{
		if ($current_action === 'home')
		{
			$current_action = 'base';
		}

		if (empty($_REQUEST['action']))
		{
			return;
		}

		if ($_REQUEST['action'] !== 'forum')
		{
			return;
		}

		$current_action = 'home';
	}

	/**
	 * Tells if the controller can be displayed as front page.
	 *
	 * @return bool
	 */
	public static function canFrontPage()
	{
		return in_array(FrontpageInterface::class, class_implements(static::class), true);
	}

	/**
	 * Used to define the parameters the controller may need for the front page
	 * action to work
	 *
	 * - e.g. specify a topic ID or a board listing
	 */
	public static function frontPageOptions()
	{
		return [];
	}

	/**
	 * Used to validate any parameters the controller may need for the front page
	 * action to work
	 *
	 * - e.g. specify a topic ID
	 * - should return true or false based on if its able to show the front page
	 */
	public static function validateFrontPageOptions($post)
	{
		return true;
	}

	/**
	 * Tells if the controller requires the security framework to be loaded. This is called
	 * immediately after the controller is initialized.
	 *
	 * @param string $action the function name of the current action
	 *
	 * @return bool
	 */
	public function needSecurity($action = '')
	{
		return true;
	}

	/**
	 * Tells if the controller needs the theme loaded up.
	 *
	 * @param string $action the function name of the current action
	 *
	 * @return bool
	 */
	public function needTheme($action = '')
	{
		return true;
	}

	/**
	 * Tells if the controller wants to be tracked.
	 *
	 * @param string $action the function name of the current action
	 *
	 * @return bool
	 */
	public function trackStats($action = '')
	{
		return empty($this->_req->getRequest('api', 'trim', false));
	}

	/**
	 * Public function to return the controllers generic hook name
	 */
	public function getHook()
	{
		if ($this->_hook === '')
		{
			// Use the base controller name for the hook, ie post
			$this->_hook = $this->getModuleClass();

			// Initialize the events associated with this controller
			$this->_initEventManager();
		}

		return strtolower($this->_hook);
	}

	/**
	 * Public function to return the modules hook class name
	 */
	public function getModuleClass()
	{
		// Use the base controller name for the hook, ie post
		$module_class = explode('\\', trim(static::class, '\\'));
		$module_class = end($module_class);

		return ucfirst($module_class);
	}

	/**
	 * Initialize the event manager for the controller
	 *
	 * Uses the \ElkArte\Controller\XXX name to define the set of event hooks to load
	 */
	protected function _initEventManager()
	{
		// Find any module classes associated with this controller
		$classes = $this->_loadModules();

		// Register any module classes => events we found
		$this->_events->registerClasses($classes);

		$this->_events->setSource($this);
	}

	/**
	 * Finds modules registered to a certain controller
	 *
	 * What it does:
	 *
	 * - Uses the controllers generic hook name to find modules
	 * - Searches for modules registered against the module name
	 * - Example
	 *   - \ElkArte\Controller\Display results in searching for modules registered against modules_display
	 *   - $modSettings['modules_display'] returns drafts,calendar,.....
	 *   - Verifies classes Drafts_Display_Module, Calendar_Display_Module, ... exist
	 *
	 * @return string[] Valid Module Classes for this Controller
	 */
	protected function _loadModules()
	{
		global $modSettings;

		$classes = [];
		$setting_key = 'modules_' . $this->getHook();
		$namespace = '\\ElkArte\\Modules\\';

		// For all the modules that have been registered see if we have a class to load for this hook area
		if (!empty($modSettings[$setting_key]))
		{
			$modules = explode(',', $modSettings[$setting_key]);

			foreach ($modules as $module)
			{
				// drafts => Drafts, some_name => SomeName
				$module_name = array_map('ucfirst', explode('_', $module));
				$class = $namespace . implode('', $module_name) . '\\' . $this->getModuleClass();

				if (class_exists($class))
				{
					$classes[] = $class;
				}
			}
		}

		return $classes;
	}

	/**
	 * An odd function that allows events to request dependencies from properties
	 * of the class.  Used by the EventManager to allow registered events to access
	 * values of the class that triggered the event.
	 *
	 * If the property does not exist in the class, will also look in globals.
	 *
	 * @param string $dep - The name of the property the even wants
	 * @param array $dependencies - the array that will be filled with the references to the dependencies
	 */
	public function provideDependencies($dep, &$dependencies)
	{
		if (property_exists($this, $dep))
		{
			$dependencies[$dep] = &$this->{$dep};
		}
		elseif (property_exists($this, '_' . $dep))
		{
			$dependencies[$dep] = &$this->{'_' . $dep};
		}
		elseif (array_key_exists($dep, $GLOBALS))
		{
			$dependencies[$dep] = &$GLOBALS[$dep];
		}
	}

	/**
	 * Returns the user object.
	 *
	 * @return ValuesContainer the user object.
	 */
	public function getUser(): ValuesContainer
	{
		return $this->user;
	}

	/**
	 * Sets the $this->user property to the current user
	 *
	 * @param ValuesContainer $user
	 */
	public function setUser($user)
	{
		$this->user = $user;
	}

	/**
	 * Helper function to see if the request is asking for any api processing
	 *
	 * @return string|false
	 */
	public function getApi()
	{
		global $db_show_debug;

		// API Call?
		$api = $this->_req->getRequest('api', 'trim', '');
		$api = in_array($api, ['xml', 'json', 'html']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? $api : false;

		// Lazy developers, nuff said :P
		if ($api !== false)
		{
			$db_show_debug = false;
		}

		return $api;
	}

	/**
	 * Shortcut to register an array of names as events triggered at a certain position in the code.
	 *
	 * @param string $name - Name of the trigger where the events will be executed.
	 * @param string $method - The method that will be executed.
	 * @param string[] $to_register - An array of classes to register.
	 */
	protected function _registerEvent($name, $method, $to_register)
	{
		foreach ($to_register as $class)
		{
			$this->_events->register($name, array($name, array($class, $method, 0)));
		}
	}
}
