<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * This is the attachments and avatars controller class.
 * It is doing the job of attachments and avatars maintenance and management.
 */
class ManageAttachments_Controller
{
	/**
	 * Attachments settings form
	 * @var Settings_Form
	 */
	protected $_attachSettingsForm;

	/**
	 * The main 'Attachments and Avatars' management function.
	 * This function is the entry point for index.php?action=admin;area=manageattachments
	 * and it calls a function based on the sub-action.
	 * It requires the manage_attachments permission.
	 *
	 * @uses ManageAttachments template.
	 * @uses Admin language file.
	 * @uses template layer 'manage_files' for showing the tab bar.
	 *
	 */
	public function action_index()
	{
		global $txt, $context;

		// You have to be able to moderate the forum to do this.
		isAllowedTo('manage_attachments');

		// Setup the template stuff we'll probably need.
		loadTemplate('ManageAttachments');

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		// If they want to delete attachment(s), delete them. (otherwise fall through..)
		$subActions = array(
			'attachments' => array($this, 'action_attachSettings_display'),
			'avatars' => array(
				'file' => 'ManageAvatars.php',
				'controller' => 'ManageAvatars_Controller',
				'function' => 'action_index'),
			'attachpaths' => array ($this, 'action_attachpaths'),
			'browse' => array ($this, 'action_browse'),
			'byAge' => array ($this, 'action_byAge'),
			'bySize' => array ($this, 'action_bySize'),
			'maintenance' => array ($this, 'action_maintenance'),
			'moveAvatars' => array ($this, 'action_moveAvatars'),
			'repair' => array ($this, 'action_repair'),
			'remove' => array ($this, 'action_remove'),
			'removeall' => array ($this, 'action_removeall'),
			'transfer' => array ($this, 'action_transfer'),
		);

		$action = new Action();
		$action->initialize($subActions);

		call_integration_hook('integrate_manage_attachments', array(&$subActions));

		// Pick the correct sub-action.
		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
			$subAction = $_REQUEST['sa'];
		else
			$subAction = 'browse';

		$context['sub_action'] = $subAction;

		// Default page title is good.
		$context['page_title'] = $txt['attachments_avatars'];

		// This uses admin tabs - as it should!
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['attachments_avatars'],
			'help' => 'manage_files',
			'description' => $txt['attachments_desc'],
		);

		// Finally go to where we want to go
		$action->dispatch($subAction);
	}

	/**
	 * Allows to show/change attachment settings.
	 * This is the default sub-action of the 'Attachments and Avatars' center.
	 * Called by index.php?action=admin;area=manageattachments;sa=attachments.
	 *
	 * @uses 'attachments' sub template.
	 */
	public function action_attachSettings_display()
	{
		global $modSettings, $scripturl, $context;

		// initialize the form
		$this->_initAttachSettingsForm();

		$config_vars = $this->_attachSettingsForm->settings();

		$context['settings_post_javascript'] = '
	var storing_type = document.getElementById(\'automanage_attachments\');
	var base_dir = document.getElementById(\'use_subdirectories_for_attachments\');

	createEventListener(storing_type)
	storing_type.addEventListener("change", toggleSubDir, false);
	createEventListener(base_dir)
	base_dir.addEventListener("change", toggleSubDir, false);
	toggleSubDir();';

		call_integration_hook('integrate_modify_attachment_settings');

		// These are very likely to come in handy! (i.e. without them we're doomed!)
		require_once(ADMINDIR . '/ManagePermissions.php');
		require_once(ADMINDIR . '/ManageServer.php');
		require_once(SUBSDIR . '/Settings.class.php');

		// Saving settings?
		if (isset($_GET['save']))
		{
			checkSession();

			if (isset($_POST['attachmentUploadDir']))
			{
				if (!empty($_POST['attachmentUploadDir']) && $modSettings['attachmentUploadDir'] != $_POST['attachmentUploadDir'])
					rename($modSettings['attachmentUploadDir'], $_POST['attachmentUploadDir']);

				$modSettings['attachmentUploadDir'] = array(1 => $_POST['attachmentUploadDir']);
				$_POST['attachmentUploadDir'] = serialize($modSettings['attachmentUploadDir']);
			}

			if (!empty($_POST['use_subdirectories_for_attachments']))
			{
				if (isset($_POST['use_subdirectories_for_attachments']) && empty($_POST['basedirectory_for_attachments']))
					$_POST['basedirectory_for_attachments'] = (!empty($modSettings['basedirectory_for_attachments']) ? ($modSettings['basedirectory_for_attachments']) : BOARDDIR);

				if (!empty($_POST['use_subdirectories_for_attachments']) && !empty($modSettings['attachment_basedirectories']))
				{
					if (!is_array($modSettings['attachment_basedirectories']))
						$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);
				}
				else
					$modSettings['attachment_basedirectories'] = array();

				if (!empty($_POST['use_subdirectories_for_attachments']) && !empty($_POST['basedirectory_for_attachments']) && !in_array($_POST['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']))
				{
					$currentAttachmentUploadDir = $modSettings['currentAttachmentUploadDir'];

					if (!in_array($_POST['basedirectory_for_attachments'], $modSettings['attachmentUploadDir']))
					{
						if (!automanage_attachments_create_directory($_POST['basedirectory_for_attachments']))
							$_POST['basedirectory_for_attachments'] = $modSettings['basedirectory_for_attachments'];
					}

					if (!in_array($_POST['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']))
					{
						$modSettings['attachment_basedirectories'][$modSettings['currentAttachmentUploadDir']] = $_POST['basedirectory_for_attachments'];
						updateSettings(array(
							'attachment_basedirectories' => serialize($modSettings['attachment_basedirectories']),
							'currentAttachmentUploadDir' => $currentAttachmentUploadDir,
						));

						$_POST['use_subdirectories_for_attachments'] = 1;
						$_POST['attachmentUploadDir'] = serialize($modSettings['attachmentUploadDir']);

					}
				}
			}

			call_integration_hook('integrate_save_attachment_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=manageattachments;sa=attachments');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=manageattachments;save;sa=attachments';
		Settings_Form::prepare_db($config_vars);

		$context['sub_template'] = 'show_settings';
	}

	/**
	 * Initialize attachmentForm.
	 * Retrieve and return the administration settings for attachments.
	 */
	private function _initAttachSettingsForm()
	{
		global $modSettings, $txt, $scripturl;

		// instantiate the form
		$this->_attachSettingsForm = new Settings_Form();

		// initialize settings
		require_once(SUBSDIR . '/Attachments.subs.php');

		// Get the current attachment directory.
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$context['attachmentUploadDir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

		// First time here?
		if (empty($modSettings['attachment_basedirectories']) && $modSettings['currentAttachmentUploadDir'] == 1 && count($modSettings['attachmentUploadDir']) == 1)
			$modSettings['attachmentUploadDir'] = $modSettings['attachmentUploadDir'][1];

		// If not set, show a default path for the base directory
		if (!isset($_GET['save']) && empty($modSettings['basedirectory_for_attachments']))
			if (is_dir($modSettings['attachmentUploadDir'][1]))
				$modSettings['basedirectory_for_attachments'] = $modSettings['attachmentUploadDir'][1];
			else
				$modSettings['basedirectory_for_attachments'] = $context['attachmentUploadDir'];

		$context['valid_upload_dir'] = is_dir($context['attachmentUploadDir']) && is_writable($context['attachmentUploadDir']);

		if (!empty($modSettings['automanage_attachments']))
			$context['valid_basedirectory'] =  !empty($modSettings['basedirectory_for_attachments']) && is_writable($modSettings['basedirectory_for_attachments']);
		else
			$context['valid_basedirectory'] = true;

		// A bit of razzle dazzle with the $txt strings. :)
		$txt['attachment_path'] = $context['attachmentUploadDir'];
		$txt['basedirectory_for_attachments_path']= isset($modSettings['basedirectory_for_attachments']) ? $modSettings['basedirectory_for_attachments'] : '';
		$txt['use_subdirectories_for_attachments_note'] = empty($modSettings['attachment_basedirectories']) || empty($modSettings['use_subdirectories_for_attachments']) ? $txt['use_subdirectories_for_attachments_note'] : '';
		$txt['attachmentUploadDir_multiple_configure'] = '<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">[' . $txt['attachmentUploadDir_multiple_configure'] . ']</a>';
		$txt['attach_current_dir'] = empty($modSettings['automanage_attachments']) ? $txt['attach_current_dir'] : $txt['attach_last_dir'];
		$txt['attach_current_dir_warning'] = $txt['attach_current_dir'] . $txt['attach_current_dir_warning'];
		$txt['basedirectory_for_attachments_warning'] = $txt['basedirectory_for_attachments_current'] . $txt['basedirectory_for_attachments_warning'];

		// Perform a test to see if the GD module or ImageMagick are installed.
		$testImg = get_extension_funcs('gd') || class_exists('Imagick');

		// See if we can find if the server is set up to support the attacment limits
		$post_max_size = ini_get('post_max_size');
		$upload_max_filesize = ini_get('upload_max_filesize');
		$testPM = !empty($post_max_size) ? (memoryReturnBytes($post_max_size) >= (isset($modSettings['attachmentPostLimit']) ? $modSettings['attachmentPostLimit'] * 1024 : 0)) : true;
		$testUM = !empty($upload_max_filesize) ? (memoryReturnBytes($upload_max_filesize) >= (isset($modSettings['attachmentSizeLimit']) ? $modSettings['attachmentSizeLimit'] * 1024 : 0)) : true;

		$config_vars = array(
			array('title', 'attachment_manager_settings'),
				// Are attachments enabled?
				array('select', 'attachmentEnable', array($txt['attachmentEnable_deactivate'], $txt['attachmentEnable_enable_all'], $txt['attachmentEnable_disable_new'])),
			'',
				// Extension checks etc.
				array('check', 'attachmentRecodeLineEndings'),
			'',
				// Directory and size limits.
				array('select', 'automanage_attachments', array(0 => $txt['attachments_normal'], 1 => $txt['attachments_auto_space'], 2 => $txt['attachments_auto_years'], 3 => $txt['attachments_auto_months'], 4 => $txt['attachments_auto_16'])),
				array('check', 'use_subdirectories_for_attachments', 'subtext' => $txt['use_subdirectories_for_attachments_note']),
				(empty($modSettings['attachment_basedirectories']) ? array('text', 'basedirectory_for_attachments', 40,) : array('var_message', 'basedirectory_for_attachments', 'message' => 'basedirectory_for_attachments_path', 'invalid' => empty($context['valid_basedirectory']), 'text_label' => (!empty($context['valid_basedirectory']) ? $txt['basedirectory_for_attachments_current'] : $txt['basedirectory_for_attachments_warning']))),
				empty($modSettings['attachment_basedirectories']) && $modSettings['currentAttachmentUploadDir'] == 1 && count($modSettings['attachmentUploadDir']) == 1	? array('text', 'attachmentUploadDir', 'subtext' => $txt['attachmentUploadDir_multiple_configure'], 40, 'invalid' => !$context['valid_upload_dir']) : array('var_message', 'attach_current_directory', 'subtext' => $txt['attachmentUploadDir_multiple_configure'], 'message' => 'attachment_path', 'invalid' => empty($context['valid_upload_dir']), 'text_label' => (!empty($context['valid_upload_dir']) ? $txt['attach_current_dir'] : $txt['attach_current_dir_warning'])),
				array('int', 'attachmentDirFileLimit', 'subtext' => $txt['zero_for_no_limit'], 6),
				array('int', 'attachmentDirSizeLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
			'',
				// Posting limits
				array('int', 'attachmentPostLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
				array('warning', empty($testPM) ? 'attachment_postsize_warning' : ''),
				array('int', 'attachmentSizeLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
				array('warning', empty($testUM) ? 'attachment_filesize_warning' : ''),
				array('int', 'attachmentNumPerPostLimit', 'subtext' => $txt['zero_for_no_limit'], 6),
				// Security Items
			array('title', 'attachment_security_settings'),
				// Extension checks etc.
				array('check', 'attachmentCheckExtensions'),
				array('text', 'attachmentExtensions', 40),
			'',
				// Image checks.
				array('warning', empty($testImg) ? 'attachment_img_enc_warning' : ''),
				array('check', 'attachment_image_reencode'),
			'',
				array('warning', 'attachment_image_paranoid_warning'),
				array('check', 'attachment_image_paranoid'),
				// Thumbnail settings.
			array('title', 'attachment_thumbnail_settings'),
				array('check', 'attachmentShowImages'),
				array('check', 'attachmentThumbnails'),
				array('check', 'attachment_thumb_png'),
				array('check', 'attachment_thumb_memory', 'subtext' => $txt['attachment_thumb_memory_note1'], 'postinput' => $txt['attachment_thumb_memory_note2']),
				array('warning', 'attachment_thumb_memory_note'),
				array('text', 'attachmentThumbWidth', 6),
				array('text', 'attachmentThumbHeight', 6),
			'',
				array('int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']),
				array('int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']),
		);

		return $this->_attachSettingsForm->settings($config_vars);
	}

	/**
	 * Retrieve and return the administration settings
	 *  for attachments.
	 *  @deprecated
	 */
	public function settings()
	{
		global $modSettings, $txt, $scripturl;

		require_once(SUBSDIR . '/Attachments.subs.php');

		// Get the current attachment directory.
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$context['attachmentUploadDir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

		// First time here?
		if (empty($modSettings['attachment_basedirectories']) && $modSettings['currentAttachmentUploadDir'] == 1 && count($modSettings['attachmentUploadDir']) == 1)
			$modSettings['attachmentUploadDir'] = $modSettings['attachmentUploadDir'][1];

		// If not set, show a default path for the base directory
		if (!isset($_GET['save']) && empty($modSettings['basedirectory_for_attachments']))
			if (is_dir($modSettings['attachmentUploadDir'][1]))
				$modSettings['basedirectory_for_attachments'] = $modSettings['attachmentUploadDir'][1];
			else
				$modSettings['basedirectory_for_attachments'] = $context['attachmentUploadDir'];

		$context['valid_upload_dir'] = is_dir($context['attachmentUploadDir']) && is_writable($context['attachmentUploadDir']);

		if (!empty($modSettings['automanage_attachments']))
			$context['valid_basedirectory'] =  !empty($modSettings['basedirectory_for_attachments']) && is_writable($modSettings['basedirectory_for_attachments']);
		else
			$context['valid_basedirectory'] = true;

		// A bit of razzle dazzle with the $txt strings. :)
		$txt['attachment_path'] = $context['attachmentUploadDir'];
		$txt['basedirectory_for_attachments_path']= isset($modSettings['basedirectory_for_attachments']) ? $modSettings['basedirectory_for_attachments'] : '';
		$txt['use_subdirectories_for_attachments_note'] = empty($modSettings['attachment_basedirectories']) || empty($modSettings['use_subdirectories_for_attachments']) ? $txt['use_subdirectories_for_attachments_note'] : '';
		$txt['attachmentUploadDir_multiple_configure'] = '<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">[' . $txt['attachmentUploadDir_multiple_configure'] . ']</a>';
		$txt['attach_current_dir'] = empty($modSettings['automanage_attachments']) ? $txt['attach_current_dir'] : $txt['attach_last_dir'];
		$txt['attach_current_dir_warning'] = $txt['attach_current_dir'] . $txt['attach_current_dir_warning'];
		$txt['basedirectory_for_attachments_warning'] = $txt['basedirectory_for_attachments_current'] . $txt['basedirectory_for_attachments_warning'];

		// Perform a test to see if the GD module or ImageMagick are installed.
		$testImg = get_extension_funcs('gd') || class_exists('Imagick');

		// See if we can find if the server is set up to support the attacment limits
		$post_max_size = ini_get('post_max_size');
		$upload_max_filesize = ini_get('upload_max_filesize');
		$testPM = !empty($post_max_size) ? (memoryReturnBytes($post_max_size) >= (isset($modSettings['attachmentPostLimit']) ? $modSettings['attachmentPostLimit'] * 1024 : 0)) : true;
		$testUM = !empty($upload_max_filesize) ? (memoryReturnBytes($upload_max_filesize) >= (isset($modSettings['attachmentSizeLimit']) ? $modSettings['attachmentSizeLimit'] * 1024 : 0)) : true;

		$config_vars = array(
			array('title', 'attachment_manager_settings'),
				// Are attachments enabled?
				array('select', 'attachmentEnable', array($txt['attachmentEnable_deactivate'], $txt['attachmentEnable_enable_all'], $txt['attachmentEnable_disable_new'])),
			'',
				// Extension checks etc.
				array('check', 'attachmentRecodeLineEndings'),
			'',
				// Directory and size limits.
				array('select', 'automanage_attachments', array(0 => $txt['attachments_normal'], 1 => $txt['attachments_auto_space'], 2 => $txt['attachments_auto_years'], 3 => $txt['attachments_auto_months'], 4 => $txt['attachments_auto_16'])),
				array('check', 'use_subdirectories_for_attachments', 'subtext' => $txt['use_subdirectories_for_attachments_note']),
				(empty($modSettings['attachment_basedirectories']) ? array('text', 'basedirectory_for_attachments', 40,) : array('var_message', 'basedirectory_for_attachments', 'message' => 'basedirectory_for_attachments_path', 'invalid' => empty($context['valid_basedirectory']), 'text_label' => (!empty($context['valid_basedirectory']) ? $txt['basedirectory_for_attachments_current'] : $txt['basedirectory_for_attachments_warning']))),
				empty($modSettings['attachment_basedirectories']) && $modSettings['currentAttachmentUploadDir'] == 1 && count($modSettings['attachmentUploadDir']) == 1	? array('text', 'attachmentUploadDir', 'subtext' => $txt['attachmentUploadDir_multiple_configure'], 40, 'invalid' => !$context['valid_upload_dir']) : array('var_message', 'attach_current_directory', 'subtext' => $txt['attachmentUploadDir_multiple_configure'], 'message' => 'attachment_path', 'invalid' => empty($context['valid_upload_dir']), 'text_label' => (!empty($context['valid_upload_dir']) ? $txt['attach_current_dir'] : $txt['attach_current_dir_warning'])),
				array('int', 'attachmentDirFileLimit', 'subtext' => $txt['zero_for_no_limit'], 6),
				array('int', 'attachmentDirSizeLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
			'',
				// Posting limits
				array('int', 'attachmentPostLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
				array('warning', empty($testPM) ? 'attachment_postsize_warning' : ''),
				array('int', 'attachmentSizeLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
				array('warning', empty($testUM) ? 'attachment_filesize_warning' : ''),
				array('int', 'attachmentNumPerPostLimit', 'subtext' => $txt['zero_for_no_limit'], 6),
				// Security Items
			array('title', 'attachment_security_settings'),
				// Extension checks etc.
				array('check', 'attachmentCheckExtensions'),
				array('text', 'attachmentExtensions', 40),
			'',
				// Image checks.
				array('warning', empty($testImg) ? 'attachment_img_enc_warning' : ''),
				array('check', 'attachment_image_reencode'),
			'',
				array('warning', 'attachment_image_paranoid_warning'),
				array('check', 'attachment_image_paranoid'),
				// Thumbnail settings.
			array('title', 'attachment_thumbnail_settings'),
				array('check', 'attachmentShowImages'),
				array('check', 'attachmentThumbnails'),
				array('check', 'attachment_thumb_png'),
				array('check', 'attachment_thumb_memory', 'subtext' => $txt['attachment_thumb_memory_note1'], 'postinput' => $txt['attachment_thumb_memory_note2']),
				array('warning', 'attachment_thumb_memory_note'),
				array('text', 'attachmentThumbWidth', 6),
				array('text', 'attachmentThumbHeight', 6),
			'',
				array('int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']),
				array('int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']),
		);

		return $config_vars;
	}

	/**
	 * Show a list of attachment or avatar files.
	 * Called by ?action=admin;area=manageattachments;sa=browse for attachments
	 *  and ?action=admin;area=manageattachments;sa=browse;avatars for avatars.
	 * Allows sorting by name, date, size and member.
	 * Paginates results.
	 *
	 *  @uses the 'browse' sub template
	 */
	public function action_browse()
	{
		global $context, $txt, $scripturl, $modSettings;

		// We're working with them attachments here!
		require_once(SUBSDIR . '/Attachments.subs.php');

		// Attachments or avatars?
		$context['browse_type'] = isset($_REQUEST['avatars']) ? 'avatars' : (isset($_REQUEST['thumbs']) ? 'thumbs' : 'attachments');

		// Set the options for the list component.
		$listOptions = array(
			'id' => 'attach_browse',
			'title' => $txt['attachment_manager_browse_files'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=browse' . ($context['browse_type'] === 'avatars' ? ';avatars' : ($context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
			'default_sort_col' => 'name',
			'no_items_label' => $txt['attachment_manager_' . ($context['browse_type'] === 'avatars' ? 'avatars' : ( $context['browse_type'] === 'thumbs' ? 'thumbs' : 'attachments')) . '_no_entries'],
			'get_items' => array(
				'function' => 'list_getFiles',
				'params' => array(
					$context['browse_type'],
				),
			),
			'get_count' => array(
				'function' => 'list_getNumFiles',
				'params' => array(
					$context['browse_type'],
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['attachment_name'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $modSettings, $context, $scripturl;

							$link = \'<a href="\';

							// In case of a custom avatar URL attachments have a fixed directory.
							if ($rowData[\'attachment_type\'] == 1)
								$link .= sprintf(\'%1$s/%2$s\', $modSettings[\'custom_avatar_url\'], $rowData[\'filename\']);

							// By default avatars are downloaded almost as attachments.
							elseif ($context[\'browse_type\'] == \'avatars\')
								$link .= sprintf(\'%1$s?action=dlattach;type=avatar;attach=%2$d\', $scripturl, $rowData[\'id_attach\']);

							// Normal attachments are always linked to a topic ID.
							else
								$link .= sprintf(\'%1$s?action=dlattach;topic=%2$d.0;attach=%3$d\', $scripturl, $rowData[\'id_topic\'], $rowData[\'id_attach\']);

							$link .= \'"\';

							// Show a popup on click if it\'s a picture and we know its dimensions.
							if (!empty($rowData[\'width\']) && !empty($rowData[\'height\']))
								$link .= sprintf(\' onclick="return reqWin(this.href\' . ($rowData[\'attachment_type\'] == 1 ? \'\' : \' + \\\';image\\\'\') . \', %1$d, %2$d, true);"\', $rowData[\'width\'] + 20, $rowData[\'height\'] + 20);

							$link .= sprintf(\'>%1$s</a>\', preg_replace(\'~&amp;#(\\\\d{1,7}|x[0-9a-fA-F]{1,6});~\', \'&#\\\\1;\', htmlspecialchars($rowData[\'filename\'])));

							// Show the dimensions.
							if (!empty($rowData[\'width\']) && !empty($rowData[\'height\']))
								$link .= sprintf(\' <span class="smalltext">%1$dx%2$d</span>\', $rowData[\'width\'], $rowData[\'height\']);

							return $link;
						'),
					),
					'sort' => array(
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					),
				),
				'filesize' => array(
					'header' => array(
						'value' => $txt['attachment_file_size'],
					),
					'data' => array(
						'function' => create_function('$rowData','
							global $txt;

							return sprintf(\'%1$s%2$s\', round($rowData[\'size\'] / 1024, 2), $txt[\'kilobyte\']);
						'),
					),
					'sort' => array(
						'default' => 'a.size',
						'reverse' => 'a.size DESC',
					),
				),
				'member' => array(
					'header' => array(
						'value' => $context['browse_type'] == 'avatars' ? $txt['attachment_manager_member'] : $txt['posted_by'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl;

							// In case of an attachment, return the poster of the attachment.
							if (empty($rowData[\'id_member\']))
								return htmlspecialchars($rowData[\'poster_name\']);

							// Otherwise it must be an avatar, return the link to the owner of it.
							else
								return sprintf(\'<a href="%1$s?action=profile;u=%2$d">%3$s</a>\', $scripturl, $rowData[\'id_member\'], $rowData[\'poster_name\']);
						'),
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'date' => array(
					'header' => array(
						'value' => $context['browse_type'] == 'avatars' ? $txt['attachment_manager_last_active'] : $txt['date'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt, $context, $scripturl;

							// The date the message containing the attachment was posted or the owner of the avatar was active.
							$date = empty($rowData[\'poster_time\']) ? $txt[\'never\'] : standardTime($rowData[\'poster_time\']);

							// Add a link to the topic in case of an attachment.
							if ($context[\'browse_type\'] !== \'avatars\')
								$date .= sprintf(\'<br />%1$s <a href="%2$s?topic=%3$d.0.msg%4$d#msg%4$d">%5$s</a>\', $txt[\'in\'], $scripturl, $rowData[\'id_topic\'], $rowData[\'id_msg\'], $rowData[\'subject\']);

							return $date;
							'),
					),
					'sort' => array(
						'default' => $context['browse_type'] === 'avatars' ? 'mem.last_login' : 'm.id_msg',
						'reverse' => $context['browse_type'] === 'avatars' ? 'mem.last_login DESC' : 'm.id_msg DESC',
					),
				),
				'downloads' => array(
					'header' => array(
						'value' => $txt['downloads'],
					),
					'data' => array(
						'db' => 'downloads',
						'comma_format' => true,
					),
					'sort' => array(
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="remove[%1$d]" class="input_check" />',
							'params' => array(
								'id_attach' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=manageattachments;sa=remove' . ($context['browse_type'] === 'avatars' ? ';avatars' : ($context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					'type' => $context['browse_type'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="remove_submit" class="button_submit" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['confirm_delete_attachments'] . '\');" />',
				),
			),
			'list_menu' => array(
				'show_on' => 'top',
				'links' => array(
					array(
						'href' => $scripturl . '?action=admin;area=manageattachments;sa=browse',
						'is_selected' => $context['browse_type'] === 'attachments',
						'label' => $txt['attachment_manager_attachments']
					),
					array(
						'href' => $scripturl . '?action=admin;area=manageattachments;sa=browse;avatars',
						'is_selected' => $context['browse_type'] === 'avatars',
						'label' => $txt['attachment_manager_avatars']
					),
					array(
						'href' => $scripturl . '?action=admin;area=manageattachments;sa=browse;thumbs',
						'is_selected' => $context['browse_type'] === 'thumbs',
						'label' => $txt['attachment_manager_thumbs']
					),
				),
			),
		);

		// Create the list.
		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);
	}

	/**
	 * Show several file maintenance options.
	 * Called by ?action=admin;area=manageattachments;sa=maintain.
	 * Calculates file statistics (total file size, number of attachments,
	 * number of avatars, attachment space available).
	 *
	 * @uses the 'maintain' sub template.
	 */
	public function action_maintenance()
	{
		global $context, $modSettings;

		$context['sub_template'] = 'maintenance';

		// We're working with them attachments here!
		require_once(SUBSDIR . '/Attachments.subs.php');
		require_once(SUBSDIR . '/Attachments.subs.php');

		// we need our attachments directories...
		$attach_dirs = getAttachmentDirs();

		// Get the number of attachments...
		$context['num_attachments'] = comma_format(getAttachmentCount(), 0);

		// Also get the avatar amount...
		$context['num_avatars'] = comma_format(getAvatarCount(), 0);

		// Total size of attachments
		$context['attachment_total_size'] = overallAttachmentsSize();

		// Total size and files from the current attachments dir.
		$current_dir = currentAttachDirProperties();

		// If they specified a limit only....
		if (!empty($modSettings['attachmentDirSizeLimit']))
			$context['attachment_space'] = comma_format(max($modSettings['attachmentDirSizeLimit'] - $current_dir['size'], 0), 2);
		$context['attachment_current_size'] = comma_format($current_dir['size'], 2);

		if (!empty($modSettings['attachmentDirFileLimit']))
			$context['attachment_files'] = comma_format(max($modSettings['attachmentDirFileLimit'] - $current_dir['files'], 0), 0);
		$context['attachment_current_files'] = comma_format($current_dir['files'], 0);

		$context['attach_multiple_dirs'] = count($attach_dirs) > 1 ? true : false;
		$context['attach_dirs'] = $attach_dirs;
		$context['base_dirs'] = !empty($modSettings['attachment_basedirectories']) ? unserialize($modSettings['attachment_basedirectories']) : array();
		$context['checked'] = isset($_SESSION['checked']) ? $_SESSION['checked'] : true;
		if (!empty($_SESSION['results']))
		{
			$context['results'] = implode('<br />', $_SESSION['results']);
			unset($_SESSION['results']);
		}
	}

	/**
	 * Move avatars from their current location, to the custom_avatar_dir folder.
	 * Called from the maintenance screen by ?action=admin;area=manageattachments;sa=action_moveAvatars.
	 */
	public function action_moveAvatars()
	{
		global $modSettings;

		// First make sure the custom avatar dir is writable.
		if (!is_writable($modSettings['custom_avatar_dir']))
		{
			// Try to fix it.
			@chmod($modSettings['custom_avatar_dir'], 0777);

			// Guess that didn't work?
			if (!is_writable($modSettings['custom_avatar_dir']))
				fatal_lang_error('attachments_no_write', 'critical');
		}

		// Finally move the attachments..
		require_once(SUBSDIR . '/Attachments.subs.php');
		moveAvatars();

		redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * Remove attachments older than a given age.
	 * Called from the maintenance screen by
	 *   ?action=admin;area=manageattachments;sa=byAge.
	 * It optionally adds a certain text to the messages the attachments
	 *  were removed from.
	 *  @todo refactor this silly superglobals use...
	 */
	public function action_byAge()
	{
		checkSession('post', 'admin');

		// @todo Ignore messages in topics that are stickied?

		// someone has to do the dirty work
		require_once(SUBSDIR . '/Attachments.subs.php');
		require_once(SUBSDIR . '/Attachments.subs.php');

		// Deleting an attachment?
		if ($_REQUEST['type'] != 'avatars')
		{
			// Get rid of all the old attachments.
			$messages = removeAttachments(array('attachment_type' => 0, 'poster_time' => (time() - 24 * 60 * 60 * $_POST['age'])), 'messages', true);

			// Update the messages to reflect the change.
			if (!empty($messages) && !empty($_POST['notice']))
				setRemovalNotice($messages, $_POST['notice']);
		}
		else
		{
			// Remove all the old avatars.
			removeAttachments(array('not_id_member' => 0, 'last_login' => (time() - 24 * 60 * 60 * $_POST['age'])), 'members');
		}
		redirectexit('action=admin;area=manageattachments' . (empty($_REQUEST['avatars']) ? ';sa=maintenance' : ';avatars'));
	}

	/**
	 * Remove attachments larger than a given size.
	 * Called from the maintenance screen by
	 *  ?action=admin;area=manageattachments;sa=bySize.
	 * Optionally adds a certain text to the messages the attachments were
	 * 	removed from.
	 */
	public function action_bySize()
	{
		checkSession('post', 'admin');

		// we'll need this
		require_once(SUBSDIR . '/Attachments.subs.php');
		require_once(SUBSDIR . '/Attachments.subs.php');

		// Find humungous attachments.
		$messages = removeAttachments(array('attachment_type' => 0, 'size' => 1024 * $_POST['size']), 'messages', true);

		// And make a note on the post.
		if (!empty($messages) && !empty($_POST['notice']))
			setRemovalNotice($messages, $_POST['notice']);

		redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * Remove a selection of attachments or avatars.
	 * Called from the browse screen as submitted form by
	 *  ?action=admin;area=manageattachments;sa=remove
	 */
	public function action_remove()
	{
		global $txt, $language, $user_info;

		checkSession('post');

		if (!empty($_POST['remove']))
		{
			// we'll need this
			require_once(SUBSDIR . '/Attachments.subs.php');
			require_once(SUBSDIR . '/Attachments.subs.php');

			$attachments = array();
			// There must be a quicker way to pass this safety test??
			foreach ($_POST['remove'] as $removeID => $dummy)
				$attachments[] = (int) $removeID;

			if ($_REQUEST['type'] == 'avatars' && !empty($attachments))
				removeAttachments(array('id_attach' => $attachments));
			else if (!empty($attachments))
			{
				$messages = removeAttachments(array('id_attach' => $attachments), 'messages', true);

				// And change the message to reflect this.
				if (!empty($messages))
				{
					loadLanguage('index', $language, true);
					setRemovalNotice($messages, $txt['attachment_delete_admin']);
					loadLanguage('index', $user_info['language'], true);
				}
			}
		}

		$_GET['sort'] = isset($_GET['sort']) ? $_GET['sort'] : 'date';
		redirectexit('action=admin;area=manageattachments;sa=browse;' . $_REQUEST['type'] . ';sort=' . $_GET['sort'] . (isset($_GET['desc']) ? ';desc' : '') . ';start=' . $_REQUEST['start']);
	}

	/**
	 * Removes all attachments in a single click
	 * Called from the maintenance screen by
	 *  ?action=admin;area=manageattachments;sa=removeall.
	 */
	public function action_removeall()
	{
		global $txt;

		checkSession('get', 'admin');

		// lots of work to do
		require_once(SUBSDIR . '/Attachments.subs.php');

		$messages = removeAttachments(array('attachment_type' => 0), '', true);

		$notice = isset($_POST['notice']) ? $_POST['notice'] : $txt['attachment_delete_admin'];

		// Add the notice on the end of the changed messages.
		if (!empty($messages))
			setRemovalNotice($messages, $notice);

		redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * This function should find attachments in the database that no longer exist and clear them, and fix filesize issues.
	 * @todo Move db queries to ManageAttachments.subs.php
	 */
	public function action_repair()
	{
		global $modSettings, $context, $txt;

		$db = database();

		checkSession('get');

		// If we choose cancel, redirect right back.
		if (isset($_POST['cancel']))
			redirectexit('action=admin;area=manageattachments;sa=maintenance');

		// Try give us a while to sort this out...
		@set_time_limit(600);

		$_GET['step'] = empty($_GET['step']) ? 0 : (int) $_GET['step'];
		$context['starting_substep'] = $_GET['substep'] = empty($_GET['substep']) ? 0 : (int) $_GET['substep'];

		// Don't recall the session just in case.
		if ($_GET['step'] == 0 && $_GET['substep'] == 0)
		{
			unset($_SESSION['attachments_to_fix'], $_SESSION['attachments_to_fix2']);

			// If we're actually fixing stuff - work out what.
			if (isset($_GET['fixErrors']))
			{
				// Nothing?
				if (empty($_POST['to_fix']))
					redirectexit('action=admin;area=manageattachments;sa=maintenance');

				$_SESSION['attachments_to_fix'] = array();
				// @todo No need to do this I think.
				foreach ($_POST['to_fix'] as $key => $value)
					$_SESSION['attachments_to_fix'][] = $value;
			}
		}

		// We will work hard with attachments.
		require_once(SUBSDIR . '/Attachments.subs.php');

		// All the valid problems are here:
		$context['repair_errors'] = array(
			'missing_thumbnail_parent' => 0,
			'parent_missing_thumbnail' => 0,
			'file_missing_on_disk' => 0,
			'file_wrong_size' => 0,
			'file_size_of_zero' => 0,
			'attachment_no_msg' => 0,
			'avatar_no_member' => 0,
			'wrong_folder' => 0,
			'files_without_attachment' => 0,
		);

		$to_fix = !empty($_SESSION['attachments_to_fix']) ? $_SESSION['attachments_to_fix'] : array();
		$context['repair_errors'] = isset($_SESSION['attachments_to_fix2']) ? $_SESSION['attachments_to_fix2'] : $context['repair_errors'];
		$fix_errors = isset($_GET['fixErrors']) ? true : false;

		// Get stranded thumbnails.
		if ($_GET['step'] <= 0)
		{
			$result = $db->query('', '
				SELECT MAX(id_attach)
				FROM {db_prefix}attachments
				WHERE attachment_type = {int:thumbnail}',
				array(
					'thumbnail' => 3,
				)
			);
			list ($thumbnails) = $db->fetch_row($result);
			$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
			{
				$to_remove = array();

				$result = $db->query('', '
					SELECT thumb.id_attach, thumb.id_folder, thumb.filename, thumb.file_hash
					FROM {db_prefix}attachments AS thumb
						LEFT JOIN {db_prefix}attachments AS tparent ON (tparent.id_thumb = thumb.id_attach)
					WHERE thumb.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND thumb.attachment_type = {int:thumbnail}
						AND tparent.id_attach IS NULL',
					array(
						'thumbnail' => 3,
						'substep' => $_GET['substep'],
					)
				);
				while ($row = $db->fetch_assoc($result))
				{
					// Only do anything once... just in case
					if (!isset($to_remove[$row['id_attach']]))
					{
						$to_remove[$row['id_attach']] = $row['id_attach'];
						$context['repair_errors']['missing_thumbnail_parent']++;

						// If we are repairing remove the file from disk now.
						if ($fix_errors && in_array('missing_thumbnail_parent', $to_fix))
						{
							$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
							@unlink($filename);
						}
					}
				}
				if ($db->num_rows($result) != 0)
					$to_fix[] = 'missing_thumbnail_parent';
				$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove) && in_array('missing_thumbnail_parent', $to_fix))
					$db->query('', '
						DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})
							AND attachment_type = {int:attachment_type}',
						array(
							'to_remove' => $to_remove,
							'attachment_type' => 3,
						)
					);

				pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 1;
			$_GET['substep'] = 0;
			pauseAttachmentMaintenance($to_fix);
		}

		// Find parents which think they have thumbnails, but actually, don't.
		if ($_GET['step'] <= 1)
		{
			$thumbnails = maxThumbnails();

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
			{
				$to_update = array();

				$result = $db->query('', '
					SELECT a.id_attach
					FROM {db_prefix}attachments AS a
						LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
					WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND a.id_thumb != {int:no_thumb}
						AND thumb.id_attach IS NULL',
					array(
						'no_thumb' => 0,
						'substep' => $_GET['substep'],
					)
				);
				while ($row = $db->fetch_assoc($result))
				{
					$to_update[] = $row['id_attach'];
					$context['repair_errors']['parent_missing_thumbnail']++;
				}
				if ($db->num_rows($result) != 0)
					$to_fix[] = 'parent_missing_thumbnail';
				$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_update) && in_array('parent_missing_thumbnail', $to_fix))
					$db->query('', '
						UPDATE {db_prefix}attachments
						SET id_thumb = {int:no_thumb}
						WHERE id_attach IN ({array_int:to_update})',
						array(
							'to_update' => $to_update,
							'no_thumb' => 0,
						)
					);

				pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 2;
			$_GET['substep'] = 0;
			pauseAttachmentMaintenance($to_fix);
		}

		// This may take forever I'm afraid, but life sucks... recount EVERY attachments!
		if ($_GET['step'] <= 2)
		{
			$result = $db->query('', '
				SELECT MAX(id_attach)
				FROM {db_prefix}attachments',
				array(
				)
			);
			list ($thumbnails) = $db->fetch_row($result);
			$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 250)
			{
				$to_remove = array();
				$errors_found = array();

				$result = $db->query('', '
					SELECT id_attach, id_folder, filename, file_hash, size, attachment_type
					FROM {db_prefix}attachments
					WHERE id_attach BETWEEN {int:substep} AND {int:substep} + 249',
					array(
						'substep' => $_GET['substep'],
					)
				);
				while ($row = $db->fetch_assoc($result))
				{
					// Get the filename.
					if ($row['attachment_type'] == 1)
						$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
					else
						$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

					// File doesn't exist?
					if (!file_exists($filename))
					{
						// If we're lucky it might just be in a different folder.
						if (!empty($modSettings['currentAttachmentUploadDir']))
						{
							// Get the attachment name with out the folder.
							$attachment_name = !empty($row['file_hash']) ? $row['id_attach'] . '_' . $row['file_hash'] : getLegacyAttachmentFilename($row['filename'], $row['id_attach'], null, true);

							if (!is_array($modSettings['attachmentUploadDir']))
								$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

							// Loop through the other folders.
							foreach ($modSettings['attachmentUploadDir'] as $id => $dir)
								if (file_exists($dir . '/' . $attachment_name))
								{
									$context['repair_errors']['wrong_folder']++;
									$errors_found[] = 'wrong_folder';

									// Are we going to fix this now?
									if ($fix_errors && in_array('wrong_folder', $to_fix))
										attachment_folder($row['id_attach'], $id);

									continue 2;
								}
						}

						$to_remove[] = $row['id_attach'];
						$context['repair_errors']['file_missing_on_disk']++;
						$errors_found[] = 'file_missing_on_disk';
					}
					elseif (filesize($filename) == 0)
					{
						$context['repair_errors']['file_size_of_zero']++;
						$errors_found[] = 'file_size_of_zero';

						// Fixing?
						if ($fix_errors && in_array('file_size_of_zero', $to_fix))
						{
							$to_remove[] = $row['id_attach'];
							@unlink($filename);
						}
					}
					elseif (filesize($filename) != $row['size'])
					{
						$context['repair_errors']['file_wrong_size']++;
						$errors_found[] = 'file_wrong_size';

						// Fix it here?
						if ($fix_errors && in_array('file_wrong_size', $to_fix))
							attachment_filesize($row['id_attach'], filesize($filename));
					}
				}

				if (in_array('file_missing_on_disk', $errors_found))
					$to_fix[] = 'file_missing_on_disk';
				if (in_array('file_size_of_zero', $errors_found))
					$to_fix[] = 'file_size_of_zero';
				if (in_array('file_wrong_size', $errors_found))
					$to_fix[] = 'file_wrong_size';
				if (in_array('wrong_folder', $errors_found))
					$to_fix[] = 'wrong_folder';
				$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove))
					removeOrphanAttachments($to_remove);

				pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 3;
			$_GET['substep'] = 0;
			pauseAttachmentMaintenance($to_fix);
		}

		// Get avatars with no members associated with them.
		if ($_GET['step'] <= 3)
		{
			$result = $db->query('', '
				SELECT MAX(id_attach)
				FROM {db_prefix}attachments',
				array(
				)
			);
			list ($thumbnails) = $db->fetch_row($result);
			$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
			{
				$to_remove = array();

				$result = $db->query('', '
					SELECT a.id_attach, a.id_folder, a.filename, a.file_hash, a.attachment_type
					FROM {db_prefix}attachments AS a
						LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
					WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND a.id_member != {int:no_member}
						AND a.id_msg = {int:no_msg}
						AND mem.id_member IS NULL',
					array(
						'no_member' => 0,
						'no_msg' => 0,
						'substep' => $_GET['substep'],
					)
				);
				while ($row = $db->fetch_assoc($result))
				{
					$to_remove[] = $row['id_attach'];
					$context['repair_errors']['avatar_no_member']++;

					// If we are repairing remove the file from disk now.
					if ($fix_errors && in_array('avatar_no_member', $to_fix))
					{
						if ($row['attachment_type'] == 1)
							$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
						else
							$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
						@unlink($filename);
					}
				}
				if ($db->num_rows($result) != 0)
					$to_fix[] = 'avatar_no_member';
				$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove) && in_array('avatar_no_member', $to_fix))
					$db->query('', '
						DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})
							AND id_member != {int:no_member}
							AND id_msg = {int:no_msg}',
						array(
							'to_remove' => $to_remove,
							'no_member' => 0,
							'no_msg' => 0,
						)
					);

				pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 4;
			$_GET['substep'] = 0;
			pauseAttachmentMaintenance($to_fix);
		}

		// What about attachments, who are missing a message :'(
		if ($_GET['step'] <= 4)
		{
			$result = $db->query('', '
				SELECT MAX(id_attach)
				FROM {db_prefix}attachments',
				array(
				)
			);
			list ($thumbnails) = $db->fetch_row($result);
			$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
			{
				$to_remove = array();

				$result = $db->query('', '
					SELECT a.id_attach, a.id_folder, a.filename, a.file_hash
					FROM {db_prefix}attachments AS a
						LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
					WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND a.id_member = {int:no_member}
						AND a.id_msg != {int:no_msg}
						AND m.id_msg IS NULL',
					array(
						'no_member' => 0,
						'no_msg' => 0,
						'substep' => $_GET['substep'],
					)
				);
				while ($row = $db->fetch_assoc($result))
				{
					$to_remove[] = $row['id_attach'];
					$context['repair_errors']['attachment_no_msg']++;

					// If we are repairing remove the file from disk now.
					if ($fix_errors && in_array('attachment_no_msg', $to_fix))
					{
						$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
						@unlink($filename);
					}
				}
				if ($db->num_rows($result) != 0)
					$to_fix[] = 'attachment_no_msg';
				$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove) && in_array('attachment_no_msg', $to_fix))
					$db->query('', '
						DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})
							AND id_member = {int:no_member}
							AND id_msg != {int:no_msg}',
						array(
							'to_remove' => $to_remove,
							'no_member' => 0,
							'no_msg' => 0,
						)
					);

				pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 5;
			$_GET['substep'] = 0;
			pauseAttachmentMaintenance($to_fix);
		}

		// What about files who are not recorded in the database?
		if ($_GET['step'] <= 5)
		{
			// Just use the current path for temp files.
			if (!is_array($modSettings['attachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
			$attach_dirs = $modSettings['attachmentUploadDir'];

			$current_check = 0;
			$max_checks = 500;
			$files_checked = empty($_GET['substep']) ? 0 : $_GET['substep'];
			foreach ($attach_dirs as $attach_dir)
			{
				if ($dir = @opendir($attach_dir))
				{
					while ($file = readdir($dir))
					{
						if ($file == '.' || $file == '..')
							continue;

						if ($files_checked <= $current_check)
						{
							// Temporary file, get rid of it!
							if (strpos($file, 'post_tmp_') !== false)
							{
								// Temp file is more than 5 hours old!
								if (filemtime($attach_dir . '/' . $file) < time() - 18000)
									@unlink($attach_dir . '/' . $file);
							}
							// That should be an attachment, let's check if we have it in the database
							elseif (strpos($file, '_') !== false)
							{
								$attachID = (int) substr($file, 0, strpos($file, '_'));
								if (!empty($attachID))
								{
									$request = $db->query('', '
										SELECT  id_attach
										FROM {db_prefix}attachments
										WHERE id_attach = {int:attachment_id}
										LIMIT 1',
										array(
											'attachment_id' => $attachID,
										)
									);
									if ($db->num_rows($request) == 0)
									{
										if ($fix_errors && in_array('files_without_attachment', $to_fix))
										{
											@unlink($attach_dir . '/' . $file);
										}
										else
										{
											$context['repair_errors']['files_without_attachment']++;
											$to_fix[] = 'files_without_attachment';
										}
									}
									$db->free_result($request);
								}
							}
							elseif ($file != 'index.php')
							{
								if ($fix_errors && in_array('files_without_attachment', $to_fix))
								{
									@unlink($attach_dir . '/' . $file);
								}
								else
								{
									$context['repair_errors']['files_without_attachment']++;
									$to_fix[] = 'files_without_attachment';
								}
							}
						}
						$current_check++;
						$_GET['substep'] = $current_check;
						if ($current_check - $files_checked >= $max_checks)
							pauseAttachmentMaintenance($to_fix);
					}
					closedir($dir);
				}
			}

			$_GET['step'] = 5;
			$_GET['substep'] = 0;
			pauseAttachmentMaintenance($to_fix);
		}

		// Got here we must be doing well - just the template! :D
		$context['page_title'] = $txt['repair_attachments'];
		$context[$context['admin_menu_name']]['current_subsection'] = 'maintenance';
		$context['sub_template'] = 'attachment_repair';

		// What stage are we at?
		$context['completed'] = $fix_errors ? true : false;
		$context['errors_found'] = !empty($to_fix) ? true : false;

	}

	/**
	 * This function lists and allows updating of multiple attachments paths.
	 * @todo Move db queries to ManageAttachments.subs.php
	 */
	public function action_attachpaths()
	{
		global $modSettings, $scripturl, $context, $txt;

		$db = database();

		require_once(SUBSDIR . '/Attachments.subs.php');

		// Since this needs to be done eventually.
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		if (!isset($modSettings['attachment_basedirectories']))
			$modSettings['attachment_basedirectories'] = array();
		elseif (!is_array($modSettings['attachment_basedirectories']))
			$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);

		$errors = array();

		// Saving?
		if (isset($_REQUEST['save']))
		{
			checkSession();

			$_POST['current_dir'] = isset($_POST['current_dir']) ? (int) $_POST['current_dir'] : 0;
			$new_dirs = array();
			foreach ($_POST['dirs'] as $id => $path)
			{
				$error = '';
				$id = (int) $id;
				if ($id < 1)
					continue;

				// Hmm, a new path maybe?
				if (!array_key_exists($id, $modSettings['attachmentUploadDir']))
				{
					// or is it?
					if (in_array($path, $modSettings['attachmentUploadDir']) || in_array(BOARDDIR . DIRECTORY_SEPARATOR . $path, $modSettings['attachmentUploadDir']))
					{
							$errors[] = $path . ': ' . $txt['attach_dir_duplicate_msg'];
							continue;
					}

					// OK, so let's try to create it then.
					require_once(SUBSDIR . '/Attachments.subs.php');
					if (automanage_attachments_create_directory($path))
						$_POST['current_dir'] = $modSettings['currentAttachmentUploadDir'];
					else
						$errors[] =  $path . ': ' . $txt[$context['dir_creation_error']];
				}

				// Changing a directory name?
				if (!empty($modSettings['attachmentUploadDir'][$id]) && !empty($path) && $path != $modSettings['attachmentUploadDir'][$id])
				{
					if ($path != $modSettings['attachmentUploadDir'][$id] && !is_dir($path))
					{
						if (!@rename($modSettings['attachmentUploadDir'][$id], $path))
						{
							$errors[] = $path . ': ' . $txt['attach_dir_no_rename'];
							$path = $modSettings['attachmentUploadDir'][$id];
						}
					}
					else
					{
						$errors[] = $path . ': ' . $txt['attach_dir_exists_msg'];
						$path = $modSettings['attachmentUploadDir'][$id];
					}

					// Update the base directory path
					if (!empty($modSettings['attachment_basedirectories']) && array_key_exists($id, $modSettings['attachment_basedirectories']))
					{
						$base = $modSettings['basedirectory_for_attachments'] == $modSettings['attachmentUploadDir'][$id] ? $path : $modSettings['basedirectory_for_attachments'];

						$modSettings['attachment_basedirectories'][$id] = $path;
						updateSettings(array(
							'attachment_basedirectories' => serialize($modSettings['attachment_basedirectories']),
							'basedirectory_for_attachments' => $base,
						));
						$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);
					}
				}

				if (empty($path))
				{
					$path = $modSettings['attachmentUploadDir'][$id];

					// It's not a good idea to delete the current directory.
					if ($id == (!empty($_POST['current_dir']) ? $_POST['current_dir'] : $modSettings['currentAttachmentUploadDir']))
						$errors[] = $path . ': ' . $txt['attach_dir_is_current'];
					// Or the current base directory
					elseif (!empty($modSettings['basedirectory_for_attachments']) && $modSettings['basedirectory_for_attachments'] == $modSettings['attachmentUploadDir'][$id])
						$errors[] = $path . ': ' . $txt['attach_dir_is_current_bd'];
					else
					{
						// Let's not try to delete a path with files in it.
						$request = $db->query('', '
							SELECT COUNT(id_attach) AS num_attach
							FROM {db_prefix}attachments
							WHERE id_folder = {int:id_folder}',
							array(
								'id_folder' => (int) $id,
							)
						);

						list ($num_attach) = $db->fetch_row($request);
						$db->free_result($request);

						// A check to see if it's a used base dir.
						if (!empty($modSettings['attachment_basedirectories']))
						{
							// Count any sub-folders.
							foreach ($modSettings['attachmentUploadDir'] as $sub)
								if (strpos($sub, $path . DIRECTORY_SEPARATOR) !== false)
									$num_attach++;
						}

						// It's safe to delete. So try to delete the folder also
						if ($num_attach == 0)
						{
							if (is_dir($path))
								$doit = true;
							elseif (is_dir(BOARDDIR . DIRECTORY_SEPARATOR . $path))
							{
								$doit = true;
								$path = BOARDDIR . DIRECTORY_SEPARATOR . $path;
							}

							if (isset($doit))
							{
								unlink($path . '/.htaccess');
								unlink($path . '/index.php');
								if (!@rmdir($path))
									$error = $path . ': ' . $txt['attach_dir_no_delete'];
							}

							// Remove it from the base directory list.
							if (empty($error) && !empty($modSettings['attachment_basedirectories']))
							{
								unset($modSettings['attachment_basedirectories'][$id]);
								updateSettings(array('attachment_basedirectories' => serialize($modSettings['attachment_basedirectories'])));
								$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);
							}
						}
						else
							$error = $path . ': ' . $txt['attach_dir_no_remove'];

						if (empty($error))
							continue;
						else
							$errors[] = $error;
					}
				}

				$new_dirs[$id] = $path;
			}

			// We need to make sure the current directory is right.
			if (empty($_POST['current_dir']) && !empty($modSettings['currentAttachmentUploadDir']))
				$_POST['current_dir'] = $modSettings['currentAttachmentUploadDir'];

			// Find the current directory if there's no value carried,
			if (empty($_POST['current_dir']) || empty($new_dirs[$_POST['current_dir']]))
			{
				if (array_key_exists($modSettings['currentAttachmentUploadDir'], $modSettings['attachmentUploadDir']))
					$_POST['current_dir'] = $modSettings['currentAttachmentUploadDir'];
				else
					$_POST['current_dir'] = max(array_keys($modSettings['attachmentUploadDir']));
			}

			// If the user wishes to go back, update the last_dir array
			if ($_POST['current_dir'] !=  $modSettings['currentAttachmentUploadDir']&& !empty($modSettings['last_attachments_directory']) && (isset($modSettings['last_attachments_directory'][$_POST['current_dir']]) || isset($modSettings['last_attachments_directory'][0])))
			{
				if (!is_array($modSettings['last_attachments_directory']))
					$modSettings['last_attachments_directory'] = unserialize($modSettings['last_attachments_directory']);
				$num = substr(strrchr($modSettings['attachmentUploadDir'][$_POST['current_dir']], '_'), 1);

				if (is_numeric($num))
				{
					// Need to find the base folder.
					$bid = -1;
					$use_subdirectories_for_attachments = 0;
					if (!empty($modSettings['attachment_basedirectories']))
						foreach ($modSettings['attachment_basedirectories'] as $bid => $base)
							if (strpos($modSettings['attachmentUploadDir'][$_POST['current_dir']], $base . DIRECTORY_SEPARATOR) !==false)
							{
								$use_subdirectories_for_attachments = 1;
								break;
							}

					if ($use_subdirectories_for_attachments == 0 && strpos($modSettings['attachmentUploadDir'][$_POST['current_dir']], BOARDDIR . DIRECTORY_SEPARATOR) !== false)
						$bid = 0;

					$modSettings['last_attachments_directory'][$bid] = (int) $num;
					$modSettings['basedirectory_for_attachments'] = !empty($modSettings['basedirectory_for_attachments']) ? $modSettings['basedirectory_for_attachments'] : '';
					$modSettings['use_subdirectories_for_attachments'] = !empty($modSettings['use_subdirectories_for_attachments']) ? $modSettings['use_subdirectories_for_attachments'] : 0;
					updateSettings(array(
						'last_attachments_directory' => serialize($modSettings['last_attachments_directory']),
						'basedirectory_for_attachments' => $bid == 0 ? $modSettings['basedirectory_for_attachments'] : $modSettings['attachment_basedirectories'][$bid],
						'use_subdirectories_for_attachments' => $use_subdirectories_for_attachments,
					));
				}
			}

			// Going back to just one path?
			if (count($new_dirs) == 1)
			{
				// We might need to reset the paths. This loop will just loop through once.
				foreach ($new_dirs as $id => $dir)
				{
					if ($id != 1)
						$db->query('', '
							UPDATE {db_prefix}attachments
							SET id_folder = {int:default_folder}
							WHERE id_folder = {int:current_folder}',
							array(
								'default_folder' => 1,
								'current_folder' => $id,
							)
						);

					$update = array(
						'currentAttachmentUploadDir' => 1,
						'attachmentUploadDir' => serialize(array(1 => $dir)),
					);
				}
			}
			else
			{
				// Save it to the database.
				$update = array(
					'currentAttachmentUploadDir' => $_POST['current_dir'],
					'attachmentUploadDir' => serialize($new_dirs),
				);
			}

			if (!empty($update))
				updateSettings($update);

			if (!empty($errors))
				$_SESSION['errors']['dir'] = $errors;

			redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Saving a base directory?
		if (isset($_REQUEST['save2']))
		{
			checkSession();

			// Changing the current base directory?
			$_POST['current_base_dir'] = (int) $_POST['current_base_dir'];
			if (empty($_POST['new_base_dir']) && !empty($_POST['current_base_dir']))
			{
				if ($modSettings['basedirectory_for_attachments'] != $modSettings['attachmentUploadDir'][$_POST['current_base_dir']])
					$update = (array(
						'basedirectory_for_attachments' => $modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
					));

				//$modSettings['attachmentUploadDir'] = serialize($modSettings['attachmentUploadDir']);
			}

			If (isset($_POST['base_dir']))
			{
				foreach ($_POST['base_dir'] as $id => $dir)
				{
					if (!empty($dir) && $dir != $modSettings['attachmentUploadDir'][$id])
					{
						if (@rename($modSettings['attachmentUploadDir'][$id], $dir))
						{
							$modSettings['attachmentUploadDir'][$id] = $dir;
							$modSettings['attachment_basedirectories'][$id] = $dir;
							$update = (array(
								'attachmentUploadDir' => serialize($modSettings['attachmentUploadDir']),
								'attachment_basedirectories' => serialize($modSettings['attachment_basedirectories']),
								'basedirectory_for_attachments' => $modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
							));
						}
					}

					if (empty($dir))
					{
						if ($id == $_POST['current_base_dir'])
						{
							$errors[] = $modSettings['attachmentUploadDir'][$id] . ': ' . $txt['attach_dir_is_current'];
							continue;
						}

						unset($modSettings['attachment_basedirectories'][$id]);
						$update = (array(
							'attachment_basedirectories' => serialize($modSettings['attachment_basedirectories']),
							'basedirectory_for_attachments' => $modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
						));
					}
				}
			}

			// Or adding a new one?
			if (!empty($_POST['new_base_dir']))
			{
				require_once(SUBSDIR . '/Attachments.subs.php');
				$_POST['new_base_dir'] = htmlspecialchars($_POST['new_base_dir'], ENT_QUOTES);

				$current_dir = $modSettings['currentAttachmentUploadDir'];

				if (!in_array($_POST['new_base_dir'], $modSettings['attachmentUploadDir']))
				{
					if (!automanage_attachments_create_directory($_POST['new_base_dir']))
						$errors[] = $_POST['new_base_dir'] . ': ' . $txt['attach_dir_base_no_create'];
				}

				$modSettings['currentAttachmentUploadDir'] = array_search($_POST['new_base_dir'], $modSettings['attachmentUploadDir']);
				if (!in_array($_POST['new_base_dir'], $modSettings['attachment_basedirectories']))
					$modSettings['attachment_basedirectories'][$modSettings['currentAttachmentUploadDir']] = $_POST['new_base_dir'];
				ksort($modSettings['attachment_basedirectories']);

				$update = (array(
					'attachment_basedirectories' => serialize($modSettings['attachment_basedirectories']),
					'basedirectory_for_attachments' => $_POST['new_base_dir'],
					'currentAttachmentUploadDir' => $current_dir,
				));
			}

			if (!empty($errors))
				$_SESSION['errors']['base'] = $errors;

			if (!empty($update))
				updateSettings($update);

			redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id']);
		}

		if (isset($_SESSION['errors']))
		{
			if (is_array($_SESSION['errors']))
			{
				$errors = array();
				if (!empty($_SESSION['errors']['dir']))
					foreach ($_SESSION['errors']['dir'] as $error)
						$errors['dir'][] = Util::htmlspecialchars($error, ENT_QUOTES);

				if (!empty($_SESSION['errors']['base']))
					foreach ($_SESSION['errors']['base'] as $error)
						$errors['base'][] = Util::htmlspecialchars($error, ENT_QUOTES);
			}
			unset($_SESSION['errors']);
		}

		$listOptions = array(
			'id' => 'attach_paths',
			'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
			'title' => $txt['attach_paths'],
			'get_items' => array(
				'function' => 'list_getAttachDirs',
			),
			'columns' => array(
				'current_dir' => array(
					'header' => array(
						'value' => $txt['attach_current'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return \'<input type="radio" name="current_dir" value="\' . $rowData[\'id\'] . \'" \' . ($rowData[\'current\'] ? \' checked="checked"\' : \'\') . (!empty($rowData[\'disable_current\']) ? \' disabled="disabled"\' : \'\') . \' class="input_radio" />\';
						'),
						'style' => 'width: 10%;',
						'class' => 'centertext',
					),
				),
				'path' => array(
					'header' => array(
						'value' => $txt['attach_path'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return \'<input type="hidden" name="dirs[\' . $rowData[\'id\'] . \']" value="\' . $rowData[\'path\'] . \'" /><input type="text" size="40" name="dirs[\' . $rowData[\'id\'] . \']" value="\' . $rowData[\'path\'] . \'"\' . (!empty($rowData[\'disable_base_dir\']) ? \' disabled="disabled"\' : \'\') . \' class="input_text" style="width: 100%" />\';
						'),
						'style' => 'width: 40%;',
					),
				),
				'current_size' => array(
					'header' => array(
						'value' => $txt['attach_current_size'],
					),
					'data' => array(
						'db' => 'current_size',
						'style' => 'width: 15%;',
					),
				),
				'num_files' => array(
					'header' => array(
						'value' => $txt['attach_num_files'],
					),
					'data' => array(
						'db' => 'num_files',
						'style' => 'width: 15%;',
					),
				),
				'status' => array(
					'header' => array(
						'value' => $txt['attach_dir_status'],
						'class' => 'centertext',
					),
					'data' => array(
						'db' => 'status',
						'style' => 'width: 25%;',
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />
					<input type="submit" name="new_path" value="' . $txt['attach_add_path'] . '" class="button_submit" />',
				),
				empty($errors['dir']) ? array(
					'position' => 'top_of_list',
					'value' => $txt['attach_dir_desc'],
					'style' => 'padding: 5px 10px;',
					'class' => 'windowbg2 smalltext'
				) : array(
					'position' => 'top_of_list',
					'value' => $txt['attach_dir_save_problem'] . '<br />' . implode('<br />', $errors['dir']),
					'style' => 'padding-left: 35px;',
					'class' => 'noticebox',
				),
			),
		);
		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		if (!empty($modSettings['attachment_basedirectories']))
		{
			$listOptions2 = array(
				'id' => 'base_paths',
				'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
				'title' => $txt['attach_base_paths'],
				'get_items' => array(
					'function' => 'list_getBaseDirs',
				),
				'columns' => array(
					'current_dir' => array(
						'header' => array(
							'value' => $txt['attach_current'],
							'class' => 'centertext',
						),
						'data' => array(
							'function' => create_function('$rowData', '
								return \'<input type="radio" name="current_base_dir" value="\' . $rowData[\'id\'] . \'" \' . ($rowData[\'current\'] ? \' checked="checked"\' : \'\') . \' class="input_radio" />\';
							'),
							'style' => 'width: 10%;',
							'class' => 'centertext',
						),
					),
					'path' => array(
						'header' => array(
							'value' => $txt['attach_path'],
						),
						'data' => array(
							'db' => 'path',
							'style' => 'width: 45%;',
						),
					),
					'num_dirs' => array(
						'header' => array(
							'value' => $txt['attach_num_dirs'],
						),
						'data' => array(
							'db' => 'num_dirs',
							'style' => 'width: 15%;',
						),
					),
					'status' => array(
						'header' => array(
							'value' => $txt['attach_dir_status'],
						),
						'data' => array(
							'db' => 'status',
							'style' => 'width: 15%;',
							'class' => 'centertext',
						),
					),
				),
				'form' => array(
					'href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
				),
				'additional_rows' => array(
					array(
						'position' => 'below_table_data',
						'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" /><input type="submit" name="save2" value="' . $txt['save'] . '" class="button_submit" />
						<input type="submit" name="new_base_path" value="' . $txt['attach_add_path'] . '" class="button_submit" />',
					),
					empty($errors['base']) ? array(
						'position' => 'top_of_list',
						'value' => $txt['attach_dir_base_desc'],
						'style' => 'padding: 5px 10px;',
						'class' => 'windowbg2 smalltext'
					) : array(
						'position' => 'top_of_list',
						'value' => $txt['attach_dir_save_problem'] . '<br />' . implode('<br />', $errors['base']),
						'style' => 'padding-left: 35px',
						'class' => 'noticebox',
					),
				),
			);
			createList($listOptions2);
		}

		// Fix up our template.
		$context[$context['admin_menu_name']]['current_subsection'] = 'attachpaths';
		$context['page_title'] = $txt['attach_path_manage'];
	}

	/**
	 * Maintance function to move attachments from one directory to another
	 * @todo Move db queries to ManageAttachments.subs.php
	 */
	public function action_transfer()
	{
		global $modSettings, $txt;

		$db = database();

		checkSession();

		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		if (!empty($modSettings['attachment_basedirectories']))
			$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);
		else
			$modSettings['basedirectory_for_attachments'] = array();

		$_POST['from'] = (int) $_POST['from'];
		$_POST['auto'] = !empty($_POST['auto']) ? (int) $_POST['auto'] : 0;
		$_POST['to'] = (int) $_POST['to'];
		$start = !empty($_POST['empty_it']) ? 0 : $modSettings['attachmentDirFileLimit'];
		$_SESSION['checked'] = !empty($_POST['empty_it']) ? true : false;
		$limit = 501;
		$results = array();
		$dir_files = 0;
		$current_progress = 0;
		$total_moved = 0;
		$total_not_moved = 0;

		if (empty($_POST['from']) || (empty($_POST['auto']) && empty($_POST['to'])))
			$results[] = $txt['attachment_transfer_no_dir'];

		if ($_POST['from'] == $_POST['to'])
			$results[] = $txt['attachment_transfer_same_dir'];

		if (empty($results))
		{
			// Get the total file count for the progress bar.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}attachments
				WHERE id_folder = {int:folder_id}
					AND attachment_type != {int:attachment_type}',
				array(
					'folder_id' => $_POST['from'],
					'attachment_type' => 1,
				)
			);
			list ($total_progress) = $db->fetch_row($request);
			$db->free_result($request);
			$total_progress -= $start;

			if ($total_progress < 1)
				$results[] = $txt['attachment_transfer_no_find'];
		}

		if (empty($results))
		{
			// Where are they going?
			if (!empty($_POST['auto']))
			{
				require_once(SUBSDIR . '/Attachments.subs.php');

				$modSettings['automanage_attachments'] = 1;
				$modSettings['use_subdirectories_for_attachments'] = $_POST['auto'] == -1 ? 0 : 1;
				$modSettings['basedirectory_for_attachments'] = $_POST['auto'] > 0 ? $modSettings['attachmentUploadDir'][$_POST['auto']] : $modSettings['basedirectory_for_attachments'];

				automanage_attachments_check_directory();
				$new_dir = $modSettings['currentAttachmentUploadDir'];
			}
			else
				$new_dir = $_POST['to'];

			$modSettings['currentAttachmentUploadDir'] = $new_dir;

			$break = false;
			while ($break == false)
			{
				@set_time_limit(300);
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				// If limts are set, get the file count and size for the destination folder
				if ($dir_files <= 0 && (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit'])))
				{
					$request = $db->query('', '
						SELECT COUNT(*), SUM(size)
						FROM {db_prefix}attachments
						WHERE id_folder = {int:folder_id}
							AND attachment_type != {int:attachment_type}',
						array(
							'folder_id' => $new_dir,
							'attachment_type' => 1,
						)
					);
					list ($dir_files, $dir_size) = $db->fetch_row($request);
					$db->free_result($request);
				}

				// Find some attachments to move
				$request = $db->query('', '
					SELECT id_attach, filename, id_folder, file_hash, size
					FROM {db_prefix}attachments
					WHERE id_folder = {int:folder}
						AND attachment_type != {int:attachment_type}
					LIMIT {int:start}, {int:limit}',
					array(
						'folder' => $_POST['from'],
						'attachment_type' => 1,
						'start' => $start,
						'limit' => $limit,
					)
				);

				if ($db->num_rows($request) === 0)
				{
					if (empty($current_progress))
						$results[] = $txt['attachment_transfer_no_find'];
					break;
				}

				if ($db->num_rows($request) < $limit)
					$break = true;

				// Move them
				$moved = array();
				while ($row = $db->fetch_assoc($request))
				{
					// Size and file count check
					if (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit']))
					{
						$dir_files++;
						// @todo $source is unitialized at this point. If this isn't a bug, we should comment where it is set as to not add confusion later
						$dir_size += !empty($row['size']) ? $row['size'] : filesize($source);

						// If we've reached a limit. Do something.
						if (!empty($modSettings['attachmentDirSizeLimit']) && $dir_size > $modSettings['attachmentDirSizeLimit'] * 1024 || (!empty($modSettings['attachmentDirFileLimit']) && $dir_files >  $modSettings['attachmentDirFileLimit']))
						{
							if (!empty($_POST['auto']))
							{
								// Since we're in auto mode. Create a new folder and reset the counters.
								automanage_attachments_by_space();

								$results[] = sprintf($txt['attachments_transfered'], $total_moved, $modSettings['attachmentUploadDir'][$new_dir]);
								if (!empty($total_not_moved))
									$results[] = sprintf($txt['attachments_not_transfered'], $total_not_moved);

								$dir_files = 0;
								$total_moved = 0;
								$total_not_moved = 0;

								$break = false;
								break;
							}
							else
							{
								// Hmm, not in auto. Time to bail out then...
								$results[] = $txt['attachment_transfer_no_room'];
								$break = true;
								break;
							}
						}
					}

					$source = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
					$dest = $modSettings['attachmentUploadDir'][$new_dir] . '/' . basename($source);

					if (@rename($source, $dest))
					{
						$total_moved++;
						$current_progress++;
						$moved[] = $row['id_attach'];
					}
					else
						$total_not_moved++;
				}
				$db->free_result($request);

				if (!empty($moved))
				{
					// Update the database
					$db->query('', '
						UPDATE {db_prefix}attachments
						SET id_folder = {int:new}
						WHERE id_attach IN ({array_int:attachments})',
						array(
							'attachments' => $moved,
							'new' => $new_dir,
						)
					);
				}

				$moved = array();
				$new_dir = $modSettings['currentAttachmentUploadDir'];

				// Create the progress bar.
				if (!$break)
				{
					$percent_done = min(round($current_progress / $total_progress * 100, 0), 100);
					$prog_bar = '
						<div class="progress_bar">
							<div class="full_bar">' . $percent_done . '%</div>
							<div class="green_percent" style="width: ' . $percent_done . '%;">&nbsp;</div>
						</div>';
					// Write it to a file so it can be displayed
					$fp = fopen(BOARDDIR . '/progress.php', "w");
					fwrite($fp, $prog_bar);
					fclose($fp);
					usleep(500000);
				}
			}

			$results[] = sprintf($txt['attachments_transfered'], $total_moved, $modSettings['attachmentUploadDir'][$new_dir]);
			if (!empty($total_not_moved))
				$results[] = sprintf($txt['attachments_not_transfered'], $total_not_moved);
		}

		$_SESSION['results'] = $results;
		if (file_exists(BOARDDIR . '/progress.php'))
			unlink(BOARDDIR . '/progress.php');

		redirectexit('action=admin;area=manageattachments;sa=maintenance#transfer');
	}

}
/**
 * Function called in-between each round of attachments and avatar repairs.
 * Called by repairAttachments().
 * If repairAttachments() has more steps added, this function needs updated!
 *
 * @param array $to_fix attachments to fix
 * @param int $max_substep = 0
 * @todo Move to ManageAttachments.subs.php
 */
function pauseAttachmentMaintenance($to_fix, $max_substep = 0)
{
	global $context, $txt, $time_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if (time() - array_sum(explode(' ', $time_start)) < 3 || $context['starting_substep'] == $_GET['substep'])
		return;

	$context['continue_get_data'] = '?action=admin;area=manageattachments;sa=repair' . (isset($_GET['fixErrors']) ? ';fixErrors' : '') . ';step=' . $_GET['step'] . ';substep=' . $_GET['substep'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'maintenance';

	// Change these two if more steps are added!
	if (empty($max_substep))
		$context['continue_percent'] = round(($_GET['step'] * 100) / 25);
	else
		$context['continue_percent'] = round(($_GET['step'] * 100 + ($_GET['substep'] * 100) / $max_substep) / 25);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	$_SESSION['attachments_to_fix'] = $to_fix;
	$_SESSION['attachments_to_fix2'] = $context['repair_errors'];

	obExit();
}