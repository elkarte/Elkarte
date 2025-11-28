<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Database\QueryInterface;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;

/**
 * This class is used as interface to load/get the members from the database.
 * And it keeps track of all the members loaded.
 *
 * It consists mostly of static methods, while the instantiated part is used
 * by MemberLoader to access the list of loaded users.
 */
class MembersList
{
	/** @var Member[] List of all the members already loaded */
	protected static $members = [];

	/** @var MemberLoader Instance of MemberLoader used to actually get the data out of the database and ready in a Member object */
	protected static $loader;

	/** @var MembersList Instance of this very class */
	protected static $instance;

	/**
	 * Protected construct to avoid external access
	 */
	protected function __construct()
	{
	}

	/**
	 * Initialize the loader and the instance of this class
	 *
	 * @param QueryInterface $db The object to query the database
	 * @param Cache $cache Cache object used to... well cache content of each member
	 * @param ParserWrapper $bbc_parser BBC parser to convert BBC to HTML
	 */
	public static function init($db, $cache, $bbc_parser): void
	{
		global $modSettings, $board_info;

		if (self::$instance === null)
		{
			self::$instance = new MembersList();
		}

		self::$loader = new MemberLoader($db, $cache, $bbc_parser, self::$instance, [
			'titlesEnable' => !empty($modSettings['titlesEnable']),
			'custom_fields' => featureEnabled('cp'),
			'load_moderators' => !empty($board_info['moderators']),
			'display_fields' => isset($modSettings['displayFields']) ? Util::unserialize($modSettings['displayFields']) : []
		]);
	}

	/**
	 * Loads the data of a set of users from the database into a member object.
	 *
	 * @param int|int[]|string|string[] $users Name/s or id/s of the members to load
	 * @param bool $is_name If the data passed with $users is a name or an id (true if names)
	 * @param string $set The "amount" of data to be loaded (see constants in \ElkArte\MemberLoader for the values)
	 * @return bool|int[]
	 */
	public static function load($users, $is_name = false, $set = 'normal')
	{
		$result = $is_name
			? self::$loader->loadByName($users, $set)
			: self::$loader->loadById($users, $set);

		return empty($result) ? false : $result;
	}

	/**
	 * Loads a guest member (i.e. some standard data for guests)
	 */
	public static function loadGuest(): void
	{
		if (isset(self::$members[0]))
		{
			return;
		}

		self::$loader->loadGuest();
	}

	/**
	 * Returns the Member object of the requested (numeric) $id
	 *
	 * @param int $id Member id to retrieve and return
	 * @return ValuesContainer
	 */
	public static function get($id)
	{
		$id = (int) $id;
		$member = self::$instance->getById($id);

		return $member !== false ? $member : new class() extends ValuesContainer {
			public function loadContext($display_custom_fields = false): void
			{
			}
		};
	}

	/**
	 * Returns the \ElkArte\Member object of the requested (numeric) $id
	 *
	 * @param int $id id of the member
	 * @return false|Member
	 */
	public function getById($id)
	{
		return self::$members[$id] ?? false;
	}

	/**
	 * Adds an \ElkArte\Member object to the list.
	 *
	 * @param Member $member_data The object representing
	 * @param int $id id of the member
	 */
	public static function add($member_data, $id): void
	{
		self::$members[$id] = $member_data;
	}

	/**
	 * Unloads a \ElkArte\Member object from the list to allow to free some memory.
	 *
	 * @param int $id id of the member
	 */
	public static function unset($id): void
	{
		if (isset(self::$members[$id]))
		{
			unset(self::$members[$id]);
		}
	}

	/**
	 * Adds data to a certain member's object.
	 *
	 * @param array $data The data to append
	 * @param string $type The type of data to append (by default only 'options' for custom fields
	 * @param int $id The id of the member
	 * @param array $display_fields - Basically the content of $modSettings['displayFields']
	 */
	public function appendTo($data, $type, $id, $display_fields = []): void
	{
		self::$members[$id]->append($type, $data, $display_fields);
	}

	/**
	 * Loads member data and their context into a structured array.
	 *
	 * This method loads the specified members, fetches their context data,
	 * and organizes the information into an associative array indexed by member IDs.
	 * It places that data in a global $memberContext array to mimic previous behavior.
	 *
	 * Helper function for loadMemberData() + loadMemberContext(), intended to help with addon transition to 2.0
	 *
	 * @param int|int[]|string|string[] $members Name/s or id/s of the members to load
	 * @param bool $is_name Indicates whether the input in $members represents names (true) or IDs (false)
	 * @param string $set The amount of data to load, typically defined by a pre-configured set type
	 * @return false|int[]
	 */
	public static function loadMemberData($members, $is_name = false, $set = 'normal')
	{
		if ($set === 'minimal')
		{
			return self::load($members, $is_name, $set);
		}

		$members = (array) $members;
		$members = array_map('intval', $members);
		$loaded_ids = [];

		if (self::load($members, $is_name, $set))
		{
			foreach ($members as $id)
			{
				$member = self::get($id);

				// Load their context data.
				if ($member->isEmpty())
				{
					continue;
				}

				/* @var Member $member */
				$member->loadContext();

				// Store this member's information.
				$GLOBALS['memberContext'][$id] = $member;
				$loaded_ids[] = $id;
			}
		}

		return empty($loaded_ids) ? false : $loaded_ids;
	}
}
