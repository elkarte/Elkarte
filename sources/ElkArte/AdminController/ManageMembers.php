<?php

/**
 * Show a list of members or a selection of members.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * ManageMembers controller deals with members administration, approval,
 * admin-visible list, and search in it.
 *
 * @package Members
 */
class ManageMembers extends AbstractController
{
	/** @var array Holds various setting conditions for the current action */
	protected array $conditions;

	/** @var array Holds the members that the action is being applied to */
	protected array $member_info;

	/**
	 * The main entrance point for the Manage Members screen.
	 *
	 * What it does:
	 *
	 * - As everywhere else, it calls a function based on the given sub-action.
	 * - Called by ?action=admin;area=viewmembers.
	 * - Requires the moderate_forum permission.
	 *
	 * @event integrate_manage_members used to add subactions and tabs
	 * @uses ManageMembers template
	 * @uses ManageMembers language file.
	 * @see  AbstractCowntroller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context, $modSettings;

		// Load the essentials.
		Txt::load('ManageMembers');
		theme()->getTemplates()->load('ManageMembers');

		$subActions = [
			'all' => [
				'controller' => $this,
				'function' => 'action_list',
				'permission' => 'moderate_forum'],
			'approve' => [
				'controller' => $this,
				'function' => 'action_approve',
				'permission' => 'moderate_forum'],
			'browse' => [
				'controller' => $this,
				'function' => 'action_browse',
				'permission' => 'moderate_forum'],
			'search' => [
				'controller' => $this,
				'function' => 'action_search',
				'permission' => 'moderate_forum'],
			'query' => [
				'controller' => $this,
				'function' => 'action_list',
				'permission' => 'moderate_forum'],
		];

		// Prepare our action control
		$action = new Action();

		// Default to sub action 'all', needed for the tabs array below
		$subAction = $action->initialize($subActions, 'all');

		// Get counts on every type of activation - for sections and filtering alike.
		require_once(SUBSDIR . '/Members.subs.php');

		$context['awaiting_activation'] = 0;
		$context['awaiting_approval'] = 0;
		$context['activation_numbers'] = countInactiveMembers();

		foreach ($context['activation_numbers'] as $activation_type => $total_members)
		{
			if (in_array($activation_type, [0, 2], true))
			{
				$context['awaiting_activation'] += $total_members;
			}
			elseif (in_array($activation_type, [3, 4, 5], true))
			{
				$context['awaiting_approval'] += $total_members;
			}
		}

		// Last items for the template
		$context['page_title'] = $txt['admin_members'];
		$context['sub_action'] = $subAction;

		// For the page header... do we show activation?
		$context['show_activate'] = (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1) || !empty($context['awaiting_activation']);

		// What about approval?
		$context['show_approve'] = (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($context['awaiting_approval']) || !empty($modSettings['approveAccountDeletion']);

		// Setup the admin tabs.
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'admin_members',
			'help' => 'view_members',
			'description' => 'admin_members_list',
			'tabs' => [
				'viewmembers' => [
					'label' => $txt['view_all_members'],
					'description' => $txt['admin_members_list'],
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'all']),
					'selected' => $subAction === 'all',
				],
				'search' => [
					'label' => $txt['mlist_search'],
					'description' => $txt['admin_members_list'],
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'search']),
					'selected' => $subAction === 'search' || $subAction === 'query',
				],
				'approve' => [
					'label' => sprintf($txt['admin_browse_awaiting_approval'], $context['awaiting_approval']),
					'description' => $txt['admin_browse_approve_desc'],
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'browse', 'type' => 'approve']),
					'disabled' => !$context['show_approve'] && ($subAction !== 'browse' || $this->_req->getQuery('type') !== 'approve'),
					'selected' => $subAction === 'browse' && $this->_req->getQuery('type') === 'approve',
				],
				'activate' => [
					'label' => sprintf($txt['admin_browse_awaiting_activate'], $context['awaiting_activation']),
					'description' => $txt['admin_browse_activate_desc'],
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'browse', 'type' => 'activate']),
					'disabled' => !$context['show_activate'] && ($subAction !== 'browse' || $this->_req->query->type !== 'activate'),
					'selected' => $subAction === 'browse' && $this->_req->getQuery('type') === 'activate',
				],
			]
		]);

		// Call integrate_manage_members
		call_integration_hook('integrate_manage_members', [&$subActions]);

		// Off we go
		$action->dispatch($subAction);
	}

	/**
	 * View all members list. It allows sorting on several columns, and deletion of
	 * selected members.
	 *
	 * - It also handles the search query sent by ?action=admin;area=viewmembers;sa=search.
	 * - Called by ?action=admin;area=viewmembers;sa=all or ?action=admin;area=viewmembers;sa=query.
	 * - Requires the moderate_forum permission.
	 *
	 * @event integrate_list_member_list
	 * @event integrate_view_members_params passed $params
	 * @uses the view_members sub template of the ManageMembers template.
	 */
	public function action_list(): void
	{
		global $txt, $context, $modSettings;

		// Set the current sub action.
		$context['sub_action'] = $this->_req->getQuery('sa', 'strval', $this->_req->getPost('sa', 'strval', 'all'));

		// Are we performing a mass action?
		if (isset($this->_req->post->maction_on_members, $this->_req->post->maction) && !empty($this->_req->post->members))
		{
			$this->_multiMembersAction();
		}

		// Check input after a member search has been submitted.
		if ($context['sub_action'] === 'query')
		{
			// Retrieving the membergroups and postgroups.
			require_once(SUBSDIR . '/Membergroups.subs.php');
			$groups = getBasicMembergroupData([], ['moderator'], null, true);

			$context['membergroups'] = $groups['membergroups'];
			$context['postgroups'] = $groups['groups'];
			unset($groups);

			// Some data about the form fields and how they are linked to the database.
			$params = [
				'mem_id' => [
					'db_fields' => ['id_member'],
					'type' => 'int',
					'range' => true
				],
				'age' => [
					'db_fields' => ['birthdate'],
					'type' => 'age',
					'range' => true
				],
				'posts' => [
					'db_fields' => ['posts'],
					'type' => 'int',
					'range' => true
				],
				'reg_date' => [
					'db_fields' => ['date_registered'],
					'type' => 'date',
					'range' => true
				],
				'last_online' => [
					'db_fields' => ['last_login'],
					'type' => 'date',
					'range' => true
				],
				'activated' => [
					'db_fields' => ['is_activated'],
					'type' => 'checkbox',
					'values' => ['0', '1', '11'],
				],
				'membername' => [
					'db_fields' => ['member_name', 'real_name'],
					'type' => 'string'
				],
				'email' => [
					'db_fields' => ['email_address'],
					'type' => 'string'
				],
				'website' => [
					'db_fields' => ['website_title', 'website_url'],
					'type' => 'string'
				],
				'ip' => [
					'db_fields' => ['member_ip'],
					'type' => 'string'
				]
			];

			$range_trans = [
				'--' => '<',
				'-' => '<=',
				'=' => '=',
				'+' => '>=',
				'++' => '>'
			];

			call_integration_hook('integrate_view_members_params', [&$params]);

			$search_params = [];
			if ($context['sub_action'] === 'query' && !empty($this->_req->query->params) && empty($this->_req->post->types))
			{
				$search_params = @json_decode(base64_decode($this->_req->query->params), true);
			}
			elseif (!empty($this->_req->post))
			{
				$search_params['types'] = $this->_req->post->types;
				foreach (array_keys($params) as $param_name)
				{
					if (isset($this->_req->post->{$param_name}))
					{
						$search_params[$param_name] = $this->_req->post->{$param_name};
					}
				}
			}

			$search_url_params = isset($search_params) ? base64_encode(json_encode($search_params)) : null;

			// @todo Validate a little more.
			// Loop through every field of the form.
			$query_parts = [];
			$where_params = [];
			foreach ($params as $param_name => $param_info)
			{
				// Not filled in?
				if (!isset($search_params[$param_name]) || $search_params[$param_name] === '')
				{
					continue;
				}

				// Make sure numeric values are really numeric.
				if (in_array($param_info['type'], ['int', 'age']))
				{
					$search_params[$param_name] = (int) $search_params[$param_name];
				}
				// Date values have to match the specified format.
				elseif ($param_info['type'] === 'date')
				{
					// Check if this date format is valid.
					if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $search_params[$param_name]) == 0)
					{
						continue;
					}

					$search_params[$param_name] = strtotime($search_params[$param_name]);
				}

				// Those values that are in some kind of range (<, <=, =, >=, >).
				if (!empty($param_info['range']))
				{
					// Default to '=', just in case...
					if (empty($range_trans[$search_params['types'][$param_name]]))
					{
						$search_params['types'][$param_name] = '=';
					}

					// Handle special case 'age'.
					if ($param_info['type'] === 'age')
					{
						// All people that were born between $lowerlimit and $upperlimit are currently the specified age.
						$datearray = getdate(forum_time());
						$upperlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $search_params[$param_name], $datearray['mon'], $datearray['mday']);
						$lowerlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $search_params[$param_name] - 1, $datearray['mon'], $datearray['mday']);

						if (in_array($search_params['types'][$param_name], ['-', '--', '=']))
						{
							$query_parts[] = ($param_info['db_fields'][0]) . ' > {string:' . $param_name . '_minlimit}';
							$where_params[$param_name . '_minlimit'] = ($search_params['types'][$param_name] === '--' ? $upperlimit : $lowerlimit);
						}

						if (in_array($search_params['types'][$param_name], ['+', '++', '=']))
						{
							$query_parts[] = ($param_info['db_fields'][0]) . ' <= {string:' . $param_name . '_pluslimit}';
							$where_params[$param_name . '_pluslimit'] = ($search_params['types'][$param_name] === '++' ? $lowerlimit : $upperlimit);

							// Make sure that members that didn't set their birth year are not queried.
							$query_parts[] = ($param_info['db_fields'][0]) . ' > {date:dec_zero_date}';
							$where_params['dec_zero_date'] = '0004-12-31';
						}
					}
					// Special case - equals a date.
					elseif ($param_info['type'] === 'date' && $search_params['types'][$param_name] === '=')
					{
						$query_parts[] = $param_info['db_fields'][0] . ' > ' . $search_params[$param_name] . ' AND ' . $param_info['db_fields'][0] . ' < ' . ($search_params[$param_name] + 86400);
					}
					else
					{
						$query_parts[] = $param_info['db_fields'][0] . ' ' . $range_trans[$search_params['types'][$param_name]] . ' ' . $search_params[$param_name];
					}
				}
				// Checkboxes.
				elseif ($param_info['type'] === 'checkbox')
				{
					// Each checkbox or no checkbox at all is checked -> ignore.
					if (!is_array($search_params[$param_name]) || $search_params[$param_name] === [] || count($search_params[$param_name]) === count($param_info['values']))
					{
						continue;
					}

					$query_parts[] = ($param_info['db_fields'][0]) . ' IN ({array_string:' . $param_name . '_check})';
					$where_params[$param_name . '_check'] = $search_params[$param_name];
				}
				else
				{
					// Replace the wildcard characters ('*' and '?') into MySQL ones.
					$parameter = strtolower(strtr(Util::htmlspecialchars($search_params[$param_name], ENT_QUOTES), ['%' => '\%', '_' => '\_', '*' => '%', '?' => '_']));

					$query_parts[] = '({column_case_insensitive:' . implode('} LIKE {string_case_insensitive:' . $param_name . '_normal} OR {column_case_insensitive:', $param_info['db_fields']) . '} LIKE {string_case_insensitive:' . $param_name . '_normal})';

					$where_params[$param_name . '_normal'] = '%' . $parameter . '%';
				}
			}

			// Set up the membergroup query part.
			$mg_query_parts = [];

			// Primary membergroups, but only if at least was not selected.
			if (!empty($search_params['membergroups'][1]) && count($context['membergroups']) !== count($search_params['membergroups'][1]))
			{
				$mg_query_parts[] = 'mem.id_group IN ({array_int:group_check})';
				$where_params['group_check'] = $search_params['membergroups'][1];
			}

			// Additional membergroups (these are only relevant if not all primary groups where selected!).
			if (!empty($search_params['membergroups'][2]) && (empty($search_params['membergroups'][1]) || count($context['membergroups']) !== count($search_params['membergroups'][1])))
			{
				foreach ($search_params['membergroups'][2] as $mg)
				{
					$mg_query_parts[] = 'FIND_IN_SET({int:add_group_' . $mg . '}, mem.additional_groups) != 0';
					$where_params['add_group_' . $mg] = $mg;
				}
			}

			// Combine the one or two membergroup parts into one query part linked with an OR.
			if ($mg_query_parts !== [])
			{
				$query_parts[] = '(' . implode(' OR ', $mg_query_parts) . ')';
			}

			// Get all selected post count related membergroups.
			if (!empty($search_params['postgroups']) && count($search_params['postgroups']) !== count($context['postgroups']))
			{
				$query_parts[] = 'id_post_group IN ({array_int:post_groups})';
				$where_params['post_groups'] = $search_params['postgroups'];
			}

			// Construct the where part of the query.
			$where = $query_parts === [] ? '1=1' : implode('
				AND ', $query_parts);
		}
		else
		{
			$search_url_params = null;
		}

		// Construct the additional URL part with the query info in it.
		$context['params_url'] = $context['sub_action'] === 'query' ? ['sa' => 'query', 'params' => $search_url_params] : [];

		// Get the title and sub template ready..
		$context['page_title'] = $txt['admin_members'];
		$where_params = $where_params ?? [];

		$listOptions = [
			'id' => 'member_list',
			'title' => $txt['members_list'],
			'items_per_page' => $modSettings['defaultMaxMembers'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers'] + $context['params_url']),
			'default_sort_col' => 'user_name',
			'get_items' => [
				'file' => SUBSDIR . '/Members.subs.php',
				'function' => 'list_getMembers',
				'params' => [
					$where ?? '1=1',
					$where_params,
				],
			],
			'get_count' => [
				'file' => SUBSDIR . '/Members.subs.php',
				'function' => 'list_getNumMembers',
				'params' => [
					$where ?? '1=1',
					$where_params,
				],
			],
			'columns' => [
				'id_member' => [
					'header' => [
						'value' => $txt['member_id'],
					],
					'data' => [
						'db' => 'id_member',
					],
					'sort' => [
						'default' => 'id_member',
						'reverse' => 'id_member DESC',
					],
				],
				'user_name' => [
					'header' => [
						'value' => $txt['username'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => '%1$d', 'name' => '%2$s']) . '">%2$s</a>',
							'params' => [
								'id_member' => false,
								'member_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_name',
						'reverse' => 'member_name DESC',
					],
				],
				'display_name' => [
					'header' => [
						'value' => $txt['display_name'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => '%1$d']) . '">%2$s</a>',
							'params' => [
								'id_member' => false,
								'real_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'real_name',
						'reverse' => 'real_name DESC',
					],
				],
				'email' => [
					'header' => [
						'value' => $txt['email_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="mailto:%1$s">%1$s</a>',
							'params' => [
								'email_address' => true,
							],
						],
					],
					'sort' => [
						'default' => 'email_address',
						'reverse' => 'email_address DESC',
					],
				],
				'ip' => [
					'header' => [
						'value' => $txt['ip_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('action', ['action' => 'trackip', 'searchip' => '%1$s']) . '">%1$s</a>',
							'params' => [
								'member_ip' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_ip',
						'reverse' => 'member_ip DESC',
					],
				],
				'last_active' => [
					'header' => [
						'value' => $txt['viewmembers_online'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							require_once(SUBSDIR . '/Members.subs.php');

							// Calculate number of days since last online.
							$difference = empty($rowData['last_login']) ? $txt['never'] : htmlTime($rowData['last_login']);

							// Show it in italics if they're not activated...
							if ($rowData['is_activated'] % 10 !== 1)
							{
								return sprintf('<em title="%1$s">%2$s</em>', $txt['not_activated'], $difference);
							}

							return $difference;
						},
					],
					'sort' => [
						'default' => 'last_login DESC',
						'reverse' => 'last_login',
					],
				],
				'posts' => [
					'header' => [
						'value' => $txt['member_postcount'],
					],
					'data' => [
						'db' => 'posts',
					],
					'sort' => [
						'default' => 'posts',
						'reverse' => 'posts DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'function' => static fn($rowData) => '<input type="checkbox" name="members[]" value="' . $rowData['id_member'] . '" class="input_check" ' . ($rowData['id_member'] === User::$info->id || $rowData['id_group'] == 1 || in_array(1, explode(',', $rowData['additional_groups'])) ? 'disabled="disabled"' : '') . ' />',
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers'] + $context['params_url']),
				'include_start' => true,
				'include_sort' => true,
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => template_users_multiactions($this->_getGroups()),
					'class' => 'flow_flex_additional_row',
				],
			],
		];

		// Without enough permissions, don't show 'delete members' checkboxes.
		if (!allowedTo('profile_remove_any'))
		{
			unset($listOptions['cols']['check'], $listOptions['form'], $listOptions['additional_rows']);
		}

		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'member_list';
	}

	/**
	 * Handle mass action processing on a group of members
	 *
	 * - Deleting members
	 * - Group changes
	 * - Banning
	 */
	protected function _multiMembersAction(): void
	{
		global $txt;

		// @todo add a token too?
		checkSession();

		// Clean the input.
		$members = [];
		foreach ($this->_req->post->members as $value)
		{
			// Don't delete yourself, idiot.
			if ($this->_req->post->maction === 'delete' && $value == $this->user->id)
			{
				continue;
			}

			$members[] = (int) $value;
		}

		$members = array_filter($members);

		// No members, nothing to do.
		if (empty($members))
		{
			return;
		}

		// Are we performing a delete?
		if ($this->_req->post->maction === 'delete' && allowedTo('profile_remove_any'))
		{
			// Delete all the selected members.
			require_once(SUBSDIR . '/Members.subs.php');
			deleteMembers($members, true);
		}

		// Are we changing groups?
		if (in_array($this->_req->post->maction, ['pgroup', 'agroup']) && allowedTo('manage_membergroups'))
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');

			$groups = ['p', 'a'];
			foreach ($groups as $group)
			{
				if ($this->_req->post->maction == $group . 'group' && !empty($this->_req->post->new_membergroup))
				{
					$type = $group === 'p' ? 'force_primary' : 'only_additional';

					// Change all the selected members' group.
					if ($this->_req->post->new_membergroup != -1)
					{
						addMembersToGroup($members, $this->_req->post->new_membergroup, $type, true);
					}
					else
					{
						removeMembersFromGroups($members, null, true);
					}
				}
			}
		}

		// Are we banning?
		if (in_array($this->_req->post->maction, ['ban_names', 'ban_mails', 'ban_ips', 'ban_names_mails']) && allowedTo('manage_bans'))
		{
			require_once(SUBSDIR . '/Bans.subs.php');
			require_once(SUBSDIR . '/Members.subs.php');

			$ban_group_id = insertBanGroup([
				'name' => $txt['admin_ban_name'],
				'cannot' => [
					'access' => 1,
					'register' => 0,
					'post' => 0,
					'login' => 0,
				],
				'db_expiration' => 'NULL',
				'reason' => '',
				'notes' => '',
			]);

			$ban_name = in_array($this->_req->post->maction, ['ban_names', 'ban_names_mails']);
			$ban_email = in_array($this->_req->post->maction, ['ban_mails', 'ban_names_mails']);
			$ban_ips = $this->_req->post->maction === 'ban_ips';
			$suggestions = [];

			if ($ban_email)
			{
				$suggestions[] = 'email';
			}

			if ($ban_name)
			{
				$suggestions[] = 'user';
			}

			if ($ban_ips)
			{
				$suggestions[] = 'main_ip';
			}

			$members_data = getBasicMemberData($members, ['moderation' => true]);
			foreach ($members_data as $member)
			{
				saveTriggers([
					'main_ip' => $ban_ips ? $member['member_ip'] : '',
					'hostname' => '',
					'email' => $ban_email ? $member['email_address'] : '',
					'user' => $ban_name ? $member['member_name'] : '',
					'ban_suggestions' => $suggestions,
				], $ban_group_id, $ban_name ? $member['id_member'] : 0);
			}
		}
	}

	/**
	 * Prepares the list of groups to be used in the dropdown for "mass actions".
	 *
	 * @return array
	 */
	protected function _getGroups(): array
	{
		global $txt;

		require_once(SUBSDIR . '/Membergroups.subs.php');

		$member_groups = getGroupsList();

		// Better remove admin membergroup...and set it to a "remove all"
		$member_groups[1] = [
			'id' => -1,
			'name' => $txt['remove_groups'],
			'is_primary' => 0,
		];

		// no primary is tricky...
		$member_groups[0] = [
			'id' => 0,
			'name' => '',
			'is_primary' => 1,
		];

		return $member_groups;
	}

	/**
	 * Search the member list, using one or more criteria.
	 *
	 * What it does:
	 *
	 * - Called by ?action=admin;area=viewmembers;sa=search.
	 * - Requires the moderate_forum permission.
	 * - form is submitted to action=admin;area=viewmembers;sa=query.
	 *
	 * @uses the search_members sub template of the ManageMembers template.
	 */
	public function action_search(): void
	{
		global $context, $txt;

		// Get a list of all the membergroups and postgroups that can be selected.
		require_once(SUBSDIR . '/Membergroups.subs.php');
		$groups = getBasicMembergroupData([], ['moderator'], null, true);

		$context['membergroups'] = $groups['membergroups'];
		$context['postgroups'] = $groups['postgroups'];
		$context['page_title'] = $txt['admin_members'];
		$context['sub_template'] = 'search_members';

		unset($groups);
	}

	/**
	 * List all members who are awaiting approval / activation, sortable on different columns.
	 *
	 * What it does:
	 *
	 * - It allows instant approval or activation of (a selection of) members.
	 * - Called by ?action=admin;area=viewmembers;sa=browse;type=approve
	 * or ?action=admin;area=viewmembers;sa=browse;type=activate.
	 * - The form submits to ?action=admin;area=viewmembers;sa=approve.
	 * - Requires the moderate_forum permission.
	 *
	 * @event integrate_list_approve_list
	 * @uses the admin_browse sub template of the ManageMembers template.
	 */
	public function action_browse(): void
	{
		global $txt, $context, $modSettings;

		// Not a lot here!
		$context['page_title'] = $txt['admin_members'];
		$context['sub_template'] = 'admin_browse';
		$context['browse_type'] = $this->_req->query->type ?? (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? 'activate' : 'approve');

		if (isset($context['tabs'][$context['browse_type']]))
		{
			$context['tabs'][$context['browse_type']]['is_selected'] = true;
		}

		// Allowed filters are those we can have, in theory.
		$context['allowed_filters'] = $context['browse_type'] === 'approve' ? [3, 4, 5] : [0, 2];
		$context['current_filter'] = isset($this->_req->query->filter) && in_array($this->_req->query->filter, $context['allowed_filters']) && !empty($context['activation_numbers'][$this->_req->query->filter]) ? (int) $this->_req->query->filter : -1;

		// Sort out the different sub areas that we can actually filter by.
		$context['available_filters'] = [];
		foreach ($context['activation_numbers'] as $type => $amount)
		{
			// We have some of these...
			if (!in_array($type, $context['allowed_filters']))
			{
				continue;
			}

			if ($amount <= 0)
			{
				continue;
			}

			$context['available_filters'][] = [
				'type' => $type,
				'amount' => $amount,
				'desc' => $txt['admin_browse_filter_type_' . $type] ?? '?',
				'selected' => $type === $context['current_filter']
			];
		}

		// If the filter was not sent, set it to whatever has people in it!
		if ($context['current_filter'] == -1 && !empty($context['available_filters'][0]['amount']))
		{
			$context['current_filter'] = $context['available_filters'][0]['type'];
			$context['available_filters'][0]['selected'] = true;
		}

		// This little variable is used to determine if we should flag where we are looking.
		$context['show_filter'] = ($context['current_filter'] != 0 && $context['current_filter'] != 3) || count($context['available_filters']) > 1;

		// The columns that can be sorted.
		$context['columns'] = [
			'id_member' => ['label' => $txt['admin_browse_id']],
			'member_name' => ['label' => $txt['admin_browse_username']],
			'email_address' => ['label' => $txt['admin_browse_email']],
			'member_ip' => ['label' => $txt['admin_browse_ip']],
			'date_registered' => ['label' => $txt['admin_browse_registered']],
		];

		// Are we showing duplicate information?
		if (isset($this->_req->query->showdupes))
		{
			$_SESSION['showdupes'] = (int) $this->_req->query->showdupes;
		}

		$context['show_duplicates'] = !empty($_SESSION['showdupes']);

		// Determine which actions we should allow on this page.
		$context['allowed_actions'] = [];
		if ($context['browse_type'] === 'approve')
		{
			// If we are approving deleted accounts we have a slightly different list... actually a mirror ;)
			if ($context['current_filter'] == 4)
			{
				$context['allowed_actions'] = [
					'reject' => $txt['admin_browse_w_approve_deletion'],
					'ok' => $txt['admin_browse_w_reject_delete'],
				];
			}
			else
			{
				$context['allowed_actions'] = [
					'ok' => $txt['admin_browse_w_approve'],
					'okemail' => $txt['admin_browse_w_approve'] . ' ' . $txt['admin_browse_w_email'],
					'require_activation' => $txt['admin_browse_w_approve_require_activate'],
					'reject' => $txt['admin_browse_w_reject'],
					'rejectemail' => $txt['admin_browse_w_reject'] . ' ' . $txt['admin_browse_w_email'],
				];
			}
		}
		elseif ($context['browse_type'] === 'activate')
		{
			$context['allowed_actions'] = [
				'ok' => $txt['admin_browse_w_activate'],
				'okemail' => $txt['admin_browse_w_activate'] . ' ' . $txt['admin_browse_w_email'],
				'delete' => $txt['admin_browse_w_delete'],
				'deleteemail' => $txt['admin_browse_w_delete'] . ' ' . $txt['admin_browse_w_email'],
				'remind' => $txt['admin_browse_w_remind'] . ' ' . $txt['admin_browse_w_email'],
			];
		}

		// Create an option list for actions allowed to be done with selected members.
		$allowed_actions = '
			<option selected="selected" value="">' . $txt['admin_browse_with_selected'] . ':</option>
			<option value="" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['admin_browse_with_selected'])) . '</option>';

		foreach ($context['allowed_actions'] as $key => $desc)
		{
			$allowed_actions .= '
				<option value="' . $key . '">' . '&#10148;&nbsp;' . $desc . '</option>';
		}

		// Setup the Javascript function for selecting an action for the list.
		$javascript = '
			function onSelectChange()
			{
				if (document.forms.postForm.todo.value === "")
					return;

				var message = "";';

		// We have special messages for approving deletion of accounts - it's surprisingly logical - honest.
		if ($context['current_filter'] == 4)
		{
			$javascript .= '
				if (document.forms.postForm.todo.value.indexOf("reject") !== -1)
					message = "' . $txt['admin_browse_w_delete'] . '";
				else
					message = "' . $txt['admin_browse_w_reject'] . '";';
		}
		// Otherwise a nice standard message.
		else
		{
			$javascript .= '
				if (document.forms.postForm.todo.value.indexOf("delete") !== -1)
					message = "' . $txt['admin_browse_w_delete'] . '";
				else if (document.forms.postForm.todo.value.indexOf("reject") !== -1)
					message = "' . $txt['admin_browse_w_reject'] . '";
				else if (document.forms.postForm.todo.value == "remind")
					message = "' . $txt['admin_browse_w_remind'] . '";
				else
					message = "' . ($context['browse_type'] === 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate']) . '";';
		}

		$javascript .= '
				if (confirm(message + " ' . $txt['admin_browse_warn'] . '"))
					document.forms.postForm.submit();
			}';

		$listOptions = [
			'id' => 'approve_list',
			'items_per_page' => $modSettings['defaultMaxMembers'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'browse', 'type' => $context['browse_type'] . (empty($context['show_filter']) ? '' : ", 'filter' =>" . $context['current_filter'])]),
			'default_sort_col' => 'date_registered',
			'get_items' => [
				'file' => SUBSDIR . '/Members.subs.php',
				'function' => 'list_getMembers',
				'params' => [
					'is_activated = {int:activated_status}',
					['activated_status' => $context['current_filter']],
					$context['show_duplicates'],
				],
			],
			'get_count' => [
				'file' => SUBSDIR . '/Members.subs.php',
				'function' => 'list_getNumMembers',
				'params' => [
					'is_activated = {int:activated_status}',
					['activated_status' => $context['current_filter']],
				],
			],
			'columns' => [
				'id_member' => [
					'header' => [
						'value' => $txt['member_id'],
					],
					'data' => [
						'db' => 'id_member',
					],
					'sort' => [
						'default' => 'id_member',
						'reverse' => 'id_member DESC',
					],
				],
				'user_name' => [
					'header' => [
						'value' => $txt['username'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => '%1$d']) . '">%2$s</a>',
							'params' => [
								'id_member' => false,
								'member_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_name',
						'reverse' => 'member_name DESC',
					],
				],
				'email' => [
					'header' => [
						'value' => $txt['email_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="mailto:%1$s">%1$s</a>',
							'params' => [
								'email_address' => true,
							],
						],
					],
					'sort' => [
						'default' => 'email_address',
						'reverse' => 'email_address DESC',
					],
				],
				'ip' => [
					'header' => [
						'value' => $txt['ip_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('profile', ['action' => 'trackip', 'searchip' => '%1$s']) . '">%1$s</a>',
							'params' => [
								'member_ip' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_ip',
						'reverse' => 'member_ip DESC',
					],
				],
				'hostname' => [
					'header' => [
						'value' => $txt['hostname'],
					],
					'data' => [
						'function' => static fn($rowData) => host_from_ip($rowData['member_ip']),
						'class' => 'smalltext',
					],
				],
				'date_registered' => [
					'header' => [
						'value' => $context['current_filter'] == 4 ? $txt['viewmembers_online'] : $txt['date_registered'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $context;
							return standardTime($rowData[($context['current_filter'] == 4 ? 'last_login' : 'date_registered')]);
						},
					],
					'sort' => [
						'default' => $context['current_filter'] == 4 ? 'mem.last_login DESC' : 'date_registered DESC',
						'reverse' => $context['current_filter'] == 4 ? 'mem.last_login' : 'date_registered',
					],
				],
				'duplicates' => [
					'header' => [
						'value' => $txt['duplicates'],
						// Make sure it doesn't go too wide.
						'style' => 'width: 20%;',
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							$member_links = [];
							foreach ($rowData['duplicate_members'] as $member)
							{
								if ($member['id'])
								{
									$member_links[] = '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $member['id'], 'name' => $member['name']]) . '" ' . (empty($member['is_banned']) ? '' : 'class="alert"') . '>' . $member['name'] . '</a>';
								}
								else
								{
									$member_links[] = $member['name'] . ' (' . $txt['guest'] . ')';
								}
							}

							return implode(', ', $member_links);
						},
						'class' => 'smalltext',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="todoAction[]" value="%1$d" class="input_check" />',
							'params' => [
								'id_member' => false,
							],
						],
						'class' => 'centertext',
					],
				],
			],
			'javascript' => $javascript,
			'form' => [
				'href' => getUrl('action', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'approve', 'type' => $context['browse_type']]),
				'name' => 'postForm',
				'include_start' => true,
				'include_sort' => true,
				'hidden_fields' => [
					'orig_filter' => $context['current_filter'],
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'class' => 'flow_flex_additional_row',
					'value' => '
						<div class="submitbutton">
							<a class="linkbutton" href="' . getUrl('action', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'browse', 'showdupes' => $context['show_duplicates'] ? 0 : 1, 'type' => $context['browse_type'], '{session_data}'] + (empty($context['show_filter']) ? [] : ['filter' => $context['current_filter']])) . '">' . ($context['show_duplicates'] ? $txt['dont_check_for_duplicate'] : $txt['check_for_duplicate']) . '</a>
							<select name="todo" onchange="onSelectChange();">
								' . $allowed_actions . '
							</select>
							<noscript>
								<input type="submit" value="' . $txt['go'] . '" />
							</noscript>
						</div>
					',
				],
			],
		];

		// Pick what column to actually include if we're showing duplicates.
		if ($context['show_duplicates'])
		{
			unset($listOptions['columns']['email']);
		}
		else
		{
			unset($listOptions['columns']['duplicates']);
		}

		// Only show hostname on duplicates as it takes a lot of time.
		if (!$context['show_duplicates'] || !empty($modSettings['disableHostnameLookup']))
		{
			unset($listOptions['columns']['hostname']);
		}

		// Is there any need to show filters?
		if (isset($context['available_filters']))
		{
			$listOptions['list_menu'] = [
				'show_on' => 'top',
				'links' => []
			];

			foreach ($context['available_filters'] as $filter)
			{
				$listOptions['list_menu']['links'][] = [
					'is_selected' => $filter['selected'],
					'href' => getUrl('action', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'browse', 'type' => $context['browse_type'], 'filter' => $filter['type']]),
					'label' => $filter['desc'] . ' - ' . $filter['amount'] . ' ' . ($filter['amount'] == 1 ? $txt['user'] : $txt['users'])
				];
			}
		}

		// Now that we have all the options, create the list.
		createList($listOptions);
	}

	/**
	 * This function handles the approval, rejection, activation or deletion of members.
	 *
	 * What it does:
	 *
	 * - Called by ?action=admin;area=viewmembers;sa=approve.
	 * - Requires the moderate_forum permission.
	 * - Redirects to ?action=admin;area=viewmembers;sa=browse
	 * with the same parameters as the calling page.
	 */
	public function action_approve(): void
	{
		global $modSettings;

		// First, check our session.
		checkSession();

		require_once(SUBSDIR . '/Mail.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');

		// We also need to the login languages here - for emails.
		Txt::load('Login');

		// Start off clean
		$this->conditions = [];

		// Sort out where we are going...
		$original_filter = $this->_req->getPost('orig_filter', 'intval', null);
		$current_filter = $this->conditions['activated_status'] = $original_filter;

		$type = $this->_req->getQuery('type', 'trim', '');
		$filter = $this->_req->getPost('filter', 'trim', null);
		$sort = $this->_req->getRequest('sort', 'trim', '');
		$start = $this->_req->getRequest('start', 'intval', 0);
		$todoAction = $this->_req->getPost('todoAction');
		$time_passed = $this->_req->getPost('$time_passed', 'intval');
		$todo = $this->_req->getPost('todo', 'trim');

		// If we are applying a filter do just that - then redirect.
		if (isset($filter) && $filter !== $original_filter)
		{
			redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $type . ';sort=' . $sort . ';filter=' . $filter . ';start=' . $start);
		}

		// Nothing to do?
		if (!isset($todoAction) && !isset($time_passed))
		{
			redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $type . ';sort=' . $sort . ';filter=' . $current_filter . ';start=' . $start);
		}

		// Are we dealing with members who have been waiting for > set amount of time?
		if (isset($time_passed))
		{
			$this->conditions['time_before'] = time() - 86400 * $time_passed;
		}
		// Coming from checkboxes - validate the members passed through to us.
		else
		{
			$this->conditions['members'] = [];
			foreach ($todoAction as $id)
			{
				$this->conditions['members'][] = (int) $id;
			}
		}

		$data = retrieveMemberData($this->conditions);
		if ($data['member_count'] === 0)
		{
			redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $type . ';sort=' . $sort . ';filter=' . $current_filter . ';start=' . $start);
		}

		$this->member_info = $data['member_info'];
		$this->conditions['members'] = $data['members'];

		// What do we want to do with this application?
		switch ($todo)
		{
			// Are we activating or approving the members?
			case 'ok':
			case 'okemail':
				$this->_okMember();
				break;
			// Maybe we're sending it off for activation?
			case 'require_activation':
				$this->_requireMember();
				break;
			// Are we rejecting them?
			case 'reject':
			case 'rejectemail':
				$this->_rejectMember();
				break;
			// A simple delete?
			case 'delete':
			case 'deleteemail':
				$this->_deleteMember();
				break;
			// Remind them to activate their account?
			case 'remind':
				$this->_remindMember();
				break;
		}

		// Log what we did? Core features Moderation Logging must be enabled
		if (featureEnabled('ml') && in_array($todo, ['ok', 'okemail', 'require_activation', 'remind']))
		{
			$log_action = $todo === 'remind' ? 'remind_member' : 'approve_member';

			foreach ($this->member_info as $member)
			{
				logAction($log_action, ['member' => $member['id']], 'admin');
			}
		}

		// Although updateMemberStats *may* catch this, best to do it manually just in case (Doesn't always sort out unapprovedMembers).
		if (in_array($current_filter, [3, 4, 5]))
		{
			updateSettings(['unapprovedMembers' => ($modSettings['unapprovedMembers'] > $data['member_count'] ? $modSettings['unapprovedMembers'] - $data['member_count'] : 0)]);
		}

		// Update the member's stats. (but, we know the member didn't change their name.)
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberStats();

		// If they haven't been deleted, update the post group statistics on them...
		if (!in_array($todo, ['delete', 'deleteemail', 'reject', 'rejectemail', 'remind']))
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');
			updatePostGroupStats($this->conditions['members']);
		}

		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $type . ';sort=' . $sort . ';filter=' . $current_filter . ';start=' . $start);
	}

	/**
	 * Approve a member application
	 */
	private function _okMember(): void
	{
		// Approve / activate this member.
		approveMembers($this->conditions);

		// Check for email.
		if ($this->_req->post->todo === 'okemail')
		{
			foreach ($this->member_info as $member)
			{
				$replacements = [
					'NAME' => $member['name'],
					'USERNAME' => $member['username'],
					'PROFILELINK' => getUrl('profile', ['action' => 'profile', 'u' => $member['id'], 'name' => $member['name']]),
					'FORGOTPASSWORDLINK' => getUrl('action', ['action' => 'reminder']),
				];

				$emaildata = loadEmailTemplate('admin_approve_accept', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
			}
		}

		// Update the menu action cache so its forced to refresh
		Cache::instance()->remove('num_menu_errors');
	}

	/**
	 * Tell some members that they require activation of their account
	 */
	private function _requireMember(): void
	{
		require_once(SUBSDIR . '/Auth.subs.php');

		// We have to do this for each member I'm afraid.
		foreach ($this->member_info as $member)
		{
			$this->conditions['selected_member'] = $member['id'];

			// Generate a random activation code.
			$this->conditions['validation_code'] = generateValidationCode(14);

			// Set these members for activation - I know this includes two id_member checks but it's safer than bodging $condition ;).
			enforceReactivation($this->conditions);

			$replacements = [
				'USERNAME' => $member['name'],
				'ACTIVATIONLINK' => getUrl('action', ['action' => 'register', 'sa' => 'activate', 'u' => $member['id'], 'code' => $this->conditions['validation_code']]),
				'ACTIVATIONLINKWITHOUTCODE' => getUrl('action', ['action' => 'register', 'sa' => 'activate', 'u' => $member['id']]),
				'ACTIVATIONCODE' => $this->conditions['validation_code'],
			];

			$emaildata = loadEmailTemplate('admin_approve_activation', $replacements, $member['language']);
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}
	}

	/**
	 * Reject a set a member applications, maybe even tell them
	 */
	private function _rejectMember(): void
	{
		deleteMembers($this->conditions['members']);

		// Send email telling them they aren't welcome?
		if ($this->_req->post->todo === 'rejectemail')
		{
			foreach ($this->member_info as $member)
			{
				$replacements = [
					'USERNAME' => $member['name'],
				];

				$emaildata = loadEmailTemplate('admin_approve_reject', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
			}
		}
	}

	/**
	 * Deletes the members specified in the conditions array.
	 *
	 * What it does:
	 *
	 * - Called by the action_approve method.
	 * - Deletes the members specified in the conditions array.
	 * - Optionally sends email to notify the deleted members.
	 *
	 * @return void
	 */
	private function _deleteMember(): void
	{
		deleteMembers($this->conditions['members']);

		// Send email telling them they aren't welcome?
		if ($this->_req->post->todo === 'deleteemail')
		{
			foreach ($this->member_info as $member)
			{
				$replacements = [
					'USERNAME' => $member['name'],
				];

				$emaildata = loadEmailTemplate('admin_approve_delete', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
			}
		}
	}

	/**
	 * Remind a set of members that they have an activation email waiting
	 */
	private function _remindMember(): void
	{
		require_once(SUBSDIR . '/Auth.subs.php');

		foreach ($this->member_info as $member)
		{
			$this->conditions['selected_member'] = $member['id'];
			$this->conditions['validation_code'] = generateValidationCode(14);

			enforceReactivation($this->conditions);

			$replacements = [
				'USERNAME' => $member['name'],
				'ACTIVATIONLINK' => getUrl('action', ['action' => 'register', 'sa' => 'activate', 'u' => $member['id'], 'code' => $this->conditions['validation_code']]),
				'ACTIVATIONLINKWITHOUTCODE' => getUrl('action', ['action' => 'register', 'sa' => 'activate', 'u' => $member['id']]),
				'ACTIVATIONCODE' => $this->conditions['validation_code'],
			];

			$emaildata = loadEmailTemplate('admin_approve_remind', $replacements, $member['language']);
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
		}
	}
}
