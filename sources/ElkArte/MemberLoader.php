<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * This class loads all the data related to a certain member or
 * set of members taken from the db
 */
class MemberLoader
{
	/**
	 * Just the bare minimum set of fields
	 */
	const SET_MINIMAL = 'minimal';

	/**
	 * What is needed in most of the cases
	 */
	const SET_NORMAL = 'normal';

	/**
	 * What is required to see a profile page
	 */
	const SET_PROFILE = 'profile';

	/**
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $db = null;

	/**
	 * @var \ElkArte\Cache\Cache
	 */
	protected $cache = null;

	/**
	 * @var \BBC\ParserWrapper
	 */
	protected $bbc_parser = null;

	/**
	 * @var \ElkArte\MembersList
	 */
	protected $users_list = null;

	/**
	 * @var mixed[]
	 */
	protected $options = [
		'titlesEnable' => false,
		'custom_fields' => false,
		'load_moderators' => false,
		'display_fields' => []
	];

	/**
	 * @var bool
	 */
	protected $useCache = false;

	/**
	 * @var string
	 */
	protected $set = '';

	/**
	 * @var string
	 */
	protected $base_select_columns = '';

	/**
	 * @var string
	 */
	protected $base_select_tables = '';

	/**
	 * @var int[]
	 */
	protected $loaded_ids = [];

	/**
	 * @var \ElkArte\Member[]
	 */
	protected $loaded_members = [];

	/**
	 * Initialize the class
	 *
	 * @param \ElkArte\Database\QueryInterface $db The object to query the database
	 * @param \ElkArte\Cache\Cache $cache Cache object used to... well cache content of each member
	 * @param \BBC\ParserWrapper $bbc_parser BBC parser to convert BBC to HTML
	 * @param \ElkArte\MembersList $list the instance of the list of members
	 * @param mixed[] $options Random options useful to the loader to decide what to actually load
	 */
	public function __construct($db, $cache, $bbc_parser, $list, $options = [])
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->bbc_parser = $bbc_parser;
		$this->users_list = $list;
		$this->options = array_merge($this->options, $options);

		$this->useCache = $this->cache->isEnabled() && $this->cache->levelHigherThan(2);

		$this->base_select_columns = '
			COALESCE(lo.log_time, 0) AS is_online, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login, mem.likes_given, mem.likes_received,
			mem.karma_good, mem.id_post_group, mem.karma_bad, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mg.online_color AS member_group_color, COALESCE(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, COALESCE(pg.group_name, {string:blank_string}) AS post_group,
			mem.is_activated, mem.warning, ' . (!empty($this->options['titlesEnable']) ? 'mem.usertitle, ' : '') . '
			CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons';
		$this->base_select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}

	/**
	 * Loads users data from a member id
	 *
	 * @param int|int[] $users Single id or list of ids to load
	 * @param string $set The data to load (see the constants SET_*)
	 * @return bool|int[] The ids of the members loaded
	 */
	public function loadById($users, $set = MemberLoader::SET_NORMAL)
	{
		// Can't just look for no users :P.
		if (empty($users))
		{
			return false;
		}

		$this->set = $set;
		$users = array_unique((array) $users);
		$this->loaded_ids = [];
		$this->loaded_members = [];

		$to_load = $this->loadFromCache($users);

		$this->loadByCondition('mem.id_member' . (count($to_load) === 1 ? ' = {int:users}' : ' IN ({array_int:users})'), $to_load);

		$this->loadModerators();

		return $this->loaded_ids;
	}

	/**
	 * Retrieve \ElkArte\Member objects from the cache
	 *
	 * @param int|int[] $users Single id or list of ids to load
	 * @return int[] The ids not found in the cache
	 */
	protected function loadFromCache($users)
	{
		if (!$this->useCache)
		{
			$to_load = $users;
		}
		else
		{
			$to_load = [];
			foreach ($users as $user)
			{
				$data = $this->cache->get('member_data-' . $this->set . '-' . $user, 240);
				if ($this->cache->isMiss())
				{
					$to_load[] = $user;
					continue;
				}

				$member = new Member($data['data'], $data['set'], $this->bbc_parser);
				foreach ($data['additional_data'] as $key => $values)
				{
					$member->append($key, $values, $this->options['display_fields']);
				}
				$this->users_list->add($member, $data['data']['id_member']);
				$this->loaded_ids[] = $data['data']['id_member'];
				$this->loaded_members[$data['data']['id_member']] = $member;
			}
		}

		return $to_load;
	}

	/**
	 * Loads users data provided a where clause and an array of ids
	 *
	 * @param string $where_clause The WHERE clause of the query to run
	 * @param int[] $to_load Array of ids to load
	 * @return bool If loaded anything or not
	 */
	protected function loadByCondition($where_clause, $to_load)
	{
		if (empty($to_load))
		{
			return false;
		}

		$select_columns = $this->base_select_columns;
		$select_tables = $this->base_select_tables;

		// We add or replace according to the set
		switch ($this->set)
		{
			case MemberLoader::SET_NORMAL:
				$select_columns .= ', mem.buddy_list';
				break;
			case MemberLoader::SET_PROFILE:
				$select_columns .= ', mem.openid_uri, mem.id_theme, mem.pm_ignore_list, mem.pm_email_notify, mem.receive_from,
				mem.time_format, mem.secret_question, mem.additional_groups, mem.smiley_set,
				mem.total_time_logged_in, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
				mem.notify_types, lo.url, mem.ignore_boards, mem.password_salt, mem.pm_prefs, mem.buddy_list, mem.otp_secret, mem.enable_otp';
				break;
			case MemberLoader::SET_MINIMAL:
				$select_columns = '
				mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
				mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
				$select_tables = '';
				break;
			default:
				trigger_error('\ElkArte\MembersList::load(): Invalid member data set \'' . $this->set . '\'', E_USER_WARNING);
		}

		// Allow addons to easily add to the selected member data
		call_integration_hook('integrate_load_member_data', array(&$select_columns, &$select_tables, $this->set));

		// Load the member's data.
		$request = $this->db->query('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE ' . $where_clause,
			array(
				'blank_string' => '',
				'users' => count($to_load) === 1 ? current($to_load) : $to_load,
			)
		);

		$new_loaded_ids = array();
		while ($row = $request->fetch_assoc())
		{
			$new_loaded_ids[] = $row['id_member'];
			$this->loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$this->loaded_members[$row['id_member']] = new Member($row, $this->set, $this->bbc_parser);
			$this->users_list->add($this->loaded_members[$row['id_member']], $row['id_member']);
		}
		$request->free_result();
		$this->loadCustomFields($new_loaded_ids);

		if (!empty($new_loaded_ids))
		{
			// Anything else integration may want to add to the user_profile array
			call_integration_hook('integrate_add_member_data', array($new_loaded_ids, $this->users_list));

			$this->storeInCache($new_loaded_ids);
		}

		return !empty($new_loaded_ids);
	}

	/**
	 * Loads custom fields data for a set of users
	 *
	 * @param int[] $new_loaded_ids Array of ids to load
	 */
	protected function loadCustomFields($new_loaded_ids)
	{
		if (empty($new_loaded_ids) || $this->options['custom_fields'] === false)
		{
			return;
		}

		$request = $this->db->query('', '
			SELECT 
				cfd.id_member, cfd.variable, cfd.value, cf.field_options, cf.field_type
			FROM {db_prefix}custom_fields_data AS cfd
			JOIN {db_prefix}custom_fields AS cf ON (cf.col_name = cfd.variable)
			WHERE id_member' . (count($new_loaded_ids) === 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) === 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		$data = [];
		while ($row = $request->fetch_assoc())
		{
			if (!empty($row['field_options']))
			{
				$field_options = explode(',', $row['field_options']);
				$key = (int) array_search($row['value'], $field_options);
			}
			else
			{
				$key = 0;
			}

			$data[$row['id_member']][$row['variable'] . '_key'] = $row['variable'] . '_' . $key;
			$data[$row['id_member']][$row['variable']] = $row['value'];
		}

		foreach ($data as $id => $val)
		{
			$this->users_list->appendTo($val, 'options', $id, $this->options['display_fields']);
		}
		$request->free_result();
	}

	/**
	 * Stored \ElkArte\Member objects in the cache
	 *
	 * @param int[] $new_loaded_ids Ids of members that have been loaded
	 */
	protected function storeInCache($new_loaded_ids)
	{
		if ($this->useCache)
		{
			foreach ($new_loaded_ids as $id)
			{
				$this->cache->put('member_data-' . $this->set . '-' . $id, $this->users_list->getById($id)->toArray(), 240);
			}
		}
	}

	/**
	 * Loads moderators data into the \ElkArte\Member objects
	 */
	protected function loadModerators()
	{
		global $board_info;

		if (empty($this->loaded_ids) || $this->options['load_moderators'] === false || $this->set === MemberLoader::SET_NORMAL)
		{
			return;
		}

		$temp_mods = array_intersect($this->loaded_ids, array_keys($board_info['moderators']));
		if (count($temp_mods) !== 0)
		{
			$group_info = array();
			if ($this->cache->getVar($group_info, 'moderator_group_info', 480) === false)
			{
				require_once(SUBSDIR . '/Membergroups.subs.php');
				$group_info = membergroupById(3, true);

				$this->cache->put('moderator_group_info', $group_info, 480);
			}

			foreach ($temp_mods as $id)
			{
				// By popular demand, don't show admins or global moderators as moderators.
				if ($this->loaded_members[$id]['id_group'] != 1 && $this->loaded_members[$id]['id_group'] != 2)
				{
					$this->loaded_members[$id]['member_group'] = $group_info['group_name'];
				}

				// If the Moderator group has no color or icons, but their group does... don't overwrite.
				if (!empty($group_info['icons']))
				{
					$this->loaded_members[$id]['icons'] = $group_info['icons'];
				}
				if (!empty($group_info['online_color']))
				{
					$this->loaded_members[$id]['member_group_color'] = $group_info['online_color'];
				}
			}
		}
	}

	/**
	 * Loads users data from a member name
	 *
	 * @param string|string[] $name Single name or list of names to load
	 * @param string $set The data to load (see the constants SET_*)
	 * @return bool|int[] The ids of the members loaded
	 */
	public function loadByName($name, $set = MemberLoader::SET_NORMAL)
	{
		// Can't just look for no users :P.
		if (empty($name))
		{
			return false;
		}

		$this->set = $set;
		$users = array_unique((array) $name);
		$this->loaded_ids = [];

		$this->loadByCondition('{column_case_insensitive:mem.member_name}' . (count($users) === 1 ? ' = {string_case_insensitive:users}' : ' IN ({array_string_case_insensitive:users})'), $users);

		$this->loadModerators();

		return $this->loaded_ids;
	}

	/**
	 * Loads a guest member (i.e. some standard data for guests)
	 */
	public function loadGuest()
	{
		global $txt;

		if (isset($this->loaded_members[0]))
		{
			return;
		}

		$this->loaded_members[0] = new Member([
			'id' => 0,
			'name' => $txt['guest_title'],
			'group' => $txt['guest_title'],
			'href' => '',
			'link' => $txt['guest_title'],
			'email' => $txt['guest_title'],
			'is_guest' => true
		], $this->set, $this->bbc_parser);
		$this->users_list->add($this->loaded_members[0], 0);
	}
}
