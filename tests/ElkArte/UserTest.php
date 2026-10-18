<?php

declare(strict_types=1);

namespace ElkArte;

use ElkArte\Cache\Cache;
use ElkArte\Helper\ValuesContainer;
use tests\ElkArteCommonSetupTest;

require_once __DIR__ . '/UserSettingsLoaderTest.php';

class UserTest extends ElkArteCommonSetupTest
{
	protected function setUp(): void
	{
		parent::setUp();
		User::reset(true);
	}

	protected function tearDown(): void
	{
		User::reset(true);
		parent::tearDown();
	}

	public function testResetClearsStaticStateAndCompatGlobal(): void
	{
		global $user_info;

		User::$info = new UserInfo(['id' => 5, 'name' => 'Tester', 'is_guest' => false]);
		$user_info = User::$info;

		$this->assertNotNull(User::$info);
		$this->assertNotNull($user_info);

		User::reset(true);

		$this->assertNull(User::$info);
		$this->assertNull(User::$settings);
		$this->assertNull($user_info);
	}

	public function testLoadWithoutForceMemoizesInstance(): void
	{
		global $user_info;

		$loader1 = new UserSettingsLoaderTestDouble(database(), Cache::instance(), request());
		$loader1->setVerificationResult(true);
		$loader1->loadUserById(1, true, '');

		User::reloadByUser($loader1, true);
		$firstInfo = User::$info;
		$this->assertSame(1, User::$info->id);

		// Normal load() should keep the existing instance (no-op)
		User::load(true);
		$this->assertSame($firstInfo, User::$info);
	}

	public function testLoadWithForceResetsAndReloads(): void
	{
		global $user_info, $modSettings;

		$loader = new UserSettingsLoaderTestDouble(database(), Cache::instance(), request());
		$loader->setVerificationResult(true);
		$loader->loadUserById(1, true, '');
		User::reloadByUser($loader, true);

		$this->assertSame(1, User::$info->id);

		// With force=true and no cookies/session, it reloads as guest (id 0)
		User::load(true, true);

		$this->assertNotNull(User::$info);
		$this->assertSame(0, User::$info->id);
		$this->assertTrue(User::$info->is_guest);
		$this->assertSame(User::$info, $user_info);
	}

	public function testReloadResetsAndReloadsUser(): void
	{
		global $user_info;

		$loader = new UserSettingsLoaderTestDouble(database(), Cache::instance(), request());
		$loader->setVerificationResult(true);
		$loader->loadUserById(1, true, '');
		User::reloadByUser($loader, true);

		$this->assertSame(1, User::$info->id);
		$this->assertFalse(User::$info->is_guest);

		// Reload without cookies/session resets and loads guest
		User::reload(true);

		$this->assertNotNull(User::$info);
		$this->assertSame(0, User::$info->id);
		$this->assertTrue(User::$info->is_guest);
		$this->assertSame(User::$info, $user_info);
	}

	public function testLogOutUserSetsGuestState(): void
	{
		global $user_info;

		$loader = new UserSettingsLoaderTestDouble(database(), Cache::instance(), request());
		$loader->setVerificationResult(true);
		$loader->loadUserById(1, true, '');
		User::reloadByUser($loader, true);

		$this->assertSame(1, User::$info->id);
		$this->assertFalse(User::$info->is_guest);

		User::logOutUser(true);

		$this->assertNotNull(User::$info);
		$this->assertSame(0, User::$info->id);
		$this->assertTrue(User::$info->is_guest);
		$this->assertSame(User::$info, $user_info);
	}

	public function testLogOutUserWhenInstanceIsNull(): void
	{
		global $user_info;

		User::reset(true);
		User::logOutUser(true);

		$this->assertNotNull(User::$info);
		$this->assertSame(0, User::$info->id);
		$this->assertTrue(User::$info->is_guest);
		$this->assertSame(User::$info, $user_info);
	}
}
