<?php

/**
 * Http Request class for providing global vars to class for improved
 * encapsulation
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Helper;

/**
 * Class used to interact with super globals, POST, GET, SERVER, COOKIES, SESSION
 *
 * - Currently only a 'getter' of values
 * - Can be passed DataValidation sanitation values to return sanitized values
 * - Fetch raw values as $instance->post->keyname
 *     - $this->-req->post->filename
 *     - $this->_req->query->sa
 * - Fetch cleaned values with $instance->getPost('keyname', 'sanitation needs', 'default value')
 *     - $this->-req->getPost('filename', 'trim|strval', '');
 *     - $this->-req->getQuery('filename', 'intval', 0);
 *     - Can use rules as 'htmlspecialchars[ENT_COMPAT]', '\\ElkArte\\Helper\\Util::htmlspecialchars[ENT_QUOTES]'
 */
class HttpReq
{
	/** @var object The returned POST values */
	public $post;

	/** @var array The compiled post values (json and cleaned from request) */
	private $_derived_post;

	/** @var object The returned GET values */
	public $query;

	/** @var object The returned COOKIE values */
	public $cookie;

	/** @var object The returned SESSION values */
	public $session;

	/** @var object The returned SERVER values */
	public $server;

	/** @var HttpReq Sole private \ElkArte\Helper\HttpReq instance */
	private static $instance;

	/** @var array Used to hold processed (sanitised) values */
	private $_param;

	/** @var DataValidator holds instance of the validator */
	protected $_dataValidator;

	/**
	 * Class constructor, sets PHP globals to class members
	 *
	 * @param $dataValidator DataValidator|null Instance of the data validator
	 */
	private function __construct($dataValidator = null)
	{
		// Make sure the validator is initiated
		$this->_dataValidator = $dataValidator ?? new DataValidator();

		// Make the superglobals available as R/W properties
		$this->cookie = new \ArrayObject($_COOKIE, \ArrayObject::ARRAY_AS_PROPS);
		if (session_status() === PHP_SESSION_ACTIVE)
		{
			$this->session = new \ArrayObject($_SESSION, \ArrayObject::ARRAY_AS_PROPS);
		}
		else
		{
			$this->session = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
		}

		$this->server = detectServer();

		// Get will be in ->query, Post in ->post
		$this->_loadParsed();
	}

	/**
	 * Certain variables are born in Request, others are sanitized, and stored in
	 * $_REQUEST, its a awful mess really.
	 *
	 * Once that mess is cleaned up this should not be needed.  But the basis is due to
	 * what function cleanRequest() does.
	 *
	 * What it does:
	 *
	 * - Finds items added by cleanRequest to $_REQUEST
	 * - Adds the above to both $_POST and $_GET
	 * - Looks for duplicate items in $_REQUEST and $_POST and uses the $_REQUEST
	 *   values, being they are "sanitized"
	 * - $_GET ones are already re-stuffed by cleanRequest
	 */
	private function _loadParsed()
	{
		// Any that were born in cleanRequest, like start from topic=xyz.START
		// are added to the other supers
		$derived = array_diff_key($_REQUEST, $_POST, $_GET);
		$derived_get = array_merge($_GET, $derived);
		$this->_derived_post = array_merge($_POST, $derived);

		// Others may have been "sanitized" from either get or post and saved in request
		// these values replace the existing ones in $_POST
		$cleaned = array_intersect_key($_REQUEST, $this->_derived_post);
		$this->_derived_post = array_merge($this->_derived_post, $cleaned);

		// Make the $_GET $_POST super globals available as R/W properties
		$this->post = new \ArrayObject($this->_derived_post, \ArrayObject::ARRAY_AS_PROPS);
		$this->query = new \ArrayObject($derived_get, \ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Generic fetch access for values contained in the super globals
	 *
	 * - gets in order of param, get and post
	 * - $instance->keyanme will check cleaned params, get then post for values
	 *     - $_POST['foo'] = 'bar', $_GET['bar'] = 'foo'
	 *     - $this->req->post->foo is explicit and returns bar
	 *     - $this->req->foo is loose and will trigger this method, return foo as its a found key in GET
	 *
	 * @param string $key
	 *
	 * @return mixed|null
	 */
	public function __get($key)
	{
		if (isset($this->_param[$key]))
		{
			return $this->_param[$key];
		}

		if (isset($this->query->{$key}))
		{
			return $this->query->{$key};
		}

		return $this->post->{$key} ?? null;
	}

	/**
	 * Alias to __get
	 *
	 * Allows lazy way to find and return a value from get or post key name
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 *
	 * @return mixed
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
	 *
	 * @return bool
	 */
	public function __isset($key)
	{
		return match (true)
		{
			isset($this->post->{$key}), isset($this->query->{$key}), isset($this->_param[$key]) => true,
			default => false,
		};
	}

	/**
	 * Alias to __isset()
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function isSet($key)
	{
		return $this->__isset($key);
	}

	/**
	 * Method to return a $_GET value
	 *
	 * - Uses any sanitize rule(s) that can be passed to the \ElkArte\Helper\DataValidator class
	 * - Returned value will be the sanitized value or null of the key is not in $_GET
	 * - If you just want a value back access it directly as $req->query->{$name}
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 *
	 * @return mixed
	 */
	public function getQuery($name = '', $sanitize = null, $default = null)
	{
		$this->_param[$name] = $default;

		if (isset($this->query->{$name}))
		{
			$this->_param[$name] = $this->query->{$name};
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Method to return a $_POST value
	 *
	 * - Uses any sanitize rule(s) that can be passed to the \ElkArte\Helper\DataValidator class
	 * - Returned value will be the sanitized value or null of the key is not in $_POST
	 * - If you just want a value back access it directly as $req->post->{$name}
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 *
	 * @return mixed
	 */
	public function getPost($name = '', $sanitize = null, $default = null)
	{
		$this->_param[$name] = $default;

		if (isset($this->post->{$name}))
		{
			$this->_param[$name] = $this->post->{$name};
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Method to return a $_REQUEST value
	 *
	 * - Uses any sanitize rule(s) that can be passed to the \ElkArte\Helper\DataValidator class
	 * - Returned value will be the sanitized value or null if the key is not found in either $_GET
	 * or $_POST (in that order).  Ideally you should know if something is in GET or POST and use
	 * those get function directly.
	 *
	 * @param string $name The key name of the value to return
	 * @param string|null $sanitize a comma separated list of sanitation rules to apply
	 * @param mixed|null $default default value to return if key value is not found
	 *
	 * @return mixed
	 */
	public function getRequest($name = '', $sanitize = null, $default = null)
	{
		$this->_param[$name] = $default;

		if (isset($this->query->{$name}))
		{
			$this->_param[$name] = $this->query->{$name};
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}
		elseif (isset($this->post->{$name}))
		{
			$this->_param[$name] = $this->post->{$name};
			$this->_param[$name] = $this->cleanValue($name, $sanitize);
		}

		return $this->_param[$name];
	}

	/**
	 * Helper function to do a value comparison to a GET value.  Returns false
	 * if the value does not exist or if it does not equal the comparison value.
	 *
	 * @param string $name get value to fetch
	 * @param mixed $compare value to compare the get value to
	 * @param null|string $sanitize optional | delimited data for validator
	 * @param null|mixed $default if no value exists, what to set it to (will also be used in the compare)
	 *
	 * @return bool
	 */
	public function compareQuery($name, $compare, $sanitize = null, $default = null)
	{
		$this->getQuery($name, $sanitize, $default);

		return $this->isSet($name) && $this->_param[$name] === $compare;
	}

	/**
	 * Helper function to do a value comparison to a Post value.  Returns false
	 * if the value does not exist or if it does not equal the comparison value.
	 *
	 * @param string $name get value to fetch
	 * @param mixed $compare value to compare the post value to
	 * @param null|string $sanitize optional | delimited data for validator
	 * @param null|mixed $default if no value exists, what to set it to (will also be used in the compare)
	 *
	 * @return bool
	 */
	public function comparePost($name, $compare, $sanitize = null, $default = null)
	{
		$this->getPost($name, $sanitize, $default);

		return $this->isSet($name) && $this->_param[$name] === $compare;
	}

	/**
	 * Method to return a $_COOKIE value
	 *
	 * - Does not provide sanitation capability
	 *
	 * @param string $name the name of the value to return
	 * @param mixed|null $default default value to return if key value is not found
	 *
	 * @return mixed|null
	 */
	public function getCookie($name = '', $default = null)
	{
		if (isset($this->cookie->{$name}))
		{
			return $this->cookie->{$name};
		}

		return $default ?? null;
	}

	/**
	 * Method to get a $_SESSION value
	 *
	 * - Does not provide sanitation capability
	 *
	 * @param string $name the name of the value to return
	 * @param mixed|null $default default value to return if key value is not found
	 *
	 * @return mixed|null
	 */
	public function getSession($name = '', $default = null)
	{
		if (isset($this->session->{$name}))
		{
			return $this->session->{$name};
		}

		return $default ?? null;
	}

	/**
	 * Runs sanitation rules against a single value
	 *
	 * @param string $name the key name in the _param array
	 * @param string|null $sanitize comma separated list of rules
	 *
	 * @return mixed|array|null
	 */
	public function cleanValue($name, $sanitize = null)
	{
		// No rules, then return the current value
		if ($sanitize === null)
		{
			return $this->_param[$name];
		}

		// To the validator
		$this->_dataValidator->validation_rules();
		$this->_dataValidator->sanitation_rules([$name => $sanitize]);

		if (is_array($this->_param[$name]))
		{
			$this->_dataValidator->input_processing([$name => 'array']);
		}

		$this->_dataValidator->validate($this->_param);

		// Return the clean value
		return $this->_dataValidator->validation_data($name);
	}

	/**
	 * Removes a value from the post or query arrays
	 *
	 * @param string $name the key name in the _param array
	 * @param string|null $type where you want the value removed from post, query, both
	 */
	public function clearValue($name, $type)
	{
		unset($this->_param[$name]);

		if ($type === 'post' || $type === 'both')
		{
			unset($this->post->{$name});
		}

		if ($type === 'query' || $type === 'both')
		{
			unset($this->post->{$name});
		}
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return HttpReq
	 */
	public static function instance()
	{
		if (self::$instance === null)
		{
			self::$instance = new HttpReq();
		}

		return self::$instance;
	}
}
