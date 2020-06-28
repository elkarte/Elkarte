<?php

/**
 * This is a notification task, by default a container that may act like
 * an array (through ArrayAccess), with some ad-hoc methods.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class NotificationsTask
 */
class NotificationsTask extends ValuesContainer
{
	/**
	 * Data of the members to notify.
	 * Populated only if the getMembersData method is called.
	 *
	 * @var mixed[]
	 */
	protected $_members_data = null;

	/**
	 * Data of the member generating the notification.
	 * Populated only if the getNotifierData method is called.
	 *
	 * @var mixed[]
	 */
	protected $_notifier_data = null;

	/**
	 * The constructor prepared the data array and fills some default values
	 * if needed.
	 *
	 * @param string $type The notification type we are dealing with
	 * @param int $id The id of the target (can be a message, a topic, a member, whatever)
	 * @param int $id_member The id of the member generating the notification
	 * @param mixed[] $data An array of data that can be necessary in the process
	 * @param string $namespace A namespace for the class if different from the
	 *               default \ElkArte\Mentions\MentionType\Notification\
	 */
	public function __construct($type, $id, $id_member, $data, $namespace = '')
	{
		parent::__construct();

		$this->data = array(
			'notification_type' => $type,
			'namespace' => empty($namespace) ? '\\ElkArte\\Mentions\\MentionType\\Notification\\' : rtrim($namespace, '\\') . '\\',
			'id_target' => $id,
			'id_member_from' => $id_member,
			'source_data' => $data,
			'log_time' => time()
		);

		if (!isset($this->data['source_data']['status']))
		{
			$this->data['source_data']['status'] = 'new';
		}

		if (isset($this->data['source_data']['id_members']))
		{
			$this->setMembers($this->data['source_data']['id_members']);
		}
		else
		{
			$this->setMembers(array());
		}
	}

	/**
	 * Sets the members that have to receive the notification.
	 *
	 * @param int|int[] An array of member id
	 */
	public function setMembers($members)
	{
		$this->data['source_data']['id_members'] = (array) $members;
	}

	/**
	 * Returns the data from getBasicMemberData about the members to be notified.
	 *
	 * @return mixed[]
	 */
	public function getMembersData()
	{
		if ($this->_members_data === null)
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$this->_members_data = getBasicMemberData($this->getMembers(), array('preferences' => true));
		}

		return $this->_members_data;
	}

	/**
	 * Returns the array of member that have to receive the notification.
	 *
	 * @return int[] An array of member id
	 */
	public function getMembers()
	{
		return $this->data['source_data']['id_members'];
	}

	/**
	 * Returns the data from getBasicMemberData about the member that
	 * generated the notification
	 *
	 * @return mixed[]
	 */
	public function getNotifierData()
	{
		if ($this->_notifier_data === null)
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$this->_notifier_data = getBasicMemberData($this->id_member_from);
		}

		return $this->_notifier_data;
	}

	/**
	 * Returns the class name of the MentionType.
	 *
	 * @return string
	 */
	public function getClass()
	{
		return $this->data['namespace'] . ucfirst($this->data['notification_type']);
	}
}
