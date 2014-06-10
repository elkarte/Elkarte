<?php

/**
 * Handles all news and newsletter functions for the site
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManageNews controller, for news administration screens.
 *
 * @package News
 */
class ManageNews_Controller extends Action_Controller
{
	/**
	 * News settings form.
	 * @var Settings_Form
	 */
	protected $_newsSettings;

	/**
	 * The news dispatcher / delegator
	 *
	 * What it does:
	 * - This is the entrance point for all News and Newsletter screens.
	 * - Called by ?action=admin;area=news.
	 * - It does the permission checks, and calls the appropriate function
	 * based on the requested sub-action.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		loadTemplate('ManageNews');

		// Format: 'sub-action' => array('function', 'permission')
		$subActions = array(
			'editnews' => array(
				'controller' => $this,
				'function' => 'action_editnews',
				'permission' => 'edit_news'),
			'mailingmembers' => array(
				'controller' => $this,
				'function' => 'action_mailingmembers',
				'permission' => 'send_mail'),
			'mailingcompose' => array(
				'controller' => $this,
				'function' => 'action_mailingcompose',
				'permission' => 'send_mail'),
			'mailingsend' => array(
				'controller' => $this,
				'function' => 'action_mailingsend',
				'permission' => 'send_mail'),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_newsSettings_display',
				'permission' => 'admin_forum'),
		);

		// Action control
		$action = new Action('manage_news');

		// Create the tabs for the template.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['news_title'],
			'help' => 'edit_news',
			'description' => $txt['admin_news_desc'],
			'tabs' => array(
				'editnews' => array(
				),
				'mailingmembers' => array(
					'description' => $txt['news_mailing_desc'],
				),
				'settings' => array(
					'description' => $txt['news_settings_desc'],
				),
			),
		);

		// Default to sub action 'editnews' or 'mailingmembers' or 'settings' depending on permissions.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('edit_news') ? 'editnews' : (allowedTo('send_mail') ? 'mailingmembers' : 'settings'));

		// Give integration its shot via integrate_sa_manage_news
		$action->initialize($subActions, 'settings');

		// Some bits for the tempalte
		$context['page_title'] = $txt['news_title'];
		$context['sub_action'] = $subAction;

		// Force the right area...
		if (substr($subAction, 0, 7) == 'mailing')
			$context[$context['admin_menu_name']]['current_subsection'] = 'mailingmembers';

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Let the administrator(s) edit the news items for the forum.
	 *
	 * What it does:
	 * - It writes an entry into the moderation log.
	 * - This function uses the edit_news administration area.
	 * - Called by ?action=admin;area=news.
	 * - Requires the edit_news permission.
	 * - Can be accessed with ?action=admin;sa=editnews.
	 */
	public function action_editnews()
	{
		global $txt, $modSettings, $context, $scripturl;

		require_once(SUBSDIR . '/Post.subs.php');

		// The 'remove selected' button was pressed.
		if (!empty($_POST['delete_selection']) && !empty($_POST['remove']))
		{
			checkSession();

			// Store the news temporarily in this array.
			$temp_news = explode("\n", $modSettings['news']);

			// Remove the items that were selected.
			foreach ($temp_news as $i => $news)
				if (in_array($i, $_POST['remove']))
					unset($temp_news[$i]);

			// Update the database.
			updateSettings(array('news' => implode("\n", $temp_news)));

			logAction('news');
		}
		// The 'Save' button was pressed.
		elseif (!empty($_POST['save_items']))
		{
			checkSession();

			foreach ($_POST['news'] as $i => $news)
			{
				if (trim($news) == '')
					unset($_POST['news'][$i]);
				else
				{
					$_POST['news'][$i] = Util::htmlspecialchars($_POST['news'][$i], ENT_QUOTES);
					preparsecode($_POST['news'][$i]);
				}
			}

			// Send the new news to the database.
			updateSettings(array('news' => implode("\n", $_POST['news'])));

			// Log this into the moderation log.
			logAction('news');
		}

		// We're going to want this for making our list.
		require_once(SUBSDIR . '/GenericList.class.php');
		require_once(SUBSDIR . '/News.subs.php');

		$context['page_title'] = $txt['admin_edit_news'];

		// Use the standard templates for showing this.
		$listOptions = array(
			'id' => 'news_lists',
			'get_items' => array(
				'function' => 'getNews',
			),
			'columns' => array(
				'news' => array(
					'header' => array(
						'value' => $txt['admin_edit_news'],
					),
					'data' => array(
						'function' => create_function('$news', '
							return \'<textarea class="" id="data_\' . $news[\'id\'] . \'" rows="3" name="news[]">\' . $news[\'unparsed\'] . \'</textarea>
								<br />
								<div id="preview_\' . $news[\'id\'] . \'"></div>\';
						'),
						'class' => 'newsarea',
					),
				),
				'preview' => array(
					'header' => array(
						'value' => $txt['preview'],
					),
					'data' => array(
						'function' => create_function('$news', '
							return \'<div id="box_preview_\' . $news[\'id\'] . \'">\' . $news[\'parsed\'] . \'</div>\';
						'),
						'class' => 'newspreview',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$news', '
							if (is_numeric($news[\'id\']))
								return \'<input type="checkbox" name="remove[]" value="\' . $news[\'id\'] . \'" class="input_check" />\';
							else
								return \'\';
						'),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=news;sa=editnews',
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'class' => 'submitbutton',
					'value' => '
					<input type="submit" name="save_items" value="' . $txt['save'] . '" class="right_submit" />
					<input type="submit" name="delete_selection" value="' . $txt['editnews_remove_selected'] . '" onclick="return confirm(\'' . $txt['editnews_remove_confirm'] . '\');" class="right_submit" />
					<span id="moreNewsItems_link" style="display: none;">
						<a class="linkbutton" href="javascript:void(0);" onclick="addAnotherNews(); return false;">' . $txt['editnews_clickadd'] . '</a>
					</span>',
				),
			),
			'javascript' => '
			document.getElementById(\'list_news_lists_last\').style.display = "none";
			document.getElementById("moreNewsItems_link").style.display = "";
			var last_preview = 0;
			var txt_preview = ' . javaScriptEscape($txt['preview']) . ';
			var txt_news_error_no_news = ' . javaScriptEscape($txt['news_error_no_news']) . ';

			$(document).ready(function () {
				$("div[id ^= \'preview_\']").each(function () {
					var preview_id = $(this).attr(\'id\').split(\'_\')[1];
					if (last_preview < preview_id)
						last_preview = preview_id;
					make_preview_btn(preview_id);
				});
			});
		',
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'news_lists';
	}

	/**
	 * This function allows a user to select the membergroups to send their mailing to.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=news;sa=mailingmembers.
	 * - Requires the send_mail permission.
	 * - Form is submitted to ?action=admin;area=news;mailingcompose.
	 *
	 * @uses the ManageNews template and email_members sub template.
	 */
	public function action_mailingmembers()
	{
		global $txt, $context;

		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/News.subs.php');

		// Setup the template
		$context['page_title'] = $txt['admin_newsletters'];
		$context['sub_template'] = 'email_members';
		loadJavascriptFile('suggest.js', array('defer' => true));

		// We need group data, including which groups we have and who is in them
		$allgroups = getBasicMembergroupData(array('all'), array(), null, true);
		$groups = $allgroups['groups'];

		// All of the members in post based and member based groups
		$pg = array();
		foreach ($allgroups['postgroups'] as $postgroup)
			$pg[] = $postgroup['id'];

		$mg = array();
		foreach ($allgroups['membergroups'] as $membergroup)
			$mg[] = $membergroup['id'];

		// How many are in each group
		$mem_groups = membersInGroups($pg, $mg, true, true);
		foreach ($mem_groups as $id_group => $member_count)
		{
			if (isset($groups[$id_group]['member_count']))
				$groups[$id_group]['member_count'] += $member_count;
			else
				$groups[$id_group]['member_count'] = $member_count;
		}

		// Generate the include and exclude group select lists for the template
		foreach ($groups as $group)
		{
			$groups[$group['id']]['status'] = 'on';
			$groups[$group['id']]['is_postgroup'] = in_array($group['id'], $pg);
		}

		$context['groups'] = array(
			'select_group' => $txt['admin_newsletters_select_groups'],
			'member_groups' => $groups,
		);

		foreach ($groups as $group)
			$groups[$group['id']]['status'] = 'off';

		$context['exclude_groups'] = array(
			'select_group' => $txt['admin_newsletters_exclude_groups'],
			'member_groups' => $groups,
		);

		// Needed if for the PM option in the mail to all
		$context['can_send_pm'] = allowedTo('pm_send');
	}

	/**
	 * Shows a form to edit a forum mailing and its recipients.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=news;sa=mailingcompose.
	 * - Requires the send_mail permission.
	 * - Form is submitted to ?action=admin;area=news;sa=mailingsend.
	 *
	 * @uses ManageNews template, email_members_compose sub-template.
	 */
	public function action_mailingcompose()
	{
		global $txt, $context;

		// Setup the template!
		$context['page_title'] = $txt['admin_newsletters'];
		$context['sub_template'] = 'email_members_compose';
		$context['subject'] = !empty($_POST['subject']) ? $_POST['subject'] : htmlspecialchars($context['forum_name'] . ': ' . $txt['subject'], ENT_COMPAT, 'UTF-8');
		$context['message'] = !empty($_POST['message']) ? $_POST['message'] : htmlspecialchars($txt['message'] . "\n\n" . replaceBasicActionUrl($txt['regards_team']) . "\n\n" . '{$board_url}', ENT_COMPAT, 'UTF-8');

		// Needed for the WYSIWYG editor.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Now create the editor.
		$editorOptions = array(
			'id' => 'message',
			'value' => $context['message'],
			'height' => '250px',
			'width' => '100%',
			'labels' => array(
				'post_button' => $txt['sendtopic_send'],
			),
			'preview_type' => 2,
		);
		create_control_richedit($editorOptions);

		if (isset($context['preview']))
		{
			require_once(SUBSDIR . '/Mail.subs.php');
			$context['recipients']['members'] = !empty($_POST['members']) ? explode(',', $_POST['members']) : array();
			$context['recipients']['exclude_members'] = !empty($_POST['exclude_members']) ? explode(',', $_POST['exclude_members']) : array();
			$context['recipients']['groups'] = !empty($_POST['groups']) ? explode(',', $_POST['groups']) : array();
			$context['recipients']['exclude_groups'] = !empty($_POST['exclude_groups']) ? explode(',', $_POST['exclude_groups']) : array();
			$context['recipients']['emails'] = !empty($_POST['emails']) ? explode(';', $_POST['emails']) : array();
			$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
			$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
			$context['max_id_member'] = !empty($_POST['max_id_member']) ? (int) $_POST['max_id_member'] : 0;
			$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
			$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';

			return prepareMailingForPreview();
		}

		// Start by finding any members!
		$toClean = array();
		if (!empty($_POST['members']))
			$toClean[] = 'members';

		if (!empty($_POST['exclude_members']))
			$toClean[] = 'exclude_members';

		if (!empty($toClean))
		{
			require_once(SUBSDIR . '/Auth.subs.php');
			foreach ($toClean as $type)
			{
				// Remove the quotes.
				$_POST[$type] = strtr((string) $_POST[$type], array('\\"' => '"'));

				preg_match_all('~"([^"]+)"~', $_POST[$type], $matches);
				$_POST[$type] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST[$type]))));

				foreach ($_POST[$type] as $index => $member)
				{
					if (strlen(trim($member)) > 0)
						$_POST[$type][$index] = Util::htmlspecialchars(Util::strtolower(trim($member)));
					else
						unset($_POST[$type][$index]);
				}

				// Find the members
				$_POST[$type] = implode(',', array_keys(findMembers($_POST[$type])));
			}
		}

		if (isset($_POST['member_list']) && is_array($_POST['member_list']))
		{
			$members = array();
			foreach ($_POST['member_list'] as $member_id)
				$members[] = (int) $member_id;

			$_POST['members'] = implode(',', $members);
		}

		if (isset($_POST['exclude_member_list']) && is_array($_POST['exclude_member_list']))
		{
			$members = array();
			foreach ($_POST['exclude_member_list'] as $member_id)
				$members[] = (int) $member_id;

			$_POST['exclude_members'] = implode(',', $members);
		}

		// Clean the other vars.
		$this->action_mailingsend(true);

		// We need a couple strings from the email template file
		loadLanguage('EmailTemplates');
		require_once(SUBSDIR . '/News.subs.php');

		// Get a list of all full banned users.  Use their Username and email to find them.
		// Only get the ones that can't login to turn off notification.
		$context['recipients']['exclude_members'] = excludeBannedMembers();

		// Did they select moderators - if so add them as specific members...
		if ((!empty($context['recipients']['groups']) && in_array(3, $context['recipients']['groups'])) || (!empty($context['recipients']['exclude_groups']) && in_array(3, $context['recipients']['exclude_groups'])))
		{
			$mods = getModerators();

			foreach ($mods as $row)
			{
				if (in_array(3, $context['recipients']))
					$context['recipients']['exclude_members'][] = $row['identifier'];
				else
					$context['recipients']['members'][] = $row['identifier'];
			}
		}

		require_once(SUBSDIR . '/Members.subs.php');

		// For progress bar!
		$context['total_emails'] = count($context['recipients']['emails']);
		$context['max_id_member'] = maxMemberID();

		// Clean up the arrays.
		$context['recipients']['members'] = array_unique($context['recipients']['members']);
		$context['recipients']['exclude_members'] = array_unique($context['recipients']['exclude_members']);
	}

	/**
	 * Handles the sending of the forum mailing in batches.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=news;sa=mailingsend
	 * - Requires the send_mail permission.
	 * - Redirects to itself when more batches need to be sent.
	 * - Redirects to ?action=admin after everything has been sent.
	 *
	 * @uses the ManageNews template and email_members_send sub template.
	 * @param bool $clean_only = false; if set, it will only clean the variables, put them in context, then return.
	 */
	public function action_mailingsend($clean_only = false)
	{
		global $txt, $context, $scripturl, $modSettings, $user_info;

		// A nice successful screen if you did it
		if (isset($_REQUEST['success']))
		{
			$context['sub_template'] = 'email_members_succeeded';
			loadTemplate('ManageNews');
			return;
		}

		// If just previewing we prepare a message and return it for viewing
		if (isset($_POST['preview']))
		{
			$context['preview'] = true;
			return $this->action_mailingcompose();
		}

		// How many to send at once? Quantity depends on whether we are queueing or not.
		// @todo Might need an interface? (used in Post.controller.php too with different limits)
		$num_at_once = empty($modSettings['mail_queue']) ? 60 : 1000;

		// If by PM's I suggest we half the above number.
		if (!empty($_POST['send_pm']))
			$num_at_once /= 2;

		checkSession();

		// Where are we actually to?
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
		$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
		$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
		$context['max_id_member'] = !empty($_POST['max_id_member']) ? (int) $_POST['max_id_member'] : 0;
		$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;
		$context['parse_html'] = !empty($_POST['parse_html']) ? 1 : 0;

		// Create our main context.
		$context['recipients'] = array(
			'groups' => array(),
			'exclude_groups' => array(),
			'members' => array(),
			'exclude_members' => array(),
			'emails' => array(),
		);

		// Have we any excluded members?
		if (!empty($_POST['exclude_members']))
		{
			$members = explode(',', $_POST['exclude_members']);
			foreach ($members as $member)
			{
				if ($member >= $context['start'])
					$context['recipients']['exclude_members'][] = (int) $member;
			}
		}

		// What about members we *must* do?
		if (!empty($_POST['members']))
		{
			$members = explode(',', $_POST['members']);
			foreach ($members as $member)
			{
				if ($member >= $context['start'])
					$context['recipients']['members'][] = (int) $member;
			}
		}

		// Cleaning groups is simple - although deal with both checkbox and commas.
		if (isset($_POST['groups']))
		{
			if (is_array($_POST['groups']))
			{
				foreach ($_POST['groups'] as $group => $dummy)
					$context['recipients']['groups'][] = (int) $group;
			}
			elseif (trim($_POST['groups']) != '')
			{
				$groups = explode(',', $_POST['groups']);
				foreach ($groups as $group)
					$context['recipients']['groups'][] = (int) $group;
			}
		}

		// Same for excluded groups
		if (isset($_POST['exclude_groups']))
		{
			if (is_array($_POST['exclude_groups']))
			{
				foreach ($_POST['exclude_groups'] as $group => $dummy)
					$context['recipients']['exclude_groups'][] = (int) $group;
			}
			elseif (trim($_POST['exclude_groups']) != '')
			{
				$groups = explode(',', $_POST['exclude_groups']);
				foreach ($groups as $group)
					$context['recipients']['exclude_groups'][] = (int) $group;
			}
		}

		// Finally - emails!
		if (!empty($_POST['emails']))
		{
			$addressed = array_unique(explode(';', strtr($_POST['emails'], array("\n" => ';', "\r" => ';', ',' => ';'))));
			foreach ($addressed as $curmem)
			{
				$curmem = trim($curmem);
				if ($curmem != '')
					$context['recipients']['emails'][$curmem] = $curmem;
			}
		}

		// If we're only cleaning drop out here.
		if ($clean_only)
			return;

		// Some functions we will need
		require_once(SUBSDIR . '/Mail.subs.php');
		if ($context['send_pm'])
			require_once(SUBSDIR . '/PersonalMessage.subs.php');

		// We are relying too much on writing to superglobals...
		$base_subject = !empty($_POST['subject']) ? $_POST['subject'] : '';
		$base_message = !empty($_POST['message']) ? $_POST['message'] : '';

		// Save the message and its subject in $context
		$context['subject'] = htmlspecialchars($base_subject, ENT_COMPAT, 'UTF-8');
		$context['message'] = htmlspecialchars($base_message, ENT_COMPAT, 'UTF-8');

		// Prepare the message for sending it as HTML
		if (!$context['send_pm'] && !empty($_POST['send_html']))
		{
			// Prepare the message for HTML.
			if (!empty($_POST['parse_html']))
				$base_message = str_replace(array("\n", '  '), array('<br />' . "\n", '&nbsp; '), $base_message);

			// This is here to prevent spam filters from tagging this as spam.
			if (preg_match('~\<html~i', $base_message) == 0)
			{
				if (preg_match('~\<body~i', $base_message) == 0)
					$base_message = '<html><head><title>' . $base_subject . '</title></head>' . "\n" . '<body>' . $base_message . '</body></html>';
				else
					$base_message = '<html>' . $base_message . '</html>';
			}
		}

		if (empty($base_message) || empty($base_subject))
		{
			$context['preview'] = true;
			return $this->action_mailingcompose();
		}

		// Use the default time format.
		$user_info['time_format'] = $modSettings['time_format'];

		$variables = array(
			'{$board_url}',
			'{$current_time}',
			'{$latest_member.link}',
			'{$latest_member.id}',
			'{$latest_member.name}'
		);

		// We might need this in a bit
		$cleanLatestMember = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($modSettings['latestRealName']) : $modSettings['latestRealName'];

		// Replace in all the standard things.
		$base_message = str_replace($variables,
			array(
				!empty($_POST['send_html']) ? '<a href="' . $scripturl . '">' . $scripturl . '</a>' : $scripturl,
				standardTime(forum_time(), false),
				!empty($_POST['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
				$modSettings['latestMember'],
				$cleanLatestMember
			), $base_message);

		$base_subject = str_replace($variables,
			array(
				$scripturl,
				standardTime(forum_time(), false),
				$modSettings['latestRealName'],
				$modSettings['latestMember'],
				$modSettings['latestRealName']
			), $base_subject);

		$from_member = array(
			'{$member.email}',
			'{$member.link}',
			'{$member.id}',
			'{$member.name}'
		);

		// If we still have emails, do them first!
		$i = 0;
		foreach ($context['recipients']['emails'] as $k => $email)
		{
			// Done as many as we can?
			if ($i >= $num_at_once)
				break;

			// Don't sent it twice!
			unset($context['recipients']['emails'][$k]);

			// Dammit - can't PM emails!
			if ($context['send_pm'])
				continue;

			$to_member = array(
				$email,
				!empty($_POST['send_html']) ? '<a href="mailto:' . $email . '">' . $email . '</a>' : $email,
				'??',
				$email
			);

			sendmail($email, str_replace($from_member, $to_member, $base_subject), str_replace($from_member, $to_member, $base_message), null, null, !empty($_POST['send_html']), 5);

			// Done another...
			$i++;
		}

		// Got some more to send this batch?
		$last_id_member = 0;
		if ($i < $num_at_once)
		{
			// Need to build quite a query!
			$sendQuery = '(';
			$sendParams = array();
			if (!empty($context['recipients']['groups']))
			{
				// Take the long route...
				$queryBuild = array();
				foreach ($context['recipients']['groups'] as $group)
				{
					$sendParams['group_' . $group] = $group;
					$queryBuild[] = 'mem.id_group = {int:group_' . $group . '}';
					if (!empty($group))
					{
						$queryBuild[] = 'FIND_IN_SET({int:group_' . $group . '}, mem.additional_groups) != 0';
						$queryBuild[] = 'mem.id_post_group = {int:group_' . $group . '}';
					}
				}

				if (!empty($queryBuild))
					$sendQuery .= implode(' OR ', $queryBuild);
			}

			if (!empty($context['recipients']['members']))
			{
				$sendQuery .= ($sendQuery == '(' ? '' : ' OR ') . 'mem.id_member IN ({array_int:members})';
				$sendParams['members'] = $context['recipients']['members'];
			}

			$sendQuery .= ')';

			// If we've not got a query then we must be done!
			if ($sendQuery == '()')
				redirectexit('action=admin');

			// Anything to exclude?
			if (!empty($context['recipients']['exclude_groups']) && in_array(0, $context['recipients']['exclude_groups']))
				$sendQuery .= ' AND mem.id_group != {int:regular_group}';

			if (!empty($context['recipients']['exclude_members']))
			{
				$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
				$sendParams['exclude_members'] = $context['recipients']['exclude_members'];
			}

			// Force them to have it?
			if (empty($context['email_force']))
				$sendQuery .= ' AND mem.notify_announcements = {int:notify_announcements}';

			require_once(SUBSDIR . '/News.subs.php');

			// Get the smelly people - note we respect the id_member range as it gives us a quicker query.
			$recipients = getNewsletterRecipients($sendQuery, $sendParams, $context['start'], $num_at_once, $i);

			foreach ($recipients as $row)
			{
				$last_id_member = $row['id_member'];

				// What groups are we looking at here?
				if (empty($row['additional_groups']))
					$groups = array($row['id_group'], $row['id_post_group']);
				else
					$groups = array_merge(
						array($row['id_group'], $row['id_post_group']),
						explode(',', $row['additional_groups'])
					);

				// Excluded groups?
				if (array_intersect($groups, $context['recipients']['exclude_groups']))
					continue;

				// We might need this
				$cleanMemberName = empty($_POST['send_html']) || $context['send_pm'] ? un_htmlspecialchars($row['real_name']) : $row['real_name'];

				// Replace the member-dependant variables
				$message = str_replace($from_member,
					array(
						$row['email_address'],
						!empty($_POST['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $cleanMemberName . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $row['id_member'] . ']' . $cleanMemberName . '[/url]' : $cleanMemberName),
						$row['id_member'],
						$cleanMemberName,
					), $base_message);

				$subject = str_replace($from_member,
					array(
						$row['email_address'],
						$row['real_name'],
						$row['id_member'],
						$row['real_name'],
					), $base_subject);

				// Send the actual email - or a PM!
				if (!$context['send_pm'])
					sendmail($row['email_address'], $subject, $message, null, null, !empty($_POST['send_html']), 5);
				else
					sendpm(array('to' => array($row['id_member']), 'bcc' => array()), $subject, $message);
			}
		}

		// If used our batch assume we still have a member.
		if ($i >= $num_at_once)
			$last_id_member = $context['start'];
		// Or we didn't have one in range?
		elseif (empty($last_id_member) && $context['start'] + $num_at_once < $context['max_id_member'])
			$last_id_member = $context['start'] + $num_at_once;
		// If we have no id_member then we're done.
		elseif (empty($last_id_member) && empty($context['recipients']['emails']))
		{
			// Log this into the admin log.
			logAction('newsletter', array(), 'admin');
			redirectexit('action=admin;area=news;sa=mailingsend;success');
		}

		$context['start'] = $last_id_member;

		// Working out progress is a black art of sorts.
		$percentEmails = $context['total_emails'] == 0 ? 0 : ((count($context['recipients']['emails']) / $context['total_emails']) * ($context['total_emails'] / ($context['total_emails'] + $context['max_id_member'])));
		$percentMembers = ($context['start'] / $context['max_id_member']) * ($context['max_id_member'] / ($context['total_emails'] + $context['max_id_member']));
		$context['percentage_done'] = round(($percentEmails + $percentMembers) * 100, 2);

		$context['page_title'] = $txt['admin_newsletters'];
		$context['sub_template'] = 'email_members_send';
	}

	/**
	 * Set general news and newsletter settings and permissions.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=news;sa=settings.
	 * - Requires the forum_admin permission.
	 *
	 * @uses ManageNews template, news_settings sub-template.
	 */
	public function action_newsSettings_display()
	{
		global $context, $txt, $scripturl;

		// Initialize the form
		$this->_initNewsSettingsForm();

		$config_vars = $this->_newsSettings->settings();

		// Add some javascript at the bottom...
		addInlineJavascript('
			document.getElementById("xmlnews_maxlen").disabled = !document.getElementById("xmlnews_enable").checked;
			document.getElementById("xmlnews_limit").disabled = !document.getElementById("xmlnews_enable").checked;', true);

		$context['page_title'] = $txt['admin_edit_news'] . ' - ' . $txt['settings'];
		$context['sub_template'] = 'show_settings';

		// Wrap it all up nice and warm...
		$context['post_url'] = $scripturl . '?action=admin;area=news;save;sa=settings';
		$context['permissions_excluded'] = array(-1);

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession();

			call_integration_hook('integrate_save_news_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=news;sa=settings');
		}

		// We need this for the in-line permissions
		createToken('admin-mp');

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize the news settings screen in admin area for the forum.
	 */
	private function _initNewsSettingsForm()
	{
		// We're working with them settings here.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_newsSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_newsSettings->settings($config_vars);
	}

	/**
	 * Get the settings of the forum related to news.
	 */
	private function _settings()
	{
		global $txt;

		$config_vars = array(
			array('title', 'settings'),
				// Inline permissions.
				array('permissions', 'edit_news', 'help' => ''),
				array('permissions', 'send_mail'),
			'',
				// Just the remaining settings.
				array('check', 'xmlnews_enable', 'onclick' => 'document.getElementById(\'xmlnews_maxlen\').disabled = !this.checked;document.getElementById(\'xmlnews_limit\').disabled = !this.checked;'),
				array('int', 'xmlnews_maxlen', 'subtext' => $txt['xmlnews_maxlen_note'], 10),
				array('int', 'xmlnews_limit', 'subtext' => $txt['xmlnews_limit_note'], 10),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_news_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}