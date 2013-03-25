<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Load a few illegal permissions in context.
 */
function loadIllegalPermissions()
{
	global $context;

	$context['illegal_permissions'] = array();
	if (!allowedTo('admin_forum'))
		$context['illegal_permissions'][] = 'admin_forum';
	if (!allowedTo('manage_membergroups'))
		$context['illegal_permissions'][] = 'manage_membergroups';
	if (!allowedTo('manage_permissions'))
		$context['illegal_permissions'][] = 'manage_permissions';

	call_integration_hook('integrate_load_illegal_permissions');
}

/**
 * Loads those permissions guests cannot have, into context.
 */
function loadIllegalGuestPermissions()
{
	global $context;

	$context['non_guest_permissions'] = array(
		'delete_replies',
		'karma_edit',
		'poll_add_own',
		'pm_read',
		'pm_send',
		'profile_identity',
		'profile_extra',
		'profile_title',
		'profile_remove',
		'profile_server_avatar',
		'profile_upload_avatar',
		'profile_remote_avatar',
		'profile_view_own',
		'mark_any_notify',
		'mark_notify',
		'admin_forum',
		'manage_boards',
		'manage_attachments',
		'manage_smileys',
		'edit_news',
		'access_mod_center',
		'moderate_forum',
		'issue_warning',
		'manage_membergroups',
		'manage_permissions',
		'manage_bans',
		'move_own',
		'modify_replies',
		'send_mail',
		'approve_posts',
		'post_draft',
		'post_autosave_draft',
	);

	call_integration_hook('integrate_load_illegal_guest_permissions');
}