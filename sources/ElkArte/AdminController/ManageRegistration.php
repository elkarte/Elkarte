<?php

/**
 * This file helps the administrator setting registration settings and policy
 * as well as allow the administrator to register new members themselves.
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Agreement;
use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\PrivacyPolicy;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;
use ElkArte\TokenHash;
use ElkArte\Util;

/**
 * ManageRegistration admin controller: handles the registration pages
 *
 * - allow admins (or moderators with moderate_forum permission)
 * to register a new member,
 * - to see and edit the registration agreement,
 * - to set up reserved words for forum names.
 *
 * @package Registration
 */
class ManageRegistration extends AbstractController
{
	/**
	 * Entrance point for the registration center, it checks permissions and forwards
	 * to the right method based on the subaction.
	 *
	 * - Accessed by ?action=admin;area=regcenter.
	 * - Requires either the moderate_forum or the admin_forum permission.
	 *
	 * @event integrate_sa_manage_registrations add new registration sub actions
	 * @uses Login language file
	 * @uses Register template.
	 * @see  \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		// Loading, always loading.
		Txt::load('Login');
		theme()->getTemplates()->load('Register');
		loadJavascriptFile('register.js');

		$subActions = array(
			'register' => array(
				'controller' => $this,
				'function' => 'action_register',
				'permission' => 'moderate_forum',
			),
			'agreement' => array(
				'controller' => $this,
				'function' => 'action_agreement',
				'permission' => 'admin_forum',
			),
			'privacypol' => array(
				'controller' => $this,
				'function' => 'action_privacypol',
				'permission' => 'admin_forum',
			),
			'reservednames' => array(
				'controller' => $this,
				'function' => 'action_reservednames',
				'permission' => 'admin_forum',
			),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_registerSettings_display',
				'permission' => 'admin_forum',
			),
		);

		// Action controller
		$action = new Action('manage_registrations');

		// Next create the tabs for the template.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['registration_center'],
			'help' => 'registrations',
			'description' => $txt['admin_settings_desc'],
			'tabs' => array(
				'register' => array(
					'description' => $txt['admin_register_desc'],
				),
				'agreement' => array(
					'description' => $txt['registration_agreement_desc'],
				),
				'privacypol' => array(
					'description' => $txt['privacy_policy_desc'],
				),
				'reservednames' => array(
					'description' => $txt['admin_reserved_desc'],
				),
				'settings' => array(
					'description' => $txt['admin_settings_desc'],
				)
			)
		);

		// Work out which to call... call integrate_sa_manage_registrations
		$subAction = $action->initialize($subActions, 'register');

		// Final bits
		$context['page_title'] = $txt['maintain_title'];
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * This function allows the admin to register a new member by hand.
	 *
	 * - It also allows assigning a primary group to the member being registered.
	 * - Accessed by ?action=admin;area=regcenter;sa=register
	 * - Requires the moderate_forum permission.
	 *
	 * @uses Register template, admin_register sub-template.
	 */
	public function action_register()
	{
		global $txt, $context;

		if (!empty($this->_req->post->regSubmit))
		{
			checkSession();
			validateToken('admin-regc');

			// @todo move this to a filter/sanitation class
			foreach ($this->_req->post as $key => $value)
			{
				if (!is_array($value))
				{
					$this->_req->post[$key] = Util::htmltrim__recursive(str_replace(array("\n", "\r"), '', $value));
				}
			}

			// Generate a password
			if (empty($this->_req->post->password) || !is_string($this->_req->post->password) || trim($this->_req->post->password) === '')
			{
				$tokenizer = new TokenHash();
				$password = $tokenizer->generate_hash(14);
			}
			else
			{
				$password = $this->_req->post->password;
			}

			$regOptions = array(
				'interface' => 'admin',
				'username' => $this->_req->post->user,
				'email' => $this->_req->post->email,
				'password' => $password,
				'password_check' => $password,
				'check_reserved_name' => true,
				'check_password_strength' => true,
				'check_email_ban' => false,
				'send_welcome_email' => isset($this->_req->post->emailPassword),
				'require' => isset($this->_req->post->emailActivate) ? 'activation' : 'nothing',
				'memberGroup' => empty($this->_req->post->group) || !allowedTo('manage_membergroups') ? 0 : (int) $this->_req->post->group,
				'ip' => '127.0.0.1',
				'ip2' => '127.0.0.1',
				'auth_method' => 'password',
			);

			require_once(SUBSDIR . '/Members.subs.php');
			$reg_errors = ErrorContext::context('register', 0);
			$memberID = registerMember($regOptions, 'register');

			// If there are "important" errors and you are not an admin: log the first error
			// Otherwise grab all of them and don't log anything
			$error_severity = $reg_errors->hasErrors(1) && !$this->user->is_admin ? 1 : null;
			foreach ($reg_errors->prepareErrors($error_severity) as $error)
			{
				throw new Exception($error, $error_severity === null ? false : 'general');
			}

			if (!empty($memberID))
			{
				$context['new_member'] = array(
					'id' => $memberID,
					'name' => $this->_req->post->user,
					'href' => getUrl('profile', ['action' => 'profile', 'u' => $memberID, 'name' => $this->_req->post->user]),
					'link' => '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $memberID, 'name' => $this->_req->post->user]) . '">' . $this->_req->post->user . '</a>',
				);
				$context['registration_done'] = sprintf($txt['admin_register_done'], $context['new_member']['link']);
			}
		}

		// Load the assignable member groups.
		if (allowedTo('manage_membergroups'))
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');
			$includes = allowedTo('admin_forum') ? array('admin', 'globalmod', 'member') : array('globalmod', 'member', 'custom');

			$groups = array();
			$membergroups = getBasicMembergroupData($includes, array('hidden', 'protected'));
			foreach ($membergroups as $membergroup)
			{
				$groups[$membergroup['id']] = $membergroup['name'];
			}

			$context['member_groups'] = $groups;
		}
		else
		{
			$context['member_groups'] = array();
		}

		// Basic stuff.
		loadJavascriptFile('mailcheck.min.js');
		theme()->addInlineJavascript('disableAutoComplete();
		$("input[type=email]").on("blur", function(event) {
			$(this).mailcheck({
				suggested: function(element, suggestion) {
				  	$("#suggestion").html("' . $txt['register_did_you'] . ' <b><i>" + suggestion.full + "</b></i>");
				  	element.addClass("check_input");
				},
				empty: function(element) {
				  	$("#suggestion").html("");
				  	element.removeClass("check_input");
				}
			});
		});', true);

		$context['sub_template'] = 'admin_register';
		$context['page_title'] = $txt['registration_center'];

		createToken('admin-regc');
	}

	/**
	 * Allows the administrator to edit the registration agreement, and choose whether
	 * it should be shown or not.
	 *
	 * - It writes and saves the agreement to the appropriate file.
	 * - Accessed by ?action=admin;area=regcenter;sa=agreement.
	 * - Requires the admin_forum permission.
	 *
	 * @uses Admin template and the edit_agreement sub template.
	 */
	public function action_agreement()
	{
		// I hereby agree not to be a lazy bum.
		global $txt, $context, $modSettings;

		// By default, we look at Languages/Agreement/English.txt.
		$context['current_agreement'] = 'English';

		// Is there more than one to edit?
		$context['editable_agreements'] = array('' => $txt['admin_agreement_default']);

		// Get our languages.
		$languages = getLanguages();

		// Try to figure out if we have more agreements.
		$fileFunc = FileFunctions::instance();
		foreach ($languages as $lang)
		{
			if ($fileFunc->fileExists(SOURCEDIR . '/ElkArte/Languages/Agreement/' . $lang['name'] . '.txt'))
			{
				$context['editable_agreements'][$lang['filename']] = $lang['name'];

				// Are we editing this?
				if ($this->_req->getPost('agree_lang') === $lang['filename'])
				{
					$context['current_agreement'] = $lang['name'];
					break;
				}
			}
		}

		$context['warning'] = '';
		$agreement = new Agreement($context['current_agreement']);

		// Saving the edit
		if (isset($this->_req->post->save, $this->_req->post->agreement))
		{
			checkSession();
			validateToken('admin-rega');

			// Off it goes to the agreement file.
			$success = $agreement->save($this->_req->post->agreement, !empty($this->_req->post->checkboxAcceptAgreement));
			if (!empty($this->_req->post->checkboxAcceptAgreement))
			{
				if ($success === false)
				{
					$context['warning'] .= $txt['agreement_backup_not_writable'] . '<br />';
				}
				else
				{
					updateSettings(array('agreementRevision' => $success));
				}
			}

			updateSettings(array('requireAgreement' => !empty($this->_req->post->requireAgreement), 'checkboxAgreement' => !empty($this->_req->post->checkboxAgreement)));
		}

		$context['agreement'] = Util::htmlspecialchars($agreement->getPlainText(false));
		$context['warning'] .= $agreement->isWritable() ? '' : $txt['agreement_not_writable'];
		$context['require_agreement'] = !empty($modSettings['requireAgreement']);
		$context['checkbox_agreement'] = !empty($modSettings['checkboxAgreement']);
		$context['sub_template'] = 'edit_agreement';
		$context['subaction'] = 'agreement';
		$context['agreement_show_options'] = true;
		$context['page_title'] = $txt['registration_agreement'];

		createToken('admin-rega');
	}

	/**
	 * Allows the administrator to edit the privacy policy, and choose whether
	 * it should be shown or not.
	 *
	 * - It writes and saves the privacy policy to the appropriate privacy policy file.
	 * - Accessed by ?action=admin;area=regcenter;sa=privacypol
	 * - Requires the admin_forum permission.
	 *
	 * @uses Admin template and the edit_agreement sub template.
	 */
	public function action_privacypol()
	{
		// I hereby agree not to be a lazy bum.
		global $txt, $context, $modSettings;

		// By default we look at Languages/PrivacyPolicy/English.txt.
		$context['current_agreement'] = '';

		// Is there more than one to edit?
		$context['editable_agreements'] = array('' => $txt['admin_agreement_default']);

		// Get our languages.
		$languages = getLanguages();

		// Try to figure out if we have more agreements.
		$fileFunc = FileFunctions::instance();
		foreach ($languages as $lang)
		{
			if ($fileFunc->fileExists(SOURCEDIR . '/ElkArte/Languages/PrivacyPolicy/' . $lang['filename'] . '.txt'))
			{
				$context['editable_agreements'][$lang['filename']] = $lang['name'];

				// Are we editing this?
				if ($this->_req->getPost('agree_lang') === $lang['filename'])
				{
					$context['current_agreement'] = $lang['filename'];
					break;
				}
			}
		}

		$context['warning'] = '';
		$privacypol = new PrivacyPolicy($context['current_agreement']);

		if (isset($this->_req->post->save, $this->_req->post->agreement))
		{
			checkSession();
			validateToken('admin-rega');

			// Off it goes to the agreement file.
			$success = $privacypol->save($this->_req->post->agreement, !empty($this->_req->post->checkboxAcceptAgreement));
			if (!empty($this->_req->post->checkboxAcceptAgreement))
			{
				if ($success === false)
				{
					$context['warning'] .= $txt['privacypol_backup_not_writable'] . '<br />';
				}
				else
				{
					updateSettings(array('privacypolicyRevision' => $success));
				}
			}

			updateSettings(array('requirePrivacypolicy' => !empty($this->_req->post->requireAgreement)));
		}

		$context['agreement'] = Util::htmlspecialchars($privacypol->getPlainText(false));
		$context['warning'] .= $privacypol->isWritable() ? '' : $txt['privacypol_not_writable'];
		$context['require_agreement'] = !empty($modSettings['requirePrivacypolicy']);
		$context['sub_template'] = 'edit_agreement';
		$context['subaction'] = 'privacypol';
		$context['page_title'] = $txt['privacy_policy'];

		// These overrides are here to be able to reuse the template in a simple way without having to change much.
		$txt['admin_agreement'] = $txt['admin_privacypol'];
		$txt['admin_checkbox_accept_agreement'] = $txt['admin_checkbox_accept_privacypol'];
		$txt['confirm_request_accept_agreement'] = $txt['confirm_request_accept_privacy_policy'];

		createToken('admin-rega');
	}

	/**
	 * Set the names under which users are not allowed to register.
	 *
	 * - Accessed by ?action=admin;area=regcenter;sa=reservednames.
	 * - Requires the admin_forum permission.
	 *
	 * @uses Register template, reserved_words sub-template.
	 */
	public function action_reservednames()
	{
		global $txt, $context, $modSettings;

		// Submitting new reserved words.
		if (!empty($this->_req->post->save_reserved_names))
		{
			checkSession();
			validateToken('admin-regr');

			// Set all the options....
			updateSettings(array(
				'reserveWord' => (isset($this->_req->post->matchword) ? '1' : '0'),
				'reserveCase' => (isset($this->_req->post->matchcase) ? '1' : '0'),
				'reserveUser' => (isset($this->_req->post->matchuser) ? '1' : '0'),
				'reserveName' => (isset($this->_req->post->matchname) ? '1' : '0'),
				'reserveNames' => str_replace("\r", '', $this->_req->post->reserved)
			));
		}

		// Get the reserved word options and words.
		$modSettings['reserveNames'] = str_replace('\n', "\n", $modSettings['reserveNames']);
		$context['reserved_words'] = explode("\n", $modSettings['reserveNames']);
		$context['reserved_word_options'] = array();
		$context['reserved_word_options']['match_word'] = $modSettings['reserveWord'] == '1';
		$context['reserved_word_options']['match_case'] = $modSettings['reserveCase'] == '1';
		$context['reserved_word_options']['match_user'] = $modSettings['reserveUser'] == '1';
		$context['reserved_word_options']['match_name'] = $modSettings['reserveName'] == '1';

		// Ready the template......
		$context['sub_template'] = 'edit_reserved_words';
		$context['page_title'] = $txt['admin_reserved_set'];

		createToken('admin-regr');
	}

	/**
	 * This function handles registration settings, and provides a few pretty stats too while it's at it.
	 *
	 * - General registration settings and Coppa compliance settings.
	 * - Accessed by ?action=admin;area=regcenter;sa=settings.
	 * - Requires the admin_forum permission.
	 *
	 * @event integrate_save_registration_settings
	 */
	public function action_registerSettings_display()
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Setup the template
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['registration_center'];

		if (isset($this->_req->query->save))
		{
			checkSession();

			// Are there some contacts missing?
			if (!empty($this->_req->post->coppaAge) && !empty($this->_req->post->coppaType) && empty($this->_req->post->coppaPost) && empty($this->_req->post->coppaFax))
			{
				throw new Exception('admin_setting_coppa_require_contact');
			}

			// Post needs to take into account line breaks.
			$this->_req->post->coppaPost = str_replace("\n", '<br />', empty($this->_req->post->coppaPost) ? '' : $this->_req->post->coppaPost);

			call_integration_hook('integrate_save_registration_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			redirectexit('action=admin;area=regcenter;sa=settings');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'regcenter', 'save', 'sa' => 'settings']);
		$context['settings_title'] = $txt['settings'];

		// Define some javascript for COPPA.
		theme()->addInlineJavascript('
			function checkCoppa()
			{
				var coppaDisabled = document.getElementById(\'coppaAge\').value == 0;
				document.getElementById(\'coppaType\').disabled = coppaDisabled;

				var disableContacts = coppaDisabled || document.getElementById(\'coppaType\').options[document.getElementById(\'coppaType\').selectedIndex].value != 1;
				document.getElementById(\'coppaPost\').disabled = disableContacts;
				document.getElementById(\'coppaFax\').disabled = disableContacts;
				document.getElementById(\'coppaPhone\').disabled = disableContacts;
			}
			checkCoppa();', true);

		// Turn the postal address into something suitable for a textbox.
		$modSettings['coppaPost'] = !empty($modSettings['coppaPost']) ? preg_replace('~<br ?/?' . '>~', "\n", $modSettings['coppaPost']) : '';

		$settingsForm->prepare();
	}

	/**
	 * Return configuration settings for new members registration.
	 *
	 * @event integrate_modify_registration_settings
	 */
	private function _settings()
	{
		global $txt;

		$config_vars = array(
			array('select', 'registration_method', array($txt['setting_registration_standard'], $txt['setting_registration_activate'], $txt['setting_registration_approval'], $txt['setting_registration_disabled'])),
			array('check', 'notify_new_registration'),
			array('check', 'force_accept_agreement'),
			array('check', 'force_accept_privacy_policy'),
			array('check', 'send_welcomeEmail'),
			array('check', 'show_DisplayNameOnRegistration'),
			'',
			array('int', 'coppaAge', 'subtext' => $txt['setting_coppaAge_desc'], 'onchange' => 'checkCoppa();', 'onkeyup' => 'checkCoppa();'),
			array('select', 'coppaType', array($txt['setting_coppaType_reject'], $txt['setting_coppaType_approval']), 'onchange' => 'checkCoppa();'),
			array('large_text', 'coppaPost', 'subtext' => $txt['setting_coppaPost_desc']),
			array('text', 'coppaFax'),
			array('text', 'coppaPhone'),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_registration_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the registration settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
