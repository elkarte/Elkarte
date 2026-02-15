<?php

/**
 * Allows for the modifying of the forum layout settings.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * Layout administration controller.
 * This class allows modifying admin layout settings for the forum.
 */
class ManageLayout extends AbstractController
{
	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// We need this in a few places, so it's easier to have it loaded here
		require_once(SUBSDIR . '/ManageFeatures.subs.php');

		Txt::load('Help+ManageSettings');
	}

	/**
	 * This function passes control through to the relevant tab.
	 */
	public function action_index()
	{
		$subActions = [
			'layout' => [$this, 'action_layoutSettings_display', 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_layout');

		// By default, do the layout settings
		$subAction = $action->initialize($subActions, 'layout');

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Allows modifying the global layout settings in the forum
	 *
	 * - Accessed through ?action=admin;area=featuresettings;sa=layout;
	 *
	 * @event integrate_save_layout_settings
	 */
	public function action_layoutSettings_display(): void
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_layoutSettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			// Setting a custom frontpage, set the hook to the FrontpageInterface of the controller
			if (!empty($this->_req->post->front_page))
			{
				// Addons may have left this blank
				$modSettings['front_page'] = empty($modSettings['front_page']) ? 'MessageIndex_Controller' : $modSettings['front_page'];

				$front_page = (string) $this->_req->getPost('front_page', 'trim', '');
				if (
					class_exists($modSettings['front_page'])
					&& in_array('validateFrontPageOptions', get_class_methods($modSettings['front_page']))
					&& !$front_page::validateFrontPageOptions($this->_req->post)
				)
				{
					$this->_req->post->front_page = '';
				}
			}

			checkSession();

			call_integration_hook('integrate_save_layout_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			writeLog();

			redirectexit('action=admin;area=featuresettings;sa=layout');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'layout', 'save']);
		$context['settings_title'] = $txt['mods_cat_layout'];
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['modSettings_title'];

		$settingsForm->prepare();
	}

	/**
	 * Return layout settings.
	 *
	 * @event integrate_modify_layout_settings Adds options to Configuration->Layout
	 */
	private function _layoutSettings()
	{
		global $txt;

		$config_vars = array_merge(getFrontPageControllers(), [
			'',
			// Pagination stuff.
			['check', 'compactTopicPagesEnable'],
			['int', 'compactTopicPagesContiguous', 'subtext' => str_replace(' ', '&nbsp;', '"3" ' . $txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br />' . str_replace(' ', '&nbsp;', '"5" ' . $txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>')],
			['int', 'defaultMaxMembers'],
			['check', 'displayMemberNames'],
			'',
			// Stuff that just is everywhere - today, search, online, etc.
			['select', 'todayMod', [$txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'], $txt['relative_time']]],
			['check', 'onlineEnable'],
			'',
			// Automagic image resizing.
			['int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']],
			'',
			// This is like debugging sorta.
			['check', 'timeLoadPageEnable'],
		]);

		call_integration_hook('integrate_modify_layout_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the layout settings, used for admin search
	 */
	public function layoutSettings_search()
	{
		return $this->_layoutSettings();
	}
}
