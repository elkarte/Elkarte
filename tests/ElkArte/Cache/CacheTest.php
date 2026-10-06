<?php

namespace ElkArte\Cache;

require_once dirname(__DIR__, 3) . '/sources/Subs.php';

use ElkArte\Cache\CacheMethod\Apc;
use ElkArte\Cache\CacheMethod\Filebased;
use ElkArte\Cache\CacheMethod\Memcached;
use ElkArte\Cache\CacheMethod\Redis;
use PHPUnit\Framework\TestCase;

class MockMemcached extends Memcached
{
	/**
	 * Server array for count.
	 *
	 * @return string[]
	 */
	public function getNumServers()
	{
		return $this->getServers();
	}

	protected function getServers(): array
	{
		return $this->_options['servers'] ?? [];
	}
}

class MockRedisClient
{
	public $selectedDb = null;
	public $authPassword = null;
	public $options = [];

	public function auth($password)
	{
		$this->authPassword = $password;
		return true;
	}

	public function select(int $db)
	{
		$this->selectedDb = $db;
		return true;
	}

	public function setOption($option, $value)
	{
		$this->options[$option] = $value;
		return true;
	}

	public function ping()
	{
		return true;
	}
}

class TestableRedisMethod extends Redis
{
	public function __construct($options, $mockObj = null)
	{
		$this->_options = $options;
		if ($mockObj !== null)
		{
			$this->obj = $mockObj;
			$this->isConnected = true;
			$this->setOptions();
		}
	}

	public function getIsConnected(): bool
	{
		return $this->isConnected;
	}

	public function getObj()
	{
		return $this->obj;
	}
}

/**
 * TestCase class for caching classes.
 */
class CacheTest extends TestCase
{
	private $_cache_obj;
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		if (!defined('CACHEDIR'))
		{
			$cacheDir = sys_get_temp_dir() . '/elkarte_cache';
			if (!is_dir($cacheDir))
			{
				mkdir($cacheDir, 0777, true);
			}
			define('CACHEDIR', $cacheDir);
		}
	}

	/**
	 * Testing the filebased caching
	 */
	public function testFilebasedCache()
	{
		$this->_cache_obj = new Filebased(array());
		$this->doCacheTests();
	}

	/**
	 * Testing Apc
	 */
	public function testApc()
	{
		$this->_cache_obj = new Apc(array());

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
	}

	/**
	 * Testing Redis cache with invalid non-numeric cache_uid
	 */
	public function testRedisInvalidCacheUid()
	{
		$mock = new MockRedisClient();
		$redis = new TestableRedisMethod(['cache_uid' => 'xyz'], $mock);

		$this->assertFalse($redis->getIsConnected());
		$this->assertNull($mock->selectedDb);
	}

	/**
	 * Testing Redis cache with valid string numeric cache_uid
	 */
	public function testRedisNumericStringCacheUid()
	{
		$mock = new MockRedisClient();
		$redis = new TestableRedisMethod(['cache_uid' => '3'], $mock);

		$this->assertTrue($redis->getIsConnected());
		$this->assertSame(3, $mock->selectedDb);
	}

	/**
	 * Testing Redis cache with integer cache_uid
	 */
	public function testRedisIntCacheUid()
	{
		$mock = new MockRedisClient();
		$redis = new TestableRedisMethod(['cache_uid' => 2], $mock);

		$this->assertTrue($redis->getIsConnected());
		$this->assertSame(2, $mock->selectedDb);
	}

	/**
	 * Testing Redis cache with empty cache_uid
	 */
	public function testRedisEmptyCacheUid()
	{
		$mock = new MockRedisClient();
		$redis = new TestableRedisMethod(['cache_uid' => ''], $mock);

		$this->assertTrue($redis->getIsConnected());
		$this->assertNull($mock->selectedDb);
	}

	/**
	 * Testing Redis cache with zero cache_uid
	 */
	public function testRedisZeroCacheUid()
	{
		$mock = new MockRedisClient();
		$redis = new TestableRedisMethod(['cache_uid' => '0'], $mock);

		$this->assertTrue($redis->getIsConnected());
		$this->assertSame(0, $mock->selectedDb);
	}

	/**
	 * Testing the filebased caching
	 */
	public function testCacheClass()
	{
		global $cache_accelerator, $cache_enable;

		$cache_accelerator = '';
		$cache_enable = 1;

		$cache = Cache::instance();
		$file_cache = new Filebased(array());
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
