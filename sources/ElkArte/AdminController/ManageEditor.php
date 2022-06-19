<?php

/**
 * Handles administration options for BBC tags.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\SettingsForm\SettingsForm;

/**
 * ManageEditor controller handles administration options for BBC tags.
 *
 * @package Editor
 */
class ManageEditor extends AbstractController
{
	/**
	 * The Editor admin area
	 *
	 * What it does:
	 *
	 * - This method is the entry point for index.php?action=admin;area=postsettings;sa=editor
	 * and it calls a function based on the sub-action, here only display.
	 * - requires admin_forum permissions
	 *
	 * @event integrate_sa_manage_editor Used to add more sub actions
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		$subActions = array(
			'display' => array(
				'controller' => $this,
				'function' => 'action_editorSettings_display',
				'permission' => 'admin_forum')
		);

		// Set up
		$action = new Action('manage_editor');

		// Only one option I'm afraid, but integrate_sa_manage_editor can add more
		$subAction = $action->initialize($subActions, 'display');
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['manageposts_editor_settings_title'];

		// Make the call
		$action->dispatch($subAction);
	}

	/**
	 * Administration page in Posts and Topics > Editor.
	 *
	 * - This method handles displaying and changing which BBC tags are enabled on the forum.
	 *
	 * @event integrate_save_bbc_settings called during the save action
	 * @uses Admin template, edit_editor_settings sub-template.
	 */
	public function action_editorSettings_display()
	{
		global $context, $txt, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

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
			$codes = ParserWrapper::instance()->getCodes();
			$bbcTags = $codes->getTags();

			$disabledBBC_enabledTags = $this->_req->getPost('disabledBBC_enabledTags', null, []);
			if (!is_array($disabledBBC_enabledTags))
			{
				$disabledBBC_enabledTags = array($disabledBBC_enabledTags);
			}

			// Work out what is actually disabled!
			$this->_req->post->disabledBBC = implode(',', array_diff($bbcTags, $disabledBBC_enabledTags));

			// Notify addons and integrations
			call_integration_hook('integrate_save_bbc_settings', array($bbcTags));

			// Save the result
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// And we're out of here!
			redirectexit('action=admin;area=editor');
		}

		// Make sure the template stuff is ready now...
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['manageposts_editor_settings_title'];
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'editor', 'save']);
		$context['settings_title'] = $txt['manageposts_editor_settings_title'];

		$settingsForm->prepare();
	}

	/**
	 * Return the editor settings of the forum.
	 *
	 * @event integrate_modify_editor_settings used to add more options to config vars,
	 * formerly known as integrate_modify_bbc_settings
	 */
	private function _settings()
	{
		$config_vars = array(
			array('check', 'enableBBC'),
			array('check', 'enableBBC', 0, 'onchange' => 'toggleBBCDisabled(\'disabledBBC\', !this.checked);'),
			array('bbc', 'disabledBBC'),

			array('title', 'editorSettings'),
			array('check', 'enableUndoRedo'),
			array('check', 'enableSplitTag'),

			array('title', 'mods_cat_modifications_misc'),
			array('check', 'autoLinkUrls'), // @todo not editor or bbc
			array('check', 'enablePostHTML'),
			array('check', 'enablePostMarkdown'),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_editor_settings', array(&$config_vars));
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
