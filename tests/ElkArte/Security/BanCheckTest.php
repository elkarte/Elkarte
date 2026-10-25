<?php

namespace ElkArte\Security;

use ElkArte\Cache\Cache;
use ElkArte\User;
use ElkArte\UserInfo;
use PHPUnit\Framework\TestCase;

class BanCheckTest extends TestCase
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
		require_once(SOURCEDIR . '/database/Database.subs.php');
		require_once(SUBSDIR . '/Bans.subs.php');
	}

	protected function setUp(): void
	{
		global $modSettings, $db_instance, $cache_enable, $cache_accelerator;

		$cache_enable = 1;
		$cache_accelerator = 'filebased';

		$ref = new \ReflectionProperty(Cache::class, '_instance');
		$ref->setAccessible(true);
		$ref->setValue(null, null);

		$modSettings = [
			'banLastUpdated' => time(),
			'disableHostnameLookup' => 0,
		];

		unset($_SESSION['ban']);

		$dbMock = new class {
			public $queries = [];

			public function quote($val, $type) { return $val; }
			public function query($identifier, $query, $params = [])
			{
				$this->queries[] = $query;
				return new class {
					public function num_rows() { return 0; }
					public function free_result() {}
				};
			}
			public function fetchQuery($query, $params = [])
			{
				$this->queries[] = $query;
				return new class {
					public function fetch_callback($cb) { return []; }
					public function fetch_assoc() { return false; }
				};
			}
			public function replace($table, $columns, $data, $keys) {}
		};

		$db_instance = $dbMock;

		User::$settings = [
			'is_activated' => 1,
		];

		User::$info = new UserInfo([
			'id' => 10,
			'is_admin' => false,
			'is_guest' => false,
			'username' => 'testuser',
			'email' => 'test@example.com',
			'ip' => '127.0.0.1',
			'ip2' => '127.0.0.1',
		]);
	}

	public function testHostFromIpLocalAndExternalCache()
	{
		$cache = Cache::instance();
		$testIp = '192.0.2.1';

		// Seed cache
		$cache->put('hostlookup-' . $testIp, 'testhost.example.com', 3600);

		$resolved = host_from_ip($testIp);
		$this->assertSame('testhost.example.com', $resolved);

		// Second call returns from local in-memory static cache
		$resolved2 = host_from_ip($testIp);
		$this->assertSame('testhost.example.com', $resolved2);

		// Clean up
		$cache->remove('hostlookup-' . $testIp);
	}

	public function testHostFromIpCachesNegativeResult()
	{
		$cache = Cache::instance();
		$testIp = '255.255.255.254';

		// Empty IP returns empty string without error
		$this->assertSame('', host_from_ip(''));

		$res = host_from_ip($testIp);
		// It should be cached in Cache instance
		$cachedVal = null;
		$found = $cache->getVar($cachedVal, 'hostlookup-' . $testIp, 3600);
		$this->assertTrue($found);
		$this->assertSame($res, $cachedVal);

		// Clean up
		$cache->remove('hostlookup-' . $testIp);
	}

	public function testIsNotBannedPerformsHostnameLookup()
	{
		global $modSettings;

		$modSettings['disableHostnameLookup'] = 0;

		$cache = Cache::instance();
		$testIp = '198.51.100.2';
		User::$info->ip = $testIp;
		User::$info->ip2 = $testIp;

		// Seed cache so lookup returns immediately without hitting network
		$cache->put('hostlookup-' . $testIp, 'knownhost.example.com', 3600);

		is_not_banned(true);

		$this->assertArrayHasKey('ban', $_SESSION);
		$this->assertArrayHasKey('host', $_SESSION['ban']);
		$this->assertSame('knownhost.example.com', $_SESSION['ban']['host']['ip']);

		// Clean up
		$cache->remove('hostlookup-' . $testIp);
	}

	public function testIsNotBannedSkipsHostnameWhenDisabledInSettings()
	{
		global $modSettings;

		$modSettings['disableHostnameLookup'] = 1;

		$cache = Cache::instance();
		$testIp = '198.51.100.3';
		User::$info->ip = $testIp;
		User::$info->ip2 = $testIp;

		$cache->put('hostlookup-' . $testIp, 'knownhost.example.com', 3600);

		is_not_banned(true);

		$this->assertArrayHasKey('ban', $_SESSION);
		$this->assertEmpty($_SESSION['ban']['host']);

		// Clean up
		$cache->remove('hostlookup-' . $testIp);
	}
}
