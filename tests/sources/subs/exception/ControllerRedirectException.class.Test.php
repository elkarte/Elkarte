<?php

use ElkArte\AbstractController;
use ElkArte\Exceptions\ControllerRedirectException;
use PHPUnit\Framework\TestCase;

/**
 * TestCase class for \ElkArte\Exceptions\ControllerRedirectException class.
 */
class TestControllerRedirectException extends TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	public function testBasicRedirect()
	{
		$exception = new ControllerRedirectException('Mock_Controller', 'action_plain');
		$result = $exception->doRedirect($this);

		$this->assertSame($result, 'success');
	}

	public function testPredispatchRedirect()
	{
		$exception = new ControllerRedirectException('Mockpre_Controller', 'action_plain');
		$result = $exception->doRedirect($this);

		$this->assertSame($result, 'success');
	}

	public function testSameControllerRedirect()
	{
		$same = new Same_Controller($this);
	}
}

class Same_Controller extends AbstractController
{
	public function __construct($tester)
	{
		$exception = new ControllerRedirectException('Same_Controller', 'action_plain');
		$result = $exception->doRedirect($this);

		$tester->assertSame($result, 'success');
	}

	public function action_index()
	{
	}

	public function action_plain()
	{
		return 'success';
	}
}

class Mock_Controller extends AbstractController
{
	public function action_index()
	{
	}

	public function action_plain()
	{
		return 'success';
	}
}

class Mockpre_Controller extends AbstractController
{
	protected $_pre_run = false;

	public function pre_dispatch()
	{
		$this->_pre_run = true;
	}

	public function action_index()
	{
	}

	public function action_plain()
	{
		if ($this->_pre_run)
			return 'success';
		else
			return 'fail';
	}
}
