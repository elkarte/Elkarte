<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 * license:   BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version   2.0 dev
 *
 */

declare(strict_types=1);

namespace ElkArte\Menu;

/**
 * This class implements a standard way of creating menus
 */
class MenuOptions
{
	/** @var string $action                    => overrides the default action */
	private $action='';

	/** @var string $currentArea              => overrides the current area */
	private $currentArea='';

	/** @var array $extraUrlParameters      => an array or pairs or parameters to be added to the url */
	private $extraUrlParameters=[];

	/** @var boolean $disableUrlSessionCheck => (boolean) if true the session var/id are omitted from the url */
	private $disableUrlSessionCheck=false;

	/** @var string $baseUrl                  => an alternative base url */
	private $baseUrl='';

	/** @var string $menuType                 => alternative menu types? */
	private $menuType='';

	/** @var boolean $canToggleDropDown      => (boolean) if the menu can "toggle" */
	private $canToggleDropDown=true;

	/** @var string $templateName             => an alternative template to load (instead of Generic) */
	private $templateName='GenericMenu';

	/** @var string $layerName                => alternative layer name for the menu */
	private $layerName='generic_menu';

	/** @var array $hook                      => hook name to call integrate_ . 'hook name' . '_areas' */
	private $counters=[];

	/**
	 * @return string
	 */
	public function getAction(): string
	{
		return $this->action;
	}

	/**
	 * @param string $action
	 */
	public function setAction(string $action)
	{
		$this->action = $action;
	}

	/**
	 * @return string
	 */
	public function getCurrentArea(): string
	{
		return $this->currentArea;
	}

	/**
	 * @param string $currentArea
	 */
	public function setCurrentArea(string $currentArea)
	{
		$this->currentArea = $currentArea;
	}

	/**
	 * @return array
	 */
	public function getExtraUrlParameters(): array
	{
		return $this->extraUrlParameters;
	}

	/**
	 * @param array $extraUrlParameters
	 */
	public function setExtraUrlParameters(array $extraUrlParameters)
	{
		$this->extraUrlParameters = $extraUrlParameters;
	}

	/**
	 * @return bool
	 */
	public function isUrlSessionCheckDisabled(): bool
	{
		return $this->disableUrlSessionCheck;
	}

	/**
	 * @param bool $disableUrlSessionCheck
	 */
	public function setDisableUrlSessionCheck(bool $disableUrlSessionCheck)
	{
		$this->disableUrlSessionCheck = $disableUrlSessionCheck;
	}

	/**
	 * @return string
	 */
	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	/**
	 * @param string $baseUrl
	 */
	public function setBaseUrl(string $baseUrl)
	{
		$this->baseUrl = $baseUrl;
	}

	/**
	 * @return string
	 */
	public function getMenuType(): string
	{
		return $this->menuType;
	}

	/**
	 * @param string $menuType
	 */
	public function setMenuType(string $menuType)
	{
		$this->menuType = $menuType;
	}

	/**
	 * @return bool
	 */
	public function isDropDownToggleable(): bool
	{
		return $this->canToggleDropDown;
	}

	/**
	 * @param bool $canToggleDropDown
	 */
	public function setCanToggleDropDown(bool $canToggleDropDown)
	{
		$this->canToggleDropDown = $canToggleDropDown;
	}

	/**
	 * @return string
	 */
	public function getTemplateName(): string
	{
		return $this->templateName;
	}

	/**
	 * @param string $templateName
	 */
	public function setTemplateName(string $templateName)
	{
		$this->templateName = $templateName;
	}

	/**
	 * @return string
	 */
	public function getLayerName(): string
	{
		return $this->layerName;
	}

	/**
	 * @param string $layerName
	 */
	public function setLayerName(string $layerName)
	{
		$this->layerName = $layerName;
	}

	/**
	 * @return array
	 */
	public function getCounters(): array
	{
		return $this->counters;
	}

	/**
	 * @param array $counters
	 */
	public function setCounters(array $counters)
	{
		$this->counters = $counters;
	}

	/**
	 * @param array $arr
	 *
	 * @return MenuOptions
	 */
	public static function buildFromArray(array $arr): MenuOptions
	{
		foreach (array_replace(
					$vars = get_object_vars($obj = new self),
					array_intersect_key($arr, $vars)
				) as $var => $val)
		{
			$obj->{'set' . str_replace('_', '', ucwords($var, '_'))}($val);
		}
		$obj->buildBaseUrl();
		$obj->buildTemplateVars();

		return $obj;
	}

	/**
	 * The theme needs some love, too.
	 */
	private function buildTemplateVars(): void
	{
		global $userInfo, $options;

		if (empty($this->getMenuType()))
		{
			$this->setMenuType(empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar');
		}
		$this->setCanToggleDropDown(!$userInfo['is_guest'] && $this->isDropDownToggleable());

		$this->setLayerName($this->getLayerName() . '_' . $this->getMenuType());
	}

	/**
	 * Process the array of MenuOptions passed to the class
	 */
	protected function buildBaseUrl(): void
	{
		global $context, $scripturl;

		$this->setAction($this->getAction()?:$context['current_action']);

		$this->setBaseUrl($this->getBaseUrl()?:sprintf(
				'%s?action=%s',
				$scripturl,
				$this->getAction()
			));
	}

	/**
	 * Build the additional parameters for use in the url
	 */
	public function buildAdditionalParams(): string
	{
		global $context;

		$arr = $this->getExtraUrlParameters();

		// Only include the session ID in the URL if it's strictly necessary.
		if (empty($this->menuOptions['disable_url_session_check']))
		{
			$arr[$context['session_var']]=$context['session_id'];
		}

		$extraUrlParameters = '';
			foreach ($this->extraUrlParameters as $key => $value)
			{
				$extraUrlParameters .= sprintf(
				';%s=%s',
				$key,
				$value
			);
			}

		return $extraUrlParameters;
	}
}
