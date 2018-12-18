<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class Member
 *
 * This class groups all the members loaded from the db
 */
class MembersList
{
	protected static $members = [];
	protected static $dataLoaded = [];
	protected static $loader = null;
	protected static $instance = null;

	public static function init($db, $cache, $bbc_parser)
	{
		global $modSettings, $context, $board_info;

		if (self::$instance === null)
		{
			self::$instance = new MembersList();
		}

		self::$loader = new \ElkArte\MemberLoader($db, $cache, $bbc_parser, self::$instance, [
			'titlesEnable' => !empty($modSettings['titlesEnable']),
			'custom_fields' => featureEnabled('cp'),
			'load_moderators' => !empty($board_info['moderators']),
			'display_fields' => isset($modSettings['displayFields']) ? \ElkArte\Util::unserialize($modSettings['displayFields']) : []
		]);
	}

	public static function load($users, $is_name = false, $set = 'normal')
	{
		if ($is_name === true)
		{
			$result = self::$loader->loadByName($users, $set);
		}
		else
		{
			$result = self::$loader->loadById($users, $set);
		}

		return !empty($result) ? $result : false;
	}

	public static function loadGuest()
	{
		global $txt;

		self::$members[0] = array(
			'id' => 0,
			'name' => $txt['guest_title'],
			'group' => $txt['guest_title'],
			'href' => '',
			'link' => $txt['guest_title'],
			'email' => $txt['guest_title'],
			'is_guest' => true
		);
	}

	public static function get($id)
	{
		$member = self::$instance->getById($id);

		return $member !== false ? $member : new class() extends \ElkArte\ValuesContainer {
			public function loadContext($display_custom_fields = false)
			{}
		};
	}

	public static function add($member_data, $id)
	{
		self::$members[$id] = $member_data;
	}

	public static function unset($id)
	{
		if (isset(self::$members[$id]))
		{
			unset(self::$members[$id]);
		}
	}

	public function getById($id)
	{
		if (isset(self::$members[$id]))
		{
			return self::$members[$id];
		}
		else
		{
			return false;
		}
	}

	public function appendTo($data, $type, $id, $display_fields = [])
	{
		self::$members[$id]->append($type, $data, $display_fields);
	}

	protected function __construct()
	{
	}
}
