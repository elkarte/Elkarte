<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code by:
 * copyright:	2012 Simple Machines Forum contributors (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * OpenID controller.
 */
class OpenID_Controller
{
	/**
	 * Callback action handler for OpenID
	 */
	function action_openidreturn()
	{
		global $user_info, $user_profile, $modSettings, $context, $sc, $user_settings;

		// We'll need our subs.
		require_once(SUBSDIR . '/OpenID.subs.php');

		$db = database();

		// Is OpenID even enabled?
		if (empty($modSettings['enableOpenID']))
			fatal_lang_error('no_access', false);

		if (!isset($_GET['openid_mode']))
			fatal_lang_error('openid_return_no_mode', false);

		// @todo Check for error status!
		if ($_GET['openid_mode'] != 'id_res')
			fatal_lang_error('openid_not_resolved');

		// this has annoying habit of removing the + from the base64 encoding.  So lets put them back.
		foreach (array('openid_assoc_handle', 'openid_invalidate_handle', 'openid_sig', 'sf') as $key)
			if (isset($_GET[$key]))
				$_GET[$key] = str_replace(' ', '+', $_GET[$key]);

		// Did they tell us to remove any associations?
		if (!empty($_GET['openid_invalidate_handle']))
			openid_removeAssociation($_GET['openid_invalidate_handle']);

		$server_info = openid_getServerInfo($_GET['openid_identity']);

		// Get the association data.
		$assoc = openID_getAssociation($server_info['server'], $_GET['openid_assoc_handle'], true);
		if ($assoc === null)
			fatal_lang_error('openid_no_assoc');

		$secret = base64_decode($assoc['secret']);

		$signed = explode(',', $_GET['openid_signed']);
		$verify_str = '';
		foreach ($signed as $sign)
		{
			$verify_str .= $sign . ':' . strtr($_GET['openid_' . str_replace('.', '_', $sign)], array('&amp;' => '&')) . "\n";
		}

		$verify_str = base64_encode(sha1_hmac($verify_str, $secret));

		if ($verify_str != $_GET['openid_sig'])
		{
			fatal_lang_error('openid_sig_invalid', 'critical');
		}

		if (!isset($_SESSION['openid']['saved_data'][$_GET['t']]))
			fatal_lang_error('openid_load_data');

		$openid_uri = $_SESSION['openid']['saved_data'][$_GET['t']]['openid_uri'];
		$modSettings['cookieTime'] = $_SESSION['openid']['saved_data'][$_GET['t']]['cookieTime'];

		if (empty($openid_uri))
			fatal_lang_error('openid_load_data');

		// Any save fields to restore?
		$context['openid_save_fields'] = isset($_GET['sf']) ? unserialize(base64_decode($_GET['sf'])) : array();

		// Is there a user with this OpenID_uri?
		$result = $db->query('', '
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
				openid_uri
			FROM {db_prefix}members
			WHERE openid_uri = {string:openid_uri}',
			array(
				'openid_uri' => $openid_uri,
			)
		);

		$member_found = $db->num_rows($result);

		if (!$member_found && isset($_GET['sa']) && $_GET['sa'] == 'change_uri' && !empty($_SESSION['new_openid_uri']) && $_SESSION['new_openid_uri'] == $openid_uri)
		{
			// Update the member.
			updateMemberData($user_settings['id_member'], array('openid_uri' => $openid_uri));

			unset($_SESSION['new_openid_uri']);
			$_SESSION['openid'] = array(
				'verified' => true,
				'openid_uri' => $openid_uri,
			);

			// Send them back to profile.
			redirectexit('action=profile;area=authentication;updated');
		}
		elseif (!$member_found)
		{
			// Store the received openid info for the user when returned to the registration page.
			$_SESSION['openid'] = array(
				'verified' => true,
				'openid_uri' => $openid_uri,
			);
			if (isset($_GET['openid_sreg_nickname']))
				$_SESSION['openid']['nickname'] = $_GET['openid_sreg_nickname'];
			if (isset($_GET['openid_sreg_email']))
				$_SESSION['openid']['email'] = $_GET['openid_sreg_email'];
			if (isset($_GET['openid_sreg_dob']))
				$_SESSION['openid']['dob'] = $_GET['openid_sreg_dob'];
			if (isset($_GET['openid_sreg_gender']))
				$_SESSION['openid']['gender'] = $_GET['openid_sreg_gender'];

			// Were we just verifying the registration state?
			if (isset($_GET['sa']) && $_GET['sa'] == 'register2')
			{
				require_once(CONTROLLERDIR . '/Register.controller.php');
				$controller = new Register_Controller();
				return $controller->action_register2(true);
			}
			else
				redirectexit('action=register');
		}
		elseif (isset($_GET['sa']) && $_GET['sa'] == 'revalidate' && $user_settings['openid_uri'] == $openid_uri)
		{
			$_SESSION['openid_revalidate_time'] = time();

			// Restore the get data.
			require_once(SUBSDIR . '/Auth.subs.php');
			$_SESSION['openid']['saved_data'][$_GET['t']]['get']['openid_restore_post'] = $_GET['t'];
			$query_string = construct_query_string($_SESSION['openid']['saved_data'][$_GET['t']]['get']);

			redirectexit($query_string);
		}
		else
		{
			$user_settings = $db->fetch_assoc($result);
			$db->free_result($result);

			$user_settings['passwd'] = sha1(strtolower($user_settings['member_name']) . $secret);
			$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);

			updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd'], 'password_salt' => $user_settings['password_salt']));

			// Cleanup on Aisle 5.
			$_SESSION['openid'] = array(
				'verified' => true,
				'openid_uri' => $openid_uri,
			);

			require_once(CONTROLLERDIR . '/Auth.controller.php');

			if (!checkActivation())
				return;

			doLogin();
		}
	}
}
