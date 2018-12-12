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
	protected static $loader = null;
	protected static $instance = null;

	public static function init($db, $cache)
	{
		self::$loader = new \ElkArte\MemberLoader($db, $cache, $list, []);
	}

	public static function load($users, $is_name = false, $set = 'normal')
	{
		if ($is_name === true)
		{
			$members = self::$loader->loadByName($users, $set);
		}
		else
		{
			$members = self::$loader->loadById($users, $set);
		}
		foreach ($members as $member)
		{
			self::$members[$member->getId()] = $member;
		}
	}

	public function add($member_data, $id)
	{
		self::$members[$id] = $member_data;
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

	public function appendTo($data, $type, $id)
	{
		self::$members[$id]->append($type, $data);
	}

	protected function __construct()
	{
		if (self::$instance === null)
		{
			self::$instance = new MembersList();
		}
	}
}
