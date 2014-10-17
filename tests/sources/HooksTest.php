<?php

/**
 * TestCase class for hooks adding/removing/other stuff
 * @backupGlobals disabled
 */
class TestHooks extends PHPUnit_Framework_TestCase
{
	/**
	 * Name of the hook used for testing
	 */
	private $_hook_name = 'integrate_testing_hook';

	/**
	 * Array holding all the test hooks
	 */
	private $_tests = array();

	/**
	 * Array holding all the test calls
	 */
	private $_call = array();

	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		$this->_tests = array(
			// A simple one, just the function, default (i.e. in theory permanent)
			array('call' => 'testing_hook', 'perm' => true),
			// Something a bit more complex: function + file
			array('call' => 'testing_hook2', 'file' => 'SOURCEDIR/Testing.php', 'perm' => true),
			// Static method + file
			array('call' => 'testing_class::hook', 'file' => 'SOURCEDIR/Testing.php', 'perm' => true),
			// Simple, just the function, non permanent
			array('call' => 'testing_hook_np', 'perm' => false),
			// Non permanent, function + file
			array('call' => 'testing_hook_np2', 'file' => 'SOURCEDIR/Testing_np.php', 'perm' => false),
			// Non permanent, static method + file
			array('call' => 'testing_class_np::hook', 'file' => 'SOURCEDIR/Testing.php', 'perm' => false),
		);

		foreach ($this->_tests as $key => $test)
			if (!isset($test['file']))
				$this->_tests[$key]['file'] = '';
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	 * Tests adding hooks in some different ways
	 */
	public function testAddHooks()
	{
		foreach ($this->_tests as $test)
		{
			add_integration_function($this->_hook_name, $test['call'], $test['file'], $test['perm']);
			$this->assertTrue($this->_hook_exists($test, $test['perm']));
		}
	}

	/**
	 * We want to see if they are called
	 *
	 * @depends testAddHooks
	 */
	public function testCallHooks()
	{
		$this->_call = call_integration_hook($this->_hook_name);

		foreach ($this->_tests as $test)
			$this->assertTrue($this->_is_hook_called($test['call'] . (!empty($test['file']) ? '|' . $test['file'] : '')));
	}

	/**
	 * And let's see what happens when removing them
	 *
	 * @depends testAddHooks
	 */
	public function testRemoveHooks()
	{
		foreach ($this->_tests as $test)
		{
			remove_integration_function($this->_hook_name, $test['call'], $test['file']);
			$this->assertFalse($this->_hook_exists($test, $test['perm']));
		}
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

	/**
	 * Determines if the hook has been executed by checking the returned value
	 *
	 * @param string $hook_string - the function/method called + file with the hook
	 * @return bool
	 */
	private function _is_hook_called($hook_string)
	{
		if (strpos($hook_string, '|') !== false)
		{
			$temp = explode('|', $hook_string);
			$function = $temp[0];
		}
		else
			$function = $hook_string;

		if (strpos($function, '::') !== false)
			$call = explode('::', $function);
		else
			$call = $function;

		$result = call_user_func_array($call, array());

		return $result === $this->_call[$hook_string];
	}
}

/**
 * A dummy function to test if hooks are called
 */
function testing_hook()
{
	return 'testing_hook_called';
}

/**
 * A dummy function to test if hooks are called
 */
function testing_hook2()
{
	return 'testing_hook2_called';
}

/**
 * A dummy function to test if hooks are called
 */
function testing_hook_np()
{
	return 'testing_hook_np_called';
}

/**
 * A dummy function to test if hooks are called
 */
function testing_hook_np2()
{
	return 'testing_hook_np2_called';
}

/**
 * A dummy class to test if hooks are called
 */
class testing_class
{
	/**
	 * Dummy function
	 */
	static public function hook()
	{
		return 'testing_class::hook';
	}
}

/**
 * A dummy class to test if hooks are called
 */
class testing_class_np
{
	/**
	 * Dummy function
	 */
	static public function hook()
	{
		return 'testing_class_np::hook';
	}
}