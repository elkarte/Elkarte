<?php

namespace tests\ElkArte\Security;

use ElkArte\Controller\Auth;
use ElkArte\Database\AbstractResult;
use ElkArte\Database\QueryInterface;
use ElkArte\Errors\Errors;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\HttpReq;
use ElkArte\Hooks;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\Request;
use ElkArte\Themes\Templates;
use ElkArte\Themes\Theme;
use ElkArte\User;
use ElkArte\UserInfo;
use ElkArte\UserSettings;
use tests\ElkArteCommonSetupTest;

require_once dirname(__DIR__, 2) . '/ElkArteCommonSetupTest.php';

class AuthSessionTest extends ElkArteCommonSetupTest
{
	protected $backupGlobals = true;
	protected $backupGlobalsExcludeList = ['user_info'];

	private array $staticState = [];
	private $db;
	private $templates;

	public static function setUpBeforeClass(): void
	{
		$root = dirname(__DIR__, 3);
		foreach ([
			'ELK' => '1',
			'BOARDDIR' => $root,
			'SOURCEDIR' => $root . '/sources',
			'SUBSDIR' => $root . '/sources/subs',
			'ADMINDIR' => $root . '/sources/ElkArte/AdminController',
			'CONTROLLERDIR' => $root . '/sources/ElkArte/Controller',
			'LANGUAGEDIR' => $root . '/sources/ElkArte/Languages',
			'CACHEDIR' => $root . '/cache',
			'EXTDIR' => $root . '/sources/ext',
			'ADDONSDIR' => $root . '/Addons',
		] as $name => $value)
		{
			if (!defined($name))
			{
				define($name, $value);
			}
		}

		require_once SOURCEDIR . '/Load.php';
		require_once SOURCEDIR . '/Subs.php';
		require_once SOURCEDIR . '/Security.php';
		require_once SOURCEDIR . '/database/Database.subs.php';
	}

	protected function setUp(): void
	{
		global $context, $modSettings, $settings, $boardurl, $scripturl, $db_instance, $txt, $language, $url_format;

		// Use isolated guests/members and a mock database instead of loading a real account.
		foreach ([
			[Request::class, '_instance'],
			[HttpReq::class, 'instance'],
			[Headers::class, 'instance'],
			[Txt::class, 'loader'],
			[Hooks::class, '_instance'],
			[Errors::class, '_errors'],
			[User::class, 'info'],
			[User::class, 'settings'],
		] as [$class, $name])
		{
			$property = new \ReflectionProperty($class, $name);
			$property->setAccessible(true);
			$this->staticState[] = [$property, $property->getValue()];
			$property->setValue(null, null);
		}

		$_GET = $_POST = $_REQUEST = [];
		$_COOKIE = ['PHPSESSID' => 'test-session'];
		$_SESSION = ['session_var' => 'csrf', 'session_value' => 'current-token'];
		$_SERVER['HTTP_USER_AGENT'] = 'Desktop browser';
		$_SERVER['HTTP_HOST'] = 'forum.example';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['HTTP_REFERER'] = 'http://forum.example/index.php';
		unset($_SERVER['HTTP_PURPOSE'], $_SERVER['HTTP_X_MOZ']);
		$boardurl = 'http://forum.example';
		$scripturl = $boardurl . '/index.php';
		$url_format = 'standard';
		$language = 'English';
		$settings = [];
		$txt = ['login' => 'Log in', 'logout' => 'Log out', 'error_occurred' => 'An error occurred'];
		$modSettings = ['spamWaitTime' => 2, 'cookieTime' => 60, 'failed_login_threshold' => 3];
		$context = ['session_var' => 'csrf', 'session_id' => 'current-token'];
		User::$info = new UserInfo([
			'id' => 0, 'is_guest' => true, 'is_admin' => false, 'language' => 'English',
			'ip' => '127.0.0.1', 'permissions' => [],
		]);

		$result = $this->createMock(AbstractResult::class);
		$result->method('fetch_assoc')->willReturn(false);
		$result->method('affected_rows')->willReturn(1);
		$this->db = $this->createMock(QueryInterface::class);
		$this->db->method('fetchQuery')->willReturn($result);
		$this->db->method('query')->willReturn($result);
		$this->db->method('replace')->willReturn($result);
		$db_instance = $this->db;

		$this->templates = $this->getMockBuilder(Templates::class)->disableOriginalConstructor()->getMock();
		// Only template loading is needed to prepare the login and logout forms.
		$theme = $this->getMockBuilder(Theme::class)->disableOriginalConstructor()->onlyMethods(['getSettings', 'getTemplates'])->getMock();
		$theme->method('getTemplates')->willReturn($this->templates);
		$context['theme_instance'] = $theme;
	}

	protected function tearDown(): void
	{
		foreach (array_reverse($this->staticState) as [$property, $value])
		{
			$property->setValue(null, $value);
		}
		$this->staticState = [];
	}

	public static function userAgents(): array
	{
		return [['Desktop browser'], ['Mobile browser'], [null]];
	}

	/** @dataProvider userAgents */
	public function testAuthenticationTokenChecksAllowUserAgentChanges(?string $savedAgent): void
	{
		$this->setSavedAgent($savedAgent);
		$_POST['csrf'] = $_GET['csrf'] = 'current-token';
		$this->assertSame('', checkSession('post', '', false, false));
		$this->assertSame('', checkSession('get', '', false, false));
	}

	public function testOtherActionsStillRejectChangedOrMissingUserAgents(): void
	{
		$_POST['csrf'] = 'current-token';
		foreach (['Mobile browser', null] as $agent)
		{
			$this->setSavedAgent($agent);
			$this->assertSame('session_verify_fail', checkSession('post', '', false));
		}
		$this->setSavedAgent('Desktop browser');
		$this->assertSame('', checkSession('post', '', false));
	}

	public function testAuthenticationStillRequiresSessionTokensAndSameSiteReferrers(): void
	{
		foreach ([null, 'expired-token'] as $token)
		{
			$_POST['csrf'] = $_GET['csrf'] = $token;
			$this->assertSame('session_timeout', checkSession('post', '', false, false));
			$this->assertSame('session_verify_fail', checkSession('get', '', false, false));
		}
		$_POST['csrf'] = $_GET['csrf'] = 'current-token';
		$_SERVER['HTTP_REFERER'] = 'http://foreign.example/index.php';
		$this->assertSame('verify_url_fail', checkSession('post', '', false, false));
		$this->assertSame('verify_url_fail', checkSession('get', '', false, false));
	}

	/** @dataProvider userAgents */
	public function testLoginReachesCredentialValidationWithChangedUserAgent(?string $savedAgent): void
	{
		global $context, $txt;
		$this->setSavedAgent($savedAgent);
		$_POST['csrf'] = 'current-token';
		createToken('login');
		$this->assertFalse((new Auth(new EventManager()))->action_login2());
		$this->assertSame([$txt['need_username']], $context['login_errors']);
		$this->assertArrayNotHasKey('post-login', $_SESSION['token']);
	}

	public static function expiredTokens(): array
	{
		return [[null], ['expired-token']];
	}

	/** @dataProvider expiredTokens */
	public function testExpiredLoginFormRecoversBeforeCredentialProcessing(?string $token): void
	{
		global $context, $txt;
		$this->db->expects($this->never())->method('replace');
		$this->db->expects($this->never())->method('query');
		$_POST = ['user' => '<member "name">', 'passwrd' => 'secret', 'otp_token' => '123456'];
		if ($token !== null)
		{
			$_POST['csrf'] = $token;
		}
		$this->assertFalse((new Auth(new EventManager()))->action_login2());
		$this->assertSame('login', $context['sub_template']);
		$this->assertSame([$txt['login_session_expired']], $context['login_errors']);
		$this->assertSame('&lt;member &quot;name&quot;&gt;', $context['default_username']);
		$this->assertSame('', $context['default_password']);
		$this->assertNotEmpty($context['login_token']);
		$this->assertSame($context['login_token'], $_SESSION['token']['post-login'][0][3]);
		$_POST['csrf'] = $context['session_id'];
		$this->assertSame('', checkSession('post', '', false, false));
	}

	/** @dataProvider userAgents */
	public function testLogoutReachesCleanupWithChangedUserAgent(?string $savedAgent): void
	{
		global $modSettings;
		$this->setMember();
		$this->setSavedAgent($savedAgent);
		$_GET['csrf'] = 'current-token';
		$modSettings['integrate_logout'] = self::class . '::stopLogout';
		$this->expectExceptionMessage('LOGOUT_VERIFIED');
		(new Auth(new EventManager()))->action_logout();
	}

	/** @dataProvider expiredTokens */
	public function testStaleLogoutRequestsRequireConfirmation(?string $token): void
	{
		$this->setMember();
		$this->db->expects($this->never())->method('query');
		$this->db->expects($this->never())->method('replace');
		if ($token !== null)
		{
			$_GET['csrf'] = $token;
		}
		$this->templates->expects($this->once())->method('load')->with('Login')
			->willThrowException(new \RuntimeException('LOGOUT_CONFIRMATION'));
		$this->expectExceptionMessage('LOGOUT_CONFIRMATION');
		(new Auth(new EventManager()))->action_logout();
	}

	public function testInternalLogoutStillSkipsSessionVerification(): void
	{
		global $modSettings;
		$this->setMember();
		$modSettings['integrate_logout'] = self::class . '::stopLogout';
		$this->expectExceptionMessage('LOGOUT_VERIFIED');
		(new Auth(new EventManager()))->action_logout(true, false);
	}

	public function testGuestLogoutRedirectsWithoutCleanup(): void
	{
		$this->db->expects($this->never())->method('query');
		$this->db->expects($this->never())->method('replace');
		$headers = $this->getMockBuilder(Headers::class)->disableOriginalConstructor()->onlyMethods(['removeHeader', 'redirect'])->getMock();
		$headers->method('removeHeader')->willReturnSelf();
		$headers->expects($this->once())->method('redirect')->with('')
			->willThrowException(new \RuntimeException('GUEST_REDIRECT'));
		$property = new \ReflectionProperty(Headers::class, 'instance');
		$property->setAccessible(true);
		$property->setValue(null, $headers);
		$this->expectExceptionMessage('GUEST_REDIRECT');
		(new Auth(new EventManager()))->action_logout();
	}

	public static function authenticationActions(): array
	{
		return [['action_login2'], ['action_logout']];
	}

	/** @dataProvider authenticationActions */
	public function testAuthenticationRejectsForeignReferrers(string $action): void
	{
		if ($action === 'action_logout')
		{
			$this->setMember();
		}
		$this->setSavedAgent('Mobile browser');
		$_POST['csrf'] = $_GET['csrf'] = 'current-token';
		$_SERVER['HTTP_REFERER'] = 'http://foreign.example/index.php';
		$this->db->expects($this->never())->method('query');
		$this->db->expects($this->never())->method('replace');
		$this->templates->expects($this->never())->method('load');
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Unable to verify referring URL: http://foreign.example/index.php');
		(new Auth(new EventManager()))->{$action}();
	}

	public function testCapturedSessionErrorRetainsLoggingAndFormatting(): void
	{
		global $txt;
		Txt::load('Errors');
		$referrer = 'http://foreign.example/?q=<tag>&name="test"';
		$escapedReferrer = 'http://foreign.example/?q=&lt;tag&gt;&amp;name=&quot;test&quot;';
		$expectedMessage = sprintf($txt['verify_url_fail'], $escapedReferrer);
		$errors = $this->getMockBuilder(Errors::class)->disableOriginalConstructor()->onlyMethods(['log_error'])->getMock();
		$errors->expects($this->once())->method('log_error')->with($expectedMessage, 'user')->willReturn($expectedMessage);
		$property = new \ReflectionProperty(Errors::class, '_errors');
		$property->setAccessible(true);
		$property->setValue(null, $errors);

		$_POST['csrf'] = 'current-token';
		$_SERVER['HTTP_REFERER'] = $referrer;
		$sessionError = checkSessionRequest('post', '', false);
		$this->assertSame([
			'error' => 'verify_url_fail', 'log' => 'user', 'sprintf' => [$escapedReferrer],
		], $sessionError);

		// Reporting must use the captured failure even if the request has since changed.
		$_SERVER['HTTP_REFERER'] = 'http://forum.example/index.php';
		$this->expectException(Exception::class);
		$this->expectExceptionMessage($expectedMessage);
		handleSessionError($sessionError);
	}

	public function testLegacyNonFatalCheckReturnsErrorWithoutLogging(): void
	{
		$errors = $this->getMockBuilder(Errors::class)->disableOriginalConstructor()->onlyMethods(['log_error'])->getMock();
		$errors->expects($this->never())->method('log_error');
		$property = new \ReflectionProperty(Errors::class, '_errors');
		$property->setAccessible(true);
		$property->setValue(null, $errors);
		$_POST['csrf'] = 'current-token';
		$_SERVER['HTTP_REFERER'] = 'http://foreign.example/';
		$this->assertSame('verify_url_fail', checkSession('post', '', false, false));
	}

	public function testLegacyFatalTokenFailureIsNotLogged(): void
	{
		global $txt;
		Txt::load('Errors');
		$errors = $this->getMockBuilder(Errors::class)->disableOriginalConstructor()->onlyMethods(['log_error'])->getMock();
		$errors->expects($this->never())->method('log_error');
		$property = new \ReflectionProperty(Errors::class, '_errors');
		$property->setAccessible(true);
		$property->setValue(null, $errors);
		$this->expectException(Exception::class);
		$this->expectExceptionMessage($txt['session_timeout']);
		checkSession('post', '', true, false);
	}

	public function testReferringActionFailureRetainsItsFormattingArguments(): void
	{
		$_POST['csrf'] = 'current-token';
		$_SESSION['old_url'] = 'http://forum.example/index.php?action=other';
		$this->assertSame([
			'error' => 'verify_url_fail', 'log' => 'user', 'sprintf' => [$_SERVER['HTTP_REFERER']],
		], checkSessionRequest('post', 'post', false));
	}

	public static function apiSessionChecks(): array
	{
		return [['checkSession'], ['action_login2'], ['action_logout']];
	}

	/** @dataProvider apiSessionChecks */
	public function testApiFailuresKeepTheir403Response(string $entrypoint): void
	{
		if ($entrypoint === 'action_logout')
		{
			$this->setMember();
		}
		$_POST['csrf'] = $_GET['csrf'] = 'current-token';
		$_SERVER['HTTP_REFERER'] = 'http://foreign.example/';
		$_REQUEST['api'] = 'xml';
		$errors = $this->getMockBuilder(Errors::class)->disableOriginalConstructor()->onlyMethods(['log_error'])->getMock();
		$errors->expects($this->never())->method('log_error');
		$property = new \ReflectionProperty(Errors::class, '_errors');
		$property->setAccessible(true);
		$property->setValue(null, $errors);

		// Intercept sending headers so the test can inspect the response before die.
		$headers = $this->getMockBuilder(Headers::class)->disableOriginalConstructor()->onlyMethods(['sendHeaders'])->getMock();
		$headers->header('X-Before-Error', 'discard');
		$headers->expects($this->once())->method('sendHeaders')->willThrowException(new \RuntimeException('API_RESPONSE'));
		$property = new \ReflectionProperty(Headers::class, 'instance');
		$property->setAccessible(true);
		$property->setValue(null, $headers);

		$bufferLevel = ob_get_level();
		ob_start();
		echo 'Discard this response body';
		try
		{
			if ($entrypoint === 'checkSession')
			{
				checkSession('post', '', true, false);
			}
			else
			{
				(new Auth(new EventManager()))->{$entrypoint}();
			}
			$this->fail('The API error response was not sent.');
		}
		catch (\RuntimeException $exception)
		{
			$this->assertSame('API_RESPONSE', $exception->getMessage());
			$this->assertSame($bufferLevel, ob_get_level());
		}
		finally
		{
			while (ob_get_level() > $bufferLevel)
			{
				ob_end_clean();
			}
		}
		$this->assertSame(['X-Error-Message' => 'Session timeout'], $headers->getHeaders());
		$status = new \ReflectionProperty(Headers::class, 'httpCode');
		$status->setAccessible(true);
		$this->assertSame(403, $status->getValue($headers));
	}
	public static function stopLogout(): void
	{
		throw new \RuntimeException('LOGOUT_VERIFIED');
	}

	private function setMember(): void
	{
		User::$info = new UserInfo(['id' => 1, 'is_guest' => false, 'language' => 'English']);
		User::$settings = new UserSettings(['member_name' => 'member']);
	}

	private function setSavedAgent(?string $agent): void
	{
		unset($_SESSION['USER_AGENT']);
		if ($agent !== null)
		{
			$_SESSION['USER_AGENT'] = $agent;
		}
	}
}
