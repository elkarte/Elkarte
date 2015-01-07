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
		global $modSettings;

		// We are not logged in for this test, so lets fake it
		$modSettings['mentions_enabled'] = true;

		$modSettings['enabled_mentions'] = 'likemsg,mentionmem';

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
			'id' => 1,
			'name' => 'test-user',
			'email' => 'noemail@test.tes',
			'update_post_count' => false,
			'ip' => ''
		);

		// Attempt to make the new topic.
		createPost($msgOptions, $topicOptions, $posterOptions);

		// Keep id of the new topic.
		$this->id_topic = $topicOptions['id'];
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
		$mentions = new Mentions_Controller();

		$test_message = basicMessageInfo($this->id_topic, true, true);

		// Lets mention the member
		$mentions->setData(array(
			'id_member' => 1,
			'type' => 'men',
			'id_msg' => $test_message['id_msg'],
			'status' => 'new',
		));
		$mentions->action_add();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'men', 1);
		$this->assertEquals(1, $count);

		// Check this is thier mention
		$this->assertTrue(findMemberMention(1, 1));
	}

	/**
	 * Mention due to a liked topic
	 */
	public function testAddMentionByLike()
	{
		$mentions = new Mentions_Controller();

		$test_message = basicMessageInfo($this->id_topic, true, true);

		// Lets like a post mention
		$mentions->setData(array(
			'id_member' => 1,
			'type' => 'like',
			'id_msg' => $test_message['id_msg'],
			'status' => 'new',
		));
		$mentions->action_add();

		// Get the number of mentions, should be one
		$count = countUserMentions(false, 'like', 1);
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
		global $user_info;

		$user_info['id'] = 1;
		$mentions = getUserMentions(0, 10, 'mtn.id_mention', true);

		$this->assertEquals(2, count($mentions));
	}
}