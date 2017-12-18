<?php

/**
 * Moderation Center, provides at a glance view of moderation items to the team
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0-dev
 *
 */

/**
 * ModerationCenter_Controller Class
 * Provides overview of moderation items to the team
 */
class ModerationCenter_Controller extends Action_Controller
{
	/**
	 * Holds function array to pass to callMenu to call the right moderation area
	 * @var array
	 */
	private $_mod_include_data;

	/**
	 * Entry point for the moderation center.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Set up moderation menu.
		$this->prepareModcenter();

		// Now call the menu action.
		if (isset($this->_mod_include_data['file']))
			require_once($this->_mod_include_data['file']);

		callMenu($this->_mod_include_data);
	}

	/**
	 * Prepare menu, make checks, load files, and create moderation menu.
	 *
	 * This can be called from the class, or from outside, to
	 * set up moderation menu.
	 */
	public function prepareModcenter()
	{
		global $txt, $context, $scripturl, $modSettings, $user_info, $options;

		// Don't run this twice... and don't conflict with the admin bar.
		if (isset($context['admin_area']))
			return;

		$context['can_moderate_boards'] = $user_info['mod_cache']['bq'] != '0=1';
		$context['can_moderate_groups'] = $user_info['mod_cache']['gq'] != '0=1';
		$context['can_moderate_approvals'] = $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']);

		// Everyone using this area must be allowed here!
		if (!$context['can_moderate_boards'] && !$context['can_moderate_groups'] && !$context['can_moderate_approvals'])
			isAllowedTo('access_mod_center');

		// We're gonna want a menu of some kind.
		require_once(SUBSDIR . '/Menu.subs.php');

		// Load the language, and the template.
		theme()->getTemplates()->loadLanguageFile('ModerationCenter');
		loadCSSFile('admin.css');

		if (!empty($options['admin_preferences']))
		{
			$context['admin_preferences'] = serializeToJson($options['admin_preferences'], function ($array_form) {
				global $context;

				$context['admin_preferences'] = $array_form;
				require_once(SUBSDIR . '/Admin.subs.php');
				updateAdminPreferences();
			});
		}
		else
		{
			$context['admin_preferences'] = array();
		}

		$context['robot_no_index'] = true;

		// Moderation counts for things that this moderator can take care of
		require_once(SUBSDIR . '/Moderation.subs.php');
		$mod_counts = loadModeratorMenuCounts();

		// This is the menu structure - refer to subs/Menu.subs.php for the details.
		$moderation_areas = array(
			'main' => array(
				'title' => $txt['mc_main'],
				'areas' => array(
					'index' => array(
						'label' => $txt['moderation_center'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_moderationHome',
						'icon' => 'transparent.png',
						'class' => 'admin_img_home',
					),
					'settings' => array(
						'label' => $txt['mc_settings'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_moderationSettings',
						'icon' => 'transparent.png',
						'class' => 'admin_img_features',
					),
					'modlogoff' => array(
						'label' => $txt['mc_logoff'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_modEndSession',
						'enabled' => empty($modSettings['securityDisable_moderate']),
						'icon' => 'transparent.png',
						'class' => 'admin_img_exit',
					),
					'notice' => array(
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_showNotice',
						'select' => 'index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_news',
					),
				),
			),
			'logs' => array(
				'title' => $txt['mc_logs'],
				'areas' => array(
					'modlog' => array(
						'label' => $txt['modlog_view'],
						'enabled' => !empty($modSettings['modlog_enabled']) && $context['can_moderate_boards'],
						'controller' => 'Modlog_Controller',
						'function' => 'action_log',
						'icon' => 'transparent.png',
						'class' => 'admin_img_logs',
					),
					'warnings' => array(
						'label' => $txt['mc_warnings'],
						'enabled' => in_array('w', $context['admin_features']) && !empty($modSettings['warning_enable']) && $context['can_moderate_boards'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_viewWarnings',
						'icon' => 'transparent.png',
						'class' => 'admin_img_reports',
						'subsections' => array(
							'log' => array($txt['mc_warning_log']),
							'templates' => array($txt['mc_warning_templates'], 'issue_warning'),
						),
					),
				),
			),
			'posts' => array(
				'title' => $txt['mc_posts'] . (!empty($mod_counts['pt_total']) ? ' [' . $mod_counts['pt_total'] . ']' : ''),
				'enabled' => $context['can_moderate_boards'] || $context['can_moderate_approvals'],
				'areas' => array(
					'postmod' => array(
						'label' => $txt['mc_unapproved_posts'] . (!empty($mod_counts['postmod']) ? ' [' . $mod_counts['postmod'] . ']' : ''),
						'enabled' => $context['can_moderate_approvals'],
						'controller' => 'PostModeration_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_posts',
						'custom_url' => $scripturl . '?action=moderate;area=postmod',
						'subsections' => array(
							'posts' => array($txt['mc_unapproved_replies']),
							'topics' => array($txt['mc_unapproved_topics']),
						),
					),
					'emailmod' => array(
						'label' => $txt['mc_emailerror'] . (!empty($mod_counts['emailmod']) ? ' [' . $mod_counts['emailmod'] . ']' : ''),
						'enabled' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
						'function' => 'UnapprovedEmails',
						'icon' => 'transparent.png',
						'class' => 'admin_img_mail',
						'custom_url' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
					),
					'attachmod' => array(
						'label' => $txt['mc_unapproved_attachments'] . (!empty($mod_counts['attachments']) ? ' [' . $mod_counts['attachments'] . ']' : ''),
						'enabled' => $context['can_moderate_approvals'],
						'controller' => 'PostModeration_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_attachment',
						'custom_url' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
					),
					'reports' => array(
						'label' => $txt['mc_reported_posts'] . (!empty($mod_counts['reports']) ? ' [' . $mod_counts['reports'] . ']' : ''),
						'enabled' => $context['can_moderate_boards'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_reportedPosts',
						'icon' => 'transparent.png',
						'class' => 'admin_img_reports',
						'subsections' => array(
							'open' => array($txt['mc_reportedp_active'] . (!empty($mod_counts['reports']) ? ' [' . $mod_counts['reports'] . ']' : '')),
							'closed' => array($txt['mc_reportedp_closed']),
						),
					),
					'pm_reports' => array(
						'label' => $txt['mc_reported_pms'] . (!empty($mod_counts['pm_reports']) ? ' [' . $mod_counts['pm_reports'] . ']' : ''),
						'enabled' => $user_info['is_admin'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_reportedPosts',
						'subsections' => array(
							'open' => array($txt['mc_reportedp_active']),
							'closed' => array($txt['mc_reportedp_closed']),
						),
					),
				),
			),
			'groups' => array(
				'title' => $txt['mc_groups'] . (!empty($mod_counts['mg_total']) ? ' [' . $mod_counts['mg_total'] . ']' : ''),
				'enabled' => $context['can_moderate_groups'],
				'areas' => array(
					'userwatch' => array(
						'label' => $txt['mc_watched_users_title'],
						'enabled' => in_array('w', $context['admin_features']) && !empty($modSettings['warning_enable']) && $context['can_moderate_boards'],
						'controller' => 'ModerationCenter_Controller',
						'function' => 'action_viewWatchedUsers',
						'icon' => 'transparent.png',
						'class' => 'admin_img_permissions',
						'subsections' => array(
							'member' => array($txt['mc_watched_users_member']),
							'post' => array($txt['mc_watched_users_post']),
						),
					),
					'groups' => array(
						'label' => $txt['mc_group_requests'] . (!empty($mod_counts['groupreq']) ? ' [' . $mod_counts['groupreq'] . ']' : ''),
						'controller' => 'Groups_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_regcenter',
						'custom_url' => $scripturl . '?action=moderate;area=groups;sa=requests',
					),
					'members' => array(
						'enabled' => allowedTo('moderate_forum'),
						'label' => $txt['mc_member_requests'] . (!empty($mod_counts['memberreq']) ? ' [' . $mod_counts['memberreq'] . ']' : ''),
						'controller' => 'ManageMembers_Controller',
						'function' => 'action_approve',
						'icon' => 'transparent.png',
						'class' => 'admin_img_members',
						'custom_url' => $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve',
					),
					'viewgroups' => array(
						'label' => $txt['mc_view_groups'],
						'controller' => 'Groups_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_membergroups',
					),
				),
			),
		);

		// Make sure the administrator has a valid session...
		validateSession('moderate');

		// I don't know where we're going - I don't know where we've been...
		$menuOptions = array(
			'action' => 'moderate',
			'hook' => 'moderation',
			'disable_url_session_check' => true,
			'default_include_dir' => CONTROLLERDIR,
		);

		$mod_include_data = createMenu($moderation_areas, $menuOptions);
		unset($moderation_areas);

		// We got something - didn't we? DIDN'T WE!
		if ($mod_include_data === false)
			throw new Elk_Exception('no_access', false);

		// Retain the ID information in case required by a subaction.
		$context['moderation_menu_id'] = $context['max_menu_id'];
		$context['moderation_menu_name'] = 'menu_data_' . $context['moderation_menu_id'];
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['moderation_center'],
			'help' => '',
			'description' => sprintf($txt['mc_description'], $context['user']['name'], $scripturl . '?action=moderate;area=settings'));

		// What a pleasant shortcut - even tho we're not *really* on the admin screen who cares...
		$context['admin_area'] = $mod_include_data['current_area'];

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=moderate',
			'name' => $txt['moderation_center'],
		);

		if (isset($mod_include_data['current_area']) && $mod_include_data['current_area'] != 'index')
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=moderate;area=' . $mod_include_data['current_area'],
				'name' => $mod_include_data['label'],
			);

		if (!empty($mod_include_data['current_subsection']) && $mod_include_data['subsections'][$mod_include_data['current_subsection']][0] != $mod_include_data['label'])
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=moderate;area=' . $mod_include_data['current_area'] . ';sa=' . $mod_include_data['current_subsection'],
				'name' => $mod_include_data['subsections'][$mod_include_data['current_subsection']][0],
			);

		// Finally, store this, so that if we're called from the class, it can use it.
		$this->_mod_include_data = $mod_include_data;
	}

	/**
	 * This handler presents the home page of the moderation center.
	 */
	public function action_moderationHome()
	{
		global $txt, $context, $user_settings;

		theme()->getTemplates()->load('ModerationCenter');
		loadJavascriptFile('admin.js', array(), 'admin_scripts');

		$context['page_title'] = $txt['moderation_center'];
		$context['sub_template'] = 'moderation_center';

		// Start off with no blocks
		$valid_blocks = array();

		// Load what blocks the user actually can see...
		$valid_blocks['p'] = 'notes';

		if ($context['can_moderate_boards'])
		{
			$valid_blocks['a'] = 'actionRequired';
			$valid_blocks['r'] = 'reportedPosts';
		}

		if ($context['can_moderate_groups'])
			$valid_blocks['g'] = 'groupRequests';

		if ($context['can_moderate_boards'])
			$valid_blocks['w'] = 'watchedUsers';

		$valid_blocks['n'] = 'latestNews';

		if (empty($user_settings['mod_prefs']))
			$user_blocks = 'n' . ($context['can_moderate_boards'] ? 'wra' : '') . ($context['can_moderate_groups'] ? 'g' : '');
		else
			list (, $user_blocks) = explode('|', $user_settings['mod_prefs']);

		$user_blocks = str_split($user_blocks);

		$context['mod_blocks'] = array();
		foreach ($valid_blocks as $k => $block)
		{
			if (in_array($k, $user_blocks))
			{
				$block = 'block_' . $block;

				if (method_exists($this, $block))
					$context['mod_blocks'][] = $this->{$block}();
			}
		}
	}

	/**
	 * This ends a moderator session, requiring authentication to access the MCP again.
	 */
	public function action_modEndSession()
	{
		// This is so easy!
		unset($_SESSION['moderate_time']);

		// Clean any moderator tokens as well.
		cleanTokens(false, '-mod');

		redirectexit('action=moderate');
	}

	/**
	 * Show a warning notice sent to a user.
	 */
	public function action_showNotice()
	{
		global $txt, $context;

		// What notice have they asked to view
		$id_notice = $this->_req->getQuery('nid', 'intval', 0);
		$notice = moderatorNotice($id_notice);

		// legit?
		if (empty($notice) || !$context['can_moderate_boards'])
			throw new Elk_Exception('no_access', false);

		list ($context['notice_body'], $context['notice_subject']) = $notice;

		$parser = \BBC\ParserWrapper::instance();

		$context['notice_body'] = $parser->parseNotice($context['notice_body']);
		$context['page_title'] = $txt['show_notice'];
		$context['sub_template'] = 'show_notice';

		theme()->getLayers()->removeAll();
		theme()->getTemplates()->load('ModerationCenter');
	}

	/**
	 * Browse all the reported posts.
	 *
	 * @todo this needs to be given its own file?
	 */
	public function action_reportedPosts()
	{
		global $txt, $context, $scripturl, $user_info;

		theme()->getTemplates()->load('ModerationCenter');
		require_once(SUBSDIR . '/Moderation.subs.php');

		// Put the open and closed options into tabs, because we can...
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_reported_posts'],
			'help' => '',
			'description' => $txt['mc_reported_posts_desc'],
		);

		// Set up the comforting bits...
		$context['page_title'] = $txt['mc_reported_posts'];
		$context['sub_template'] = 'reported_posts';

		// This comes under the umbrella of moderating posts.
		if ($user_info['mod_cache']['bq'] === '0=1')
			isAllowedTo('moderate_forum');

		// Are they wanting to view a particular report?
		if (!empty($this->_req->query->report))
			return $this->action_modReport();

		// This should not be needed...
		$show_pms = false;
		if ($context['admin_area'] === 'pm_reports')
		{
			$show_pms = true;
			isAllowedTo('admin_forum');

			// Put the open and closed options into tabs, because we can...
			$context[$context['moderation_menu_name']]['tab_data'] = array(
				'title' => $txt['mc_reported_pms'],
				'help' => '',
				'description' => $txt['mc_reported_pms_desc'],
			);
			$context['page_title'] = $txt['mc_reported_pms'];
		}

		// Are we viewing open or closed reports?
		$context['view_closed'] = $this->_req->getQuery('sa') === 'closed' ? 1 : 0;

		// Are we doing any work?
		if ((isset($this->_req->query->ignore) || isset($this->_req->query->close)) && isset($this->_req->query->rid))
		{
			checkSession('get');
			$rid = $this->_req->getQuery('rid', 'intval');

			// Update the report...
			if (isset($this->_req->query->ignore))
				updateReportsStatus($rid, 'ignore', (int) $this->_req->query->ignore);
			elseif (isset($this->_req->query->close))
				updateReportsStatus($rid, 'close', (int) $this->_req->query->close);

			// Time to update.
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports(true, $show_pms);
		}
		elseif (isset($this->_req->post->close) && isset($this->_req->post->close_selected))
		{
			checkSession('post');

			// All the ones to update...
			$toClose = array_map('intval', $this->_req->post->close);
			if (!empty($toClose))
			{
				updateReportsStatus($toClose, 'close', 1);

				// Time to update.
				updateSettings(array('last_mod_report_action' => time()));
				recountOpenReports(true, $show_pms);
			}
		}

		// How many entries are we viewing?
		$context['total_reports'] = totalReports($context['view_closed'], $show_pms);

		// So, that means we can page index, yes?
		$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=' . $context['admin_area'] . ($context['view_closed'] ? ';sa=closed' : ''), $this->_req->query->start, $context['total_reports'], 10);
		$context['start'] = $this->_req->query->start;

		// By George, that means we in a position to get the reports, golly good.
		$context['reports'] = getModReports($context['view_closed'], $context['start'], 10, $show_pms);
		$report_ids = array_keys($context['reports']);
		$report_boards_ids = array();
		$bbc_parser = \BBC\ParserWrapper::instance();
		foreach ($context['reports'] as $row)
		{
			$context['reports'][$row['id_report']] = array(
				'board' => $row['id_board'],
				'id' => $row['id_report'],
				'topic_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'report_href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . ';report=' . $row['id_report'],
				'author' => array(
					'id' => $row['id_author'],
					'name' => $row['author_name'],
					'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
				),
				'comments' => array(),
				'time_started' => standardTime($row['time_started']),
				'last_updated' => standardTime($row['time_updated']),
				'subject' => $row['subject'],
				'body' => $bbc_parser->parseReport($row['body']),
				'num_reports' => $row['num_reports'],
				'closed' => $row['closed'],
				'ignore' => $row['ignore_all'],
				'buttons' => array(
					'quickmod_check' => array(
						'checkbox' => !$context['view_closed'],
						'name' => 'close',
						'value' => $row['id_report'],
					),
					'details' => array(
						'href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . ';report=' . $row['id_report'],
						'text' => $txt['mc_reportedp_details'],
					),
					'ignore' => array(
						'href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . '' . ($context['view_closed'] ? ';sa=closed' : '') . ';ignore=' . ((int) !$row['ignore_all']) . ';rid=' . $row['id_report'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
						'text' => $row['ignore_all'] ? $txt['mc_reportedp_unignore'] : $txt['mc_reportedp_ignore'],
						'custom' => $row['ignore_all'] ? '' : 'onclick="return confirm(' . JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) . ');"',
					),
					'close' => array(
						'href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . '' . ($context['view_closed'] ? ';sa=closed' : '') . ';close=' . ((int) !$row['closed']) . ';rid=' . $row['id_report'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
						'text' => $context['view_closed'] ? $txt['mc_reportedp_open'] : $txt['mc_reportedp_close'],
					),
				),
			);
			$report_boards_ids[] = $row['id_board'];
		}

		// Get the names of boards these topics are in.
		if (!empty($report_ids))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$board_names = getBoardList(array('included_boards' => $report_boards_ids), true);

			// Add the board name to the report array
			foreach ($context['reports'] as $id_report => $report)
				if (!empty($board_names[$report['board']]))
					$context['reports'][$id_report]['board_name'] = $board_names[$report['board']]['board_name'];
		}

		// Now get all the people who reported it.
		if (!empty($report_ids))
		{
			$comments = getReportsUserComments($report_ids);
			foreach ($comments as $id_rep => $rows)
			{
				foreach ($rows as $row)
					$context['reports'][$id_rep]['comments'][] = array(
						'id' => $row['id_comment'],
						'message' => $row['comment'],
						'raw_time' => $row['time_sent'],
						'time' => standardTime($row['time_sent']),
						'html_time' => htmlTime($row['time_sent']),
						'timestamp' => forum_time(true, $row['time_sent']),
						'member' => array(
							'id' => $row['id_member'],
							'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
							'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
							'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
						),
					);
			}
		}
	}

	/**
	 * Change moderation preferences.
	 */
	public function action_moderationSettings()
	{
		global $context, $txt, $user_settings, $user_info;

		// Some useful context stuff.
		theme()->getTemplates()->load('ModerationCenter');
		$context['page_title'] = $txt['mc_settings'];
		$context['sub_template'] = 'moderation_settings';
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_prefs_title'],
			'help' => '',
			'description' => $txt['mc_prefs_desc']
		);

		// What blocks can this user see?
		$context['homepage_blocks'] = array(
			'n' => $txt['mc_prefs_latest_news'],
			'p' => $txt['mc_notes'],
		);

		if ($context['can_moderate_groups'])
			$context['homepage_blocks']['g'] = $txt['mc_group_requests'];

		if ($context['can_moderate_boards'])
		{
			$context['homepage_blocks']['r'] = $txt['mc_reported_posts'];
			$context['homepage_blocks']['w'] = $txt['mc_watched_users'];
			$context['homepage_blocks']['a'] = $txt['mc_required'];
		}

		// Does the user have any settings yet?
		if (empty($user_settings['mod_prefs']))
		{
			$mod_blocks = 'np' . ($context['can_moderate_boards'] ? 'wra' : '') . ($context['can_moderate_groups'] ? 'g' : '');
			$pref_binary = 5;
			$show_reports = 1;
		}
		else
			list ($show_reports, $mod_blocks, $pref_binary) = explode('|', $user_settings['mod_prefs']);

		// Are we saving?
		if (isset($this->_req->post->save))
		{
			checkSession('post');
			validateToken('mod-set');

			/* Current format of mod_prefs is:
				x|ABCD|yyy

				WHERE:
					x = Show report count on forum header.
					ABCD = Block indexes to show on moderation main page.
					yyy = Integer with the following bit status:
						- yyy & 1 = Always notify on reports.
						- yyy & 2 = Notify on reports for moderators only.
						- yyy & 4 = Notify about posts awaiting approval.
			*/

			// Do blocks first!
			$mod_blocks = '';
			if (!empty($this->_req->post->mod_homepage))
				foreach ($this->_req->post->mod_homepage as $k => $v)
				{
					// Make sure they can add this...
					if (isset($context['homepage_blocks'][$k]))
						$mod_blocks .= $k;
				}

			// Now check other options!
			$pref_binary = 0;

			if ($context['can_moderate_approvals'] && !empty($this->_req->post->mod_notify_approval))
				$pref_binary |= 4;

			if ($context['can_moderate_boards'])
			{
				if (!empty($this->_req->post->mod_notify_report))
					$pref_binary |= ($this->_req->post->mod_notify_report == 2 ? 1 : 2);

				$show_reports = !empty($this->_req->post->mod_show_reports) ? 1 : 0;
			}

			// Put it all together.
			$mod_prefs = $show_reports . '|' . $mod_blocks . '|' . $pref_binary;
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_info['id'], array('mod_prefs' => $mod_prefs));
		}

		// What blocks does the user currently have selected?
		$context['mod_settings'] = array(
			'show_reports' => $show_reports,
			'notify_report' => $pref_binary & 2 ? 1 : ($pref_binary & 1 ? 2 : 0),
			'notify_approval' => $pref_binary & 4,
			'user_blocks' => str_split($mod_blocks),
		);

		createToken('mod-set');
	}

	/**
	 * Edit a warning template.
	 *
	 * @uses template_warn_template()
	 */
	public function action_modifyWarningTemplate()
	{
		global $context, $txt, $user_info;

		require_once(SUBSDIR . '/Moderation.subs.php');
		loadJavascriptFile('admin.js', array(), 'admin_scripts');

		$context['id_template'] = $this->_req->getQuery('tid', 'intval', 0);
		$context['is_edit'] = $context['id_template'];

		// Standard template things.
		$context['page_title'] = $context['is_edit'] ? $txt['mc_warning_template_modify'] : $txt['mc_warning_template_add'];
		$context['sub_template'] = 'warn_template';
		$context[$context['moderation_menu_name']]['current_subsection'] = 'templates';

		// Defaults.
		$context['template_data'] = array(
			'title' => '',
			'body' => $txt['mc_warning_template_body_default'],
			'personal' => false,
			'can_edit_personal' => true,
		);

		// If it's an edit load it.
		if ($context['is_edit'])
			modLoadTemplate($context['id_template']);

		// Wait, we are saving?
		if (isset($this->_req->post->save))
		{
			checkSession('post');
			validateToken('mod-wt');

			// To check the BBC is pretty good...
			require_once(SUBSDIR . '/Post.subs.php');

			// Bit of cleaning!
			$template_body = trim($this->_req->post->template_body);
			$template_title = trim($this->_req->post->template_title);

			// Need something in both boxes.
			if (!empty($template_body) && !empty($template_title))
			{
				// Safety first.
				$template_title = Util::htmlspecialchars($template_title);

				// Clean up BBC.
				preparsecode($template_body);

				// But put line breaks back!
				$template_body = strtr($template_body, array('<br />' => "\n"));

				// Is this personal?
				$recipient_id = !empty($this->_req->post->make_personal) ? $user_info['id'] : 0;

				// If we are this far it's save time.
				if ($context['is_edit'])
				{
					// Simple update...
					modAddUpdateTemplate($recipient_id, $template_title, $template_body, $context['id_template']);

					// If it wasn't visible and now is they've effectively added it.
					if ($context['template_data']['personal'] && !$recipient_id)
						logAction('add_warn_template', array('template' => $template_title));
					// Conversely if they made it personal it's a delete.
					elseif (!$context['template_data']['personal'] && $recipient_id)
						logAction('delete_warn_template', array('template' => $template_title));
					// Otherwise just an edit.
					else
						logAction('modify_warn_template', array('template' => $template_title));
				}
				else
				{
					modAddUpdateTemplate($recipient_id, $template_title, $template_body, $context['id_template'], false);
					logAction('add_warn_template', array('template' => $template_title));
				}

				// Get out of town...
				redirectexit('action=moderate;area=warnings;sa=templates');
			}
			else
			{
				$context['warning_errors'] = array();
				$context['template_data']['title'] = !empty($template_title) ? $template_title : '';
				$context['template_data']['body'] = !empty($template_body) ? $template_body : $txt['mc_warning_template_body_default'];
				$context['template_data']['personal'] = !empty($this->_req->post->make_personal);

				if (empty($template_title))
					$context['warning_errors'][] = $txt['mc_warning_template_error_no_title'];

				if (empty($template_body))
					$context['warning_errors'][] = $txt['mc_warning_template_error_no_body'];
			}
		}

		createToken('mod-wt');
	}

	/**
	 * Get details about the moderation report
	 *
	 * - report is specified in the url param report.
	 */
	public function action_modReport()
	{
		global $context, $scripturl, $txt;

		// Have to at least give us something
		$report = $this->_req->getQuery('report', 'intval', 0);
		if (empty($report))
			throw new Elk_Exception('mc_no_modreport_specified');

		// This should not be needed...
		$show_pms = false;
		if ($context['admin_area'] === 'pm_reports')
		{
			$show_pms = true;
			isAllowedTo('admin_forum');
		}

		// Get the report details, need this so we can limit access to a particular board
		$row = modReportDetails($report, $show_pms);

		// So did we find anything?
		if ($row === false)
			throw new Elk_Exception('mc_no_modreport_found');

		// Woohoo we found a report and they can see it!  Bad news is we have more work to do
		// If they are adding a comment then... add a comment.
		if (isset($this->_req->post->add_comment) && !empty($this->_req->post->mod_comment))
		{
			checkSession();

			$newComment = trim(Util::htmlspecialchars($this->_req->post->mod_comment));

			// In it goes.
			if (!empty($newComment))
			{
				addReportComment($report, $newComment);

				// Redirect to prevent double submission.
				redirectexit($scripturl . '?action=moderate;area=' . $context['admin_area'] . ';report=' . $report);
			}
		}

		$bbc_parser = \BBC\ParserWrapper::instance();

		$context['report'] = array(
			'id' => $row['id_report'],
			'topic_id' => $row['id_topic'],
			'board_id' => $row['id_board'],
			'message_id' => $row['id_msg'],
			'message_href' => $scripturl . '?msg=' . $row['id_msg'],
			'message_link' => '<a href="' . $scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
			'report_href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . ';' . $context['admin_area'] . '=' . $row['id_report'],
			'author' => array(
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
			),
			'comments' => array(),
			'mod_comments' => array(),
			'time_started' => standardTime($row['time_started']),
			'last_updated' => standardTime($row['time_updated']),
			'subject' => $row['subject'],
			'body' => $bbc_parser->parseReport($row['body']),
			'num_reports' => $row['num_reports'],
			'closed' => $row['closed'],
			'ignore' => $row['ignore_all']
		);

		// So what bad things do the reporters have to say about it?
		$comments = getReportsUserComments($context['report']['id']);
		foreach ($comments[$context['report']['id']] as $row)
		{
			$context['report']['comments'][] = array(
				'id' => $row['id_comment'],
				'message' => strtr($row['comment'], array("\n" => '<br />')),
				'time' => standardTime($row['time_sent']),
				'html_time' => htmlTime($row['time_sent']),
				'timestamp' => forum_time(true, $row['time_sent']),
				'member' => array(
					'id' => $row['id_member'],
					'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
					'href' => $row['id_member'] ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
					'ip' => !empty($row['member_ip']) && allowedTo('moderate_forum') ? '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>' : '',
				),
			);
		}

		// Hang about old chap, any comments from moderators on this one?
		$mod_comments = getReportModeratorsComments($context['report']['id']);
		foreach ($mod_comments as $row)
		{
			$context['report']['mod_comments'][] = array(
				'id' => $row['id_comment'],
				'message' => $bbc_parser->parseReport($row['body']),
				'time' => standardTime($row['log_time']),
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
				'member' => array(
					'id' => $row['id_member'],
					'name' => $row['moderator'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['moderator'] . '</a>' : $row['moderator'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				),
			);
		}

		// What have the other moderators done to this message?
		require_once(SUBSDIR . '/Modlog.subs.php');
		theme()->getTemplates()->loadLanguageFile('Modlog');

		// This is all the information from the moderation log.
		$listOptions = array(
			'id' => 'moderation_actions_list',
			'title' => $txt['mc_modreport_modactions'],
			'items_per_page' => 15,
			'no_items_label' => $txt['modlog_no_entries_found'],
			'base_href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . ';report=' . $context['report']['id'],
			'default_sort_col' => 'time',
			'get_items' => array(
				'function' => 'list_getModLogEntries',
				'params' => array(
					'lm.id_topic = {int:id_topic}',
					array('id_topic' => $context['report']['topic_id']),
					1,
				),
			),
			'get_count' => array(
				'function' => 'list_getModLogEntryCount',
				'params' => array(
					'lm.id_topic = {int:id_topic}',
					array('id_topic' => $context['report']['topic_id']),
					1,
				),
			),
			// This assumes we are viewing by user.
			'columns' => array(
				'action' => array(
					'header' => array(
						'value' => $txt['modlog_action'],
					),
					'data' => array(
						'db' => 'action_text',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'lm.action',
						'reverse' => 'lm.action DESC',
					),
				),
				'time' => array(
					'header' => array(
						'value' => $txt['modlog_date'],
					),
					'data' => array(
						'db' => 'time',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'lm.log_time',
						'reverse' => 'lm.log_time DESC',
					),
				),
				'moderator' => array(
					'header' => array(
						'value' => $txt['modlog_member'],
					),
					'data' => array(
						'db' => 'moderator_link',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'position' => array(
					'header' => array(
						'value' => $txt['modlog_position'],
					),
					'data' => array(
						'db' => 'position',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'ip' => array(
					'header' => array(
						'value' => $txt['modlog_ip'],
					),
					'data' => array(
						'db' => 'ip',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'lm.ip',
						'reverse' => 'lm.ip DESC',
					),
				),
			),
		);

		// Create the watched user list.
		createList($listOptions);

		// Make sure to get the correct tab selected.
		if ($context['report']['closed'])
			$context[$context['moderation_menu_name']]['current_subsection'] = 'closed';

		// Finally we are done :P
		theme()->getTemplates()->load('ModerationCenter');
		if ($context['admin_area'] === 'pm_reports')
		{
			$context['page_title'] = sprintf($txt['mc_view_pmreport'], $context['report']['author']['name']);
			$context['section_title'] = sprintf($txt['mc_view_pmreport'], $context['report']['author']['link']);
			$context['section_descripion'] = sprintf($txt['mc_pmreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']);
		}
		else
		{
			$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
			$context['section_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['message_link'], $context['report']['author']['link']);
			$context['section_descripion'] = sprintf($txt['mc_modreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']);
		}

		$context['sub_template'] = 'viewmodreport';
	}

	/**
	 * View watched users and their posts
	 */
	public function action_viewWatchedUsers()
	{
		global $modSettings, $context, $txt, $scripturl, $user_info;

		// Some important context!
		$context['page_title'] = $txt['mc_watched_users_title'];
		$context['view_posts'] = isset($this->_req->query->sa) && $this->_req->query->sa === 'post';
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);

		theme()->getTemplates()->load('ModerationCenter');

		// Get some key settings!
		$modSettings['warning_watch'] = empty($modSettings['warning_watch']) ? 1 : $modSettings['warning_watch'];

		// Put some pretty tabs on cause we're gonna be doing hot stuff here...
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_watched_users_title'],
			'help' => '',
			'description' => $txt['mc_watched_users_desc'],
		);

		// First off - are we deleting?
		if (!empty($this->_req->query->delete) || !empty($this->_req->post->delete))
		{
			checkSession(isset($this->_req->query->delete) ? 'get' : 'post');

			// Clicked on remove or using checkboxes to multi delete
			$toDelete = array();
			if (isset($this->_req->query->delete))
				$toDelete[] = (int) $this->_req->query->delete;
			else
				$toDelete = array_map('intval', $this->_req->post->delete);

			if (!empty($toDelete))
			{
				$remover = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);

				// If they don't have permission we'll let it error - either way no chance of a security slip here!
				foreach ($toDelete as $did)
					$remover->removeMessage($did);
			}
		}

		// Start preparing the list by grabbing relevant permissions.
		if (!$context['view_posts'])
		{
			$approve_query = '';
			$delete_boards = array();
		}
		else
		{
			// Still obey permissions!
			$approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');
			$delete_boards = boardsAllowedTo('delete_any');

			if ($approve_boards == array(0))
				$approve_query = '';
			elseif (!empty($approve_boards))
				$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
			// Nada, zip, etc...
			else
				$approve_query = ' AND 1=0';
		}

		// This is all the information required for a watched user listing.
		$listOptions = array(
			'id' => 'watch_user_list',
			'title' => $txt['mc_watched_users_title'] . ' - ' . ($context['view_posts'] ? $txt['mc_watched_users_post'] : $txt['mc_watched_users_member']),
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $context['view_posts'] ? $txt['mc_watched_users_no_posts'] : $txt['mc_watched_users_none'],
			'base_href' => $scripturl . '?action=moderate;area=userwatch;sa=' . ($context['view_posts'] ? 'post' : 'member'),
			'default_sort_col' => $context['view_posts'] ? '' : 'member',
			'get_items' => array(
				'function' => $context['view_posts'] ? array($this, 'list_getWatchedUserPosts') : array($this, 'list_getWatchedUsers'),
				'params' => array(
					$approve_query,
					$delete_boards,
				),
			),
			'get_count' => array(
				'function' => $context['view_posts'] ? array($this, 'list_getWatchedUserPostsCount') : array($this, 'list_getWatchedUserCount'),
				'params' => array(
					$approve_query,
				),
			),
			// This assumes we are viewing by user.
			'columns' => array(
				'member' => array(
					'header' => array(
						'value' => $txt['mc_watched_users_member'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
							'params' => array(
								'id' => false,
								'name' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'real_name',
						'reverse' => 'real_name DESC',
					),
				),
				'warning' => array(
					'header' => array(
						'value' => $txt['mc_watched_users_warning'],
					),
					'data' => array(
						'function' => function ($member) {
							global $scripturl;

							return allowedTo('issue_warning') ? '<a href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $member['id'] . '">' . $member['warning'] . '%</a>' : $member['warning'] . '%';
						},
					),
					'sort' => array(
						'default' => 'warning',
						'reverse' => 'warning DESC',
					),
				),
				'posts' => array(
					'header' => array(
						'value' => $txt['posts'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d;area=showposts;sa=messages">%2$s</a>',
							'params' => array(
								'id' => false,
								'posts' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'posts',
						'reverse' => 'posts DESC',
					),
				),
				'last_login' => array(
					'header' => array(
						'value' => $txt['mc_watched_users_last_login'],
					),
					'data' => array(
						'db' => 'last_login',
					),
					'sort' => array(
						'default' => 'last_login',
						'reverse' => 'last_login DESC',
					),
				),
				'last_post' => array(
					'header' => array(
						'value' => $txt['mc_watched_users_last_post'],
					),
					'data' => array(
						'function' => function ($member) {
							global $scripturl;

							if ($member['last_post_id'])
								return '<a href="' . $scripturl . '?msg=' . $member['last_post_id'] . '">' . $member['last_post'] . '</a>';
							else
								return $member['last_post'];
						},
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=moderate;area=userwatch;sa=post',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				$context['view_posts'] ?
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="delete_selected" value="' . $txt['quickmod_delete_selected'] . '" class="right_submit" />',
				) : array(),
			),
		);

		// If this is being viewed by posts we actually change the columns to call a template each time.
		if ($context['view_posts'])
		{
			$listOptions['columns'] = array(
				'posts' => array(
					'data' => array(
						'function' => function ($post) {
							return template_user_watch_post_callback($post);
						},
					),
				),
			);
		}

		// Create the watched user list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'watch_user_list';
	}

	/**
	 * Simply put, look at the warning log!
	 */
	public function action_viewWarningLog()
	{
		global $modSettings, $context, $txt, $scripturl;

		// Setup context as always.
		$context['page_title'] = $txt['mc_warning_log_title'];

		require_once(SUBSDIR . '/Moderation.subs.php');
		theme()->getTemplates()->loadLanguageFile('Modlog');

		// If we're coming in from a search, get the variables.
		if (!empty($this->_req->post->params) && empty($this->_req->post->is_search))
		{
			$search_params = base64_decode(strtr($this->_req->post->params, array(' ' => '+')));
			$search_params = @json_decode($search_params);
		}

		// This array houses all the valid search types.
		$searchTypes = array(
			'member' => array('sql' => 'mem.real_name', 'label' => $txt['profile_warning_previous_issued']),
			'recipient' => array('sql' => 'recipient_name', 'label' => $txt['mc_warnings_recipient']),
		);

		// Setup the allowed quick search type
		$context['order'] = isset($this->_req->query->sort) && isset($searchTypes[$this->_req->query->sort]) ? $this->_req->query->sort : 'member';
		$context['url_start'] = '?action=moderate;area=warnings;sa=log;sort=' . $context['order'];

		if (!isset($search_params['string']) || (!empty($this->_req->post->search) && $search_params['string'] != $this->_req->post->search))
			$search_params_string = empty($this->_req->post->search) ? '' : $this->_req->post->search;
		else
			$search_params_string = $search_params['string'];

		if (isset($this->_req->post->search_type) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
			$search_params_type = isset($this->_req->post->search_type) && isset($searchTypes[$this->_req->post->search_type]) ? $this->_req->post->search_type : (isset($searchTypes[$context['order']]) ? $context['order'] : 'member');
		else
			$search_params_type = $search_params['type'];

		$search_params_column = $searchTypes[$search_params_type]['sql'];
		$search_params = array(
			'string' => $search_params_string,
			'type' => $search_params_type,
		);

		// Setup the search context.
		$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(json_encode($search_params));
		$context['search'] = array(
			'string' => $search_params['string'],
			'type' => $search_params['type'],
			'label' => $searchTypes[$search_params_type]['label'],
		);

		// This is all the information required for a watched user listing.
		$listOptions = array(
			'id' => 'warning_list',
			'title' => $txt['mc_warning_log_title'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['mc_warnings_none'],
			'base_href' => $scripturl . '?action=moderate;area=warnings;sa=log;' . $context['session_var'] . '=' . $context['session_id'],
			'default_sort_col' => 'time',
			'get_items' => array(
				'function' => array($this, 'list_getWarnings'),
				'params' => array(
					(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
					array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getWarningCount'),
				'params' => array(
					(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
					array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
				),
			),
			// This assumes we are viewing by user.
			'columns' => array(
				'issuer' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_issued'],
					),
					'data' => array(
						'db' => 'issuer_link',
					),
					'sort' => array(
						'default' => 'member_name_col',
						'reverse' => 'member_name_col DESC',
					),
				),
				'recipient' => array(
					'header' => array(
						'value' => $txt['mc_warnings_recipient'],
					),
					'data' => array(
						'db' => 'recipient_link',
					),
					'sort' => array(
						'default' => 'recipient_name',
						'reverse' => 'recipient_name DESC',
					),
				),
				'time' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_time'],
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_reason'],
					),
					'data' => array(
						'function' => function ($warning) {
							global $scripturl, $txt;

							$output = '
								<div class="floatleft">
									' . $warning['reason'] . '
								</div>';

							// If a notice was sent, provide a link to it
							if (!empty($warning['id_notice']))
								$output .= '
									<a href="' . $scripturl . '?action=moderate;area=notice;nid=' . $warning['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=480,height=320\');return false;" target="_blank" class="new_win" title="' . $txt['profile_warning_previous_notice'] . '"><i class="icon icon-small i-search" title"' . $txt['profile_warning_previous_notice'] . '"></i></a>';
							return $output;
						},
					),
				),
				'points' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_level'],
					),
					'data' => array(
						'db' => 'counter',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . $context['url_start'],
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
					'params' => $context['search_params']
				),
			),
			'additional_rows' => array(
				array(
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '
						' . $txt['modlog_search'] . ' (' . $txt['modlog_by'] . ': ' . $context['search']['label'] . ')
						<input type="text" name="search" size="18" value="' . Util::htmlspecialchars($context['search']['string']) . '" class="input_text" />
						<input type="submit" name="is_search" value="' . $txt['modlog_go'] . '" />',
				),
			),
		);

		// Create the watched user list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'warning_list';
	}

	/**
	 * View all the custom warning templates.
	 *
	 *  - Shows all the templates in the system
	 *  - Provides for actions to add or delete them
	 */
	public function action_viewWarningTemplates()
	{
		global $modSettings, $context, $txt, $scripturl;

		require_once(SUBSDIR . '/Moderation.subs.php');

		// Submitting a new one?
		if (isset($this->_req->post->add))
		{
			$this->action_modifyWarningTemplate();
			return true;
		}
		// Deleting and existing one
		elseif (isset($this->_req->post->delete) && !empty($this->_req->post->deltpl))
		{
			checkSession('post');
			validateToken('mod-wt');

			removeWarningTemplate($this->_req->post->deltpl);
		}

		// Setup context as always.
		$context['page_title'] = $txt['mc_warning_templates_title'];

		// This is all the information required for a watched user listing.
		$listOptions = array(
			'id' => 'warning_template_list',
			'title' => $txt['mc_warning_templates_title'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['mc_warning_templates_none'],
			'base_href' => $scripturl . '?action=moderate;area=warnings;sa=templates;' . $context['session_var'] . '=' . $context['session_id'],
			'default_sort_col' => 'title',
			'get_items' => array(
				'function' => array($this, 'list_getWarningTemplates'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getWarningTemplateCount'),
			),
			'columns' => array(
				'title' => array(
					'header' => array(
						'value' => $txt['mc_warning_templates_name'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=moderate;area=warnings;sa=templateedit;tid=%1$d">%2$s</a>',
							'params' => array(
								'id_comment' => false,
								'title' => false,
								'body' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'template_title',
						'reverse' => 'template_title DESC',
					),
				),
				'creator' => array(
					'header' => array(
						'value' => $txt['mc_warning_templates_creator'],
					),
					'data' => array(
						'db' => 'creator',
					),
					'sort' => array(
						'default' => 'creator_name',
						'reverse' => 'creator_name DESC',
					),
				),
				'time' => array(
					'header' => array(
						'value' => $txt['mc_warning_templates_time'],
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 4%;text-align: center;',
					),
					'data' => array(
						'function' => function ($rowData) {
							return '<input type="checkbox" name="deltpl[]" value="' . $rowData['id_comment'] . '" class="input_check" />';
						},
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=moderate;area=warnings;sa=templates',
				'token' => 'mod-wt',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="delete" value="' . $txt['mc_warning_template_delete'] . '" onclick="return confirm(\'' . $txt['mc_warning_template_delete_confirm'] . '\');" class="right_submit" />
						<input type="submit" name="add" value="' . $txt['mc_warning_template_add'] . '" class="right_submit" />',
				),
			),
		);

		// Create the watched user list.
		createToken('mod-wt');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'warning_template_list';
	}

	/**
	 * Entry point for viewing warning related stuff.
	 */
	public function action_viewWarnings()
	{
		global $context, $txt;

		// Some of this stuff is overseas, so to speak.
		theme()->getTemplates()->load('ModerationCenter');
		theme()->getTemplates()->loadLanguageFile('Profile');

		$subActions = array(
			'log' => array($this, 'action_viewWarningLog'),
			'templateedit' => array($this, 'action_modifyWarningTemplate', 'permission' => 'issue_warning'),
			'templates' => array($this, 'action_viewWarningTemplates', 'permission' => 'issue_warning'),
		);

		// Setup the admin tabs.
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_warnings'],
			'description' => $txt['mc_warnings_description'],
		);

		// Call the right function.
		$action = new Action();
		$subAction = $action->initialize($subActions, 'log');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * Callback for createList() for watched users
	 *
	 * - returns count
	 *
	 * @uses watchedUserCount()
	 */
	public function list_getWatchedUserCount()
	{
		global $modSettings;

		return watchedUserCount($modSettings['warning_watch']);
	}

	/**
	 * Callback for createList() used in watched users
	 *
	 * @uses watchedUsers()
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page  The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 */
	public function list_getWatchedUsers($start, $items_per_page, $sort)
	{
		// Find all our watched users
		return watchedUsers($start, $items_per_page, $sort);
	}

	/**
	 * Callback for createList().
	 *
	 * @uses watchedUserPostsCount()
	 * @param string $approve_query
	 */
	public function list_getWatchedUserPostsCount($approve_query)
	{
		global $modSettings;

		return watchedUserPostsCount($approve_query, $modSettings['warning_watch']);
	}

	/**
	 * Callback for createList().
	 *
	 * @uses watchedUserPosts()
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page  The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $approve_query
	 * @param int[] $delete_boards
	 */
	public function list_getWatchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards)
	{
		// Watched users posts
		return watchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards);
	}

	/**
	 * Callback for createList() to get all the templates of a type from the system
	 *
	 * @uses warningTemplates()
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page  The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $template_type type of template to load
	 */
	public function list_getWarningTemplates($start, $items_per_page, $sort, $template_type = 'warntpl')
	{
		return warningTemplates($start, $items_per_page, $sort, $template_type);
	}

	/**
	 * Callback for createList() to get the number of templates of a type in the system
	 *
	 * @uses warningTemplateCount()
	 * @param string $template_type
	 */
	public function list_getWarningTemplateCount($template_type = 'warntpl')
	{
		return warningTemplateCount($template_type);
	}

	/**
	 * Callback for createList()
	 *
	 * - Used to get all issued warnings in the system
	 * @uses warnings() function in moderation.subs
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page  The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $query_string
	 * @param mixed[] $query_params
	 */
	public function list_getWarnings($start, $items_per_page, $sort, $query_string, $query_params)
	{
		return warnings($start, $items_per_page, $sort, $query_string, $query_params);
	}

	/**
	 * Callback for createList()
	 *
	 * - Get the total count of all current warnings
	 * @uses warningCount() function in moderation.subs
	 *
	 * @param string $query_string
	 * @param mixed[] $query_params
	 */
	public function list_getWarningCount($query_string, $query_params)
	{
		return warningCount($query_string, $query_params);
	}

	/**
	 * Show a list of all the group requests they can see.
	 * Checks permissions for group moderation.
	 */
	public function block_groupRequests()
	{
		global $context, $user_info;

		// Make sure they can even moderate someone!
		if ($user_info['mod_cache']['gq'] === '0=1')
			return 'group_requests_block';

		$context['group_requests'] = groupRequests();

		return 'group_requests_block';
	}

	/**
	 * Just prepares the time stuff for the latest news.
	 */
	public function block_latestNews()
	{
		global $context, $user_info;

		$context['time_format'] = urlencode($user_info['time_format']);

		// Return the template to use.
		return 'latest_news';
	}

	/**
	 * Show a list of the most active watched users.
	 */
	public function block_watchedUsers()
	{
		global $context, $scripturl;

		$watched_users = basicWatchedUsers();

		$context['watched_users'] = array();
		if (is_array($watched_users) || is_object($watched_users))
		{
			foreach ($watched_users as $user)
			{
				$context['watched_users'][] = array(
					'id' => $user['id_member'],
					'name' => $user['real_name'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $user['id_member'] . '">' . $user['real_name'] . '</a>',
					'href' => $scripturl . '?action=profile;u=' . $user['id_member'],
					'last_login' => !empty($user['last_login']) ? standardTime($user['last_login']) : '',
				);
			}
		}

		return 'watched_users';
	}

	/**
	 * Shows a list of items requiring moderation action
	 * Includes post, topic, attachment, group, member and PBE values with links to each
	 */
	public function block_actionRequired()
	{
		global $context;

		// Get the action totals
		$mod_totals = loadModeratorMenuCounts();

		// This blocks total is only these fields
		$context['mc_required'] = $mod_totals['attachments'] + $mod_totals['emailmod'] + $mod_totals['topics'] + $mod_totals['posts'] + $mod_totals['memberreq'] + $mod_totals['groupreq'] + + $mod_totals['reports'];
		unset($mod_totals['postmod'], $mod_totals['pt_total'], $mod_totals['mg_total'], $mod_totals['grand_total']);
		$context['required'] = $mod_totals;

		// Links to the areas
		$context['links'] = array(
			'attachments' => '?action=moderate;area=attachmod;sa=attachments',
			'emailmod' => '?action=admin;area=maillist;sa=emaillist',
			'topics' => '?action=moderate;area=postmod;sa=topics',
			'posts' => '?action=moderate;area=postmod;sa=posts',
			'memberreq' => '?action=admin;area=viewmembers;sa=browse;type=approve',
			'groupreq' => '?action=moderate;area=groups;sa=requests',
			'reports' => '?action=moderate;area=reports;sa=open',
			'pm_reports' => '?action=moderate;area=pm_reports;sa=open',
		);

		return 'action_required';
	}

	/**
	 * Show an area for the moderator to type into.
	 */
	public function block_notes()
	{
		global $context, $scripturl, $txt, $user_info;

		// Are we saving a note?
		if (isset($this->_req->post->makenote) && isset($this->_req->post->new_note))
		{
			checkSession();

			$new_note = Util::htmlspecialchars(trim($this->_req->post->new_note));

			// Make sure they actually entered something.
			if (!empty($new_note) && $new_note !== $txt['mc_click_add_note'])
			{
				// Insert it into the database then!
				addModeratorNote($user_info['id'], $user_info['name'], $new_note);

				// Clear the cache.
				Cache::instance()->remove('moderator_notes');
				Cache::instance()->remove('moderator_notes_total');
			}

			// Redirect otherwise people can resubmit.
			redirectexit('action=moderate');
		}

		// Bye... bye...
		if (isset($this->_req->query->notes) && isset($this->_req->query->delete) && is_numeric($this->_req->query->delete))
		{
			checkSession('get');

			// Just checkin'!
			$id_delete = (int) $this->_req->query->delete;

			// Lets delete it.
			removeModeratorNote($id_delete);

			// Clear the cache.
			Cache::instance()->remove('moderator_notes');
			Cache::instance()->remove('moderator_notes_total');

			redirectexit('action=moderate');
		}

		// How many notes in total?
		$moderator_notes_total = countModeratorNotes();

		// Grab the current notes. We can only use the cache for the first page of notes.
		$offset = isset($this->_req->query->notes) && isset($this->_req->query->start) ? $this->_req->query->start : 0;
		$moderator_notes = moderatorNotes($offset);

		// Lets construct a page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=index;notes', $this->_req->query->start, $moderator_notes_total, 10);
		$context['start'] = $this->_req->query->start;

		$bbc_parser = \BBC\ParserWrapper::instance();

		$context['notes'] = array();
		foreach ($moderator_notes as $note)
		{
			$context['notes'][] = array(
				'author' => array(
					'id' => $note['id_member'],
					'link' => $note['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $note['id_member'] . '" title="' . $txt['on'] . ' ' . strip_tags(standardTime($note['log_time'])) . '">' . $note['member_name'] . '</a>') : $note['member_name'],
				),
				'time' => standardTime($note['log_time']),
				'html_time' => htmlTime($note['log_time']),
				'timestamp' => forum_time(true, $note['log_time']),
				'text' => $bbc_parser->parseReport($note['body']),
				'delete_href' => $scripturl . '?action=moderate;area=index;notes;delete=' . $note['id_note'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			);
		}

		return 'notes';
	}

	/**
	 * Show a list of the most recent reported posts.
	 */
	public function block_reportedPosts()
	{
		global $context, $user_info, $scripturl;

		if ($user_info['mod_cache']['bq'] === '0=1')
			return 'reported_posts_block';

		$context['reported_posts'] = array();

		$reported_posts = reportedPosts(false);
		foreach ($reported_posts as $i => $row)
		{
			$context['reported_posts'][] = array(
				'id' => $row['id_report'],
				'alternate' => $i % 2,
				'topic_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'report_href' => $scripturl . '?action=moderate;area=' . $context['admin_area'] . ';report=' . $row['id_report'],
				'author' => array(
					'id' => $row['id_author'],
					'name' => $row['author_name'],
					'link' => $row['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
				),
				'comments' => array(),
				'subject' => $row['subject'],
				'num_reports' => $row['num_reports'],
			);
		}

		return 'reported_posts_block';
	}
}
