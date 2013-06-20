<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(SUBSDIR . '/Poll.subs.php');

/**
 * TestCase class for poll subs
 */
class TestPoll extends UnitTestCase
{
	/**
	 * @todo prepare some test data, to use in these tests
	 */
	function setUp()
	{
		// make sure a topic exists
		require_once(SUBSDIR . '/Post.subs.php');

		// post variables
		$msgOptions = array(
			'id' => 0,
			'subject' => 'Test poll topic',
			'smileys_enabled' => true,
			'body' => 'This is a test poll.',
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
	 * cleanup data we no longer need at the end of the tests in this class.
	 */
	function tearDown()
	{
		// remove temporary test data
		require_once(SUBSDIR . '/Topic.subs.php');
		removeTopics($this->id_topic);
	}

	/**
	 * Poll creation in an existing topic
	 */
	function testCreatePollInTopic()
	{
		$question = 'Who is the next best contender for Grudge award?';
		$id_member = 1;
		$poster_name = 'test';

		// Create the poll.
		$id_poll = createPoll($question, $id_member, $poster_name);

		// Link the poll to the topic.
		associatedPoll($this->id_topic, $id_poll);

		// it worked, right?
		$this->assertEqual($id_poll, associatedPoll($this->id_topic));

		// get some values from it
		$pollinfo = pollInfoForTopic($this->id_topic);
		
		$this->assertEqual($pollinfo['id_member_started'], 1);
		$this->assertEqual($pollinfo['question'], $question);
		$this->assertEqual($pollinfo['max_votes'], 1); // the default value
		$this->assertEqual($pollinfo['poll_starter'], 1);
	}
}
