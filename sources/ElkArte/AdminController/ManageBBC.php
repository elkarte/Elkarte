<?php

/**
 * Handles administration options for BBC tags.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

/**
 * ManageBBC controller handles administration options for BBC tags.
 *
 * @package BBC
 */
class ManageBBC extends \ElkArte\AbstractController
{
	/**
	 * The BBC admin area
	 *
	 * What it does:
	 *
	 * - This method is the entry point for index.php?action=admin;area=postsettings;sa=bbc
	 * and it calls a function based on the sub-action, here only display.
	 * - requires admin_forum permissions
	 *
	 * @event integrate_sa_manage_bbc Used to add more sub actions
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		$subActions = array(
			'display' => array(
				'controller' => $this,
				'function' => 'action_bbcSettings_display',
				'permission' => 'admin_forum')
		);

		// Set up
		$action = new \ElkArte\Action('manage_bbc');

		// Only one option I'm afraid, but integrate_sa_manage_bbc can add more
		$subAction = $action->initialize($subActions, 'display');
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['manageposts_bbc_settings_title'];

		// Make the call
		$action->dispatch($subAction);
	}

	/**
	 * Administration page in Posts and Topics > BBC.
	 *
	 * - This method handles displaying and changing which BBC tags are enabled on the forum.
	 *
	 * @event integrate_save_bbc_settings called during the save action
	 * @uses Admin template, edit_bbc_settings sub-template.
	 */
	public function action_bbcSettings_display()
	{
		global $context, $txt, $modSettings;

		// Initialize the form
		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Make sure a nifty javascript will enable/disable checkboxes, according to BBC globally set or not.
		theme()->addInlineJavascript('
			toggleBBCDisabled(\'disabledBBC\', ' . (empty($modSettings['enableBBC']) ? 'true' : 'false') . ');', true);

		// Make sure we check the right tags!
		$modSettings['bbc_disabled_disabledBBC'] = empty($modSettings['disabledBBC']) ? array() : explode(',', $modSettings['disabledBBC']);

		// Save page
		if (isset($this->_req->query->save))
		{
			checkSession();

			// Security: make a pass through all tags and fix them as necessary
			$codes = \BBC\ParserWrapper::instance()->getCodes();
			$bbcTags = $codes->getTags();

			if (!isset($this->_req->post->disabledBBC_enabledTags))
				$this->_req->post->disabledBBC_enabledTags = array();
			elseif (!is_array($this->_req->post->disabledBBC_enabledTags))
				$this->_req->post->disabledBBC_enabledTags = array($this->_req->post->disabledBBC_enabledTags);

			// Work out what is actually disabled!
			$this->_req->post->disabledBBC = implode(',', array_diff($bbcTags, $this->_req->post->disabledBBC_enabledTags));

			// Notify addons and integrations
			call_integration_hook('integrate_save_bbc_settings', array($bbcTags));

			// Save the result
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// And we're out of here!
			redirectexit('action=admin;area=postsettings;sa=bbc');
		}

		// Make sure the template stuff is ready now...
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['manageposts_bbc_settings_title'];
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'postsettings', 'sa' => 'bbc', 'save']);
		$context['settings_title'] = $txt['manageposts_bbc_settings_title'];

		$settingsForm->prepare();
	}

	/**
	 * Return the BBC settings of the forum.
	 *
	 * @event integrate_modify_bbc_settings used to add more options to config vars
	 */
	private function _settings()
	{
		$config_vars = array(
				array('check', 'enableBBC'),
				array('check', 'enableBBC', 0, 'onchange' => 'toggleBBCDisabled(\'disabledBBC\', !this.checked);'),
				array('check', 'enablePostHTML'),
				array('check', 'autoLinkUrls'),
			'',
				array('bbc', 'disabledBBC'),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_bbc_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
