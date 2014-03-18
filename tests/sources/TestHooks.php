<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');

/**
 * TestCase class for hooks adding/removing/other stuff
 */
class TestHooks extends UnitTestCase
{
	/**
	 * Name of the hook used for testing
	 */
	private $_hook_name = 'integrate_testing_hook';

	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
	}

	/**
	 * Tests adding hooks in some different ways
	 */
	function testAddHooks()
	{
		// A simple one, just the function, default (i.e. in theory permanent)
		add_integration_function($this->_hook_name, 'testing_hook');
		$this->assertTrue($this->_hook_exists(array('call' => 'testing_hook'), true));

		// Something a bit more complex: function + file
		add_integration_function($this->_hook_name, 'testing_hook2', 'SOURCEDIR/Testing.php');
		$this->assertTrue($this->_hook_exists(array('call' => 'testing_hook2', 'file' => 'SOURCEDIR/Testing.php'), true));

		// Simple, just the function, non permanent
		add_integration_function($this->_hook_name, 'testing_hook_np', '', false);
		$this->assertTrue($this->_hook_exists(array('call' => 'testing_hook_np'), false));

		// Non permanent, function + file
		add_integration_function($this->_hook_name, 'testing_hook_np2', 'SOURCEDIR/Testing_np.php', false);
		$this->assertTrue($this->_hook_exists(array('call' => 'testing_hook_np2', 'file' => 'SOURCEDIR/Testing_np.php'), false));
	}

	/**
	 * And let's see what happens when removing them
	 */
	function testRemoveHooks()
	{
		// A simple one, just the function, default (i.e. in theory permanent)
		remove_integration_function($this->_hook_name, 'testing_hook');
		$this->assertFalse($this->_hook_exists(array('call' => 'testing_hook'), true));

		// Something a bit more complex: function + file
		remove_integration_function($this->_hook_name, 'testing_hook2', 'SOURCEDIR/Testing.php');
		$this->assertFalse($this->_hook_exists(array('call' => 'testing_hook2', 'file' => 'SOURCEDIR/Testing.php'), true));

		// Simple, just the function, non permanent
		remove_integration_function($this->_hook_name, 'testing_hook_np', false);
		$this->assertFalse($this->_hook_exists(array('call' => 'testing_hook_np'), false));

		// Non permanent, function + file
		remove_integration_function($this->_hook_name, 'testing_hook_np2', 'SOURCEDIR/Testing_np.php', false);
		$this->assertFalse($this->_hook_exists(array('call' => 'testing_hook_np2', 'file' => 'SOURCEDIR/Testing_np.php'), false));
	}

	/**
	 * Verifies the hook exists both in $modSettings and db (if permanent)
	 * or just in $modSettings
	 *
	 * @param string[] $expected_hook - the call and file (optional) expected
	 * @param bool $permanent - if the hook is perma or not
	 */
	private function _hook_exists($expected_hook, $permanent)
	{
		global $modSettings;

		$db = database();
		$request = $db->query('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:hook_name}',
			array(
				'hook_name'=> $this->_hook_name,
			)
		);
		list ($db_hook_string) = $db->fetch_row($request);
		$db->free_result($request);

		$db_hooks = $this->_parse_hooks($db_hook_string);
		// If it is permanent and the system doesn't know anything about it: bad
		if ($permanent && !$this->_find_hook($expected_hook, $db_hooks))
			return false;

		// If it is not permanent but it is in the db: bad again
		if (!$permanent && $this->_find_hook($expected_hook, $db_hooks))
			return false;

		// If it is empty there is no hook
		if (empty($modSettings[$this->_hook_name]))
			return false;

		// Permanent or not it should be in $modSettings
		$db_hooks = $this->_parse_hooks($modSettings[$this->_hook_name]);
		if (!$this->_find_hook($expected_hook, $db_hooks))
			return false;

		return true;
	}

	/**
	 * Parse a string into hook calls
	 * @param string $hook_string - The string stored in {db_prefix}settings for a hook
	 * @return string[]
	 */
	private function _parse_hooks($hook_string)
	{
		$hooks = array();
		$functions = explode(',', $hook_string);
		foreach ($functions as $function)
		{
			$function = trim($function);
			if (strpos($function, '|') !== false)
				list ($call, $file) = explode('|', $function);
			else
				$call = $function;

			$hooks[] = array('call' => $call, 'file' => !empty($file) ? $file : '');
		}

		return $hooks;
	}

	/**
	 * Loops through the functions associated with a hook
	 * and finds if the specified one exists
	 *
	 * @param string[] $expected_hook - the call and file (optional) expected
	 * @param string[] $hooks - Array oh hooks found in the db or $modSettings
	 * @return bool
	 */
	private function _find_hook($expected_hook, $hooks)
	{
		foreach ($hooks as $hook)
		{
			if ($expected_hook['call'] === $hook['call'] && (empty($expected_hook['file']) || $expected_hook['file'] === $hook['file']))
				return true;
		}
		return false;
	}
}
