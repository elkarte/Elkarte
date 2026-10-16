<?php

namespace ElkArte\Security;

use ElkArte\Controller\Xml;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\HttpReq;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\User;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		if (!defined('BOARDDIR'))
		{
			define('BOARDDIR', realpath(__DIR__ . '/../../..'));
			define('SOURCEDIR', BOARDDIR . '/sources');
			define('SUBSDIR', SOURCEDIR . '/subs');
			define('ADMINDIR', SOURCEDIR . '/ElkArte/AdminController');
			define('CONTROLLERDIR', SOURCEDIR . '/ElkArte/Controller');
			define('LANGUAGEDIR', SOURCEDIR . '/ElkArte/Languages');
			define('CACHEDIR', BOARDDIR . '/cache');
			define('EXTDIR', SOURCEDIR . '/ext');
			define('ADDONSDIR', BOARDDIR . '/Addons');
		}

		require_once(SOURCEDIR . '/Load.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Security.php');
		require_once(SOURCEDIR . '/Logging.php');
		require_once(SOURCEDIR . '/database/Database.subs.php');
	}

	protected function setUp(): void
	{
		global $context, $db_instance, $txt, $modSettings, $settings, $boardurl, $scripturl;

		$_SESSION = [];
		$_POST = [];
		$_GET = [];
		$_REQUEST = [];
		$context = [];
		$txt = [];
		$modSettings = [
			'warning_settings' => '1,10,1',
		];
		$boardurl = 'http://127.0.0.1';
		$scripturl = 'http://127.0.0.1/index.php';
		$settings = [
			'default_theme_dir' => BOARDDIR . '/themes/default',
			'theme_dir' => BOARDDIR . '/themes/default',
			'default_theme_url' => 'http://127.0.0.1/themes/default',
			'theme_url' => 'http://127.0.0.1/themes/default',
			'images_url' => 'http://127.0.0.1/themes/default/images',
		];

		$ref = new \ReflectionProperty(\ElkArte\Languages\Txt::class, 'loader');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		$userData = [
			'id' => 1,
			'ip' => '127.0.0.1',
			'language' => 'English',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'admin',
			'name' => 'admin',
			'permissions' => ['admin_forum'],
		];
		User::$info = new \ElkArte\UserInfo($userData);
		$_SESSION['USER_AGENT'] = \ElkArte\Request::instance()->user_agent();

		$queryResult = new class {
			public $currentQuery = '';
			public function fetch_assoc() { return false; }
			public function fetch_row() { return false; }
			public function fetch_callback($callback)
			{
				if (str_contains($this->currentQuery, 'themes'))
				{
					$callback([
						'id_theme' => 1,
						'id_member' => 0,
						'variable' => 'theme_dir',
						'value' => BOARDDIR . '/themes/default',
					]);
					$callback([
						'id_theme' => 1,
						'id_member' => 0,
						'variable' => 'theme_url',
						'value' => 'http://127.0.0.1/themes/default',
					]);
					$callback([
						'id_theme' => 1,
						'id_member' => 0,
						'variable' => 'images_url',
						'value' => 'http://127.0.0.1/themes/default/images',
					]);
				}
				return [];
			}
			public function free_result() {}
			public function num_rows() { return 0; }
			public function data_seek($offset) { return true; }
			public function num_fields() { return 0; }
		};

		$db_instance = $this->createMock(\ElkArte\Database\QueryInterface::class);
		$db_instance->method('fetchQuery')->willReturnCallback(function ($query, $params = []) use ($queryResult) {
			$queryResult->currentQuery = is_string($query) ? $query : '';
			return $queryResult;
		});
		$db_instance->method('query')->willReturnCallback(function ($identifier, $query, $params = []) use ($queryResult) {
			$queryResult->currentQuery = is_string($query) ? $query : '';
			return $queryResult;
		});
	}

	/**
	 * Quick Win 2: Clean tokens with prefix match should not be skipped due to strpos returning 0.
	 */
	public function testCleanTokensPrefixMatch()
	{
		$_SESSION['token'] = [
			'post-admin-ssc' => ['var1', 'hash1', time(), 'token1'],
			'get-admin-test' => ['var2', 'hash2', time(), 'token2'],
			'post-other' => ['var3', 'hash3', time(), 'token3'],
		];

		// cleanTokens with suffix matching 'post' at index 0 should clean 'post-admin-ssc' and 'post-other'
		cleanTokens(false, 'post');

		$this->assertArrayNotHasKey('post-admin-ssc', $_SESSION['token']);
		$this->assertArrayNotHasKey('post-other', $_SESSION['token']);
		$this->assertArrayHasKey('get-admin-test', $_SESSION['token']);
	}

	/**
	 * Quick Win 4: validateToken with $fatal = true must not regenerate a new session token on failure.
	 */
	public function testValidateTokenFatalDoesNotRegenerateToken()
	{
		createToken('test-fatal-action');
		$originalTokenTuple = $_SESSION['token']['post-test-fatal-action'];
		$this->assertIsArray($originalTokenTuple);

		// Intentionally tamper with POST so validation fails
		$_POST = [];

		$exceptionThrown = false;
		try
		{
			validateToken('test-fatal-action', 'post', true, true);
		}
		catch (Exception $e)
		{
			$exceptionThrown = true;
			$this->assertStringContainsString('Token verification failed', $e->getMessage());
		}

		$this->assertTrue($exceptionThrown);
		// After fatal failure, a new replacement token was NOT generated and session token was not mutated
		$this->assertSame($originalTokenTuple, $_SESSION['token']['post-test-fatal-action']);
	}

	/**
	 * Quick Win 3: SettingsForm scopes token to admin_area and sets token_check in context.
	 */
	public function testSettingsFormScopedToken()
	{
		global $context;

		$context['admin_area'] = 'serversettings';

		$mockAdapter = new class extends \ElkArte\SettingsForm\SettingsFormAdapter\Adapter {
			public $prepared = false;
			public $saved = false;

			public function prepare(): void
			{
				$this->prepared = true;
			}

			public function save(): void
			{
				$this->saved = true;
			}
		};

		$settingsForm = new SettingsForm();
		$refProp = new \ReflectionProperty(SettingsForm::class, 'adapter');
		$refProp->setAccessible(true);
		$refProp->setValue($settingsForm, $mockAdapter);

		$settingsForm->prepare();

		$expectedToken = 'admin-ssc-serversettings';
		$this->assertTrue($mockAdapter->prepared);
		$this->assertSame($expectedToken, $context['token_check']);
		$this->assertArrayHasKey('post-' . $expectedToken, $_SESSION['token']);
		$this->assertArrayHasKey($expectedToken . '_token', $context);
		$this->assertArrayHasKey($expectedToken . '_token_var', $context);

		// Now simulate form submission with valid token
		$tokenVar = $context[$expectedToken . '_token_var'];
		$tokenVal = $context[$expectedToken . '_token'];
		$_POST[$tokenVar] = $tokenVal;

		// Validation should succeed for the scoped token
		$settingsForm->save();
		$this->assertTrue($mockAdapter->saved);
	}

	/**
	 * Quick Win 1: Xml::action_corefeatures token attribute mapping.
	 */
	public function testXmlCorefeaturesTokenAttributeMapping()
	{
		global $context, $txt;

		$txt['core_settings_activation_message'] = 'Activated';
		$txt['core_settings_deactivation_message'] = 'Deactivated';
		$txt['core_settings_generic_error'] = 'Error';

		$context['session_var'] = 'elk_token';
		$context['session_id'] = '123456';
		$_SESSION['session_value'] = '123456';
		$_SESSION['session_var'] = 'elk_token';
		$_SESSION['admin_time'] = time() + 3600;
		$_POST['elk_token'] = '123456';
		$_GET['api'] = 'xml';
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

		$rc = new \ReflectionClass(\ElkArte\Helper\HttpReq::class);
		$prop = $rc->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, null);

		$req = HttpReq::instance();
		$req->post->feature_id = 'k';
		$req->post->feature_k = 1;
		$req->post->elk_token = '123456';

		$controller = new Xml(new EventManager());
		$controller->setUser(User::$info);
		$mockTheme = $this->createMock(\ElkArte\Themes\Theme::class);
		$context['theme_instance'] = $mockTheme;
		$controller->action_corefeatures();

		$this->assertArrayHasKey('xml_data', $context);
		$tokens = $context['xml_data']['tokens']['children'];

		$this->assertCount(2, $tokens);
		$this->assertSame('token', $tokens[0]['attributes']['type']);
		$this->assertSame($context['admin-core_token'], $tokens[0]['value']);

		$this->assertSame('token_var', $tokens[1]['attributes']['type']);
		$this->assertSame($context['admin-core_token_var'], $tokens[1]['value']);
	}

	/**
	 * Sequential core feature toggling without page refresh.
	 * Toggling on returns new token pair via XML, and client using [type="token_var"] as key
	 * and [type="token"] as value can toggle off without token verification failure.
	 */
	public function testXmlCorefeaturesSequentialToggle()
	{
		global $context, $txt;

		$txt['core_settings_activation_message'] = 'Activated {core_feature}';
		$txt['core_settings_deactivation_message'] = 'Deactivated {core_feature}';
		$txt['core_settings_generic_error'] = 'Error';
		$txt['token_verify_fail'] = 'Token verification failed';

		$context['session_var'] = 'elk_token';
		$context['session_id'] = '123456';
		$_SESSION['session_value'] = '123456';
		$_SESSION['session_var'] = 'elk_token';
		$_SESSION['admin_time'] = time() + 3600;
		$_GET['api'] = 'xml';
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

		// 1. Initial page render creates token
		createToken('admin-core');
		$initialTokenVar = $context['admin-core_token_var'];
		$initialTokenVal = $context['admin-core_token'];

		// 2. First toggle (Turn ON Karma)
		$_POST = [
			'elk_token' => '123456',
			'save' => 'save',
			'feature_id' => 'k',
			'feature_k' => 1,
			$initialTokenVar => $initialTokenVal,
		];

		$rc = new \ReflectionClass(\ElkArte\Helper\HttpReq::class);
		$prop = $rc->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, null);

		$controller = new Xml(new EventManager());
		$controller->setUser(User::$info);
		$mockTheme = $this->createMock(\ElkArte\Themes\Theme::class);
		$context['theme_instance'] = $mockTheme;

		$controller->action_corefeatures();

		$this->assertEmpty($context['xml_data']['errors']['children']);
		$this->assertNotEmpty($context['xml_data']['tokens']['children']);

		// Client extracts token according to admin.js logic:
		// token_name = [type="token_var"]
		// token_value = [type="token"]
		$tokens = $context['xml_data']['tokens']['children'];
		$tokenName = null;
		$tokenValue = null;
		foreach ($tokens as $tokenElement)
		{
			if ($tokenElement['attributes']['type'] === 'token_var')
			{
				$tokenName = $tokenElement['value'];
			}
			elseif ($tokenElement['attributes']['type'] === 'token')
			{
				$tokenValue = $tokenElement['value'];
			}
		}

		$this->assertNotNull($tokenName);
		$this->assertNotNull($tokenValue);

		// 3. Second toggle (Turn OFF Karma) without page refresh
		$_POST = [
			'elk_token' => '123456',
			'save' => 'save',
			'feature_id' => 'k',
			'feature_k' => 0,
			$tokenName => $tokenValue,
		];

		$prop->setValue(null, null);

		$controller2 = new Xml(new EventManager());
		$controller2->setUser(User::$info);
		$controller2->action_corefeatures();

		$this->assertEmpty($context['xml_data']['errors']['children'], 'Second toggle should succeed without token failure');
		$this->assertStringContainsStringIgnoringCase('deactivated', $context['xml_data']['messages']['children'][0]['value']);
	}

	/**
	 * Quick Win 5: ManagePermissions::action_quick uses valid POST token.
	 */
	public function testManagePermissionsQuickToken()
	{
		global $context;

		// When loading permission index, token is created
		createToken('admin-mpq');
		$this->assertArrayHasKey('post-admin-mpq', $_SESSION['token']);

		$tokenVar = $context['admin-mpq_token_var'];
		$tokenVal = $context['admin-mpq_token'];
		$_POST[$tokenVar] = $tokenVal;

		// validateToken('admin-mpq', 'post') should validate the token successfully
		$valid = validateToken('admin-mpq', 'post', false, false);
		$this->assertTrue($valid);
	}
}
