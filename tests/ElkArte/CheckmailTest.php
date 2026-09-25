<?php

namespace ElkArte\Mail;

use ElkArte\Cache\Cache;
use ElkArte\Errors\ErrorContext;
use PHPUnit\Framework\TestCase;
use function ElkArte\check_mail_validate_email;
use function ElkArte\irc_check_mail;
use function ElkArte\setOptions;

class CheckmailTest extends TestCase
{
	protected function setUp(): void
	{
		require_once(ADDONSDIR . '/Checkmail.php');
	}

	public function testSetOptions()
	{
		global $modSettings;

		$modSettings['check_mail_key'] = 'my_test_api_key';
		$options = setOptions();

		$this->assertSame('POST', $options[CURLOPT_CUSTOMREQUEST]);
		$this->assertContains('Authorization: Bearer my_test_api_key', $options[CURLOPT_HTTPHEADER]);
		$this->assertContains('Accept: application/json', $options[CURLOPT_HTTPHEADER]);
		$this->assertContains('Content-Type: application/x-www-form-urlencoded', $options[CURLOPT_HTTPHEADER]);

		$optionsCustom = setOptions('override_key');
		$this->assertContains('Authorization: Bearer override_key', $optionsCustom[CURLOPT_HTTPHEADER]);
	}

	public function testValidateEmailCache()
	{
		global $modSettings;

		$modSettings['check_mail_enabled'] = 1;
		$modSettings['check_mail_key'] = 'dummy_key';

		$email = 'cached_test@example.com';
		$cacheKey = 'check_mail_' . md5(strtolower($email));

		$cache = Cache::instance();
		$cache->put($cacheKey, 'dea', 3600);

		$this->assertSame('dea', check_mail_validate_email($email));

		$cache->put($cacheKey, 'invalid', 3600);
		$this->assertSame('invalid', check_mail_validate_email($email));

		$cache->put($cacheKey, true, 3600);
		$this->assertTrue(check_mail_validate_email($email));

		// Clear cache key
		$cache->put($cacheKey, null, 1);
	}

	public function testIrcCheckMailHook()
	{
		global $modSettings;

		$modSettings['check_mail_enabled'] = 1;
		$modSettings['check_mail_key'] = 'dummy_key';

		$email = 'hook_test@example.com';
		$cacheKey = 'check_mail_' . md5(strtolower($email));

		$cache = Cache::instance();
		$cache->put($cacheKey, 'dea', 3600);

		$errors = ErrorContext::context('register', 0);
		irc_check_mail(['email' => $email], $errors);

		$this->assertTrue($errors->hasErrors());

		$cache->put($cacheKey, null, 1);
	}
}
