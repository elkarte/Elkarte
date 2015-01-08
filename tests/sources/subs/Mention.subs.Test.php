<?php

/**
 * TestCase class for mention subs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestMentions extends PHPUnit_Framework_TestCase
{
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

		// Attempt to make the new topic.
		createPost($msgOptions, $topicOptions, $posterOptions);

		// Keep id of the new topic.
		$this->id_msg = $msgOptions['id'];
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
		$loader = new Controller_Loader('Mentions');
		$mentions = $loader->initDispatch();
		$id_member = 2;

		// Lets mention the member
		$mentions->setData(array(
			'id_member' => $id_member,
			'type' => 'mentionmem',
			'id_msg' => $this->id_msg,
			'status' => 'new',
		));
		$mentions->action_add();

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
		$loader = new Controller_Loader('Mentions');
		$mentions = $loader->initDispatch();
		$id_member = 1;

		// Lets like a post mention
		$mentions->setData(array(
			'id_member' => $id_member,
			'type' => 'likemsg',
			'id_msg' => $this->id_msg,
			'status' => 'new',
		));
		$mentions->action_add();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'likemsg', $id_member);
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
		// Mark mention 2 as read
		$result = changeMentionStatus(2, 1);

		$this->assertTrue($result);
	}

	/**
	 * Load all the users mentions
	 *
	 * @depends testAddMentionByLike
	 * @depends testAddMentionByMember
	 */
	public function testLoadMention()
	{
// 		global $user_info;

// 		$user_info['id'] = 1;
		$mentions = getUserMentions(0, 10, 'mtn.id_mention', true);

		$this->assertEquals(2, count($mentions));
	}
}