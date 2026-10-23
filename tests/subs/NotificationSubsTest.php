<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

use ElkArte\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * TestCase class for notification subs.
 */
class NotificationSubsTest extends TestCase
{
	/** @var mixed */
	private $previousDb;

	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Notification.subs.php');

		$this->previousDb = $GLOBALS['db_instance'] ?? null;
	}

	protected function tearDown(): void
	{
		$GLOBALS['db_instance'] = $this->previousDb;
	}

	public function testFetchTopicNotificationsReturnsEverySubscriberForTheSameTopic()
	{
		$topic = 42;
		$rows = [
			$this->notificationRow(2, $topic, 100),
			$this->notificationRow(3, $topic, 101),
		];

		$this->mockNotificationQuery($rows);

		$notifications = fetchTopicNotifications(1, [$topic], 'reply', []);

		$this->assertCount(2, $notifications);
		$this->assertSame([2, 3], array_column($notifications, 'id_member'));
		$this->assertSame([100, 101], array_column($notifications, 'last_id'));
	}

	private function mockNotificationQuery(array $rows): void
	{
		$result = new class($rows) {
			private $rows;

			public function __construct(array $rows)
			{
				$this->rows = $rows;
			}

			public function fetch_callback($callback, $seeds = null)
			{
				$results = $seeds !== null ? (array) $seeds : [];

				foreach ($this->rows as $row)
				{
					$results[] = $callback($row);
				}

				return $results;
			}
		};

		$db = $this->createMock(QueryInterface::class);
		$db->expects($this->once())
			->method('fetchQuery')
			->willReturn($result);

		$GLOBALS['db_instance'] = $db;
	}

	private function notificationRow(int $idMember, int $idTopic, int $lastId): array
	{
		return [
			'id_member' => $idMember,
			'email_address' => 'member' . $idMember . '@example.com',
			'notify_regularity' => 0,
			'notify_types' => 1,
			'notify_send_body' => 0,
			'notify_from' => 0,
			'lngfile' => 'english',
			'warning' => 0,
			'id_group' => 1,
			'additional_groups' => '',
			'id_post_group' => 1,
			'password_salt' => '',
			'id_member_started' => 1,
			'member_groups' => '1',
			'name' => 'Board',
			'id_profile' => 1,
			'id_board' => 1,
			'id_topic' => $idTopic,
			'id_last_msg' => $lastId,
			'sent' => 0,
		];
	}
}
