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
 * This class implements a standard way of creating menus
 *
 * @package ElkArte\Menu
 */
class MenuOptions
{
	/** @var string $action overrides the default action */
	private $action = '';

	/** @var string $area overrides the current area */
	private $area = '';

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

	/** @var array $counters All the counters to be used for a menu. See Menu::parseCounter() */
	private $counters = [];

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * @param string $action
	 */
	public function setAction($action)
	{
		$this->action = $action;
	}

	/**
	 * @return string
	 */
	public function getArea()
	{
		return $this->area;
	}

	/**
	 * @param string $area
	 */
	public function setArea($area)
	{
		$this->area = $area;
	}

	/**
	 * @return array
	 */
	public function getExtraUrlParameters()
	{
		return $this->extraUrlParameters;
	}

	/**
	 * @param array $extraUrlParameters
	 */
	public function setExtraUrlParameters($extraUrlParameters)
	{
		$this->extraUrlParameters = $extraUrlParameters;
	}

	/**
	 * @return bool
	 */
	public function isUrlSessionCheckDisabled()
	{
		return $this->disableUrlSessionCheck;
	}

	/**
	 * @param bool $disableUrlSessionCheck
	 */
	public function setDisableUrlSessionCheck($disableUrlSessionCheck)
	{
		$this->disableUrlSessionCheck = $disableUrlSessionCheck;
	}

	/**
	 * @return string
	 */
	public function getBaseUrl()
	{
		return $this->baseUrl;
	}

	/**
	 * @param string $baseUrl
	 */
	public function setBaseUrl($baseUrl)
	{
		$this->baseUrl = $baseUrl;
	}

	/**
	 * @return string
	 */
	public function getMenuType()
	{
		return $this->menuType;
	}

	/**
	 * @param string $menuType
	 */
	public function setMenuType($menuType)
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
	 * @param bool $canToggleDropDown
	 */
	public function setCanToggleDropDown($canToggleDropDown)
	{
		$this->canToggleDropDown = $canToggleDropDown;
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return $this->templateName;
	}

	/**
	 * @param string $templateName
	 */
	public function setTemplateName($templateName)
	{
		$this->templateName = $templateName;
	}

	/**
	 * @return string
	 */
	public function getLayerName()
	{
		return $this->layerName;
	}

	/**
	 * @param string $layerName
	 */
	public function setLayerName($layerName)
	{
		$this->layerName = $layerName;
	}

	/**
	 * @return array
	 */
	public function getCounters()
	{
		return $this->counters;
	}

	/**
	 * @param array $counters
	 */
	public function setCounters($counters)
	{
		$this->counters = $counters;
	}

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
	 * Process the array of MenuOptions passed to the class
	 */
	protected function buildBaseUrl()
	{
		global $context, $scripturl;

		$this->setAction($this->getAction() ?: $context['current_action']);

		$this->setBaseUrl($this->getBaseUrl() ?: sprintf('%s?action=%s', $scripturl, $this->getAction()));
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
}