<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file contains one humble function, which applauds or smites a user.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Modify a user's karma.
 * It redirects back to the referrer afterward, whether by javascript or the passed parameters.
 * Requires the karma_edit permission, and that the user isn't a guest.
 * It depends on the karmaMode, karmaWaitTime, and karmaTimeRestrictAdmins settings.
 * It is accessed via ?action=karma, sa=smite or sa=applaud.
 */
function action_karma()
{
	global $modSettings, $txt, $user_info, $topic, $smcFunc, $context;

	// If the mod is disabled, show an error.
	if (empty($modSettings['karmaMode']))
		fatal_lang_error('feature_disabled', true);

	// If you're a guest or can't do this, blow you off...
	is_not_guest();
	isAllowedTo('karma_edit');

	checkSession('get');

	// we hold karma here.
	require_once(SUBSDIR . '/Karma.subs.php');

	// If you don't have enough posts, tough luck.
	// @todo Should this be dropped in favor of post group permissions?
	// Should this apply to the member you are smiting/applauding?
	if (!$user_info['is_admin'] && $user_info['posts'] < $modSettings['karmaMinPosts'])
		fatal_lang_error('not_enough_posts_karma', true, array($modSettings['karmaMinPosts']));

	// And you can't modify your own, punk! (use the profile if you need to.)
	if (empty($_REQUEST['uid']) || (int) $_REQUEST['uid'] == $user_info['id'])
		fatal_lang_error('cant_change_own_karma', false);

	// The user ID _must_ be a number, no matter what.
	$_REQUEST['uid'] = (int) $_REQUEST['uid'];

	// Applauding or smiting?
	$dir = $_REQUEST['sa'] != 'applaud' ? -1 : 1;

	clearKarma($modSettings['karmaWaitTime']);

	// Start off with no change in karma.
	$action = 0;

	// Not an administrator... or one who is restricted as well.
	if (!empty($modSettings['karmaTimeRestrictAdmins']) || !allowedTo('moderate_forum'))
	{
		// Find out if this user has done this recently...
		$action = lastActionOn($user_info['id'], $_REQUEST['uid']);
	}

	// They haven't, not before now, anyhow.
	if (empty($action) || empty($modSettings['karmaWaitTime']))
		addKarma($user_info['id'], $_REQUEST['uid'], $dir);
	else
	{
		// If you are gonna try to repeat.... don't allow it.
		if ($action == $dir)
			fatal_lang_error('karma_wait_time', false, array($modSettings['karmaWaitTime'], ($modSettings['karmaWaitTime'] == 1 ? strtolower($txt['hour']) : $txt['hours'])));

		updateKarma($user_info['id'], $_REQUEST['uid'], $dir);
	}

	// Figure out where to go back to.... the topic?
	if (!empty($topic))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . '#msg' . (int) $_REQUEST['m']);
	// Hrm... maybe a personal message?
	elseif (isset($_REQUEST['f']))
		redirectexit('action=pm;f=' . $_REQUEST['f'] . ';start=' . $_REQUEST['start'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . (isset($_REQUEST['pm']) ? '#' . (int) $_REQUEST['pm'] : ''));
	// JavaScript as a last resort.
	else
	{
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>...</title>
		<script type="text/javascript"><!-- // --><![CDATA[
			history.go(-1);
		// ]]></script>
	</head>
	<body>&laquo;</body>
</html>';

		obExit(false);
	}
}