<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

/**
 * TestCase class for topic subs: database operations and manipulations on topics.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TopicSubsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Track created topic IDs for cleanup in tearDown.
	 * @var int[]
	 */
	protected $createdTopics = [];

	/**
	 * Prepare some test data and dependencies before each test method.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		parent::setUp();
		parent::setSession();

		global $board, $topic;
		$board = 0;
		$topic = 0;

		User::$info->mod_cache = [
			'bq' => '1=1',
			'ap' => [0],
			'gq' => '1=1',
			'time' => time(),
			'id' => 1,
			'mb' => 1,
			'mq' => 'b.id_board IN (1)',
		];

		global $txt;
		$lang = new Loader('english', $txt, database());
		$lang->load('Post');
		$lang->load('Errors');
		$lang->load('Index');
	}

	/**
	 * Cleanup all topics and test data created during test execution.
	 */
	protected function tearDown(): void
	{
		global $board, $topic;
		$board = 0;
		$topic = 0;

		if (!empty($this->createdTopics))
		{
			removeTopics($this->createdTopics, false, true);
			$this->createdTopics = [];
		}

		parent::tearDown();
	}

	/**
	 * Helper method to create a test topic with an initial message and optional replies.
	 *
	 * @param string $subject
	 * @param int $board
	 * @param int $memberId
	 * @param int $numReplies
	 * @param bool $approved
	 * @return array ['topic_id' => int, 'first_msg' => int, 'messages' => int[]]
	 */
	protected function createTestTopic(string $subject = 'TopicSubs Test Topic', int $board = 1, int $memberId = 1, int $numReplies = 0, bool $approved = true): array
	{
		$msgOptions = [
			'id' => 0,
			'subject' => $subject,
			'smileys_enabled' => true,
			'body' => 'This is the initial message body for ' . $subject,
			'attachments' => [],
			'approved' => $approved ? 1 : 0
		];

		$topicOptions = [
			'id' => 0,
			'board' => $board,
			'mark_as_read' => false
		];

		$posterOptions = [
			'id' => $memberId,
			'name' => 'testuser' . $memberId,
			'email' => 'test' . $memberId . '@example.com',
			'update_post_count' => false,
			'ip' => '127.0.0.1'
		];

		createPost($msgOptions, $topicOptions, $posterOptions);

		$topicId = (int) $topicOptions['id'];
		$firstMsgId = (int) $msgOptions['id'];
		$messages = [$firstMsgId];

		$this->createdTopics[] = $topicId;

		for ($i = 1; $i <= $numReplies; $i++)
		{
			$replyMsgOptions = [
				'id' => 0,
				'subject' => 'Re: ' . $subject,
				'smileys_enabled' => true,
				'body' => 'This is reply message number ' . $i,
				'attachments' => [],
				'approved' => $approved ? 1 : 0
			];

			$replyTopicOptions = [
				'id' => $topicId,
				'board' => $board,
				'mark_as_read' => false
			];

			createPost($replyMsgOptions, $replyTopicOptions, $posterOptions);
			$messages[] = (int) $replyMsgOptions['id'];
		}

		return [
			'topic_id' => $topicId,
			'first_msg' => $firstMsgId,
			'last_msg' => end($messages),
			'messages' => $messages
		];
	}

	/**
	 * Test topicAttribute() with both single topic ID and array of topic IDs.
	 */
	public function testTopicAttributeSingleAndArray(): void
	{
		$fixture1 = $this->createTestTopic('Topic Attribute 1');
		$fixture2 = $this->createTestTopic('Topic Attribute 2');

		// Single topic query
		$singleAttr = topicAttribute($fixture1['topic_id'], ['id_topic', 'id_board', 'locked', 'is_sticky']);
		$this->assertIsArray($singleAttr);
		$this->assertEquals($fixture1['topic_id'], (int) $singleAttr['id_topic']);
		$this->assertEquals(1, (int) $singleAttr['id_board']);
		$this->assertEquals(0, (int) $singleAttr['locked']);
		$this->assertEquals(0, (int) $singleAttr['is_sticky']);

		// Multiple topics query
		$multiAttr = topicAttribute([$fixture1['topic_id'], $fixture2['topic_id']], ['id_topic', 'id_board']);
		$this->assertIsArray($multiAttr);
		$this->assertCount(2, $multiAttr);
		$ids = array_column($multiAttr, 'id_topic');
		$this->assertContains((string) $fixture1['topic_id'], $ids);
		$this->assertContains((string) $fixture2['topic_id'], $ids);
	}

	/**
	 * Test topicStatus() returns correct starter and locked status.
	 */
	public function testTopicStatus(): void
	{
		$fixture = $this->createTestTopic('Topic Status Test', 1, 1);
		list($starter, $locked) = topicStatus($fixture['topic_id']);

		$this->assertEquals(1, $starter);
		$this->assertEquals(0, $locked);
	}

	/**
	 * Test topicUserAttributes() returns correct attributes for a given topic and user.
	 */
	public function testTopicUserAttributes(): void
	{
		$fixture = $this->createTestTopic('Topic User Attributes Test', 1, 1);
		$attrs = topicUserAttributes($fixture['topic_id'], 1);

		$this->assertIsArray($attrs);
		$this->assertArrayHasKey('locked', $attrs);
		$this->assertArrayHasKey('notify', $attrs);
		$this->assertArrayHasKey('is_sticky', $attrs);
		$this->assertArrayHasKey('id_poll', $attrs);
		$this->assertArrayHasKey('id_last_msg', $attrs);
		$this->assertArrayHasKey('id_first_msg', $attrs);
		$this->assertArrayHasKey('subject', $attrs);
		$this->assertArrayHasKey('last_post_time', $attrs);
		$this->assertEquals($fixture['first_msg'], (int) $attrs['id_first_msg']);
		$this->assertEquals('Topic User Attributes Test', $attrs['subject']);
	}

	/**
	 * Test topicsDetails() returns correct details for given topic IDs.
	 */
	public function testTopicsDetails(): void
	{
		$fixture1 = $this->createTestTopic('Topic Details 1');
		$fixture2 = $this->createTestTopic('Topic Details 2');

		$details = topicsDetails([$fixture1['topic_id'], $fixture2['topic_id']]);
		$this->assertIsArray($details);
		$this->assertCount(2, $details);

		$firstDetail = $details[0];
		$this->assertArrayHasKey('id_topic', $firstDetail);
		$this->assertArrayHasKey('id_member_started', $firstDetail);
		$this->assertArrayHasKey('id_board', $firstDetail);
		$this->assertArrayHasKey('locked', $firstDetail);
		$this->assertArrayHasKey('approved', $firstDetail);
		$this->assertArrayHasKey('unapproved_posts', $firstDetail);
	}

	/**
	 * Test topicsStartedBy() returns correct topic IDs for a given member.
	 */
	public function testTopicsStartedBy(): void
	{
		$fixture = $this->createTestTopic('Topic Started By Test', 1, 1);
		$topicIds = topicsStartedBy(1);

		$this->assertIsArray($topicIds);
		$this->assertContains((string) $fixture['topic_id'], $topicIds);
	}

	/**
	 * Test getSubject() returns the correct subject for a given topic ID.
	 */
	public function testGetSubject(): void
	{
		$fixture = $this->createTestTopic('Unique Subject For Test');
		$subject = getSubject($fixture['topic_id']);

		$this->assertEquals('Unique Subject For Test', $subject);
	}

	/**
	 * Test getSubject() throws an exception for a non-existent topic ID.
	 */
	public function testGetSubjectException(): void
	{
		$this->expectException(\ElkArte\Exceptions\Exception::class);
		getSubject(99999999);
	}

	/**
	 * Test countTopicsByBoard() returns the correct count for a given board.
	 */
	public function testCountTopicsByBoard(): void
	{
		$initialCount = countTopicsByBoard(1, true);
		$this->assertIsInt($initialCount);

		$this->createTestTopic('Count Topic Board Test 1', 1, 1, 0, true);
		$newCount = countTopicsByBoard(1, true);
		$this->assertGreaterThanOrEqual($initialCount + 1, $newCount);
	}

	/**
	 * Test messagesSince() and countMessagesSince() return correct counts.
	 */
	public function testMessagesSinceAndCountMessages(): void
	{
		$fixture = $this->createTestTopic('Messages Count Since Test', 1, 1, 3);
		$msgIds = $fixture['messages'];
		$firstMsg = $msgIds[0];
		$secondMsg = $msgIds[1];

		// messagesSince
		$sinceExclusive = messagesSince($fixture['topic_id'], $secondMsg, false);
		$this->assertCount(2, $sinceExclusive);
		$this->assertEquals([$msgIds[2], $msgIds[3]], array_map('intval', $sinceExclusive));

		$sinceInclusive = messagesSince($fixture['topic_id'], $secondMsg, true);
		$this->assertCount(3, $sinceInclusive);
		$this->assertEquals([$msgIds[1], $msgIds[2], $msgIds[3]], array_map('intval', $sinceInclusive));

		// countMessagesSince
		$countSince = countMessagesSince($fixture['topic_id'], $firstMsg, false);
		$this->assertEquals(3, $countSince);

		$countSinceInclusive = countMessagesSince($fixture['topic_id'], $firstMsg, true);
		$this->assertEquals(4, $countSinceInclusive);

		// empty parameters check
		$this->assertFalse(countMessagesSince(0, 0));

		// countMessagesBefore
		$countBefore = countMessagesBefore($fixture['topic_id'], $msgIds[2], false);
		$this->assertEquals(2, $countBefore);

		$countBeforeInclusive = countMessagesBefore($fixture['topic_id'], $msgIds[2], true);
		$this->assertEquals(3, $countBeforeInclusive);
	}

	/**
	 * Test unapprovedPosts() returns correct count for a given topic and member.
	 */
	public function testUnapprovedPosts(): void
	{
		// Empty member ID returns empty array
		$this->assertSame([], unapprovedPosts(1, 0));

		$fixture = $this->createTestTopic('Unapproved Topic', 1, 1, 1, false);
		$unapproved = unapprovedPosts($fixture['topic_id'], 1);
		$this->assertEquals(2, $unapproved);
	}

	/**
	 * Test postersCount() and topicsPosters() return correct counts for a given topic.
	 */
	public function testPostersCountAndTopicsPosters(): void
	{
		$fixture = $this->createTestTopic('Posters Count Test', 1, 1, 2);

		$posters = postersCount($fixture['topic_id']);
		$this->assertIsArray($posters);
		$this->assertArrayHasKey(1, $posters);
		$this->assertEquals(3, $posters[1]);

		$topicsPosters = topicsPosters([$fixture['topic_id']]);
		$this->assertIsArray($topicsPosters);
		$this->assertArrayHasKey(1, $topicsPosters);
		$this->assertContains($fixture['topic_id'], $topicsPosters[1]);
	}

	/**
	 * Test messagesInTopics() returns correct messages for given topic IDs.
	 */
	public function testMessagesInTopics(): void
	{
		$fixture = $this->createTestTopic('Messages In Topics Test', 1, 1, 2);
		$messages = messagesInTopics([$fixture['topic_id']]);

		$this->assertIsArray($messages);
		$this->assertCount(3, $messages);
		foreach ($fixture['messages'] as $msgId)
		{
			$this->assertContains((string) $msgId, $messages);
		}
	}

	/**
	 * Test topicsList() returns correct list for given topic IDs.
	 */
	public function testTopicsList(): void
	{
		$this->assertSame([], topicsList([]));

		$fixture = $this->createTestTopic('Topics List Subject Test');
		$list = topicsList([$fixture['topic_id']]);

		$this->assertIsArray($list);
		$this->assertArrayHasKey($fixture['topic_id'], $list);
		$this->assertEquals('Topics List Subject Test', $list[$fixture['topic_id']]['subject']);
	}

	/**
	 * Test getTopicsPostsAndPoster() returns correct messages and posters for a given topic.
	 */
	public function testGetTopicsPostsAndPoster(): void
	{
		$fixture = $this->createTestTopic('Posts and Poster Test', 1, 1, 2);

		$limit = ['start' => 0, 'offset' => 10, 'messages_per_page' => 10];
		$result = getTopicsPostsAndPoster($fixture['topic_id'], $limit, true);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('messages', $result);
		$this->assertArrayHasKey('all_posters', $result);
		$this->assertCount(3, $result['messages']);
	}

	/**
	 * Test topicMessages() and selectMessages() return correct messages for a given topic.
	 */
	public function testTopicMessagesAndSelectMessages(): void
	{
		$fixture = $this->createTestTopic('Topic Messages Render Test', 1, 1, 1);

		// topicMessages()
		$printMessages = topicMessages($fixture['topic_id'], 'print');
		$this->assertIsArray($printMessages);
		$this->assertCount(2, $printMessages);

		$firstPost = reset($printMessages);
		$this->assertArrayHasKey('subject', $firstPost);
		$this->assertArrayHasKey('body', $firstPost);
		$this->assertArrayHasKey('member', $firstPost);
		$this->assertArrayHasKey('id_msg', $firstPost);

		// selectMessages()
		$selected = selectMessages($fixture['topic_id'], 0, 10, [], true);
		$this->assertIsArray($selected);
		$this->assertCount(2, $selected);

		$firstSelected = reset($selected);
		$this->assertArrayHasKey('id', $firstSelected);
		$this->assertArrayHasKey('subject', $firstSelected);
		$this->assertArrayHasKey('poster', $firstSelected);
	}

	/**
	 * Test getTopicInfo() returns correct information for a given topic ID.
	 */
	public function testGetTopicInfo(): void
	{
		$this->assertFalse(getTopicInfo([]));

		$fixture = $this->createTestTopic('Topic Info Test Subject', 1, 1, 1);

		// Basic topic info
		$basicInfo = getTopicInfo($fixture['topic_id']);
		$this->assertIsArray($basicInfo);
		$this->assertEquals($fixture['topic_id'], $basicInfo['id_topic']);
		$this->assertEquals(1, $basicInfo['id_board']);
		$this->assertEquals(1, $basicInfo['num_replies']);

		// Full 'message' topic info
		$msgInfo = getTopicInfo($fixture['topic_id'], 'message');
		$this->assertIsArray($msgInfo);
		$this->assertEquals('Topic Info Test Subject', $msgInfo['subject']);
		$this->assertNotEmpty($msgInfo['body']);

		// Full 'starter' topic info
		$starterInfo = getTopicInfo($fixture['topic_id'], 'starter');
		$this->assertIsArray($starterInfo);
		$this->assertArrayHasKey('poster_name', $starterInfo);

		// Full 'all' topic info
		$allInfo = getTopicInfo($fixture['topic_id'], 'all');
		$this->assertIsArray($allInfo);
		$this->assertArrayHasKey('new_from', $allInfo);
		$this->assertArrayHasKey('unwatched', $allInfo);
	}

	/**
	 * Test getTopicInfoByMsg() returns correct information for a given message ID.
	 */
	public function testGetTopicInfoByMsg(): void
	{
		$this->assertFalse(getTopicInfoByMsg(0));

		$fixture = $this->createTestTopic('Topic Info By Msg Test', 1, 1, 1);
		$msgInfo = getTopicInfoByMsg($fixture['topic_id'], $fixture['first_msg']);

		$this->assertIsArray($msgInfo);
		$this->assertEquals($fixture['first_msg'], $msgInfo['id_msg']);
		$this->assertEquals('Topic Info By Msg Test', $msgInfo['subject']);
		$this->assertEquals(1, $msgInfo['id_member']);
	}

	/**
	 * Test topicPointerAndNextPrevious() returns correct next and previous topic IDs.
	 */
	public function testTopicPointerAndNextPrevious(): void
	{
		$fixture1 = $this->createTestTopic('Topic Pointer 1');
		$fixture2 = $this->createTestTopic('Topic Pointer 2');

		$nextTopic = nextTopic($fixture1['topic_id'], 1);
		$this->assertIsNumeric($nextTopic);

		$prevTopic = previousTopic($fixture2['topic_id'], 1);
		$this->assertIsNumeric($prevTopic);
	}

	/**
	 * Test getUnreadCountSince() returns correct count for a given member and timestamp.
	 */
	public function testGetUnreadCountSince(): void
	{
		$this->createTestTopic('Unread Count Test');
		$count = getUnreadCountSince(1, 0);

		$this->assertIsNumeric($count);
		$this->assertGreaterThanOrEqual(0, (int) $count);
	}

	/**
	 * Test mergeableTopics() returns correct mergeable candidates for a given topic.
	 */
	public function testMergeableTopics(): void
	{
		$fixture1 = $this->createTestTopic('Mergeable Candidate 1');
		$fixture2 = $this->createTestTopic('Mergeable Candidate 2');

		global $modSettings;
		$modSettings['defaultMaxTopics'] = 20;

		$mergeables = mergeableTopics(1, $fixture1['topic_id'], true, 0);
		$this->assertIsArray($mergeables);
		$this->assertNotEmpty($mergeables);

		$found = false;
		foreach ($mergeables as $candidate)
		{
			if ($candidate['id'] === $fixture2['topic_id'])
			{
				$found = true;
				$this->assertArrayHasKey('poster', $candidate);
				$this->assertArrayHasKey('subject', $candidate);
				break;
			}
		}
		$this->assertTrue($found, 'Candidate topic should be mergeable');
	}

	/**
	 * Test topicNotificationCount() and topicNotifications() return correct counts and notifications for a given member.
	 */
	public function testTopicNotificationCountAndList(): void
	{
		$fixture = $this->createTestTopic('Notification Topic Test');
		setTopicNotification(1, $fixture['topic_id'], true);

		$count = topicNotificationCount(1);
		$this->assertIsInt($count);
		$this->assertGreaterThanOrEqual(1, $count);

		$notifications = topicNotifications(0, 10, 't.id_topic DESC', 1);
		$this->assertIsArray($notifications);

		$found = false;
		foreach ($notifications as $notif)
		{
			if ($notif['id'] == $fixture['topic_id'])
			{
				$found = true;
				$this->assertArrayHasKey('subject', $notif);
				$this->assertArrayHasKey('href', $notif);
				break;
			}
		}
		$this->assertTrue($found, 'Subscribed topic notification was found');

		setTopicNotification(1, $fixture['topic_id'], false);
	}

	/**
	 * Test increaseViewCounter() increments the view count for a given topic.
	 */
	public function testIncreaseViewCounter(): void
	{
		$fixture = $this->createTestTopic('View Counter Test');
		$initialInfo = topicAttribute($fixture['topic_id'], 'num_views');
		$initialViews = (int) $initialInfo['num_views'];

		increaseViewCounter($fixture['topic_id']);

		$updatedInfo = topicAttribute($fixture['topic_id'], 'num_views');
		$this->assertEquals($initialViews + 1, (int) $updatedInfo['num_views']);
	}

	/**
	 * Test messagesAttachments() returns an empty array for non-existent message IDs.
	 */
	public function testMessagesAttachments(): void
	{
		$result = messagesAttachments([99999999]);
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test setTopicAttribute() sets the correct attributes for a given topic.
	 */
	public function testSetTopicAttribute(): void
	{
		$fixture = $this->createTestTopic('Set Topic Attribute Test');

		// Empty attributes should return false
		$this->assertFalse(setTopicAttribute($fixture['topic_id'], []));

		// Set locked and is_sticky
		$affected = setTopicAttribute($fixture['topic_id'], ['locked' => 1, 'is_sticky' => 1]);
		$this->assertEquals(1, $affected);

		$attrs = topicAttribute($fixture['topic_id'], ['locked', 'is_sticky']);
		$this->assertEquals(1, (int) $attrs['locked']);
		$this->assertEquals(1, (int) $attrs['is_sticky']);
	}

	/**
	 * Test toggleTopicsLock() correctly toggles the lock status for a given topic.
	 */
	public function testToggleTopicsLock(): void
	{
		$fixture = $this->createTestTopic('Toggle Lock Test');

		// Initial lock status = 0
		$status = topicStatus($fixture['topic_id']);
		$this->assertEquals(0, $status[1]);

		// Toggle to locked (1)
		toggleTopicsLock([$fixture['topic_id']], false);
		$status = topicStatus($fixture['topic_id']);
		$this->assertNotEquals(0, $status[1]);

		// Toggle back to unlocked (0)
		toggleTopicsLock([$fixture['topic_id']], false);
		$status = topicStatus($fixture['topic_id']);
		$this->assertEquals(0, $status[1]);
	}

	/**
	 * Test toggleTopicSticky() correctly toggles the sticky status for a given topic.
	 */
	public function testToggleTopicSticky(): void
	{
		$fixture = $this->createTestTopic('Toggle Sticky Test');

		$attr = topicAttribute($fixture['topic_id'], 'is_sticky');
		$this->assertEquals(0, (int) $attr['is_sticky']);

		// Toggle sticky to 1
		$toggled = toggleTopicSticky([$fixture['topic_id']], false);
		$this->assertEquals(1, $toggled);

		$attr = topicAttribute($fixture['topic_id'], 'is_sticky');
		$this->assertEquals(1, (int) $attr['is_sticky']);

		// Toggle sticky back to 0
		toggleTopicSticky([$fixture['topic_id']], false);
		$attr = topicAttribute($fixture['topic_id'], 'is_sticky');
		$this->assertEquals(0, (int) $attr['is_sticky']);
	}

	/**
	 * Test setTopicWatch() and getLoggedTopics() correctly manage topic watch status.
	 */
	public function testSetTopicWatchAndGetLoggedTopics(): void
	{
		$fixture = $this->createTestTopic('Topic Watch Test');

		// Set watch on
		setTopicWatch(1, $fixture['topic_id'], true);
		$logged = getLoggedTopics(1, [$fixture['topic_id']]);

		$this->assertArrayHasKey($fixture['topic_id'], $logged);
		$this->assertEquals(1, (int) $logged[$fixture['topic_id']]['unwatched']);

		// Set watch off
		setTopicWatch(1, $fixture['topic_id'], false);
		$logged = getLoggedTopics(1, [$fixture['topic_id']]);
		$this->assertEquals(0, (int) $logged[$fixture['topic_id']]['unwatched']);
	}

	/**
	 * Test topicNotificationManagement() correctly manages topic notifications for a given member.
	 */
	public function testTopicNotificationManagement(): void
	{
		$fixture = $this->createTestTopic('Notification Management Test');

		$this->assertFalse(hasTopicNotification(1, $fixture['topic_id']));

		// Enable notification
		setTopicNotification(1, $fixture['topic_id'], true);
		$this->assertTrue(hasTopicNotification(1, $fixture['topic_id']));

		// Disable notification
		setTopicNotification(1, $fixture['topic_id'], false);
		$this->assertFalse(hasTopicNotification(1, $fixture['topic_id']));
	}

	/**
	 * Test updateReadNotificationsFor() correctly updates read notifications for a given topic and member.
	 */
	public function testUpdateReadNotificationsFor(): void
	{
		global $context;
		$fixture = $this->createTestTopic('Read Notifications Test');

		setTopicNotification(1, $fixture['topic_id'], true);
		updateReadNotificationsFor($fixture['topic_id'], 1);

		$this->assertTrue(!empty($context['is_marked_notify']));
		setTopicNotification(1, $fixture['topic_id'], false);
	}

	/**
	 * Test markTopicsRead() correctly marks topics as read for a given member.
	 */
	public function testMarkTopicsRead(): void
	{
		$fixture = $this->createTestTopic('Mark Topics Read Test');

		markTopicsRead([[1, $fixture['topic_id'], $fixture['first_msg'], 0]], false);
		$logged = getLoggedTopics(1, [$fixture['topic_id']]);

		$this->assertArrayHasKey($fixture['topic_id'], $logged);
		$this->assertEquals($fixture['first_msg'], (int) $logged[$fixture['topic_id']]['id_msg']);
	}

	/**
	 * Test updateTopicStats() correctly updates the total topic count.
	 */
	public function testUpdateTopicStats(): void
	{
		global $modSettings;

		$initial = (int) ($modSettings['totalTopics'] ?? 0);

		// Increment
		updateTopicStats(true);
		$this->assertEquals($initial + 1, (int) $modSettings['totalTopics']);

		// Recount
		updateTopicStats();
		$this->assertIsNumeric($modSettings['totalTopics']);
	}

	/**
	 * Test approveTopics() and approveMessages() correctly approve and unapprove topics and messages.
	 */
	public function testApproveTopicsAndMessages(): void
	{
		$this->assertFalse(approveTopics([]));

		$fixture = $this->createTestTopic('Unapproved Topic Test', 1, 1, 1, false);

		$attr = topicAttribute($fixture['topic_id'], 'approved');
		$this->assertEquals(0, (int) $attr['approved']);

		// Approve topic
		approveTopics([$fixture['topic_id']], true, false);

		$attr = topicAttribute($fixture['topic_id'], 'approved');
		$this->assertEquals(1, (int) $attr['approved']);

		// Unapprove topic
		approveTopics([$fixture['topic_id']], false, false);

		$attr = topicAttribute($fixture['topic_id'], 'approved');
		$this->assertEquals(0, (int) $attr['approved']);

		// Approve via approveMessages
		approveMessages([$fixture['topic_id']], [], 'topics');
		$attr = topicAttribute($fixture['topic_id'], 'approved');
		$this->assertEquals(1, (int) $attr['approved']);
	}

	/**
	 * Test splitTopic() successfully splits a topic into a new topic with the selected messages.
	 */
	public function testSplitTopicSuccess(): void
	{
		$fixture = $this->createTestTopic('Original Topic For Split', 1, 1, 3);
		$splitMsgs = [$fixture['messages'][2], $fixture['messages'][3]];

		$newTopicId = splitTopic($fixture['topic_id'], $splitMsgs, 'New Splitted Topic');
		$this->assertGreaterThan(0, $newTopicId);
		$this->createdTopics[] = $newTopicId;

		// Original topic should have 1 reply left
		$origInfo = getTopicInfo($fixture['topic_id']);
		$this->assertEquals(1, $origInfo['num_replies']);
		$this->assertEquals($fixture['messages'][0], $origInfo['id_first_msg']);
		$this->assertEquals($fixture['messages'][1], $origInfo['id_last_msg']);

		// New topic should have 1 reply and correct messages
		$newInfo = getTopicInfo($newTopicId, 'message');
		$this->assertEquals(1, $newInfo['num_replies']);
		$this->assertEquals($fixture['messages'][2], $newInfo['id_first_msg']);
		$this->assertEquals($fixture['messages'][3], $newInfo['id_last_msg']);
		$this->assertEquals('New Splitted Topic', $newInfo['subject']);
	}

	/**
	 * Test splitTopic() throws exceptions for invalid split scenarios.
	 */
	public function testSplitTopicExceptions(): void
	{
		global $txt;

		$fixture = $this->createTestTopic('Split Topic Exceptions Test', 1, 1, 2);

		// 1. Empty messages
		try
		{
			splitTopic($fixture['topic_id'], [], 'New Subject');
			$this->fail('Expected exception on empty split messages');
		}
		catch (\ElkArte\Exceptions\Exception $e)
		{
			$this->assertSame($txt['no_posts_selected'], $e->getMessage());
		}

		// 2. Selected all messages
		try
		{
			splitTopic($fixture['topic_id'], $fixture['messages'], 'New Subject');
			$this->fail('Expected exception on selecting all messages');
		}
		catch (\ElkArte\Exceptions\Exception $e)
		{
			$this->assertSame($txt['selected_all_posts'], $e->getMessage());
		}

		// 3. Splitting the first post
		try
		{
			splitTopic($fixture['topic_id'], [$fixture['messages'][0]], 'New Subject');
			$this->fail('Expected exception on splitting first message');
		}
		catch (\ElkArte\Exceptions\Exception $e)
		{
			$this->assertSame($txt['split_first_post'], $e->getMessage());
		}
	}

	/**
	 * Test updateSplitTopics() correctly updates split topic information.
	 */
	public function testUpdateSplitTopics(): void
	{
		$fixture1 = $this->createTestTopic('UpdateSplit Topic 1', 1, 1, 2);
		$fixture2 = $this->createTestTopic('UpdateSplit Topic 2', 1, 1, 1);

		$options = [
			'splitMessages' => [$fixture1['messages'][1]],
			'split1_ID_TOPIC' => $fixture1['topic_id'],
			'split1_replies' => 0,
			'split1_first_msg' => $fixture1['messages'][0],
			'split1_last_msg' => $fixture1['messages'][0],
			'split1_firstMem' => 1,
			'split1_lastMem' => 1,
			'split1_unapprovedposts' => 0,
			'split2_ID_TOPIC' => $fixture2['topic_id'],
			'split2_first_msg' => $fixture1['messages'][1],
			'split2_last_msg' => $fixture1['messages'][1],
			'split2_approved' => true,
		];

		updateSplitTopics($options, 1);

		$info = getTopicInfo($fixture1['topic_id']);
		$this->assertEquals(0, $info['num_replies']);
		$this->assertEquals($fixture1['messages'][0], $info['id_last_msg']);
	}

	/**
	 * Test splitDestinationBoard() returns the correct destination board information.
	 */
	public function testSplitDestinationBoard(): void
	{
		global $board, $topic;
		$fixture = $this->createTestTopic('Split Destination Board Test');
		$board = 1;
		$topic = $fixture['topic_id'];

		$dest = splitDestinationBoard(1);
		$this->assertIsArray($dest);
		$this->assertArrayHasKey('current', $dest);
		$this->assertArrayHasKey('destination', $dest);
		$this->assertEquals(1, $dest['destination']['id']);
	}

	/**
	 * Test splitAttemptMove() correctly attempts to move a split topic to the destination board.
	 */
	public function testSplitAttemptMove(): void
	{
		global $board;
		$board = 1;

		$fixture = $this->createTestTopic('Split Attempt Move Test');
		$boards = [
			'current' => ['id' => 1, 'count_posts' => 0],
			'destination' => ['id' => 1, 'count_posts' => 0]
		];

		$result = splitAttemptMove($boards, $fixture['topic_id']);
		$this->assertEquals(1, $result['id']);
	}

	/**
	 * Test postSplitRedirect() correctly handles post-split redirection.
	 */
	public function testPostSplitRedirect(): void
	{
		global $board, $topic;
		$fixture = $this->createTestTopic('Post Split Redirect Topic');
		$board = 1;
		$topic = $fixture['topic_id'];

		$boardInfo = ['id' => 1, 'name' => 'General Board', 'count_posts' => 0];
		postSplitRedirect('Redirect reason text', 'Target Topic Subject', $boardInfo, $fixture['topic_id']);

		$info = getTopicInfo($fixture['topic_id']);
		$this->assertGreaterThan(0, $info['num_replies']);
	}

	/**
	 * Test fixMergedTopics() correctly merges source topics into the target topic.
	 */
	public function testFixMergedTopics(): void
	{
		$fixture1 = $this->createTestTopic('Merge Target Topic', 1, 1, 1);
		$fixture2 = $this->createTestTopic('Merge Source Topic', 1, 1, 1);

		fixMergedTopics(
			$fixture1['first_msg'],
			[$fixture1['topic_id'], $fixture2['topic_id']],
			$fixture1['topic_id'],
			1,
			'Merged Final Subject',
			'enforce',
			[]
		);

		// Source topic should be deleted
		$this->assertEmpty(getTopicInfo($fixture2['topic_id']));

		// Target topic should contain all messages and updated subject
		$targetInfo = getTopicInfo($fixture1['topic_id'], 'message');
		$this->assertEquals('Merged Final Subject', $targetInfo['subject']);

		$messages = messagesInTopics([$fixture1['topic_id']]);
		$this->assertCount(4, $messages);
	}

	/**
	 * Test moveTopics() correctly moves a topic to a new board.
	 */
	public function testMoveTopics(): void
	{
		$fixture = $this->createTestTopic('Move Topic Test', 1, 1, 1);

		moveTopics($fixture['topic_id'], 1, false);

		$info = getTopicInfo($fixture['topic_id']);
		$this->assertEquals(1, $info['id_board']);
	}

	/**
	 * Test moveTopicsPermissions() correctly moves topics with permissions.
	 */
	public function testMoveTopicsPermissions(): void
	{
		$fixture = $this->createTestTopic('Move Topics Permissions Test', 1, 1, 0);

		$moveCache = [
			0 => [$fixture['topic_id']],
			1 => [$fixture['topic_id'] => 1]
		];

		moveTopicsPermissions($moveCache);

		$info = getTopicInfo($fixture['topic_id']);
		$this->assertEquals(1, $info['id_board']);
	}

	/**
	 * Test moveTopicConcurrence() correctly handles topic move concurrence scenarios.
	 */
	public function testMoveTopicConcurrence(): void
	{
		// Same board returns true
		$this->assertTrue(moveTopicConcurrence(1, 1, 1));
		$this->assertTrue(moveTopicConcurrence(0, 0, 0));

		$fixture = $this->createTestTopic('Concurrence Topic');

		try
		{
			moveTopicConcurrence(1, 2, $fixture['topic_id']);
			$this->fail('Expected Exception topic_already_moved');
		}
		catch (\ElkArte\Exceptions\Exception $e)
		{
			$this->assertInstanceOf(\ElkArte\Exceptions\Exception::class, $e);
			$this->assertStringContainsString('Concurrence Topic', $e->getMessage());
			$this->assertStringContainsString('has been moved to the board', $e->getMessage());
		}
	}

	/**
	 * Test removeDeleteConcurrence() correctly handles delete concurrence scenarios.
	 */
	public function testRemoveDeleteConcurrence(): void
	{
		global $modSettings, $board;
		$modSettings['recycle_enable'] = 0;
		$board = 1;

		// When recycling is not enabled, simply returns without exception
		removeDeleteConcurrence();
		$this->assertTrue(true);
	}

	/**
	 * Test removeOldTopics() correctly removes topics older than a given timestamp.
	 */
	public function testRemoveOldTopics(): void
	{
		$fixture = $this->createTestTopic('Old Topic Prune Test', 1, 1, 0);

		// Run prune for topics older than current time + 1 hour
		removeOldTopics([1], 'all', false, time() + 3600);

		$info = getTopicInfo($fixture['topic_id']);
		$this->assertEmpty($info);
	}

	/**
	 * Test removeMessages() correctly removes messages from a topic.
	 */
	public function testRemoveMessages(): void
	{
		/**
		 * Test removeMessages() correctly removes messages from a topic.
		 */
		$fixture = $this->createTestTopic('Remove Messages Batch Test', 1, 1, 2);
		$msgToDelete = $fixture['messages'][2];

		removeMessages([$msgToDelete], [$msgToDelete => ['board' => 1, 'topic' => $fixture['topic_id'], 'subject' => 'Re: Sub', 'member' => 1]], 'replies');

		$remainingMsgs = messagesInTopics([$fixture['topic_id']]);
		$this->assertNotContains((string) $msgToDelete, $remainingMsgs);
	}

	/**
	 * Test removeTopics() and removeTopicsPermissions() correctly remove topics and their permissions.
	 */
	public function testRemoveTopicsAndPermissions(): void
	{
		$fixture1 = $this->createTestTopic('Remove Topic Direct Test', 1, 1, 1);
		$this->assertNotEmpty(getTopicInfo($fixture1['topic_id']));

		// removeTopics()
		removeTopics($fixture1['topic_id'], true, true, false);
		$this->assertEmpty(getTopicInfo($fixture1['topic_id']));

		$fixture2 = $this->createTestTopic('Remove Topic Permissions Test', 1, 1, 0);
		$this->assertNotEmpty(getTopicInfo($fixture2['topic_id']));

		// removeTopicsPermissions()
		global $board;
		$board = 1;
		removeTopicsPermissions($fixture2['topic_id']);
		$this->assertEmpty(getTopicInfo($fixture2['topic_id']));
	}
}
