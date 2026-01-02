<?php

/**
 * Manage signature settings.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\AdminController;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * Signature administration controller.
 * This class allows modifying signature settings for the forum.
 */
class ManageSignature extends AbstractController
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
	 * Default action for this controller.
	 */
	public function action_index()
	{
		$subActions = [
			'sig' => [$this, 'action_signatureSettings_display', 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_sig');

		// There is only one option
		$subAction = $action->initialize($subActions, 'sig');
		$action->dispatch($subAction);
	}

	/**
	 * Display configuration settings for signatures on forum.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=sig;
	 *
	 * @event integrate_save_signature_settings
	 */
	public function action_signatureSettings_display(): void
	{
		global $context, $txt, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_signatureSettings());

		// Set up the template.
		$context['page_title'] = $txt['signature_settings'];
		$context['sub_template'] = 'show_settings';

		// Disable the max smileys option if we don't allow smileys at all!
		theme()->addInlineJavascript('
			document.getElementById(\'signature_max_smileys\').disabled = !document.getElementById(\'signature_allow_smileys\').checked;', true);

		// Load all the signature settings.
		[$sig_limits, $sig_bbc] = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = empty($sig_bbc) ? [] : explode(',', $sig_bbc);

		// It does not work in signatures, and seriously, why would you do this?
		$disabledTags[] = 'footnote';

		// Applying to ALL signatures?!!
		if ($this->_req->hasQuery('apply'))
		{
			// Security!
			checkSession('get');

			// This is horrid - but I suppose some people will want the option to do it.
			$applied_sigs = $this->_req->getQuery('step', 'intval', 0);
			updateAllSignatures($applied_sigs);

			$settings_applied = true;
		}

		$context['signature_settings'] = [
			'enable' => $sig_limits[0] ?? 0,
			'max_length' => $sig_limits[1] ?? 0,
			'max_lines' => $sig_limits[2] ?? 0,
			'max_images' => $sig_limits[3] ?? 0,
			'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
			'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
			'max_image_width' => $sig_limits[5] ?? 0,
			'max_image_height' => $sig_limits[6] ?? 0,
			'max_font_size' => $sig_limits[7] ?? 0,
			'repetition_guests' => $sig_limits[8] ?? 0,
			'repetition_members' => $sig_limits[9] ?? 0,
		];

		// Temporarily make each setting a modSetting!
		foreach ($context['signature_settings'] as $key => $value)
		{
			$modSettings['signature_' . $key] = $value;
		}

		// Make sure we check the right tags!
		$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			// Clean up the tag stuff!
			$codes = ParserWrapper::instance()->getCodes();
			$bbcTags = $codes->getTags();

			$signature_bbc_enabledTags = $this->_req->getPost('signature_bbc_enabledTags', null, []);
			if (!is_array($signature_bbc_enabledTags))
			{
				$signature_bbc_enabledTags = [$signature_bbc_enabledTags];
			}

			// Do not mutate the request; keep a local copy for settings persistence
			$signature_bbc_enabledTags_local = $signature_bbc_enabledTags;

			$sig_limits = [];
			foreach (array_keys($context['signature_settings']) as $key)
			{
				if ($key === 'allow_smileys')
				{
					continue;
				}
				if ($key === 'max_smileys' && empty($this->_req->post->signature_allow_smileys))
				{
					$sig_limits[] = -1;
				}
				else
				{
					$current_key = $this->_req->getPost('signature_' . $key, 'intval');
					$sig_limits[] = empty($current_key) ? 0 : max(1, $current_key);
				}
			}

			call_integration_hook('integrate_save_signature_settings', [&$sig_limits, &$bbcTags]);

			// Build the combined signature settings string using locals (do not write back to request)
			$signature_settings_local = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $signature_bbc_enabledTags_local));

			// Even though we have practically no settings, let's keep the convention going!
			$save_vars = [];
			$save_vars[] = ['text', 'signature_settings'];

			$settingsForm->setConfigVars($save_vars);
			// Start from posted values but override with our local computed values
			$config_values = (array) $this->_req->post;
			$config_values['signature_bbc_enabledTags'] = $signature_bbc_enabledTags_local;
			$config_values['signature_settings'] = $signature_settings_local;
			$settingsForm->setConfigValues($config_values);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=sig');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'sig', 'save']);
		$context['settings_title'] = $txt['signature_settings'];
		$context['settings_message'] = empty($settings_applied) ? sprintf($txt['signature_settings_warning'], getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'sig', 'apply', '{session_data}'])) : $txt['signature_settings_applied'];

		$settingsForm->prepare();
	}

	/**
	 * Return signature settings.
	 *
	 * - Used in admin center search and settings form
	 *
	 * @event integrate_modify_signature_settings Adds options to Signature Settings
	 */
	private function _signatureSettings()
	{
		global $txt;

		$config_vars = [
			// Are signatures even enabled?
			['check', 'signature_enable'],
			'',
			// Tweaking settings!
			['int', 'signature_max_length', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_lines', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_font_size', 'subtext' => $txt['zero_for_no_limit']],
			['check', 'signature_allow_smileys', 'onclick' => "document.getElementById('signature_max_smileys').disabled = !this.checked;"],
			['int', 'signature_max_smileys', 'subtext' => $txt['zero_for_no_limit']],
			['select', 'signature_repetition_guests',
				[
					$txt['signature_always'],
					$txt['signature_onlyfirst'],
					$txt['signature_never'],
				],
			],
			['select', 'signature_repetition_members',
				[
					$txt['signature_always'],
					$txt['signature_onlyfirst'],
					$txt['signature_never'],
				],
			],
			'',
			// Image settings.
			['int', 'signature_max_images', 'subtext' => $txt['signature_max_images_note']],
			['int', 'signature_max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_image_height', 'subtext' => $txt['zero_for_no_limit']],
			'',
			['bbc', 'signature_bbc'],
		];

		call_integration_hook('integrate_modify_signature_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the signature settings, used for admin search
	 */
	public function signatureSettings_search()
	{
		return $this->_signatureSettings();
	}
}
