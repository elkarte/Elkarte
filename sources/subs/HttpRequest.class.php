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
	 * Sole private HttpReq instance
	 * @var HttpReq
	 */
	private static $_req = null;

	/**
	 * Class constructor, sets PHP gobals to class members
	 */
	public function __construct()
	{
		$this->post = new ArrayObject($_POST, ArrayObject::ARRAY_AS_PROPS);
		$this->query = new ArrayObject($_GET, ArrayObject::ARRAY_AS_PROPS);
		$this->request = new ArrayObject($_REQUEST, ArrayObject::ARRAY_AS_PROPS);
		$this->cookie = new ArrayObject($_COOKIE, ArrayObject::ARRAY_AS_PROPS);
		$this->session = new ArrayObject($_SESSION, ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Generic access values contained in the superglobals
	 *
	 * @param string $key
	 */
	public function __get($key)
	{
		switch (true) {
			case isset($this->request->$key):
				return $this->request->$key;
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
	 * @param string $key
	 */
	public function __isset($key)
	{
		switch (true) {
			case isset($this->request->$key):
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
	 * Method to set, clean, sanitize a $_GET value if set
	 *
	 * @param string $name The name of the value to return
	 */
	public function getRequest($name = '')
	{
		return (isset($this->request->$name)) ? $this->request->$name : null;
	}
	
	/**
	 * Method to return a $_GET value
	 *
	 * @param string $name The name of the value to return
	 */
	public function getQuery($name = '')
	{
		return (isset($this->query->$name)) ? $this->query->$name : null;
	}

	/**
	 * Method to return a $_POST value
	 *
	 * @param string $name the name of the value to return
	 */
	public function getPost($name = '')
	{
		return (isset($this->post->$name)) ? $this->post->$name : null;
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
		$this->session->$name = (isset($this->session->$name)) ? $this->session->$name : null;

		return $this->session;
	}

	public function cleanValue($value)
	{
		return Util::htmlspecialchars($value, ENT_QUOTES);
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