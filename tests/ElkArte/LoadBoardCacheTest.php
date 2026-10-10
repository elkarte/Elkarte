<?php

namespace ElkArte;

use ElkArte\Cache\Cache;
use ElkArte\Helper\ValuesContainer;
use PHPUnit\Framework\TestCase;

class MockQueryResult
{
	private $rows;
	private $index = 0;

	public function __construct(array $rows = [])
	{
		$this->rows = $rows;
	}

	public function num_rows(): int
	{
		return count($this->rows);
	}

	public function fetch_assoc()
	{
		if (isset($this->rows[$this->index]))
		{
			return $this->rows[$this->index++];
		}

		return false;
	}

	public function fetch_row()
	{
		$row = $this->fetch_assoc();
		if ($row !== false)
		{
			return array_values($row);
		}

		return false;
	}

	public function free_result(): void
	{
		$this->rows = [];
		$this->index = 0;
	}
}

class MockDatabase
{
	public $userTopicCounts = [];
	public $boardData = [];
	public $queryLog = [];

	public function quote($string, $params)
	{
		foreach ($params as $key => $val)
		{
			$string = str_replace(['{int:' . $key . '}', '{string:' . $key . '}', '{raw:' . $key . '}'], (string) $val, $string);
		}

		return $string;
	}

	public function query($identifier, $query, $params = [])
	{
		$this->queryLog[] = ['identifier' => $identifier, 'query' => $query, 'params' => $params];

		// Check if it's the count query for unapproved topics
		if (str_contains($query, 'COUNT(id_topic)'))
		{
			$memberId = $params['id_member'] ?? 0;
			$count = $this->userTopicCounts[$memberId] ?? 0;
			return new MockQueryResult([['count' => $count]]);
		}

		// Check if it's the board info query
		if (str_contains($query, 'FROM {db_prefix}boards'))
		{
			$boardId = $params['board_link'] ?? 0;
			if (isset($this->boardData[$boardId]))
			{
				return new MockQueryResult($this->boardData[$boardId]);
			}
		}

		return new MockQueryResult([]);
	}
}

class LoadBoardCacheTest extends TestCase
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

		if (!defined('CACHE_STALE'))
		{
			define('CACHE_STALE', '?123');
		}

		require_once(SOURCEDIR . '/QueryString.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/database/Database.subs.php');
		require_once(SOURCEDIR . '/Load.php');
		require_once(SOURCEDIR . '/Security.php');
	}

	protected function setUp(): void
	{
		global $context, $modSettings, $scripturl, $boardurl, $txt, $mock_db, $db_instance;

		$boardurl = 'http://example.com';
		$scripturl = 'http://example.com/index.php';
		$modSettings['lastActive'] = 15;
		$modSettings['settings_updated'] = 0;
		$modSettings['cache_enable'] = 2;
		$modSettings['postmod_active'] = 1;
		$modSettings['default_forum_action'] = [];

		$txt['forum_name_html_safe'] = 'Test Forum';

		$mock_db = new MockDatabase();
		$db_instance = $mock_db;
		$mock_db->boardData[2] = [
			[
				'id_cat' => 1,
				'bname' => 'Moderated Test Board',
				'description' => 'A board for post moderation testing',
				'num_topics' => 0,
				'member_groups' => '1,2',
				'deny_member_groups' => '',
				'id_parent' => 0,
				'cname' => 'General',
				'id_moderator' => 0,
				'real_name' => '',
				'id_board' => 2,
				'child_level' => 0,
				'id_theme' => 0,
				'override_theme' => 0,
				'count_posts' => 1,
				'old_posts' => 1,
				'id_profile' => 1,
				'redirect' => '',
				'unapproved_topics' => 5,
				'unapproved_posts' => 5,
				'approved' => 1,
				'id_member_started' => 0,
			]
		];
		$mock_db->userTopicCounts = [
			10 => 3, // User A has 3 unapproved topics
			20 => 0, // User B has 0 unapproved topics
			30 => 7, // User C has 7 unapproved topics
		];

		User::$info = new ValuesContainer([
			'id' => 10,
			'name' => 'UserA',
			'groups' => [2],
			'language' => 'english',
			'is_guest' => false,
			'is_admin' => false,
			'permissions' => [],
			'query_see_board' => '1=1',
		]);

		$context['breadcrumbs'] = [];
	}

	protected function tearDown(): void
	{
		global $db_instance;

		$db_instance = null;
		Cache::instance()->remove('board-2');
		Cache::instance()->remove('topic_board-0');
	}

	public function testBoardCacheCleanFromUserState()
	{
		global $board, $topic, $board_info;

		$cache = Cache::instance();
		$cache->setLevel(2);
		$cache->enable(true);

		$cache->remove('board-2');
		$cache->remove('topic_board-0');

		// 1. User A (id: 10, 3 unapproved topics) loads board 2 on cache miss
		$board = 2;
		$topic = 0;
		User::$info->id = 10;
		User::$info->groups = [2];
		User::$info->permissions = []; // Cannot approve posts

		loadBoard();

		$this->assertSame(2, $board_info['id']);
		$this->assertSame(0, $board_info['num_topics']);
		$this->assertSame(3, $board_info['unapproved_user_topics'], 'User A should see 3 unapproved user topics');

		// 2. Verify that the cached board entry has clean unapproved_user_topics = 0
		$cachedBoard = $cache->get('board-2');
		$this->assertNotNull($cachedBoard, 'Board data must be stored in cache');
		$this->assertSame(0, $cachedBoard['unapproved_user_topics'], 'Cached board must have unapproved_user_topics set to 0, not polluted with User A count');

		// 3. User B (id: 20, 0 unapproved topics) loads the same board on cache hit
		$board = 2;
		$topic = 0;
		$board_info = [];
		User::$info->id = 20;
		User::$info->groups = [2];
		User::$info->permissions = [];

		loadBoard();

		$this->assertSame(2, $board_info['id']);
		$this->assertSame(0, $board_info['unapproved_user_topics'], 'User B should see 0 unapproved user topics, not User A count');

		// 4. User C (id: 30, 7 unapproved topics) loads the same board on cache hit
		$board = 2;
		$topic = 0;
		$board_info = [];
		User::$info->id = 30;
		User::$info->groups = [2];
		User::$info->permissions = [];

		loadBoard();

		$this->assertSame(2, $board_info['id']);
		$this->assertSame(7, $board_info['unapproved_user_topics'], 'User C should dynamically receive 7 unapproved user topics on cache hit');

		// Clean up cache
		$cache->remove('board-2');
	}
}
