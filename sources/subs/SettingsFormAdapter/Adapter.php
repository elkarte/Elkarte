<?php

/**
 * This class handles display, edit, save, of forum settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

namespace ElkArte\sources\subs\SettingsFormAdapter;

/**
 * Class Adapter
 *
 * @package ElkArte\sources\subs\SettingsFormAdapter
 */
abstract class Adapter implements AdapterInterface
{
	/**
	 * Configuration variables and values for this settings form.
	 *
	 * @var array
	 */
	protected $configVars;

	/**
	 * Post variables and values for this settings form.
	 *
	 * @var array
	 */
	protected $configValues;

	/**
	 * @var array
	 */
	protected $context = array();

	/**
	 * @return array
	 */
	public function getConfigVars()
	{
		return $this->configVars;
	}

	/**
	 * @param array $configVars
	 */
	public function setConfigVars(array $configVars)
	{
		$this->configVars = $configVars;
	}

	/**
	 * @return array
	 */
	public function getConfigValues()
	{
		return $this->configValues;
	}

	/**
	 * @param array $configValues
	 */
	public function setConfigValues(array $configValues)
	{
		$this->configValues = $configValues;
	}

	/**
	 * Prepare the template by loading context
	 * variables for each setting.
	 */
	protected function prepareContext()
	{
		global $context;

		$context['config_vars'] = $this->context;
	}
}
