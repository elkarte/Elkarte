<?php

/**
 * TestCase class for \ElkArte\Exceptions\ControllerRedirectException class.
 */

namespace ElkArte\Exceptions;

use ElkArte\AbstractController;
use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use ElkArte\SiteDispatcher;
use ElkArte\User;
use PHPUnit\Framework\TestCase;

class ControllerRedirectExceptionTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info', 'modSettings', 'context'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		require_once(__DIR__ . '/../../../sources/Load.php');
		require_once(__DIR__ . '/../../../sources/Subs.php');

		$rc = new \ReflectionClass(Hooks::class);
		$hooksInstance = $rc->newInstanceWithoutConstructor();

		$ref = new \ReflectionProperty(Hooks::class, '_instance');
		$ref->setAccessible(true);
		$ref->setValue(null, $hooksInstance);

		$mockLoader = new class {
			public function load($file_name, $fatal = true, $fix_calendar_arrays = false): void {}
			public function setFallback(bool $newStatus): void {}
		};
		$txtRef = new \ReflectionProperty(Txt::class, 'loader');
		$txtRef->setAccessible(true);
		$txtRef->setValue(null, $mockLoader);
	}

	protected function setUp(): void
	{
		global $modSettings;

		$modSettings = [];
		User::$info = new ValuesContainer([
			'id' => 1,
			'name' => 'Tester',
			'is_admin' => true,
			'is_guest' => false,
			'permissions' => ['admin_forum'],
		]);
	}

	public function testGetters()
	{
		$exception = new ControllerRedirectException('Mock_Controller', 'action_plain');
		$this->assertSame('Mock_Controller', $exception->getController());
		$this->assertSame('action_plain', $exception->getMethod());
	}

	public function testBasicRedirect()
	{
		$exception = new ControllerRedirectException(Mock_Controller::class, 'action_plain');
		$result = $exception->doRedirect($this);

		$this->assertSame('success', $result);
	}

	public function testPredispatchRedirect()
	{
		$exception = new ControllerRedirectException(Mockpre_Controller::class, 'action_plain');
		$result = $exception->doRedirect($this);

		$this->assertSame('success', $result);
	}

	public function testSameControllerRedirect()
	{
		$same = new Same_Controller($this);
	}

	public function testSiteDispatcherCatchesRedirectException()
	{
		$req = HttpReq::instance();
		$req->query->action = 'testredirect';

		$dispatcher = new class($req) extends SiteDispatcher {
			public function __construct(HttpReq $req)
			{
				$this->action = 'testredirect';
				$this->_controller_name = RedirectSource_Controller::class;
				$this->_function_name = 'action_start';
				$this->_controller = new $this->_controller_name(new EventManager(), User::$info);
			}
		};

		$result = $dispatcher->dispatch();
		$this->assertSame('target_reached', $result);
		$this->assertInstanceOf(RedirectTarget_Controller::class, $dispatcher->getController());
	}

	public function testSiteDispatcherSameControllerRedirect()
	{
		$req = HttpReq::instance();
		$dispatcher = new class($req) extends SiteDispatcher {
			public function __construct(HttpReq $req)
			{
				$this->action = 'testredirect';
				$this->_controller_name = RedirectSame_Controller::class;
				$this->_function_name = 'action_step1';
				$this->_controller = new $this->_controller_name(new EventManager(), User::$info);
			}
		};

		$result = $dispatcher->dispatch();
		$this->assertSame('step2_reached', $result);
	}

	public function testSiteDispatcherPreventsInfiniteRedirectLoop()
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('redirect_loop');

		$req = HttpReq::instance();
		$dispatcher = new class($req) extends SiteDispatcher {
			public function __construct(HttpReq $req)
			{
				$this->action = 'testredirect';
				$this->_controller_name = InfiniteLoop_Controller::class;
				$this->_function_name = 'action_loop';
				$this->_controller = new $this->_controller_name(new EventManager(), User::$info);
			}
		};

		$dispatcher->dispatch();
	}

	public function testEmptyRedirectReturnsNull()
	{
		$exception = new ControllerRedirectException('', '');
		$result = $exception->doRedirect($this);
		$this->assertNull($result);
	}
}

class Same_Controller extends AbstractController
{
	public function __construct($tester)
	{
		parent::__construct(new EventManager());
		$exception = new ControllerRedirectException(Same_Controller::class, 'action_plain');
		$result = $exception->doRedirect($this);

		$tester->assertSame('success', $result);
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
		{
			return 'success';
		}

		return 'fail';
	}
}

class RedirectSource_Controller extends AbstractController
{
	public function action_start()
	{
		throw new ControllerRedirectException(RedirectTarget_Controller::class, 'action_target');
	}

	public function action_index()
	{
	}
}

class RedirectTarget_Controller extends AbstractController
{
	public function action_target()
	{
		return 'target_reached';
	}

	public function action_index()
	{
	}
}

class RedirectSame_Controller extends AbstractController
{
	public function action_step1()
	{
		throw new ControllerRedirectException(RedirectSame_Controller::class, 'action_step2');
	}

	public function action_step2()
	{
		return 'step2_reached';
	}

	public function action_index()
	{
	}
}

class InfiniteLoop_Controller extends AbstractController
{
	public function action_loop()
	{
		throw new ControllerRedirectException(InfiniteLoop_Controller::class, 'action_loop');
	}

	public function action_index()
	{
	}
}
