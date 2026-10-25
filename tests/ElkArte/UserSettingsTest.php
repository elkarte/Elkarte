<?php

declare(strict_types=1);

namespace ElkArte;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/sources/Subs.php';

class UserSettingsTest extends TestCase
{
	public function testRehashPasswordSetsCost12(): void
	{
		$settings = new UserSettings([
			'id_member' => 1,
			'member_name' => 'testuser',
			'passwd' => '',
			'password_salt' => '',
		]);

		$password = 'SecretPassword123';
		$settings->rehashPassword($password);

		$this->assertNotEmpty($settings->passwd);
		$this->assertNotEmpty($settings->password_salt);

		$info = password_get_info($settings->passwd);
		$this->assertSame(PASSWORD_BCRYPT, $info['algo']);
		$this->assertSame(12, $info['options']['cost']);

		$this->assertTrue($settings->validatePassword($password));
		$this->assertFalse($settings->validatePassword('WrongPassword'));
		$this->assertFalse(password_needs_rehash($settings->passwd, PASSWORD_BCRYPT, ['cost' => 12]));
	}

	public function testValidatePasswordWithLegacyCost10Hash(): void
	{
		$username = 'testuser';
		$password = 'SecretPassword123';
		$sha256 = hash('sha256', strtolower($username) . $password);
		$cost10Hash = password_hash($sha256, PASSWORD_BCRYPT, ['cost' => 10]);

		$settings = new UserSettings([
			'id_member' => 1,
			'member_name' => $username,
			'passwd' => $cost10Hash,
			'password_salt' => 'randomsalt123',
		]);

		$this->assertTrue($settings->validatePassword($password));
		$this->assertTrue(password_needs_rehash($settings->passwd, PASSWORD_BCRYPT, ['cost' => 12]));

		// Rehash to cost 12
		$settings->rehashPassword($password);
		$this->assertTrue($settings->validatePassword($password));
		$this->assertFalse(password_needs_rehash($settings->passwd, PASSWORD_BCRYPT, ['cost' => 12]));
		$info = password_get_info($settings->passwd);
		$this->assertSame(12, $info['options']['cost']);
	}
}
