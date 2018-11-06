<?php

/**
 * TestCase class for mention subs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestMentions extends \PHPUnit\Framework\TestCase
{
	protected $_posterOptions = null;

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $modSettings, $user_info;

		// We are not logged in for this test, so lets fake it
		$modSettings['mentions_enabled'] = true;

		$modSettings['enabled_mentions'] = 'likemsg,mentionmem';

		$user_info = array(
			'id' => 1,
			'ip' => '127.0.0.1',
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'testing',
		);

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
			'id' => $user_info['id'],
			'name' => 'test-user',
			'email' => 'noemail@test.tes',
			'update_post_count' => false,
			'ip' => '127.0.0.1'
		);
		$this->_posterOptions = $posterOptions;

		// Attempt to make the new topic.
		createPost($msgOptions, $topicOptions, $posterOptions);

		// Keep id of the new topic.
		$this->id_msg = $msgOptions['id'];

		$db = database();

		$db->insert('ignore',
			'{db_prefix}notifications_pref',
			array(
				'id_member' => 'int',
				'notification_level' => 'int',
				'mention_type' => 'string',
			),
			array(
				array(
					0,
					1,
					'likemsg'
				),
				array(
					0,
					1,
					'mentionmem'
				),
				array(
					2,
					1,
					'mentionmem'
				),
				array(
					2,
					1,
					'likemsg'
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
	public function tearDown()
	{
	}

	/**
	 * Mention a member
	 */
	public function testAddMentionByMember()
	{
		$id_member = 2;

		$notifier = \ElkArte\Notifications::instance();
		$notifier->add(new \ElkArte\NotificationsTask(
			'mentionmem',
			$this->id_msg,
			$this->_posterOptions['id'],
			array('id_members' => array($id_member), 'notifier_data' => $this->_posterOptions)
		));

		$notifier->send();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'mentionmem', $id_member);
		$this->assertEquals(1, $count);

		// Check this is thier mention
		$this->assertTrue(findMemberMention(1, $id_member));
	}

	/**
	 * Mention due to a liked topic
	 */
	public function testAddMentionByLike()
	{
		global $user_info;

		$user_info['id'] = 2;
		$user_info['ip'] = '127.0.0.1';

		$notifier = \ElkArte\Notifications::instance();
		$notifier->add(new \ElkArte\NotificationsTask(
			'likemsg',
			$this->id_msg,
			$user_info['id'],
			array('id_members' => array(1), 'rlike_notif' => true)
		));

		$notifier->send();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'likemsg', $user_info['id']);
		$this->assertEquals(1, $count);
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

		$mentioning = new \ElkArte\Mentions(database(), new Data_Validator, $modSettings['enabled_mentions']);
		// Mark mention 2 as read
		$result = $mentioning->markread(2);

		$this->assertTrue($result);
	}

	/**
	 * Loads the "current user" mentions.
	 *
	 * @depends testAddMentionByLike
	 * @depends testAddMentionByMember
	 */
	public function testLoadCurrentUserMention()
	{
		global $user_info;

		// User 1 has 1 unread mention (i.e. the like)
		$user_info = array(
			'id' => 1,
		);

		$mentions = getUserMentions(0, 10, 'mtn.id_mention', true);

		$this->assertEquals(1, count($mentions));

		$user_info = array(
			'id' => 2,
		);

		// User 2 has 1 total mentions
		$mentions = getUserMentions(1, 10, 'mtn.id_mention', true);
		$this->assertEquals(0, count($mentions));

		// User 2 has 0 unread mention because it has been marked as read in testReadMention
		$mentions = getUserMentions(1, 10, 'mtn.id_mention', false);
		$this->assertEquals(0, count($mentions));
	}
}
