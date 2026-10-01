<?php

namespace ElkArte;

use ElkArte\Helper\HttpReq;
use PHPUnit\Framework\TestCase;

/**
 * TestCase class for Action class.
 */
class ActionTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		require_once(__DIR__ . '/../../sources/Load.php');
		require_once(__DIR__ . '/../../sources/Subs.php');
		require_once(__DIR__ . '/../../sources/Security.php');
	}

	public function testInitializeDefault()
	{
		$req = HttpReq::instance();
		$action = new Action(null, $req);

		$called = false;
		$subActions = [
			'test1' => function () use (&$called) {
				$called = true;
			},
			'test2' => function () {}
		];

		$sa = $action->initialize($subActions, 'test1');
		$this->assertSame('test1', $sa);
	}

	public function testDispatchValidSubaction()
	{
		$req = HttpReq::instance();
		$action = new Action(null, $req);

		$executed = '';
		$subActions = [
			'action1' => function () use (&$executed) {
				$executed = 'action1';
			},
			'action2' => function () use (&$executed) {
				$executed = 'action2';
			}
		];

		$action->initialize($subActions, 'action1');
		$action->dispatch('action2');

		$this->assertSame('action2', $executed);
	}

	public function testDispatchFallbackToDefaultSubaction()
	{
		$req = HttpReq::instance();
		$action = new Action(null, $req);

		$executed = '';
		$subActions = [
			'default_action' => function () use (&$executed) {
				$executed = 'default_action';
			},
			'other_action' => function () use (&$executed) {
				$executed = 'other_action';
			}
		];

		$action->initialize($subActions, 'default_action');
		$action->dispatch('unknown_action');

		$this->assertSame('default_action', $executed);
	}

	public function testDispatchEvaluatesPermissionOnFallback()
	{
		$req = HttpReq::instance();

		// Custom subclass to verify which sub_id is passed to isAllowedTo
		$action = new class(null, $req) extends Action {
			public $checkedSubId = null;

			protected function isAllowedTo(string $sub_id): bool
			{
				$this->checkedSubId = $sub_id;
				return true;
			}
		};

		$executed = '';
		$subActions = [
			'default_action' => [
				'function' => function () use (&$executed) {
					$executed = 'default_action';
				},
				'permission' => 'admin_forum'
			],
			'other_action' => [
				'function' => function () use (&$executed) {
					$executed = 'other_action';
				}
			]
		];

		$action->initialize($subActions, 'default_action');
		$action->dispatch('invalid_subaction');

		// The resolved subaction passed to isAllowedTo must be 'default_action', not 'invalid_subaction'
		$this->assertSame('default_action', $action->checkedSubId);
		$this->assertSame('default_action', $executed);
	}

	public function testInitializeFiltersDisabledAndEnabled()
	{
		$req = HttpReq::instance();
		$action = new Action(null, $req);

		$subActions = [
			'enabled_action' => [
				'function' => function () {},
				'enabled' => true,
			],
			'disabled_action' => [
				'function' => function () {},
				'disabled' => true,
			],
			'not_enabled_action' => [
				'function' => function () {},
				'enabled' => false,
			],
		];

		$sa = $action->initialize($subActions, 'enabled_action');
		$this->assertSame('enabled_action', $sa);

		// Attempting to dispatch a disabled action falls back to default
		$executed = false;
		$subActions = [
			'default_sa' => [
				'function' => function () use (&$executed) {
					$executed = true;
				},
			],
			'disabled_sa' => [
				'function' => function () {},
				'disabled' => true,
			],
		];

		$action->initialize($subActions, 'default_sa');
		$action->dispatch('disabled_sa');
		$this->assertTrue($executed);
	}

	public function testDispatchWithControllerObject()
	{
		$req = HttpReq::instance();
		$action = new Action(null, $req);

		$controller = new class {
			public $called = false;
			public function myMethod() {
				$this->called = true;
			}
		};

		$subActions = [
			'test_controller' => [
				'controller' => $controller,
				'function' => 'myMethod',
			]
		];

		$action->initialize($subActions, 'test_controller');
		$action->dispatch('test_controller');

		$this->assertTrue($controller->called);
	}

	public function testDispatchWithArrayCallable()
	{
		$req = HttpReq::instance();
		$action = new Action(null, $req);

		$obj = new class {
			public $called = false;
			public function handler() {
				$this->called = true;
			}
		};

		$subActions = [
			'test_callable' => [$obj, 'handler']
		];

		$action->initialize($subActions, 'test_callable');
		$action->dispatch('test_callable');

		$this->assertTrue($obj->called);
	}
}
