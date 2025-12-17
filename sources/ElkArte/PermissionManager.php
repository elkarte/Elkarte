<?php

/**
 * PermissionManager is a direct replacement for loadAllPermissions() that was/is in ManagePermissions.subs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte;

/**
 * Class PermissionManager
 * Manages permissions within the system, including loading all permissions,
 * processing rules, and configuring hidden or relabeled permissions.
 */
class PermissionManager
{
	private array $context;
	private array $txt;
	private array $modSettings;
	private array $hiddenPermissions = [];
	private array $relabelPermissions = [];
	private Permissions $permissionsObject;

	/**
	 * Initializes the class instance by setting the provided context, text strings, and
	 * module settings. Also creates an instance of the Permissions object for further use.
	 *
	 * Passes by reference to account to any updated due to addons.
	 *
	 * @param array &$context A reference to the global context array used for storing
	 *                        runtime data and settings.
	 * @param array &$txt A reference to the array of text strings, typically used
	 *                    for localization or display purposes.
	 * @param array $modSettings An array of module configuration settings used to
	 *                           customize application behavior.
	 *
	 * @return void
	 */
	public function __construct(array &$context, array &$txt, array &$modSettings)
	{
		// Load the permissions object with global settings.
		$this->context = &$context;
		$this->txt = &$txt;
		$this->modSettings = $modSettings;
		$this->permissionsObject = new Permissions();
	}

	/**
	 * Loads all permissions and processes them to populate the context with structured permission data.
	 *
	 * @return void
	 */
	public function loadAllPermissions(): void
	{
		$permissionGroups = $this->getPermissionGroups();
		$permissionList = $this->getPermissionList();
		$leftPermissionGroups = $this->getLeftPermissionGroups();

		$this->setupHiddenPermissions();
		$this->setupRelabeledPermissions();

		// Provide a practical way to modify permissions.
		call_integration_hook('integrate_load_permissions', [
			&$permissionGroups,
			&$permissionList,
			&$leftPermissionGroups,
			&$this->hiddenPermissions,
			&$this->relabelPermissions
		]);

		// We need to know what permissions we can't give to guests.
		$illegalGuestPermissions = $this->permissionsObject->getIllegalGuestPermissions();

		$this->context['permissions'] = [];
		$this->context['hidden_permissions'] = [];

		$this->processPermissions($permissionList, $permissionGroups, $leftPermissionGroups, $illegalGuestPermissions);
	}

	/**
	 * Retrieves an array of permission groups categorized by membergroup and board.
	 *
	 * Note to Addon authors - you don't need to stick your permission group here if you don't
	 * mind having it as the last group on the page.
	 *
	 * @return array An associative array containing permission groups for membergroup and board,
	 * where each group includes a list of specific permissions.
	 */
	private function getPermissionGroups(): array
	{
		return [
			'membergroup' => [
				'general',
				'pm',
				'calendar',
				'maintenance',
				'member_admin',
				'profile',
			],
			'board' => [
				'general_board',
				'topic',
				'post',
				'poll',
				'notification',
				'attachment',
			],
		];
	}

	/**
	 * Retrieves a comprehensive list of permissions available for different user groups and boards,
	 * categorized by features such as general, profile, calendar, maintenance, topics, polls, attachments, etc.
	 *
	 * The format of this list is as follows:
	 * 'membergroup' => array(
	 *      'permissions_inside' => array(has_multiple_options, view_group),
	 * ),
	 *
	 * 'board' => array(
	 *      'permissions_inside' => array(has_multiple_options, view_group),
	 * );
	 *
	 * @return array Returns an associative array where permissions are grouped into major categories
	 *               such as 'membergroup' and 'board'. Each category contains specific permissions,
	 *               their default states, and associated groups or modules.
	 */
	private function getPermissionList(): array
	{
		return [
			'membergroup' => [
				'view_stats' => [false, 'general'],
				'view_mlist' => [false, 'general'],
				'who_view' => [false, 'general'],
				'search_posts' => [false, 'general'],
				'karma_edit' => [false, 'general'],
				'like_posts_stats' => [false, 'general'],
				'disable_censor' => [false, 'general'],
				'post_nofollow' => [false, 'general'],
				'pm_read' => [false, 'pm'],
				'pm_send' => [false, 'pm'],
				'send_email_to_members' => [false, 'pm'],
				'calendar_view' => [false, 'calendar'],
				'calendar_post' => [false, 'calendar'],
				'calendar_edit' => [true, 'calendar'],
				'admin_forum' => [false, 'maintenance'],
				'manage_boards' => [false, 'maintenance'],
				'manage_attachments' => [false, 'maintenance'],
				'manage_smileys' => [false, 'maintenance'],
				'edit_news' => [false, 'maintenance'],
				'access_mod_center' => [false, 'maintenance'],
				'moderate_forum' => [false, 'member_admin'],
				'manage_membergroups' => [false, 'member_admin'],
				'manage_permissions' => [false, 'member_admin'],
				'manage_bans' => [false, 'member_admin'],
				'send_mail' => [false, 'member_admin'],
				'issue_warning' => [false, 'member_admin'],
				'profile_view' => [true, 'profile'],
				'profile_identity' => [true, 'profile'],
				'profile_extra' => [true, 'profile'],
				'profile_title' => [true, 'profile'],
				'profile_remove' => [true, 'profile'],
				'profile_set_avatar' => [false, 'profile'],
				'approve_emails' => [false, 'member_admin'],
			],
			'board' => [
				'moderate_board' => [false, 'general_board'],
				'approve_posts' => [false, 'general_board'],
				'post_new' => [false, 'topic'],
				'post_unapproved_topics' => [false, 'topic'],
				'post_unapproved_replies' => [true, 'topic'],
				'post_reply' => [true, 'topic'],
				'merge_any' => [false, 'topic'],
				'split_any' => [false, 'topic'],
				'make_sticky' => [false, 'topic'],
				'move' => [true, 'topic'],
				'lock' => [true, 'topic'],
				'remove' => [true, 'topic'],
				'modify_replies' => [false, 'topic'],
				'delete_replies' => [false, 'topic'],
				'announce_topic' => [false, 'topic'],
				'delete' => [true, 'post'],
				'modify' => [true, 'post'],
				'report_any' => [false, 'post'],
				'poll_view' => [false, 'poll'],
				'poll_vote' => [false, 'poll'],
				'poll_post' => [false, 'poll'],
				'poll_add' => [true, 'poll'],
				'poll_edit' => [true, 'poll'],
				'poll_lock' => [true, 'poll'],
				'poll_remove' => [true, 'poll'],
				'mark_any_notify' => [false, 'notification'],
				'mark_notify' => [false, 'notification'],
				'view_attachments' => [false, 'attachment'],
				'post_unapproved_attachments' => [false, 'attachment'],
				'post_attachment' => [false, 'attachment'],
				'postby_email' => [false, 'topic'],
				'like_posts' => [false, 'topic'],
			],
		];
	}

	/**
	 * Retrieves a list of permission group categories that are commonly used for defining
	 * and managing access controls within the system.
	 *
	 * @return array Returns an indexed array of strings, where each string represents a
	 *               permission group category, such as 'general', 'calendar', 'maintenance',
	 *               'member_admin', 'topic', or 'post'.
	 */
	private function getLeftPermissionGroups(): array
	{
		// All permission groups that will be shown in the left column
		return [
			'general',
			'calendar',
			'maintenance',
			'member_admin',
			'topic',
			'post',
		];
	}

	/**
	 * Configures a list of permissions that should be hidden based on the current system settings
	 * and enabled features. This method updates the hidden permissions array dynamically depending
	 * on feature flags and system configurations such as calendar, warnings, post-moderation, and attachments.
	 *
	 * @return void No value is returned. The method modifies the internal $hiddenPermissions property directly
	 *              by adding or merging permissions that are determined to be unavailable or irrelevant.
	 */
	private function setupHiddenPermissions(): void
	{
		// Some permissions are hidden if core features are off.
		if (!featureEnabled('cd'))
		{
			$this->hiddenPermissions = array_merge($this->hiddenPermissions, [
				'calendar_view',
				'calendar_post',
				'calendar_edit'
			]);
		}

		if (!featureEnabled('w'))
		{
			$this->hiddenPermissions[] = 'issue_warning';
		}

		if (featureEnabled('k') === false)
		{
			$this->hiddenPermissions[] = 'karma_edit';
		}

		if (featureEnabled('l') === false)
		{
			$this->hiddenPermissions[] = 'like_posts';
		}

		if (featureEnabled('pe') === false)
		{
			$this->hiddenPermissions[] = 'approve_emails';
			$this->hiddenPermissions[] = 'postby_email';
		}

		// Post moderation?
		if (!$this->modSettings['postmod_active'])
		{
			$this->hiddenPermissions[] = 'approve_posts';
			$this->hiddenPermissions[] = 'post_unapproved_topics';
			$this->hiddenPermissions[] = 'post_unapproved_replies';
			$this->hiddenPermissions[] = 'post_unapproved_attachments';
		}

		// Are attachments enabled?
		if (empty($this->modSettings['attachmentEnable']))
		{
			$this->hiddenPermissions = array_merge($this->hiddenPermissions, [
				'manage_attachments',
				'view_attachments',
				'post_unapproved_attachments',
				'post_attachment'
			]);
		}
	}

	/**
	 * Configures relabeled permissions based on the post-moderation setting.
	 *
	 * @return void
	 */
	private function setupRelabeledPermissions(): void
	{
		if ($this->modSettings['postmod_active'])
		{
			// If we show them on classic view, we change the name.
			$this->relabelPermissions = [
				'post_new' => 'auto_approve_topics',
				'post_reply' => 'auto_approve_replies',
				'post_attachment' => 'auto_approve_attachments'
			];
		}
	}

	/**
	 * Processes and organizes permissions by type, preparing them for display and further operations.
	 *
	 * @param array $permissionList An associative array where keys represent the type of permissions and values are the respective permissions lists.
	 * @param array $permissionGroups An array of groups that define the organization of permissions.
	 * @param array $leftPermissionGroups An array of permission groups that are specifically marked as "left" groups.
	 * @param array $illegalGuestPermissions An array of permissions that are prohibited for guest users.
	 *
	 * @return void
	 */
	private function processPermissions(array $permissionList, array $permissionGroups, array $leftPermissionGroups, array $illegalGuestPermissions): void
	{
		foreach ($permissionList as $permissionType => $currentPermissionList)
		{
			$this->context['permissions'][$permissionType] = [
				'id' => $permissionType,
				'columns' => []
			];

			$this->processPermissionType(
				$permissionType,
				$currentPermissionList,
				$permissionGroups,
				$leftPermissionGroups,
				$illegalGuestPermissions
			);

			$this->finalizePermissionColumns($permissionType);
		}
	}

	/**
	 * Processes the permission type by iterating through the current permission list, validating permissions,
	 * and organizing them into predefined groups. Handles the creation of permission groups and
	 * updates the context with the appropriate permissions.
	 *
	 * @param string $permissionType The type/category of permissions being processed.
	 * @param array $currentPermissionList The list of current permissions to be processed,
	 *                                     with permission keys and their associated metadata.
	 * @param array $permissionGroups A reference array where processed permission groups are stored.
	 * @param array $leftPermissionGroups A list of remaining permissions groups to be processed.
	 * @param array $illegalGuestPermissions A set of permissions that are restricted for guest users.
	 *
	 * @return void
	 */
	private function processPermissionType(string $permissionType, array $currentPermissionList, array $permissionGroups, array $leftPermissionGroups, array $illegalGuestPermissions): void
	{
		foreach ($currentPermissionList as $permission => $permissionArray)
		{
			if ($this->shouldSkipGuestPermission($permission, $illegalGuestPermissions))
			{
				continue;
			}

			// What groups will this permission be in?
			$ownGroup = $permissionArray[1];
			$position = $this->getPermissionPosition($ownGroup, $leftPermissionGroups);

			$this->ensurePermissionGroupExists($permissionType, $ownGroup, $permissionGroups);
			$this->createPermissionGroups($permissionType, $position, $ownGroup);
			$this->addPermissionToContext($permissionType, $position, $ownGroup, $permission, $permissionArray);
		}
	}

	/**
	 * Determines whether a given permission should be skipped for guest users based on
	 * the current context and a predefined list of illegal guest permissions.
	 *
	 * @param string $permission The specific permission being evaluated.
	 * @param array $illegalGuestPermissions A list of permissions that are not allowed for guest users.
	 *
	 * @return bool Returns true if the permission should be skipped, otherwise false.
	 */
	private function shouldSkipGuestPermission(string $permission, array $illegalGuestPermissions): bool
	{
		// If this is a guest permission, we don't do it if it's the guest group.
		return isset($this->context['group']['id'])
			&& $this->context['group']['id'] === -1
			&& in_array($permission, $illegalGuestPermissions, true);
	}

	/**
	 * Determines the position of a permission group within the remaining permission groups.
	 * This function assigns a position value based on whether the specified group exists
	 * in the list of left permission groups.
	 *
	 * @param string $ownGroup The name of the permission group being evaluated.
	 * @param array $leftPermissionGroups The array of permission groups that have yet to be processed.
	 *
	 * @return int Returns 1 if the group is not found in the list of left permission groups,
	 *             and 0 if it is found.
	 */
	private function getPermissionPosition(string $ownGroup, array $leftPermissionGroups): int
	{
		return !in_array($ownGroup, $leftPermissionGroups, true) ? 1 : 0;
	}

	/**
	 * Ensures the existence of a specific permission group within the provided permission groups array.
	 * If the group does not exist, it is initialized.
	 *
	 * @param string $permissionType The type/category of permissions to which the group belongs.
	 * @param string $ownGroup The name of the permission group to verify or initialize.
	 * @param array &$permissionGroups A reference to the permission groups array where the group should exist or be added.
	 *
	 * @return void
	 */
	private function ensurePermissionGroupExists(string $permissionType, string $ownGroup, array &$permissionGroups): void
	{
		// First, Do these groups actually exist - if not add them.
		if (!isset($permissionGroups[$permissionType][$ownGroup]))
		{
			$permissionGroups[$permissionType][$ownGroup] = true;
		}
	}

	/**
	 * Creates and initializes permission groups for the specified permission type.
	 * It ensures that groups are properly configured in the permissions context,
	 * including their metadata and default properties.
	 *
	 * @param string $permissionType The type/category of permissions for which groups are being created.
	 * @param int $position The position/index in the permissions columns where the group is to be placed.
	 * @param string $ownGroup The name of the group that is being processed and added to the context.
	 *
	 * @return void
	 */
	private function createPermissionGroups(string $permissionType, int $position, string $ownGroup): void
	{
		$bothGroups = $this->getBothGroups($ownGroup);

		foreach ($bothGroups as $group)
		{
			if (!isset($this->context['permissions'][$permissionType]['columns'][$position][$group]['type']))
			{
				$this->context['permissions'][$permissionType]['columns'][$position][$group] = [
					'type' => $permissionType,
					'id' => $group,
					'name' => $this->txt['permissiongroup_' . $group],
					'icon' => $this->txt['permissionicon_' . $group] ?? $this->txt['permissionicon'],
					'help' => $this->txt['permissionhelp_' . $group] ?? '',
					'hidden' => false,
					'permissions' => []
				];
			}
		}
	}

	/**
	 * Retrieves both groups based on the provided group and the current context.
	 * If the group context does not meet specific conditions, only the own group is returned.
	 * Otherwise, an alternative group is returned.
	 *
	 * @param string $ownGroup The identifier of the group to be evaluated.
	 *
	 * @return array An associative array containing the applicable group(s),
	 *               either keyed as 'own' or 'any', depending on the context.
	 */
	private function getBothGroups(string $ownGroup): array
	{
		// Guests can only have any registered users both
		if (!isset($this->context['group']['id']) || $this->context['group']['id'] !== -1)
		{
			return ['own' => $ownGroup];
		}

		return ['any' => $ownGroup];
	}

	/**
	 * Adds a specific permission to the context structure, organizing it into the appropriate
	 * group and position. Handles labeling, metadata, and visibility of the permission.
	 *
	 * @param string $permissionType The type/category of permissions to which the permission belongs.
	 * @param int $position The position/index where the permission should be placed within a group.
	 * @param string $ownGroup The name of the group to which the permission is assigned.
	 * @param string $permission The unique identifier of the permission being added.
	 * @param array $permissionArray An array containing metadata about the permission,
	 *                               such as whether it applies to "own" or "any" actions.
	 *
	 * @return void
	 */
	private function addPermissionToContext(string $permissionType, int $position, string $ownGroup, string $permission, array $permissionArray): void
	{
		$isHidden = in_array($permission, $this->hiddenPermissions, true);

		// This is where we set up the permission.
		$this->context['permissions'][$permissionType]['columns'][$position][$ownGroup]['permissions'][$permission] = [
			'id' => $permission,
			'name' => !isset($this->relabelPermissions[$permission])
				? $this->txt['permissionname_' . $permission]
				: $this->txt[$this->relabelPermissions[$permission]],
			'show_help' => isset($this->txt['permissionhelp_' . $permission]),
			'note' => $this->txt['permissionnote_' . $permission] ?? '',
			'has_own_any' => $permissionArray[0],
			'own' => [
				'id' => $permission . '_own',
				'name' => $permissionArray[0] ? $this->txt['permissionname_' . $permission . '_own'] : ''
			],
			'any' => [
				'id' => $permission . '_any',
				'name' => $permissionArray[0] ? $this->txt['permissionname_' . $permission . '_any'] : ''
			],
			'hidden' => $isHidden,
		];

		if ($isHidden)
		{
			$this->addToHiddenPermissions($permission, $permissionArray[0]);
		}
	}

	/**
	 * Adds a permission to the hidden permissions list within the context. Depending on whether
	 * the permission has "own" or "any" variants, it updates the list accordingly.
	 *
	 * @param string $permission The base permission to be added to the hidden permissions list.
	 * @param bool $hasOwnAny Determines whether the permission should include "own" and "any" variants.
	 *
	 * @return void
	 */
	private function addToHiddenPermissions(string $permission, bool $hasOwnAny): void
	{
		if ($hasOwnAny)
		{
			$this->context['hidden_permissions'][] = $permission . '_own';
			$this->context['hidden_permissions'][] = $permission . '_any';
		}
		else
		{
			$this->context['hidden_permissions'][] = $permission;
		}
	}

	/**
	 * Finalizes the organization of permission columns by sorting them and processing
	 * their associated groups. Ensures that each column's permission groups are properly
	 * finalized and structured within the given permission type context.
	 *
	 * @param string $permissionType The type/category of permissions whose columns are being finalized.
	 *
	 * @return void
	 */
	private function finalizePermissionColumns(string $permissionType): void
	{
		ksort($this->context['permissions'][$permissionType]['columns']);

		foreach ($this->context['permissions'][$permissionType]['columns'] as $column => $groups)
		{
			$this->finalizePermissionGroup($permissionType, $column, $groups);
		}
	}

	/**
	 * Finalizes the permission group by iterating through the provided groups, ensuring that
	 * empty groups are removed from the context and marking groups as hidden when necessary.
	 *
	 * @param string $permissionType The type/category of permissions being finalized.
	 * @param int $column The specific column index related to the groups being processed.
	 * @param array $groups The list of permission groups to be finalized, indexed by group ID.
	 *
	 * @return void
	 */
	private function finalizePermissionGroup(string $permissionType, int $column, array $groups): void
	{
		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach ($groups as $id => $group)
		{
			if (empty($group['permissions']))
			{
				unset($this->context['permissions'][$permissionType]['columns'][$column][$id]);
				continue;
			}

			$this->markGroupHiddenIfNeeded($permissionType, $column, $id, $group);
		}
	}

	/**
	 * Marks a permission group as hidden if all permissions within the group are flagged as hidden.
	 * Updates the context data accordingly to reflect the hidden status of the group.
	 *
	 * @param string $permissionType The type/category of permissions being processed.
	 * @param int $column The column index where the permission group resides.
	 * @param string $id The unique identifier of the permission group.
	 * @param array $group The permission group data, including its associated permissions and their metadata.
	 *
	 * @return void
	 */
	private function markGroupHiddenIfNeeded(string $permissionType, int $column, string $id, array $group): void
	{
		$foundNonHidden = false;
		foreach ($group['permissions'] as $permission)
		{
			if (empty($permission['hidden']))
			{
				$foundNonHidden = true;
				break;
			}
		}

		if (!$foundNonHidden)
		{
			$this->context['permissions'][$permissionType]['columns'][$column][$id]['hidden'] = true;
		}
	}
}
