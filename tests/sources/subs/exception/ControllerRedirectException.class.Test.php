<?php

/**
 * TestCase class for \ElkArte\Exceptions\ControllerRedirectException class.
 */
class TestControllerRedirectException extends \PHPUnit\Framework\TestCase
{
	public function testBasicRedirect()
	{
		$exception = new \ElkArte\Exceptions\ControllerRedirectException('Mock_Controller', 'action_plain');
		$result = $exception->doRedirect($this);

		$this->assertSame($result, 'success');
	}

	public function testPredispatchRedirect()
	{
		$exception = new \ElkArte\Exceptions\ControllerRedirectException('Mockpre_Controller', 'action_plain');
		$result = $exception->doRedirect($this);

		$this->assertSame($result, 'success');
	}

	public function testSameControllerRedirect()
	{
		$same = new Same_Controller($this);
	}
}

class Same_Controller extends Action_Controller
{
	public function __construct($tester)
	{
		$exception = new \ElkArte\Exceptions\ControllerRedirectException('Same_Controller', 'action_plain');
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

class Mock_Controller extends Action_Controller
{
	public function action_index()
	{
	}

	public function action_plain()
	{
		return 'success';
	}
}

class Mockpre_Controller extends Action_Controller
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
