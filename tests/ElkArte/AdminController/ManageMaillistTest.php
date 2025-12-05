<?php

/**
 * TestCase class for ManageMaillist controller
 */

namespace ElkArte\AdminController;

use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class ManageMaillistTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	private array $created_error_ids = [];
	private array $created_template_ids = [];
	private int $id_email;

	protected function setUp(): void
	{
		parent::setUp();

		global $txt, $boardurl;

		// Load languages used by the controller/settings
		$lang = new Loader('english', $txt, database());
		$lang->load('Maillist');
		$lang->load('Admin');

		// Minimal globals commonly used
		$boardurl = 'http://example.com';

		// Needed by list_maillist_unapproved() to translate dummy error
		$txt['dummy_short'] = 'Dummy';

		// Commonly required subs
		require_once(SUBSDIR . '/Maillist.subs.php');
		require_once(SUBSDIR . '/Moderation.subs.php');

		// Create a failed email entry (id_board = -1 to always show)
		$db = database();
		$db->insert('insert', '{db_prefix}postby_emails_error',
			[
				'error' => 'string',
				'message_key' => 'string',
				'subject' => 'string',
				'message_id' => 'string',
				'email_from' => 'string',
				'message_type' => 'string',
				'message' => 'string',
				'id_board' => 'int',
			],
			[
				'error_not_find_member',
				'7738c27ae6c431495ad26587f30e2121',
				'MM subject',
				'987',
				'a@a.com',
				't',
				'Body here',
				-1,
			],
			['id_email']
		);

		// Get the new id and track for cleanup
		$request = $db->query('', '
			SELECT MAX(id_email) 
			FROM {db_prefix}postby_emails_error',
			[]
		);
		$id_email = $request->fetch_row();
		$request->free_result();
		$this->id_email = (int) $id_email[0];
	}

	protected function tearDown(): void
	{
		$db = database();

		// Clean up created postby_emails_error rows
		foreach ($this->created_error_ids as $id) {
			$db->query('', 'DELETE FROM {db_prefix}postby_emails_error WHERE id_email = {int:id}', ['id' => $id]);
		}

		// Clean up created bounce templates
		if (!empty($this->created_template_ids)) {
			$db->query('', 'DELETE FROM {db_prefix}log_comments WHERE id_comment IN ({array_int:ids}) AND comment_type = {string:t}', [
				'ids' => $this->created_template_ids,
				't' => 'bnctpl',
			]);
		}

		parent::tearDown();
	}

	public function testSettingsSearchDisabledAndEnabled(): void
	{
		global $modSettings;

		$controller = new ManageMaillist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		// When disabled it should return the toggle entry only
		$modSettings['maillist_enabled'] = 0;
		$settings = $controller->settings_search();
		$this->assertIsArray($settings);
		$this->assertSame(['check', 'maillist_enabled'], $settings);

		// When enabled we should get the full config array
		$modSettings['maillist_enabled'] = 1;
		$settings = $controller->settings_search();
		$this->assertIsArray($settings);
		$this->assertNotEmpty($settings);
		$this->assertContains(['check', 'maillist_enabled'], $settings);
	}

	public function testListMaillistUnapprovedWrapper(): void
	{
		$this->created_error_ids[] = $this->id_email;

		$controller = new ManageMaillist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		$rows = $controller->list_maillist_unapproved(0, 50, 'id_email DESC', 0);
		$this->assertIsArray($rows);

		$found = null;
		foreach ($rows as $r) {
			if ($r['key'] === '7738c27ae6c431495ad26587f30e2121') {
				$found = $r;
				break;
			}
		}
		$this->assertNotNull($found, 'Inserted error not found via controller wrapper');
		$this->assertSame('MM subject', $found['subject']);
	}

	public function testListMaillistUnapprovedEmpty(): void
	{
		global $context;

		$controller = new ManageMaillist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		$controller->action_unapproved_email();
		$this->assertNotEmpty($context['view_email_errors']);
		$this->assertSame('Failed Emails', $context['view_email_errors']['title']);
	}

	public function testActionViewEmail(): void
	{
		global $context;

		$this->setSession();

		$_GET['item'] = $this->id_email;

		// Rebuild request wrapper after stuffing the superglobal
		$this->resetHttpReq();

		$controller = new ManageMaillist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_view_email();

		$this->assertSame('MM subject', $context['notice_subject']);
	}

	public function testActionAapproveEmail(): void
	{
		$this->setSession();

		$req = HttpReq::instance();
		$req->query->item = $this->id_email;

		$controller = new ManageMaillist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_approve_email();

		// will set error_approved, There was an error trying to approve this email
		$this->assertNotEmpty($_SESSION['email_error']);
	}


	public function testBounceTemplatesListAndCount(): void
	{
		// Insert a bounce template via subs helper
		// Using direct insert to avoid needing POST/session tokens
		$title = 'MM Bounce Template ' . uniqid('', true);
		$body = 'A body for bounce template';

		// Insert new template into log_comments
		$db = database();
		$db->insert('', '{db_prefix}log_comments',
			[
				'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
				'recipient_name' => 'string-255', 'body' => 'string-65535', 'log_time' => 'int',
			],
			[
				User::$info->id, User::$info->name, 'bnctpl', 0,
				$title, $body, time(),
			],
			['id_comment']
		);

		// Capture id for cleanup
		$req = $db->query('', '
			SELECT MAX(id_comment) FROM {db_prefix}log_comments 
			WHERE comment_type = {string:t}',
			['t' => 'bnctpl']
		);
		[$id_comment] = $req->fetch_row();
		$req->free_result();
		$this->created_template_ids[] = (int) $id_comment;

		$controller = new ManageMaillist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		// Fetch list and count via controller wrappers
		$list = $controller->list_getBounceTemplates(0, 50, 'template_title');
		$count = $controller->list_getBounceTemplateCount();

		$this->assertIsArray($list);
		$this->assertGreaterThanOrEqual(1, $count);

		$found = false;
		foreach ($list as $tpl) {
			if ($tpl['title'] === $title) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Inserted bounce template not found via controller wrapper');
	}
}
