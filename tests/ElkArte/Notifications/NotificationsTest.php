<?php

/**
 * TestCase class for mention subs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */

namespace ElkArte\Notifications;

use ElkArte\Errors\ErrorContext;
use ElkArte\Helper\DataValidator;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Languages\Loader;
use ElkArte\Mentions\Mentioning;
use ElkArte\User;
use ElkArte\UserInfo;
use PHPUnit\Framework\TestCase;

class NotificationsTest extends TestCase
{
	protected $_posterOptions;
	protected $_msgOptions;
	protected $backupGlobalsExcludeList = ['user_info'];
	public $id_msg;

	/**
	 * setUpBeforeClass() is run automatically by the testing framework just once before any test method.
	 */
	public static function setUpBeforeClass(): void
	{
		require_once(SUBSDIR . '/Members.subs.php');

		// Just to ensure we have someone to mention
		$regOptions = [
			'interface' => 'admin',
			'username' => 'Notification User',
			'email' => 'notify@email1.tld',
			'password' => 'password',
			'password_check' => 'password',
			'check_reserved_name' => false,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'send_welcome_email' => false,
			'require' => 'nothing',
			'memberGroup' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'ip2' => long2ip(rand(0, 2147483647)),
			'auth_method' => 'password',
		];

		registerMember($regOptions);
	}

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		global $modSettings, $txt;

		// We are not logged in for this test, so lets fake it
		$modSettings['mentions_enabled'] = true;

		$modSettings['enabled_mentions'] = 'likemsg,mentionmem,buddy';
		$modSettings['notification_methods'] = serialize([
			'buddy' => [
				'notification' => "1",
				'email' => "1",
				'emaildaily' => "1",
				'emailweekly' => "1"
			],
			'likemsg' => [
				'notification' => "2"
			],
			"mentionmem" => [
				"notification" => "1",
				"email" => "1",
				"emaildaily" => "1",
				"emailweekly" => "1",
			],
			"quotedmem" => [
				"notification" => "1",
				"email" => "1",
				"emaildaily" => "1",
				"emailweekly" => "1"
			]
		]);

		User::$info = new UserInfo([
			'id' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'testing',
			'name' => 'itsme',
		]);

		$lang = new Loader('english', $txt, database());
		$lang->load('EmailTemplates+MaillistTemplates');

		// Lets start by ensuring a topic exists by creating one
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Mentions.subs.php');

		// Post variables
		$msgOptions = array(
			'id' => 0,
			'subject' => 'Mentions Topic',
			'smileys_enabled' => true,
			'body' => 'Something for us to mention, for the @admin-test user.',
			'attachments' => array(),
			'approved' => 1
		);
		$topicOptions = array(
			'id' => 0,
			'board' => 1,
			'mark_as_read' => false
		);
		$posterOptions = array(
			'id' => User::$info->id,
			'name' => 'test-user',
			'subject' => 'the subject',
			'email' => 'noemail@test.tes',
			'update_post_count' => false,
			'ip' => long2ip(rand(0, 2147483647))
		);
		$this->_posterOptions = $posterOptions;
		$this->_msgOptions = $msgOptions;

		// Attempt to make the new topic.
		createPost($msgOptions, $topicOptions, $posterOptions);

		// Keep id of the new topic.
		$this->id_msg = $msgOptions['id'];

		$db = database();

		$db->insert('',
			'{db_prefix}notifications_pref',
			array(
				'id_member' => 'int',
				'mention_type' => 'string-12',
				'notification_type' => 'string',
			),
			array(
				array(
					'id_member' => 1,
					'mention_type' => 'mentionmem',
					'notification_type' => json_encode(['email'])
				),
				array(
					'id_member' => 2,
					'mention_type' => 'mentionmem',
					'notification_type' => json_encode(['notification', 'email']),
				),
				array(
					'id_member' => 2,
					'mention_type' => 'likemsg',
					'notification_type' => json_encode(['email']),
				),
			),
			array('id_member', 'mention_type')
		);
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		global $modSettings;

		// remove temporary test data
		unset($modSettings);
		User::$info = null;

		$db = database();
		$db->query('', '
			DELETE FROM {db_prefix}notifications_pref', []);
	}

	/**
	 * Mention a member
	 */
	public function testAddMentionByMember()
	{
		$id_member = 2;

		$notifier = Notifications::instance();
		$notifier->add(new NotificationsTask(
			'mentionmem',
			$this->id_msg,
			$this->_posterOptions['id'],
			array(
				'id_members' => [$id_member],
				'subject' => $this->_msgOptions['subject'],
				'notifier_data' => $this->_posterOptions,
				'status' => 'new'
			)
		));

		$notifier->send();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'mentionmem', $id_member);
		$this->assertEquals(1, $count, 'Mention count wrong, expected 1 and found ' . $count);

		// Check this is their mention
		$this->assertTrue(findMemberMention(1, $id_member));
	}

	/**
	 * Mention due to a liked topic
	 */
	public function testAddMentionByLike()
	{
		User::$info->id = 2;
		User::$info->ip = long2ip(rand(0, 2147483647));

		$notifier = Notifications::instance();
		$notifier->add(new NotificationsTask(
			'likemsg',
			$this->id_msg,
			User::$info->id,
			array('id_members' => array(1), 'subject' => $this->_msgOptions['subject'], 'rlike_notif' => true)
		));

		$notifier->send();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'likemsg', 1);
		$this->assertEquals(1, $count);
	}

	/**
	 * Test a notification that is off
	 */
	public function testAddMentionBuddy()
	{
		User::$info->id = 2;
		User::$info->ip = long2ip(rand(0, 2147483647));

		$notifier = Notifications::instance();
		$notifier->add(new NotificationsTask(
			'buddy',
			1,
			User::$info->id,
			array('id_members' => array(1), 'subject' => $this->_msgOptions['subject'])
		));

		$notifier->send();

		// Get the number of mentions, should be zero
		$count = countUserMentions(false, 'buddy', User::$info->id);
		$this->assertEquals(0, $count);
	}

	/**
	 * Read the mention
	 *
	 * @depends testAddMentionByLike
	 * @depends testAddMentionByMember
	 */
	public function testReadMention()
	{
		global $modSettings;

		$mentioning = new Mentioning(database(), User::$info, new DataValidator, $modSettings['enabled_mentions']);
		// Mark mention 2 as read
		$result = $mentioning->markread(2);

		$this->assertTrue($result);
	}

	/**
	 * Loads the "current user" mentions.
	 *
	 * @depends testAddMentionByLike
	 * @depends testAddMentionByMember
	 * @depends testReadMention
	 */
	public function testLoadCurrentUserMention()
	{
		// User 1 has 1 unread mention (i.e. the like)
		User::$info = new UserInfo([
			'id' => 1,
		]);

		$mentions = getUserMentions(0, 10, 'mtn.id_mention', true);

		$this->assertCount(1, $mentions);

		User::$info = new UserInfo([
			'id' => 2,
		]);

		// User 2 has 1 total mentions
		$mentions = getUserMentions(1, 10, 'mtn.id_mention', true);
		$this->assertCount(0, $mentions);

		// User 2 has 0 unread mention because it has been marked as read in testReadMention
		$mentions = getUserMentions(1, 10, 'mtn.id_mention', false);
		$this->assertCount(0, $mentions);
	}
}
