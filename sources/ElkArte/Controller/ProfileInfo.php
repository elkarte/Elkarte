<?php

/**
 * Handles the retrieving and display of a users posts, attachments, stats, permissions
 * warnings and the like
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

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\MembersList;
use ElkArte\MessagesDelete;
use ElkArte\Util;

/**
 * Access all profile summary areas for a user including overall summary,
 * post listing, attachment listing, user statistics user permissions, user warnings
 */
class ProfileInfo extends AbstractController
{
	/**
	 * Member id for the profile being worked with
	 *
	 * @var int
	 */
	private $_memID = 0;

	/**
	 * The \ElkArte\Member object is stored here to avoid some global
	 *
	 * @var \ElkArte\Member
	 */
	private $_profile = null;

	/**
	 * Holds the current summary tabs to load
	 *
	 * @var array
	 */
	private $_summary_areas;

	/**
	 * Called before all other methods when coming from the dispatcher or
	 * action class.
	 *
	 * - If you initiate the class outside of those methods, call this method.
	 * or setup the class yourself or fall awaits.
	 */
	public function pre_dispatch()
	{
		global $context;

		require_once(SUBSDIR . '/Profile.subs.php');

		$this->_memID = currentMemberID();
		$this->_profile = MembersList::get($this->_memID);

		if (!isset($context['user']['is_owner']))
		{
			$context['user']['is_owner'] = (int) $this->_memID === (int) $this->user->id;
		}

		theme()->getTemplates()->loadLanguageFile('Profile');
	}

	/**
	 * Intended as entry point which delegates to methods in this class...
	 *
	 * - But here, today, for now, the methods are mainly called from other places
	 * like menu picks and the like.
	 */
	public function action_index()
	{
		global $context;

		// What do we do, do you even know what you do?
		$subActions = array(
			'buddies' => array($this, 'action_profile_buddies'),
			'recent' => array($this, 'action_profile_recent'),
			'summary' => array('controller' => '\\ElkArte\\Controller\\Profile', 'function' => 'action_index'),
		);

		// Action control
		$action = new Action('profile_info');

		// By default we want the summary
		$subAction = $action->initialize($subActions, 'summary');

		// Final bits
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * View the user profile summary.
	 *
	 * @uses ProfileInfo template
	 */
	public function action_summary()
	{
		global $context, $modSettings, $scripturl;

		// Attempt to load the member's profile data.
		if ($this->_profile->isEmpty())
		{
			throw new Exception('not_a_user', false);
		}
		$this->_profile->loadContext();

		theme()->getTemplates()->load('ProfileInfo');
		theme()->getTemplates()->loadLanguageFile('Profile');

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?action=profile;u=' . $this->_memID;

		// Are there things we don't show?
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

		// Menu tab
		$context[$context['profile_menu_name']]['tab_data'] = array();

		// Profile summary tabs, like Summary, Recent, Buddies
		$this->_register_summarytabs();

		// Load in everything we know about the user to preload the summary tab
		$this->_define_user_values();
		$this->_load_summary();

		// To finish this off, custom profile fields.
		loadCustomFields($this->_memID);

		// To make tabs work, we need jQueryUI
		$modSettings['jquery_include_ui'] = true;
		theme()->addInlineJavascript('start_tabs();', true);
		loadCSSFile('jquery.ui.tabs.css');
	}

	/**
	 * Prepares the tabs for the profile summary page
	 *
	 * What it does:
	 *
	 * - Tab information for use in the summary page
	 * - Each tab template defines a div, the value of which are the template(s) to load in that div
	 * - array(array(1, 2), array(3, 4)) <div>template 1, template 2</div><div>template 3 template 4</div>
	 * - Templates are named template_profile_block_YOURNAME
	 * - Tabs with href defined will not preload/create any page divs but instead be loaded via ajax
	 */
	private function _register_summarytabs()
	{
		global $txt, $context, $modSettings, $scripturl;

		$context['summarytabs'] = array(
			'summary' => array(
				'name' => $txt['summary'],
				'templates' => array(
					array('summary', 'user_info'),
					array('contact', 'other_info'),
					array('user_customprofileinfo', 'moderation'),
				),
				'active' => true,
			),
			'recent' => array(
				'name' => $txt['profile_recent_activity'],
				'templates' => array('posts', 'topics', 'attachments'),
				'active' => true,
				'href' => $scripturl . '?action=profileInfo;sa=recent;xml;u=' . $this->_memID . ';' . $context['session_var'] . '=' . $context['session_id'],
			),
			'buddies' => array(
				'name' => $txt['buddies'],
				'templates' => array('buddies'),
				'active' => !empty($modSettings['enable_buddylist']) && $context['user']['is_owner'],
				'href' => $scripturl . '?action=profileInfo;sa=buddies;xml;u=' . $this->_memID . ';' . $context['session_var'] . '=' . $context['session_id'],
			)
		);

		// Let addons add or remove to the tabs array
		call_integration_hook('integrate_profile_summary', array($this->_memID));

		// Go forward with whats left after integration adds or removes
		$summary_areas = '';
		foreach ($context['summarytabs'] as $id => $tab)
		{
			// If the tab is active we add it
			if ($tab['active'] !== true)
			{
				unset($context['summarytabs'][$id]);
			}
			else
			{
				// All the active templates, used to prevent processing data we don't need
				foreach ($tab['templates'] as $template)
				{
					$summary_areas .= is_array($template) ? implode(',', $template) : ',' . $template;
				}
			}
		}

		$this->_summary_areas = explode(',', $summary_areas);
	}

	/**
	 * Sets in to context what we know about a given user
	 *
	 * - Defines various user permissions for profile views
	 */
	private function _define_user_values()
	{
		global $context, $modSettings, $txt;

		// Set up the context stuff and load the user.
		$context += array(
			'page_title' => sprintf($txt['profile_of_username'], $this->_profile['name']),
			'can_send_pm' => allowedTo('pm_send'),
			'can_send_email' => allowedTo('send_email_to_members'),
			'can_have_buddy' => allowedTo('profile_identity_own') && !empty($modSettings['enable_buddylist']),
			'can_issue_warning' => featureEnabled('w') && allowedTo('issue_warning') && !empty($modSettings['warning_enable']),
			'can_view_warning' => featureEnabled('w') && (allowedTo('issue_warning') && !$context['user']['is_owner']) || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $context['user']['is_owner']))
		);

		// @critical: potential problem here
		$context['member'] = $this->_profile;
		$context['member']->loadContext();
		$context['member']['id'] = $this->_memID;

		// Is the signature even enabled on this forum?
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
	}

	/**
	 * Loads the information needed to create the profile summary view
	 */
	private function _load_summary()
	{
		// Load all areas of interest in to context for template use
		$this->_determine_warning_level();
		$this->_determine_posts_per_day();
		$this->_determine_age_birth();
		$this->_determine_member_ip();
		$this->_determine_member_action();
		$this->_determine_member_activation();
		$this->_determine_member_bans();
	}

	/**
	 * If they have been disciplined, show the warning level for those that can see it.
	 */
	private function _determine_warning_level()
	{
		global $modSettings, $context, $txt;

		// See if they have broken any warning levels...
		if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $context['member']['warning'])
		{
			$context['warning_status'] = $txt['profile_warning_is_muted'];
		}
		elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $context['member']['warning'])
		{
			$context['warning_status'] = $txt['profile_warning_is_moderation'];
		}
		elseif (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $context['member']['warning'])
		{
			$context['warning_status'] = $txt['profile_warning_is_watch'];
		}
	}

	/**
	 * Gives there spam level as a posts per day kind of statistic
	 */
	private function _determine_posts_per_day()
	{
		global $context, $txt;

		// They haven't even been registered for a full day!?
		$days_registered = (int) ((time() - $this->_profile['registered_raw']) / (3600 * 24));
		if (empty($this->_profile['date_registered']) || $days_registered < 1)
		{
			$context['member']['posts_per_day'] = $txt['not_applicable'];
		}
		else
		{
			$context['member']['posts_per_day'] = comma_format($context['member']['real_posts'] / $days_registered, 3);
		}
	}

	/**
	 * Show age and birthday data if applicable.
	 */
	private function _determine_age_birth()
	{
		global $context, $txt;

		// Set the age...
		if (empty($context['member']['birth_date']))
		{
			$context['member']['age'] = $txt['not_applicable'];
			$context['member']['today_is_birthday'] = false;
		}
		else
		{
			list ($birth_year, $birth_month, $birth_day) = sscanf($context['member']['birth_date'], '%d-%d-%d');
			$datearray = getdate(forum_time());
			$context['member']['age'] = $birth_year <= 4 ? $txt['not_applicable'] : $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] === $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);
			$context['member']['today_is_birthday'] = $datearray['mon'] === $birth_month && $datearray['mday'] === $birth_day;

		}
	}

	/**
	 * Show IP and hostname information for the users current IP of record.
	 */
	private function _determine_member_ip()
	{
		global $context, $modSettings;

		if (allowedTo('moderate_forum'))
		{
			// Make sure it's a valid ip address; otherwise, don't bother...
			if (filter_var($this->_profile['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false && empty($modSettings['disableHostnameLookup']))
			{
				$context['member']['hostname'] = host_from_ip($this->_profile['ip']);
			}
			else
			{
				$context['member']['hostname'] = '';
			}

			$context['can_see_ip'] = true;
		}
		else
		{
			$context['can_see_ip'] = false;
		}
	}

	/**
	 * Determines what action user is "doing" at the time of the summary view
	 */
	private function _determine_member_action()
	{
		global $context, $modSettings;

		if (!empty($modSettings['who_enabled']) && $context['member']['online']['is_online'])
		{
			include_once(SUBSDIR . '/Who.subs.php');
			$action = determineActions($this->_profile['url']);
			theme()->getTemplates()->loadLanguageFile('index');

			if ($action !== false)
			{
				$context['member']['action'] = $action;
			}
		}
	}

	/**
	 * Checks if hte member is activated
	 *
	 * - Creates a link if the viewing member can activate a user
	 */
	private function _determine_member_activation()
	{
		global $context, $scripturl, $txt;

		// If the user is awaiting activation, and the viewer has permission - setup some activation context messages.
		if ($context['member']['is_activated'] % 10 !== 1 && allowedTo('moderate_forum'))
		{
			$context['activate_type'] = $context['member']['is_activated'];

			// What should the link text be?
			$context['activate_link_text'] = in_array($context['member']['is_activated'], array(3, 4, 5, 13, 14, 15))
				? $txt['account_approve']
				: $txt['account_activate'];

			// Should we show a custom message?
			$context['activate_message'] = isset($txt['account_activate_method_' . $context['member']['is_activated'] % 10])
				? $txt['account_activate_method_' . $context['member']['is_activated'] % 10]
				: $txt['account_not_activated'];

			$context['activate_url'] = $scripturl . '?action=profile;save;area=activateaccount;u=' . $this->_memID . ';' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['profile-aa' . $this->_memID . '_token_var'] . '=' . $context['profile-aa' . $this->_memID . '_token'];
		}
	}

	/**
	 * Checks if a member has been banned
	 */
	private function _determine_member_bans()
	{
		global $context;

		// How about, are they banned?
		if (allowedTo('moderate_forum'))
		{
			require_once(SUBSDIR . '/Bans.subs.php');

			$hostname = !empty($context['member']['hostname']) ? $context['member']['hostname'] : '';
			$email = !empty($context['member']['email']) ? $context['member']['email'] : '';
			$context['member']['bans'] = BanCheckUser($this->_memID, $hostname, $email);

			// Can they edit the ban?
			$context['can_edit_ban'] = allowedTo('manage_bans');
		}
	}

	/**
	 * Show all posts by the current user.
	 *
	 * @todo This function needs to be split up properly.
	 */
	public function action_showPosts()
	{
		global $txt, $scripturl, $modSettings, $context, $board;

		// Some initial context.
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);
		$context['current_member'] = $this->_memID;

		// What are we viewing
		$action = $this->_req->getQuery('sa', 'trim', '');
		$action_title = array('messages' => 'Messages', 'attach' => 'Attachments', 'topics' => 'Topics', 'unwatchedtopics' => 'Unwatched');
		$action_title = isset($action_title[$action]) ? $action_title[$action] : 'Posts';

		theme()->getTemplates()->load('ProfileInfo');

		// Create the tabs for the template.
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['show' . $action_title],
			'description' => sprintf($txt['showGeneric_help'], $txt['show' . $action_title]),
			'class' => 'profile',
			'tabs' => array(
				'messages' => array(),
				'topics' => array(),
				'unwatchedtopics' => array(),
				'attach' => array(),
			),
		);

		// Set the page title
		$context['page_title'] = $txt['showPosts'] . ' - ' . $this->_profile['real_name'];

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_show_posts']) && $modSettings['current_load'] >= $modSettings['loadavg_show_posts'])
		{
			throw new Exception('loadavg_show_posts_disabled', false);
		}

		// If we're specifically dealing with attachments use that function!
		if ($action === 'attach')
		{
			return $this->action_showAttachments();
		}
		// Instead, if we're dealing with unwatched topics (and the feature is enabled) use that other function.
		elseif ($action === 'unwatchedtopics' && $modSettings['enable_unwatch'])
		{
			return $this->action_showUnwatched();
		}

		// Are we just viewing topics?
		$context['is_topics'] = $action === 'topics';

		// If just deleting a message, do it and then redirect back.
		if (isset($this->_req->query->delete) && !$context['is_topics'])
		{
			checkSession('get');

			// We can be lazy, since removeMessage() will check the permissions for us.
			$remover = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);
			$remover->removeMessage((int) $this->_req->query->delete);

			// Back to... where we are now ;).
			redirectexit('action=profile;u=' . $this->_memID . ';area=showposts;start=' . $context['start']);
		}

		$msgCount = $context['is_topics'] ? count_user_topics($this->_memID, $board) : count_user_posts($this->_memID, $board);

		list ($min_msg_member, $max_msg_member) = findMinMaxUserMessage($this->_memID, $board);
		$range_limit = '';
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $this->_memID . ';area=showposts' . ($context['is_topics'] ? ';sa=topics' : ';sa=messages') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = $context['start'];
		$reverse = $this->_req->getQuery('start', 'intval', 0) > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
			$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
		}

		// Guess the range of messages to be shown to help minimize what the query needs to do
		if ($msgCount > 1000)
		{
			$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));

			// Make a bigger margin for topics only.
			if ($context['is_topics'])
			{
				$margin *= 5;
				$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
			}
			else
			{
				$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
			}
		}

		// Find this user's posts or topics started
		if ($context['is_topics'])
		{
			$rows = load_user_topics($this->_memID, $start, $maxIndex, $range_limit, $reverse, $board);
		}
		else
		{
			$rows = load_user_posts($this->_memID, $start, $maxIndex, $range_limit, $reverse, $board);
		}

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = array();
		$board_ids = array('own' => array(), 'any' => array());
		$bbc_parser = ParserWrapper::instance();
		foreach ($rows as $row)
		{
			// Censor....
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			// Do the code.
			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// And the array...
			$context['posts'][$counter += $reverse ? -1 : 1] = array(
				'body' => $row['body'],
				'counter' => $counter,
				'alternate' => $counter % 2,
				'category' => array(
					'name' => $row['cname'],
					'id' => $row['id_cat']
				),
				'board' => array(
					'name' => $row['bname'],
					'id' => $row['id_board'],
					'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>',
				),
				'topic' => array(
					'id' => $row['id_topic'],
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				),
				'subject' => $row['subject'],
				'start' => 'msg' . $row['id_msg'],
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id' => $row['id_msg'],
				'tests' => array(
					'can_reply' => false,
					'can_mark_notify' => false,
					'can_delete' => false,
				),
				'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
				'approved' => $row['approved'],

				'buttons' => array(
					// How about... even... remove it entirely?!
					'remove' => array(
						'href' => $scripturl . '?action=deletemsg;msg=' . $row['id_msg'] . ';topic=' . $row['id_topic'] . ';profile;u=' . $context['member']['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
						'text' => $txt['remove'],
						'test' => 'can_delete',
						'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['remove_message'] . '?') . ');"',
					),
					// Can we request notification of topics?
					'notify' => array(
						'href' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.msg' . $row['id_msg'],
						'text' => $txt['notify'],
						'test' => 'can_mark_notify',
					),
					// If they *can* reply?
					'reply' => array(
						'href' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.msg' . $row['id_msg'],
						'text' => $txt['reply'],
						'test' => 'can_reply',
					),
					// If they *can* quote?
					'quote' => array(
						'href' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';quote=' . $row['id_msg'],
						'text' => $txt['quote'],
						'test' => 'can_quote',
					),
				)
			);

			if ($this->user->id == $row['id_member_started'])
			{
				$board_ids['own'][$row['id_board']][] = $counter;
			}
			$board_ids['any'][$row['id_board']][] = $counter;
		}

		// All posts were retrieved in reverse order, get them right again.
		if ($reverse)
		{
			$context['posts'] = array_reverse($context['posts'], true);
		}

		// These are all the permissions that are different from board to board..
		if ($context['is_topics'])
		{
			$permissions = array(
				'own' => array(
					'post_reply_own' => 'can_reply',
				),
				'any' => array(
					'post_reply_any' => 'can_reply',
					'mark_any_notify' => 'can_mark_notify',
				)
			);
		}
		else
		{
			$permissions = array(
				'own' => array(
					'post_reply_own' => 'can_reply',
					'delete_own' => 'can_delete',
				),
				'any' => array(
					'post_reply_any' => 'can_reply',
					'mark_any_notify' => 'can_mark_notify',
					'delete_any' => 'can_delete',
				)
			);
		}

		// For every permission in the own/any lists...
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// Get the boards they can do this on...
				$boards = boardsAllowedTo($permission);

				// Hmm, they can do it on all boards, can they?
				if (!empty($boards) && $boards[0] == 0)
				{
					$boards = array_keys($board_ids[$type]);
				}

				// Now go through each board they can do the permission on.
				foreach ($boards as $board_id)
				{
					// There aren't any posts displayed from this board.
					if (!isset($board_ids[$type][$board_id]))
					{
						continue;
					}

					// Set the permission to true ;).
					foreach ($board_ids[$type][$board_id] as $counter)
					{
						$context['posts'][$counter]['tests'][$allowed] = true;
					}
				}
			}
		}

		// Clean up after posts that cannot be deleted and quoted.
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
		foreach ($context['posts'] as $counter => $dummy)
		{
			$context['posts'][$counter]['tests']['can_delete'] &= $context['posts'][$counter]['delete_possible'];
			$context['posts'][$counter]['tests']['can_quote'] = $context['posts'][$counter]['tests']['can_reply'] && $quote_enabled;
		}
	}

	/**
	 * Show all the attachments of a user.
	 */
	public function action_showAttachments()
	{
		global $txt, $scripturl, $modSettings, $context;

		// OBEY permissions!
		$boardsAllowed = boardsAllowedTo('view_attachments');

		// Make sure we can't actually see anything...
		if (empty($boardsAllowed))
		{
			$boardsAllowed = array(-1);
		}

		// This is all the information required to list attachments.
		$listOptions = array(
			'id' => 'profile_attachments',
			'title' => $txt['showAttachments'] . ($context['user']['is_owner'] ? '' : ' - ' . $context['member']['name']),
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['show_attachments_none'],
			'base_href' => $scripturl . '?action=profile;area=showposts;sa=attach;u=' . $this->_memID,
			'default_sort_col' => 'filename',
			'get_items' => array(
				'function' => function ($start, $items_per_page, $sort, $boardsAllowed) {
					return $this->list_getAttachments($start, $items_per_page, $sort, $boardsAllowed);
				},
				'params' => array(
					$boardsAllowed,
				),
			),
			'get_count' => array(
				'function' => function ($boardsAllowed) {
					return $this->list_getNumAttachments($boardsAllowed);
				},
				'params' => array(
					$boardsAllowed,
				),
			),
			'data_check' => array(
				'class' => function ($data) {
					return $data['approved'] ? '' : 'approvebg';
				},
			),
			'columns' => array(
				'filename' => array(
					'header' => array(
						'value' => $txt['show_attach_filename'],
						'class' => 'lefttext grid25',
					),
					'data' => array(
						'db' => 'filename',
					),
					'sort' => array(
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					),
				),
				'thumb' => array(
					'header' => array(
						'value' => '',
					),
					'data' => array(
						'function' => function ($rowData) {
							global $scripturl;

							if ($rowData['is_image'] && !empty($rowData['id_thumb']))
							{
								return '<img src="' . $scripturl . '?action=dlattach;attach=' . $rowData['id_thumb'] . ';image" />';
							}
							else
							{
								return '<img src="' . $scripturl . '?action=dlattach;attach=' . $rowData['id'] . ';thumb" />';
							}
						},
						'class' => 'centertext recent_attachments',
					),
					'sort' => array(
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					),
				),
				'downloads' => array(
					'header' => array(
						'value' => $txt['show_attach_downloads'],
					),
					'data' => array(
						'db' => 'downloads',
						'comma_format' => true,
					),
					'sort' => array(
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					),
				),
				'subject' => array(
					'header' => array(
						'value' => $txt['message'],
						'class' => 'lefttext grid30',
					),
					'data' => array(
						'db' => 'subject',
					),
					'sort' => array(
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					),
				),
				'posted' => array(
					'header' => array(
						'value' => $txt['show_attach_posted'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'posted',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'profile_attachments';
	}

	/**
	 * Get a list of attachments for this user
	 * Callback for createList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int[] $boardsAllowed
	 *
	 * @return array
	 */
	public function list_getAttachments($start, $items_per_page, $sort, $boardsAllowed)
	{
		// @todo tweak this method to use $context, etc,
		// then call subs function with params set.
		return profileLoadAttachments($start, $items_per_page, $sort, $boardsAllowed, $this->_memID);
	}

	/**
	 * Callback for createList()
	 *
	 * @param int[] $boardsAllowed
	 *
	 * @return int
	 */
	public function list_getNumAttachments($boardsAllowed)
	{
		// @todo tweak this method to use $context, etc,
		// then call subs function with params set.
		return getNumAttachments($boardsAllowed, $this->_memID);
	}

	/**
	 * Show all the unwatched topics.
	 */
	public function action_showUnwatched()
	{
		global $txt, $scripturl, $modSettings, $context;

		// Only the owner can see the list (if the function is enabled of course)
		if ($this->user->id != $this->_memID || !$modSettings['enable_unwatch'])
		{
			return;
		}

		// And here they are: the topics you don't like
		$listOptions = array(
			'id' => 'unwatched_topics',
			'title' => $txt['showUnwatched'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['unwatched_topics_none'],
			'base_href' => $scripturl . '?action=profile;area=showposts;sa=unwatchedtopics;u=' . $this->_memID,
			'default_sort_col' => 'started_on',
			'get_items' => array(
				'function' => function ($start, $items_per_page, $sort) {
					return $this->list_getUnwatched($start, $items_per_page, $sort);
				},
			),
			'get_count' => array(
				'function' => function () {
					return $this->list_getNumUnwatched();
				},
			),
			'columns' => array(
				'subject' => array(
					'header' => array(
						'value' => $txt['subject'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?topic=%1$d.0">%2$s</a>',
							'params' => array(
								'id_topic' => false,
								'subject' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					),
				),
				'started_by' => array(
					'header' => array(
						'value' => $txt['started_by'],
						'style' => 'width: 15%;',
					),
					'data' => array(
						'db' => 'started_by',
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'started_on' => array(
					'header' => array(
						'value' => $txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					),
					'data' => array(
						'db' => 'started_on',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
				'last_post_by' => array(
					'header' => array(
						'value' => $txt['last_post'],
						'style' => 'width: 15%;',
					),
					'data' => array(
						'db' => 'last_post_by',
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'last_post_on' => array(
					'header' => array(
						'value' => $txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					),
					'data' => array(
						'db' => 'last_post_on',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'unwatched_topics';
	}

	/**
	 * Get the relevant topics in the unwatched list
	 * Callback for createList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 *
	 * @return array
	 */
	public function list_getUnwatched($start, $items_per_page, $sort)
	{
		return getUnwatchedBy($start, $items_per_page, $sort, $this->_memID);
	}

	/**
	 * Count the number of topics in the unwatched list
	 * Callback for createList()
	 */
	public function list_getNumUnwatched()
	{
		return getNumUnwatchedBy($this->_memID);
	}

	/**
	 * Gets the user stats for display.
	 */
	public function action_statPanel()
	{
		global $txt, $context, $modSettings;

		require_once(SUBSDIR . '/Stats.subs.php');

		$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $this->_profile['real_name'];

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_userstats']) && $modSettings['current_load'] >= $modSettings['loadavg_userstats'])
		{
			throw new Exception('loadavg_userstats_disabled', false);
		}

		theme()->getTemplates()->load('ProfileInfo');

		// General user statistics.
		$timeDays = floor($this->_profile['total_time_logged_in'] / 86400);
		$timeHours = floor(($this->_profile['total_time_logged_in'] % 86400) / 3600);
		$context['time_logged_in'] = ($timeDays > 0 ? $timeDays . $txt['totalTimeLogged2'] : '') . ($timeHours > 0 ? $timeHours . $txt['totalTimeLogged3'] : '') . floor(($this->_profile['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged4'];
		$context['num_posts'] = comma_format($this->_profile['posts']);

		// Menu tab
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['statPanel_generalStats'] . ' - ' . $context['member']['name'],
			'class' => 'stats_info'
		);

		// Number of topics started.
		$context['num_topics'] = UserStatsTopicsStarted($this->_memID);

		// Number of polls started.
		$context['num_polls'] = UserStatsPollsStarted($this->_memID);

		// Number of polls voted in.
		$context['num_votes'] = UserStatsPollsVoted($this->_memID);

		// Format the numbers...
		$context['num_topics'] = comma_format($context['num_topics']);
		$context['num_polls'] = comma_format($context['num_polls']);
		$context['num_votes'] = comma_format($context['num_votes']);

		// Grab the boards this member posted in most often.
		$context['popular_boards'] = UserStatsMostPostedBoard($this->_memID);

		// Now get the 10 boards this user has most often participated in.
		$context['board_activity'] = UserStatsMostActiveBoard($this->_memID);

		// Posting activity by time.
		$context['posts_by_time'] = UserStatsPostingTime($this->_memID);

		// Custom stats (just add a template_layer to add it to the template!)
		call_integration_hook('integrate_profile_stats', array($this->_memID));
	}

	/**
	 * Show permissions for a user.
	 */
	public function action_showPermissions()
	{
		global $txt, $board, $context, $scripturl;

		// Verify if the user has sufficient permissions.
		isAllowedTo('manage_permissions');

		theme()->getTemplates()->loadLanguageFile('ManagePermissions');
		theme()->getTemplates()->loadLanguageFile('Admin');
		theme()->getTemplates()->load('ManageMembers');
		theme()->getTemplates()->load('ProfileInfo');

		// Load all the permission profiles.
		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		loadPermissionProfiles();

		$context['member']['id'] = $this->_memID;
		$context['member']['name'] = $this->_profile['real_name'];

		$context['page_title'] = $txt['showPermissions'];
		$board = empty($board) ? 0 : (int) $board;
		$context['board'] = $board;

		$curGroups = empty($this->_profile['additional_groups']) ? array() : explode(',', $this->_profile['additional_groups']);

		$curGroups[] = $this->_profile['id_group'];
		$curGroups[] = $this->_profile['id_post_group'];

		// Load a list of boards for the jump box - except the defaults.
		require_once(SUBSDIR . '/Boards.subs.php');
		$board_list = getBoardList(array('moderator' => $this->_memID), true);

		$context['boards'] = array();
		$context['no_access_boards'] = array();
		foreach ($board_list as $row)
		{
			if (count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0 && !$row['is_mod'])
			{
				$context['no_access_boards'][] = array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'is_last' => false,
				);
			}
			elseif ($row['id_profile'] != 1 || $row['is_mod'])
			{
				$context['boards'][$row['id_board']] = array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'url' => $scripturl, '?board=', $row['id_board'], '.0',
					'selected' => $board == $row['id_board'],
					'profile' => $row['id_profile'],
					'profile_name' => $context['profiles'][$row['id_profile']]['name'],
				);
			}
		}

		if (!empty($context['no_access_boards']))
		{
			$context['no_access_boards'][count($context['no_access_boards']) - 1]['is_last'] = true;
		}

		$context['member']['permissions'] = array(
			'general' => array(),
			'board' => array()
		);

		// If you're an admin we know you can do everything, we might as well leave.
		$context['member']['has_all_permissions'] = in_array(1, $curGroups);
		if ($context['member']['has_all_permissions'])
		{
			return;
		}

		// Get all general permissions for the groups this member is in
		$context['member']['permissions']['general'] = getMemberGeneralPermissions($curGroups);

		// Get all board permissions for this member
		$context['member']['permissions']['board'] = getMemberBoardPermissions($this->_memID, $curGroups, $board);
	}

	/**
	 * View a members warnings.
	 */
	public function action_viewWarning()
	{
		global $modSettings, $context, $txt, $scripturl;

		// Firstly, can we actually even be here?
		if (!allowedTo('issue_warning') && (empty($modSettings['warning_show']) || ($modSettings['warning_show'] == 1 && !$context['user']['is_owner'])))
		{
			throw new Exception('no_access', false);
		}

		theme()->getTemplates()->load('ProfileInfo');

		// We need this because of template_load_warning_variables
		theme()->getTemplates()->load('Profile');

		// Make sure things which are disabled stay disabled.
		$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
		$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) && !empty($modSettings['postmod_active']) ? $modSettings['warning_moderate'] : 110;
		$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

		// Let's use a generic list to get all the current warnings
		// and use the issue warnings grab-a-granny thing.
		$listOptions = array(
			'id' => 'view_warnings',
			'title' => $txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['profile_viewwarning_no_warnings'],
			'base_href' => $scripturl . '?action=profile;area=viewwarning;sa=user;u=' . $this->_memID,
			'default_sort_col' => 'log_time',
			'get_items' => array(
				'function' => 'list_getUserWarnings',
				'params' => array(
					$this->_memID,
				),
			),
			'get_count' => array(
				'function' => 'list_getUserWarningCount',
				'params' => array(
					$this->_memID,
				),
			),
			'columns' => array(
				'log_time' => array(
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
						'style' => 'width: 50%;',
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'level' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_level'],
					),
					'data' => array(
						'db' => 'counter',
					),
					'sort' => array(
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['profile_viewwarning_desc'],
					'class' => 'smalltext',
					'style' => 'padding: 2ex;',
				),
			),
		);

		// Create the list for viewing.
		createList($listOptions);

		// Create some common text bits for the template.
		$context['level_effects'] = array(
			0 => '',
			$modSettings['warning_watch'] => $txt['profile_warning_effect_own_watched'],
			$modSettings['warning_moderate'] => $txt['profile_warning_effect_own_moderated'],
			$modSettings['warning_mute'] => $txt['profile_warning_effect_own_muted'],
		);
		$context['current_level'] = 0;
		$context['sub_template'] = 'viewWarning';

		foreach ($context['level_effects'] as $limit => $dummy)
		{
			if ($context['member']['warning'] >= $limit)
			{
				$context['current_level'] = $limit;
			}
		}
	}

	/**
	 * Collect and output data related to the profile buddy tab
	 *
	 * - Ajax call from profile info buddy tab
	 */
	public function action_profile_buddies()
	{
		global $context;

		checkSession('get');

		// Need the ProfileInfo and Index (for helper functions) templates
		theme()->getTemplates()->load('Index');
		theme()->getTemplates()->load('ProfileInfo');

		// Prep for a buddy check
		$this->_register_summarytabs();
		$this->_define_user_values();

		// This is returned only for ajax request to a jqueryUI tab
		theme()->getLayers()->removeAll();
		header('Content-Type: text/html; charset=UTF-8');

		// Some buddies for you
		if (in_array('buddies', $this->_summary_areas))
		{
			$this->_load_buddies();
			$context['sub_template'] = 'profile_block_buddies';
		}
	}

	/**
	 * Load the buddies tab with their buddies, real or imaginary
	 */
	private function _load_buddies()
	{
		global $context, $modSettings;

		// Would you be mine? Could you be mine? Be my buddy :D
		$context['buddies'] = array();
		if (!empty($modSettings['enable_buddylist'])
			&& $context['user']['is_owner']
			&& !empty($this->user->buddies)
			&& in_array('buddies', $this->_summary_areas))
		{
			MembersList::load($this->user->buddies, false, 'profile');

			// Get the info for this buddy
			foreach ($this->user->buddies as $buddy)
			{
				$member = MembersList::get($buddy);
				$member->loadContext(true);

				$context['buddies'][$buddy] = $member;
			}
		}
	}

	/**
	 * Collect and output data related to the profile recent tab
	 *
	 * - Ajax call from profile info recent tab
	 */
	public function action_profile_recent()
	{
		global $context;

		checkSession('get');

		// Prep for recent activity
		$this->_register_summarytabs();
		$this->_define_user_values();

		// The block templates are here
		theme()->getTemplates()->load('ProfileInfo');
		$context['sub_template'] = 'profile_blocks';
		$context['profile_blocks'] = array();

		// Flush everything since we intend to return the information to an ajax handler
		theme()->getLayers()->removeAll();
		header('Content-Type: text/html; charset=UTF-8');

		// So, just what have you been up to?
		if (in_array('posts', $this->_summary_areas))
		{
			$this->_load_recent_posts();
			$context['profile_blocks'][] = 'template_profile_block_posts';
		}

		if (in_array('topics', $this->_summary_areas))
		{
			$this->_load_recent_topics();
			$context['profile_blocks'][] = 'template_profile_block_topics';
		}

		if (in_array('attachments', $this->_summary_areas))
		{
			$this->_load_recent_attachments();
			$context['profile_blocks'][] = 'template_profile_block_attachments';
		}
	}

	/**
	 * Load a members most recent posts
	 */
	private function _load_recent_posts()
	{
		global $context, $modSettings, $scripturl;

		// How about their most recent posts?
		if (in_array('posts', $this->_summary_areas))
		{
			// Is the load average too high just now, then let them know
			if (!empty($modSettings['loadavg_show_posts']) && $modSettings['current_load'] >= $modSettings['loadavg_show_posts'])
			{
				$context['loadaverage'] = true;
			}
			else
			{
				// Set up to get the last 10 posts of this member
				$msgCount = count_user_posts($this->_memID);
				$range_limit = '';
				$maxIndex = 10;
				$start = $this->_req->getQuery('start', 'intval', 0);

				// If they are a frequent poster, we guess the range to help minimize what the query work
				if ($msgCount > 1000)
				{
					list ($min_msg_member, $max_msg_member) = findMinMaxUserMessage($this->_memID);
					$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
					$range_limit = 'm.id_msg > ' . ($max_msg_member - $margin);
				}

				// Find this user's most recent posts
				$rows = load_user_posts($this->_memID, 0, $maxIndex, $range_limit);
				$bbc_parser = ParserWrapper::instance();
				$context['posts'] = array();
				foreach ($rows as $row)
				{
					// Censor....
					$row['body'] = censor($row['body']);
					$row['subject'] = censor($row['subject']);

					// Do the code.
					$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);
					$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));
					$preview = Util::shorten_text($preview, !empty($modSettings['ssi_preview_length']) ? $modSettings['ssi_preview_length'] : 128);
					$short_subject = Util::shorten_text($row['subject'], !empty($modSettings['ssi_subject_length']) ? $modSettings['ssi_subject_length'] : 24);

					// And the array...
					$context['posts'][] = array(
						'body' => $preview,
						'board' => array(
							'name' => $row['bname'],
							'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
						),
						'subject' => $row['subject'],
						'short_subject' => $short_subject,
						'time' => standardTime($row['poster_time']),
						'html_time' => htmlTime($row['poster_time']),
						'timestamp' => forum_time(true, $row['poster_time']),
						'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $short_subject . '</a>',
					);
				}
			}
		}
	}

	/**
	 * Load a users recent topics
	 */
	private function _load_recent_topics()
	{
		global $context, $modSettings, $scripturl;

		// How about the most recent topics that they started?
		if (in_array('topics', $this->_summary_areas))
		{
			// Is the load average still too high?
			if (!empty($modSettings['loadavg_show_posts']) && $modSettings['current_load'] >= $modSettings['loadavg_show_posts'])
			{
				$context['loadaverage'] = true;
			}
			else
			{
				// Set up to get the last 10 topics of this member
				$topicCount = count_user_topics($this->_memID);
				$range_limit = '';
				$maxIndex = 10;
				$start = $this->_req->getQuery('start', 'intval', 0);

				// If they are a frequent topic starter we guess the range to help the query
				if ($topicCount > 1000)
				{
					list ($min_topic_member, $max_topic_member) = findMinMaxUserTopic($this->_memID);
					$margin = floor(($max_topic_member - $min_topic_member) * (($start + $modSettings['defaultMaxMessages']) / $topicCount) + .1 * ($max_topic_member - $min_topic_member));
					$margin *= 5;
					$range_limit = 't.id_first_msg > ' . ($max_topic_member - $margin);
				}

				// Find this user's most recent topics
				$rows = load_user_topics($this->_memID, 0, $maxIndex, $range_limit);
				$context['topics'] = array();
				$bbc_parser = ParserWrapper::instance();

				foreach ($rows as $row)
				{
					// Censor....
					$row['body'] = censor($row['body']);
					$row['subject'] = censor($row['subject']);

					// Do the code.
					$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);
					$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));
					$preview = Util::shorten_text($preview, !empty($modSettings['ssi_preview_length']) ? $modSettings['ssi_preview_length'] : 128);
					$short_subject = Util::shorten_text($row['subject'], !empty($modSettings['ssi_subject_length']) ? $modSettings['ssi_subject_length'] : 24);

					// And the array...
					$context['topics'][] = array(
						'board' => array(
							'name' => $row['bname'],
							'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
						),
						'subject' => $row['subject'],
						'short_subject' => $short_subject,
						'body' => $preview,
						'time' => standardTime($row['poster_time']),
						'html_time' => htmlTime($row['poster_time']),
						'timestamp' => forum_time(true, $row['poster_time']),
						'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $short_subject . '</a>',
					);
				}
			}
		}
	}

	/**
	 * If they have made recent attachments, lets get a list of them to display
	 */
	private function _load_recent_attachments()
	{
		global $context, $modSettings, $scripturl, $settings;

		$context['thumbs'] = array();

		// Load up the most recent attachments for this user for use in profile views etc.
		if (!empty($modSettings['attachmentEnable'])
			&& !empty($settings['attachments_on_summary'])
			&& in_array('attachments', $this->_summary_areas))
		{
			$boardsAllowed = boardsAllowedTo('view_attachments');

			if (empty($boardsAllowed))
			{
				$boardsAllowed = array(-1);
			}

			$attachments = $this->list_getAttachments(0, $settings['attachments_on_summary'], 'm.poster_time DESC', $boardsAllowed);

			// Some generic images for mime types
			$mime_images_url = $settings['default_images_url'] . '/mime_images/';
			$mime_path = $settings['default_theme_dir'] . '/images/mime_images/';

			// Load them in to $context for use in the template
			foreach ($attachments as $i => $attachment)
			{
				$context['thumbs'][$i] = array(
					'url' => $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id'],
					'img' => '',
					'filename' => $attachment['filename'],
					'downloads' => $attachment['downloads'],
					'subject' => $attachment['subject'],
					'id' => $attachment['id'],
				);

				// Show a thumbnail image as well?
				if ($attachment['is_image'] && !empty($modSettings['attachmentShowImages']) && !empty($modSettings['attachmentThumbnails']))
				{
					if (!empty($attachment['id_thumb']))
					{
						$context['thumbs'][$i]['img'] = '<img id="thumb_' . $attachment['id'] . '" src="' . $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_thumb'] . ';image" title="" alt="" />';
					}
					elseif (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']))
					{
						// No thumbnail available ... use html instead
						if ($attachment['width'] > $modSettings['attachmentThumbWidth'] || $attachment['height'] > $modSettings['attachmentThumbHeight'])
						{
							$context['thumbs'][$i]['img'] = '<img id="thumb_' . $attachment['id'] . '" src="' . $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id'] . '" title="" alt="" width="' . $modSettings['attachmentThumbWidth'] . '" height="' . $modSettings['attachmentThumbHeight'] . '" />';
						}
						else
						{
							$context['thumbs'][$i]['img'] = '<img id="thumb_' . $attachment['id'] . '" src="' . $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id'] . '" title="" alt="" width="' . $attachment['width'] . '" height="' . $attachment['height'] . '" />';
						}
					}
				}
				// Not an image so lets set a mime thumbnail based off the filetype
				elseif ((!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight'])) && (128 > $modSettings['attachmentThumbWidth'] || 128 > $modSettings['attachmentThumbHeight']))
				{
					$context['thumbs'][$i]['img'] = '<img src="' . $mime_images_url . (!file_exists($mime_path . $attachment['fileext'] . '.png') ? 'default' : $attachment['fileext']) . '.png" title="" alt="" width="' . $modSettings['attachmentThumbWidth'] . '" height="' . $modSettings['attachmentThumbHeight'] . '" />';
				}
				else
				{
					$context['thumbs'][$i]['img'] = '<img src="' . $mime_images_url . (!file_exists($mime_path . $attachment['fileext'] . '.png') ? 'default' : $attachment['fileext']) . '.png" title="" alt="" />';
				}
			}
		}
	}
}
