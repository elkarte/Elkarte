<?php

/**
 * This file is mainly concerned with the Who's Online list.
 * Although, it also handles credits. :P
 *
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

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\MembersList;
use ElkArte\Languages\Txt;
use ElkArte\Util;

/**
 * I woke up in a Soho doorway A policeman knew my name He said "You can go sleep at home
 * tonight If you can get up and walk away"
 */
class Who extends AbstractController
{
	/**
	 * Default action of this class
	 *
	 * Accessed with ?action=who
	 */
	public function action_index()
	{
		// We know how to... peek at who's online
		$this->action_who();
	}

	/**
	 * Who's online, and what are they doing?
	 *
	 * What it does:
	 *
	 * - This function prepares the who's online data for the Who template.
	 * - It requires the who_view permission.
	 * - It is enabled with the who_enabled setting.
	 * - It is accessed via ?action=who.
	 *
	 * @uses  template_whos_online() sub-template in Who.template
	 * @uses  Who language file.
	 */
	public function action_who()
	{
		global $context, $txt, $modSettings;

		// Permissions, permissions, permissions.
		isAllowedTo('who_view');

		// You can't do anything if this is off.
		if (empty($modSettings['who_enabled']))
		{
			throw new Exception('who_off', false);
		}

		// Load the 'Who' template.
		theme()->getTemplates()->load('Who');
		Txt::load('Who');

		// Sort out... the column sorting.
		$sort_methods = array(
			'user' => 'mem.real_name',
			'time' => 'lo.log_time'
		);

		$show_methods = array(
			'members' => '(lo.id_member != 0)',
			'guests' => '(lo.id_member = 0)',
			'all' => '1=1',
		);

		// Store the sort methods and the show types for use in the template.
		$context['sort_methods'] = array(
			'user' => $txt['who_user'],
			'time' => $txt['who_time'],
		);

		$context['show_methods'] = array(
			'all' => $txt['who_show_all'],
			'members' => $txt['who_show_members_only'],
			'guests' => $txt['who_show_guests_only'],
		);

		// Can they see spiders too?
		if (!empty($modSettings['show_spider_online']) && ($modSettings['show_spider_online'] == 2 || allowedTo('admin_forum')) && !empty($modSettings['spider_name_cache']))
		{
			$show_methods['spiders'] = '(lo.id_member = 0 AND lo.id_spider > 0)';
			$show_methods['guests'] = '(lo.id_member = 0 AND lo.id_spider = 0)';
			$context['show_methods']['spiders'] = $txt['who_show_spiders_only'];
		}
		elseif (empty($modSettings['show_spider_online']) && isset($_SESSION['who_online_filter']) && $_SESSION['who_online_filter'] === 'spiders')
		{
			unset($_SESSION['who_online_filter']);
		}

		// Does the user prefer a different sort direction?
		if (isset($this->_req->query->sort, $sort_methods[$this->_req->query->sort]))
		{
			$context['sort_by'] = $_SESSION['who_online_sort_by'] = $this->_req->query->sort;
			$sort_method = $sort_methods[$this->_req->query->sort];
		}
		// Did we set a preferred sort order earlier in the session?
		elseif (isset($_SESSION['who_online_sort_by']))
		{
			$context['sort_by'] = $_SESSION['who_online_sort_by'];
			$sort_method = $sort_methods[$_SESSION['who_online_sort_by']];
		}
		// Default to last time online.
		else
		{
			$context['sort_by'] = $_SESSION['who_online_sort_by'] = 'time';
			$sort_method = 'lo.log_time';
		}

		$context['sort_direction'] = isset($this->_req->query->asc) || ($this->_req->getQuery('sort_dir', 'trim', '') === 'asc') ? 'up' : 'down';

		$conditions = array();
		if (!allowedTo('moderate_forum'))
		{
			$conditions[] = '(COALESCE(mem.show_online, 1) = 1)';
		}

		// Fallback to top filter?
		if (isset($this->_req->post->submit_top, $this->_req->post->show_top))
		{
			$this->_req->post->show = $this->_req->post->show_top;
		}

		// Does the user wish to apply a filter?
		if (isset($this->_req->post->show, $show_methods[$this->_req->post->show]))
		{
			$context['show_by'] = $_SESSION['who_online_filter'] = $this->_req->post->show;
			$conditions[] = $show_methods[$this->_req->post->show];
		}
		// Perhaps we saved a filter earlier in the session?
		elseif (isset($_SESSION['who_online_filter']))
		{
			$context['show_by'] = $_SESSION['who_online_filter'];
			$conditions[] = $show_methods[$_SESSION['who_online_filter']];
		}
		else
		{
			$context['show_by'] = $_SESSION['who_online_filter'] = 'all';
		}

		require_once(SUBSDIR . '/Members.subs.php');
		$totalMembers = countMembersOnline($conditions);

		// Prepare some page index variables.
		$start = $this->_req->get('start', 'intval');
		$context['page_index'] = constructPageIndex('{scripturl}?action=who;sort=' . $context['sort_by'] . ($context['sort_direction'] === 'up' ? ';asc' : '') . ';show=' . $context['show_by'], $start, $totalMembers, $modSettings['defaultMaxMembers']);
		$context['start'] = $start;
		$context['sub_template'] = 'whos_online';
		theme()->getLayers()->add('whos_selection');

		// Look for people online, provided they don't mind if you see they are.
		$members = onlineMembers($conditions, $sort_method, $context['sort_direction'], $context['start']);

		$context['members'] = array();
		$member_ids = array();
		$url_data = array();

		foreach ($members as $row)
		{
			$actions = Util::unserialize($row['url']);
			if ($actions === false)
			{
				continue;
			}

			// Send the information to the template.
			$context['members'][$row['session']] = array(
				'id' => $row['id_member'],
				'ip' => allowedTo('moderate_forum') ? $row['ip'] : '',
				// It is *going* to be today or yesterday, so why keep that information in there?
				'time' => standardTime($row['log_time'], true),
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
				'query' => $actions,
				'is_hidden' => $row['show_online'] == 0,
				'id_spider' => $row['id_spider'],
				'color' => empty($row['online_color']) ? '' : $row['online_color']
			);

			$url_data[$row['session']] = array($row['url'], $row['id_member']);
			$member_ids[] = $row['id_member'];
		}

		// Load the user data for these members (and the guests).
		MembersList::load($member_ids);
		MembersList::loadGuest();

		// Are we showing spiders?
		$spiderContext = array();
		if (!empty($modSettings['show_spider_online']) && ($modSettings['show_spider_online'] == 2 || allowedTo('admin_forum')) && !empty($modSettings['spider_name_cache']))
		{
			foreach (unserialize($modSettings['spider_name_cache']) as $id => $name)
			{
				$spiderContext[$id] = array(
					'id' => 0,
					'name' => $name,
					'group' => $txt['spiders'],
					'href' => '',
					'link' => $name,
					'email' => $name,
					'is_guest' => true
				);
			}
		}

		require_once(SUBSDIR . '/Who.subs.php');
		$url_data = determineActions($url_data);

		// Setup the linktree and page title (do it down here because the language files are now loaded..)
		$context['page_title'] = $txt['who_title'];
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'who']),
			'name' => $txt['who_title']
		);

		foreach ($context['members'] as $i => $member)
		{
			$member_context = MembersList::get($member['id']);

			// The following happens when $member['id'] is not found among the loaded for any reason
			// in such cases we load a guest dummy
			if ($member_context->isEmpty())
			{
				$member_context = MembersList::get(0);
			}
			else
			{
				$member_context->loadContext();
			}

			// Keep the IP that came from the database.
			$member_context['ip'] = $member['ip'];
			$context['members'][$i]['action'] = $url_data[$i] ?? $txt['who_hidden'];

			if ($member['id'] == 0 && isset($spiderContext[$member['id_spider']]))
			{
				$context['members'][$i] += $spiderContext[$member['id_spider']];
			}
			else
			{
				$context['members'][$i] += $member_context->toArray()['data'];
			}

			if ($member_context['is_guest'])
			{
				$context['members'][$i]['track_href'] = getUrl('action', ['action' => 'trackip', 'searchip' => $member_context['ip']]);
			}
			else
			{
				$context['members'][$i]['track_href'] = getUrl('profile', ['action' => 'profile', 'area' => 'history', 'sa' => 'ip', 'u' => $member['id'], 'name' => $member_context['name'], 'searchip' => $member_context['ip']]);
			}
		}

		// Some people can't send personal messages...
		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_send_email'] = allowedTo('send_email_to_members');

		// Any profile fields disabled?
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();
	}

	/**
	 * It prepares credit and copyright information for the credits page or the admin page.
	 *
	 * - Accessed by ?action=who;sa=credits
	 *
	 * @uses Who language file
	 * @uses template_credits() sub template in Who.template,
	 */
	public function action_credits()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Who.subs.php');
		Txt::load('Who');

		$context += prepareCreditsData();

		theme()->getTemplates()->load('Who');
		$context['sub_template'] = 'credits';
		$context['robot_no_index'] = true;
		$context['page_title'] = $txt['credits'];
	}
}
