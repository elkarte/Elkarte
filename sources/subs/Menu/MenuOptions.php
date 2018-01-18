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

	/** @var string $current_area              => overrides the current area */
	private $current_area='';

	/** @var array $extra_url_parameters      => an array or pairs or parameters to be added to the url */
	private $extra_url_parameters=[];

	/** @var boolean $disable_url_session_check => (boolean) if true the session var/id are omitted from the url */
	private $disable_url_session_check=false;

	/** @var string $base_url                  => an alternative base url */
	private $base_url='';

	/** @var string $menu_type                 => alternative menu types? */
	private $menu_type='';

	/** @var boolean $can_toggle_drop_down      => (boolean) if the menu can "toggle" */
	private $can_toggle_drop_down=true;

	/** @var string $template_name             => an alternative template to load (instead of Generic) */
	private $template_name='GenericMenu';

	/** @var string $layer_name                => alternative layer name for the menu */
	private $layer_name='generic_menu';

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
		return $this->current_area;
	}

	/**
	 * @param string $current_area
	 */
	public function setCurrentArea(string $current_area)
	{
		$this->current_area = $current_area;
	}

	/**
	 * @return array
	 */
	public function getExtraUrlParameters(): array
	{
		return $this->extra_url_parameters;
	}

	/**
	 * @param array $extra_url_parameters
	 */
	public function setExtraUrlParameters(array $extra_url_parameters)
	{
		$this->extra_url_parameters = $extra_url_parameters;
	}

	/**
	 * @return bool
	 */
	public function isUrlSessionCheckDisabled(): bool
	{
		return $this->disable_url_session_check;
	}

	/**
	 * @param bool $disable_url_session_check
	 */
	public function setDisableUrlSessionCheck(bool $disable_url_session_check)
	{
		$this->disable_url_session_check = $disable_url_session_check;
	}

	/**
	 * @return string
	 */
	public function getBaseUrl(): string
	{
		return $this->base_url;
	}

	/**
	 * @param string $base_url
	 */
	public function setBaseUrl(string $base_url)
	{
		$this->base_url = $base_url;
	}

	/**
	 * @return string
	 */
	public function getMenuType(): string
	{
		return $this->menu_type;
	}

	/**
	 * @param string $menu_type
	 */
	public function setMenuType(string $menu_type)
	{
		$this->menu_type = $menu_type;
	}

	/**
	 * @return bool
	 */
	public function isDropDownToggleable(): bool
	{
		return $this->can_toggle_drop_down;
	}

	/**
	 * @param bool $can_toggle_drop_down
	 */
	public function setCanToggleDropDown(bool $can_toggle_drop_down)
	{
		$this->can_toggle_drop_down = $can_toggle_drop_down;
	}

	/**
	 * @return string
	 */
	public function getTemplateName(): string
	{
		return $this->template_name;
	}

	/**
	 * @param string $template_name
	 */
	public function setTemplateName(string $template_name)
	{
		$this->template_name = $template_name;
	}

	/**
	 * @return string
	 */
	public function getLayerName(): string
	{
		return $this->layer_name;
	}

	/**
	 * @param string $layer_name
	 */
	public function setLayerName(string $layer_name)
	{
		$this->layer_name = $layer_name;
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

		$extra_url_parameters = '';
			foreach ($this->extra_url_parameters as $key => $value)
			{
				$extra_url_parameters .= sprintf(
				';%s=%s',
				$key,
				$value
			);
			}

		return $extra_url_parameters;
	}
}
