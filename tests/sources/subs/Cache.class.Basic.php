<?php

class MockFilebased extends ElkArte\sources\subs\CacheMethod\Filebased
{
	/**
	 * Obtain from the parent class the variables necessary
	 * to help the tests stay running smoothly.
	 *
	 * @param string $key
	 * @return string
	 */
	public function getFileName($key)
	{
		return $this->prefix . '_' . $key . '.' . $this->ext;
	}

	/**
	 * Check that the specified cache entry exists on the filesystem.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function fileExists($key)
	{
		return file_exists(CACHEDIR . '/' . $this->getFileName($key));
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
		$this->_cache_obj = new MockFilebased(array());
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
		$this->assertTrue($this->_cache_obj->fileExists($key));
		$test_cached = $this->_cache_obj->get($key);
		$this->assertSame($test_array, $test_cached);
	}
}
