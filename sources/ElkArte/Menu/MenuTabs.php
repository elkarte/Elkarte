<?php

/**
 * Creates tabs based on the subsections of an area.  Called when no specific tab data has been
 * defined in a controller.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Menu;

/**
 * Class MenuTabs
 *
 * Mostly a class of setters/getters to build a set of menu tabs when no specific array
 * has been defined.  Easy way to add tabs but must follow naming conventions.  You can
 * still write to $context your tab_data array as well, but that is not recommended.
 */
class MenuTabs
{
	/** @var string The tab box title id to look for in $txt */
	public $title;

	/** @var string the index of helptxt to use  */
	public $help;

	/** @var string The tab box description to look for in $txt */
	public $description;

	/** @var string Class name to add to the title, generally used for icons */
	public $class;

	/** @var string The action prefix to use for naming convention $txt lookups' */
	public $prefix;

	/** @var string the current area, such as 'managesearch' */
	public $current_area;

	/** @var string the current action for the area, such as settings */
	public $currentSubaction;

	/**
	 * Basic constructor
	 *
	 * @param string $currentArea
	 * @param string $currentSubaction
	 */
	public function __construct($currentArea, $currentSubaction)
	{
		global $context;

		$this->current_area = $currentArea;
		$this->currentSubaction = $currentSubaction;

		// Do we have an active action, we might as this is often called
		// from the controller just before dispatch
		if (isset($context['sub_action']))
		{
			$this->currentSubaction = $context['sub_action'];
		}
	}

	/**
	 * Will attempt to build tabs if nothing is pre-defined by the controller
	 *
	 * - When $context[$context[some_menu_name']['tab_data'] is empty, attempts to create it
	 * - Will set the active tab if known
	 *
	 * @return array
	 */
	public function getTabs($currentArea)
	{
		foreach ($currentArea['subsections'] as $area => $subsection)
		{
			$currentArea['subsections'][$area]['description'] = $this->getIndividualDescription($currentArea, $area);

			// Set the active tab
			$currentArea['subsections'][$area]['selected'] = $currentArea['subsections'][$area]['selected'] ?? $area === $this->currentSubaction;
		}

		// Load the tabs [abc] [def] [etc]
		return $currentArea['subsections'];
	}

	/**
	 * The area above the tabs, title with icon (help or class), description
	 *
	 * @return array
	 */
	public function setHeader()
	{
		// Define the area above the tabs
		return [
			'title' => $this->getTitle(),
			'help' => $this->getHelp(),
			'description' => $this->getDescription(),
			'class' => $this->getClass(),
		];
	}

	/**
	 * Sets a description for a give tab/subaction
	 *
	 * - Trys naming convention of $prefix_$area_desc
	 * - Failing that, sets it to the description of the tab block
	 *
	 * @param array $currentArea
	 * @param string $area
	 * @return string
	 */
	public function getIndividualDescription($currentArea, $area)
	{
		global $txt;

		// No predefined description for this area
		if (!isset($currentArea['subsections'][$area]['description']))
		{
			$check = $this->getPrefix() . $area . '_desc';

			return $txt[$check] ?? $this->description;
		}

		return $currentArea['subsections'][$area]['description'];
	}

	/**
	 * Get the area title
	 */
	public function getTitle()
	{
		if (!isset($this->title))
		{
			$this->setTitle();
		}

		return $this->title;
	}

	/**
	 * Set the area title
	 *
	 * - Supplied one, will look for $txt[$title]
	 * - Not found in $txt, will return the supplied string
	 *
	 * @param $title
	 * @return $this
	 */
	public function setTitle($title = '')
	{
		global $txt;

		$checkTitle = $title ?? '';

		$this->title = $txt[$checkTitle] ?? $title;

		return $this;
	}

	/**
	 * Get the area help
	 *
	 * @return string
	 */
	public function getHelp()
	{
		if (!isset($this->help))
		{
			$this->setHelp();
		}

		return $this->help;
	}

	/**
	 * Set the area help
	 *
	 * @param $help
	 * @return $this
	 */
	public function setHelp($help = null)
	{
		$this->help = $help ?? null;

		return $this;
	}

	/**
	 * Return the area description
	 *
	 * @return string
	 */
	public function getDescription()
	{
		if (!isset($this->description))
		{
			$this->setDescription();
		}

		return $this->description;
	}

	/**
	 * Set the area description.
	 *
	 * - None supplied, will look for $txt[$this->current_area . '_desc']
	 * - Supplied one, will look for $txt[$description]
	 * - Not found in $txt, will return the supplied string
	 *
	 * @param string $description
	 * @return $this
	 */
	public function setDescription($description = '')
	{
		global $txt;

		$checkDescription = $description ?? $this->current_area . '_desc';

		$this->description = $txt[$checkDescription] ?? $description;

		return $this;
	}

	/**
	 * Get the area class
	 *
	 * @return string
	 */
	public function getClass()
	{
		if (!isset($this->class))
		{
			$this->setClass();
		}

		return $this->class;
	}

	/**
	 * Set the area class, only intended when help is not set
	 *
	 * @param $class
	 * @return $this
	 */
	public function setClass($class = null)
	{
		$this->class = $class ?? '';

		return $this;
	}

	/**
	 * Get the prefix
	 *
	 * @return string
	 */
	public function getPrefix()
	{
		if (!isset($this->prefix))
		{
			$this->setPrefix();
		}

		return $this->prefix . '_';
	}

	/**
	 * Set the area prefix, a help string to find txt description strings for subactions
	 *
	 * @param $prefix
	 * @return $this
	 */
	public function setPrefix($prefix = null)
	{
		$this->prefix = $prefix ?? '';

		return $this;
	}
}