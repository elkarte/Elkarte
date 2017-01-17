<?php

/**
 * Manage features and options administration page.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Manage features and options administration page.
 *
 * This controller handles the pages which allow the admin
 * to see and change the basic feature settings of their site.
 */
class ManageFeatures_Controller extends Action_Controller
{
	/**
	 * Pre Dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// We need this in few places so it's easier to have it loaded here
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
	}

	/**
	 * This function passes control through to the relevant tab.
	 *
	 * @see Action_Controller::action_index()
	 * @uses Help, ManageSettings languages
	 * @uses sub_template show_settings
	 */
	public function action_index()
	{
		global $context, $txt, $settings, $scripturl;

		// Often Helpful
		loadLanguage('Help');
		loadLanguage('ManageSettings');
		loadLanguage('Mentions');

		// All the actions we know about
		$subActions = array(
			'basic' => array(
				'controller' => $this,
				'function' => 'action_basicSettings_display',
				'permission' => 'admin_forum'
			),
			'layout' => array(
				'controller' => $this,
				'function' => 'action_layoutSettings_display',
				'permission' => 'admin_forum'
			),
			'karma' => array(
				'controller' => $this,
				'function' => 'action_karmaSettings_display',
				'enabled' => in_array('k', $context['admin_features']),
				'permission' => 'admin_forum'
			),
			'pmsettings' => array(
				'controller' => $this,
				'function' => 'action_pmsettings',
				'permission' => 'admin_forum'
			),
			'likes' => array(
				'controller' => $this,
				'function' => 'action_likesSettings_display',
				'enabled' => in_array('l', $context['admin_features']),
				'permission' => 'admin_forum'
			),
			'mention' => array(
				'controller' => $this,
				'function' => 'action_notificationsSettings_display',
				'permission' => 'admin_forum'
			),
			'sig' => array(
				'controller' => $this,
				'function' => 'action_signatureSettings_display',
				'permission' => 'admin_forum'
			),
			'profile' => array(
				'controller' => $this,
				'function' => 'action_profile',
				'enabled' => in_array('cp', $context['admin_features']),
				'permission' => 'admin_forum'
			),
			'profileedit' => array(
				'controller' => $this,
				'function' => 'action_profileedit',
				'permission' => 'admin_forum'
			),
		);

		// Set up the action control
		$action = new Action('modify_features');

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['modSettings_title'],
			'help' => 'featuresettings',
			'description' => sprintf($txt['modSettings_desc'], $scripturl . '?action=admin;area=theme;sa=list;th=' . $settings['theme_id'] . ';' . $context['session_id'] . '=' . $context['session_var']),
			'tabs' => array(
				'basic' => array(
				),
				'layout' => array(
				),
				'pmsettings' => array(
				),
				'karma' => array(
				),
				'likes' => array(
				),
				'mention' => array(
					'description' => $txt['mentions_settings_desc'],
				),
				'sig' => array(
					'description' => $txt['signature_settings_desc'],
				),
				'profile' => array(
					'description' => $txt['custom_profile_desc'],
				),
			),
		);

		// By default do the basic settings, call integrate_sa_modify_features
		$subAction = $action->initialize($subActions, 'basic');

		// Some final pieces for the template
		$context['sub_template'] = 'show_settings';
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['modSettings_title'];

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Config array for changing the basic forum settings
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=basic;
	 */
	public function action_basicSettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_basicSettings());

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			// Prevent absurd boundaries here - make it a day tops.
			if (isset($this->_req->post->lastActive))
				$this->_req->post->lastActive = min((int) $this->_req->post->lastActive, 1440);

			call_integration_hook('integrate_save_basic_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			writeLog();
			redirectexit('action=admin;area=featuresettings;sa=basic');
		}
		if (isset($this->_req->post->cleanhives))
		{
			$clean_hives_result = theme()->cleanHives();

			Template_Layers::getInstance()->removeAll();
			loadTemplate('Json');
			addJavascriptVar(array('txt_invalid_response' => $txt['ajax_bad_response']), true);
			$context['sub_template'] = 'send_json';
			$context['json_data'] = array(
				'success' => $clean_hives_result,
				'response' => $clean_hives_result ? $txt['clean_hives_sucess'] : $txt['clean_hives_failed']
			);
			return;
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
		$context['settings_title'] = $txt['mods_cat_features'];

		// Show / hide custom jquery fields as required
		addInlineJavascript('showhideJqueryOptions();', true);

		$settingsForm->prepare();
	}

	/**
	 * Allows modifying the global layout settings in the forum
	 *
	 * - Accessed through ?action=admin;area=featuresettings;sa=layout;
	 */
	public function action_layoutSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_layoutSettings());

		// Saving?
		if (isset($this->_req->query->save))
		{
			// Setting a custom frontpage, set the hook to the FrontpageInterface of the controller
			if (!empty($this->_req->post->front_page))
			{
				$front_page = (string) $this->_req->post->front_page;
				if (
					is_callable(array($modSettings['front_page'], 'validateFrontPageOptions'))
					&& !$front_page::validateFrontPageOptions($this->_req->post)
				) {
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

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=layout';
		$context['settings_title'] = $txt['mods_cat_layout'];

		$settingsForm->prepare();
	}

	/**
	 * Display configuration settings page for karma settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=karma;
	 */
	public function action_karmaSettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_karmaSettings());

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_karma_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=karma');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=karma';
		$context['settings_title'] = $txt['karma'];

		$settingsForm->prepare();
	}

	/**
	 * Display configuration settings page for likes settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=likes;
	 */
	public function action_likesSettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_likesSettings());

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_likes_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=likes');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=likes';
		$context['settings_title'] = $txt['likes'];

		$settingsForm->prepare();
	}

	/**
	 * Initializes the mentions settings admin page.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=mention;
	 */
	public function action_notificationsSettings_display()
	{
		global $txt, $context, $scripturl, $modSettings;

		loadLanguage('Mentions');

		// Instantiate the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_notificationsSettings());

		// Some context stuff
		$context['page_title'] = $txt['mentions_settings'];
		$context['sub_template'] = 'show_settings';

		// Saving the settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_modify_mention_settings');

			if (empty($this->_req->post->notifications))
			{
				$notification_methods = serialize(array());
			}
			else
			{
				$notification_methods = serialize($this->_req->post->notifications);
			}

			require_once(SUBSDIR . '/Mentions.subs.php');
			$enabled_mentions = array();
			$current_settings = unserialize($modSettings['notification_methods']);

			// Fist hide what was visible
			$modules_toggle = array('enable' => array(), 'disable' => array());
			foreach ($current_settings as $type => $val)
			{
				if (!isset($this->_req->post->notifications[$type]))
				{
					toggleMentionsVisibility($type, false);
					$modules_toggle['disable'][] = $type;
				}
			}

			// Then make visible what was hidden, but only if there is anything
			if (!empty($this->_req->post->notifications))
			{
				foreach ($this->_req->post->notifications as $type => $val)
				{
					if (!isset($current_settings[$type]))
					{
						toggleMentionsVisibility($type, true);
						$modules_toggle['enable'][] = $type;
					}
				}

				$enabled_mentions = array_keys($this->_req->post->notifications);
			}

			// Let's just keep it active, there are too many reasons it should be.
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');
			toggleTaskStatusByName('user_access_mentions', true);

			// Disable or enable modules as needed
			foreach ($modules_toggle as $action => $toggles)
			{
				if (!empty($toggles))
				{
					// The modules associated with the notification (mentionmem, likes, etc) area
					$modules = getMentionsModules($toggles);

					// The action will either be enable to disable
					$function = $action . 'Modules';

					// Something like enableModule('mentions', array('post', 'display');
					foreach ($modules as $key => $val)
						$function($key, $val);
				}
			}

			updateSettings(array('enabled_mentions' => implode(',', array_unique($enabled_mentions)), 'notification_methods' => $notification_methods));
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=mention');
		}

		// Prepare the settings for display
		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=mention';
		$settingsForm->prepare();
	}

	/**
	 * Display configuration settings for signatures on forum.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=sig;
	 */
	public function action_signatureSettings_display()
	{
		global $context, $txt, $modSettings, $sig_start, $scripturl;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_signatureSettings());

		// Setup the template.
		$context['page_title'] = $txt['signature_settings'];
		$context['sub_template'] = 'show_settings';

		// Disable the max smileys option if we don't allow smileys at all!
		addInlineJavascript('
			document.getElementById(\'signature_max_smileys\').disabled = !document.getElementById(\'signature_allow_smileys\').checked;', true);

		// Load all the signature settings.
		list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

		// @todo temporary since it does not work, and seriously why would you do this?
		$disabledTags[] = 'footnote';

		// Applying to ALL signatures?!!
		if (isset($this->_req->query->apply))
		{
			// Security!
			checkSession('get');

			$sig_start = time();

			// This is horrid - but I suppose some people will want the option to do it.
			$applied_sigs = $this->_req->getQuery('step', 'intval', 0);
			updateAllSignatures($applied_sigs);

			$settings_applied = true;
		}

		$context['signature_settings'] = array(
			'enable' => isset($sig_limits[0]) ? $sig_limits[0] : 0,
			'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
			'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
			'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
			'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
			'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
			'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
			'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
			'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
			'repetition_guests' => isset($sig_limits[8]) ? $sig_limits[8] : 0,
			'repetition_members' => isset($sig_limits[9]) ? $sig_limits[9] : 0,
		);

		// Temporarily make each setting a modSetting!
		foreach ($context['signature_settings'] as $key => $value)
			$modSettings['signature_' . $key] = $value;

		// Make sure we check the right tags!
		$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			// Clean up the tag stuff!
			$codes = \BBC\ParserWrapper::getInstance()->getCodes();
			$bbcTags = $codes->getTags();

			if (!isset($this->_req->post->signature_bbc_enabledTags))
				$this->_req->post->signature_bbc_enabledTags = array();
			elseif (!is_array($this->_req->post->signature_bbc_enabledTags))
				$this->_req->post->signature_bbc_enabledTags = array($this->_req->post->signature_bbc_enabledTags);

			$sig_limits = array();
			foreach ($context['signature_settings'] as $key => $value)
			{
				if ($key == 'allow_smileys')
					continue;
				elseif ($key == 'max_smileys' && empty($this->_req->post->signature_allow_smileys))
					$sig_limits[] = -1;
				else
				{
					$current_key = $this->_req->getPost('signature_' . $key, 'intval');
					$sig_limits[] = !empty($current_key) ? max(1, $current_key) : 0;
				}
			}

			call_integration_hook('integrate_save_signature_settings', array(&$sig_limits, &$bbcTags));

			$this->_req->post->signature_settings = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $this->_req->post->signature_bbc_enabledTags));

			// Even though we have practically no settings let's keep the convention going!
			$save_vars = array();
			$save_vars[] = array('text', 'signature_settings');

			$settingsForm->setConfigVars($save_vars);
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=sig');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=sig';
		$context['settings_title'] = $txt['signature_settings'];
		$context['settings_message'] = !empty($settings_applied) ? $txt['signature_settings_applied'] : sprintf($txt['signature_settings_warning'], $scripturl . '?action=admin;area=featuresettings;sa=sig;apply;' . $context['session_var'] . '=' . $context['session_id']);

		$settingsForm->prepare();
	}

	/**
	 * Show all the custom profile fields available to the user.
	 *
	 * - Allows for drag/drop sorting of custom profile fields
	 * - Accessed with ?action=admin;area=featuresettings;sa=profile
	 *
	 * @uses sub template show_custom_profile
	 */
	public function action_profile()
	{
		global $txt, $scripturl, $context;

		loadTemplate('ManageFeatures');
		$context['page_title'] = $txt['custom_profile_title'];
		$context['sub_template'] = 'show_custom_profile';

		// What about standard fields they can tweak?
		$standard_fields = array('website', 'posts', 'warning_status', 'date_registered');

		// What fields can't you put on the registration page?
		$context['fields_no_registration'] = array('posts', 'warning_status', 'date_registered');

		// Are we saving any standard field changes?
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-scp');

			$changes = array();

			// Do the active ones first.
			$disable_fields = array_flip($standard_fields);
			if (!empty($this->_req->post->active))
			{
				foreach ($this->_req->post->active as $value)
				{
					if (isset($disable_fields[$value]))
					{
						unset($disable_fields[$value]);
					}
				}
			}

			// What we have left!
			$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

			// Things we want to show on registration?
			$reg_fields = array();
			if (!empty($this->_req->post->reg))
			{
				foreach ($this->_req->post->reg as $value)
				{
					if (in_array($value, $standard_fields) && !isset($disable_fields[$value]))
						$reg_fields[] = $value;
				}
			}

			// What we have left!
			$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

			if (!empty($changes))
				updateSettings($changes);
		}

		createToken('admin-scp');

		// Create a listing for all our standard fields
		$listOptions = array(
			'id' => 'standard_profile_fields',
			'title' => $txt['standard_profile_title'],
			'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
			'get_items' => array(
				'function' => 'list_getProfileFields',
				'params' => array(
					true,
				),
			),
			'columns' => array(
				'field' => array(
					'header' => array(
						'value' => $txt['standard_profile_field'],
					),
					'data' => array(
						'db' => 'label',
						'style' => 'width: 60%;',
					),
				),
				'active' => array(
					'header' => array(
						'value' => $txt['custom_edit_active'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($rowData) {
							$isChecked = $rowData['disabled'] ? '' : ' checked="checked"';
							$onClickHandler = $rowData['can_show_register'] ? sprintf('onclick="document.getElementById(\'reg_%1$s\').disabled = !this.checked;"', $rowData['id']) : '';
							return sprintf('<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" class="input_check" %2$s %3$s />', $rowData['id'], $isChecked, $onClickHandler);
						},
						'style' => 'width: 20%;',
						'class' => 'centertext',
					),
				),
				'show_on_registration' => array(
					'header' => array(
						'value' => $txt['custom_edit_registration'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($rowData) {
							$isChecked = $rowData['on_register'] && !$rowData['disabled'] ? ' checked="checked"' : '';
							$isDisabled = $rowData['can_show_register'] ? '' : ' disabled="disabled"';
							return sprintf('<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" class="input_check" %2$s %3$s />', $rowData['id'], $isChecked, $isDisabled);
						},
						'style' => 'width: 20%;',
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
				'name' => 'standardProfileFields',
				'token' => 'admin-scp',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="save" value="' . $txt['save'] . '" class="right_submit" />',
				),
			),
		);
		createList($listOptions);

		// And now we do the same for all of our custom ones
		$token = createToken('admin-sort');
		$listOptions = array(
			'id' => 'custom_profile_fields',
			'title' => $txt['custom_profile_title'],
			'base_href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
			'default_sort_col' => 'vieworder',
			'no_items_label' => $txt['custom_profile_none'],
			'items_per_page' => 25,
			'sortable' => true,
			'get_items' => array(
				'function' => 'list_getProfileFields',
				'params' => array(
					false,
				),
			),
			'get_count' => array(
				'function' => 'list_getProfileFieldSize',
			),
			'columns' => array(
				'vieworder' => array(
					'header' => array(
						'value' => '',
						'class' => 'hide',
					),
					'data' => array(
						'db' => 'vieworder',
						'class' => 'hide',
					),
					'sort' => array(
						'default' => 'vieworder',
					),
				),
				'field_name' => array(
					'header' => array(
						'value' => $txt['custom_profile_fieldname'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $scripturl;

							return sprintf('<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>', $scripturl, $rowData['id_field'], $rowData['field_name'], $rowData['field_desc']);
						},
						'style' => 'width: 65%;',
					),
					'sort' => array(
						'default' => 'field_name',
						'reverse' => 'field_name DESC',
					),
				),
				'field_type' => array(
					'header' => array(
						'value' => $txt['custom_profile_fieldtype'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);
							return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
						},
						'style' => 'width: 10%;',
					),
					'sort' => array(
						'default' => 'field_type',
						'reverse' => 'field_type DESC',
					),
				),
				'cust' => array(
					'header' => array(
						'value' => $txt['custom_profile_active'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($rowData) {
							$isChecked = $rowData['active'] ? ' checked="checked"' : '';
							return sprintf('<input type="checkbox" name="cust[]" id="cust_%1$s" value="%1$s" class="input_check"%2$s />', $rowData['id_field'], $isChecked);
						},
						'style' => 'width: 8%;',
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'active DESC',
						'reverse' => 'active',
					),
				),
				'placement' => array(
					'header' => array(
						'value' => $txt['custom_profile_placement'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;
							$placement = 'custom_profile_placement_';

							switch ((int) $rowData['placement'])
							{
								case 0:
									$placement .= 'standard';
									break;
								case 1:
									$placement .= 'withicons';
									break;
								case 2:
									$placement .= 'abovesignature';
									break;
								case 3:
									$placement .= 'aboveicons';
									break;
							}

							return $txt[$placement];
						},
						'style' => 'width: 5%;',
					),
					'sort' => array(
						'default' => 'placement DESC',
						'reverse' => 'placement',
					),
				),
				'show_on_registration' => array(
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=%1$s">' . $txt['modify'] . '</a>',
							'params' => array(
								'id_field' => false,
							),
						),
						'style' => 'width: 5%;',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=featuresettings;sa=profileedit',
				'name' => 'customProfileFields',
				'token' => 'admin-scp',
			),
			'additional_rows' => array(
				array(
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="onoff" value="' . $txt['save'] . '" class="right_submit" />
					<input type="submit" name="new" value="' . $txt['custom_profile_make_new'] . '" class="right_submit" />',
				),
				array(
					'position' => 'top_of_list',
					'value' => '<p class="infobox">' . $txt['custom_profile_sort'] . '</p>',
				),
			),
			'javascript' => '
				$().elkSortable({
					sa: "profileorder",
					error: "' . $txt['admin_order_error'] . '",
					title: "' . $txt['admin_order_title'] . '",
					placeholder: "ui-state-highlight",
					href: "?action=admin;area=featuresettings;sa=profile",
					token: {token_var: "' . $token['admin-sort_token_var'] . '", token_id: "' . $token['admin-sort_token'] . '"}
				});
			',
		);

		createList($listOptions);
	}

	/**
	 * Edit some profile fields?
	 *
	 * - Accessed with ?action=admin;area=featuresettings;sa=profileedit
	 *
	 * @uses sub template edit_profile_field
	 */
	public function action_profileedit()
	{
		global $txt, $scripturl, $context;

		loadTemplate('ManageFeatures');

		// Sort out the context!
		$context['fid'] = $this->_req->getQuery('fid', 'intval', 0);
		$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
		$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
		$context['sub_template'] = 'edit_profile_field';

		// Any errors messages to show?
		if (isset($this->_req->query->msg))
		{
			loadLanguage('Errors');

			if (isset($txt['custom_option_' . $this->_req->query->msg]))
				$context['custom_option__error'] = $txt['custom_option_' . $this->_req->query->msg];
		}

		// Load the profile language for section names.
		loadLanguage('Profile');

		// Load up the profile field, if one was supplied
		if ($context['fid'])
			$context['field'] = getProfileField($context['fid']);

		// Setup the default values as needed.
		if (empty($context['field']))
			$context['field'] = array(
				'name' => '',
				'colname' => '???',
				'desc' => '',
				'profile_area' => 'forumprofile',
				'reg' => false,
				'display' => false,
				'memberlist' => false,
				'type' => 'text',
				'max_length' => 255,
				'rows' => 4,
				'cols' => 30,
				'bbc' => false,
				'default_check' => false,
				'default_select' => '',
				'default_value' => '',
				'options' => array('', '', ''),
				'active' => true,
				'private' => false,
				'can_search' => false,
				'mask' => 'nohtml',
				'regex' => '',
				'enclose' => '',
				'placement' => 0,
			);

		// All the javascript for this page... everything else is in admin.js
		addJavascriptVar(array('startOptID' => count($context['field']['options'])));
		addInlineJavascript('updateInputBoxes();', true);

		// Are we toggling which ones are active?
		if (isset($this->_req->post->onoff))
		{
			checkSession();
			validateToken('admin-scp');

			// Enable and disable custom fields as required.
			$enabled = array(0);
			foreach ($this->_req->post->cust as $id)
				$enabled[] = (int) $id;

			updateRenamedProfileStatus($enabled);
		}
		// Are we saving?
		elseif (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-ecp');

			// Everyone needs a name - even the (bracket) unknown...
			if (trim($this->_req->post->field_name) == '')
				redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $this->_req->query->fid . ';msg=need_name');

			// Regex you say?  Do a very basic test to see if the pattern is valid
			if (!empty($this->_req->post->regex) && @preg_match($this->_req->post->regex, 'dummy') === false)
				redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $this->_req->query->fid . ';msg=regex_error');

			$this->_req->post->field_name = $this->_req->getPost('field_name', 'Util::htmlspecialchars');
			$this->_req->post->field_desc = $this->_req->getPost('field_desc', 'Util::htmlspecialchars');

			$rows = isset($this->_req->post->rows) ? (int) $this->_req->post->rows : 4;
			$cols = isset($this->_req->post->cols) ? (int) $this->_req->post->cols : 30;

			// Checkboxes...
			$show_reg = $this->_req->getPost('reg', 'intval', 0);
			$show_display = isset($this->_req->post->display) ? 1 : 0;
			$show_memberlist = isset($this->_req->post->memberlist) ? 1 : 0;
			$bbc = isset($this->_req->post->bbc) ? 1 : 0;
			$show_profile = $this->_req->post->profile_area;
			$active = isset($this->_req->post->active) ? 1 : 0;
			$private = $this->_req->getPost('private', 'intval', 0);
			$can_search = isset($this->_req->post->can_search) ? 1 : 0;

			// Some masking stuff...
			$mask = $this->_req->getPost('mask', 'strval', '');
			if ($mask == 'regex' && isset($this->_req->post->regex))
				$mask .= $this->_req->post->regex;

			$field_length = $this->_req->getPost('max_length', 'intval', 255);
			$enclose = $this->_req->getPost('enclose', 'strval', '');
			$placement = $this->_req->getPost('placement', 'intval', 0);

			// Select options?
			$field_options = '';
			$newOptions = array();

			// Set default
			$default = '';

			switch ($this->_req->post->field_type)
			{
				case 'check':
					$default = isset($this->_req->post->default_check) ? 1 : '';
					break;
				case 'select':
				case 'radio':
					if (!empty($this->_req->post->select_option))
					{
						foreach ($this->_req->post->select_option as $k => $v)
						{
							// Clean, clean, clean...
							$v = Util::htmlspecialchars($v);
							$v = strtr($v, array(',' => ''));

							// Nada, zip, etc...
							if (trim($v) == '')
								continue;

							// Otherwise, save it boy.
							$field_options .= $v . ',';

							// This is just for working out what happened with old options...
							$newOptions[$k] = $v;

							// Is it default?
							if (isset($this->_req->post->default_select) && $this->_req->post->default_select == $k)
								$default = $v;
						}

						if (isset($_POST['default_select']) && $_POST['default_select'] == 'no_default')
							$default = 'no_default';

						$field_options = substr($field_options, 0, -1);
					}
					break;
				default:
					$default = isset($this->_req->post->default_value) ? $this->_req->post->default_value : '';
			}

			// Text area by default has dimensions
//			if ($this->_req->post->field_type == 'textarea')
//				$default = (int) $this->_req->post->rows . ',' . (int) $this->_req->post->cols;

			// Come up with the unique name?
			if (empty($context['fid']))
			{
				$colname = Util::substr(strtr($this->_req->post->field_name, array(' ' => '')), 0, 6);
				preg_match('~([\w\d_-]+)~', $colname, $matches);

				// If there is nothing to the name, then let's start our own - for foreign languages etc.
				if (isset($matches[1]))
					$colname = $initial_colname = 'cust_' . strtolower($matches[1]);
				else
					$colname = $initial_colname = 'cust_' . mt_rand(1, 999999);

				$unique = ensureUniqueProfileField($colname, $initial_colname);

				// Still not a unique column name? Leave it up to the user, then.
				if (!$unique)
					throw new Elk_Exception('custom_option_not_unique');

				// And create a new field
				$new_field = array(
					'col_name' => $colname,
					'field_name' => $this->_req->post->field_name,
					'field_desc' => $this->_req->post->field_desc,
					'field_type' => $this->_req->post->field_type,
					'field_length' => $field_length,
					'field_options' => $field_options,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'show_profile' => $show_profile,
					'private' => $private,
					'active' => $active,
					'default_value' => $default,
					'rows' => $rows,
					'cols' => $cols,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'vieworder' => list_getProfileFieldSize() + 1,
				);
				addProfileField($new_field);
			}
			// Work out what to do with the user data otherwise...
			else
			{
				// Anything going to check or select is pointless keeping - as is anything coming from check!
				if (($this->_req->post->field_type == 'check' && $context['field']['type'] != 'check')
					|| (($this->_req->post->field_type == 'select' || $this->_req->post->field_type == 'radio') && $context['field']['type'] != 'select' && $context['field']['type'] != 'radio')
					|| ($context['field']['type'] == 'check' && $this->_req->post->field_type != 'check'))
				{
					deleteProfileFieldUserData($context['field']['colname']);
				}
				// Otherwise - if the select is edited may need to adjust!
				elseif ($this->_req->post->field_type == 'select' || $this->_req->post->field_type == 'radio')
				{
					$optionChanges = array();
					$takenKeys = array();

					// Work out what's changed!
					foreach ($context['field']['options'] as $k => $option)
					{
						if (trim($option) == '')
							continue;

						// Still exists?
						if (in_array($option, $newOptions))
						{
							$takenKeys[] = $k;
							continue;
						}
					}

					// Finally - have we renamed it - or is it really gone?
					foreach ($optionChanges as $k => $option)
					{
						// Just been renamed?
						if (!in_array($k, $takenKeys) && !empty($newOptions[$k]))
							updateRenamedProfileField($k, $newOptions, $context['field']['colname'], $option);
					}
				}
				// @todo Maybe we should adjust based on new text length limits?

				// And finally update an existing field
				$field_data = array(
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'private' => $private,
					'active' => $active,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'current_field' => $context['fid'],
					'field_name' => $this->_req->post->field_name,
					'field_desc' => $this->_req->post->field_desc,
					'field_type' => $this->_req->post->field_type,
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'rows' => $rows,
					'cols' => $cols,
				);

				updateProfileField($field_data);

				// Just clean up any old selects - these are a pain!
				if (($this->_req->post->field_type == 'select' || $this->_req->post->field_type == 'radio') && !empty($newOptions))
					deleteOldProfileFieldSelects($newOptions, $context['field']['colname']);
			}
		}
		// Deleting?
		elseif (isset($this->_req->post->delete) && $context['field']['colname'])
		{
			checkSession();
			validateToken('admin-ecp');

			// Delete the old data first, then the field.
			deleteProfileFieldUserData($context['field']['colname']);
			deleteProfileField($context['fid']);
		}

		// Rebuild display cache etc.
		if (isset($this->_req->post->delete) || isset($this->_req->post->save) || isset($this->_req->post->onoff))
		{
			checkSession();

			// Update the display cache
			updateDisplayCache();
			redirectexit('action=admin;area=featuresettings;sa=profile');
		}

		createToken('admin-ecp');
	}

	/**
	 * Editing personal messages settings
	 *
	 * - Accessed with ?action=admin;area=featuresettings;sa=pmsettings
	 */
	public function action_pmsettings()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_pmSettings());

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		loadLanguage('ManageMembers');

		$context['pm_limits'] = loadPMLimits();

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			require_once(SUBSDIR . '/Membergroups.subs.php');
			foreach ($context['pm_limits'] as $group_id => $group)
			{
				if (isset($this->_req->post->group[$group_id]) && $this->_req->post->group[$group_id] != $group['max_messages'])
					updateMembergroupProperties(array('current_group' => $group_id, 'max_messages' => $this->_req->post->group[$group_id]));
			}

			call_integration_hook('integrate_save_pmsettings_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=pmsettings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=pmsettings';
		$context['settings_title'] = $txt['personal_messages'];

		$settingsForm->prepare();
	}

	/**
	 * Return basic feature settings.
	 */
	private function _basicSettings()
	{
		global $txt;

		$config_vars = array(
				// Basic stuff, titles, permissions...
				array('check', 'allow_guestAccess'),
				array('check', 'enable_buddylist'),
				array('check', 'allow_editDisplayName'),
				array('check', 'allow_hideOnline'),
				array('check', 'titlesEnable'),
			'',
				// Javascript and CSS options
				array('select', 'jquery_source', array('auto' => $txt['jquery_auto'], 'local' => $txt['jquery_local'], 'cdn' => $txt['jquery_cdn'])),
				array('check', 'jquery_default', 'onchange' => 'showhideJqueryOptions();'),
				array('text', 'jquery_version', 'postinput' => $txt['jquery_custom_after']),
				array('check', 'jqueryui_default', 'onchange' => 'showhideJqueryOptions();'),
				array('text', 'jqueryui_version', 'postinput' => $txt['jqueryui_custom_after']),
				array('check', 'minify_css_js', 'postinput' => '<a href="#" id="clean_hives" class="linkbutton">' . $txt['clean_hives'] . '</a>'),
			'',
				// Number formatting, timezones.
				array('text', 'time_format'),
				array('float', 'time_offset', 'subtext' => $txt['setting_time_offset_note'], 6, 'postinput' => $txt['hours']),
				'default_timezone' => array('select', 'default_timezone', array()),
			'',
				// Who's online?
				array('check', 'who_enabled'),
				array('int', 'lastActive', 6, 'postinput' => $txt['minutes']),
			'',
				// Statistics.
				array('check', 'trackStats'),
				array('check', 'hitStats'),
			'',
				// Option-ish things... miscellaneous sorta.
				array('check', 'allow_disableAnnounce'),
				array('check', 'disallow_sendBody'),
				array('select', 'enable_contactform', array('disabled' => $txt['contact_form_disabled'], 'registration' => $txt['contact_form_registration'], 'menu' => $txt['contact_form_menu'])),
		);

		// Get all the time zones.
		$all_zones = timezone_identifiers_list();
		if ($all_zones === false)
			unset($config_vars['default_timezone']);
		else
		{
			// Make sure we set the value to the same as the printed value.
			foreach ($all_zones as $zone)
				$config_vars['default_timezone'][2][$zone] = $zone;
		}

		call_integration_hook('integrate_modify_basic_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the basic settings, used for admin search
	 */
	public function basicSettings_search()
	{
		return $this->_basicSettings();
	}

	/**
	 * Return layout settings.
	 */
	private function _layoutSettings()
	{
		global $txt;

		$config_vars = array_merge(getFrontPageControllers(), array(
			'',
				// Pagination stuff.
				array('check', 'compactTopicPagesEnable'),
				array('int', 'compactTopicPagesContiguous', 'subtext' => str_replace(' ', '&nbsp;', '"3" ' . $txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br />' . str_replace(' ', '&nbsp;', '"5" ' . $txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>')),
				array('int', 'defaultMaxMembers'),
				array('check', 'displayMemberNames'),
			'',
				// Stuff that just is everywhere - today, search, online, etc.
				array('select', 'todayMod', array($txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'], $txt['relative_time'])),
				array('check', 'onlineEnable'),
				array('check', 'enableVBStyleLogin'),
			'',
				// Automagic image resizing.
				array('int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']),
				array('int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']),
			'',
				// This is like debugging sorta.
				array('check', 'timeLoadPageEnable'),
		));

		call_integration_hook('integrate_modify_layout_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the layout settings, used for admin search
	 */
	public function layoutSettings_search()
	{
		return $this->_layoutSettings();
	}

	/**
	 * Return karma settings.
	 */
	private function _karmaSettings()
	{
		global $txt;

		$config_vars = array(
				// Karma - On or off?
				array('select', 'karmaMode', explode('|', $txt['karma_options'])),
			'',
				// Who can do it.... and who is restricted by time limits?
				array('int', 'karmaMinPosts', 6, 'postinput' => $txt['manageposts_posts']),
				array('float', 'karmaWaitTime', 6, 'postinput' => $txt['hours']),
				array('check', 'karmaTimeRestrictAdmins'),
				array('check', 'karmaDisableSmite'),
			'',
				// What does it look like?  [smite]?
				array('text', 'karmaLabel'),
				array('text', 'karmaApplaudLabel', 'mask' => 'nohtml'),
				array('text', 'karmaSmiteLabel', 'mask' => 'nohtml'),
		);

		call_integration_hook('integrate_modify_karma_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the karma settings, used for admin search
	 */
	public function karmaSettings_search()
	{
		return $this->_karmaSettings();
	}

	/**
	 * Return likes settings.
	 */
	private function _likesSettings()
	{
		global $txt;

		$config_vars = array(
				// Likes - On or off?
				array('check', 'likes_enabled'),
			'',
				// Who can do it.... and who is restricted by count limits?
				array('int', 'likeMinPosts', 6, 'postinput' => $txt['manageposts_posts']),
				array('int', 'likeWaitTime', 6, 'postinput' => $txt['minutes']),
				array('int', 'likeWaitCount', 6),
				array('check', 'likeRestrictAdmins'),
				array('check', 'likeAllowSelf'),
			'',
				array('int', 'likeDisplayLimit', 6)
		);

		call_integration_hook('integrate_modify_likes_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the likes settings, used for admin search
	 */
	public function likesSettings_search()
	{
		return $this->_likesSettings();
	}

	/**
	 * Return mentions settings.
	 */
	private function _notificationsSettings()
	{
		global $txt, $modSettings;

		loadLanguage('Profile');
		loadLanguage('UserNotifications');

		// The mentions settings
		$config_vars = array(
			array('title', 'mentions_settings'),
			array('check', 'mentions_enabled'),
		);

		$notification_methods = Notifications::getInstance()->getNotifiers();
		$notification_types = getNotificationTypes();
		$current_settings = unserialize($modSettings['notification_methods']);

		foreach ($notification_types as $title)
		{
			$config_vars[] = array('title', 'setting_' . $title);

			foreach ($notification_methods as $method)
			{
				if ($method === 'notification')
					$text_label = $txt['setting_notify_enable_this'];
				else
					$text_label = $txt['notify_' . $method];

				$config_vars[] = array('check', 'notifications[' . $title . '][' . $method . ']', 'text_label' => $text_label);
				$modSettings['notifications[' . $title . '][' . $method . ']'] = !empty($current_settings[$title][$method]);
			}
		}

		call_integration_hook('integrate_modify_mention_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the mention settings, used for admin search
	 */
	public function mentionSettings_search()
	{
		return $this->_notificationsSettings();
	}

	/**
	 * Return signature settings.
	 *
	 * - Used in admin center search and settings form
	 */
	private function _signatureSettings()
	{
		global $txt;

		$config_vars = array(
				// Are signatures even enabled?
				array('check', 'signature_enable'),
			'',
				// Tweaking settings!
				array('int', 'signature_max_length', 'subtext' => $txt['zero_for_no_limit']),
				array('int', 'signature_max_lines', 'subtext' => $txt['zero_for_no_limit']),
				array('int', 'signature_max_font_size', 'subtext' => $txt['zero_for_no_limit']),
				array('check', 'signature_allow_smileys', 'onclick' => 'document.getElementById(\'signature_max_smileys\').disabled = !this.checked;'),
				array('int', 'signature_max_smileys', 'subtext' => $txt['zero_for_no_limit']),
				array('select', 'signature_repetition_guests',
					array(
						$txt['signature_always'],
						$txt['signature_onlyfirst'],
						$txt['signature_never'],
					),
				),
				array('select', 'signature_repetition_members',
					array(
						$txt['signature_always'],
						$txt['signature_onlyfirst'],
						$txt['signature_never'],
					),
				),
			'',
				// Image settings.
				array('int', 'signature_max_images', 'subtext' => $txt['signature_max_images_note']),
				array('int', 'signature_max_image_width', 'subtext' => $txt['zero_for_no_limit']),
				array('int', 'signature_max_image_height', 'subtext' => $txt['zero_for_no_limit']),
			'',
				array('bbc', 'signature_bbc'),
		);

		call_integration_hook('integrate_modify_signature_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the signature settings, used for admin search
	 */
	public function signatureSettings_search()
	{
		return $this->_signatureSettings();
	}

	/**
	 * Return pm settings.
	 *
	 * - Used in admin center search and settings form
	 */
	private function _pmSettings()
	{
		global $txt;

		$config_vars = array(
			// Reporting of personal messages?
			array('check', 'enableReportPM'),
			// Inline permissions.
			array('permissions', 'pm_send'),
			// PM Settings
			array('title', 'antispam_PM'),
				'pm1' => array('int', 'max_pm_recipients', 'postinput' => $txt['max_pm_recipients_note']),
				'pm2' => array('int', 'pm_posts_verification', 'postinput' => $txt['pm_posts_verification_note']),
				'pm3' => array('int', 'pm_posts_per_hour', 'postinput' => $txt['pm_posts_per_hour_note']),
			array('title', 'membergroups_max_messages'),
				array('desc', 'membergroups_max_messages_desc'),
				array('callback', 'pm_limits'),
		);

		call_integration_hook('integrate_modify_pmsettings_settings', array(&$config_vars));

		return $config_vars;
	}
}

/**
 * Just pause the signature applying thing.
 *
 * @todo Move to subs file
 * @todo Merge with other pause functions?
 *    pausePermsSave(), pauseAttachmentMaintenance(), pauseRepairProcess()
 *
 * @param int $applied_sigs
 * @throws Elk_Exception
 */
function pauseSignatureApplySettings($applied_sigs)
{
	global $context, $txt, $sig_start;

	// Try get more time...
	detectServer()->setTimeLimit(600);

	// Have we exhausted all the time we allowed?
	if (time() - array_sum(explode(' ', $sig_start)) < 3)
		return;

	$context['continue_get_data'] = '?action=admin;area=featuresettings;sa=sig;apply;step=' . $applied_sigs . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'sig';

	// Get the right percent.
	$context['continue_percent'] = round(($applied_sigs / $context['max_member']) * 100);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}
