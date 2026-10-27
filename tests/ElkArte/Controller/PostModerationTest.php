<?php

namespace ElkArte\Controller;

use ElkArte\Database\AbstractResult;
use ElkArte\Database\QueryInterface;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\HttpReq;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\UserInfo;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class PostModerationTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	private ?UserInfo $originalUser = null;
	private $originalDbInstance;
	private ?array $originalModSettings = null;

	public static ?string $redirectTarget = null;

	public static function captureRedirect(string $loc): void
	{
		self::$redirectTarget = $loc;
		throw new RuntimeException('REDIRECT_EXIT');
	}

	protected function setUp(): void
	{
		global $modSettings, $context, $boardurl, $db_instance;

		$boarddir = defined('BOARDDIR') ? BOARDDIR : dirname(__DIR__, 3);
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}
		if (!defined('BOARDDIR'))
		{
			define('BOARDDIR', $boarddir);
		}
		if (!defined('SOURCEDIR'))
		{
			define('SOURCEDIR', BOARDDIR . '/sources');
		}
		if (!defined('SUBSDIR'))
		{
			define('SUBSDIR', SOURCEDIR . '/subs');
		}
		if (!defined('CONTROLLERDIR'))
		{
			define('CONTROLLERDIR', SOURCEDIR . '/ElkArte/Controller');
		}
		if (!defined('ADMINDIR'))
		{
			define('ADMINDIR', SOURCEDIR . '/ElkArte/AdminController');
		}
		if (!defined('CACHEDIR'))
		{
			define('CACHEDIR', BOARDDIR . '/cache');
		}

		require_once(SOURCEDIR . '/Load.php');
		require_once(SOURCEDIR . '/Security.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Logging.php');
		require_once(SOURCEDIR . '/database/Database.subs.php');

		$this->originalUser = User::$info ?? null;
		$this->originalDbInstance = $db_instance ?? null;
		$this->originalModSettings = $modSettings ?? null;

		$dbMock = $this->createMock(QueryInterface::class);
		$resultMock = $this->createMock(AbstractResult::class);
		$resultMock->method('hasResults')->willReturn(false);
		$resultMock->method('fetch_assoc')->willReturn(false);
		$resultMock->method('num_rows')->willReturn(0);
		$dbMock->method('fetchQuery')->willReturn($resultMock);
		$dbMock->method('query')->willReturn($resultMock);
		$db_instance = $dbMock;

		$boardurl = 'http://127.0.0.1';
		Txt::load('Errors');

		$modSettings = [
			'disableCheckUA' => 1,
			'postmod_active' => 1,
			'lastActive' => 15,
		];

		// Set up session tokens for checkSession('get')
		$_GET['elk_test_session'] = 'test_token_123';
		$_SESSION['session_var'] = 'elk_test_session';
		$_SESSION['session_value'] = 'test_token_123';
		$_SESSION['USER_AGENT'] = 'elkarte_test';
		$context['session_var'] = 'elk_test_session';
		$context['session_value'] = 'test_token_123';
		$context['session_id'] = 'test_token_123';

		Headers::instance()->removeHeader('all');
	}

	protected function tearDown(): void
	{
		global $db_instance, $modSettings;

		User::$info = $this->originalUser;
		$db_instance = $this->originalDbInstance;
		$modSettings = $this->originalModSettings;

		unset($_GET['elk_test_session'], $_GET['msg'], $_GET['topic'], $_GET['sa'], $_GET['area'], $_SESSION['session_var'], $_SESSION['session_value'], $_SESSION['USER_AGENT']);

		Headers::instance()->removeHeader('all');
	}

	/**
	 * Reset HttpReq singleton after changing $_GET
	 */
	private function resetHttpReq(): void
	{
		$rc = new ReflectionClass(HttpReq::class);
		$prop = $rc->getProperty('instance');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	/**
	 * Test that attempting to approve a non-existent message throws cant_find_messages exception.
	 */
	public function testNonExistentMessageThrowsException(): void
	{
		global $db_instance, $txt;

		$_GET['msg'] = 999;
		$this->resetHttpReq();

		User::$info = new UserInfo([
			'id' => 2,
			'is_admin' => false,
			'is_guest' => false,
			'permissions' => ['approve_posts'],
			'groups' => [2],
		]);

		// Mock DB returning no message results
		$dbMock = $this->createMock(QueryInterface::class);
		$resultMock = $this->createMock(AbstractResult::class);
		$resultMock->method('hasResults')->willReturn(false);
		$resultMock->method('fetch_assoc')->willReturn(false);
		$resultMock->method('num_rows')->willReturn(0);

		$dbMock->method('fetchQuery')->willReturn($resultMock);
		$dbMock->method('query')->willReturn($resultMock);
		$db_instance = $dbMock;

		$this->expectException(Exception::class);
		$this->expectExceptionMessage($txt['cant_find_messages']);

		$controller = new PostModeration(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_approve();
	}

	/**
	 * Test that a moderator of Board 1 cannot approve a post located in Board 2 (Cross-board IDOR).
	 */
	public function testCrossBoardModeratorCannotApproveVictimBoardPost(): void
	{
		global $db_instance, $txt;

		$_GET['msg'] = 100;
		$this->resetHttpReq();

		// User has global permissions array containing approve_posts (e.g. from Board 1), but not in Board 2
		User::$info = new UserInfo([
			'id' => 5,
			'is_admin' => false,
			'is_guest' => false,
			'permissions' => ['approve_posts'],
			'groups' => [4],
		]);

		$messageData = [
			'id_msg' => 100,
			'id_topic' => 20,
			'id_board' => 2, // Victim board
			'id_member' => 10,
			'subject' => 'Victim post',
			'body' => 'Post in board 2',
			'poster_name' => 'VictimUser',
			'poster_email' => 'victim@example.com',
			'poster_time' => time(),
			'approved' => 0,
			'id_first_msg' => 100,
			'num_replies' => 0,
			'unapproved_posts' => 1,
			'id_last_msg' => 100,
			'id_member_started' => 10,
			'locked' => 0,
			'topic_approved' => 0,
		];

		$dbMock = $this->createMock(QueryInterface::class);

		// Message query result for basicMessageInfo
		$msgResultMock = $this->createMock(AbstractResult::class);
		$msgResultMock->method('hasResults')->willReturn(true);
		$msgResultMock->method('fetch_assoc')->willReturn($messageData);

		$emptyResultMock = $this->createMock(AbstractResult::class);
		$emptyResultMock->method('hasResults')->willReturn(false);
		$emptyResultMock->method('num_rows')->willReturn(0);
		$emptyResultMock->method('fetch_assoc')->willReturn(false);

		$dbMock->method('fetchQuery')->willReturnCallback(function ($query) use ($msgResultMock, $emptyResultMock) {
			if (str_contains($query, '{db_prefix}messages'))
			{
				return $msgResultMock;
			}
			return $emptyResultMock;
		});

		// Permission query for allowedTo('approve_posts', 2) -> returns no rows or add_deny = 0
		$dbMock->method('query')->willReturn($emptyResultMock);
		$db_instance = $dbMock;

		$this->expectException(Exception::class);
		$this->expectExceptionMessage($txt['cannot_approve_posts']);

		$controller = new PostModeration(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_approve();
	}

	/**
	 * Test that supplying a mismatched topic ID parameter throws no_access exception.
	 */
	public function testTopicMismatchRejection(): void
	{
		global $db_instance, $txt;

		$_GET['msg'] = 50;
		$_GET['topic'] = 999; // Mismatched topic
		$this->resetHttpReq();

		User::$info = new UserInfo([
			'id' => 3,
			'is_admin' => true, // Admin to pass permission check
			'is_guest' => false,
			'permissions' => ['approve_posts'],
			'groups' => [1],
		]);

		$messageData = [
			'id_msg' => 50,
			'id_topic' => 10, // Actual topic is 10, not 999
			'id_board' => 1,
			'id_member' => 3,
			'subject' => 'First message of topic 10',
			'body' => 'Hello world',
			'poster_name' => 'Attacker',
			'poster_email' => 'attacker@example.com',
			'poster_time' => time(),
			'approved' => 0,
			'id_first_msg' => 50,
			'num_replies' => 0,
			'unapproved_posts' => 1,
			'id_last_msg' => 50,
			'id_member_started' => 3,
			'locked' => 0,
			'topic_approved' => 0,
		];

		$dbMock = $this->createMock(QueryInterface::class);
		$msgResultMock = $this->createMock(AbstractResult::class);
		$msgResultMock->method('hasResults')->willReturn(true);
		$msgResultMock->method('fetch_assoc')->willReturn($messageData);

		$dbMock->method('fetchQuery')->willReturn($msgResultMock);
		$db_instance = $dbMock;

		$this->expectException(Exception::class);
		$this->expectExceptionMessage($txt['no_access']);

		$controller = new PostModeration(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_approve();
	}

	/**
	 * Test that an authorized moderator can approve a reply post in their assigned board.
	 */
	public function testAuthorizedModeratorCanApproveReplyPost(): void
	{
		global $db_instance, $topic, $board;

		$_GET['msg'] = 25;
		$_GET['topic'] = 10;
		$this->resetHttpReq();

		User::$info = new UserInfo([
			'id' => 3,
			'is_admin' => true,
			'is_guest' => false,
			'permissions' => ['approve_posts'],
			'groups' => [1],
		]);

		$messageData = [
			'id_msg' => 25,
			'id_topic' => 10,
			'id_board' => 1,
			'id_member' => 8,
			'subject' => 'Reply subject',
			'body' => 'Reply body',
			'poster_name' => 'MemberUser',
			'poster_email' => 'member@example.com',
			'poster_time' => time(),
			'approved' => 0,
			'id_first_msg' => 1, // First message is msg 1, not msg 25
			'num_replies' => 5,
			'unapproved_posts' => 1,
			'id_last_msg' => 25,
			'id_member_started' => 7,
			'locked' => 0,
			'topic_approved' => 1,
		];

		$dbMock = $this->createMock(QueryInterface::class);

		$msgResultMock = $this->createMock(AbstractResult::class);
		$msgResultMock->method('hasResults')->willReturn(true);
		$msgResultMock->method('fetch_assoc')->willReturn($messageData);

		$emptyResultMock = $this->createMock(AbstractResult::class);
		$emptyResultMock->method('hasResults')->willReturn(false);
		$emptyResultMock->method('num_rows')->willReturn(1);
		$emptyResultMock->method('fetch_assoc')->willReturn(false);

		$dbMock->method('fetchQuery')->willReturnCallback(function ($query) use ($msgResultMock, $emptyResultMock) {
			if (str_contains($query, '{db_prefix}messages') && str_contains($query, 'id_msg = {int:message}'))
			{
				return $msgResultMock;
			}
			return $emptyResultMock;
		});

		$dbMock->method('query')->willReturn($emptyResultMock);
		$db_instance = $dbMock;

		// Catch redirect via integrate_redirect hook so obExit recursion exit is avoided
		self::$redirectTarget = null;
		add_integration_function('integrate_redirect', self::class . '::captureRedirect', '', false);

		$controller = new PostModeration(new EventManager());
		$controller->setUser(User::$info);

		try
		{
			$controller->action_approve();
		}
		catch (\RuntimeException $e)
		{
			if ($e->getMessage() !== 'REDIRECT_EXIT')
			{
				throw $e;
			}
		}

		$this->assertSame(10, $topic);
		$this->assertSame(1, $board);
		$this->assertStringContainsString('topic=10.msg25#msg25', self::$redirectTarget);
	}

	/**
	 * Test that an authorized moderator approving first message approves the entire topic.
	 */
	public function testAuthorizedModeratorCanApproveTopicFirstMessage(): void
	{
		global $db_instance, $topic, $board;

		$_GET['msg'] = 30;
		$_GET['topic'] = 15;
		$this->resetHttpReq();

		User::$info = new UserInfo([
			'id' => 3,
			'is_admin' => true,
			'is_guest' => false,
			'permissions' => ['approve_posts'],
			'groups' => [1],
		]);

		$messageData = [
			'id_msg' => 30,
			'id_topic' => 15,
			'id_board' => 1,
			'id_member' => 8,
			'subject' => 'Topic subject',
			'body' => 'Topic body',
			'poster_name' => 'MemberUser',
			'poster_email' => 'member@example.com',
			'poster_time' => time(),
			'approved' => 0,
			'id_first_msg' => 30, // First message is msg 30
			'num_replies' => 0,
			'unapproved_posts' => 1,
			'id_last_msg' => 30,
			'id_member_started' => 8,
			'locked' => 0,
			'topic_approved' => 0,
		];

		$dbMock = $this->createMock(QueryInterface::class);

		$msgResultMock = $this->createMock(AbstractResult::class);
		$msgResultMock->method('hasResults')->willReturn(true);
		$msgResultMock->method('fetch_assoc')->willReturn($messageData);

		$emptyResultMock = $this->createMock(AbstractResult::class);
		$emptyResultMock->method('hasResults')->willReturn(false);
		$emptyResultMock->method('num_rows')->willReturn(1);
		$emptyResultMock->method('fetch_assoc')->willReturn(false);

		$dbMock->method('fetchQuery')->willReturnCallback(function ($query) use ($msgResultMock, $emptyResultMock) {
			if (str_contains($query, '{db_prefix}messages') && str_contains($query, 'id_msg = {int:message}'))
			{
				return $msgResultMock;
			}
			return $emptyResultMock;
		});

		$dbMock->method('query')->willReturn($emptyResultMock);
		$db_instance = $dbMock;

		// Catch redirect via integrate_redirect hook so obExit recursion exit is avoided
		self::$redirectTarget = null;
		add_integration_function('integrate_redirect', self::class . '::captureRedirect', '', false);

		$controller = new PostModeration(new EventManager());
		$controller->setUser(User::$info);

		try
		{
			$controller->action_approve();
		}
		catch (\RuntimeException $e)
		{
			if ($e->getMessage() !== 'REDIRECT_EXIT')
			{
				throw $e;
			}
		}

		$this->assertSame(15, $topic);
		$this->assertSame(1, $board);
		$this->assertStringContainsString('topic=15.msg30#msg30', self::$redirectTarget);
	}
}
