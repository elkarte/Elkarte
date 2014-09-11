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
		require_once(SUBSDIR . '/CacheMethod/CacheMethod.interface.class.php');
		require_once(SUBSDIR . '/CacheMethod/CacheMethodAbstract.class.php');
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	 * Testing the filebase caching
	 */
	public function testFilebasedCache()
	{
		require_once(SUBSDIR . '/CacheMethod/FilebasedCache.class.php');
		$this->_cache_obj = new Filebased_Cache(array());
		$this->doCacheTests(function($key) {
			return file_exists(CACHEDIR . '/data_' . $key . '.php');
		});
	}

	/**
	 * Performs the testing of the caching object
	 */
	private function doCacheTests($putAssert = null)
	{
		$test_array = serialize(array('anindex' => 'avalue'));
		$key = 'testcache';

		$this->_cache_obj->put($key, $test_array);

		if ($putAssert !== null)
			$this->assertTrue($putAssert($key));

		$test_cached = $this->_cache_obj->get($key);
		$this->assertSame($test_array, $test_cached);
	}
}