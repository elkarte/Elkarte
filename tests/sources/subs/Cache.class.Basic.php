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
class TestCache extends PHPUnit\Framework\TestCase
{
	private $_cache_obj = null;

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		Elk_Autoloader::instance()->register(SUBSDIR . '/CacheMethod', '\\ElkArte\\sources\\subs\\CacheMethod');
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

		// We may not build APCu for every matrix
		if (!$this->_cache_obj->isAvailable())
		{
			$this->markTestSkipped('APCu is not loaded; skipping this test method');
		}

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
		$this->_cache_obj = new ElkArte\sources\subs\CacheMethod\Xcache(array('cache_uid' => 'mOo', 'cache_password' => 'test'));

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
	 * Testing the filebased caching
	 */
	public function testCacheClass()
	{
		global $cache_accelerator, $cache_enable;

		$cache_accelerator = '';
		$cache_enable = 1;

		require_once(SUBSDIR . '/Cache.class.php');
		$cache = Cache::instance();
		$file_cache = new ElkArte\sources\subs\CacheMethod\Filebased(array());
		$object = new \ReflectionClass($cache);
		$property = $object->getProperty('_cache_obj');
		$property->setAccessible(true);

		$property->setValue($cache, $file_cache);

		$cache->setLevel(1);
		$cache->enable(true);
		$this->assertSame($cache_enable, $cache->getLevel());
		$this->assertTrue($cache->isEnabled());

		$test_array = array('anindex' => 'avalue', array());

		$cache->put('test', $test_array);
		$this->assertSame($test_array, $cache->get('test'));
		$var = array();
		$found = $cache->getVar($var, 'test');
		$this->assertTrue($found);
		$this->assertSame($test_array, $var);
		$var = array();
		$found = $cache->getVar($var, 'test_undef');
		$this->assertFalse($found);
		$this->assertSame(null, $var);

		$cache->setLevel(2);
		$this->assertSame(2, $cache->getLevel());
		$this->assertFalse($cache->levelHigherThan(3));
		$this->assertFalse($cache->levelHigherThan(2));
		$this->assertTrue($cache->levelHigherThan(1));
		$this->assertTrue($cache->levelLowerThan(3));
		$this->assertFalse($cache->levelLowerThan(2));
		$this->assertFalse($cache->levelLowerThan(1));
		$cache->enable(false);
		$this->assertFalse($cache->levelHigherThan(3));
		$this->assertFalse($cache->levelHigherThan(2));
		$this->assertFalse($cache->levelHigherThan(1));
		$this->assertTrue($cache->levelLowerThan(3));
		$this->assertTrue($cache->levelLowerThan(2));
		$this->assertTrue($cache->levelLowerThan(1));
		$cache->setLevel(1);
	}

	/**
	 * Performs the testing of the caching object
	 */
	private function doCacheTests()
	{
		$test_array = serialize(array('anindex' => 'avalue'));

		$this->_cache_obj->put('test', $test_array);
		$this->assertTrue($this->_cache_obj->exists('test'));
		$this->assertSame($test_array, $this->_cache_obj->get('test'));

		$this->_cache_obj->put('test', null);
		$this->assertFalse($this->_cache_obj->exists('test'));
		$this->assertNull($this->_cache_obj->get('test'));

		$this->_cache_obj->put('test', $test_array);
		$this->assertTrue($this->_cache_obj->exists('test'));
		$this->_cache_obj->remove('test');
		$this->assertFalse($this->_cache_obj->exists('test'));
		$this->assertNull($this->_cache_obj->get('test'));

		$this->_cache_obj->put('test', $test_array);
		$this->assertTrue($this->_cache_obj->exists('test'));
		$this->_cache_obj->put('test2', $test_array);
		$this->assertTrue($this->_cache_obj->exists('test2'));
		$this->_cache_obj->clean();
		$this->assertFalse($this->_cache_obj->exists('test'));
		$this->assertFalse($this->_cache_obj->exists('test2'));
	}
}
