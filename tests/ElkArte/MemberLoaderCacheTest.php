<?php

namespace ElkArte;

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Cache\CacheMethod\Filebased;
use PHPUnit\Framework\TestCase;

class MockDbResult
{
	private $rows;
	private $index = 0;

	public function __construct(array $rows = [])
	{
		$this->rows = $rows;
	}

	public function fetch_callback(callable $callback): void
	{
		foreach ($this->rows as $row)
		{
			$callback($row);
		}
	}
}

class MockMemberDb
{
	public $membersData = [];
	public $queriedIds = [];

	public function fetchQuery($query, $params = [])
	{
		$ids = (array) ($params['users'] ?? []);
		$this->queriedIds = array_merge($this->queriedIds, $ids);

		$resultRows = [];
		foreach ($ids as $id)
		{
			if (isset($this->membersData[$id]))
			{
				$resultRows[] = $this->membersData[$id];
			}
		}

		return new MockDbResult($resultRows);
	}
}

class MemberLoaderCacheTest extends TestCase
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
			define('BOARDDIR', dirname(__DIR__, 2));
			define('SOURCEDIR', BOARDDIR . '/sources');
			define('SUBSDIR', SOURCEDIR . '/subs');
			define('CACHEDIR', BOARDDIR . '/cache');
			define('LANGUAGEDIR', SOURCEDIR . '/ElkArte/Languages');
		}

		require_once(SOURCEDIR . '/QueryString.php');
		require_once(SOURCEDIR . '/Subs.php');
	}

	protected function setUp(): void
	{
		global $modSettings, $txt, $boardurl;

		$boardurl = 'http://example.com';
		$modSettings['lastActive'] = 15;
		$modSettings['titlesEnable'] = 0;
		$txt['guest_title'] = 'Guest';
	}

	public function testBatchLoadFromCacheAndDb()
	{
		global $cache_accelerator, $cache_enable;

		$cache_accelerator = '';
		$cache_enable = 3;

		$cache = Cache::instance();
		$file_cache = new Filebased([]);
		$object = new \ReflectionClass($cache);
		$property = $object->getProperty('_cache_obj');
		$property->setAccessible(true);
		$property->setValue($cache, $file_cache);

		$cache->setLevel(3);
		$cache->enable(true);

		$mockDb = new MockMemberDb();
		$mockDb->membersData[101] = [
			'id_member' => 101,
			'member_name' => 'User101',
			'real_name' => 'User One Hundred One',
			'email_address' => 'user101@example.com',
			'date_registered' => time() - 10000,
			'posts' => 10,
			'last_login' => time(),
			'member_ip' => '127.0.0.1',
			'member_ip2' => '127.0.0.1',
			'lngfile' => 'english',
			'id_group' => 0,
			'id_post_group' => 4,
			'is_activated' => 1,
			'warning' => 0,
			'time_offset' => 0,
			'show_online' => 1,
			'likes_given' => 0,
			'likes_received' => 0,
			'karma_good' => 0,
			'karma_bad' => 0,
			'signature' => '',
			'avatar' => '',
			'website_title' => '',
			'website_url' => '',
			'birthdate' => '0001-01-01',
			'member_group' => '',
			'member_group_color' => '',
			'post_group' => 'Newbie',
			'post_group_color' => '',
			'icons' => '',
			'is_online' => 0,
			'id_attach' => 0,
			'filename' => '',
			'attachment_type' => 0,
			'buddy_list' => '',
			'pm_ignore_list' => '',
		];

		$mockDb->membersData[102] = [
			'id_member' => 102,
			'member_name' => 'User102',
			'real_name' => 'User One Hundred Two',
			'email_address' => 'user102@example.com',
			'date_registered' => time() - 20000,
			'posts' => 50,
			'last_login' => time(),
			'member_ip' => '127.0.0.1',
			'member_ip2' => '127.0.0.1',
			'lngfile' => 'english',
			'id_group' => 0,
			'id_post_group' => 4,
			'is_activated' => 1,
			'warning' => 0,
			'time_offset' => 0,
			'show_online' => 1,
			'likes_given' => 0,
			'likes_received' => 0,
			'karma_good' => 0,
			'karma_bad' => 0,
			'signature' => '',
			'avatar' => '',
			'website_title' => '',
			'website_url' => '',
			'birthdate' => '0001-01-01',
			'member_group' => '',
			'member_group_color' => '',
			'post_group' => 'Jr. Member',
			'post_group_color' => '',
			'icons' => '',
			'is_online' => 0,
			'id_attach' => 0,
			'filename' => '',
			'attachment_type' => 0,
			'buddy_list' => '',
			'pm_ignore_list' => '',
		];

		$parser = new ParserWrapper();
		$usersList = new MembersList();

		// Clear any existing cache for these users
		$cache->put('member_data-normal-101', null);
		$cache->put('member_data-normal-102', null);

		$loader = new MemberLoader($mockDb, $cache, $parser, $usersList);

		// First load: cache miss for both 101 and 102 -> DB queried for both and saved in cache via putMulti
		$loadedIds = $loader->loadById([101, 102], MemberLoader::SET_NORMAL);

		$this->assertSame([101, 102], $loadedIds);
		$this->assertEquals([101, 102], $mockDb->queriedIds);

		// Verify cache entries were populated
		$cached101 = $cache->get('member_data-normal-101', 240);
		$cached102 = $cache->get('member_data-normal-102', 240);
		$this->assertNotNull($cached101);
		$this->assertNotNull($cached102);
		$this->assertSame(101, $cached101['data']['id_member']);
		$this->assertSame(102, $cached102['data']['id_member']);

		// Second load: both users in cache -> DB should not be queried at all!
		$mockDb->queriedIds = [];
		$loader2 = new MemberLoader($mockDb, $cache, $parser, $usersList);
		$loadedIds2 = $loader2->loadById([101, 102], MemberLoader::SET_NORMAL);

		$this->assertSame([101, 102], $loadedIds2);
		$this->assertEmpty($mockDb->queriedIds, 'Database should not be queried when all users are retrieved via getMulti');

		// Third load: 101 in cache, 103 not in cache (and not in DB)
		$mockDb->queriedIds = [];
		$loader3 = new MemberLoader($mockDb, $cache, $parser, $usersList);
		$loadedIds3 = $loader3->loadById([101, 103], MemberLoader::SET_NORMAL);

		$this->assertSame([101], $loadedIds3);
		$this->assertSame([103], $mockDb->queriedIds, 'Only missing user 103 should be queried from DB');

		// Clean up cache
		$cache->put('member_data-normal-101', null);
		$cache->put('member_data-normal-102', null);
	}
}
