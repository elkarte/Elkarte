<?php

use PHPUnit\Framework\TestCase;
use ElkArte\Languages\Loader;

/**
 * TestCase class for like subs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestLikes extends TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		global $txt;
		// Lets make sure a topic exists by creating one
		require_once(SUBSDIR . '/Likes.subs.php');
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
			'ip' => long2ip(rand(0, 2147483647))
		);

		// Attempt to make the new topic.
		createPost($msgOptions, $topicOptions, $posterOptions);

		// Keep id of the new topic.
		$this->id_topic = $topicOptions['id'];
		// Hey now, force a reload, we still rely on globals!
		$lang = new Loader('english', $txt);
		$lang->load('Errors');
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		// remove temporary test data
		require_once(SUBSDIR . '/Topic.subs.php');
		removeTopics($this->id_topic); // it should remove the likes too
	}

	/**
	 * Like the topic
	 */
	public function testLikeTopic()
	{
		global $modSettings;

		$liked_message = basicMessageInfo($this->id_topic, true, true);

		// Lets like the topic
		$modSettings['likeAllowSelf'] = 1;
		likePost(1, $liked_message, '+');

		// Get the number of likes, better be one
		$likescount = messageLikeCount($this->id_topic);
		$this->assertEquals(1, $likescount);

		// Load it in, should be able to find this as well
		$likes = loadLikes($this->id_topic, false);
		$this->assertEquals(1, $likes[$this->id_topic]['count']);
	}

	/**
	 * Remove the like that was given
	 */
	public function testRemoveLikeTopic()
	{
		$liked_message = basicMessageInfo($this->id_topic, true, true);

		// Lets remove the like from the topic
		likePost(1, $liked_message, '-');

		// get the number of likes, better be none now
		$likescount = messageLikeCount($this->id_topic);
		$this->assertEquals(0, $likescount);
	}
}
