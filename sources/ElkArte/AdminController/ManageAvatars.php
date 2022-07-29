<?php

/**
 * Displays and allows the changing of avatar settings
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\FileFunctions;
use ElkArte\Graphics\Manipulators\Gd2;
use ElkArte\Graphics\Manipulators\Imagick;
use ElkArte\SettingsForm\SettingsForm;

/**
 * This is the avatars administration controller class.
 *
 * - It is doing the job of maintenance and allow display and change of avatar settings.
 *
 * @package Avatars
 */
class ManageAvatars extends AbstractController
{
	/**
	 * The Avatars admin area
	 *
	 * What it does:
	 *
	 * - This method is the entry point for index.php?action=admin;area=manageattachments;sa=avatars
	 * - It calls a function based on the sub-action.
	 * - Is called from ManageAttachments.controller.php
	 * - requires manage_attachments permissions
	 *
	 * @event integrate_sa_manage_avatars
	 * @see \ElkArte\AbstractController::action_index()
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
	 * @event integrate_save_avatar_settings
	 * @uses 'avatars' sub-template.
	 */
	public function action_avatarSettings_display()
	{
		global $txt, $context, $boardurl;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize settings
		$settingsForm->setConfigVars($this->_settings());

		// Saving avatar settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_avatar_settings');

			// Ensure we do not have empty values for these
			$this->_req->post = (object) array_filter((array) $this->_req->post);
			$this->_req->post->custom_avatar_dir = $this->_req->getPost('custom_avatar_dir', 'trim', BOARDDIR . '/avatars_user');
			$this->_req->post->custom_avatar_url = $this->_req->getPost('custom_avatar_url', 'trim', $boardurl . '/avatars_user');
			$this->_req->post->avatar_directory = $this->_req->getPost('avatar_directory', 'trim', BOARDDIR . '/avatars');
			$this->_req->post->avatar_url = $this->_req->getPost('avatar_url', 'trim', $boardurl . '/avatars');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=manageattachments;sa=avatars');
		}

		// Attempt to figure out if the admin is trying to break things.
		$context['settings_save_onclick'] = 'return (document.getElementById(\'custom_avatar_dir\').value == \'\' || document.getElementById(\'custom_avatar_url\').value == \'\') ? confirm(\'' . $txt['custom_avatar_check_empty'] . '\') : true;';

		// Prepare the context.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'avatars', 'save']);
		$settingsForm->prepare();

		$context['sub_template'] = 'show_settings';
	}

	/**
	 * This method retrieves and returns the settings.
	 *
	 * @event integrate_modify_avatar_settings
	 */
	private function _settings()
	{
		global $txt, $context, $modSettings;

		// Check for GD and ImageMagick. It will set up a warning for the admin otherwise.
		$testImg = Gd2::canUse() || Imagick::canUse();

		$context['valid_avatar_dir'] = FileFunctions::instance()->isDir($modSettings['avatar_directory']);
		$context['valid_custom_avatar_dir'] = !empty($modSettings['custom_avatar_dir'])
			&& FileFunctions::instance()->isDir($modSettings['custom_avatar_dir'])
			&& FileFunctions::instance()->isWritable($modSettings['custom_avatar_dir']);

		// Load the configuration vars for the form
		$config_vars = array(
			array('title', 'avatar_settings'),
			array('check', 'avatar_default'),
			array('int', 'avatar_max_width', 'subtext' => $txt['zero_for_no_limit'], 6),
			array('int', 'avatar_max_height', 'subtext' => $txt['zero_for_no_limit'], 6),
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
			array('title', 'gravatar'),
			array('check', 'avatar_gravatar_enabled'),
			array('check', 'gravatar_as_default'),
			array('select', 'gravatar_rating', ['g' => 'g', 'pg' => 'pg', 'r' => 'r', 'x' => 'x']),
			array('select', 'gravatar_default', [
				'none' => $txt['gravatar_none'],
				'identicon' => $txt['gravatar_identicon'],
				'monsterid' => $txt['gravatar_monsterid'],
				'wavatar' => $txt['gravatar_wavatar'],
				'retro' => $txt['gravatar_retro'],
				'robohash' => $txt['gravatar_robohash']]),
			// Upload-able avatars?
			array('title', 'avatar_upload'),
			array('check', 'avatar_upload_enabled'),
			array('text', 'custom_avatar_dir', 40, 'subtext' => $txt['custom_avatar_dir_desc'], 'invalid' => !$context['valid_custom_avatar_dir']),
			array('text', 'custom_avatar_url', 40),
			array('title', 'avatar_resize_options'),
			array('check', 'avatar_reencode'),
			array('check', 'avatar_download_png'),
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
