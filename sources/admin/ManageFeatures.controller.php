<?php

/**
 * Manage features and options administration page.
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
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Manage features and options administration page.
 *
 * This controller handles the pages which allow the admin
 * to see and change the basic feature settings of their site.
 */
class ManageFeatures_Controller extends Action_Controller
{
	/**
	 * Basic feature settings form
	 * @var Settings_Form
	 */
	protected $_basicSettings;

	/**
	 * Karma settings form
	 * @var Settings_Form
	 */
	protected $_karmaSettings;

	/**
	 * Likes settings form
	 * @var Settings_Form
	 */
	protected $_likesSettings;

	/**
	 * Layout settings form
	 * @var Settings_Form
	 */
	protected $_layoutSettings;

	/**
	 * Signature settings form
	 * @var Settings_Form
	 */
	protected $_signatureSettings;

	/**
	 * Mentions settings form
	 * @var Settings_Form
	 */
	protected $_mentionSettings;

	/**
	 * Mentions settings form
	 * @var Settings_Form
	 */
	protected $_PMSettings;

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
				'function' => 'action_mentionSettings_display',
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
		$this->_initBasicSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_basicSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			// Prevent absurd boundaries here - make it a day tops.
			if (isset($_POST['lastActive']))
				$_POST['lastActive'] = min((int) $_POST['lastActive'], 1440);

			call_integration_hook('integrate_save_basic_settings');

			Settings_Form::save_db($config_vars);

			writeLog();
			redirectexit('action=admin;area=featuresettings;sa=basic');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
		$context['settings_title'] = $txt['mods_cat_features'];

		// Show / hide custom jquery fields as required
		addInlineJavascript('showhideJqueryOptions();', true);

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize basic settings form.
	 */
	private function _initBasicSettingsForm()
	{
		// We need some settings! ..ok, some work with our settings :P
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_basicSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_basicSettings();

		return $this->_basicSettings->settings($config_vars);
	}

	/**
	 * Allows modifying the global layout settings in the forum
	 *
	 * - Accessed through ?action=admin;area=featuresettings;sa=layout;
	 */
	public function action_layoutSettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$this->_initLayoutSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_layoutSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			call_integration_hook('integrate_save_layout_settings');

			Settings_Form::save_db($config_vars);
			writeLog();

			redirectexit('action=admin;area=featuresettings;sa=layout');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=layout';
		$context['settings_title'] = $txt['mods_cat_layout'];

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize the layout settings screen from features and options admin area.
	 */
	private function _initLayoutSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_layoutSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_layoutSettings();

		return $this->_layoutSettings->settings($config_vars);
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
		$this->_initKarmaSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_karmaSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			call_integration_hook('integrate_save_karma_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=featuresettings;sa=karma');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=karma';
		$context['settings_title'] = $txt['karma'];

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes the karma settings admin page.
	 */
	private function _initKarmaSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_karmaSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_karmaSettings();

		return $this->_karmaSettings->settings($config_vars);
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
		$this->_initLikesSettingsForm();

		// retrieve the current config settings
		$config_vars = $this->_likesSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			call_integration_hook('integrate_save_likes_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=featuresettings;sa=likes');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=likes';
		$context['settings_title'] = $txt['likes'];

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes the likes settings admin page.
	 */
	private function _initLikesSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_likesSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_likesSettings();

		return $this->_likesSettings->settings($config_vars);
	}

	/**
	 * Initializes the mentions settings admin page.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=mentions;
	 */
	public function action_mentionSettings_display()
	{
		global $context, $scripturl, $modSettings;

		// Initialize the form
		$this->_initMentionSettingsForm();

		// Initialize it with our settings
		$config_vars = $this->_mentionSettings->settings();

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession();

			if ((!isset($_POST['mentions_buddy']) && !empty($modSettings['mentions_buddy'])) || (isset($_POST['mentions_buddy']) && $_POST['mentions_buddy'] != $modSettings['mentions_buddy']))
			{
				require_once(SUBSDIR . '/Mentions.subs.php');
				toggleMentionsVisibility('buddy', !empty($_POST['mentions_buddy']));
			}

			// If mentions are enabled, the related task should be enabled as well, otherwise should be disabled.
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');
			toggleTaskStatusByName('user_access_mentions', !empty($_POST['mentions_enabled']));

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=featuresettings;sa=mention');
		}

		// Prepare the settings for display
		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=mention';
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieve and return all admin settings for mentions.
	 */
	private function _initMentionSettingsForm()
	{
		global $txt, $context;

		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		loadLanguage('Mentions');

		// Instantiate the form
		$this->_mentionSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_mentionSettings();

		// Some context stuff
		$context['page_title'] = $txt['mentions_settings'];
		$context['sub_template'] = 'show_settings';

		return $this->_mentionSettings->settings($config_vars);
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
		$this->_initSignatureSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_signatureSettings->settings();

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

		// Applying to ALL signatures?!!
		if (isset($_GET['apply']))
		{
			// Security!
			checkSession('get');

			require_once(SUBSDIR . '/ManageFeatures.subs.php');
			require_once(SUBSDIR . '/Members.subs.php');
			$sig_start = time();

			// This is horrid - but I suppose some people will want the option to do it.
			$applied_sigs = isset($_GET['step']) ? (int) $_GET['step'] : 0;
			$done = false;
			$context['max_member'] = maxMemberID();

			while (!$done)
			{
				$changes = array();
				$update_sigs = getSignatureFromMembers($applied_sigs);

				if (empty($update_sigs))
					$done = true;

				foreach ($update_sigs as $row)
				{
					// Apply all the rules we can realistically do.
					$sig = strtr($row['signature'], array('<br />' => "\n"));

					// Max characters...
					if (!empty($sig_limits[1]))
						$sig = Util::substr($sig, 0, $sig_limits[1]);

					// Max lines...
					if (!empty($sig_limits[2]))
					{
						$count = 0;
						$str_len = strlen($sig);
						for ($i = 0; $i < $str_len; $i++)
						{
							if ($sig[$i] == "\n")
							{
								$count++;
								if ($count >= $sig_limits[2])
									$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), array("\n" => ' '));
							}
						}
					}

					if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $sig, $matches) !== false && isset($matches[2]))
					{
						foreach ($matches[1] as $ind => $size)
						{
							$limit_broke = 0;

							// Attempt to allow all sizes of abuse, so to speak.
							if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
								$limit_broke = $sig_limits[7] . 'px';
							elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
								$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
							elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
								$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
							elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
								$limit_broke = 'large';

							if ($limit_broke)
								$sig = str_replace($matches[0][$ind], '[size=' . $sig_limits[7] . 'px', $sig);
						}
					}

					// Stupid images - this is stupidly, stupidly challenging.
					if ((!empty($sig_limits[3]) || !empty($sig_limits[5]) || !empty($sig_limits[6])))
					{
						$replaces = array();
						$img_count = 0;

						// Get all BBC tags...
						preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br />)*([^<">]+?)(?:<br />)*\[/img\]~i', $sig, $matches);

						// ... and all HTML ones.
						preg_match_all('~&lt;img\s+src=(?:&quot;)?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;)?(?:\s+alt=(?:&quot;)?(.*?)(?:&quot;)?)?(?:\s?/)?&gt;~i', $sig, $matches2, PREG_PATTERN_ORDER);

						// And stick the HTML in the BBC.
						if (!empty($matches2))
						{
							foreach ($matches2[0] as $ind => $dummy)
							{
								$matches[0][] = $matches2[0][$ind];
								$matches[1][] = '';
								$matches[2][] = '';
								$matches[3][] = '';
								$matches[4][] = '';
								$matches[5][] = '';
								$matches[6][] = '';
								$matches[7][] = $matches2[1][$ind];
							}
						}

						// Try to find all the images!
						if (!empty($matches))
						{
							$image_count_holder = array();
							foreach ($matches[0] as $key => $image)
							{
								$width = -1; $height = -1;
								$img_count++;

								// Too many images?
								if (!empty($sig_limits[3]) && $img_count > $sig_limits[3])
								{
									// If we've already had this before we only want to remove the excess.
									if (isset($image_count_holder[$image]))
									{
										$img_offset = -1;
										$rep_img_count = 0;
										while ($img_offset !== false)
										{
											$img_offset = strpos($sig, $image, $img_offset + 1);
											$rep_img_count++;
											if ($rep_img_count > $image_count_holder[$image])
											{
												// Only replace the excess.
												$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));

												// Stop looping.
												$img_offset = false;
											}
										}
									}
									else
										$replaces[$image] = '';

									continue;
								}

								// Does it have predefined restraints? Width first.
								if ($matches[6][$key])
									$matches[2][$key] = $matches[6][$key];

								if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
								{
									$width = $sig_limits[5];
									$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
								}
								elseif ($matches[2][$key])
									$width = $matches[2][$key];

								// ... and height.
								if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
								{
									$height = $sig_limits[6];
									if ($width != -1)
										$width = $width * ($height / $matches[4][$key]);
								}
								elseif ($matches[4][$key])
									$height = $matches[4][$key];

								// If the dimensions are still not fixed - we need to check the actual image.
								if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
								{
									// We'll mess up with images, who knows.
									require_once(SUBSDIR . '/Attachments.subs.php');

									$sizes = url_image_size($matches[7][$key]);
									if (is_array($sizes))
									{
										// Too wide?
										if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
										{
											$width = $sig_limits[5];
											$sizes[1] = $sizes[1] * ($width / $sizes[0]);
										}

										// Too high?
										if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
										{
											$height = $sig_limits[6];
											if ($width == -1)
												$width = $sizes[0];
											$width = $width * ($height / $sizes[1]);
										}
										elseif ($width != -1)
											$height = $sizes[1];
									}
								}

								// Did we come up with some changes? If so remake the string.
								if ($width != -1 || $height != -1)
									$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';

								// Record that we got one.
								$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
							}

							if (!empty($replaces))
								$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
						}
					}

					// Try to fix disabled tags.
					if (!empty($disabledTags))
					{
						$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);
						$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
					}

					$sig = strtr($sig, array("\n" => '<br />'));
					call_integration_hook('integrate_apply_signature_settings', array(&$sig, $sig_limits, $disabledTags));
					if ($sig != $row['signature'])
						$changes[$row['id_member']] = $sig;
				}

				// Do we need to delete what we have?
				if (!empty($changes))
				{
					foreach ($changes as $id => $sig)
						updateSignature($id, $sig);
				}

				$applied_sigs += 50;
				if (!$done)
					pauseSignatureApplySettings($applied_sigs);
			}

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
		);

		// Temporarily make each setting a modSetting!
		foreach ($context['signature_settings'] as $key => $value)
			$modSettings['signature_' . $key] = $value;

		// Make sure we check the right tags!
		$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			// Clean up the tag stuff!
			$bbcTags = array();
			foreach (parse_bbc(false) as $tag)
				$bbcTags[] = $tag['tag'];

			if (!isset($_POST['signature_bbc_enabledTags']))
				$_POST['signature_bbc_enabledTags'] = array();
			elseif (!is_array($_POST['signature_bbc_enabledTags']))
				$_POST['signature_bbc_enabledTags'] = array($_POST['signature_bbc_enabledTags']);

			$sig_limits = array();
			foreach ($context['signature_settings'] as $key => $value)
			{
				if ($key == 'allow_smileys')
					continue;
				elseif ($key == 'max_smileys' && empty($_POST['signature_allow_smileys']))
					$sig_limits[] = -1;
				else
					$sig_limits[] = !empty($_POST['signature_' . $key]) ? max(1, (int) $_POST['signature_' . $key]) : 0;
			}

			call_integration_hook('integrate_save_signature_settings', array(&$sig_limits, &$bbcTags));

			$_POST['signature_settings'] = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $_POST['signature_bbc_enabledTags']));

			// Even though we have practically no settings let's keep the convention going!
			$save_vars = array();
			$save_vars[] = array('text', 'signature_settings');

			Settings_Form::save_db($save_vars);
			redirectexit('action=admin;area=featuresettings;sa=sig');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=sig';
		$context['settings_title'] = $txt['signature_settings'];
		$context['settings_message'] = !empty($settings_applied) ? $txt['signature_settings_applied'] : sprintf($txt['signature_settings_warning'], $scripturl . '?action=admin;area=featuresettings;sa=sig;apply;' . $context['session_id'] . '=' . $context['session_var']);

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes signature settings form.
	 */
	private function _initSignatureSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_signatureSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_signatureSettings();

		return $this->_signatureSettings->settings($config_vars);
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
		$standard_fields = array('location', 'gender', 'website', 'personal_text', 'posts', 'warning_status');

		// What fields can't you put on the registration page?
		$context['fields_no_registration'] = array('posts', 'warning_status');

		// Are we saving any standard field changes?
		if (isset($_POST['save']))
		{
			checkSession();
			validateToken('admin-scp');

			$changes = array();

			// Do the active ones first.
			$disable_fields = array_flip($standard_fields);
			if (!empty($_POST['active']))
			{
				foreach ($_POST['active'] as $value)
					if (isset($disable_fields[$value]))
						unset($disable_fields[$value]);
			}

			// What we have left!
			$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

			// Things we want to show on registration?
			$reg_fields = array();
			if (!empty($_POST['reg']))
			{
				foreach ($_POST['reg'] as $value)
					if (in_array($value, $standard_fields) && !isset($disable_fields[$value]))
						$reg_fields[] = $value;
			}

			// What we have left!
			$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

			if (!empty($changes))
				updateSettings($changes);
		}

		createToken('admin-scp');

		require_once(SUBSDIR . '/GenericList.class.php');
		require_once(SUBSDIR . '/ManageFeatures.subs.php');

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
						'function' => create_function('$rowData', '
							$isChecked = $rowData[\'disabled\'] ? \'\' : \' checked="checked"\';
							$onClickHandler = $rowData[\'can_show_register\'] ? sprintf(\'onclick="document.getElementById(\\\'reg_%1$s\\\').disabled = !this.checked;"\', $rowData[\'id\']) : \'\';
							return sprintf(\'<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" class="input_check" %2$s %3$s />\', $rowData[\'id\'], $isChecked, $onClickHandler);
						'),
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
						'function' => create_function('$rowData', '
							$isChecked = $rowData[\'on_register\'] && !$rowData[\'disabled\'] ? \' checked="checked"\' : \'\';
							$isDisabled = $rowData[\'can_show_register\'] ? \'\' : \' disabled="disabled"\';
							return sprintf(\'<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" class="input_check" %2$s %3$s />\', $rowData[\'id\'], $isChecked, $isDisabled);
						'),
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
						'style' => 'display: none',
					),
					'data' => array(
						'db' => 'vieworder',
						'style' => 'display: none',
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
						'function' => create_function('$rowData', '
							global $scripturl;

							return sprintf(\'<a href="%1$s?action=admin;area=featuresettings;sa=profileedit;fid=%2$d">%3$s</a><div class="smalltext">%4$s</div>\', $scripturl, $rowData[\'id_field\'], $rowData[\'field_name\'], $rowData[\'field_desc\']);
						'),
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
						'function' => create_function('$rowData', '
							global $txt;

							$textKey = sprintf(\'custom_profile_type_%1$s\', $rowData[\'field_type\']);
							return isset($txt[$textKey]) ? $txt[$textKey] : $textKey;
						'),
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
						'function' => create_function('$rowData', '
							$isChecked = $rowData[\'active\'] ? \' checked="checked"\' :  \'\';
							return sprintf(\'<input type="checkbox" name="cust[]" id="cust_%1$s" value="%1$s" class="input_check"%2$s />\', $rowData[\'id_field\'], $isChecked);
						'),
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
						'function' => create_function('$rowData', '
							global $txt;

							return $txt[\'custom_profile_placement_\' . (empty($rowData[\'placement\']) ? \'standard\' : ($rowData[\'placement\'] == 1 ? \'withicons\' : \'abovesignature\'))];
						'),
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

		require_once(SUBSDIR . '/ManageFeatures.subs.php');
		loadTemplate('ManageFeatures');

		// Sort out the context!
		$context['fid'] = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
		$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
		$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
		$context['sub_template'] = 'edit_profile_field';

		// any errors messages to show?
		if (isset($_GET['msg']))
		{
			loadLanguage('Errors');

			if (isset($txt['custom_option_' . $_GET['msg']]))
				$context['custom_option__error'] = $txt['custom_option_' . $_GET['msg']];
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
		if (isset($_POST['onoff']))
		{
			checkSession();
			validateToken('admin-scp');

			// Enable and disable custom fields as required.
			$enabled = array(0);
			foreach ($_POST['cust'] as $id)
				$enabled[] = (int) $id;

			updateRenamedProfileStatus($enabled);
		}
		// Are we saving?
		elseif (isset($_POST['save']))
		{
			checkSession();
			validateToken('admin-ecp');

			// Everyone needs a name - even the (bracket) unknown...
			if (trim($_POST['field_name']) == '')
				redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';msg=need_name');

			// Regex you say?  Do a very basic test to see if the pattern is valid
			if (!empty($_POST['regex']) && @preg_match($_POST['regex'], 'dummy') === false)
				redirectexit($scripturl . '?action=admin;area=featuresettings;sa=profileedit;fid=' . $_GET['fid'] . ';msg=regex_error');

			$_POST['field_name'] = Util::htmlspecialchars($_POST['field_name']);
			$_POST['field_desc'] = Util::htmlspecialchars($_POST['field_desc']);

			// Checkboxes...
			$show_reg = isset($_POST['reg']) ? (int) $_POST['reg'] : 0;
			$show_display = isset($_POST['display']) ? 1 : 0;
			$show_memberlist = isset($_POST['memberlist']) ? 1 : 0;
			$bbc = isset($_POST['bbc']) ? 1 : 0;
			$show_profile = $_POST['profile_area'];
			$active = isset($_POST['active']) ? 1 : 0;
			$private = isset($_POST['private']) ? (int) $_POST['private'] : 0;
			$can_search = isset($_POST['can_search']) ? 1 : 0;

			// Some masking stuff...
			$mask = isset($_POST['mask']) ? $_POST['mask'] : '';
			if ($mask == 'regex' && isset($_POST['regex']))
				$mask .= $_POST['regex'];

			$field_length = isset($_POST['max_length']) ? (int) $_POST['max_length'] : 255;
			$enclose = isset($_POST['enclose']) ? $_POST['enclose'] : '';
			$placement = isset($_POST['placement']) ? (int) $_POST['placement'] : 0;

			// Select options?
			$field_options = '';
			$newOptions = array();
			$default = isset($_POST['default_check']) && $_POST['field_type'] == 'check' ? 1 : '';
			if (!empty($_POST['select_option']) && ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio'))
			{
				foreach ($_POST['select_option'] as $k => $v)
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
					if (isset($_POST['default_select']) && $_POST['default_select'] == $k)
						$default = $v;
				}

				$field_options = substr($field_options, 0, -1);
			}

			// Text area by default has dimensions
			if ($_POST['field_type'] == 'textarea')
				$default = (int) $_POST['rows'] . ',' . (int) $_POST['cols'];

			// Come up with the unique name?
			if (empty($context['fid']))
			{
				$colname = Util::substr(strtr($_POST['field_name'], array(' ' => '')), 0, 6);
				preg_match('~([\w\d_-]+)~', $colname, $matches);

				// If there is nothing to the name, then let's start our own - for foreign languages etc.
				if (isset($matches[1]))
					$colname = $initial_colname = 'cust_' . strtolower($matches[1]);
				else
					$colname = $initial_colname = 'cust_' . mt_rand(1, 999999);

				$unique = ensureUniqueProfileField($colname, $initial_colname);

				// Still not a unique colum name? Leave it up to the user, then.
				if (!$unique)
					fatal_lang_error('custom_option_not_unique');
			}
			// Work out what to do with the user data otherwise...
			else
			{
				// Anything going to check or select is pointless keeping - as is anything coming from check!
				if (($_POST['field_type'] == 'check' && $context['field']['type'] != 'check')
					|| (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && $context['field']['type'] != 'select' && $context['field']['type'] != 'radio')
					|| ($context['field']['type'] == 'check' && $_POST['field_type'] != 'check'))
				{
					deleteProfileFieldUserData($context['field']['colname']);
				}
				// Otherwise - if the select is edited may need to adjust!
				elseif ($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio')
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
			}

			// Updating an existing field?
			if ($context['fid'])
			{
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
					'field_name' => $_POST['field_name'],
					'field_desc' => $_POST['field_desc'],
					'field_type' => $_POST['field_type'],
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
				);

				updateProfileField($field_data);

				// Just clean up any old selects - these are a pain!
				if (($_POST['field_type'] == 'select' || $_POST['field_type'] == 'radio') && !empty($newOptions))
					deleteOldProfileFieldSelects($newOptions, $context['field']['colname']);
			}
			// Otherwise creating a new one
			else
			{
				$new_field = array(
					'col_name' => $colname,
					'field_name' => $_POST['field_name'],
					'field_desc' => $_POST['field_desc'],
					'field_type' => $_POST['field_type'],
					'field_length' => $field_length,
					'field_options' => $field_options,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'show_profile' => $show_profile,
					'private' => $private,
					'active' => $active,
					'default' => $default,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'vieworder' => list_getProfileFieldSize() + 1,
				);
				addProfileField($new_field);
			}
		}
		// Deleting?
		elseif (isset($_POST['delete']) && $context['field']['colname'])
		{
			checkSession();
			validateToken('admin-ecp');

			// Delete the old data first, then the field.
			deleteProfileFieldUserData($context['field']['colname']);
			deleteProfileField($context['fid']);
		}

		// Rebuild display cache etc.
		if (isset($_POST['delete']) || isset($_POST['save']) || isset($_POST['onoff']))
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
		$this->_initPMSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_PMSettings->settings();

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		loadLanguage('ManageMembers');

		$context['pm_limits'] = loadPMLimits();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			require_once(SUBSDIR . '/Membergroups.subs.php');
			foreach ($context['pm_limits'] as $group_id => $group)
			{
				if (isset($_POST['group'][$group_id]) && $_POST['group'][$group_id] != $group['max_messages'])
					updateMembergroupProperties(array('current_group' => $group_id, 'max_messages' => $_POST['group'][$group_id]));
			}

			call_integration_hook('integrate_save_pmsettings_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=featuresettings;sa=pmsettings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=pmsettings';
		$context['settings_title'] = $txt['personal_messages'];

		// We need this for the in-line permissions
		createToken('admin-mp');

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes the personal messages settings admin page.
	 */
	private function _initPMSettingsForm()
	{
		global $context;

		// We're working with them settings.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		$context['permissions_excluded'] = array(-1);

		// Instantiate the form
		$this->_PMSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_pmSettings();

		return $this->_PMSettings->settings($config_vars);
	}

	/**
	 * Return basic feature settings.
	 */
	private function _basicSettings()
	{
		global $txt, $modSettings, $context;

		// We need to know if personal text is enabled, and if it's in the registration fields option.
		// If admins have set it up as an on-registration thing, they can't set a default value (because it'll never be used)
		$disabled_fields = isset($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
		$reg_fields = isset($modSettings['registration_fields']) ? explode(',', $modSettings['registration_fields']) : array();
		$can_personal_text = !in_array('personal_text', $disabled_fields) && !in_array('personal_text', $reg_fields);

		$config_vars = array(
				// Basic stuff, titles, permissions...
				array('check', 'allow_guestAccess'),
				array('check', 'enable_buddylist'),
				array('check', 'enable_unwatch'),
				array('check', 'allow_editDisplayName'),
				array('check', 'allow_hideOnline'),
				array('check', 'titlesEnable'),
				array('text', 'default_personal_text', 'subtext' => $txt['default_personal_text_note'], 'disabled' => !$can_personal_text),
			'',
				// Javascript and CSS options
				array('select', 'jquery_source', array('auto' => $txt['jquery_auto'], 'local' => $txt['jquery_local'], 'cdn' => $txt['jquery_cdn'])),
				array('check', 'jquery_default', 'onchange' => 'showhideJqueryOptions();'),
				array('text', 'jquery_version', 'postinput' => $txt['jquery_custom_after']),
				array('check', 'jqueryui_default', 'onchange' => 'showhideJqueryOptions();'),
				array('text', 'jqueryui_version', 'postinput' => $txt['jqueryui_custom_after']),
				array('check', 'minify_css_js'),
			'',
				// SEO stuff
				array('check', 'queryless_urls', 'subtext' => '<strong>' . $txt['queryless_urls_note'] . '</strong><br />' . ($context['server']['is_apache'] || $context['server']['is_lighttpd'] ? $txt['queryless_urls_work'] : '<span class="error">' . $txt['queryless_urls_notwork'] . '</span>')),
				array('text', 'meta_keywords', 'subtext' => $txt['meta_keywords_note'], 'size' => 50),
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

		$config_vars = array(
				// Pagination stuff.
				array('check', 'compactTopicPagesEnable'),
				array('int', 'compactTopicPagesContiguous', null, $txt['contiguous_page_display'] . '<div class="smalltext">' . str_replace(' ', '&nbsp;', '"3" ' . $txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br />' . str_replace(' ', '&nbsp;', '"5" ' . $txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>') . '</div>'),
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
		);

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
				array('int', 'karmaMinPosts', 6, 'postinput' => strtolower($txt['posts'])),
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
				array('int', 'likeMinPosts', 6, 'postinput' => strtolower($txt['posts'])),
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
	private function _mentionSettings()
	{
		// The mentions settings
		$config_vars = array(
			array('title', 'mentions_settings'),
				array('check', 'mentions_enabled'),
				array('check', 'mentions_buddy'),
				array('check', 'mentions_dont_notify_rlike'),
		);

		call_integration_hook('integrate_modify_mention_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return the mention settings, used for admin search
	 */
	public function mentionSettings_search()
	{
		return $this->_mentionSettings();
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
 *    pausePermsSave(), pausAttachmentMaintenance(), pauseRepairProcess()
 *
 * @param int $applied_sigs
 */
function pauseSignatureApplySettings($applied_sigs)
{
	global $context, $txt, $sig_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

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