<?php

/**
 * This file currently just shows group info, and allows certain privileged
 * members to add/remove members.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Shows group access and allows for add/remove group members
 */
class Groups extends \ElkArte\AbstractController
{
	/**
	 * Set up templates and pre-requisites for any request processed by this class.
	 *
	 * - Called automagically before any action_() call.
	 * - It handles permission checks, and puts the moderation bar on as required.
	 */
	public function pre_dispatch()
	{
		global $context, $txt;

		// Get the template stuff up and running.
		theme()->getTemplates()->loadLanguageFile('ManageMembers');
		theme()->getTemplates()->loadLanguageFile('ModerationCenter');
		theme()->getTemplates()->load('ManageMembergroups');

		// If we can see the moderation center, and this has a mod bar entry, add the mod center bar.
		if (User::$info->canMod(true) || allowedTo('manage_membergroups'))
		{
			$this->_req->query->area = $this->_req->getQuery('sa') === 'requests' ? 'groups' : 'viewgroups';
			$controller = new ModerationCenter(new \ElkArte\EventManager());
			$controller->pre_dispatch();
			$controller->prepareModcenter();
		}
		// Otherwise add something to the link tree, for normal people.
		else
		{
			isAllowedTo('view_mlist');

			$context['linktree'][] = array(
				'url' => getUrl('group', ['action' => 'groups']),
				'name' => $txt['groups'],
			);
		}
	}

	/**
	 * Entry point to groups.
	 * It allows moderators and users to access the group showing functions.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context;

		// Little short on the list here
		$subActions = array(
			'list' => array($this, 'action_list', 'permission' => 'view_mlist'),
			'members' => array($this, 'action_members', 'permission' => 'view_mlist'),
			'requests' => array($this, 'action_requests'),
		);

		// I don't think we know what to do... throw dies?
		$action = new \ElkArte\Action('groups');
		$subAction = $action->initialize($subActions, 'list');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * This very simply lists the groups, nothing snazzy.
	 */
	public function action_list()
	{
		global $txt, $context;

		$context['page_title'] = $txt['viewing_groups'];
		$current_area = isset($context['admin_menu_name']) ? $context['admin_menu_name'] : (isset($context['moderation_menu_name']) ? $context['moderation_menu_name'] : '');
		if (!empty($current_area))
		{
			$context[$current_area]['tab_data'] = array(
				'title' => $txt['mc_group_requests'],
			);
		}

		if (isset($context['admin_menu_name']))
		{
			$base_type = 'admin';
			$base_params = ['action' => 'admin', 'area' => 'membergroups', 'sa' => 'members'];
		}
		elseif (isset($context['moderation_menu_name']))
		{
			$base_type = 'moderate';
			$base_params = ['action' => 'moderate', 'area' => 'viewgroups', 'sa' => 'members'];
		}
		else
		{
			$base_type = 'group';
			$base_params = ['action' => 'groups', 'sa' => 'members'];
		}

		// Use the standard templates for showing this.
		$listOptions = array(
			'id' => 'group_lists',
			'base_href' => getUrl($base_type, $base_params),
			'default_sort_col' => 'group',
			'get_items' => array(
				'file' => SUBSDIR . '/Membergroups.subs.php',
				'function' => 'list_getMembergroups',
				'params' => array(
					'regular',
					$this->user->id,
					allowedTo('manage_membergroups'),
					allowedTo('admin_forum'),
				),
			),
			'columns' => array(
				'group' => array(
					'header' => array(
						'value' => $txt['name'],
					),
					'data' => array(
						'function' => function ($rowData) use ($base_type, $base_params) {
							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData['id_group'] == 3)
								$group_name = $rowData['group_name'];
							else
							{
								$url = getUrl($base_type, array_merge($base_params, ['group' => $rowData['id_group']]));
								$group_name = sprintf('<a href="%1$s">%3$s</a>', $url, $rowData['id_group'], $rowData['group_name_color']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData['id_group'] == 1)
							{
								$group_name .= ' (<a href="' . getUrl('action', ['action' => 'quickhelp', 'help' => 'membergroup_administrator']) . '" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"></a>)';
							}
							elseif ($rowData['id_group'] == 3)
							{
								$group_name .= ' (<a href="' . getUrl('action', ['action' => 'quickhelp', 'help' => 'membergroup_moderator']) . '" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"></a>)';
							}

							return $group_name;
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => $txt['membergroups_icons'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $settings;

							if (!empty($rowData['icons'][0]) && !empty($rowData['icons'][1]))
								return str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $rowData['icons'][1] . '" alt="*" />', $rowData['icons'][0]);
							else
								return '';
						},
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'moderators' => array(
					'header' => array(
						'value' => $txt['moderators'],
					),
					'data' => array(
						'function' => function ($group) {
							global $txt;

							return empty($group['moderators']) ? '<em>' . $txt['membergroups_new_copy_none'] . '</em>' : implode(', ', $group['moderators']);
						},
					),
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							// No explicit members for the moderator group.
							return $rowData['id_group'] == 3 ? $txt['membergroups_guests_na'] : comma_format($rowData['num_members']);
						},
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'group_lists';
	}

	/**
	 * Display members of a group, and allow adding of members to a group.
	 *
	 * What it does:
	 *
	 * - It can be called from ManageMembergroups if it needs templating within the admin environment.
	 * - It shows a list of members that are part of a given membergroup.
	 * - It is called by ?action=moderate;area=viewgroups;sa=members;group=x
	 * - It requires the manage_membergroups permission.
	 * - It allows to add and remove members from the selected membergroup.
	 * - It allows sorting on several columns.
	 * - It redirects to itself.
	 *
	 * @uses ManageMembergroups template, group_members sub template.
	 */
	public function action_members()
	{
		global $txt, $context, $modSettings, $settings;

		$current_group = $this->_req->getQuery('group', 'intval', 0);

		// These will be needed
		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');

		// Load up the group details.
		$context['group'] = membergroupById($current_group, true, true);

		// No browsing of guests, membergroup 0 or moderators or non-existing groups.
		if ($context['group'] === false || in_array($current_group, array(-1, 0, 3)))
			throw new \ElkArte\Exceptions\Exception('membergroup_does_not_exist', false);

		$context['group']['id'] = $context['group']['id_group'];
		$context['group']['name'] = $context['group']['group_name'];

		// Fix the membergroup icons.
		$context['group']['icons'] = explode('#', $context['group']['icons']);
		$context['group']['icons'] = !empty($context['group']['icons'][0]) && !empty($context['group']['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $context['group']['icons'][1] . '" alt="*" />', $context['group']['icons'][0]) : '';
		$context['group']['can_moderate'] = allowedTo('manage_membergroups') && (allowedTo('admin_forum') || $context['group']['group_type'] != 1);

		// The template is very needy
		$context['linktree'][] = array(
			'url' => getUrl('group', ['action' => 'groups', 'sa' => 'members', 'group' => $context['group']['id'], 'name' => $context['group']['name']]),
			'name' => $context['group']['name'],
		);
		$context['can_send_email'] = allowedTo('send_email_to_members');
		$context['sort_direction'] = isset($this->_req->query->desc) ? 'down' : 'up';
		$context['start'] = $this->_req->query->start;
		$context['can_moderate_forum'] = allowedTo('moderate_forum');

		// @todo: use createList

		// Load all the group moderators, for fun.
		$context['group']['moderators'] = array();
		$moderators = getGroupModerators($current_group);
		foreach ($moderators as $id_member => $name)
		{
			$context['group']['moderators'][] = array(
				'id' => $id_member,
				'name' => $name
			);

			if ($this->user->id == $id_member && $context['group']['group_type'] != 1)
				$context['group']['can_moderate'] = true;
		}

		// If this group is hidden then it can only "exist" if the user can moderate it!
		if ($context['group']['hidden'] && !$context['group']['can_moderate'])
			throw new \ElkArte\Exceptions\Exception('membergroup_does_not_exist', false);

		// You can only assign membership if you are the moderator and/or can manage groups!
		if (!$context['group']['can_moderate'])
			$context['group']['assignable'] = 0;
		// Non-admins cannot assign admins.
		elseif ($context['group']['id'] == 1 && !allowedTo('admin_forum'))
			$context['group']['assignable'] = 0;

		// Removing member from group?
		if (isset($this->_req->post->remove)
			&& !empty($this->_req->post->rem)
			&& is_array($this->_req->post->rem)
			&& $context['group']['assignable'])
		{
			// Security first
			checkSession();
			validateToken('mod-mgm');

			// Make sure we're dealing with integers only.
			$to_remove = array_map('intval', $this->_req->post->rem);
			removeMembersFromGroups($to_remove, $current_group, true);
		}
		// Must be adding new members to the group...
		elseif (isset($this->_req->post->add)
			&& (!empty($this->_req->post->toAdd) || !empty($this->_req->post->member_add)) && $context['group']['assignable'])
		{
			// Make sure you can do this
			checkSession();
			validateToken('mod-mgm');

			$member_query = array(array('and' => 'not_in_group'));
			$member_parameters = array('not_in_group' => $current_group);

			// Get all the members to be added... taking into account names can be quoted ;)
			$toAdd = strtr(\ElkArte\Util::htmlspecialchars($this->_req->post->toAdd, ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $toAdd, $matches);
			$member_names = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $toAdd))));

			foreach ($member_names as $index => $member_name)
			{
				$member_names[$index] = trim(\ElkArte\Util::strtolower($member_names[$index]));

				if (strlen($member_names[$index]) == 0)
					unset($member_names[$index]);
			}

			// Any members passed by ID?
			$member_ids = array();
			if (!empty($this->_req->post->member_add))
			{
				foreach ($this->_req->post->member_add as $id)
				{
					if ($id > 0)
						$member_ids[] = (int) $id;
				}
			}

			// Construct the query elements, first for adds by name
			if (!empty($member_ids))
			{
				$member_query[] = array('or' => 'member_ids');
				$member_parameters['member_ids'] = $member_ids;
			}

			// And then adds by ID
			if (!empty($member_names))
			{
				$member_query[] = array('or' => 'member_names');
				$member_parameters['member_names'] = $member_names;
			}

			// Get back the ones that were not already in the group
			$members = membersBy($member_query, $member_parameters);

			// Do the updates...
			if (!empty($members))
				addMembersToGroup($members, $current_group, $context['group']['hidden'] ? 'only_additional' : 'auto', true);
		}

		// Sort out the sorting!
		$sort_methods = array(
			'name' => 'real_name',
			'email' => allowedTo('moderate_forum') ? 'email_address' : 'hide_email ' . (isset($this->_req->query->desc) ? 'DESC' : 'ASC') . ', email_address',
			'active' => 'last_login',
			'registered' => 'date_registered',
			'posts' => 'posts',
		);

		// They didn't pick one, or tried a wrong one, so default to by name..
		if (!isset($this->_req->query->sort) || !isset($sort_methods[$this->_req->query->sort]))
		{
			$context['sort_by'] = 'name';
			$querySort = 'real_name' . (isset($this->_req->query->desc) ? ' DESC' : ' ASC');
		}
		// Otherwise sort by what they asked
		else
		{
			$context['sort_by'] = $this->_req->query->sort;
			$querySort = $sort_methods[$this->_req->query->sort] . (isset($this->_req->query->desc) ? ' DESC' : ' ASC');
		}

		// The where on the query is interesting. Non-moderators should only see people who are in this group as primary.
		if ($context['group']['can_moderate'])
			$where = $context['group']['is_post_group'] ? 'in_post_group' : 'in_group';
		else
			$where = $context['group']['is_post_group'] ? 'in_post_group' : 'in_group_no_add';

		// Count members of the group.
		$context['total_members'] = countMembersBy($where, array($where => $current_group));
		$context['total_members'] = comma_format($context['total_members']);

		// Create the page index.
		$context['page_index'] = constructPageIndex(
		getUrl($context['group']['can_moderate'] ? 'moderate' : 'group', ['action' => $context['group']['can_moderate'] ? 'moderate' : 'groups', 'area' => $context['group']['can_moderate'] ? 'viewgroups' : '', 'sa' => 'members', 'group' => $current_group, 'sort' => $context['sort_by'], isset($this->_req->query->desc) ? 'desc' : '']), $this->_req->query->start, $context['total_members'], $modSettings['defaultMaxMembers']);

		// Fetch the members that meet the where criteria
		$context['members'] = membersBy($where, array($where => $current_group, 'order' => $querySort), true);
		foreach ($context['members'] as $id => $row)
		{
			$last_online = empty($row['last_login']) ? $txt['never'] : standardTime($row['last_login']);

			// Italicize the online note if they aren't activated.
			if ($row['is_activated'] % 10 != 1)
				$last_online = '<em title="' . $txt['not_activated'] . '">' . $last_online . '</em>';

			$context['members'][$id] = array(
				'id' => $row['id_member'],
				'name' => '<a href="' . getUrl('profile', ['action' => 'quickhelp', 'u' => $row['id_member'], 'name' => $row['real_name']]) . '">' . $row['real_name'] . '</a>',
				'email' => $row['email_address'],
				'show_email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']),
				'ip' => '<a href="' . getUrl('action', ['action' => 'trackip', 'searchip' => $row['member_ip']]) . '">' . $row['member_ip'] . '</a>',
				'registered' => standardTime($row['date_registered']),
				'last_online' => $last_online,
				'posts' => comma_format($row['posts']),
				'is_activated' => $row['is_activated'] % 10 == 1,
			);
		}

		if (!empty($context['group']['assignable']))
			loadJavascriptFile('suggest.js', array('defer' => true));

		// Select the template.
		$context['sub_template'] = 'group_members';
		$context['page_title'] = $txt['membergroups_members_title'] . ': ' . $context['group']['name'];
		createToken('mod-mgm');
	}

	/**
	 * Show and manage all group requests.
	 */
	public function action_requests()
	{
		global $txt, $context, $modSettings;

		// Set up the template stuff...
		$context['page_title'] = $txt['mc_group_requests'];
		$context['sub_template'] = 'show_list';
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_group_requests'],
		);

		// Verify we can be here.
		if ($this->user->mod_cache['gq'] == '0=1')
			isAllowedTo('manage_membergroups');

		// Normally, we act normally...
		$where = $this->user->mod_cache['gq'] == '1=1' || $this->user->mod_cache['gq'] == '0=1' ? $this->user->mod_cache['gq'] : 'lgr.' . $this->user->mod_cache['gq'];
		$where_parameters = array();

		// We've submitted?
		if (isset($this->_req->post->{$context['session_var']})
			&& !empty($this->_req->post->groupr)
			&& !empty($this->_req->post->req_action))
		{
			checkSession('post');
			validateToken('mod-gr');

			require_once(SUBSDIR . '/Membergroups.subs.php');

			// Clean the values.
			$this->_req->post->groupr = array_map('intval', $this->_req->post->groupr);

			// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
			if ($this->_req->post->req_action === 'reason')
			{
				// Different sub template...
				$context['sub_template'] = 'group_request_reason';

				// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
				$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
				$where_parameters['request_ids'] = $this->_req->post->groupr;

				$context['group_requests'] = list_getGroupRequests(0, $modSettings['defaultMaxMessages'], 'lgr.id_request', $where, $where_parameters);
				createToken('mod-gr');

				// Let obExit etc sort things out.
				obExit();
			}
			// Otherwise we do something!
			else
			{
				// Get the details of all the members concerned...
				require_once(SUBSDIR . '/Members.subs.php');
				$concerned = getConcernedMembers($this->_req->post->groupr, $where, $this->_req->post->req_action === 'approve');

				// Cleanup old group requests..
				deleteGroupRequests($this->_req->post->groupr);

				// Ensure everyone who is online gets their changes right away.
				updateSettings(array('settings_updated' => time()));

				if (!empty($concerned['email_details']))
				{
					require_once(SUBSDIR . '/Mail.subs.php');

					// They are being approved?
					if ($this->_req->post->req_action === 'approve')
					{
						// Make the group changes.
						foreach ($concerned['group_changes'] as $id => $groups)
						{
							// Sanity check!
							foreach ($groups['add'] as $key => $value)
								if ($value == 0 || trim($value) == '')
									unset($groups['add'][$key]);

							assignGroupsToMember($id, $groups['primary'], $groups['add']);
						}

						foreach ($concerned['email_details'] as $email)
						{
							$replacements = array(
								'USERNAME' => $email['member_name'],
								'GROUPNAME' => $email['group_name'],
							);

							$emaildata = loadEmailTemplate('mc_group_approve', $replacements, $email['language']);

							sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
						}
					}
					// Otherwise, they are getting rejected (With or without a reason).
					else
					{
						// Same as for approving, kind of.
						foreach ($concerned['email_details'] as $email)
						{
							$custom_reason = isset($this->_req->post->groupreason) && isset($this->_req->post->groupreason[$email['rid']]) ? $this->_req->post->groupreason[$email['rid']] : '';

							$replacements = array(
								'USERNAME' => $email['member_name'],
								'GROUPNAME' => $email['group_name'],
							);

							if (!empty($custom_reason))
								$replacements['REASON'] = $custom_reason;

							$emaildata = loadEmailTemplate(empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason', $replacements, $email['language']);

							sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
						}
					}
				}

				// Restore the current language.
				theme()->getTemplates()->loadLanguageFile('ModerationCenter');
			}
		}

		// We're going to want this for making our list.
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// This is all the information required for a group listing.
		$listOptions = array(
			'id' => 'group_request_list',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['mc_groupr_none_found'],
			'base_href' => getUrl('group', ['action' => 'groups', 'sa' => 'requests']),
			'default_sort_col' => 'member',
			'get_items' => array(
				'function' => 'list_getGroupRequests',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'get_count' => array(
				'function' => 'list_getGroupRequestCount',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'columns' => array(
				'member' => array(
					'header' => array(
						'value' => $txt['mc_groupr_member'],
					),
					'data' => array(
						'db' => 'member_link',
					),
					'sort' => array(
						'default' => 'mem.member_name',
						'reverse' => 'mem.member_name DESC',
					),
				),
				'group' => array(
					'header' => array(
						'value' => $txt['mc_groupr_group'],
					),
					'data' => array(
						'db' => 'group_link',
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => $txt['mc_groupr_reason'],
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'date' => array(
					'header' => array(
						'value' => $txt['date'],
						'style' => 'width: 18%; white-space:nowrap;',
					),
					'data' => array(
						'db' => 'time_submitted',
					),
				),
				'action' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 4%;text-align: center;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="groupr[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => getUrl('group', ['action' => 'groups', 'sa' => 'requests']),
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
				'token' => 'mod-gr',
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '
						<select name="req_action" onchange="if (this.value != 0 &amp;&amp; (this.value === \'reason\' || confirm(\'' . $txt['mc_groupr_warning'] . '\'))) this.form.submit();">
							<option value="0">' . $txt['with_selected'] . ':</option>
							<option value="0" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['mc_groupr_approve'])) . '</option>
							<option value="approve">&#10148;&nbsp;' . $txt['mc_groupr_approve'] . '</option>
							<option value="reject">&#10148;&nbsp;' . $txt['mc_groupr_reject'] . '</option>
							<option value="reason">&#10148;&nbsp;' . $txt['mc_groupr_reject_w_reason'] . '</option>
						</select>
						<input type="submit" name="go" value="' . $txt['go'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value !== \'reason\' &amp;&amp; !confirm(\'' . $txt['mc_groupr_warning'] . '\')) return false;" />',
					'class' => 'floatright',
				),
			),
		);

		// Create the request list.
		createToken('mod-gr');
		createList($listOptions);

		$context['default_list'] = 'group_request_list';
	}
}
