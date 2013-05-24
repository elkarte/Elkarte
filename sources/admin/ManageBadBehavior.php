<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class ManageBadBehavior_Controller
{
	/**
	 * View the forum's badbehavior log.
	 * This function sets all the context up to show the badbehavior log for review.
	 * It requires the maintain_forum permission.
	 * It is accessed from ?action=admin;area=logs;sa=errorlog.
	 *
	 * @uses the BadBehavior template and badbehavior_log sub template.
	 */
	public function action_badbehaviorlog()
	{
		global $scripturl, $txt, $context, $modSettings, $user_profile, $filter;

		$db = database();

		// Check for the administrative permission to do this.
		isAllowedTo('admin_forum');

		// Templates, etc...
		loadLanguage('BadBehaviorlog');
		loadTemplate('BadBehavior');

		// Functions we will need
		require_once(SUBSDIR . '/BadBehavior.subs.php');

		// You can filter by any of the following columns:
		$filters = array(
			'id_member' => $txt['badbehaviorlog_username'],
			'ip' => $txt['badbehaviorlog_ip'],
			'session' => $txt['badbehaviorlog_session'],
			'valid' => $txt['badbehaviorlog_key'],
			'request_uri' => $txt['badbehaviorlog_request'],
			'user_agent' => $txt['badbehaviorlog_agent'],
		);

		// Set up the filtering...
		$filter = array();
		if (isset($_GET['value'], $_GET['filter']) && isset($filters[$_GET['filter']]))
		{
			$filter = array(
				'variable' => $_GET['filter'] == 'useragent' ? 'user_agent' : $_GET['filter'],
				'value' => array(
					'sql' => in_array($_GET['filter'], array('request_uri', 'user_agent')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : $db->db_escape_wildcard_string($_GET['value']),
				),
				'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
				'entity' => $filters[$_GET['filter']]
			);
		}
		elseif (isset($_GET['filter']) || isset($_GET['value']))
		{
			// Bad filter or something else going on, back to the start you go
			unset($_GET['filter'], $_GET['value']);
			redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
		}

		// Deleting or just doing a little weeding?
		if (isset($_POST['delall']) || isset($_POST['delete']))
		{
			$type = isset($_POST['delall']) ? 'delall' : 'delete';
			// Make sure the session exists and the token is correct
			checkSession();
			validateToken('admin-bbl');

			$redirect = deleteBadBehavior($type, $filter);
			if ($redirect === 'delete')
			{
				$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
				// Go back to where we were.
				redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : '') . ';start=' . $start . (!empty($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
			}
			redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
		}

		// Just how many entries are there?
		$num_errors = getBadBehaviorLogEntryCount($filter);

		// If this filter turns up empty, just return
		if (empty($num_errors) && !empty($filter))
			redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));

		// Clean up start.
		$start = (!isset($_GET['start']) || $_GET['start'] < 0) ? 0 : (int) $_GET['start'];

		// Do we want to reverse the listing?
		$sort = isset($_REQUEST['desc']) ? 'down' : 'up';

		// Set the page listing up.
		$context['page_index'] = constructPageIndex($scripturl . '?action=admin;area=logs;sa=badbehaviorlog' . ($sort == 'down' ? ';desc' : '') . (!empty($filter) ? $filter['href'] : ''), $start, $num_errors, $modSettings['defaultMaxMessages']);

		// Find and sort out the log entries.
		$context['bb_entries'] = getBadBehaviorLogEntries($start, $modSettings['defaultMaxMessages'], $sort, $filter);

		$members = array();
		foreach($context['bb_entries'] as $member)
			$members[] = $member['member']['id'];

		// Load the member data so we have more information available
		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members = getBasicMemberData($members, array('add_guest' => true));

			// Go through each entry and add the member data.
			foreach ($context['bb_entries'] as $id => $dummy)
			{
				$memID = $context['bb_entries'][$id]['member']['id'];
				$context['bb_entries'][$id]['member']['username'] = $members[$memID]['member_name'];
				$context['bb_entries'][$id]['member']['name'] = $members[$memID]['real_name'];
				$context['bb_entries'][$id]['member']['href'] = empty($memID) ? '' : $scripturl . '?action=profile;u=' . $memID;
				$context['bb_entries'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $memID . '">' . $context['bb_entries'][$id]['member']['name'] . '</a>';
			}
		}

		// Filtering?
		if (!empty($filter))
		{
			$context['filter'] = $filter;

			// Set the filtering context.
			if ($filter['variable'] === 'id_member')
			{
				$id = $filter['value']['sql'];
				loadMemberData($id, false, 'minimal');
				$context['filter']['value']['html'] = '<a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
			}
			elseif ($filter['variable'] === 'url')
			{
				$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars((substr($filter['value']['sql'], 0, 1) === '?' ? $scripturl : '') . $filter['value']['sql']), array('\_' => '_')) . '\'';
			}
			elseif ($filter['variable'] === 'headers')
			{
				$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars($filter['value']['sql']), array("\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
				$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
			}
			else
				$context['filter']['value']['html'] = $filter['value']['sql'];
		}

		// And the standard template goodies
		$context['page_title'] = $txt['badbehaviorlog_log'];
		$context['has_filter'] = !empty($filter);
		$context['sub_template'] = 'badbehavior_log';
		$context['sort_direction'] = $sort;
		$context['start'] = $start;

		createToken('admin-bbl');
	}
}