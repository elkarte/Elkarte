<?php

require_once(TESTDIR . 'simpletest/autorun.php');

/**
 * TestCase class for caching classes.
 */
class TestMembers extends UnitTestCase
{
	private $_cache_obj = null;

	/**
	 * Prepare some test data, to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		define('CACHEDIR', TESTDIR . '../cache');
		define('ELK', '1');
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
	}

	/**
	 * Testing the filebase caching
	 */
	function testFilebasedCache()
	{
		require_once(TESTDIR . '../sources/subs/cache/FilebasedCache.class.php');
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
		$test_array = array('anindex' => 'avalue');
		$key = 'testcache';

		$this->_cache_obj->put($key, $test_array);

		if ($putAssert !== null)
			$this->assertTrue($putAssert($key));

		$test_cached = $this->_cache_obj->get($key);
		$this->assertIdentical($test_array, $test_cached);
	}
}