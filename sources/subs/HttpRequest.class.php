<?php

/**
 * Http Request class for providing global vars to class for improved
 * encapsulation
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

/**
 *
 */
class HttpReq
{
	/**
	 * The returned POST values
	 * @var object
	 */
	public $post;

	/**
	 * The returned GET values
	 * @var object
	 */
	public $query;

	/**
	 * The returned REQUEST values
	 * @var object
	 */
	public $request;

	/**
	 * The returned COOKIE values
	 * @var object
	 */
	public $cookie;

	/**
	 * The returned SESSION values
	 * @var object
	 */
	public $session;

	/**
	 * The returned SERVER values
	 * @var object
	 */
	public $server;

	/**
	 * Sole private HttpReq instance
	 * @var HttpReq
	 */
	private static $_req = null;

	private $_param;
	private $_dataValidator;


	/**
	 * Class constructor, sets PHP gobals to class members
	 */
	public function __construct($dataValidator = null)
	{
		// Make sure the validator is initiated
		$this->_dataValidator = $dataValidator === null ? new Data_Validator : $dataValidator;

		// Make the superglobals available as R/W properties
		$this->post = new ArrayObject($_POST, ArrayObject::ARRAY_AS_PROPS);
		$this->query = new ArrayObject($_GET, ArrayObject::ARRAY_AS_PROPS);
		$this->request = new ArrayObject($_REQUEST, ArrayObject::ARRAY_AS_PROPS);
		$this->cookie = new ArrayObject($_COOKIE, ArrayObject::ARRAY_AS_PROPS);
		$this->session = new ArrayObject($_SESSION, ArrayObject::ARRAY_AS_PROPS);
		$this->server = new ArrayObject($_SERVER, ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Generic access values contained in the superglobals
	 * - gets in order of param, get and post
	 *
	 * @param string $key
	 */
	public function __get($key)
	{
		switch (true) {
			case isset($this->_param[$key]):
				return $this->_param[$key];
			case isset($this->query->$key):
				return $this->query->$key;
			case isset($this->post->$key):
				return $this->post->$key;
			default:
				return null;
		}
	}

	/**
	 * Alias to __get
	 *
	 * @param string $key
	 */
	public function get($key)
	{
		return $this->__get($key);
	}

	/**
	 * Generic check to see if a property is set
	 *
	 * - checks in order of param, get, post
	 *
	 * @param string $key
	 */
	public function __isset($key)
	{
		switch (true) {
			case isset($this->_param[$key]):
				return true;
			case isset($this->query->$key):
				return true;
			case isset($this->post->$key):
				return true;
			default:
				return false;
		}
	}

	/**
	 * Alias to __isset()
	 *
	 * @param string $key
	 */
	public function is_set($key)
	{
		return $this->__isset($key);
	}

	/**
	 * Method to get, clean, sanitize a $_REQUEST value if set
	 *
	 * - Uses any sanitize rule(s) that can be passed to the Data_Validator class
	 * - Returned value will be the sanitized value or null of the key is not in $_REQUEST
	 * - If you just want a value back access it directly as $req->request->$name
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 */
	public function getRequest($name = '', $sanitize = null, $default = null)
	{
		$this->_param[$name] = $default;

		if (isset($this->request->$name))
		{
			$this->_param[$name] = $this->request->$name;
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Method to return a $_GET value
	 *
	 * - Uses any sanitize rule(s) that can be passed to the Data_Validator class
	 * - Returned value will be the sanitized value or null of the key is not in $_GET
	 * - If you just want a value back access it directly as $req->query->$name
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 */
	public function getQuery($name = '', $sanitize = null, $default = null)
	{
		$this->_param[$name] = $default;

		if (isset($this->query->$name))
		{
			$this->_param[$name] = $this->query->$name;
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Method to return a $_POST value
	 *
	 * - Uses any sanitize rule(s) that can be passed to the Data_Validator class
	 * - Returned value will be the sanitized value or null of the key is not in $_POST
	 * - If you just want a value back access it directly as $req->post->$name
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 */
	public function getPost($name = '', $sanitize = null, $default = null)
	{
		$this->_param[$name] = $default;

		if (isset($this->post->$name))
		{
			$this->_param[$name] = $this->post->$name;
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Method to return a $_COOKIE value
	 *
	 * @param string $name the name of the value to return
	 */
	public function getCookie($name = '')
	{
		return (isset($this->cookie->$name)) ? $this->cookie->$name : null;
	}

	/**
	 * Method to get a $_SESSION value
	 *
	 * @param string $name the name of the value to return
	 */
	public function getSession($name = '')
	{
		return (isset($this->session->$name)) ? $this->session->$name : null;
	}

	/**
	 * Runs sanitation rules against a single value
	 *
	 * @param string $name the key name in the _param array
	 * @param string|null $sanitize comma seperated list of rules
	 */
	public function cleanValue($name, $sanitize = null)
	{
		// No rules, then return the current value
		if ($sanitize === null)
			return $this->_param[$name];

		// To the validator
		$this->_dataValidator->validation_rules(array());
		$this->_dataValidator->sanitation_rules(array($name => $sanitize));
		$this->_dataValidator->validate($this->_param);

		// Return the clean value
		return $this->_dataValidator->validation_data($name);
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return HttpReq
	 */
	public static function instance()
	{
		if (self::$_req === null)
			self::$_req = new HttpReq();

		return self::$_req;
	}
}