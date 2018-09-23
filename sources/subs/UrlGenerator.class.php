<?php

/**
 * Dummy
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

class Url_Generator
{
	protected $_config = array();
	protected $_generators = array();
	protected $_search = array();
	protected $_replace = array();

	public function __construct($options)
	{
		Elk_Autoloader::instance()->register(SUBSDIR . '/UrlGenerator', '\\ElkArte\\UrlGenerator');

		$this->_config = array_merge(array(
			'queryless' => false,
			'frendly' => false,
			'scripturl' => '',
			'replacements' => array(),
		), $options);

		$this->register('Standard');
		$this->updateReplacements($this->_config['replacements']);
	}

	public function updateReplacements($replacements)
	{
		$this->_config['replacements'] = array_merge($this->_config['replacements'], $replacements);

		$this->_search = array_keys($this->_config['replacements']);
		$this->_replace = array_values($this->_config['replacements']);
	}

	public function register($generator)
	{
		$this->_initGen($generator);
	}

	protected function _initGen($name)
	{
		if (is_object($name))
		{
			$generator = $name;
		}
		else
		{
			$class = '\\ElkArte\\UrlGenerator\\' . $this->_config['generator'] . '\\' . $name;

			$generator = new $class();
// 			_debug($generator->getTypes(),$class);
		}

		foreach ($generator->getTypes() as $type)
		{
			$this->_generators[$type] = $generator;
		}
	}

	public function get($type, $params)
	{
		$url = $this->getQuery($type, $params);

		return $this->_append_base($url);
	}

	public function getQuery($type, $params)
	{
// 	_debug(array_keys($this->_generators), $type);
		if (isset($this->_generators[$type]) === false)
		{
			$type = 'standard';
		}

		return str_replace($this->_search, $this->_replace, $this->_generators[$type]->generate($params));
	}

	protected function _append_base($args)
	{
		if (!empty($args))
		{
			$args = '?' . $args;
		}

		return $this->_config['scripturl'] . $args;
	}
}
