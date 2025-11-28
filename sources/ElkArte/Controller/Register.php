<?php

/**
 * This file has two main jobs, but they really are one.  It registers new
 * members, and it helps the administrator moderate member registrations.
 * Similarly, it handles account activation as well.
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

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Agreement;
use ElkArte\Errors\ErrorContext;
use ElkArte\Errors\Errors;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\DataValidator;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\PrivacyPolicy;
use ElkArte\Profile\ProfileOptions;
use ElkArte\Request;

/**
 * It registers new members, and it allows the administrator moderate member registration
 */
class Register extends AbstractController
{
	/** @var int is_activated value key is as follows */
	private const STATUS_NOT_ACTIVE = 0;
	private const STATUS_APPROVED_AND_ACTIVE = 1;
	private const STATUS_AWAITING_REACTIVATION = 2;
	private const STATUS_AWAITING_COPPA = 5;

	/** @var array Holds the results of a findUser() request */
	private $_row;

	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = '')
	{
		return parent::trackStats($action);
	}

	/**
	 * Pre Dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		// Check if the administrator has it disabled.
		if (empty($modSettings['registration_method']))
		{
			return;
		}

		if ((int) $modSettings['registration_method'] !== 3)
		{
			return;
		}

		throw new Exception('registration_disabled', false);
	}

	/**
	 * Intended entry point for this class.
	 *
	 * By default, this is called for action=register
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		// Add the sub-action array to dispatch accordingly
		$subActions = [
			'register' => [$this, 'action_register'],
			'register2' => [$this, 'action_register2'],
			'usernamecheck' => [$this, 'action_registerCheckUsername'],
			'activate' => [$this, 'action_activate'],
			'agrelang' => [$this, 'action_agrelang'],
			'privacypol' => [$this, 'action_privacypol'],
			'agreement' => [$this, 'action_agreement'],
		];

		// Setup the action handler
		$action = new Action('register');
		$subAction = $action->initialize($subActions, 'register');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 * Begin the registration process.
	 *
	 * - Triggers the prepare_context event
	 *
	 * Accessed by ?action=register
	 *
	 * @uses template_registration_agreement or template_registration_form sub template in Register.template,
	 * @uses Login language file
	 */
	public function action_register(): void
	{
		global $txt, $context, $modSettings, $scripturl;

		// If this user is an admin - redirect them to the admin registration page.
		if ($this->user->is_guest === false && allowedTo('moderate_forum'))
		{
			redirectexit('action=admin;area=regcenter;sa=register');
		}
		// You are not a guest, so you are a member - and members don't get to register twice!
		elseif (empty($this->user->is_guest))
		{
			redirectexit();
		}

		// Confused and want to contact the admins instead
		if (isset($this->_req->post->show_contact))
		{
			redirectexit('action=about;sa=contact');
		}

		// If we have language support enabled then they need to be loaded
		if ($this->_load_language_support())
		{
			redirectexit('action=register');
		}

		Txt::load('Login+Profile');
		theme()->getTemplates()->load('Register');
		theme()->getTemplates()->load('ProfileOptions');

		// Do we need them to agree to the registration agreement, first?
		$context['require_agreement'] = !empty($modSettings['requireAgreement']);
		$context['checkbox_agreement'] = !empty($modSettings['checkboxAgreement']);
		$context['require_privacypol'] = !empty($modSettings['requirePrivacypolicy']);
		$context['registration_passed_agreement'] = !empty($_SESSION['registration_agreed']);
		$context['registration_passed_privacypol'] = !empty($_SESSION['registration_privacypolicy']);
		$context['show_coppa'] = !empty($modSettings['coppaAge']);
		$context['show_contact_button'] = !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] === 'registration';
		$context['insert_display_name'] = !empty($modSettings['show_DisplayNameOnRegistration']);

		// Underage restrictions?
		if ($context['show_coppa'])
		{
			$context['skip_coppa'] = false;
			$context['coppa_agree_above'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_above'], $modSettings['coppaAge']);
			$context['coppa_agree_below'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_below'], $modSettings['coppaAge']);
		}

		// What step are we at?
		$current_step = $this->_req->getPost('step', 'intval', ($context['require_agreement'] && !$context['checkbox_agreement'] ? 1 : 2));

		// Does this user agree to the registration agreement?
		if ($current_step === 1 && (isset($this->_req->post->accept_agreement) || isset($this->_req->post->accept_agreement_coppa)))
		{
			$context['registration_passed_agreement'] = $_SESSION['registration_agreed'] = true;
			$context['registration_passed_privacypol'] = $_SESSION['registration_privacypolicy'] = true;
			$current_step = 2;

			// Skip the coppa procedure if the user says he's old enough.
			if ($context['show_coppa'])
			{
				$_SESSION['skip_coppa'] = !empty($this->_req->post->accept_agreement);

				// Are they saying they're under age, while under age registration is disabled?
				if (empty($modSettings['coppaType']) && empty($_SESSION['skip_coppa']))
				{
					throw new Exception('Login.under_age_registration_prohibited', false, [$modSettings['coppaAge']]);
				}
			}
		}
		// Make sure they don't squeeze through without agreeing.
		elseif ($current_step > 1 && $context['require_agreement'] && !$context['checkbox_agreement'] && !$context['registration_passed_agreement'])
		{
			$current_step = 1;
		}

		// Show the user the right form.
		$context['sub_template'] = $current_step === 1 ? 'registration_agreement' : 'registration_form';
		$context['page_title'] = $current_step === 1 ? $txt['registration_agreement'] : $txt['registration_form'];
		loadJavascriptFile(['register.js', 'ext/mailcheck.min.js']);

		// Add the register chain to the link tree.
		$context['breadcrumbs'][] = [
			'url' => $scripturl . '?action=register',
			'name' => $txt['register'],
		];

		// Prepare the time gate! Done like this to allow later steps to reset the limit for any reason
		if (!isset($_SESSION['register']))
		{
			$_SESSION['register'] = [
				'timenow' => time(),
				// minimum number of seconds required on this page for registration
				'limit' => 8,
			];
		}
		else
		{
			$_SESSION['register']['timenow'] = time();
		}

		// If you have to agree to the agreement, it needs to be fetched from the file.
		$agreement = new Agreement($this->user->language);
		$context['agreement'] = $agreement->getParsedText();

		if (empty($context['agreement']))
		{
			// No file found or a blank file, log the error so the admin knows there is a problem!
			Txt::load('Errors');
			Errors::instance()->log_error($txt['registration_agreement_missing'], 'critical');
			throw new Exception('registration_disabled', false);
		}

		if (!empty($context['require_privacypol']))
		{
			$privacypol = new PrivacyPolicy($this->user->language);
			$context['privacy_policy'] = $privacypol->getParsedText();

			if (empty($context['privacy_policy']))
			{
				// No file found or a blank file, log the error so the admin knows there is a problem!
				Txt::load('Errors');
				Errors::instance()::instance()->log_error($txt['registration_privacy_policy_missing'], 'critical');
				throw new \Exception('registration_disabled', false);
			}
		}

		// If we have language support enabled then they need to be loaded
		$this->_load_language_support();

		// Any custom or standard profile fields we want filled in during registration?
		$this->_load_profile_fields();

		// Trigger the prepare_context event
		$this->_events->trigger('prepare_context', ['current_step' => $current_step]);

		// See whether we have some pre-filled values.
		$context['username'] = $this->_req->getPost('user', '\\ElkArte\\Helper\\Util::htmlspecialchars', '');
		$context['email'] = $this->_req->getPost('email', '\\ElkArte\\Helper\\Util::htmlspecialchars', '');
		$context['notify_announcements'] = (int) !empty($this->_req->post->notify_announcements);

		// Were there any errors?
		$context['registration_errors'] = [];
		$reg_errors = ErrorContext::context('register', 0);
		if ($reg_errors->hasErrors())
		{
			$context['registration_errors'] = $reg_errors->prepareErrors();
		}

		createToken('register');
	}

	/**
	 * Handles the registration process for members using ElkArte registration
	 *
	 * What it does:
	 *
	 * - Validates all requirements have been filled in properly
	 * - Passes final processing to do_register
	 * - Directs back to register on errors
	 * - Triggers the before_complete_register event
	 *
	 * Accessed by ?action=register;sa=register2
	 */
	public function action_register2(): void
	{
		global $modSettings;

		// Start collecting together any errors.
		$reg_errors = ErrorContext::context('register', 0);

		// Make sure registration is enabled
		$this->_can_register();

		checkSession();
		if (!validateToken('register', 'post', true, false))
		{
			$reg_errors->addError('token_verification');
		}

		// If we're using an agreement checkbox, did they check it?
		if (!empty($modSettings['checkboxAgreement']) && !empty($this->_req->post->checkbox_agreement))
		{
			$_SESSION['registration_agreed'] = true;
		}

		// Using coppa and the registration checkbox?
		if (!empty($modSettings['coppaAge']) && !empty($modSettings['checkboxAgreement']) && !empty($this->_req->post->accept_agreement))
		{
			$_SESSION['skip_coppa'] = true;
		}

		// Well, if you don't agree to the Registration Agreement, you can't register.
		if (!empty($modSettings['requireAgreement']) && empty($_SESSION['registration_agreed']))
		{
			redirectexit();
		}

		if (!empty($modSettings['requireAgreement']) && !empty($modSettings['requirePrivacypolicy']) && !empty($this->_req->post->checkbox_privacypol))
		{
			$_SESSION['registration_privacypolicy'] = true;
		}

		// Well, if you don't agree to the Privacy Policy, you can't register.
		if (!empty($modSettings['requireAgreement']) && !empty($modSettings['requirePrivacypolicy']) && empty($_SESSION['registration_privacypolicy']))
		{
			redirectexit();
		}

		// Make sure they came from *somewhere*, have a session.
		if (!isset($_SESSION['old_url']))
		{
			redirectexit('action=register');
		}

		// If we don't require an agreement, we need a extra check for coppa.
		if (empty($modSettings['requireAgreement']) && !empty($modSettings['coppaAge']))
		{
			$_SESSION['skip_coppa'] = !empty($this->_req->post->accept_agreement);
		}

		// Are they under age, and under age users are banned?
		if (!empty($modSettings['coppaAge']) && empty($modSettings['coppaType']) && empty($_SESSION['skip_coppa']))
		{
			throw new Exception('Login.under_age_registration_prohibited', false, [$modSettings['coppaAge']]);
		}

		// Check the time gate for miscreants. First make sure they came from somewhere that actually set it up.
		if (empty($_SESSION['register']['timenow']) || empty($_SESSION['register']['limit']))
		{
			redirectexit('action=register');
		}

		// Failing that, check the time limit for excessive speed.
		if (time() - $_SESSION['register']['timenow'] < $_SESSION['register']['limit'])
		{
			Txt::load('Login');
			$reg_errors->addError('too_quickly');
		}

		// Maybe the filled in our hidden honey pot form field like a good bot would
		if (!empty($this->_req->getPost('reason_for_joining_hp', 'trim', '')))
		{
			// Its not missing, it just should not be there
			Txt::load('Login');
			$reg_errors->addError('error_missing_information');
		}

		// Trigger any events required before we complete registration, like captcha verification
		$this->_events->trigger('before_complete_register', ['reg_errors' => $reg_errors]);

		$this->do_register();
	}

	/**
	 * Actually register the member.
	 *
	 * - Called from Register controller
	 * - Does the actual registration to the system
	 */
	public function do_register(): ?bool
	{
		global $txt, $modSettings, $context;

		// Start collecting together any errors.
		$reg_errors = ErrorContext::context('register', 0);

		// Clean the form values
		foreach ($this->_req->post as $key => $value)
		{
			if (!is_array($value))
			{
				$this->_req->post->{$key} = Util::htmltrim__recursive(str_replace(["\n", "\r"], '', $value));
			}
		}

		// A little security to any secret answer ... @todo increase?
		if ($this->_req->getPost('secret_answer', 'trim', '') !== '')
		{
			$this->_req->post->secret_answer = md5($this->_req->post->secret_answer);
		}

		// Needed for isReservedName() and registerMember().
		require_once(SUBSDIR . '/Members.subs.php');

		// Validation... even if we're not a mall.
		if (isset($this->_req->post->real_name) && (!empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum')))
		{
			$this->_req->post->real_name = trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $this->_req->post->real_name));
			$has_real_name = true;
		}
		else
		{
			$has_real_name = false;
		}

		// Handle a string as a birth date...
		if ($this->_req->getPost('birthdate', 'trim', '') !== '')
		{
			$this->_req->post->birthdate = Util::strftime('%Y-%m-%d', strtotime($this->_req->post->birthdate));
		}
		// Or birthdate parts...
		elseif (!empty($this->_req->post->bday1) && !empty($this->_req->post->bday2))
		{
			$this->_req->post->birthdate = sprintf('%04d-%02d-%02d', empty($this->_req->post->bday3) ? 0 : (int) $this->_req->post->bday3, (int) $this->_req->post->bday1, (int) $this->_req->post->bday2);
		}

		// Validate the passed language file.
		if (isset($this->_req->post->lngfile) && !empty($modSettings['userLanguage']))
		{
			// Do we have any languages?
			$context['languages'] = getLanguages();

			// Did we find it?
			if (isset($context['languages'][$this->_req->post->lngfile]))
			{
				$_SESSION['language'] = $this->_req->post->lngfile;
			}
			else
			{
				unset($this->_req->post->lngfile);
			}
		}
		elseif (isset($this->_req->post->lngfile))
		{
			unset($this->_req->post->lngfile);
		}

		// Set the options needed for registration.
		$regOptions = [
			'interface' => 'guest',
			'username' => empty($this->_req->post->user) ? '' : $this->_req->post->user,
			'email' => empty($this->_req->post->email) ? '' : $this->_req->post->email,
			'password' => empty($this->_req->post->passwrd1) ? '' : $this->_req->post->passwrd1,
			'password_check' => empty($this->_req->post->passwrd2) ? '' : $this->_req->post->passwrd2,
			'auth_method' => empty($this->_req->post->authenticate) ? '' : $this->_req->post->authenticate,
			'check_reserved_name' => true,
			'check_password_strength' => true,
			'check_email_ban' => true,
			'send_welcome_email' => !empty($modSettings['send_welcomeEmail']),
			'require' => !empty($modSettings['coppaAge']) && empty($_SESSION['skip_coppa']) ? 'coppa' : (empty($modSettings['registration_method']) ? 'nothing' : ($modSettings['registration_method'] == 1 ? 'activation' : 'approval')),
			'extra_register_vars' => $this->_extra_vars($has_real_name),
			'theme_vars' => [],
		];

		// Registration options are always default options...
		if (isset($this->_req->post->default_options))
		{
			$this->_req->post->options = isset($this->_req->post->options) ? $this->_req->post->options + $this->_req->post->default_options : $this->_req->post->default_options;
		}

		$regOptions['theme_vars'] = isset($this->_req->post->options) && is_array($this->_req->post->options) ? $this->_req->post->options : [];

		// Make sure they are clean, dammit!
		$regOptions['theme_vars'] = Util::htmlspecialchars__recursive($regOptions['theme_vars']);

		// Check whether we have fields that simply MUST be displayed?
		$profileFields = new \ElkArte\Profile\ProfileFields();
		$profileFields->loadCustomFields(0, 'register', $this->_req->post->customfield ?? []);

		foreach ($context['custom_fields'] as $row)
		{
			// Don't allow overriding of the theme variables.
			if (isset($regOptions['theme_vars'][$row['colname']]))
			{
				unset($regOptions['theme_vars'][$row['colname']]);
			}

			// Prepare the value!
			$value = isset($this->_req->post->customfield[$row['colname']]) ? trim($this->_req->post->customfield[$row['colname']]) : '';

			// We only care for text fields as the others are valid to be empty.
			if (!in_array($row['field_type'], ['check', 'select', 'radio']))
			{
				$is_valid = isCustomFieldValid($row, $value);
				if ($is_valid !== true)
				{
					$err_params = [$row['name']];
					if ($is_valid === 'custom_field_too_long')
					{
						$err_params[] = $row['field_length'];
					}

					$reg_errors->addError([$is_valid, $err_params]);
				}
			}

			// Is this required but not there?
			if ($row['show_reg'] > 1 && trim($value) === '')
			{
				$reg_errors->addError(['custom_field_empty', [$row['name']]]);
			}
		}

		// Lets check for other errors before trying to register the member.
		if ($reg_errors->hasErrors())
		{
			$this->_req->post->step = 2;

			// If they've filled in some details but made an error then they need less time to finish
			$_SESSION['register']['limit'] = 4;

			$this->action_register();

			return false;
		}

		// Registration needs to know your IP
		$req = Request::instance();

		$regOptions['ip'] = $this->user->ip;
		$regOptions['ip2'] = $req->ban_ip();
		$memberID = registerMember($regOptions, 'register');

		// If there are "important" errors and you are not an admin: log the first error
		// Otherwise grab all of them and don't log anything
		if ($reg_errors->hasErrors(1) && $this->user->is_admin === false)
		{
			foreach ($reg_errors->prepareErrors(1) as $error)
			{
				throw new Exception($error, 'general');
			}
		}

		// Was there actually an error of some kind dear boy?
		if ($reg_errors->hasErrors())
		{
			$this->_req->post->step = 2;
			$this->action_register();

			return false;
		}

		$lang = empty($modSettings['userLanguage']) ? 'english' : $modSettings['userLanguage'];
		$agreement = new Agreement($lang);
		$agreement->accept($memberID, $this->user->ip, empty($modSettings['agreementRevision']) ? Util::strftime('%Y-%m-%d', forum_time(false)) : $modSettings['agreementRevision']);

		if (!empty($modSettings['requirePrivacypolicy']))
		{
			$policy = new PrivacyPolicy($lang);
			$policy->accept($memberID, $this->user->ip, empty($modSettings['privacypolicyRevision']) ? Util::strftime('%Y-%m-%d', forum_time(false)) : $modSettings['privacypolicyRevision']);
		}

		// Do our spam protection now.
		spamProtection('register');

		// We'll do custom fields after as then we get to use the helper function!
		if (!empty($this->_req->post->customfield))
		{
			require_once(SUBSDIR . '/Profile.subs.php');
			makeCustomFieldChanges($memberID, 'register');
		}

		// If COPPA has been selected then things get complicated, setup the template.
		if (!empty($modSettings['coppaAge']) && empty($_SESSION['skip_coppa']))
		{
			redirectexit('action=about;sa=coppa;member=' . $memberID);
		}
		// Basic template variable setup.
		elseif (!empty($modSettings['registration_method']))
		{
			theme()->getTemplates()->load('Register');

			$context += [
				'page_title' => $txt['register'],
				'title' => $txt['registration_successful'],
				'sub_template' => 'after',
				'description' => $modSettings['registration_method'] == 2 ? $txt['approval_after_registration'] : $txt['activate_after_registration']
			];
		}
		else
		{
			call_integration_hook('integrate_activate', [$regOptions['username'], 1, 1]);

			setLoginCookie(60 * $modSettings['cookieTime'], $memberID, hash('sha256', $regOptions['register_vars']['passwd'] . $regOptions['register_vars']['password_salt']));

			redirectexit('action=auth;sa=check;member=' . $memberID);
		}

		return null;
	}

	/**
	 * Checks if registrations are enabled and the user didn't just register
	 */
	private function _can_register(): void
	{
		global $modSettings;

		// You can't register if it's disabled.
		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 3)
		{
			throw new Exception('registration_disabled', false);
		}

		// Make sure they didn't just register with this session.
		if (!empty($_SESSION['just_registered']) && empty($modSettings['disableRegisterCheck']))
		{
			throw new Exception('register_only_once', false);
		}
	}

	/**
	 * Collect all extra registration fields someone might have filled in.
	 *
	 * What it does:
	 *
	 * - Classifies variables as possible string, int, float or bool
	 * - Casts all posted data to the proper type (string, float, etc)
	 * - Drops fields that we specially exclude during registration
	 *
	 * @param bool $has_real_name - if true adds 'real_name' as well
	 *
	 * @return array
	 * @throws Exception
	 */
	private function _extra_vars($has_real_name): array
	{
		global $modSettings;

		// Define the fields that may be enabled for registration
		$possible_strings = [
			'birthdate',
			'time_format',
			'buddy_list',
			'pm_ignore_list',
			'smiley_set',
			'avatar',
			'lngfile',
			'secret_question', 'secret_answer',
			'website_url', 'website_title',
		];

		$possible_ints = [
			'pm_email_notify',
			'notify_types',
			'notify_from',
			'id_theme',
		];

		$possible_floats = [
			'time_offset',
		];

		$possible_bools = [
			'notify_announcements', 'notify_regularity', 'notify_send_body', 'show_online',
		];

		if ($has_real_name && trim($this->_req->post->real_name) !== '' && !isReservedName($this->_req->post->real_name) && Util::strlen($this->_req->post->real_name) < 60)
		{
			$possible_strings[] = 'real_name';
		}

		// Some of these fields we may not want.
		if (!empty($modSettings['registration_fields']))
		{
			// But we might want some of them if the admin asks for them.
			$reg_fields = explode(',', $modSettings['registration_fields']);

			$exclude_fields = [];

			// Website is a little different
			if (!in_array('website', $reg_fields))
			{
				$exclude_fields = array_merge($exclude_fields, ['website_url', 'website_title']);
			}

			// We used to accept signature on registration but it's being abused by spammers these days, so no more.
			$exclude_fields[] = 'signature';
		}
		else
		{
			$exclude_fields = ['signature', 'website_url', 'website_title'];
		}

		$possible_strings = array_diff($possible_strings, $exclude_fields);
		$possible_ints = array_diff($possible_ints, $exclude_fields);
		$possible_floats = array_diff($possible_floats, $exclude_fields);
		$possible_bools = array_diff($possible_bools, $exclude_fields);

		$extra_register_vars = [];

		// Include the additional options that might have been filled in.
		foreach ($possible_strings as $var)
		{
			if (isset($this->_req->post->{$var}))
			{
				$extra_register_vars[$var] = Util::htmlspecialchars($this->_req->post->{$var}, ENT_QUOTES);
			}
		}

		foreach ($possible_ints as $var)
		{
			if (isset($this->_req->post->{$var}))
			{
				$extra_register_vars[$var] = (int) $this->_req->post->{$var};
			}
		}

		foreach ($possible_floats as $var)
		{
			if (isset($this->_req->post->{$var}))
			{
				$extra_register_vars[$var] = (float) $this->_req->post->{$var};
			}
		}

		foreach ($possible_bools as $var)
		{
			if (isset($this->_req->post->{$var}))
			{
				$extra_register_vars[$var] = empty($this->_req->post->{$var}) ? 0 : 1;
			}
		}

		return $extra_register_vars;
	}

	/**
	 * Sets the users language file
	 *
	 * What it does:
	 *
	 * - If language support is enabled, loads what's available
	 * - Verifies the users choice is available
	 * - Sets in context / session
	 *
	 * @return bool true if the language was changed, false if not.
	 */
	private function _load_language_support(): bool
	{
		global $context, $modSettings, $language;

		// Language support enabled
		if (!empty($modSettings['userLanguage']))
		{
			// Do we have any languages?
			$languages = getLanguages();

			if (isset($this->_req->post->lngfile, $languages[$this->_req->post->lngfile]))
			{
				$_SESSION['language'] = $this->_req->post->lngfile;
				if ($_SESSION['language'] !== ucfirst($this->user->language))
				{
					return true;
				}
			}

			// Not selected, or not found, use the site default
			$selectedLanguage = empty($_SESSION['language']) ? $language : $_SESSION['language'];

			// Try to find our selected language.
			foreach ($languages as $key => $lang)
			{
				$context['languages'][$key]['name'] = $lang['name'];

				// Found it!
				if (ucfirst($selectedLanguage) === $lang['name'])
				{
					$context['languages'][$key]['selected'] = true;
				}
			}
		}

		return false;
	}

	/**
	 * Load standard and custom registration profile fields
	 *
	 * @uses ProfileFields->loadCustomFields() Loads standard fields in to context
	 * @uses setupProfileContext() Loads supplied fields in to context
	 */
	private function _load_profile_fields(): void
	{
		global $context, $modSettings, $cur_profile;

		// Any custom fields to load?
		$profileFields = new \ElkArte\Profile\ProfileFields();
		$profileFields->loadCustomFields(0, 'register');

		// Or any standard ones?
		if (!empty($modSettings['registration_fields']))
		{
			// Setup some important context.
			Txt::load('Profile');
			theme()->getTemplates()->load('Profile');

			$context['user']['is_owner'] = true;

			// Here, and here only, emulate the permissions the user would have to do this.
			$this->user->permissions = array_merge($this->user->permissions, ['profile_account_own', 'profile_extra_own']);
			$reg_fields = ProfileOptions::getFields('registration');

			// We might have had some submissions on this front - go check.
			foreach ($reg_fields['fields'] as $field)
			{
				if (isset($this->_req->post->{$field}))
				{
					$cur_profile[$field] = Util::htmlspecialchars($this->_req->post->{$field});
				}
			}

			// Load all the fields in question.
			setupProfileContext($reg_fields['fields'], $reg_fields['hook']);
		}
	}

	/**
	 * Verify the activation code, and activate the user if correct.
	 *
	 * What it does:
	 *
	 * - Accessed by ?action=register;sa=activate
	 * - Processes activation code requests
	 * - Checks if the user is already activate and if so does nothing
	 * - Prevents a user from using an existing email
	 */
	public function action_activate(): void
	{
		global $context, $txt, $modSettings;

		require_once(SUBSDIR . '/Auth.subs.php');

		// Logged in users should not bother to activate their accounts
		if (!empty($this->user->id))
		{
			redirectexit();
		}

		Txt::load('Login');
		theme()->getTemplates()->load('Login');

		// Need a user id to activate
		if (empty($this->_req->query->u) && empty($this->_req->post->user))
		{
			// Immediate 0 or disabled 3 means no need to try and activate
			if (empty($modSettings['registration_method']) || $modSettings['registration_method'] === '3')
			{
				throw new Exception('no_access', false);
			}

			// Otherwise its simply invalid
			$context['member_id'] = 0;
			$context['sub_template'] = 'resend';
			$context['page_title'] = $txt['invalid_activation_resend'];
			$context['can_activate'] = $modSettings['registration_method'] === '1';
			$context['default_username'] = $this->_req->getPost('user', 'trim', '');

			return;
		}

		// Get the user from the database...
		$this->_row = findUser(empty($this->_req->query->u)
			? 'member_name = {string:email_address} OR email_address = {string:email_address}'
			: 'id_member = {int:id_member}',
			[
				'id_member' => $this->_req->getQuery('u', 'intval', 0),
				'email_address' => $this->_req->getPost('user', 'trim', ''),
			], false
		);

		// Does this user exist at all?
		if (empty($this->_row))
		{
			$context['sub_template'] = 'retry_activate';
			$context['page_title'] = $txt['invalid_userid'];
			$context['member_id'] = 0;

			return;
		}

		// Change their email address if not active 0 or awaiting reactivation 2? ( they probably tried a fake one first :P )
		$email_change = $this->_activate_change_email();

		// Resend the password, but only if the account wasn't activated yet (0 or 2)
		$this->_activate_resend($email_change);

		// Quit if this code is not right.
		if ($this->_activate_validate_code() === false)
		{
			return;
		}

		// Validation complete - update the database!
		require_once(SUBSDIR . '/Members.subs.php');
		approveMembers(['members' => [$this->_row['id_member']], 'activated_status' => $this->_row['is_activated']]);

		// Also do a proper member stat re-evaluation.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberStats();

		if (!isset($this->_req->post->new_email) && empty($this->_row['is_activated']))
		{
			require_once(SUBSDIR . '/Notification.subs.php');
			sendAdminNotifications('activation', $this->_row['id_member'], $this->_row['member_name']);
		}

		$context += [
			'page_title' => $txt['registration_successful'],
			'sub_template' => 'login',
			'default_username' => $this->_row['member_name'],
			'default_password' => '',
			'never_expire' => false,
			'description' => $txt['activate_success']
		];
	}

	/**
	 * Change their email address if not active
	 *
	 * What it does:
	 *
	 * - Requires the user enter the id/password for the account
	 * - The account must not be active 0 or awaiting reactivation 2
	 */
	private function _activate_change_email(): bool
	{
		global $modSettings, $txt;

		if (isset($this->_req->post->new_email, $this->_req->post->passwd)
			&& ($this->_row['is_activated'] === self::STATUS_NOT_ACTIVE || $this->_row['is_activated'] === self::STATUS_AWAITING_REACTIVATION)
			&& validateLoginPassword($this->_req->post->passwd, $this->_row['passwd'], $this->_row['member_name'], true))
		{
			if (empty($modSettings['registration_method']) || $modSettings['registration_method'] == 3)
			{
				throw new Exception('no_access', false);
			}

			// @todo Separate the sprintf?
			if (!DataValidator::is_valid($this->_req->post, ['new_email' => 'valid_email|required|max_length[255]'], ['new_email' => 'trim']))
			{
				throw new Exception(sprintf($txt['valid_email_needed'], htmlspecialchars($this->_req->post->new_email, ENT_COMPAT, 'UTF-8')), false);
			}

			// Make sure their email isn't banned.
			isBannedEmail($this->_req->post->new_email, 'cannot_register', $txt['ban_register_prohibited']);

			// Ummm... don't take someone else's email during the change
			// @todo Separate the sprintf?
			if (userByEmail($this->_req->post->new_email) === false)
			{
				throw new Exception('email_in_use', false, [htmlspecialchars($this->_req->post->new_email, ENT_COMPAT, 'UTF-8')]);
			}

			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($this->_row['id_member'], ['email_address' => $this->_req->post->new_email]);
			$this->_row['email_address'] = $this->_req->post->new_email;

			return true;
		}

		return false;
	}

	/**
	 * Resend an activation code to a user
	 *
	 * What it does:
	 *
	 * - Called with action=register;sa=activate;resend
	 * - Will resend an activation code to non-active account
	 *
	 * @param bool $email_change if the email was changed or not
	 *
	 * @throws Exception
	 */
	private function _activate_resend($email_change): void
	{
		global $scripturl, $modSettings, $language, $txt, $context;

		if (isset($this->_req->query->resend)
			&& ($this->_row['is_activated'] === self::STATUS_NOT_ACTIVE || $this->_row['is_activated'] === self::STATUS_AWAITING_REACTIVATION)
			&& $this->_req->getPost('code', 'trim', '') === '')
		{
			require_once(SUBSDIR . '/Mail.subs.php');

			// Since you lost it, you get a nice new code
			$validation_code = generateValidationCode(14);
			$this->_row['validation_code'] = substr(hash('sha256', $validation_code), 0, 10);

			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($this->_row['id_member'], ['validation_code' => $this->_row['validation_code']]);

			$replacements = [
				'REALNAME' => $this->_row['real_name'],
				'USERNAME' => $this->_row['member_name'],
				'ACTIVATIONLINK' => $scripturl . '?action=register;sa=activate;u=' . $this->_row['id_member'] . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=register;sa=activate;u=' . $this->_row['id_member'],
				'ACTIVATIONCODE' => $validation_code,
				'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
			];

			$emaildata = loadEmailTemplate('resend_activate_message', $replacements, empty($this->_row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $this->_row['lngfile']);
			sendmail($this->_row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

			$context['page_title'] = $txt['invalid_activation_resend'];

			// Don't let them wack away on their resend
			spamProtection('remind');

			// This will ensure we don't actually get an error message if it works!
			$context['error_title'] = $txt['invalid_activation_resend'];
			throw new Exception(empty($email_change) ? 'resend_email_success' : 'change_email_success', false);
		}
	}

	/**
	 * Validates a supplied activation code is valid
	 *
	 * @throws Exception already_activated, registration_not_approved
	 */
	private function _activate_validate_code(): bool
	{
		global $txt, $scripturl, $context;

		$code = substr(hash('sha256', $this->_req->getQuery('code', 'trim', '')), 0, 10);

		if ($code !== $this->_row['validation_code'])
		{
			if (!empty($this->_row['is_activated']) && $this->_row['is_activated'] === self::STATUS_APPROVED_AND_ACTIVE)
			{
				throw new Exception('already_activated', false);
			}
			if ($this->_row['validation_code'] === '')
			{
				Txt::load('Profile');
				throw new Exception($txt['registration_not_approved'] . ' <a href="' . $scripturl . '?action=register;sa=activate;user=' . $this->_row['member_name'] . '">' . $txt['here'] . '</a>.', false);
			}
			$context['sub_template'] = 'retry_activate';
			$context['page_title'] = $txt['invalid_activation_code'];
			$context['member_id'] = $this->_row['id_member'];
			return false;
		}

		return true;
	}

	/**
	 * See if a username already exists.
	 *
	 * - Used by registration template via xml request
	 */
	public function action_registerCheckUsername(): void
	{
		global $context;

		// This is XML!
		theme()->getTemplates()->load('Xml');
		$context['sub_template'] = 'check_username';
		$context['checked_username'] = isset($this->_req->query->username) ? un_htmlspecialchars($this->_req->query->username) : '';
		$context['valid_username'] = true;

		// Clean it up like mother would.
		$context['checked_username'] = preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $context['checked_username']);

		$errors = ErrorContext::context('valid_username', 0);

		require_once(SUBSDIR . '/Auth.subs.php');
		validateUsername(0, $context['checked_username'], 'valid_username', true, false);

		$context['valid_username'] = !$errors->hasErrors();
	}

	public function action_agreement(): void
	{
		global $context, $modSettings, $txt;

		if (isset($this->_req->post->accept_agreement))
		{
			$agreement = new Agreement($this->user->language);
			$agreement->accept($this->user->id, $this->user->ip, empty($modSettings['agreementRevision']) ? Util::strftime('%Y-%m-%d', forum_time(false)) : $modSettings['agreementRevision']);

			$_SESSION['agreement_accepted'] = true;
			if (isset($_SESSION['agreement_url_redirect']))
			{
				redirectexit($_SESSION['agreement_url_redirect']);
			}
			else
			{
				redirectexit();
			}
		}
		elseif (isset($this->_req->post->no_accept))
		{
			redirectexit('action=profile;area=deleteaccount');
		}
		else
		{
			$context['sub_template'] = 'registration_agreement';
			$context['register_subaction'] = 'agreement';
		}

		Txt::load('Login');
		Txt::load('Profile');
		theme()->getTemplates()->load('Register');

		// If you have to agree to the agreement, it needs to be fetched from the file.
		$agreement = new Agreement($this->user->language);
		$context['agreement'] = $agreement->getParsedText();
		$context['page_title'] = $txt['registration_agreement'];

		$context['show_coppa'] = !empty($modSettings['coppaAge']);
		$context['show_contact_button'] = !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] === 'registration';

		// Under age restrictions?
		if ($context['show_coppa'])
		{
			$context['skip_coppa'] = false;
			$context['coppa_agree_above'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_above'], $modSettings['coppaAge']);
			$context['coppa_agree_below'] = sprintf($txt[($context['require_agreement'] ? 'agreement_' : '') . 'agree_coppa_below'], $modSettings['coppaAge']);
		}

		createToken('register');
	}

	/**
	 * Action for handling privacy policy acceptance during registration.
	 *
	 * - Called from Register controller
	 * - Handles the acceptance and redirection
	 *    - If the user accepts the privacy policy, it updates the user's acceptance status in the database,
	 *      sets a session variable indicating acceptance, and redirects the user to the specified URL.
	 *    - If the user declines the privacy policy, it redirects the user to the account deletion page.
	 *    - If the user has not yet made a decision, it loads the registration agreement template for displaying the privacy policy.
	 */
	public function action_privacypol(): void
	{
		global $context, $modSettings, $txt;

		$policy = new PrivacyPolicy($this->user->language);

		if (isset($this->_req->post->accept_agreement))
		{
			$policy->accept($this->user->id, $this->user->ip, empty($modSettings['privacypolicyRevision']) ? Util::strftime('%Y-%m-%d', forum_time(false)) : $modSettings['privacypolicyRevision']);

			$_SESSION['privacypolicy_accepted'] = true;
			if (isset($_SESSION['privacypolicy_url_redirect']))
			{
				redirectexit($_SESSION['privacypolicy_url_redirect']);
			}
			else
			{
				redirectexit();
			}
		}
		elseif (isset($this->_req->post->no_accept))
		{
			redirectexit('action=profile;area=deleteaccount');
		}
		else
		{
			$context['sub_template'] = 'registration_agreement';
			$context['register_subaction'] = 'privacypol';
		}

		Txt::load('Login');
		Txt::load('Profile');
		theme()->getTemplates()->load('Register');

		$txt['agreement_agree'] = $txt['policy_agree'];
		$txt['agreement_no_agree'] = $txt['policy_no_agree'];
		$txt['registration_agreement'] = $txt['registration_privacy_policy'];
		$context['page_title'] = $txt['registration_agreement'];

		// If you have to agree to the privacy policy, it needs to be fetched from the file.
		$context['agreement'] = $policy->getParsedText();

		$context['show_coppa'] = false;
		$context['skip_coppa'] = true;
		$context['show_contact_button'] = !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] === 'registration';

		createToken('register');
	}
}
