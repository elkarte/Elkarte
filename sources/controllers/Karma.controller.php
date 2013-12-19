<?php

/**
 * Deals with the giving and taking of a users karma
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
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Karma_Controller class, can give good or bad karma so watch out!
 */
class Karma_Controller extends Action_Controller
{
	/**
	 * Default entry point, in case action methods aren't directly
	 * called. Simply forward to applaud.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Applauds for us :P
		$this->action_applaud();
	}

	/**
	 * Modify a user's karma.
	 * It redirects back to the referrer afterward, whether by javascript or the passed parameters.
	 * Requires the karma_edit permission, and that the user isn't a guest.
	 * It depends on the karmaMode, karmaWaitTime, and karmaTimeRestrictAdmins settings.
	 * It is accessed via ?action=karma, sa=smite or sa=applaud.
	*/
	public function action_applaud()
	{
		global $user_info;

		$id_target = !empty($_REQUEST['uid']) ? (int) $_REQUEST['uid'] : 0;

		// Start off with no change in karma.
		$action = prepare_karma($id_target);

		// Applaud (if you can) and return
		give_karma($user_info['id'], $id_target, $action, 1);
		redirect_karma();
	}

	/**
	 * Smite a user.
	 */
	public function action_smite()
	{
		global $user_info;

		// The user ID _must_ be a number, no matter what.
		$id_target = !empty($_REQUEST['uid']) ? (int) $_REQUEST['uid'] : 0;

		// Start off with no change in karma.
		$action = prepare_karma($id_target);

		// Give em a wack and run away
		give_karma($user_info['id'], $_REQUEST['uid'], $action, -1);
		redirect_karma();
	}
}

/**
 * Function to move the karma needle up or down for a given user
 *
 * @param int $id_executor who is performing the action
 * @param int $id_target who is getting the action
 * @param int $action applaud or smite
 * @param int $dir
 */
function give_karma($id_executor, $id_target, $action, $dir)
{
	global $modSettings, $txt;

	// They haven't, not before now, anyhow.
	if (empty($action) || empty($modSettings['karmaWaitTime']))
		addKarma($id_executor, $id_target, $dir);
	else
	{
		// If you are gonna try to repeat.... don't allow it.
		if ($action == $dir)
			fatal_lang_error('karma_wait_time', false, array($modSettings['karmaWaitTime'], ($modSettings['karmaWaitTime'] == 1 ? strtolower($txt['hour']) : $txt['hours'])));

		updateKarma($id_executor, $id_target, $dir);
	}
}

/**
 * Makes sure that a user can perform the karma action
 *
 * @param int $id_target
 */
function prepare_karma($id_target)
{
	global $modSettings, $user_info;

	// If the mod is disabled, show an error.
	if (empty($modSettings['karmaMode']))
		fatal_lang_error('feature_disabled', true);

	// If you're a guest or can't do this, blow you off...
	is_not_guest();
	isAllowedTo('karma_edit');

	checkSession('get');

	// We hold karma here.
	require_once(SUBSDIR . '/Karma.subs.php');

	// If you don't have enough posts, tough luck.
	// @todo Should this be dropped in favor of post group permissions?
	// Should this apply to the member you are smiting/applauding?
	if (!$user_info['is_admin'] && $user_info['posts'] < $modSettings['karmaMinPosts'])
		fatal_lang_error('not_enough_posts_karma', true, array($modSettings['karmaMinPosts']));

	// And you can't modify your own, punk! (use the profile if you need to.)
	if (empty($id_target) || $id_target == $user_info['id'])
		fatal_lang_error('cant_change_own_karma', false);

	// Delete any older items from the log so we can get the go ahead or not
	clearKarma($modSettings['karmaWaitTime']);

	// Not an administrator... or one who is restricted as well.
	$action = 0;
	if (!empty($modSettings['karmaTimeRestrictAdmins']) || !allowedTo('moderate_forum'))
	{
		// Find out if this user has done this recently...
		$action = lastActionOn($user_info['id'], $id_target);
	}

	return $action;
}

/**
 * Done with the action, return to where we need to be, or make it up if we
 * can't figure it out.
 */
function redirect_karma()
{
	global $context, $topic;

	// Figure out where to go back to.... the topic?
	if (!empty($topic))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . '#msg' . (int) $_REQUEST['m']);
	// Hrm... maybe a personal message?
	elseif (isset($_REQUEST['f']))
		redirectexit('action=pm;f=' . $_REQUEST['f'] . ';start=' . $_REQUEST['start'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . (isset($_REQUEST['pm']) ? '#' . (int) $_REQUEST['pm'] : ''));
	// JavaScript as a last resort.
	else
	{
		echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<title>...</title>
		<script><!-- // --><![CDATA[
			history.go(-1);
		// ]]></script>
	</head>
	<body>&laquo;</body>
</html>';

		obExit(false);
	}
}