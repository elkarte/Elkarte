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
 * Class used to interact with super globals, POST, GET, SERVER, COOKEIS, SESSION
 *
 * - Currently only a 'getter' of values
 * - Can be passed DataValidation sanitation values to return sanitized values
 * - Fetch raw values as $instance->post->keyname
 *     - $this->-req->post->filename
 * - Fetch cleaned values with $instance->getPost('keyname', 'sanitation needs', 'default value')
 *     - $this->-req->getPost('filename', 'trim|strval', '');
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

	/**
	 * Used to hold processed (sanitised) values
	 * @var array
	 */
	private $_param;

	/**
	 * holds instance of the validator
	 * @var Data_Validator
	 */
	protected $_dataValidator;

	/**
	 * Class constructor, sets PHP globals to class members
	 */
	public function __construct($dataValidator = null)
	{
		// Make sure the validator is initiated
		$this->_dataValidator = $dataValidator === null ? new Data_Validator : $dataValidator;

		// Make the superglobals available as R/W properties
		$this->cookie = new ArrayObject($_COOKIE, ArrayObject::ARRAY_AS_PROPS);
		$this->session = new ArrayObject($_SESSION, ArrayObject::ARRAY_AS_PROPS);
		$this->server = new ArrayObject($_SERVER, ArrayObject::ARRAY_AS_PROPS);

		$this->_loadParsed();
	}

	/**
	 * Certain variables are born in Request, others are sanitized, and stored in
	 * $_REQUEST, its a awful mess really.
	 * Once that mess is cleaned up this should not be needed.  But the basis is due to
	 * what function cleanRequest() does.
	 *
	 * What it does:
	 * - finds items added by cleanRequest to $_REQUEST
	 * - adds the above to both $_POST and $_GET
	 * - looks for duplicate items in $_REQUEST and $_POST and used the $_REQUEST
	 *   values, being they are "sanitized"  $_GET ones are re-stuffed by cleanRequest
	 */
	private function _loadParsed()
	{
		// Any that were born in cleanRequest, like start from topic=xyz.START
		// are added to the other supers
		$derived = array_diff_key($_REQUEST, $_POST, $_GET);
		$derived_get = array_merge($_GET, $derived);
		$derived_post = array_merge($_POST, $derived);

		// Others may have been "sanitized" from either get or post and saved in request
		// these values replace existing ones in $_POST
		$cleaned = array_intersect_key($_REQUEST, $derived_post);
		$derived_post = array_merge($derived_post, $cleaned);

		// Make the $_GET $_POST super globals available as R/W properties
		$this->post = new ArrayObject($derived_post, ArrayObject::ARRAY_AS_PROPS);
		$this->query = new ArrayObject($derived_get, ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Generic fetch access for values contained in the super globals
	 * - gets in order of param, get and post
	 *
	 * - $instance->keyanme will check cleaned params, get then post for values
	 *     - $_POST['foo'] = 'bar', $_GET['bar'] = 'foo'
	 *     - $this->req->post->foo is explicit and returns bar
	 *     - $this->req->foo is loose and will trigger this method, return foo as its a found key in GET
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
	 * Allows lazy way to find and return a value from get or post key name
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 */
	public function get($name, $sanitize = null, $default = null)
	{
		// See if it exists in one of the supers
		$temp = $this->__get($name);

		$this->_param[$name] = $default;

		if (isset($temp))
		{
			$this->_param[$name] = $temp;
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Generic check to see if a property is set in one of the super globals
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
	 * - Does not provide sanitation capability
	 *
	 * @param string $name the name of the value to return
	 * @param mixed|null $default default value to return if key value is not found
	 */
	public function getCookie($name = '', $default = null)
	{
		if (isset($this->cookie->$name))
			return $this->cookie->$name;
		elseif ($default !== null)
			return $default;
		else
			return null;
	}

	/**
	 * Method to get a $_SESSION value
	 *
	 * - Does not provide sanitation capability
	 *
	 * @param string $name the name of the value to return
	 * @param mixed|null $default default value to return if key value is not found
	 */
	public function getSession($name = '', $default = null)
	{
		if (isset($this->session->$name))
			return $this->session->$name;
		elseif ($default !== null)
			return $default;
		else
			return null;
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