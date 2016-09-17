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
 * @version 1.1 beta 2
 *
 */
abstract class SettingsFormAdapter implements SettingsFormAdapter_Interface
{
	/**
	 * Configuration variables and values for this settings form.
	 *
	 * @var array
	 */
	protected $config_vars;

	/**
	 * Post variables and values for this settings form.
	 *
	 * @var array
	 */
	protected $post_vars;

	/**
	 * @var array
	 */
	protected $context = array();

	/**
	 * @return array
	 */
	public function getConfigVars()
	{
		return $this->config_vars;
	}

	/**
	 * @param array $config_vars
	 */
	public function setConfigVars($config_vars)
	{
		$this->config_vars = $config_vars;
	}

	/**
	 * @return array
	 */
	public function getPostVars()
	{
		return $this->post_vars;
	}

	/**
	 * @param array $post_vars
	 */
	public function setPostVars($post_vars)
	{
		$this->post_vars = $post_vars;
	}

	public function __construct()
	{
		$this->post_vars = $_POST;
	}

	/**
	 * Prepare the template by loading context
	 * variables for each setting.
	 */
	protected function prepareContext()
	{
		global $context, $txt, $modSettings;

		$context['config_vars'] = $this->context;
	}

	/**
	 * Recursively checks if a value exists in an array
	 *
	 * @param string  $needle
	 * @param mixed[] $haystack
	 *
	 * @return boolean
	 */
	private function _array_value_exists__recursive($needle, $haystack)
	{
		foreach ($haystack as $item)
		{
			if ($item == $needle || (is_array($item) && $this->_array_value_exists__recursive($needle, $item)))
			{
				return true;
			}
		}

		return false;
	}
}
