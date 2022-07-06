<?php

/**
 * Handles all news and newsletter functions for the site
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;
use ElkArte\Util;

/**
 * ManageNews controller, for news administration screens.
 *
 * @package News
 */
class ManageNews extends AbstractController
{
	/**
	 * Members specifically being included in a newsletter
	 *
	 * @var array
	 */
	protected $_members = array();

	/**
	 * Members specifically being excluded from a newsletter
	 *
	 * @var array
	 */
	protected $_exclude_members = array();

	/**
	 * The news dispatcher / delegator
	 *
	 * What it does:
	 *
	 * - This is the entrance point for all News and Newsletter screens.
	 * - Called by ?action=admin;area=news.
	 * - It does the permission checks, and calls the appropriate function
	 * based on the requested sub-action.
	 *
	 * @event integrate_sa_manage_news used to add new subactions
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		theme()->getTemplates()->load('ManageNews');

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
				'editnews' => array(),
				'mailingmembers' => array(
					'description' => $txt['news_mailing_desc'],
				),
				'settings' => array(
					'description' => $txt['news_settings_desc'],
				),
			),
		);

		// Give integration its shot via integrate_sa_manage_news
		$subAction = $action->initialize($subActions, (allowedTo('edit_news') ? 'editnews' : (allowedTo('send_mail') ? 'mailingmembers' : 'settings')));

		// Some bits for the template
		$context['page_title'] = $txt['news_title'];
		$context['sub_action'] = $subAction;

		// Force the right area...
		if (substr($subAction, 0, 7) === 'mailing')
		{
			$context[$context['admin_menu_name']]['current_subsection'] = 'mailingmembers';
		}

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Let the administrator(s) edit the news items for the forum.
	 *
	 * What it does:
	 *
	 * - It writes an entry into the moderation log.
	 * - This function uses the edit_news administration area.
	 * - Called by ?action=admin;area=news.
	 * - Requires the edit_news permission.
	 * - Can be accessed with ?action=admin;sa=editnews.
	 *
	 * @event integrate_list_news_lists
	 */
	public function action_editnews()
	{
		global $txt, $modSettings, $context;

		require_once(SUBSDIR . '/Post.subs.php');

		// The 'remove selected' button was pressed.
		if (!empty($this->_req->post->delete_selection) && !empty($this->_req->post->remove))
		{
			checkSession();

			// Store the news temporarily in this array.
			$temp_news = explode("\n", $modSettings['news']);

			// Remove the items that were selected.
			foreach ($temp_news as $i => $news)
			{
				if (in_array($i, $this->_req->post->remove))
				{
					unset($temp_news[$i]);
				}
			}

			// Update the database.
			updateSettings(array('news' => implode("\n", $temp_news)));

			logAction('news');
		}
		// The 'Save' button was pressed.
		elseif (!empty($this->_req->post->save_items))
		{
			checkSession();

			foreach ($this->_req->post->news as $i => $news)
			{
				if (trim($news) === '')
				{
					unset($this->_req->post->news[$i]);
				}
				else
				{
					$this->_req->post->news[$i] = Util::htmlspecialchars($this->_req->post->news[$i], ENT_QUOTES);
					preparsecode($this->_req->post->news[$i]);
				}
			}

			// Send the new news to the database.
			updateSettings(array('news' => implode("\n", $this->_req->post->news)));

			// Log this into the moderation log.
			logAction('news');
		}

		// We're going to want this for making our list.
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
						'function' => function ($news) {
							return '<textarea class="" id="data_' . $news['id'] . '" rows="3" name="news[]">' . $news['unparsed'] . '</textarea>
								<br />
								<div id="preview_' . $news['id'] . '"></div>';
						},
						'class' => 'newsarea',
					),
				),
				'preview' => array(
					'header' => array(
						'value' => $txt['preview'],
					),
					'data' => array(
						'function' => function ($news) {
							return '<div id="box_preview_' . $news['id'] . '">' . $news['parsed'] . '</div>';
						},
						'class' => 'newspreview',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($news) {
							if (is_numeric($news['id']))
							{
								return '<input type="checkbox" name="remove[]" value="' . $news['id'] . '" class="input_check" />';
							}
							else
							{
								return '';
							}
						},
						'style' => 'vertical-align: top',
					),
				),
			),
			'form' => array(
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'news', 'sa' => 'editnews']),
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'class' => 'submitbutton',
					'value' => '
					<input type="submit" name="save_items" value="' . $txt['save'] . '" />
					<input type="submit" name="delete_selection" value="' . $txt['editnews_remove_selected'] . '" onclick="return confirm(\'' . $txt['editnews_remove_confirm'] . '\');" />
					<span id="moreNewsItems_link" class="hide">
						<a class="linkbutton" href="javascript:void(0);" onclick="addAnotherNews(); return false;">' . $txt['editnews_clickadd'] . '</a>
					</span>',
				),
			),
			'javascript' => '
			document.getElementById(\'list_news_lists_last\').style.display = "none";
			document.getElementById("moreNewsItems_link").style.display = "inline";
			var last_preview = 0,
			    txt_preview = ' . JavaScriptEscape($txt['preview']) . ',
			    txt_news_error_no_news = ' . JavaScriptEscape($txt['news_error_no_news']) . ';

			$(function() {
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
	 *
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
		loadJavascriptFile('suggest.js');

		// We need group data, including which groups we have and who is in them
		$allgroups = getBasicMembergroupData(array('all'), array(), null, true);
		$groups = $allgroups['groups'];

		// All of the members in post based and member based groups
		$pg = array();
		foreach ($allgroups['postgroups'] as $postgroup)
		{
			$pg[] = $postgroup['id'];
		}

		$mg = array();
		foreach ($allgroups['membergroups'] as $membergroup)
		{
			$mg[] = $membergroup['id'];
		}

		// How many are in each group
		$mem_groups = membersInGroups($pg, $mg, true, true);
		foreach ($mem_groups as $id_group => $member_count)
		{
			if (isset($groups[$id_group]['member_count']))
			{
				$groups[$id_group]['member_count'] += $member_count;
			}
			else
			{
				$groups[$id_group]['member_count'] = $member_count;
			}
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
		{
			$groups[$group['id']]['status'] = 'off';
		}

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
	 *
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
		$context['subject'] = !empty($this->_req->post->subject) ? $this->_req->post->subject : $context['forum_name'] . ': ' . htmlspecialchars($txt['subject'], ENT_COMPAT, 'UTF-8');
		$context['message'] = !empty($this->_req->post->message) ? $this->_req->post->message : htmlspecialchars($txt['message'] . "\n\n" . replaceBasicActionUrl($txt['regards_team']) . "\n\n" . '{$board_url}', ENT_COMPAT, 'UTF-8');

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
			$context['recipients']['members'] = !empty($this->_req->post->members) ? explode(',', $this->_req->post->members) : array();
			$context['recipients']['exclude_members'] = !empty($this->_req->post->exclude_members) ? explode(',', $this->_req->post->exclude_members) : array();
			$context['recipients']['groups'] = !empty($this->_req->post->groups) ? explode(',', $this->_req->post->groups) : array();
			$context['recipients']['exclude_groups'] = !empty($this->_req->post->exclude_groups) ? explode(',', $this->_req->post->exclude_groups) : array();
			$context['recipients']['emails'] = !empty($this->_req->post->emails) ? explode(';', $this->_req->post->emails) : array();
			$context['email_force'] = $this->_req->getPost('email_force', 'isset', false);
			$context['total_emails'] = $this->_req->getPost('total_emails', 'intval', 0);
			$context['max_id_member'] = $this->_req->getPost('max_id_member', 'intval', 0);
			$context['send_pm'] = $this->_req->getPost('send_pm', 'isset', false);
			$context['send_html'] = $this->_req->getPost('send_html', 'isset', false);

			prepareMailingForPreview();

			return null;
		}

		// Start by finding any manually entered members!
		$this->_toClean();

		// Add in any members chosen from the auto select dropdown.
		$this->_toAddOrExclude();

		// Clean the other vars.
		$this->action_mailingsend(true);

		// We need a couple strings from the email template file
		Txt::load('EmailTemplates');
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
				{
					$context['recipients']['exclude_members'][] = $row;
				}
				else
				{
					$context['recipients']['members'][] = $row;
				}
			}
		}

		require_once(SUBSDIR . '/Members.subs.php');

		// For progress bar!
		$context['total_emails'] = count($context['recipients']['emails']);
		$context['max_id_member'] = maxMemberID();

		// Make sure to fully load the array with the form choices
		$context['recipients']['members'] = array_merge($this->_members, $context['recipients']['members']);
		$context['recipients']['exclude_members'] = array_merge($this->_exclude_members, $context['recipients']['exclude_members']);

		// Clean up the arrays.
		$context['recipients']['members'] = array_unique($context['recipients']['members']);
		$context['recipients']['exclude_members'] = array_unique($context['recipients']['exclude_members']);

		return true;
	}

	/**
	 * If they did not use auto select function on the include/exclude members then
	 * we need to look them up from the supplied "one","two" string
	 */
	private function _toClean()
	{
		$toClean = array();
		if (!empty($this->_req->post->members))
		{
			$toClean['_members'] = 'members';
		}

		if (!empty($this->_req->post->exclude_members))
		{
			$toClean['_exclude_members'] = 'exclude_members';
		}

		// Manual entries found?
		if (!empty($toClean))
		{
			require_once(SUBSDIR . '/Auth.subs.php');
			foreach ($toClean as $key => $type)
			{
				// Remove the quotes.
				$temp = strtr((string) $this->_req->post->{$type}, array('\\"' => '"'));

				// Break it up in to an array for processing
				preg_match_all('~"([^"]+)"~', $this->_req->post->{$type}, $matches);
				$temp = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $temp))));

				// Clean the valid ones, drop the mangled ones
				foreach ($temp as $index => $member)
				{
					if (trim($member) !== '')
					{
						$temp[$index] = Util::htmlspecialchars(Util::strtolower(trim($member)));
					}
					else
					{
						unset($temp[$index]);
					}
				}

				// Find the members
				$this->{$key} = array_keys(findMembers($temp));
			}
		}
	}

	/**
	 * Members may have been chosen via autoselection pulldown for both Add or Exclude
	 * this will process them and combine them to any manually added ones.
	 */
	private function _toAddOrExclude()
	{
		// Members selected (via auto select) to specifically get the newsletter
		if (is_array($this->_req->getPost('member_list')))
		{
			$members = array();
			foreach ($this->_req->post->member_list as $member_id)
			{
				$members[] = (int) $member_id;
			}

			$this->_members = array_unique(array_merge($this->_members, $members));
		}

		// Members selected (via auto select) to specifically not get the newsletter
		if (is_array($this->_req->getPost('exclude_member_list')))
		{
			$members = array();
			foreach ($this->_req->post->exclude_member_list as $member_id)
			{
				$members[] = (int) $member_id;
			}

			$this->_exclude_members = array_unique(array_merge($this->_exclude_members, $members));
		}
	}

	/**
	 * Handles the sending of the forum mailing in batches.
	 *
	 * What it does:
	 *
	 * - Called by ?action=admin;area=news;sa=mailingsend
	 * - Requires the send_mail permission.
	 * - Redirects to itself when more batches need to be sent.
	 * - Redirects to ?action=admin after everything has been sent.
	 *
	 * @param bool $clean_only = false; if set, it will only clean the variables, put them in context, then return.
	 *
	 * @return null|void
	 * @throws \ElkArte\Exceptions\Exception
	 * @uses the ManageNews template and email_members_send sub template.
	 *
	 */
	public function action_mailingsend($clean_only = false)
	{
		global $txt, $context, $scripturl, $modSettings;

		// A nice successful screen if you did it
		if (isset($this->_req->query->success))
		{
			$context['sub_template'] = 'email_members_succeeded';
			theme()->getTemplates()->load('ManageNews');

			return null;
		}

		// If just previewing we prepare a message and return it for viewing
		if (isset($this->_req->post->preview))
		{
			$context['preview'] = true;

			$this->action_mailingcompose();
			return;
		}

		// How many to send at once? Quantity depends on whether we are queueing or not.
		// @todo Might need an interface? (used in Post.controller.php too with different limits)
		$num_at_once = empty($modSettings['mail_queue']) ? 60 : 1000;

		// If by PM's I suggest we half the above number.
		if (!empty($this->_req->post->send_pm))
		{
			$num_at_once /= 2;
		}

		checkSession();

		// Where are we actually to?
		$context['start'] = $this->_req->getPost('start', 'intval', 0);
		$context['email_force'] = $this->_req->getPost('email_force', 'isset', false);
		$context['total_emails'] = $this->_req->getPost('total_emails', 'intval', 0);
		$context['max_id_member'] = $this->_req->getPost('max_id_member', 'intval', 0);
		$context['send_pm'] = $this->_req->getPost('send_pm', 'isset', false);
		$context['send_html'] = $this->_req->getPost('send_html', 'isset', false);
		$context['parse_html'] = $this->_req->getPost('parse_html', 'isset', false);

		// Create our main context.
		$context['recipients'] = array(
			'groups' => array(),
			'exclude_groups' => array(),
			'members' => array(),
			'exclude_members' => array(),
			'emails' => array(),
		);

		// Have we any excluded members?
		if (!empty($this->_req->post->exclude_members))
		{
			$members = explode(',', $this->_req->post->exclude_members);
			foreach ($members as $member)
			{
				if ($member >= $context['start'])
				{
					$context['recipients']['exclude_members'][] = (int) $member;
				}
			}
		}

		// What about members we *must* do?
		if (!empty($this->_req->post->members))
		{
			$members = explode(',', $this->_req->post->members);
			foreach ($members as $member)
			{
				if ($member >= $context['start'])
				{
					$context['recipients']['members'][] = (int) $member;
				}
			}
		}

		// Cleaning groups is simple - although deal with both checkbox and commas.
		if (is_array($this->_req->getPost('groups')))
		{
			foreach ($this->_req->post->groups as $group => $dummy)
			{
				$context['recipients']['groups'][] = (int) $group;
			}
		}
		elseif ($this->_req->getPost('groups', 'trim', '') !== '')
		{
			$groups = explode(',', $this->_req->post->groups);
			foreach ($groups as $group)
			{
				$context['recipients']['groups'][] = (int) $group;
			}
		}

		// Same for excluded groups
		if (is_array($this->_req->getPost('exclude_groups')))
		{
			foreach ($this->_req->post->exclude_groups as $group => $dummy)
			{
				$context['recipients']['exclude_groups'][] = (int) $group;
			}
		}
		elseif ($this->_req->getPost('exclude_groups', 'trim', '') !== '')
		{
			$groups = explode(',', $this->_req->post->exclude_groups);
			foreach ($groups as $group)
			{
				$context['recipients']['exclude_groups'][] = (int) $group;
			}
		}

		// Finally - emails!
		if (!empty($this->_req->post->emails))
		{
			$addressed = array_unique(explode(';', strtr($this->_req->post->emails, array("\n" => ';', "\r" => ';', ',' => ';'))));
			foreach ($addressed as $curmem)
			{
				$curmem = trim($curmem);
				if ($curmem !== '')
				{
					$context['recipients']['emails'][$curmem] = $curmem;
				}
			}
		}

		// If we're only cleaning drop out here.
		if ($clean_only)
		{
			return null;
		}

		// Some functions we will need
		require_once(SUBSDIR . '/Mail.subs.php');
		if ($context['send_pm'])
		{
			require_once(SUBSDIR . '/PersonalMessage.subs.php');
		}

		$base_subject = $this->_req->getPost('subject', 'trim|strval', '');
		$base_message = $this->_req->getPost('message', 'strval', '');

		// Save the message and its subject in $context
		$context['subject'] = htmlspecialchars($base_subject, ENT_COMPAT, 'UTF-8');
		$context['message'] = htmlspecialchars($base_message, ENT_COMPAT, 'UTF-8');

		// Prepare the message for sending it as HTML
		if (!$context['send_pm'] && !empty($context['send_html']))
		{
			// Prepare the message for HTML.
			if (!empty($context['parse_html']))
			{
				$base_message = str_replace(array("\n", '  '), array('<br />' . "\n", '&nbsp; '), $base_message);
			}

			// This is here to prevent spam filters from tagging this as spam.
			if (preg_match('~<html~i', $base_message) == 0)
			{
				if (preg_match('~<body~i', $base_message) == 0)
				{
					$base_message = '<html><head><title>' . $base_subject . '</title></head>' . "\n" . '<body>' . $base_message . '</body></html>';
				}
				else
				{
					$base_message = '<html>' . $base_message . '</html>';
				}
			}
		}

		if (empty($base_message) || empty($base_subject))
		{
			$context['preview'] = true;

			$this->action_mailingcompose();
			return;
		}

		// Use the default time format.
		$this->user->time_format = $modSettings['time_format'];

		$variables = array(
			'{$board_url}',
			'{$current_time}',
			'{$latest_member.link}',
			'{$latest_member.id}',
			'{$latest_member.name}'
		);

		// We might need this in a bit
		$cleanLatestMember = empty($context['send_html']) || $context['send_pm'] ? un_htmlspecialchars($modSettings['latestRealName']) : $modSettings['latestRealName'];

		// Replace in all the standard things.
		$base_message = str_replace($variables,
			array(
				!empty($context['send_html']) ? '<a href="' . $scripturl . '">' . $scripturl . '</a>' : $scripturl,
				standardTime(forum_time(), false),
				!empty($context['send_html']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $modSettings['latestMember'], 'name' => $cleanLatestMember]) . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . getUrl('profile', ['action' => 'profile', 'u' => $modSettings['latestMember'], 'name' => $cleanLatestMember]) . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
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
			{
				break;
			}

			// Don't sent it twice!
			unset($context['recipients']['emails'][$k]);

			// Dammit - can't PM emails!
			if ($context['send_pm'])
			{
				continue;
			}

			$to_member = array(
				$email,
				!empty($context['send_html']) ? '<a href="mailto:' . $email . '">' . $email . '</a>' : $email,
				'??',
				$email
			);

			sendmail($email, str_replace($from_member, $to_member, $base_subject), str_replace($from_member, $to_member, $base_message), null, null, !empty($context['send_html']), 5);

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
				{
					$sendQuery .= implode(' OR ', $queryBuild);
				}
			}

			if (!empty($context['recipients']['members']))
			{
				$sendQuery .= ($sendQuery === '(' ? '' : ' OR ') . 'mem.id_member IN ({array_int:members})';
				$sendParams['members'] = $context['recipients']['members'];
			}

			$sendQuery .= ')';

			// If we've not got a query then we must be done!
			if ($sendQuery === '()')
			{
				redirectexit('action=admin');
			}

			// Anything to exclude?
			if (!empty($context['recipients']['exclude_groups']) && in_array(0, $context['recipients']['exclude_groups']))
			{
				$sendQuery .= ' AND mem.id_group != {int:regular_group}';
			}

			if (!empty($context['recipients']['exclude_members']))
			{
				$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
				$sendParams['exclude_members'] = $context['recipients']['exclude_members'];
			}

			// Force them to have it?
			if (empty($context['email_force']))
			{
				$sendQuery .= ' AND mem.notify_announcements = {int:notify_announcements}';
			}

			require_once(SUBSDIR . '/News.subs.php');

			// Get the smelly people - note we respect the id_member range as it gives us a quicker query.
			$recipients = getNewsletterRecipients($sendQuery, $sendParams, $context['start'], $num_at_once, $i);

			foreach ($recipients as $row)
			{
				$last_id_member = $row['id_member'];

				// What groups are we looking at here?
				$groups = array_merge([$row['id_group'], $row['id_post_group']], (empty($row['additional_groups']) ? [] : explode(',', $row['additional_groups'])));

				// Excluded groups?
				if (array_intersect($groups, $context['recipients']['exclude_groups']) !== [])
				{
					continue;
				}

				// We might need this
				$cleanMemberName = empty($context['send_html']) || $context['send_pm'] ? un_htmlspecialchars($row['real_name']) : $row['real_name'];

				// Replace the member-dependant variables
				$message = str_replace($from_member,
					array(
						$row['email_address'],
						!empty($context['send_html']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_member'], 'name' => $cleanMemberName]) . '">' . $cleanMemberName . '</a>' : ($context['send_pm'] ? '[url=' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_member'], 'name' => $cleanMemberName]) . ']' . $cleanMemberName . '[/url]' : $cleanMemberName),
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
				{
					sendmail($row['email_address'], $subject, $message, null, null, !empty($context['send_html']), 5);
				}
				else
				{
					sendpm(array('to' => array($row['id_member']), 'bcc' => array()), $subject, $message);
				}
			}
		}

		// If used our batch assume we still have a member.
		if ($i >= $num_at_once)
		{
			$last_id_member = $context['start'];
		}
		// Or we didn't have one in range?
		elseif (empty($last_id_member) && $context['start'] + $num_at_once < $context['max_id_member'])
		{
			$last_id_member = $context['start'] + $num_at_once;
		}
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
	 *
	 * - Called by ?action=admin;area=news;sa=settings.
	 * - Requires the forum_admin permission.
	 *
	 * @event integrate_save_news_settings save new news settings
	 * @uses ManageNews template, news_settings sub-template.
	 */
	public function action_newsSettings_display()
	{
		global $context, $txt;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Add some javascript at the bottom...
		theme()->addInlineJavascript('
			document.getElementById("xmlnews_maxle").disabled = !document.getElementById("xmlnews_enable").checked;
			document.getElementById("xmlnews_limit").disabled = !document.getElementById("xmlnews_enable").checked;', true);

		// Wrap it all up nice and warm...
		$context['page_title'] = $txt['admin_edit_news'] . ' - ' . $txt['settings'];
		$context['sub_template'] = 'show_settings';
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'news', 'save', 'sa' => 'settings']);

		// Saving the settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_news_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=news;sa=settings');
		}

		$settingsForm->prepare();
	}

	/**
	 * Get the settings of the forum related to news.
	 *
	 * @event integrate_modify_news_settings add new news settings
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
		call_integration_hook('integrate_modify_news_settings');

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
