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

/**
 * Abstract base class for controllers.
 *
 * - Requires a default action handler, action_index().
 * - Defines an empty implementation for pre_dispatch() method.
 */
abstract class AbstractController
{
	/**
	 * The event manager.
	 * @var object
	 */
	protected $_events = null;

	/**
	 * The current hook.
	 * @var string
	 */
	protected $_hook = '';

	/**
	 * Holds instance of \ElkArte\HttpReq object
	 * @var \ElkArte\HttpReq
	 */
	protected $_req;

	/**
	 * @param \ElkArte\EventManager $eventManager
	 */
	public function __construct($eventManager)
	{
		// Dependency injection will come later
		$this->_req = HttpReq::instance();

		$this->_events = $eventManager;
	}

	/**
	 * Standard method to add an "home" button when using a custom action
	 * as forum index.
	 *
	 * @param array $buttons
	 */
	public static function addForumButton(&$buttons)
	{
		global $scripturl, $txt, $modSettings;

		$buttons = array_merge(array(
			'base' => array(
			'title' => $txt['home'],
			'href' => $scripturl,
			'data-icon' => 'i-home',
			'show' => true,
			'action_hook' => true,
		)), $buttons);

		$buttons['home']['href'] = getUrl('action', $modSettings['default_forum_action']);
		$buttons['home']['data-icon'] = 'i-comment-blank';
	}

	/**
	 * Standard method to tweak the current action when using a custom
	 * action as forum index.
	 *
	 * @param string $current_action
	 */
	public static function fixCurrentAction(&$current_action)
	{
		if ($current_action === 'home')
		{
			$current_action = 'base';
		}
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'forum')
		{
			$current_action = 'home';
		}
	}

	/**
	 * Tells if the controller requires the security framework to be loaded.
	 *
	 * @param string $action the function name of the current action
	 *
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
	 */
	public function trackStats($action = '')
	{
		return !isset($this->_req->api);
	}

	/**
	 * Tells if the controller can be displayed as front page.
	 *
	 * @return boolean
	 */
	public static function canFrontPage()
	{
		return in_array('ElkArte\\FrontpageInterface', class_implements(get_called_class()));
	}

	/**
	 * {@inheritdoc }
	 */
	public static function frontPageOptions()
	{
		return array();
	}

	/**
	 * {@inheritdoc }
	 */
	public static function validateFrontPageOptions($post)
	{
		return true;
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
		$module_class = explode('\\', trim(get_class($this), '\\'));
		$module_class= end($module_class);

		return ucfirst($module_class);
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

		$classes = array();
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
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
		// By default, do nothing.
		// Sub-classes may implement their prerequisite loading,
		// such as load the template, load the language(s) file(s)
	}

	/**
	 * An odd function that allows events to request dependencies from properties
	 * of the class.
	 *
	 * @param string $dep - The name of the property the even wants
	 * @param mixed[] $dependencies - the array that will be filled with the
	 *                                references to the dependencies
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
	 * Shortcut to register an array of names as events triggered at a certain
	 * position in the code.
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
