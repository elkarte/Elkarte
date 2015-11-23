<?php

/**
 * Displays and allows the changing of avatar settings
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This is the avatars administration controller class.
 *
 * - It is doing the job of maintenance and allow display and change of avatar settings.
 *
 * @package Avatars
 */
class ManageAvatars_Controller extends Action_Controller
{
	/**
	 * Avatars settings form
	 *
	 * @var Settings_Form
	 */
	protected $_avatarSettings;

	/**
	 * The Avatars admin area
	 *
	 * What it does:
	 * - This method is the entry point for index.php?action=admin;area=manageattachments;sa=avatars
	 * - It calls a function based on the sub-action.
	 * - Is called from ManageAttachments.controller.php
	 * - requires manage_attachments permissions
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		// You have to be able to moderate the forum to do this.
		isAllowedTo('manage_attachments');

		$subActions = array(
			'display' => array($this, 'action_avatarSettings_display')
		);

		// Set up for some action
		$action = new Action('manage_avatars');

		// Get the sub action or set a default, call integrate_sa_avatar_settings
		$subAction = $action->initialize($subActions, 'display');

		// Final page details
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['avatar_settings'];

		// Now go!
		$action->dispatch($subAction);
	}

	/**
	 * This action handler method displays and allows to change avatar settings.
	 *
	 * - Called by index.php?action=admin;area=manageattachments;sa=avatars.
	 *
	 * @uses 'avatars' sub-template.
	 */
	public function action_avatarSettings_display()
	{
		global $txt, $context, $scripturl;

		// Initialize the form
		$this->_initAvatarSettingsForm();

		$config_vars = $this->_avatarSettings->settings();

		// Saving avatar settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_avatar_settings');

			// Disable if invalid values would result
			if (isset($this->_req->post->custom_avatar_enabled) && $this->_req->post->custom_avatar_enabled == 1 && (empty($this->_req->post->custom_avatar_dir) || empty($this->_req->post->custom_avatar_url)))
				$this->_req->post->custom_avatar_enabled = 0;

			Settings_Form::save_db($config_vars, $this->_req->post);
			redirectexit('action=admin;area=manageattachments;sa=avatars');
		}

		// Attempt to figure out if the admin is trying to break things.
		$context['settings_save_onclick'] = 'return document.getElementById(\'custom_avatar_enabled\').value == 1 && (document.getElementById(\'custom_avatar_dir\').value == \'\' || document.getElementById(\'custom_avatar_url\').value == \'\') ? confirm(\'' . $txt['custom_avatar_check_empty'] . '\') : true;';

		// We need this for the in-line permissions
		createToken('admin-mp');

		// Prepare the context.
		$context['post_url'] = $scripturl . '?action=admin;area=manageattachments;save;sa=avatars';
		Settings_Form::prepare_db($config_vars);

		$context['sub_template'] = 'show_settings';
	}

	/**
	 * This method retrieves and returns avatar settings.
	 *
	 * - It also returns the avatar-related permission profile_set_avatar.
	 * - Initializes the avatarSettings form.
	 */
	private function _initAvatarSettingsForm()
	{
		// Instantiate the form
		$this->_avatarSettings = new Settings_Form();

		// Initialize settings
		$config_vars = $this->_settings();

		return $this->_avatarSettings->settings($config_vars);
	}

	/**
	 * This method retrieves and returns the settings.
	 */
	private function _settings()
	{
		global $txt, $context, $modSettings;

		// Check for GD and ImageMagick. It will set up a warning for the admin otherwise.
		$testImg = get_extension_funcs('gd') || class_exists('Imagick');

		$context['valid_avatar_dir'] = is_dir($modSettings['avatar_directory']);
		$context['valid_custom_avatar_dir'] = empty($modSettings['custom_avatar_enabled']) || (!empty($modSettings['custom_avatar_dir']) && is_dir($modSettings['custom_avatar_dir']) && is_writable($modSettings['custom_avatar_dir']));

		// Load the configuration vars for the form
		$config_vars = array(
			array('title', 'avatar_settings'),
				array('check', 'avatar_default'),
				array('text', 'avatar_max_width', 'subtext' => $txt['zero_for_no_limit'], 6),
				array('text', 'avatar_max_height', 'subtext' => $txt['zero_for_no_limit'], 6),
				array('select', 'avatar_action_too_large',
					array(
						'option_refuse' => $txt['option_refuse'],
						'option_resize' => $txt['option_resize'],
						'option_download_and_resize' => $txt['option_download_and_resize'],
					),
				),
				array('permissions', 'profile_set_avatar', 0, $txt['profile_set_avatar']),
			// Server stored avatars!
			array('title', 'avatar_server_stored'),
				array('warning', empty($testImg) ? 'avatar_img_enc_warning' : ''),
				array('check', 'avatar_stored_enabled'),
				array('text', 'avatar_directory', 40, 'invalid' => !$context['valid_avatar_dir']),
				array('text', 'avatar_url', 40),
			// External avatars?
			array('title', 'avatar_external'),
				array('check', 'avatar_external_enabled'),
				array('check', 'avatar_download_external', 0, 'onchange' => 'fUpdateStatus();'),
			array('title','gravatar'),
				array('check', 'avatar_gravatar_enabled'),
				array('select', 'gravatar_rating',
					array(
						'g' => 'g',
						'pg' => 'pg',
						'r' => 'r',
						'x' => 'x',
					),
				),
			// Uploadable avatars?
			array('title', 'avatar_upload'),
				array('check', 'avatar_upload_enabled'),
			array('title', 'avatar_resize_options'),
				array('check', 'avatar_reencode'),
				array('warning', 'avatar_paranoid_warning'),
				array('check', 'avatar_paranoid'),
			'',
				array('check', 'avatar_download_png'),
				array('select', 'custom_avatar_enabled', array($txt['option_attachment_dir'], $txt['option_specified_dir']), 'onchange' => 'fUpdateStatus();'),
				array('text', 'custom_avatar_dir', 40, 'subtext' => $txt['custom_avatar_dir_desc'], 'invalid' => !$context['valid_custom_avatar_dir']),
				array('text', 'custom_avatar_url', 40),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_avatar_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return avatar settings for search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}