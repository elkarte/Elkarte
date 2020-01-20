<?php

/**
 * TestCase class for poll subs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestPoll extends \PHPUnit\Framework\TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Prepare some test data, to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		// make sure a topic exists
		require_once(SUBSDIR . '/Poll.subs.php');
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
			'ip' => long2ip(rand(0, 2147483647))
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
	public function tearDown()
	{
		// remove temporary test data
		require_once(SUBSDIR . '/Topic.subs.php');
		removeTopics($this->id_topic); // it'll remove the poll too, if any
	}

	/**
	 * Poll creation in an existing topic
	 */
	public function testCreatePollInTopic()
	{
		// Required values to create the poll with.
		$question = 'Who is the next best contender for Grudge award?';
		$id_member = 0;
		$poster_name = 'test';

		// Create the poll.
		$id_poll = createPoll($question, $id_member, $poster_name);

		// Link the poll to the topic.
		associatedPoll($this->id_topic, $id_poll);

		// it worked, right?
		$this->assertEquals($id_poll, associatedPoll($this->id_topic));

		// get some values from it
		$pollinfo = pollInfoForTopic($this->id_topic);

		$this->assertEquals($pollinfo['id_member_started'], 1);
		$this->assertEquals($pollinfo['question'], $question);
		$this->assertEquals($pollinfo['max_votes'], 1); // the default value
		$this->assertEquals($pollinfo['poll_starter'], 0);

		// lets use pollStarters() and test its result
		list($topic_starter, $poll_starter) = pollStarters($this->id_topic);
		$this->assertEquals($topic_starter, 1);
		$this->assertEquals($poll_starter, 0);
	}

	/**
	 * Add options (choices) to a poll created with none.
	 */
	public function testAddingOptionsToPoll()
	{
		// Values to create it first
		$question = 'Who is the next best contender for Grudge award?';
		$id_member = 0;
		$poster_name = 'test';

		// Create the poll.
		$id_poll = createPoll($question, $id_member, $poster_name);

		// Link the poll to the topic.
		associatedPoll($this->id_topic, $id_poll);
		$this->assertTrue($this->id_topic > 2); // extra-test just to double-check

		$id_poll = associatedPoll($this->id_topic);
		$this->assertTrue(!empty($id_poll)); // extra-test, just in case.

		$options = array(
			'Ema, is that even a question?',
			'Ant. He broke error log. (no, he didn\'t, but we\'ll say he did.)',
			'No one this year',
		);
		addPollOptions($id_poll, $options);

		// Ok, what do we have now.
		$pollOptions = pollOptions($id_poll);
		$this->assertEquals($pollOptions[0]['label'], $options[0]);
		$this->assertEquals($pollOptions[1]['label'], $options[1]);
		$this->assertEquals($pollOptions[0]['id_choice'], 0);
		$this->assertEquals($pollOptions[1]['id_choice'], 1);
		$this->assertEquals($pollOptions[0]['votes'], 0);
		$this->assertEquals($pollOptions[1]['votes'], 0);

	}

	/**
	 * Remove an existing poll
	 */
	public function testRemovePoll()
	{
		// Values to create it first
		$question = 'Who is the next best contender for Grudge award?';
		$id_member = 0;
		$poster_name = 'test';

		// Create the poll.
		$id_poll = createPoll($question, $id_member, $poster_name);

		// Link the poll to the topic.
		associatedPoll($this->id_topic, $id_poll);
		$this->assertTrue($this->id_topic > 2); // extra-test just to double-check

		$id_poll = associatedPoll($this->id_topic);

		// we have something, right?
		$this->assertTrue(!empty($id_poll));

		removePoll($id_poll);
		associatedPoll($this->id_topic, 0); // hmm. Shouldn't we detach the poll in removePoll()?

		// was it removed from topic?
		$id_poll_new = associatedPoll($this->id_topic);
		$this->assertTrue(empty($id_poll_new));

		// or, really removed, not only dissociated
		$pollinfo = pollInfo($id_poll);
		$this->assertTrue(empty($pollinfo));
	}

	/**
	 * Modify a poll
	 */
	public function testModifyPoll()
	{
		// Values to create it first
		$question = 'Who is the next best contender for Grudge award?';
		$id_member = 0;
		$poster_name = 'test';

		// Create the poll.
		$id_poll = createPoll($question, $id_member, $poster_name);

		// Link the poll to the topic.
		associatedPoll($this->id_topic, $id_poll);

		$this->assertTrue(!empty($id_poll));
	}
}
