<?php

/**
 * Handles sending out of password reminders, as well as the answer / question
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
 * @version 1.0.10
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Reminder Controller handles sending out reminders, and checking the secret answer and question.
 */
class Reminder_Controller extends Action_Controller
{
	/**
	 * This is the pre-dispatch function
	 *
	 * @uses Profile language files and Reminder template
	 */
	public function pre_dispatch()
	{
		global $txt, $context;

		loadLanguage('Profile');
		loadTemplate('Reminder');

		$context['page_title'] = $txt['authentication_reminder'];
		$context['robot_no_index'] = true;
	}

	/**
	 * Default action for reminder.
	 * @uses reminder sub template
	 */
	public function action_index()
	{
		global $context;

		$context['sub_template'] = 'reminder';

		// Nothing to do, the template will ask for an action to pick
		createToken('remind');
	}

	/**
	 * Pick a reminder type.
	 * Accessed by sa=picktype
	 */
	public function action_picktype()
	{
		global $context, $txt, $scripturl, $user_info, $webmaster_email, $language, $modSettings;

		checkSession();
		validateToken('remind');
		createToken('remind');

		require_once(SUBSDIR . '/Auth.subs.php');

		// No where params just yet
		$where_params = array();
		$where = '';

		// Clean them up
		$_POST['user'] = isset($_POST['user']) ? Util::htmlspecialchars($_POST['user']) : '';
		$_REQUEST['uid'] = (int) isset($_REQUEST['uid']) ? $_REQUEST['uid'] : 0;

		// Coming with a known ID?
		if (!empty($_REQUEST['uid']))
		{
			$where = 'id_member = {int:id_member}';
			$where_params['id_member'] = (int) $_REQUEST['uid'];
		}
		elseif ($_POST['user'] != '')
		{
			$where = 'member_name = {string:member_name}';
			$where_params['member_name'] = $_POST['user'];
			$where_params['email_address'] = $_POST['user'];
		}

		// You must enter a username/email address.
		if (empty($where))
			fatal_lang_error('username_no_exist', false);

		// Make sure we are not being slammed
		// Don't call this if you're coming from the "Choose a reminder type" page - otherwise you'll likely get an error
		if (!isset($_POST['reminder_type']) || !in_array($_POST['reminder_type'], array('email', 'secret')))
			spamProtection('remind');

		$member = findUser($where, $where_params);

		$context['account_type'] = !empty($member['openid_uri']) ? 'openid' : 'password';

		// If the user isn't activated/approved, give them some feedback on what to do next.
		if ($member['is_activated'] != 1)
		{
			// Awaiting approval...
			if (trim($member['validation_code']) == '')
				fatal_error($txt['registration_not_approved'] . ' <a href="' . $scripturl . '?action=activate;user=' . $_POST['user'] . '">' . $txt['here'] . '</a>.', false);
			else
				fatal_error($txt['registration_not_activated'] . ' <a href="' . $scripturl . '?action=activate;user=' . $_POST['user'] . '">' . $txt['here'] . '</a>.', false);
		}

		// You can't get emailed if you have no email address.
		$member['email_address'] = trim($member['email_address']);
		if ($member['email_address'] == '')
			fatal_error($txt['no_reminder_email'] . '<br />' . $txt['send_email'] . ' <a href="mailto:' . $webmaster_email . '">webmaster</a> ' . $txt['to_ask_password'] . '.');

		// If they have no secret question then they can only get emailed the item, or they are requesting the email, send them an email.
		if (empty($member['secret_question']) || (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'email'))
		{
			// Randomly generate a new password, with only alpha numeric characters that is a max length of 10 chars.
			$password = generateValidationCode();

			require_once(SUBSDIR . '/Mail.subs.php');
			$replacements = array(
				'REALNAME' => $member['real_name'],
				'REMINDLINK' => $scripturl . '?action=reminder;sa=setpassword;u=' . $member['id_member'] . ';code=' . $password,
				'IP' => $user_info['ip'],
				'MEMBERNAME' => $member['member_name'],
				'OPENID' => $member['openid_uri'],
			);

			$emaildata = loadEmailTemplate('forgot_' . $context['account_type'], $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
			$context['description'] = $txt['reminder_' . (!empty($member['openid_uri']) ? 'openid_' : '') . 'sent'];

			// If they were using OpenID simply email them their OpenID identity.
			sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);

			if (empty($member['openid_uri']))
				// Set the password in the database.
				updateMemberData($member['id_member'], array('validation_code' => substr(md5($password), 0, 10)));

			// Set up the template.
			$context['sub_template'] = 'sent';

			// Dont really.
			return;
		}
		// Otherwise are ready to answer the question?
		elseif (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'secret')
			return secretAnswerInput();

		// No we're here setup the context for template number 2!
		$context['sub_template'] = 'reminder_pick';
		$context['current_member'] = array(
			'id' => $member['id_member'],
			'name' => $member['member_name'],
		);
	}

	/**
	 * Set your new password
	 * sa=setpassword
	 */
	public function action_setpassword()
	{
		global $txt, $context;

		loadLanguage('Login');

		// You need a code!
		if (!isset($_REQUEST['code']))
			fatal_lang_error('no_access', false);

		// Fill the context array.
		$context += array(
			'page_title' => $txt['reminder_set_password'],
			'sub_template' => 'set_password',
			'code' => $_REQUEST['code'],
			'memID' => (int) $_REQUEST['u']
		);

		// Some extra js is needed
		loadJavascriptFile('register.js');

		// Tokens!
		createToken('remind-sp');
	}

	/**
	 * Handle the password change.
	 * sa=setpassword2
	 */
	public function action_setpassword2()
	{
		global $context, $txt;

		checkSession();
		validateToken('remind-sp');

		if (empty($_POST['u']) || !isset($_POST['passwrd1']) || !isset($_POST['passwrd2']))
			fatal_lang_error('no_access', false);

		$_POST['u'] = (int) $_POST['u'];

		if ($_POST['passwrd1'] != $_POST['passwrd2'])
			fatal_lang_error('passwords_dont_match', false);

		if ($_POST['passwrd1'] == '')
			fatal_lang_error('no_password', false);

		loadLanguage('Login');

		// Get the code as it should be from the database.
		require_once(SUBSDIR . '/Members.subs.php');
		$member = getBasicMemberData((int) $_POST['u'], array('authentication' => true));

		// Does this user exist at all? Is he activated? Does he have a validation code?
		if (empty($member) || $member['is_activated'] != 1 || $member['validation_code'] == '')
			fatal_lang_error('invalid_userid', false);

		// Is the password actually valid?
		require_once(SUBSDIR . '/Auth.subs.php');
		$passwordError = validatePassword($_POST['passwrd1'], $member['member_name'], array($member['email_address']));

		// What - it's not?
		if ($passwordError != null)
			fatal_lang_error('profile_error_password_' . $passwordError, false);

		// Quit if this code is not right.
		if (empty($_POST['code']) || substr($member['validation_code'], 0, 10) !== substr(md5($_POST['code']), 0, 10))
		{
			// Stop brute force attacks like this.
			validatePasswordFlood($_POST['u'], $member['passwd_flood'], false);

			fatal_error($txt['invalid_activation_code'], false);
		}

		// Just in case, flood control.
		validatePasswordFlood($_POST['u'], $member['passwd_flood'], true);

		// User validated.  Update the database!
		require_once(SUBSDIR . '/Auth.subs.php');
		$sha_passwd = $_POST['passwrd1'];
		updateMemberData($_POST['u'], array('validation_code' => '', 'passwd' => validateLoginPassword($sha_passwd, '', $member['member_name'], true)));

		call_integration_hook('integrate_reset_pass', array($member['member_name'], $member['member_name'], $_POST['passwrd1']));

		loadTemplate('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));
		$context += array(
			'page_title' => $txt['reminder_password_set'],
			'sub_template' => 'login',
			'default_username' => $member['member_name'],
			'default_password' => $_POST['passwrd1'],
			'never_expire' => false,
			'description' => $txt['reminder_password_set']
		);
		createToken('login');
	}

	/**
	 * Verify the answer to the secret question.
	 * Accessed with sa=secret2
	 */
	public function action_secret2()
	{
		global $txt, $context;

		checkSession();
		validateToken('remind-sai');

		// Hacker?  How did you get this far without an email or username?
		if (empty($_REQUEST['uid']))
			fatal_lang_error('username_no_exist', false);

		loadLanguage('Login');

		// Get the information from the database.
		require_once(SUBSDIR . '/Members.subs.php');
		$member = getBasicMemberData((int) $_REQUEST['uid'], array('authentication' => true));
		if (empty($member))
			fatal_lang_error('username_no_exist', false);

		// Check if the secret answer is correct.
		if ($member['secret_question'] == '' || $member['secret_answer'] == '' || md5($_POST['secret_answer']) !== $member['secret_answer'])
		{
			log_error(sprintf($txt['reminder_error'], $member['member_name']), 'user');
			fatal_lang_error('incorrect_answer', false);
		}

		// If it's OpenID this is where the music ends.
		if (!empty($member['openid_uri']))
		{
			$context['sub_template'] = 'sent';
			$context['description'] = sprintf($txt['reminder_openid_is'], $member['openid_uri']);
			return;
		}

		// You can't use a blank one!
		if (strlen(trim($_POST['passwrd1'])) === 0)
			fatal_lang_error('no_password', false);

		// They have to be the same too.
		if ($_POST['passwrd1'] != $_POST['passwrd2'])
			fatal_lang_error('passwords_dont_match', false);

		// Make sure they have a strong enough password.
		require_once(SUBSDIR . '/Auth.subs.php');
		$passwordError = validatePassword($_POST['passwrd1'], $member['member_name'], array($member['email_address']));

		// Invalid?
		if ($passwordError != null)
			fatal_lang_error('profile_error_password_' . $passwordError, false);

		// Alright, so long as 'yer sure.
		require_once(SUBSDIR . '/Auth.subs.php');
		$sha_passwd = $_POST['passwrd1'];
		updateMemberData($member['id_member'], array('passwd' => validateLoginPassword($sha_passwd, '', $member['member_name'], true)));

		call_integration_hook('integrate_reset_pass', array($member['member_name'], $member['member_name'], $_POST['passwrd1']));

		// Tell them it went fine.
		loadTemplate('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));
		$context += array(
			'page_title' => $txt['reminder_password_set'],
			'sub_template' => 'login',
			'default_username' => $member['member_name'],
			'default_password' => $_POST['passwrd1'],
			'never_expire' => false,
			'description' => $txt['reminder_password_set']
		);

		createToken('login');
	}
}

/**
 * Get the secret answer.
 */
function secretAnswerInput()
{
	global $context;

	checkSession();

	// Strings for the register auto javascript clever stuffy wuffy.
	loadLanguage('Login');

	// Check they entered something...
	if (empty($_REQUEST['uid']))
		fatal_lang_error('username_no_exist', false);

	// Get the stuff....
	require_once(SUBSDIR . '/Members.subs.php');
	$member = getBasicMemberData((int) $_REQUEST['uid'], array('authentication' => true));
	if (empty($member))
		fatal_lang_error('username_no_exist', false);

	$context['account_type'] = !empty($member['openid_uri']) ? 'openid' : 'password';

	// If there is NO secret question - then throw an error.
	if (trim($member['secret_question']) == '')
		fatal_lang_error('registration_no_secret_question', false);

	// Ask for the answer...
	$context['remind_user'] = $member['id_member'];
	$context['remind_type'] = '';
	$context['secret_question'] = $member['secret_question'];
	$context['sub_template'] = 'ask';

	loadJavascriptFile('register.js');

	createToken('remind-sai');
}
