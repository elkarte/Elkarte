<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Menu;

/**
 * Class MenuOptions
 *
 * This class will set and access the menu options. The supported options are:
 *
 *  - action                    => overrides the default action
 *  - current_area              => overrides the current area
 *  - extra_url_parameters      => an array or pairs or parameters to be added to the url
 *  - disable_url_session_check => (boolean) if true the session var/id are omitted from the url
 *  - base_url                  => an alternative base url
 *  - menu_type                 => alternative menu types?
 *  - can_toggle_drop_down      => (boolean) if the menu can "toggle"
 *  - template_name             => an alternative template to load (instead of Generic)
 *  - layer_name                => alternative layer name for the menu
 *  - hook                      => hook name to call integrate_ . 'hook name' . '_areas'
 *
 * @package ElkArte\Menu
 */
class MenuOptions
{
	/** @var string $action overrides the default action */
	private $action = '';

	/** @var string $currentArea overrides the current area */
	private $currentArea = '';

	/** @var array $extraUrlParameters an array or pairs or parameters to be added to the url */
	private $extraUrlParameters = [];

	/** @var boolean $disableUrlSessionCheck if true the session var/id are omitted from the url */
	private $disableUrlSessionCheck = false;

	/** @var string $baseUrl an alternative base url */
	private $baseUrl = '';

	/** @var string $menuType alternative menu type to replace the usual sidebar/dropdown. */
	private $menuType = '';

	/** @var boolean $canToggleDropDown if the menu can toggle between sidebar and dropdown. */
	private $canToggleDropDown = true;

	/** @var string $templateName an alternative template to load (instead of Generic) */
	private $templateName = 'GenericMenu';

	/** @var string $layerName alternative layer name for the menu */
	private $layerName = 'generic_menu';

	/** @var string $hook name to call integrate_ . $hook . '_areas' */
	private $hook = '';

	/** @var array $counters All the counters to be used for a menu. See Menu::parseCounter() */
	private $counters = [];

	/**
	 * Add an array of options for a menu.
	 *
	 * @param array $arr Options as an array. Keys should match properties
	 *                   of MenuOptions and can be either in snake_case or in camelCase,
	 *                   depending on your style.
	 *
	 * @return MenuOptions
	 */
	public static function buildFromArray($arr)
	{
		$obj = new self;

		// For each passed option, call the value setter method
		foreach ($arr as $var => $val)
		{
			if (is_callable([$obj, $call = 'set' . str_replace('_', '', ucwords($var, '_'))]))
			{
				$obj->{$call}($val);
			}
		}

		$obj->buildBaseUrl();
		$obj->buildTemplateVars();

		return $obj;
	}

	/**
	 * Process the array of MenuOptions passed to the class
	 */
	protected function buildBaseUrl()
	{
		global $context, $scripturl;

		$this->setAction($this->getAction() ?: $context['current_action']);

		$this->setBaseUrl($this->getBaseUrl() ?: sprintf('%s?action=%s', $scripturl, $this->getAction()));
	}

	/**
	 * Get action value
	 *
	 * @return string
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * Set action value
	 *
	 * @param string $action
	 */
	private function setAction($action)
	{
		$this->action = $action;
	}

	/**
	 * Get base URL
	 *
	 * @return string
	 */
	public function getBaseUrl()
	{
		return $this->baseUrl;
	}

	/**
	 * Set base URL
	 *
	 * @param string $baseUrl
	 */
	private function setBaseUrl($baseUrl)
	{
		$this->baseUrl = $baseUrl;
	}

	/**
	 * The theme needs some love, too.
	 */
	private function buildTemplateVars()
	{
		global $user_info, $options;

		if (empty($this->getMenuType()))
		{
			$this->setMenuType(empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar');
		}

		$this->setCanToggleDropDown(!$user_info['is_guest'] && $this->isDropDownToggleable());

		$this->setLayerName($this->getLayerName() . '_' . $this->getMenuType());
	}

	/**
	 * Get menu type
	 *
	 * @return string
	 */
	public function getMenuType()
	{
		return $this->menuType;
	}

	/**
	 * Set menu type
	 *
	 * @param string $menuType
	 */
	private function setMenuType($menuType)
	{
		$this->menuType = $menuType;
	}

	/**
	 * @return bool
	 */
	public function isDropDownToggleable()
	{
		return $this->canToggleDropDown;
	}

	/**
	 * Set toggle dropdown
	 *
	 * @param bool $canToggleDropDown
	 */
	private function setCanToggleDropDown($canToggleDropDown)
	{
		$this->canToggleDropDown = $canToggleDropDown;
	}

	/**
	 * Get Layer name
	 *
	 * @return string
	 */
	public function getLayerName()
	{
		return $this->layerName;
	}

	/**
	 * Set Layer name
	 *
	 * @param string $layerName
	 */
	private function setLayerName($layerName)
	{
		$this->layerName = $layerName;
	}

	/**
	 * Get area value
	 *
	 * @return string
	 */
	public function getCurrentArea()
	{
		return $this->currentArea;
	}

	/**
	 * Set area value
	 *
	 * @param string $currentArea
	 */
	private function setCurrentArea($currentArea)
	{
		$this->currentArea = $currentArea;
	}

	/**
	 * Get session check
	 *
	 * @return bool
	 */
	public function isUrlSessionCheckDisabled()
	{
		return $this->disableUrlSessionCheck;
	}

	/**
	 * Set session check
	 *
	 * @param bool $disableUrlSessionCheck
	 */
	private function setDisableUrlSessionCheck($disableUrlSessionCheck)
	{
		$this->disableUrlSessionCheck = $disableUrlSessionCheck;
	}

	/**
	 * Get template name
	 *
	 * @return string
	 */
	public function getTemplateName()
	{
		return $this->templateName;
	}

	/**
	 * Set template name
	 *
	 * @param string $templateName
	 */
	private function setTemplateName($templateName)
	{
		$this->templateName = $templateName;
	}

	/**
	 * Get counter
	 *
	 * @return array
	 */
	public function getCounters()
	{
		return $this->counters;
	}

	/**
	 * Set Counter
	 *
	 * @param array $counters
	 */
	private function setCounters($counters)
	{
		$this->counters = $counters;
	}

	/**
	 * Build the additional parameters for use in the url
	 *
	 * @return string
	 */
	public function buildAdditionalParams()
	{
		global $context;

		$arr = $this->getExtraUrlParameters();

		// Only include the session ID in the URL if it's strictly necessary.
		if (!$this->isUrlSessionCheckDisabled())
		{
			$arr[$context['session_var']] = $context['session_id'];
		}

		$extraUrlParameters = '';
		foreach ($arr as $key => $value)
		{
			$extraUrlParameters .= sprintf(';%s=%s', $key, $value);
		}

		return $extraUrlParameters;
	}

	/**
	 * Get URL parameters
	 *
	 * @return array
	 */
	public function getExtraUrlParameters()
	{
		return $this->extraUrlParameters;
	}

	/**
	 * Set URL parameters
	 *
	 * @param array $extraUrlParameters
	 */
	private function setExtraUrlParameters($extraUrlParameters)
	{
		$this->extraUrlParameters = $extraUrlParameters;
	}

	/**
	 * Get the hook name
	 *
	 * @return string
	 */
	public function getHook()
	{
		return $this->hook;
	}

	/**
	 * Set the hook name
	 *
	 * @param string $hook
	 */
	private function setHook($hook)
	{
		$this->hook = 'integrate_' . $hook . '_areas';
	}
}
