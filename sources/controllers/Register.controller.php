<?php

/**
 * This file has two main jobs, but they really are one.  It registers new
 * members, and it helps the administrator moderate member registrations.
 * Similarly, it handles account activation as well.
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
 * Register Controller Class, It registers new members, and it alows
 * the administrator moderate member registration
 */
class Register_Controller extends Action_Controller
{
	/**
	 * Intended entry point for this class.
	 * By default, this is called for action=register
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// figure out what action to do... register, what else.
		$this->action_register();
	}

	/**
	 * Begin the registration process.
	 * Accessed by ?action=register
	 *
	 * @uses Register template, registration_agreement or registration_form sub template
	 * @uses Login language file
	 */
	public function action_register()
	{
		global $txt, $context, $modSettings, $user_info, $language, $scripturl, $cur_profile;

		// Is this an incoming AJAX check?
		if (isset($_GET['sa']) && $_GET['sa'] == 'usernamecheck')
			return $this->_registerCheckUsername();

		// Check if the administrator has it disabled.
		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == '3')
			fatal_lang_error('registration_disabled', false);

		// If this user is an admin - redirect them to the admin registration page.
		if (allowedTo('moderate_forum') && !$user_info['is_guest'])
			redirectexit('action=admin;area=regcenter;sa=register');
		// You are not a guest, so you are a member - and members don't get to register twice!
		elseif (empty($user_info['is_guest']))
			redirectexit();

		if (isset($_POST['show_contact']))
			redirectexit('action=contact');

		loadLanguage('Login');
		loadTemplate('Register');

		// Do we need them to agree to the registration agreement, first?
		$context['require_agreement'] = !empty($modSettings['requireAgreement']);
		$context['checkbox_agreement'] = !empty($modSettings['checkboxAgreement']);
		$context['registration_passed_agreement'] = !empty($_SESSION['registration_agreed']);
		$context['show_coppa'] = !empty($modSettings['coppaAge']);
		$context['show_contact_button'] = !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] == 'registration';

		// Under age restrictions?
		if ($context['show_coppa'])
		{
			$context['skip_coppa'] = false;
			$context['coppa_agree_above'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_above'], $modSettings['coppaAge']);
			$context['coppa_agree_below'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_below'], $modSettings['coppaAge']);
		}

		// What step are we at?
		$current_step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : ($context['require_agreement'] && !$context['checkbox_agreement'] ? 1 : 2);

		// Does this user agree to the registration agreement?
		if ($current_step == 1 && (isset($_POST['accept_agreement']) || isset($_POST['accept_agreement_coppa'])))
		{
			$context['registration_passed_agreement'] = $_SESSION['registration_agreed'] = true;
			$current_step = 2;

			// Skip the coppa procedure if the user says he's old enough.
			if ($context['show_coppa'])
			{
				$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);

				// Are they saying they're under age, while under age registration is disabled?
				if (empty($modSettings['coppaType']) && empty($_SESSION['skip_coppa']))
				{
					loadLanguage('Login');
					fatal_lang_error('under_age_registration_prohibited', false, array($modSettings['coppaAge']));
				}
			}
		}
		// Make sure they don't squeeze through without agreeing.
		elseif ($current_step > 1 && $context['require_agreement'] && !$context['checkbox_agreement'] && !$context['registration_passed_agreement'])
			$current_step = 1;

		// Show the user the right form.
		$context['sub_template'] = $current_step == 1 ? 'registration_agreement' : 'registration_form';
		$context['page_title'] = $current_step == 1 ? $txt['registration_agreement'] : $txt['registration_form'];
		loadJavascriptFile('register.js');
		addInlineJavascript('disableAutoComplete();', true);

		// Add the register chain to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=register',
			'name' => $txt['register'],
		);

		// Prepare the time gate! Done like this to allow later steps to reset the limit for any reason
		if (!isset($_SESSION['register']))
			$_SESSION['register'] = array(
				'timenow' => time(),
				// minimum number of seconds required on this page for registration
				'limit' => 8,
			);
		else
			$_SESSION['register']['timenow'] = time();

		// If you have to agree to the agreement, it needs to be fetched from the file.
		if ($context['require_agreement'])
		{
			// Have we got a localized one?
			if (file_exists(BOARDDIR . '/agreement.' . $user_info['language'] . '.txt'))
				$context['agreement'] = parse_bbc(file_get_contents(BOARDDIR . '/agreement.' . $user_info['language'] . '.txt'), true, 'agreement_' . $user_info['language']);
			elseif (file_exists(BOARDDIR . '/agreement.txt'))
				$context['agreement'] = parse_bbc(file_get_contents(BOARDDIR . '/agreement.txt'), true, 'agreement');
			else
				$context['agreement'] = '';

			// Nothing to show, lets disable registration and inform the admin of this error
			if (empty($context['agreement']))
			{
				// No file found or a blank file, log the error so the admin knows there is a problem!
				loadLanguage('Errors');
				log_error($txt['registration_agreement_missing'], 'critical');
				fatal_lang_error('registration_disabled', false);
			}
		}

		if (!empty($modSettings['userLanguage']))
		{
			// Do we have any languages?
			$languages = getLanguages();

			if (isset($_POST['lngfile']) && isset($languages[$_POST['lngfile']]))
				$_SESSION['language'] = $_POST['lngfile'];

			$selectedLanguage = empty($_SESSION['language']) ? $language : $_SESSION['language'];

			// Try to find our selected language.
			foreach ($languages as $key => $lang)
			{
				$context['languages'][$key]['name'] = $lang['name'];

				// Found it!
				if ($selectedLanguage == $lang['filename'])
					$context['languages'][$key]['selected'] = true;
			}
		}

		// Any custom fields we want filled in?
		require_once(SUBSDIR . '/Profile.subs.php');
		loadCustomFields(0, 'register');

		// Or any standard ones?
		if (!empty($modSettings['registration_fields']))
		{
			// Setup some important context.
			loadLanguage('Profile');
			loadTemplate('Profile');

			$context['user']['is_owner'] = true;

			// Here, and here only, emulate the permissions the user would have to do this.
			$user_info['permissions'] = array_merge($user_info['permissions'], array('profile_account_own', 'profile_extra_own'));
			$reg_fields = ProfileOptions_Controller::getFields('registration');

			// We might have had some submissions on this front - go check.
			foreach ($reg_fields['fields'] as $field)
			{
				if (isset($_POST[$field]))
					$cur_profile[$field] = Util::htmlspecialchars($_POST[$field]);
			}

			// Load all the fields in question.
			setupProfileContext($reg_fields['fields'], $reg_fields['hook']);
		}

		// Generate a visual verification code to make sure the user is no bot.
		if (!empty($modSettings['reg_verification']) && $current_step > 1)
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
			$verificationOptions = array(
				'id' => 'register',
			);
			$context['visual_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}
		// Otherwise we have nothing to show.
		else
			$context['visual_verification'] = false;

		// Are they coming from an OpenID login attempt?
		if (!empty($_SESSION['openid']['verified']) && !empty($_SESSION['openid']['openid_uri']) && !empty($_SESSION['openid']['nickname']))
		{
			$context['openid'] = $_SESSION['openid']['openid_uri'];
			$context['username'] = !empty($_POST['user']) ? Util::htmlspecialchars($_POST['user']) : $_SESSION['openid']['nickname'];
			$context['email'] = !empty($_POST['email']) ? Util::htmlspecialchars($_POST['email']) : $_SESSION['openid']['email'];
		}
		// See whether we have some prefiled values.
		else
		{
			$context += array(
				'openid' => isset($_POST['openid_identifier']) ? $_POST['openid_identifier'] : '',
				'username' => isset($_POST['user']) ? Util::htmlspecialchars($_POST['user']) : '',
				'email' => isset($_POST['email']) ? Util::htmlspecialchars($_POST['email']) : '',
			);
		}

		// Were there any errors?
		$context['registration_errors'] = array();
		$reg_errors = Error_Context::context('register', 0);
		if ($reg_errors->hasErrors())
			$context['registration_errors'] = $reg_errors->prepareErrors();

		createToken('register');
	}

	/**
	 * Actually register the member.
	 * @todo split this function in two functions:
	 *  - a function that handles action=register2, which needs no parameter;
	 *  - a function that processes the case of OpenID verification.
	 *
	 * @param bool $verifiedOpenID = false
	 */
	public function action_register2($verifiedOpenID = false)
	{
		global $txt, $modSettings, $context, $user_info;

		// Start collecting together any errors.
		$reg_errors = Error_Context::context('register', 0);

		// We can't validate the token and the session with OpenID enabled.
		if(!$verifiedOpenID)
		{
			checkSession();
			if (!validateToken('register', 'post', true, false))
				$reg_errors->addError('token_verification');
		}

		// Did we save some open ID fields?
		if ($verifiedOpenID && !empty($context['openid_save_fields']))
		{
			foreach ($context['openid_save_fields'] as $id => $value)
				$_POST[$id] = $value;
		}

		// You can't register if it's disabled.
		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 3)
			fatal_lang_error('registration_disabled', false);

		// If we're using an agreement checkbox, did they check it?
		if (!empty($modSettings['checkboxAgreement']) && !empty($_POST['checkbox_agreement']))
			$_SESSION['registration_agreed'] = true;

		// Things we don't do for people who have already confirmed their OpenID allegances via register.
		if (!$verifiedOpenID)
		{
			// Well, if you don't agree, you can't register.
			if (!empty($modSettings['requireAgreement']) && empty($_SESSION['registration_agreed']))
				redirectexit();

			// Make sure they came from *somewhere*, have a session.
			if (!isset($_SESSION['old_url']))
				redirectexit('action=register');

			// If we don't require an agreement, we need a extra check for coppa.
			if (empty($modSettings['requireAgreement']) && !empty($modSettings['coppaAge']))
				$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);

			// Are they under age, and under age users are banned?
			if (!empty($modSettings['coppaAge']) && empty($modSettings['coppaType']) && empty($_SESSION['skip_coppa']))
			{
				loadLanguage('Login');
				fatal_lang_error('under_age_registration_prohibited', false, array($modSettings['coppaAge']));
			}

			// Check the time gate for miscreants. First make sure they came from somewhere that actually set it up.
			if (empty($_SESSION['register']['timenow']) || empty($_SESSION['register']['limit']))
				redirectexit('action=register');

			// Failing that, check the time limit for exessive speed.
			if (time() - $_SESSION['register']['timenow'] < $_SESSION['register']['limit'])
			{
				loadLanguage('Login');
				$reg_errors->addError('too_quickly');
			}

			// Check whether the visual verification code was entered correctly.
			if (!empty($modSettings['reg_verification']))
			{
				require_once(SUBSDIR . '/VerificationControls.class.php');
				$verificationOptions = array(
					'id' => 'register',
				);
				$context['visual_verification'] = create_control_verification($verificationOptions, true);

				if (is_array($context['visual_verification']))
				{
					foreach ($context['visual_verification'] as $error)
						$reg_errors->addError($error);
				}
			}
		}

		foreach ($_POST as $key => $value)
		{
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));
		}

		// Collect all extra registration fields someone might have filled in.
		$possible_strings = array(
			'birthdate',
			'time_format',
			'buddy_list',
			'pm_ignore_list',
			'smiley_set',
			'personal_text', 'avatar',
			'lngfile', 'location',
			'secret_question', 'secret_answer',
			'website_url', 'website_title',
		);
		$possible_ints = array(
			'pm_email_notify',
			'notify_types',
			'id_theme',
			'gender',
		);
		$possible_floats = array(
			'time_offset',
		);
		$possible_bools = array(
			'notify_announcements', 'notify_regularity', 'notify_send_body',
			'hide_email', 'show_online',
		);

		if (isset($_POST['secret_answer']) && $_POST['secret_answer'] != '')
			$_POST['secret_answer'] = md5($_POST['secret_answer']);

		// Needed for isReservedName() and registerMember().
		require_once(SUBSDIR . '/Members.subs.php');

		// Validation... even if we're not a mall.
		if (isset($_POST['real_name']) && (!empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum')))
		{
			$_POST['real_name'] = trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $_POST['real_name']));
			if (trim($_POST['real_name']) != '' && !isReservedName($_POST['real_name']) && Util::strlen($_POST['real_name']) < 60)
				$possible_strings[] = 'real_name';
		}

		// Handle a string as a birthdate...
		if (isset($_POST['birthdate']) && $_POST['birthdate'] != '')
			$_POST['birthdate'] = strftime('%Y-%m-%d', strtotime($_POST['birthdate']));
		// Or birthdate parts...
		elseif (!empty($_POST['bday1']) && !empty($_POST['bday2']))
			$_POST['birthdate'] = sprintf('%04d-%02d-%02d', empty($_POST['bday3']) ? 0 : (int) $_POST['bday3'], (int) $_POST['bday1'], (int) $_POST['bday2']);

		// By default assume email is hidden, only show it if we tell it to.
		$_POST['hide_email'] = !empty($_POST['allow_email']) ? 0 : 1;

		// Validate the passed language file.
		if (isset($_POST['lngfile']) && !empty($modSettings['userLanguage']))
		{
			// Do we have any languages?
			$context['languages'] = getLanguages();

			// Did we find it?
			if (isset($context['languages'][$_POST['lngfile']]))
				$_SESSION['language'] = $_POST['lngfile'];
			else
				unset($_POST['lngfile']);
		}
		else
			unset($_POST['lngfile']);

		// Some of these fields we may not want.
		if (!empty($modSettings['registration_fields']))
		{
			// But we might want some of them if the admin asks for them.
			$standard_fields = array('location', 'gender');
			$reg_fields = explode(',', $modSettings['registration_fields']);

			$exclude_fields = array_diff($standard_fields, $reg_fields);

			// Website is a little different
			if (!in_array('website', $reg_fields))
				$exclude_fields = array_merge($exclude_fields, array('website_url', 'website_title'));

			// We used to accept signature on registration but it's being abused by spammers these days, so no more.
			$exclude_fields[] = 'signature';
		}
		else
			$exclude_fields = array('signature', 'location', 'gender', 'website_url', 'website_title');

		$possible_strings = array_diff($possible_strings, $exclude_fields);
		$possible_ints = array_diff($possible_ints, $exclude_fields);
		$possible_floats = array_diff($possible_floats, $exclude_fields);
		$possible_bools = array_diff($possible_bools, $exclude_fields);

		// Set the options needed for registration.
		$regOptions = array(
			'interface' => 'guest',
			'username' => !empty($_POST['user']) ? $_POST['user'] : '',
			'email' => !empty($_POST['email']) ? $_POST['email'] : '',
			'password' => !empty($_POST['passwrd1']) ? $_POST['passwrd1'] : '',
			'password_check' => !empty($_POST['passwrd2']) ? $_POST['passwrd2'] : '',
			'openid' => !empty($_POST['openid_identifier']) ? $_POST['openid_identifier'] : '',
			'auth_method' => !empty($_POST['authenticate']) ? $_POST['authenticate'] : '',
			'check_reserved_name' => true,
			'check_password_strength' => true,
			'check_email_ban' => true,
			'send_welcome_email' => !empty($modSettings['send_welcomeEmail']),
			'require' => !empty($modSettings['coppaAge']) && !$verifiedOpenID && empty($_SESSION['skip_coppa']) ? 'coppa' : (empty($modSettings['registration_method']) ? 'nothing' : ($modSettings['registration_method'] == 1 ? 'activation' : 'approval')),
			'extra_register_vars' => array(),
			'theme_vars' => array(),
		);

		// Include the additional options that might have been filled in.
		foreach ($possible_strings as $var)
			if (isset($_POST[$var]))
				$regOptions['extra_register_vars'][$var] = Util::htmlspecialchars($_POST[$var], ENT_QUOTES);

		foreach ($possible_ints as $var)
			if (isset($_POST[$var]))
				$regOptions['extra_register_vars'][$var] = (int) $_POST[$var];

		foreach ($possible_floats as $var)
			if (isset($_POST[$var]))
				$regOptions['extra_register_vars'][$var] = (float) $_POST[$var];

		foreach ($possible_bools as $var)
			if (isset($_POST[$var]))
				$regOptions['extra_register_vars'][$var] = empty($_POST[$var]) ? 0 : 1;

		// Registration options are always default options...
		if (isset($_POST['default_options']))
			$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];

		$regOptions['theme_vars'] = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : array();

		// Make sure they are clean, dammit!
		$regOptions['theme_vars'] = htmlspecialchars__recursive($regOptions['theme_vars']);

		// Check whether we have fields that simply MUST be displayed?
		require_once(SUBSDIR . '/Profile.subs.php');
		loadCustomFields(0, 'register');

		foreach ($context['custom_fields'] as $row)
		{
			// Don't allow overriding of the theme variables.
			if (isset($regOptions['theme_vars'][$row['colname']]))
				unset($regOptions['theme_vars'][$row['colname']]);

			// Prepare the value!
			$value = isset($_POST['customfield'][$row['colname']]) ? trim($_POST['customfield'][$row['colname']]) : '';

			// We only care for text fields as the others are valid to be empty.
			if (!in_array($row['type'], array('check', 'select', 'radio')))
			{
				// Is it too long?
				if ($row['field_length'] && $row['field_length'] < Util::strlen($value))
					$reg_errors->addError(array('custom_field_too_long', array($row['name'], $row['field_length'])));

				// Any masks to apply?
				if ($row['type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
				{
					// @todo We never error on this - just ignore it at the moment...
					if ($row['mask'] == 'email' && !isValidEmail($value))
						$reg_errors->addError(array('custom_field_invalid_email', array($row['name'])));
					elseif ($row['mask'] == 'number' && preg_match('~[^\d]~', $value))
						$reg_errors->addError(array('custom_field_not_number', array($row['name'])));
					elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) !== '' && preg_match(substr($row['mask'], 5), $value) === 0)
						$reg_errors->addError(array('custom_field_inproper_format', array($row['name'])));
				}
			}

			// Is this required but not there?
			if (trim($value) == '' && $row['show_reg'] > 1)
				$reg_errors->addError(array('custom_field_empty', array($row['name'])));
		}

		// Lets check for other errors before trying to register the member.
		if ($reg_errors->hasErrors())
		{
			$_REQUEST['step'] = 2;

			// If they've filled in some details but made an error then they need less time to finish
			$_SESSION['register']['limit'] = 4;

			return $this->action_register();
		}

		// If they're wanting to use OpenID we need to validate them first.
		if (empty($_SESSION['openid']['verified']) && !empty($_POST['authenticate']) && $_POST['authenticate'] == 'openid')
		{
			// What do we need to save?
			$save_variables = array();
			foreach ($_POST as $k => $v)
				if (!in_array($k, array('sc', 'sesc', $context['session_var'], 'passwrd1', 'passwrd2', 'regSubmit')))
					$save_variables[$k] = $v;

			require_once(SUBSDIR . '/OpenID.subs.php');
			$openID = new OpenID();
			$openID->validate($_POST['openid_identifier'], false, $save_variables);
		}
		// If we've come from OpenID set up some default stuff.
		elseif ($verifiedOpenID || ((!empty($_POST['openid_identifier']) || !empty($_SESSION['openid']['openid_uri'])) && $_POST['authenticate'] == 'openid'))
		{
			$regOptions['username'] = !empty($_POST['user']) && trim($_POST['user']) != '' ? $_POST['user'] : $_SESSION['openid']['nickname'];
			$regOptions['email'] = !empty($_POST['email']) && trim($_POST['email']) != '' ? $_POST['email'] : $_SESSION['openid']['email'];
			$regOptions['auth_method'] = 'openid';
			$regOptions['openid'] = !empty($_SESSION['openid']['openid_uri']) ? $_SESSION['openid']['openid_uri'] : (!empty($_POST['openid_identifier']) ? $_POST['openid_identifier'] : '');
		}

		// Registration needs to know your IP
		$req = request();

		$regOptions['ip'] = $user_info['ip'];
		$regOptions['ip2'] = $req->ban_ip();
		$memberID = registerMember($regOptions, 'register');

		// If there are "important" errors and you are not an admin: log the first error
		// Otherwise grab all of them and don't log anything
		if ($reg_errors->hasErrors(1) && !$user_info['is_admin'])
			foreach ($reg_errors->prepareErrors(1) as $error)
				fatal_error($error, 'general');

		// Was there actually an error of some kind dear boy?
		if ($reg_errors->hasErrors())
		{
			$_REQUEST['step'] = 2;
			return $this->action_register();
		}

		// Do our spam protection now.
		spamProtection('register');

		// We'll do custom fields after as then we get to use the helper function!
		if (!empty($_POST['customfield']))
		{
			require_once(SUBSDIR . '/Profile.subs.php');
			makeCustomFieldChanges($memberID, 'register');
		}

		// If COPPA has been selected then things get complicated, setup the template.
		if (!empty($modSettings['coppaAge']) && empty($_SESSION['skip_coppa']))
			redirectexit('action=coppa;member=' . $memberID);
		// Basic template variable setup.
		elseif (!empty($modSettings['registration_method']))
		{
			loadTemplate('Register');

			$context += array(
				'page_title' => $txt['register'],
				'title' => $txt['registration_successful'],
				'sub_template' => 'after',
				'description' => $modSettings['registration_method'] == 2 ? $txt['approval_after_registration'] : $txt['activate_after_registration']
			);
		}
		else
		{
			call_integration_hook('integrate_activate', array($regOptions['username'], 1, 1));

			setLoginCookie(60 * $modSettings['cookieTime'], $memberID, hash('sha256', $regOptions['register_vars']['passwd'] . $regOptions['register_vars']['password_salt']));

			redirectexit('action=auth;sa=check;member=' . $memberID, $context['server']['needs_login_fix']);
		}
	}

	/**
	 * Verify the activation code, and activate the user if correct.
	 * Accessed by ?action=activate
	 */
	public function action_activate()
	{
		global $context, $txt, $modSettings, $scripturl, $language, $user_info;

		require_once(SUBSDIR . '/Auth.subs.php');

		// Logged in users should not bother to activate their accounts
		if (!empty($user_info['id']))
			redirectexit();

		loadLanguage('Login');
		loadTemplate('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));

		if (empty($_REQUEST['u']) && empty($_POST['user']))
		{
			if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == '3')
				fatal_lang_error('no_access', false);

			$context['member_id'] = 0;
			$context['sub_template'] = 'resend';
			$context['page_title'] = $txt['invalid_activation_resend'];
			$context['can_activate'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] == '1';
			$context['default_username'] = isset($_GET['user']) ? $_GET['user'] : '';

			return;
		}

		// Get the code from the database...
		$row = findUser(empty($_REQUEST['u']) ? '
			member_name = {string:email_address} OR email_address = {string:email_address}' : '
			id_member = {int:id_member}', array(
				'id_member' => isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0,
				'email_address' => isset($_POST['user']) ? $_POST['user'] : '',
			), false
		);

		// Does this user exist at all?
		if (empty($row))
		{
			$context['sub_template'] = 'retry_activate';
			$context['page_title'] = $txt['invalid_userid'];
			$context['member_id'] = 0;

			return;
		}

		// Change their email address? (they probably tried a fake one first :P.)
		if (isset($_POST['new_email'], $_REQUEST['passwd']) && validateLoginPassword($_REQUEST['passwd'], $row['passwd'], $row['member_name'], true) && ($row['is_activated'] == 0 || $row['is_activated'] == 2))
		{
			if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == 3)
				fatal_lang_error('no_access', false);

			// @todo Separate the sprintf?
			require_once(SUBSDIR . '/DataValidator.class.php');
			if (!Data_Validator::is_valid($_POST, array('new_email' => 'valid_email|required|max_length[255]'), array('new_email' => 'trim')))
				fatal_error(sprintf($txt['valid_email_needed'], htmlspecialchars($_POST['new_email'], ENT_COMPAT, 'UTF-8')), false);

			// Make sure their email isn't banned.
			isBannedEmail($_POST['new_email'], 'cannot_register', $txt['ban_register_prohibited']);

			// Ummm... don't even dare try to take someone else's email!!
			// @todo Separate the sprintf?
			if (userByEmail($_POST['new_email']))
				fatal_lang_error('email_in_use', false, array(htmlspecialchars($_POST['new_email'], ENT_COMPAT, 'UTF-8')));

			updateMemberData($row['id_member'], array('email_address' => $_POST['new_email']));
			$row['email_address'] = $_POST['new_email'];

			$email_change = true;
		}

		// Resend the password, but only if the account wasn't activated yet.
		if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'resend' && ($row['is_activated'] == 0 || $row['is_activated'] == 2) && (!isset($_REQUEST['code']) || $_REQUEST['code'] == ''))
		{
			require_once(SUBSDIR . '/Mail.subs.php');

			$replacements = array(
				'REALNAME' => $row['real_name'],
				'USERNAME' => $row['member_name'],
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $row['id_member'] . ';code=' . $row['validation_code'],
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $row['id_member'],
				'ACTIVATIONCODE' => $row['validation_code'],
				'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
			);

			$emaildata = loadEmailTemplate('resend_activate_message', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

			$context['page_title'] = $txt['invalid_activation_resend'];

			// Don't let them wack away on their resend
			spamProtection('remind');

			// This will ensure we don't actually get an error message if it works!
			$context['error_title'] = '';

			fatal_lang_error(!empty($email_change) ? 'change_email_success' : 'resend_email_success', false);
		}

		// Quit if this code is not right.
		if (empty($_REQUEST['code']) || $row['validation_code'] != $_REQUEST['code'])
		{
			if (!empty($row['is_activated']) && $row['is_activated'] == 1)
				fatal_lang_error('already_activated', false);
			elseif ($row['validation_code'] == '')
			{
				loadLanguage('Profile');
				fatal_error($txt['registration_not_approved'] . ' <a href="' . $scripturl . '?action=activate;user=' . $row['member_name'] . '">' . $txt['here'] . '</a>.', false);
			}

			$context['sub_template'] = 'retry_activate';
			$context['page_title'] = $txt['invalid_activation_code'];
			$context['member_id'] = $row['id_member'];

			return;
		}
		require_once(SUBSDIR . '/Members.subs.php');

		// Validation complete - update the database!
		approveMembers(array('members' => array($row['id_member']), 'activated_status' => $row['is_activated']));

		// Also do a proper member stat re-evaluation.
		updateStats('member', false);

		if (!isset($_POST['new_email']) && empty($row['is_activated']))
		{
			require_once(SUBSDIR . '/Notification.subs.php');
			sendAdminNotifications('activation', $row['id_member'], $row['member_name']);
		}

		$context += array(
			'page_title' => $txt['registration_successful'],
			'sub_template' => 'login',
			'default_username' => $row['member_name'],
			'default_password' => '',
			'never_expire' => false,
			'description' => $txt['activate_success']
		);
	}

	/**
	 * This function will display the contact information for the forum, as well a form to fill in.
	 * Accessed by action=coppa
	 */
	public function action_coppa()
	{
		global $context, $modSettings, $txt;

		loadLanguage('Login');
		loadTemplate('Register');

		// No User ID??
		if (!isset($_GET['member']))
			fatal_lang_error('no_access', false);

		// Get the user details...
		require_once(SUBSDIR . '/Members.subs.php');
		$member = getBasicMemberData((int) $_GET['member'], array('authentication' => true));

		// If doesn't exist or not pending coppa
		if (empty($member) || $member['is_activated'] != 5)
			fatal_lang_error('no_access', false);

		if (isset($_GET['form']))
		{
			// Some simple contact stuff for the forum.
			$context['forum_contacts'] = (!empty($modSettings['coppaPost']) ? $modSettings['coppaPost'] . '<br /><br />' : '') . (!empty($modSettings['coppaFax']) ? $modSettings['coppaFax'] . '<br />' : '');
			$context['forum_contacts'] = !empty($context['forum_contacts']) ? $context['forum_name_html_safe'] . '<br />' . $context['forum_contacts'] : '';

			// Showing template?
			if (!isset($_GET['dl']))
			{
				// Shortcut for producing underlines.
				$context['ul'] = '<u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>';
				Template_Layers::getInstance()->removeAll();
				$context['sub_template'] = 'coppa_form';
				$context['page_title'] = replaceBasicActionUrl($txt['coppa_form_title']);
				$context['coppa_body'] = str_replace(array('{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}'), array($context['ul'], $context['ul'], $member['member_name']), replaceBasicActionUrl($txt['coppa_form_body']));
			}
			// Downloading.
			else
			{
				// The data.
				$ul = '                ';
				$crlf = "\r\n";
				$data = $context['forum_contacts'] . $crlf . $txt['coppa_form_address'] . ':' . $crlf . $txt['coppa_form_date'] . ':' . $crlf . $crlf . $crlf . replaceBasicActionUrl($txt['coppa_form_body']);
				$data = str_replace(array('{PARENT_NAME}', '{CHILD_NAME}', '{USER_NAME}', '<br>', '<br />'), array($ul, $ul, $member['member_name'], $crlf, $crlf), $data);

				// Send the headers.
				header('Connection: close');
				header('Content-Disposition: attachment; filename="approval.txt"');
				header('Content-Type: ' . (isBrowser('ie') || isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
				header('Content-Length: ' . count($data));

				echo $data;
				obExit(false);
			}
		}
		else
		{
			$context += array(
				'page_title' => $txt['coppa_title'],
				'sub_template' => 'coppa',
			);

			$context['coppa'] = array(
				'body' => str_replace('{MINIMUM_AGE}', $modSettings['coppaAge'], replaceBasicActionUrl($txt['coppa_after_registration'])),
				'many_options' => !empty($modSettings['coppaPost']) && !empty($modSettings['coppaFax']),
				'post' => empty($modSettings['coppaPost']) ? '' : $modSettings['coppaPost'],
				'fax' => empty($modSettings['coppaFax']) ? '' : $modSettings['coppaFax'],
				'phone' => empty($modSettings['coppaPhone']) ? '' : str_replace('{PHONE_NUMBER}', $modSettings['coppaPhone'], $txt['coppa_send_by_phone']),
				'id' => $_GET['member'],
			);
		}
	}

	/**
	 * Show the verification code or let it hear.
	 * Accessed by ?action=verificationcode
	 */
	public function action_verificationcode()
	{
		global $context, $scripturl;

		$verification_id = isset($_GET['vid']) ? $_GET['vid'] : '';
		$code = $verification_id && isset($_SESSION[$verification_id . '_vv']) ? $_SESSION[$verification_id . '_vv']['code'] : (isset($_SESSION['visual_verification_code']) ? $_SESSION['visual_verification_code'] : '');

		// Somehow no code was generated or the session was lost.
		if (empty($code))
		{
			header('Content-Type: image/gif');
			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}
		// Show a window that will play the verification code.
		elseif (isset($_REQUEST['sound']))
		{
			loadLanguage('Login');
			loadTemplate('Register');

			$context['verification_sound_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand()) . ($verification_id ? ';vid=' . $verification_id : '') . ';format=.wav';
			$context['sub_template'] = 'verification_sound';
			Template_Layers::getInstance()->removeAll();

			obExit();
		}
		// If we have GD, try the nice code.
		elseif (empty($_REQUEST['format']))
		{
			require_once(SUBSDIR . '/Graphics.subs.php');

			if (in_array('gd', get_loaded_extensions()) && !showCodeImage($code))
				header('HTTP/1.1 400 Bad Request');
			// Otherwise just show a pre-defined letter.
			elseif (isset($_REQUEST['letter']))
			{
				$_REQUEST['letter'] = (int) $_REQUEST['letter'];
				if ($_REQUEST['letter'] > 0 && $_REQUEST['letter'] <= strlen($code) && !showLetterImage(strtolower($code{$_REQUEST['letter'] - 1})))
				{
					header('Content-Type: image/gif');
					die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
				}
			}
			// You must be up to no good.
			else
			{
				header('Content-Type: image/gif');
				die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
			}
		}
		elseif ($_REQUEST['format'] === '.wav')
		{
			require_once(SUBSDIR . '/Sound.subs.php');

			if (!createWaveFile($code))
				header('HTTP/1.1 400 Bad Request');
		}

		// We all die one day...
		die();
	}

	/**
	 * Shows the contact form for the user to fill out
	 * Needs to be enabled to be used
	 */
	public function action_contact()
	{
		global $context, $txt, $user_info, $modSettings;

		// Already inside, no need to use this, just send a PM
		// Disabled, you cannot enter.
		if (!$user_info['is_guest'] || empty($modSettings['enable_contactform']) || $modSettings['enable_contactform'] == 'disabled')
			redirectexit();

		loadLanguage('Login');
		loadTemplate('Register');

		if (isset($_REQUEST['send']))
		{
			checkSession('post');
			validateToken('contact');
			spamProtection('contact');

			// No errors, yet.
			$context['errors'] = array();
			loadLanguage('Errors');

			// Could they get the right send topic verification code?
			require_once(SUBSDIR . '/VerificationControls.class.php');
			require_once(SUBSDIR . '/Members.subs.php');

			// form validation
			require_once(SUBSDIR . '/DataValidator.class.php');
			$validator = new Data_Validator();
			$validator->sanitation_rules(array(
				'emailaddress' => 'trim',
				'contactmessage' => 'trim|Util::htmlspecialchars'
			));
			$validator->validation_rules(array(
				'emailaddress' => 'required|valid_email',
				'contactmessage' => 'required'
			));
			$validator->text_replacements(array(
				'emailaddress' => $txt['error_email'],
				'contactmessage' => $txt['error_message']
			));

			// Any form errors
			if (!$validator->validate($_POST))
				$context['errors'] = $validator->validation_errors();

			// How about any verification errors
			$verificationOptions = array(
				'id' => 'contactform',
			);
			$context['require_verification'] = create_control_verification($verificationOptions, true);

			if (is_array($context['require_verification']))
			{
				foreach ($context['require_verification'] as $error)
					$context['errors'][] = $txt['error_' . $error];
			}

			// No errors, then send the PM to the admins
			if (empty($context['errors']))
			{
				$admins = admins();
				if (!empty($admins))
				{
					require_once(SUBSDIR . '/PersonalMessage.subs.php');
					sendpm(array('to' => array_keys($admins), 'bcc' => array()), $txt['contact_subject'], $_REQUEST['contactmessage'], false, array('id' => 0, 'name' => $validator->emailaddress, 'username' => $validator->emailaddress));
				}

				// Send the PM
				redirectexit('action=contact;sa=done');
			}
			else
			{
				$context['emailaddress'] = $validator->emailaddress;
				$context['contactmessage'] = $validator->contactmessage;
			}
		}

		if (isset($_GET['sa']) && $_GET['sa'] == 'done')
			$context['sub_template'] = 'contact_form_done';
		else
		{
			$context['sub_template'] = 'contact_form';
			$context['page_title'] = $txt['admin_contact_form'];

			require_once(SUBSDIR . '/VerificationControls.class.php');
			$verificationOptions = array(
				'id' => 'contactform',
			);
			$context['require_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		createToken('contact');
	}

	/**
	 * See if a username already exists.
	 */
	private function _registerCheckUsername()
	{
		global $context;

		// This is XML!
		loadTemplate('Xml');
		$context['sub_template'] = 'check_username';
		$context['checked_username'] = isset($_GET['username']) ? un_htmlspecialchars($_GET['username']) : '';
		$context['valid_username'] = true;

		// Clean it up like mother would.
		$context['checked_username'] = preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $context['checked_username']);

		$errors = Error_Context::context('valid_username', 0);

		require_once(SUBSDIR . '/Auth.subs.php');
		validateUsername(0, $context['checked_username'], 'valid_username', true, false);

		$context['valid_username'] = !$errors->hasErrors();
	}
}
