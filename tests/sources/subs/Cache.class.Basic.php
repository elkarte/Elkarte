<?php

class MockMemcached extends ElkArte\sources\subs\CacheMethod\Memcached
{
	/**
	 * Server count.
	 *
	 * @return int
	 */
	public function getNumServers()
	{
		return $this->getServers();
	}
}

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
	public function testApc()
	{
		$this->_cache_obj = new ElkArte\sources\subs\CacheMethod\Apc(array());
		$this->doCacheTests();
	}

	/**
	 * Testing Memcached
	 */
	public function testMemcached()
	{
		$this->_cache_obj = new MockMemcached(array('servers' => array('localhost', 'localhost:11212', 'localhost:11213')));
		$this->assertCount(3, $this->_cache_obj->getNumServers());
		$this->doCacheTests();
	}

	/**
	 * Testing Xcache
	 */
	public function testXcache()
	{
		$this->_cache_obj = new ElkArte\sources\subs\CacheMethod\Xcache(array());

		/*
		 * Xcache may not be loaded, so skip this test. The developer has
		 * not updated it for PHP 7. Also, since it conflicts with APC
		 * (NOT APCu), this test won't work in PHP 5.3.
		 */
		if (!$this->_cache_obj->isAvailable())
		{
			$this->markTestSkipped('Xcache is not loaded; skipping this test method');
		}
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

		$this->_cache_obj->put($key, null);
		$this->assertFalse($this->_cache_obj->exists($key));
		$test_cached = $this->_cache_obj->get($key);
		$this->assertNull($test_cached);
	}
}
