<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
require_once(SUBSDIR . '/Likes.subs.php');

/**
 * TestCase class for like subs.
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestLikes extends UnitTestCase
{
	/**
	 * Prepare some test data, to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		// Lets make sure a topic exists by creating one
		require_once(SUBSDIR . '/Post.subs.php');

		// post variables
		$msgOptions = array(
			'id' => 0,
			'subject' => 'A New Topic',
			'smileys_enabled' => true,
			'body' => 'Something for us to like, like bacon on a burger',
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
			'name' => 'test',
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
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
		// remove temporary test data
		require_once(SUBSDIR . '/Topic.subs.php');
		removeTopics($this->id_topic); // it should remove the likes too
	}

	/**
	 * Like the topic
	 */
	function testLikeTopic()
	{
		global $modSettings;

		$liked_message = basicMessageInfo($this->id_topic, true, true);

		// Lets like the topic
		$modSettings['likeAllowSelf'] = 1;
		likePost(1, $liked_message, '+');

		// Get the number of likes, better be one
		$likescount = messageLikeCount($this->id_topic);
		$this->assertEqual($likescount, 1);

		// Load it in, should be able to find this as well
		$likes = loadLikes($this->id_topic, false);
		$this->assertEqual($likes[$this->id_topic]['count'], 1);
	}

	/**
	 * Remove the like that was given
	 */
	function testRemoveLikeTopic()
	{
		$liked_message = basicMessageInfo($this->id_topic, true, true);

		// Lets remove the like from the topic
		likePost(1, $liked_message, '-');

		// get the number of likes, better be none now
		$likescount = messageLikeCount($this->id_topic);
		$this->assertEqual($likescount, 0);
	}
}