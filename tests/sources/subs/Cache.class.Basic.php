<?php

/**
 * TestCase class for caching classes.
 */
class TestCache extends PHPUnit_Framework_TestCase
{
	private $_cache_obj = null;

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		Elk_Autoloader::getInstance()->register(SUBSDIR . '/CacheMethod', '\\ElkArte\\sources\\subs\\CacheMethod');
	}

	/**
	 * Testing the filebased caching
	 */
	public function testFilebasedCache()
	{
		$this->_cache_obj = new ElkArte\sources\subs\CacheMethod\Filebased(array());
		$this->doCacheTests();
	}

	/**
	 * Testing Apc
	 */
	public function testApcCache()
	{
		$this->_cache_obj = new ElkArte\sources\subs\CacheMethod\Apc(array());
		$this->doCacheTests();
	}

	/**
	 * Testing Memcached
	 */
	public function testMemcachedCache()
	{
		$this->_cache_obj = new ElkArte\sources\subs\CacheMethod\Memcached(array('servers' => array('localhost')));
		$this->doCacheTests();
	}

	/**
	 * Performs the testing of the caching object
	 */
	private function doCacheTests()
	{
		$test_array = serialize(array('anindex' => 'avalue'));
		$key = 'testcache';

		$this->_cache_obj->put($key, $test_array);
		$this->assertTrue($this->_cache_obj->exists($key));
		$test_cached = $this->_cache_obj->get($key);
		$this->assertSame($test_array, $test_cached);
	}
}
