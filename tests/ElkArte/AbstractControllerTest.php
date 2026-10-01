<?php

namespace ElkArte;

use ElkArte\Helper\ValuesContainer;
use PHPUnit\Framework\TestCase;

/**
 * TestCase class for AbstractController class.
 */
class AbstractControllerTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		require_once(__DIR__ . '/../../sources/Load.php');
	}

	protected function setUp(): void
	{
		User::$info = null;
	}

	protected function tearDown(): void
	{
		User::$info = null;
	}

	public function testDefaultUserNullWhenUserInfoNotSet()
	{
		$eventManager = new EventManager();
		$controller = new class($eventManager) extends AbstractController {
			public function action_index() {}
		};

		$this->assertNull($controller->getUser());
	}

	public function testDefaultUserPopulatedFromUserInfo()
	{
		$userData = new ValuesContainer([
			'id' => 42,
			'name' => 'TestUser',
			'is_admin' => true,
			'is_guest' => false,
		]);
		User::$info = $userData;

		$eventManager = new EventManager();
		$controller = new class($eventManager) extends AbstractController {
			public function action_index() {}
		};

		$this->assertSame($userData, $controller->getUser());
	}

	public function testExplicitUserPassedToConstructor()
	{
		$defaultUser = new ValuesContainer(['id' => 1, 'name' => 'Default']);
		User::$info = $defaultUser;

		$customUser = new ValuesContainer(['id' => 99, 'name' => 'Custom']);

		$eventManager = new EventManager();
		$controller = new class($eventManager, $customUser) extends AbstractController {
			public function action_index() {}
		};

		$this->assertSame($customUser, $controller->getUser());
	}

	public function testSetUserUpdatesUserProperty()
	{
		$eventManager = new EventManager();
		$controller = new class($eventManager) extends AbstractController {
			public function action_index() {}
		};

		$this->assertNull($controller->getUser());

		$newUser = new ValuesContainer(['id' => 10, 'name' => 'UpdatedUser']);
		$controller->setUser($newUser);

		$this->assertSame($newUser, $controller->getUser());
	}
}
