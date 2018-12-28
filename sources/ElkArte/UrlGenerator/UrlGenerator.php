<?php

/**
 * This class takes care of actually putting together the URL, starting from
 * the domain/forum address and appending the query part generated in another
 * class.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator;

class UrlGenerator
{
	/**
	 * Configuration parameters (for the moment script url and replacements)
	 * @var array
	 */
	protected $_config = array();

	/**
	 * All the objects that create the queries
	 * @var array
	 */
	protected $_generators = array();

	/**
	 * Searching for in replacements
	 * @var array
	 */
	protected $_search = array();

	/**
	 * replacing with in replacements
	 * @var array
	 */
	protected $_replace = array();

	/**
	 * The begin of all
	 *
	 * @param mixed[] $options
	 */
	public function __construct($options)
	{
		$this->_config = array_merge(array(
			'scripturl' => '',
			'replacements' => array(),
		), $options);

		$this->register('Standard');
		$this->updateReplacements($this->_config['replacements']);
	}

	/**
	 * Instantiate and return the query parser.
	 *
	 * @return \ElkArte\UrlGenerator\ParseQuery_Abstract
	 */
	public function getParser()
	{
		$class = '\\ElkArte\\UrlGenerator\\' . $this->_config['generator'] . '\\ParseQuery';

		return new $class();
	}

	/**
	 * Adds new replacements to the stack.
	 *
	 * @param string[] $replacements
	 */
	public function updateReplacements($replacements)
	{
		$this->_config['replacements'] = array_merge($this->_config['replacements'], $replacements);

		$this->_search = array_keys($this->_config['replacements']);
		$this->_replace = array_values($this->_config['replacements']);
	}

	/**
	 * Adds a new UrlGenerator (e.g. standard, semantic, etc.)
	 *
	 * @param object|string $generator
	 */
	public function register($generator)
	{
		$this->_initGen($generator);
	}

	/**
	 * Initialized the URL generator (i.e. instantiate the class if needed)
	 * and sets the generators according to the types they support.
	 *
	 * @param object|string $generator
	 */
	protected function _initGen($name)
	{
		if (is_object($name))
		{
			$generator = $name;
		}
		else
		{
			$class = '\\ElkArte\\UrlGenerator\\' . $this->_config['generator'] . '\\' . $name;

			if (class_exists($class))
			{
				$generator = new $class();
			}
		}

		if (isset($generator))
		{
			foreach ($generator->getTypes() as $type)
			{
				$this->_generators[$type] = $generator;
			}
		}
	}

	/**
	 * Takes care of building the URL
	 *
	 * @param string $type The type of URL we want to build
	 * @param mixed[] $params The URL parameters
	 *
	 * @return string The whole URL
	 */
	public function get($type, $params)
	{
		$url = $this->getQuery($type, $params);

		return $this->_append_base($url);
	}

	/**
	 * Almost the same as "get", though it returns only the query.
	 * This doesn't append the script URL at the beginning.
	 *
	 * @param string $type The type of URL we want to build
	 * @param mixed[] $params The URL parameters
	 *
	 * @return string The query part of the URL
	 */
	public function getQuery($type, $params)
	{
		if (isset($this->_generators[$type]) === false)
		{
			$type = 'standard';
		}

		return str_replace($this->_search, $this->_replace, $this->_generators[$type]->generate($params));
	}

	/**
	 * Append the script URL before the parameters.
	 *
	 * @param string $args The query part
	 *
	 * @return string The whole URL
	 */
	protected function _append_base($args)
	{
		if (!empty($args))
		{
			$args = '?' . $args;
		}

		return $this->_config['scripturl'] . $args;
	}
}
