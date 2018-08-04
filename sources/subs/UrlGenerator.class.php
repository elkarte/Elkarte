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

use \ElkArte\UrlGenerator\Standard;

class Url_Generator
{
	/**
	 * The instance of the class
	 * @var Url_Generator
	 */
	private static $_instance = null;

	protected $_config = array();
	protected $_generators = array();

	private function __construct($options)
	{
		$this->_config = array_merge(array(
			'queryless' => false,
			'frendly' => false,
			'scripturl' => '',
		), $options);

		$this->register(new Standard());
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
			$class = '\\ElkArte\\UrlGenerator\\' . $name;

			$generator = new $class();
		}

		foreach ($generator->getTypes() as $type)
		{
			$this->_generators[$type] = $generator;
		}
	}

	public function get($type, $params)
	{
		if (isset($this->_generators[$type]) === false)
		{
			$type = 'standard';
		}

		$url = $this->_generators[$type]->generate($params);
		return $this->_append_base($url);
	}

	protected function _append_base($args)
	{
		if (!empty($args))
		{
			$args = '?' . $args;
		}

		return $this->_config['scripturl'] . $args;
	}

	public static function instance()
	{
		global $scripturl;

		if (self::$_instance === null)
		{
			self::$_instance = new Url_Generator(array('scripturl' => $scripturl));
		}

		return self::$_instance;
	}
}
