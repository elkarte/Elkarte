<?php

declare(strict_types=1);

namespace ElkArte;

use ElkArte\Cache\Cache;
use tests\ElkArteCommonSetupTest;

class UserSettingsLoaderTest extends ElkArteCommonSetupTest
{
	public function testFailedVerificationFallsBackToGuest(): void
	{
		$loader = new UserSettingsLoaderTestDouble(database(), Cache::instance(), request());
		$loader->setVerificationResult(false);

		$loader->loadUserById(1, false, str_repeat('a', 64));

		$info = $loader->getInfo();
		$this->assertTrue($loader->guestInitCalled());
		$this->assertFalse($loader->userInitCalled());
		$this->assertTrue($info->is_guest);
		$this->assertFalse($info->is_admin);
		$this->assertSame([-1], $info->groups);
	}

	public function testVerifiedUserStillInitializesUser(): void
	{
		$loader = new UserSettingsLoaderTestDouble(database(), Cache::instance(), request());
		$loader->setVerificationResult(true);

		$loader->loadUserById(1, true, '');

		$info = $loader->getInfo();
		$this->assertTrue($loader->userInitCalled());
		$this->assertFalse($loader->guestInitCalled());
		$this->assertFalse($info->is_guest);
		$this->assertTrue($info->is_admin);
		$this->assertSame([1], $info->groups);
	}
}

class UserSettingsLoaderTestDouble extends UserSettingsLoader
{
	private bool $verified = false;
	private bool $calledInitUser = false;
	private bool $calledInitGuest = false;

	public function setVerificationResult(bool $verified): void
	{
		$this->verified = $verified;
	}

	public function userInitCalled(): bool
	{
		return $this->calledInitUser;
	}

	public function guestInitCalled(): bool
	{
		return $this->calledInitGuest;
	}

	protected function loadUserData($already_verified, $session_password): void
	{
		$this->id = $this->verified ? 1 : 0;
	}

	protected function initUser(): array
	{
		$this->calledInitUser = true;

		return [
			'groups' => [1],
			'possibly_robot' => false,
		];
	}

	protected function initGuest(): array
	{
		$this->calledInitGuest = true;

		return [
			'groups' => [-1],
			'possibly_robot' => false,
		];
	}

	protected function compileInfo($user_info): void
	{
		$user_info += [
			'id' => $this->id,
			'is_guest' => $this->id === 0,
			'is_admin' => in_array(1, $user_info['groups'], true),
		];

		$this->info = new UserInfo($user_info);
	}
}
