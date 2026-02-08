<?php

/**
 * Handles the job of attachment and avatar maintenance / management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Attachments\AttachmentsDirectory;
use ElkArte\Graphics\Image;
use ElkArte\Graphics\Manipulators\Gd2;
use ElkArte\Graphics\Manipulators\ImageMagick;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;
use ElkArte\Languages\Loader;
use ElkArte\SettingsForm\SettingsForm;
use Exception;
use FilesystemIterator;
use UnexpectedValueException;

/**
 * This is the attachments and avatars controller class.
 * It is doing the job of attachments and avatars maintenance and management.
 *
 */
class ManageAttachments extends AbstractController
{
	/** @var int Loop counter for paused attachment maintenance actions */
	public int $step;

	/** @var int substep counter for paused attachment maintenance actions */
	public int $substep;

	/** @var int Substep at the beginning of a maintenance loop */
	public int $starting_substep;

	/** @var int Current directory key being processed */
	public int $current_dir;

	/** @var string Used during transfer of files */
	public string $from;

	/** @var string Type of attachment management in use */
	public string $auto;

	/** @var string Destination when transferring attachments */
	public string $to;

	/** @var FileFunctions */
	public FileFunctions $file_functions;

	/**
	 * Pre-dispatch, load functions needed by all methods
	 */
	public function pre_dispatch()
	{
		// These get used often enough that it makes sense to include them for every action
		require_once(SUBSDIR . '/Attachments.subs.php');
		require_once(SUBSDIR . '/ManageAttachments.subs.php');
		$this->file_functions = FileFunctions::instance();
	}

	/**
	 * The main 'Attachments and Avatars' admin.
	 *
	 * What it does:
	 *
	 * - This method is the entry point for index.php?action=admin;area=manageattachments,
	 * and it calls a function based on the sub-action.
	 * - It requires the manage_attachments permission.
	 *
	 * @event integrate_sa_manage_attachments
	 * @uses ManageAttachments template.
	 * @uses Admin language file.
	 * @uses template layer 'manage_files' for showing the tab bar.
	 *
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// You have to be able to moderate the forum to do this.
		isAllowedTo('manage_attachments');

		// Set up the template stuff we'll probably need.
		theme()->getTemplates()->load('ManageAttachments');

		// All the things we can do with attachments
		$subActions = [
			'attachments' => [$this, 'action_attachSettings_display'],
			'avatars' => ['controller' => ManageAvatars::class, 'function' => 'action_index'],
			'attachpaths' => [$this, 'action_attachpaths'],
			'browse' => [$this, 'action_browse'],
			'byAge' => [$this, 'action_byAge'],
			'bySize' => [$this, 'action_bySize'],
			'maintenance' => [$this, 'action_maintenance'],
			'repair' => [$this, 'action_repair'],
			'remove' => [$this, 'action_remove'],
			'removeall' => [$this, 'action_removeall'],
			'transfer' => [$this, 'action_transfer'],
		];

		// Get ready for some action
		$action = new Action('manage_attachments');

		// The default page title is good.
		$context['page_title'] = $txt['attachments_avatars'];

		// Get the subAction, call integrate_sa_manage_attachments
		$subAction = $action->initialize($subActions, 'browse');
		$context['sub_action'] = $subAction;

		// This uses admin tabs - as it should!
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'attachments_avatars',
			'help' => 'manage_files',
			'description' => 'attachments_desc',
		]);

		// Finally, go to where we want to go
		$action->dispatch($subAction);
	}

	/**
	 * Allows showing / changing attachment settings.
	 *
	 * - This is the default sub-action of the 'Attachments and Avatars' center.
	 * - Called by index.php?action=admin;area=manageattachments;sa=attachments.
	 *
	 * @event integrate_save_attachment_settings
	 * @uses 'attachments' sub template.
	 */
	public function action_attachSettings_display(): void
	{
		global $modSettings, $context;

		// initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());

		// Initialize settings
		$settingsForm->setConfigVars($this->_settings());

		theme()->addInlineJavascript('
	let storing_type = document.getElementById(\'automanage_attachments\'),
		base_dir = document.getElementById(\'use_subdirectories_for_attachments\');

	createEventListener(storing_type)
	storing_type.addEventListener("change", toggleSubDir, false);
	createEventListener(base_dir)
	base_dir.addEventListener("change", toggleSubDir, false);
	toggleSubDir();', true);

		// Saving settings?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			if ($this->_req->hasPost('attachmentEnable'))
			{
				enableModules('attachments', ['post', 'display']);
			}
			else
			{
				disableModules('attachments', ['post', 'display']);
			}

			// Read posted values safely
			$autoManage = $this->_req->getPost('automanage_attachments', 'intval', 0);
			$useSubdirectories = $this->_req->getPost('use_subdirectories_for_attachments', 'intval', 0);
			$baseDirPosted = $this->_req->getPost('basedirectory_for_attachments', 'trim');
			$uploadDirPosted = $this->_req->getPost('attachmentUploadDir', 'trim');
			$webpEnable = !empty($this->_req->getPost('attachment_webp_enable', null, ''));
			$attachExtensions = $this->_req->getPost('attachmentExtensions', 'trim');
			$heicEnable = !empty($this->_req->getPost('attachment_heic_enable', null, ''));

			// Default/Manual implies no subdirectories
			if ($autoManage === 0)
			{
				$useSubdirectories = 0;
			}

			// Changing the attachment upload directory
			if ($uploadDirPosted !== null)
			{
				if (!empty($uploadDirPosted)
					&& $modSettings['attachmentUploadDir'] !== $uploadDirPosted
					&& $this->file_functions->fileExists($modSettings['attachmentUploadDir']))
				{
					rename($modSettings['attachmentUploadDir'], $uploadDirPosted);
				}

				$modSettings['attachmentUploadDir'] = [1 => $uploadDirPosted];
			}

			// Adding / changing the subdirectory's for attachments
			if (!empty($useSubdirectories))
			{
				// Make sure we have a base directory defined
				if (empty($baseDirPosted))
				{
					$baseDirPosted = (empty($modSettings['basedirectory_for_attachments']) ? (BOARDDIR) : $modSettings['basedirectory_for_attachments']);
				}

				// The current base directories that we know
				if (!empty($modSettings['attachment_basedirectories']))
				{
					if (!is_array($modSettings['attachment_basedirectories']))
					{
						$modSettings['attachment_basedirectories'] = Util::unserialize($modSettings['attachment_basedirectories']);
					}
				}
				else
				{
					$modSettings['attachment_basedirectories'] = [];
				}

				// Trying to use a nonexistent base directory
				if (!empty($baseDirPosted) && $attachmentsDir->isBaseDir($baseDirPosted) === false)
				{
					$currentAttachmentUploadDir = $attachmentsDir->currentDirectoryId();

					// If this is a new directory being defined, attempt to create it
					if ($attachmentsDir->directoryExists($baseDirPosted) === false)
					{
						try
						{
							$attachmentsDir->createDirectory($baseDirPosted);
						}
						catch (Exception)
						{
							$baseDirPosted = $modSettings['basedirectory_for_attachments'];
						}
					}

					// The base directory should be in our list of available bases
					if (!in_array($baseDirPosted, $modSettings['attachment_basedirectories']))
					{
						$modSettings['attachment_basedirectories'][$modSettings['currentAttachmentUploadDir']] = $baseDirPosted;
						updateSettings(['attachment_basedirectories' => serialize($modSettings['attachment_basedirectories']), 'currentAttachmentUploadDir' => $currentAttachmentUploadDir,]);
					}
				}
			}

			// Allow or not webp extensions.
			if (!empty($webpEnable) && $attachExtensions !== null && !str_contains((string) $attachExtensions, 'webp'))
			{
				$attachExtensions = rtrim((string) $attachExtensions, ',') . ',webp';
			}

			// Allow or not heic extensions.
			if (!empty($heicEnable) && $attachExtensions !== null && !str_contains((string) $attachExtensions, 'heic'))
			{
				$attachExtensions = rtrim((string) $attachExtensions, ',') . ',heic';
			}

			call_integration_hook('integrate_save_attachment_settings');

			// Build a config array from posted values and override with sanitized ones
			$config = (array) $this->_req->post;
			$config['automanage_attachments'] = $autoManage;
			$config['use_subdirectories_for_attachments'] = $useSubdirectories;
			if ($baseDirPosted !== null)
			{
				$config['basedirectory_for_attachments'] = $baseDirPosted;
			}
			if ($uploadDirPosted !== null)
			{
				$config['attachmentUploadDir'] = serialize($modSettings['attachmentUploadDir']);
			}
			if ($attachExtensions !== null)
			{
				$config['attachmentExtensions'] = $attachExtensions;
			}

			$settingsForm->setConfigValues($config);
			$settingsForm->save();
			redirectexit('action=admin;area=manageattachments;sa=attachments');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachments', 'save']);
		$settingsForm->prepare();

		$context['sub_template'] = 'show_settings';
	}

	/**
	 * Retrieve and return the administration settings for attachments.
	 *
	 * @event integrate_modify_attachment_settings
	 */
	private function _settings()
	{
		global $modSettings, $txt, $context, $settings;

		// Get the current attachment directory.
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());
		$context['attachmentUploadDir'] = $attachmentsDir->getCurrent();

		// First time here?
		if (empty($modSettings['attachment_basedirectories']) && $modSettings['currentAttachmentUploadDir'] == 1 && (is_array($modSettings['attachmentUploadDir']) && $attachmentsDir->countDirs() == 1))
		{
			$modSettings['attachmentUploadDir'] = $modSettings['attachmentUploadDir'][1];
		}

		// If not set, show a default path for the base directory
		if (!$this->_req->hasQuery('save') && empty($modSettings['basedirectory_for_attachments']))
		{
			$modSettings['basedirectory_for_attachments'] = $context['attachmentUploadDir'];
		}

		$context['valid_upload_dir'] = $this->file_functions->isDir($context['attachmentUploadDir']) && $this->file_functions->isWritable($context['attachmentUploadDir']);

		if ($attachmentsDir->autoManageEnabled())
		{
			$context['valid_basedirectory'] = !empty($modSettings['basedirectory_for_attachments']) && $this->file_functions->isWritable($modSettings['basedirectory_for_attachments']);
		}
		else
		{
			$context['valid_basedirectory'] = true;
		}

		// A bit of razzle-dazzle with the $txt strings. :)
		$txt['basedirectory_for_attachments_warning'] = str_replace('{attach_repair_url}', getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths']), $txt['basedirectory_for_attachments_warning']);
		$txt['attach_current_dir_warning'] = str_replace('{attach_repair_url}', getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths']), $txt['attach_current_dir_warning']);
		$txt['attachment_path'] = $context['attachmentUploadDir'];
		$txt['basedirectory_for_attachments_path'] = $modSettings['basedirectory_for_attachments'] ?? '';
		$txt['use_subdirectories_for_attachments_note'] = empty($modSettings['attachment_basedirectories']) || empty($modSettings['use_subdirectories_for_attachments']) ? $txt['use_subdirectories_for_attachments_note'] : '';
		$txt['attachmentUploadDir_multiple_configure'] = '<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths']) . '">' . $txt['attachmentUploadDir_multiple_configure'] . '</a>';
		$txt['attach_current_dir'] = $attachmentsDir->autoManageEnabled() ? $txt['attach_last_dir'] : $txt['attach_current_dir'];
		$txt['attach_current_dir_warning'] = $txt['attach_current_dir'] . $txt['attach_current_dir_warning'];
		$txt['basedirectory_for_attachments_warning'] = $txt['basedirectory_for_attachments_current'] . $txt['basedirectory_for_attachments_warning'];

		// Perform several checks to determine imaging capabilities.
		$image = new Image($settings['default_theme_dir'] . '/images/blank.png');
		$testImg = Gd2::canUse() || ImageMagick::canUse();
		$testImgRotate = ImageMagick::canUse() || (Gd2::canUse() && function_exists('exif_read_data'));

		// Check on webp support, and correct if wrong
		$testWebP = $image->hasWebpSupport();
		if (!$testWebP && !empty($modSettings['attachment_webp_enable']))
		{
			updateSettings(['attachment_webp_enable' => 0]);
		}

		// Check on HEIC support, and correct if wrong
		$testHeic = $image->hasHeicSupport();
		if (!$testHeic && !empty($modSettings['attachment_heic_enable']))
		{
			updateSettings(['attachment_heic_enable' => 0]);
		}

		// Check if the server settings support these upload size values
		$post_max_size = ini_get('post_max_size');
		$upload_max_filesize = ini_get('upload_max_filesize');
		$testPM = empty($post_max_size) || memoryReturnBytes($post_max_size) >= (isset($modSettings['attachmentPostLimit']) ? $modSettings['attachmentPostLimit'] * 1024 : 0);
		$testUM = empty($upload_max_filesize) || memoryReturnBytes($upload_max_filesize) >= (isset($modSettings['attachmentSizeLimit']) ? $modSettings['attachmentSizeLimit'] * 1024 : 0);

		// Set some helpful information for the UI
		$post_max_size_text = sprintf($txt['zero_for_system_limit'], $post_max_size === '' || $post_max_size === '0' || $post_max_size === false ? $txt['none'] : $post_max_size, 'post_max_size');
		$upload_max_filesize_text = sprintf($txt['zero_for_system_limit'], $upload_max_filesize === '' || $upload_max_filesize === '0' || $upload_max_filesize === false ? $txt['none'] : $upload_max_filesize, 'upload_max_filesize');

		$config_vars = [
			['title', 'attachment_manager_settings'],
			// Are attachments enabled?
			['select', 'attachmentEnable', [$txt['attachmentEnable_deactivate'], $txt['attachmentEnable_enable_all'], $txt['attachmentEnable_disable_new']]],
			'',
			// Directory and size limits.
			['select', 'automanage_attachments', [0 => $txt['attachments_normal'], 1 => $txt['attachments_auto_space'], 2 => $txt['attachments_auto_years'], 3 => $txt['attachments_auto_months'], 4 => $txt['attachments_auto_16']]],
			['check', 'use_subdirectories_for_attachments', 'subtext' => $txt['use_subdirectories_for_attachments_note']],
			(empty($modSettings['attachment_basedirectories'])
				? ['text', 'basedirectory_for_attachments', 40,]
				: ['var_message', 'basedirectory_for_attachments', 'message' => 'basedirectory_for_attachments_path', 'invalid' => empty($context['valid_basedirectory']), 'text_label' => (empty($context['valid_basedirectory'])
					? $txt['basedirectory_for_attachments_warning']
					: $txt['basedirectory_for_attachments_current'])]
			),
			Util::is_serialized($modSettings['attachmentUploadDir'])
				? ['var_message', 'attach_current_directory', 'postinput' => $txt['attachmentUploadDir_multiple_configure'], 'message' => 'attachment_path', 'invalid' => empty($context['valid_upload_dir']),
				'text_label' => (empty($context['valid_upload_dir'])
					? $txt['attach_current_dir_warning']
					: $txt['attach_current_dir'])]
				: ['text', 'attachmentUploadDir', 'postinput' => $txt['attachmentUploadDir_multiple_configure'], 40, 'invalid' => !$context['valid_upload_dir']],
			['int', 'attachmentDirFileLimit', 'subtext' => $txt['zero_for_no_limit'], 6],
			['int', 'attachmentDirSizeLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']],
			'',
			// Posting limits
			['int', 'attachmentPostLimit', 'subtext' => $post_max_size_text, 6, 'postinput' => $testPM === false ? $txt['attachment_postsize_warning'] : $txt['kilobyte'], 'invalid' => $testPM === false],
			['int', 'attachmentSizeLimit', 'subtext' => $upload_max_filesize_text, 6, 'postinput' => $testUM === false ? $txt['attachment_postsize_warning'] : $txt['kilobyte'], 'invalid' => $testUM === false],
			['int', 'attachmentNumPerPostLimit', 'subtext' => $txt['zero_for_no_limit'], 6],
			'',
			['check', 'attachment_webp_enable', 'disabled' => !$testWebP, 'postinput' => $testWebP ? "" : $txt['attachment_webp_enable_na']],
			['check', 'attachment_autorotate', 'disabled' => !$testImgRotate, 'postinput' => $testImgRotate ? '' : $txt['attachment_autorotate_na']],
			['check', 'attachment_heic_enable', 'disabled' => !$testHeic, 'postinput' => $testHeic ? "" : $txt['attachment_heic_enable_na']],
			// Resize limits
			['title', 'attachment_image_resize'],
			['check', 'attachment_image_resize_enabled'],
			['check', 'attachment_image_resize_reformat'],
			['text', 'attachment_image_resize_width', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['attachment_image_resize_post']],
			['text', 'attachment_image_resize_height', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['attachment_image_resize_post']],
			// Security Items
			['title', 'attachment_security_settings'],
			// Extension checks etc.
			['check', 'attachmentCheckExtensions'],
			['text', 'attachmentExtensions', 40],
			'',
			// Image checks.
			['warning', $testImg === false ? 'attachment_img_enc_warning' : ''],
			['check', 'attachment_image_reencode'],
			// Thumbnail settings.
			['title', 'attachment_thumbnail_settings'],
			['check', 'attachmentShowImages'],
			['check', 'attachmentThumbnails'],
			['text', 'attachmentThumbWidth', 6],
			['text', 'attachmentThumbHeight', 6],
			'',
			['int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']],
		];

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_attachment_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the config settings, used for admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * Show a list of attachment or avatar files.
	 *
	 * - Called by
	 *     ?action=admin;area=manageattachments;sa=browse for attachments
	 *     ?action=admin;area=manageattachments;sa=browse;avatars for avatars.
	 *     ?action=admin;area=manageattachments;sa=browse;thumbs for thumbnails.
	 * - Allows sorting by name, date, size, and member.
	 * - Paginates results.
	 *
	 * @uses the 'browse' sub template
	 */
	public function action_browse(): void
	{
		global $context, $txt;

		// Attachments or avatars?
		$context['browse_type'] = $this->_req->hasQuery('avatars') ? 'avatars' : ($this->_req->hasQuery('thumbs') ? 'thumbs' : 'attachments');
		loadJavascriptFile('topic.js');

		// Set the options for the list component.
		$listOptions = [
			'id' => 'attach_browse',
			'title' => $txt['attachment_manager_browse_files'],
			'items_per_page' => 25,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'browse'] + ($context['browse_type'] === 'avatars' ? ['avatars'] : ($context['browse_type'] === 'thumbs' ? ['thumbs'] : []))),
			'default_sort_col' => 'name',
			'no_items_label' => $txt['attachment_manager_' . ($context['browse_type'] === 'avatars' ? 'avatars' : ($context['browse_type'] === 'thumbs' ? 'thumbs' : 'attachments')) . '_no_entries'],
			'get_items' => [
				'function' => 'list_getFiles',
				'params' => [
					$context['browse_type'],
				],
			],
			'get_count' => [
				'function' => 'list_getNumFiles',
				'params' => [
					$context['browse_type'],
				],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['attachment_name'],
						'class' => 'grid50',
					],
					'data' => [
						'function' => static function ($rowData) {
							global $modSettings, $context;

							$link = '<a href="';
							// In the case of a custom avatar, URL attachments have a fixed directory.
							if ((int) $rowData['attachment_type'] === 1)
							{
								$link .= sprintf('%1$s/%2$s', $modSettings['custom_avatar_url'], $rowData['filename']);
							}
							// By default, avatars are downloaded almost as attachments.
							elseif ($context['browse_type'] === 'avatars')
							{
								$link .= getUrl('attach', ['action' => 'dlattach', 'type' => 'avatar', 'attach' => (int) $rowData['id_attach'], 'name' => $rowData['filename']]);
							}
							// Normal attachments are always linked to a topic ID.
							else
							{
								$link .= getUrl('attach', ['action' => 'dlattach', 'topic' => ((int) $rowData['id_topic']) . '.0', 'attach' => (int) $rowData['id_attach'], 'name' => $rowData['filename']]);
							}
							$link .= '"';

							// Show a popup on click if it's a picture, and we know its dimensions (use a rand message to prevent navigation)
							if (!empty($rowData['width']) && !empty($rowData['height']))
							{
								$link .= 'id="link_' . $rowData['id_attach'] . '" data-lightboxmessage="' . random_int(0, 100000) . '" data-lightboximage="' . $rowData['id_attach'] . '"';
							}

							$link .= sprintf('>%1$s</a>', preg_replace('~&amp;#(\\\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\\\1;', htmlspecialchars($rowData['filename'], ENT_COMPAT, 'UTF-8')));

							// Show the dimensions.
							if (!empty($rowData['width']) && !empty($rowData['height']))
							{
								$link .= sprintf(' <span class="smalltext">%1$dx%2$d</span>', $rowData['width'], $rowData['height']);
							}

							return $link;
						},
					],
					'sort' => [
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					],
				],
				'filesize' => [
					'header' => [
						'value' => $txt['attachment_file_size'],
						'class' => 'nowrap',
					],
					'data' => [
						'function' => static fn($rowData) => byte_format($rowData['size']),
					],
					'sort' => [
						'default' => 'a.size',
						'reverse' => 'a.size DESC',
					],
				],
				'member' => [
					'header' => [
						'value' => $context['browse_type'] === 'avatars' ? $txt['attachment_manager_member'] : $txt['posted_by'],
						'class' => 'nowrap',
					],
					'data' => [
						'function' => static function ($rowData) {
							// In case of an attachment, return the poster of the attachment.
							if (empty($rowData['id_member']))
							{
								return htmlspecialchars($rowData['poster_name'], ENT_COMPAT, 'UTF-8');
							}

							return '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => (int) $rowData['id_member'], 'name' => $rowData['poster_name']]) . '">' . $rowData['poster_name'] . '</a>';
						},
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'date' => [
					'header' => [
						'value' => $context['browse_type'] === 'avatars' ? $txt['attachment_manager_last_active'] : $txt['date'],
						'class' => 'nowrap',
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt, $context;

							// The date the message containing the attachment was posted or the owner of the avatar was active.
							$date = empty($rowData['poster_time']) ? $txt['never'] : standardTime($rowData['poster_time']);
							// Add a link to the topic in case of an attachment.
							if ($context['browse_type'] !== 'avatars')
							{
								$date .= '<br />' . $txt['in'] . ' <a href="' . getUrl('topic', ['topic' => (int) $rowData['id_topic'], 'start' => 'msg' . (int) $rowData['id_msg'], 'subject' => $rowData['subject']]) . '#msg' . (int) $rowData['id_msg'] . '">' . $rowData['subject'] . '</a>';
							}

							return $date;
						},
					],
					'sort' => [
						'default' => $context['browse_type'] === 'avatars' ? 'mem.last_login' : 'm.id_msg',
						'reverse' => $context['browse_type'] === 'avatars' ? 'mem.last_login DESC' : 'm.id_msg DESC',
					],
				],
				'downloads' => [
					'header' => [
						'value' => $txt['downloads'],
						'class' => 'nowrap',
					],
					'data' => [
						'db' => 'downloads',
						'comma_format' => true,
					],
					'sort' => [
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[%1$d]" class="input_check" />',
							'params' => [
								'id_attach' => false,
							],
						],
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'remove', ($context['browse_type'] === 'avatars' ? 'avatars' : ($context['browse_type'] === 'thumbs' ? 'thumbs' : ''))]),
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					'type' => $context['browse_type'],
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="remove_submit" class="right_submit" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['confirm_delete_attachments'] . '\');" />',
				],
			],
			'list_menu' => [
				'show_on' => 'top',
				'class' => 'flow_flex_right',
				'links' => [
					[
						'href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'browse']),
						'is_selected' => $context['browse_type'] === 'attachments',
						'label' => $txt['attachment_manager_attachments']
					],
					[
						'href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'browse', 'avatars']),
						'is_selected' => $context['browse_type'] === 'avatars',
						'label' => $txt['attachment_manager_avatars']
					],
					[
						'href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'browse', 'thumbs']),
						'is_selected' => $context['browse_type'] === 'thumbs',
						'label' => $txt['attachment_manager_thumbs']
					],
				],
			],
		];

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Show several file maintenance options.
	 *
	 * What it does:
	 *
	 * - Called by ?action=admin;area=manageattachments;sa=maintain.
	 * - Calculates file statistics (total file size, number of attachments,
	 * number of avatars, attachment space available).
	 *
	 * @uses the 'maintenance' sub template.
	 */
	public function action_maintenance(): void
	{
		global $context, $modSettings;

		theme()->getTemplates()->load('ManageAttachments');
		$context['sub_template'] = 'maintenance';

		// We need our attachments directories...
		$attachmentDirectory = new AttachmentsDirectory($modSettings, database());
		$attach_dirs = $attachmentDirectory->getPaths();

		// Get the number of attachments...
		$context['num_attachments'] = comma_format(getAttachmentCountByType('attachments'), 0);

		// Also get the avatar amount...
		$context['num_avatars'] = comma_format(getAttachmentCountByType('avatars'), 0);

		// Total size of attachments
		$context['attachment_total_size'] = overallAttachmentsSize();

		// Total size and files from the current attachment dir.
		$current_dir = currentAttachDirProperties();

		// If they specified a limit only...
		if ($attachmentDirectory->hasSizeLimit())
		{
			$context['attachment_space'] = byte_format($attachmentDirectory->remainingSpace($current_dir['size']));
		}

		if ($attachmentDirectory->hasNumFilesLimit())
		{
			$context['attachment_files'] = comma_format($attachmentDirectory->remainingFiles($current_dir['files']), 0);
		}

		$context['attachment_current_size'] = byte_format($current_dir['size']);
		$context['attachment_current_files'] = comma_format($current_dir['files'], 0);
		$context['attach_multiple_dirs'] = count($attach_dirs) > 1;
		$context['attach_dirs'] = $attach_dirs;
		$context['base_dirs'] = empty($modSettings['attachment_basedirectories']) ? [] : Util::unserialize($modSettings['attachment_basedirectories']);
		$context['checked'] = $this->_req->getSession('checked', true);

		if (!empty($_SESSION['results']))
		{
			$context['results'] = implode('<br />', $this->_req->session->results);
			unset($_SESSION['results']);
		}
	}

	/**
	 * Remove attachments older than a given age.
	 *
	 * - Called from the maintenance screen by ?action=admin;area=manageattachments;sa=byAge.
	 * - It optionally adds a certain text to the messages the attachments were removed from.
	 */
	public function action_byAge(): void
	{
		checkSession('post', 'admin');

		// @todo Ignore messages in topics that are stickied?

		// Inputs
		$age = $this->_req->getPost('age', 'intval', 0);
		$notice = $this->_req->getPost('notice', 'trim');

		// Deleting an attachment?
		if (!$this->_req->compareQuery('type', 'avatars', 'trim|strval'))
		{
			// Get rid of all the old attachments.
			$messages = removeAttachments(['attachment_type' => 0, 'poster_time' => (time() - 24 * 60 * 60 * $age)], 'messages', true);

			// Update the messages to reflect the change.
			if (!empty($messages) && !empty($notice))
			{
				setRemovalNotice($messages, $notice);
			}
		}
		// Remove all the old avatars.
		else
		{
			removeAttachments(['not_id_member' => 0, 'last_login' => (time() - 24 * 60 * 60 * $age)], 'members');
		}

		redirectexit('action=admin;area=manageattachments' . (empty($this->_req->query->avatars) ? ';sa=maintenance' : ';avatars'));
	}

	/**
	 * Remove attachments larger than a given size.
	 *
	 * - Called from the maintenance screen by ?action=admin;area=manageattachments;sa=bySize.
	 * - Optionally adds a certain text to the messages the attachments were removed from.
	 */
	public function action_bySize(): void
	{
		checkSession('post', 'admin');

		$size = $this->_req->getPost('size', 'intval', 0);
		$notice = $this->_req->getPost('notice', 'trim');

		// Find humongous attachments.
		$messages = removeAttachments(['attachment_type' => 0, 'size' => 1024 * $size], 'messages', true);

		// And make a note on the post.
		if (!empty($messages) && !empty($notice))
		{
			setRemovalNotice($messages, $notice);
		}

		redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * Remove a selection of attachments or avatars.
	 *
	 * - Called from the browse screen as submitted form by ?action=admin;area=manageattachments;sa=remove
	 */
	public function action_remove(): void
	{
		global $language;

		checkSession();

		$to_remove = $this->_req->getPost('remove', null, []);
		if (!empty($to_remove) && is_array($to_remove))
		{
			// There must be a quicker way to pass this safety test??
			$attachments = [];
			foreach ($to_remove as $removeID => $dummy)
			{
				$attachments[] = (int) $removeID;
			}

			if ($this->_req->compareQuery('type', 'avatars', 'trim|strval') && !empty($attachments))
			{
				removeAttachments(['id_attach' => $attachments]);
			}
			elseif (!empty($attachments))
			{
				$messages = removeAttachments(['id_attach' => $attachments], 'messages', true);

				// And change the message to reflect this.
				if (!empty($messages))
				{
					$mtxt = [];
					$lang = new Loader($language, $mtxt, database());
					$lang->load('Admin');
					setRemovalNotice($messages, $mtxt['attachment_delete_admin']);
				}
			}
		}

		$sort = $this->_req->getQuery('sort', 'trim|strval', 'date');
		$type = $this->_req->getQuery('type', 'trim|strval', 'attachments');
		$start = $this->_req->getQuery('start', 'intval', 0);
		$desc = $this->_req->hasQuery('desc') ? ';desc' : '';
		redirectexit('action=admin;area=manageattachments;sa=browse;' . $type . ';sort=' . $sort . $desc . ';start=' . $start);
	}

	/**
	 * Removes all attachments in a single click
	 *
	 * - Called from the maintenance screen by ?action=admin;area=manageattachments;sa=removeall.
	 */
	public function action_removeall(): void
	{
		global $txt;

		checkSession('get', 'admin');

		$messages = removeAttachments(['attachment_type' => 0], '', true);

		$notice = $this->_req->getPost('notice', 'trim|strval', $txt['attachment_delete_admin']);

		// Add the notice at the end of the changed messages.
		if (!empty($messages))
		{
			setRemovalNotice($messages, $notice);
		}

		redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * This function will perform many attachment checks and provides ways to fix them
	 *
	 * What it does:
	 *
	 * Checks for the following common issues
	 * - Orphan Thumbnails
	 * - Attachments that have no thumbnails
	 * - Attachments that list thumbnails, but actually, don't have any
	 * - Attachments list in the wrong_folder
	 * - Attachments that don't exist on disk any longer
	 * - Attachments that are zero size
	 * - Attachments that file size does not match the DB size
	 * - Attachments that no longer have a message
	 * - Avatars with no members associated with them.
	 * - Attachments that are in the attachment folder, but not listed in the DB
	 */
	public function action_repair(): void
	{
		global $modSettings, $context, $txt;

		checkSession('get');

		// If we choose to cancel, redirect right back.
		if ($this->_req->hasPost('cancel'))
		{
			redirectexit('action=admin;area=manageattachments;sa=maintenance');
		}

		// Try to give us a while to sort this out...
		detectServer()->setTimeLimit(600);

		$this->step = $this->_req->getQuery('step', 'intval', 0);
		$this->substep = $this->_req->getQuery('substep', 'intval', 0);
		$this->starting_substep = $this->substep;

		// Don't recall the session just in case.
		if ($this->step === 0 && $this->substep === 0)
		{
			unset($_SESSION['attachments_to_fix'], $_SESSION['attachments_to_fix2']);

			// If we're actually fixing stuff - work out what.
			if ($this->_req->hasQuery('fixErrors'))
			{
				// Nothing?
				$to_fix_post = $this->_req->getPost('to_fix', null, []);
				if (empty($to_fix_post))
				{
					redirectexit('action=admin;area=manageattachments;sa=maintenance');
				}

				foreach ((array) $to_fix_post as $value)
				{
					$_SESSION['attachments_to_fix'][] = $value;
				}
			}
		}

		// All the valid problems are here:
		$context['repair_errors'] = [
			'missing_thumbnail_parent' => 0,
			'parent_missing_thumbnail' => 0,
			'file_missing_on_disk' => 0,
			'file_wrong_size' => 0,
			'file_size_of_zero' => 0,
			'attachment_no_msg' => 0,
			'avatar_no_member' => 0,
			'wrong_folder' => 0,
			'missing_extension' => 0,
			'files_without_attachment' => 0,
		];

		$to_fix = empty($_SESSION['attachments_to_fix']) ? [] : $_SESSION['attachments_to_fix'];
		$context['repair_errors'] = $_SESSION['attachments_to_fix2'] ?? $context['repair_errors'];
		$fix_errors = $this->_req->hasQuery('fixErrors');

		// Get stranded thumbnails.
		if ($this->step <= 0)
		{
			$thumbnails = getMaxThumbnail();

			for (; $this->substep < $thumbnails; $this->substep += 500)
			{
				$removed = findOrphanThumbnails($this->substep, $fix_errors, $to_fix);
				$context['repair_errors']['missing_thumbnail_parent'] += count($removed);

				pauseAttachmentMaintenance($to_fix, $thumbnails, $this->starting_substep, $this->substep, $this->step, $fix_errors);
			}

			// Done here, on to the next
			$this->step = 1;
			$this->substep = 0;
			pauseAttachmentMaintenance($to_fix, 0, $this->starting_substep, $this->substep, $this->step, $fix_errors);
		}

		// Find parents who think they have thumbnails, but actually, don't.
		if ($this->step <= 1)
		{
			$thumbnails = maxNoThumb();

			for (; $this->substep < $thumbnails; $this->substep += 500)
			{
				$to_update = findParentsOrphanThumbnails($this->substep, $fix_errors, $to_fix);
				$context['repair_errors']['parent_missing_thumbnail'] += count($to_update);

				pauseAttachmentMaintenance($to_fix, $thumbnails, $this->starting_substep, $this->substep, $this->step, $fix_errors);
			}

			// Another step done, but many to go
			$this->step = 2;
			$this->substep = 0;
			pauseAttachmentMaintenance($to_fix, 0, $this->starting_substep, $this->substep, $this->step, $fix_errors);
		}

		// This may take forever, I'm afraid, but life sucks... recount EVERY attachment!
		if ($this->step <= 2)
		{
			$thumbnails = maxAttachment();

			for (; $this->substep < $thumbnails; $this->substep += 250)
			{
				$repair_errors = repairAttachmentData($this->substep, $fix_errors, $to_fix);

				foreach ($repair_errors as $key => $value)
				{
					$context['repair_errors'][$key] += $value;
				}

				pauseAttachmentMaintenance($to_fix, $thumbnails, $this->starting_substep, $this->substep, $this->step, $fix_errors);
			}

			// And onward we go
			$this->step = 3;
			$this->substep = 0;
			pauseAttachmentMaintenance($to_fix, 0, $this->starting_substep, $this->substep, $this->step, $fix_errors);
		}

		// Get avatars with no members associated with them.
		if ($this->step <= 3)
		{
			$thumbnails = maxAttachment();

			for (; $this->substep < $thumbnails; $this->substep += 500)
			{
				$to_remove = findOrphanAvatars($this->substep, $fix_errors, $to_fix);
				$context['repair_errors']['avatar_no_member'] += count($to_remove);

				pauseAttachmentMaintenance($to_fix, $thumbnails, $this->starting_substep, $this->substep, $this->step, $fix_errors);
			}

			$this->step = 4;
			$this->substep = 0;
			pauseAttachmentMaintenance($to_fix, 0, $this->starting_substep, $this->substep, $this->step, $fix_errors);
		}

		// What about attachments, who are missing a message :'(
		if ($this->step <= 4)
		{
			$thumbnails = maxAttachment();

			for (; $this->substep < $thumbnails; $this->substep += 500)
			{
				$to_remove = findOrphanAttachments($this->substep, $fix_errors, $to_fix);
				$context['repair_errors']['attachment_no_msg'] += count($to_remove);

				pauseAttachmentMaintenance($to_fix, $thumbnails, $this->starting_substep, $this->substep, $this->step, $fix_errors);
			}

			$this->step = 5;
			$this->substep = 0;
			pauseAttachmentMaintenance($to_fix, 0, $this->starting_substep, $this->substep, $this->step, $fix_errors);
		}

		// What about files that are not recorded in the database?
		if ($this->step <= 5)
		{
			// Just use the current path for temp files.
			if (!is_array($modSettings['attachmentUploadDir']))
			{
				$modSettings['attachmentUploadDir'] = Util::unserialize($modSettings['attachmentUploadDir']);
			}

			$attach_dirs = $modSettings['attachmentUploadDir'];
			$current_check = 0;
			$max_checks = 500;
			$attachment_count = getAttachmentCountFromDisk();

			$files_checked = empty($this->substep) ? 0 : $this->substep;
			foreach ($attach_dirs as $attach_dir)
			{
				try
				{
					$files = new FilesystemIterator($attach_dir, FilesystemIterator::SKIP_DOTS);
					foreach ($files as $file)
					{
						if ($file->getFilename() === '.htaccess')
						{
							continue;
						}

						if ($files_checked <= $current_check)
						{
							// Temporary file, get rid of it!
							if (str_contains($file->getFilename(), 'post_tmp_'))
							{
								// Temp file is more than 5 hours old!
								if ($file->getMTime() < time() - 18000)
								{
									$this->file_functions->delete($file->getPathname());
								}
							}
							// That should be an attachment, let's check if we have it in the database
							elseif (str_contains($file->getFilename(), '_'))
							{
								$attachID = (int) substr($file->getFilename(), 0, strpos($file->getFilename(), '_'));
								if ($attachID !== 0 && !validateAttachID($attachID))
								{
									if ($fix_errors && in_array('files_without_attachment', $to_fix))
									{
										$this->file_functions->delete($file->getPathname());
									}
									else
									{
										$context['repair_errors']['files_without_attachment']++;
									}
								}
							}
							elseif ($file->getFilename() !== 'index.php' && !$file->isDir())
							{
								if ($fix_errors && in_array('files_without_attachment', $to_fix, true))
								{
									$this->file_functions->delete($file->getPathname());
								}
								else
								{
									$context['repair_errors']['files_without_attachment']++;
								}
							}
						}

						$current_check++;
						$this->substep = $current_check;

						if ($current_check - $files_checked >= $max_checks)
						{
							pauseAttachmentMaintenance($to_fix, $attachment_count, $this->starting_substep, $this->substep, $this->step, $fix_errors);
						}
					}
				}
				catch (UnexpectedValueException)
				{
					// @todo for now do nothing...
				}
			}

			$this->step = 5;
			$this->substep = 0;
			pauseAttachmentMaintenance($to_fix, 0, $this->starting_substep, $this->substep, $this->step, $fix_errors);
		}

		// Got here we must be doing well - just the template! :D
		$context['page_title'] = $txt['repair_attachments'];
		$context[$context['admin_menu_name']]['current_subsection'] = 'maintenance';
		$context['sub_template'] = 'attachment_repair';

		// What stage are we at?
		$context['completed'] = $fix_errors;
		$context['errors_found'] = false;
		foreach ($context['repair_errors'] as $number)
		{
			if (!empty($number))
			{
				$context['errors_found'] = true;
				break;
			}
		}
	}

	/**
	 * Function called in-between each round of attachments and avatar repairs.
	 *
	 * What it does:
	 *
	 * - Called by repairAttachments().
	 * - If repairAttachments() has more steps added, this function needs to be updated!
	 *
	 * @param array $to_fix attachments to fix
	 * @param int $max_substep = 0
	 * @throws \ElkArte\Exceptions\Exception
	 * @todo Move to ManageAttachments.subs.php
	 */
	private function _pauseAttachmentMaintenance(array $to_fix, int $max_substep = 0): void
	{
		global $context, $txt, $time_start;

		// Try to get more time...
		detectServer()->setTimeLimit(600);

		// Have we already used our maximum time?
		if (microtime(true) - $time_start < 3 || $this->starting_substep == $this->substep)
		{
			return;
		}

		$context['continue_get_data'] = '?action=admin;area=manageattachments;sa=repair' . ($this->_req->hasQuery('fixErrors') ? ';fixErrors' : '') . ';step=' . $this->step . ';substep=' . $this->substep . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = '2';
		$context['sub_template'] = 'not_done';

		// Specific items to not break this template!
		$context[$context['admin_menu_name']]['current_subsection'] = 'maintenance';

		// Change these two if more steps are added!
		if (empty($max_substep))
		{
			$context['continue_percent'] = round(($this->step * 100) / 25);
		}
		else
		{
			$context['continue_percent'] = round(($this->step * 100 + ($this->substep * 100) / $max_substep) / 25);
		}

		// Never more than 100%!
		$context['continue_percent'] = min($context['continue_percent'], 100);

		// Save the necessary information for the next look
		$_SESSION['attachments_to_fix'] = $to_fix;
		$_SESSION['attachments_to_fix2'] = $context['repair_errors'];

		obExit();
	}

	/**
	 * This function lists and allows updating of multiple attachments paths.
	 */
	public function action_attachpaths(): void
	{
		global $modSettings, $context, $txt;

		$attachmentsDir = new AttachmentsDirectory($modSettings, database());
		$errors = [];

		// Saving or changing attachment paths
		if ($this->_req->hasPost('save'))
		{
			$this->_savePaths($attachmentsDir);
		}

		// Saving a base directory?
		if ($this->_req->hasPost('save2'))
		{
			$this->_saveBasePaths($attachmentsDir);
		}

		// Have some errors to show?
		if (isset($_SESSION['errors']))
		{
			if (is_array($_SESSION['errors']))
			{
				if (!empty($_SESSION['errors']['dir']))
				{
					foreach ($_SESSION['errors']['dir'] as $error)
					{
						$errors['dir'][] = Util::htmlspecialchars($error, ENT_QUOTES);
					}
				}

				if (!empty($_SESSION['errors']['base']))
				{
					foreach ($_SESSION['errors']['base'] as $error)
					{
						$errors['base'][] = Util::htmlspecialchars($error, ENT_QUOTES);
					}
				}
			}

			unset($_SESSION['errors']);
		}

		// Show the list of base and path directories + any errors generated
		$listOptions = [
			'id' => 'attach_paths',
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths', '{sesstion_data}']),
			'title' => $txt['attach_paths'],
			'get_items' => [
				'function' => 'list_getAttachDirs',
			],
			'columns' => [
				'current_dir' => [
					'header' => [
						'value' => $txt['attach_current'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static fn($rowData) => '<input type="radio" name="current_dir" value="' . $rowData['id'] . '" ' . ($rowData['current'] ? ' checked="checked"' : '') . (empty($rowData['disable_current']) ? '' : ' disabled="disabled"') . ' class="input_radio" />',
						'class' => 'grid8 centertext',
					],
				],
				'path' => [
					'header' => [
						'value' => $txt['attach_path'],
					],
					'data' => [
						'function' => static fn($rowData) => '
							<input type="hidden" name="dirs[' . $rowData['id'] . ']" value="' . $rowData['path'] . '" />
							<input type="text" size="40" name="dirs[' . $rowData['id'] . ']" value="' . $rowData['path'] . '"' . ((empty($rowData['disable_base_dir']) && empty($rowData['disable_current'])) ? '' : ' disabled="disabled"') . ' class="input_text"/>',
						'class' => 'grid50',
					],
				],
				'current_size' => [
					'header' => [
						'value' => $txt['attach_current_size'],
					],
					'data' => [
						'db' => 'current_size',
					],
				],
				'num_files' => [
					'header' => [
						'value' => $txt['attach_num_files'],
					],
					'data' => [
						'db' => 'num_files',
					],
				],
				'status' => [
					'header' => [
						'value' => $txt['attach_dir_status'],
					],
					'data' => [
						'db' => 'status',
						'class' => 'grid20',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths', '{sesstion_data}']),
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					<input type="submit" name="new_path" value="' . $txt['attach_add_path'] . '" />
					<input type="submit" name="save" value="' . $txt['save'] . '" />',
				],
				empty($errors['dir']) ? [
					'position' => 'top_of_list',
					'value' => $txt['attach_dir_desc'],
					'style' => 'padding: 5px 10px;',
					'class' => 'description'
				] : [
					'position' => 'top_of_list',
					'value' => $txt['attach_dir_save_problem'] . '<br />' . implode('<br />', $errors['dir']),
					'style' => 'padding-left: 2.75em;',
					'class' => 'warningbox',
				],
			],
		];
		createList($listOptions);

		if (!empty($modSettings['attachment_basedirectories']))
		{
			$listOptions2 = [
				'id' => 'base_paths',
				'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths', '{sesstion_data}']),
				'title' => $txt['attach_base_paths'],
				'get_items' => [
					'function' => 'list_getBaseDirs',
				],
				'columns' => [
					'current_dir' => [
						'header' => [
							'value' => $txt['attach_current'],
							'class' => 'centertext',
						],
						'data' => [
							'function' => static fn($rowData) => '<input type="radio" name="current_base_dir" value="' . $rowData['id'] . '" ' . ($rowData['current'] ? ' checked="checked"' : '') . ' class="input_radio" />',
							'class' => 'grid8 centertext',
						],
					],
					'path' => [
						'header' => [
							'value' => $txt['attach_path'],
						],
						'data' => [
							'db' => 'path',
							'class' => 'grid50',
						],
					],
					'num_dirs' => [
						'header' => [
							'value' => $txt['attach_num_dirs'],
						],
						'data' => [
							'db' => 'num_dirs',
						],
					],
					'status' => [
						'header' => [
							'value' => $txt['attach_dir_status'],
						],
						'data' => [
							'db' => 'status',
							'class' => 'grid20',
						],
					],
				],
				'form' => [
					'href' => getUrl('admin', ['action' => 'admin', 'area' => 'manageattachments', 'sa' => 'attachpaths', '{sesstion_data}']),
				],
				'additional_rows' => [
					[
						'class' => 'submitbutton',
						'position' => 'below_table_data',
						'value' => '
							<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
							<input type="submit" name="new_base_path" value="' . $txt['attach_add_path'] . '" />
							<input type="submit" name="save2" value="' . $txt['save'] . '" />',
					],
					empty($errors['base']) ? [
						'position' => 'top_of_list',
						'value' => $txt['attach_dir_base_desc'],
						'style' => 'padding: 5px 10px;',
						'class' => 'description'
					] : [
						'position' => 'top_of_list',
						'value' => $txt['attach_dir_save_problem'] . '<br />' . implode('<br />', $errors['base']),
						'style' => 'padding-left: 2.75em',
						'class' => 'warningbox',
					],
				],
			];
			createList($listOptions2);
		}

		// Fix up our template.
		$context[$context['admin_menu_name']]['current_subsection'] = 'attachpaths';
		$context['page_title'] = $txt['attach_path_manage'];
	}

	/**
	 * Saves any changes or additions to the attachment paths
	 *
	 * @param AttachmentsDirectory $attachmentsDir
	 * @return void
	 */
	private function _savePaths(AttachmentsDirectory $attachmentsDir): void
	{
		global $txt, $context;

		checkSession();

		$current_dir = $this->_req->getPost('current_dir', 'intval', 1);
		$dirs = $this->_req->getPost('dirs', null, []);
		$new_dirs = [];

		// Can't use these directories for attachments
		require_once(SUBSDIR . '/Themes.subs.php');
		$themes = installedThemes();
		$reserved_dirs = [BOARDDIR, SOURCEDIR, SUBSDIR, CONTROLLERDIR, CACHEDIR, EXTDIR, LANGUAGEDIR, ADMINDIR, ADDONSDIR, ELKARTEDIR];
		foreach ($themes as $theme)
		{
			$reserved_dirs[] = $theme['theme_dir'];
		}

		foreach ($dirs as $id => $path)
		{
			$id = (int) $id;
			if ($id < 1)
			{
				continue;
			}

			// If it doesn't look like a directory, probably is not a directory
			$real_path = rtrim(trim($path), DIRECTORY_SEPARATOR);
			if (preg_match('~[/\\\\]~', $real_path) !== 1)
			{
				$real_path = realpath(BOARDDIR . DIRECTORY_SEPARATOR . ltrim($real_path, DIRECTORY_SEPARATOR));
			}

			// Hmm, a new path maybe?
			if ($attachmentsDir->directoryExists($id) === false)
			{
				// or is it?
				if ($attachmentsDir->directoryExists($path))
				{
					$errors[] = $path . ': ' . $txt['attach_dir_duplicate_msg'];
					continue;
				}

				// or is it a system dir?
				if (in_array($real_path, $reserved_dirs))
				{
					$errors[] = $real_path . ': ' . $txt['attach_dir_reserved'];
					continue;
				}

				// OK, so let's try to create it then.
				try
				{
					$attachmentsDir->createDirectory($path);
				}
				catch (Exception $e)
				{
					$errors[] = $path . ': ' . $txt[$e->getMessage()];
					continue;
				}
			}

			// Changing a directory name or trying to remove it entirely?
			try
			{
				if (!empty($path))
				{
					$attachmentsDir->rename($id, $real_path);
				}
				elseif ($attachmentsDir->delete($id, $real_path) === true)
				{
					// Don't add it back, it's now gone!
					continue;
				}
			}
			catch (Exception $e)
			{
				$errors[] = $real_path . ': ' . $txt[$e->getMessage()];
			}

			$new_dirs[$id] = $real_path;
		}

		// We need to make sure the current directory is right.
		if (empty($current_dir) && !empty($attachmentsDir->currentDirectoryId()))
		{
			$current_dir = $attachmentsDir->currentDirectoryId();
		}

		// Find the current directory if there's no value carried,
		if (empty($current_dir) || empty($new_dirs[$current_dir]))
		{
			$current_dir = $attachmentsDir->currentDirectoryId();
		}

		// If the user wishes to go back, update the last_dir array
		if ($current_dir !== $attachmentsDir->currentDirectoryId())
		{
			$attachmentsDir->updateLastDirs($current_dir);
		}

		// Going back to just one path?
		if (count($new_dirs) === 1)
		{
			// We might need to reset the paths. This loop will just loop through once.
			foreach ($new_dirs as $id => $dir)
			{
				if ($id !== 1)
				{
					updateAttachmentIdFolder($id, 1);
				}

				$update = ['currentAttachmentUploadDir' => 1, 'attachmentUploadDir' => serialize([1 => $dir]),];
			}
		}
		else
		{
			// Save it to the database.
			$update = ['currentAttachmentUploadDir' => $current_dir, 'attachmentUploadDir' => serialize($new_dirs),];
		}

		if (!empty($update))
		{
			updateSettings($update);
		}

		if (!empty($errors))
		{
			$_SESSION['errors']['dir'] = $errors;
		}

		redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Saves changes to the attachment base directory section
	 *
	 * @param AttachmentsDirectory $attachmentsDir
	 * @return void
	 */
	private function _saveBasePaths(AttachmentsDirectory $attachmentsDir): void
	{
		global $modSettings, $txt, $context;

		checkSession();

		$attachmentBaseDirectories = $attachmentsDir->getBaseDirs();
		$attachmentUploadDir = $attachmentsDir->getPaths();
		$current_base_dir = $this->_req->getPost('current_base_dir', 'intval', 1);
		$new_base_dir = $this->_req->getPost('new_base_dir', 'trim|htmlspecialchars', '');
		$base_dirs = $this->_req->getPost('base_dir');

		// Changing the current base directory?
		if (empty($new_base_dir) && !empty($current_base_dir) && $modSettings['basedirectory_for_attachments'] !== $attachmentUploadDir[$current_base_dir])
		{
			$update = ['basedirectory_for_attachments' => $attachmentUploadDir[$current_base_dir]];
		}

		// Modifying / Removing a basedir entry
		if (isset($base_dirs))
		{
			// Renaming the base dir, we can try
			foreach ($base_dirs as $id => $dir)
			{
				if (!empty($dir) && $dir !== $attachmentUploadDir[$id] && @rename($attachmentUploadDir[$id], $dir))
				{
					$attachmentUploadDir[$id] = $dir;
					$attachmentBaseDirectories[$id] = $dir;
					$update = (['attachmentUploadDir' => serialize($attachmentUploadDir), 'attachment_basedirectories' => serialize($attachmentBaseDirectories), 'basedirectory_for_attachments' => $attachmentUploadDir[$current_base_dir],]);
				}

				// Or remove it (from selection only)
				if (empty($dir))
				{
					// Can't remove the currently active one
					if ($id === $current_base_dir)
					{
						$errors[] = $attachmentUploadDir[$id] . ': ' . $txt['attach_dir_is_current'];
						continue;
					}

					// Removed from selection (not disc)
					unset($attachmentBaseDirectories[$id]);
					$update = ['attachment_basedirectories' => empty($attachmentBaseDirectories) ? '' : serialize($attachmentBaseDirectories), 'basedirectory_for_attachments' => $attachmentUploadDir[$current_base_dir] ?? '',];
				}
			}
		}

		// Adding a new one?
		if (!empty($new_base_dir))
		{
			$current_dir = $attachmentsDir->currentDirectoryId();

			// If it does not exist, try to create it.
			if (!in_array($new_base_dir, $attachmentUploadDir))
			{
				try
				{
					$attachmentsDir->createDirectory($new_base_dir);
				}
				catch (Exception)
				{
					$errors[] = $new_base_dir . ': ' . $txt['attach_dir_base_no_create'];
				}
			}

			// Find the new key
			$modSettings['currentAttachmentUploadDir'] = array_search($new_base_dir, $attachmentsDir->getPaths(), true);
			if (!in_array($new_base_dir, $attachmentBaseDirectories))
			{
				$attachmentBaseDirectories[$modSettings['currentAttachmentUploadDir']] = $new_base_dir;
			}

			ksort($attachmentBaseDirectories);
			$update = ['attachment_basedirectories' => serialize($attachmentBaseDirectories), 'basedirectory_for_attachments' => $new_base_dir, 'currentAttachmentUploadDir' => $current_dir,];
		}

		$_SESSION['errors']['base'] = $errors ?? null;

		if (!empty($update))
		{
			updateSettings($update);
		}

		redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Maintenance function to move attachments from one directory to another
	 */
	public function action_transfer(): void
	{
		global $modSettings, $txt;

		checkSession();

		$attachmentsDir = new AttachmentsDirectory($modSettings, database());

		// Clean the inputs
		$this->from = $this->_req->getPost('from', 'intval');
		$this->auto = $this->_req->getPost('auto', 'intval', 0);
		$this->to = $this->_req->getPost('to', 'intval');
		$emptyIt = !empty($this->_req->getPost('empty_it', null, ''));
		$start = $emptyIt ? 0 : (int) ($modSettings['attachmentDirFileLimit'] ?? 0);
		$_SESSION['checked'] = $emptyIt;

		// Prepare for the moving
		$limit = 501;
		$results = [];
		$dir_files = 0;
		$current_progress = 0;
		$total_moved = 0;
		$total_not_moved = 0;
		$total_progress = 0;

		// Need to know where we are moving things from
		if (empty($this->from) || (empty($this->auto) && empty($this->to)))
		{
			$results[] = $txt['attachment_transfer_no_dir'];
		}

		// Same location, that's easy
		if ($this->from === $this->to)
		{
			$results[] = $txt['attachment_transfer_same_dir'];
		}

		// No errors so determine how many we may have to move
		if (empty($results))
		{
			// Get the total file count for the progress bar.
			$total_progress = getFolderAttachmentCount($this->from);
			$total_progress -= $start;

			if ($total_progress < 1)
			{
				$results[] = $txt['attachment_transfer_no_find'];
			}
		}

		// Nothing to move (no files in a source or below the max limit)
		if (empty($results))
		{
			// Moving them automatically?
			if (!empty($this->auto))
			{
				$modSettings['automanage_attachments'] = 1;
				$tmpattachmentUploadDir = Util::unserialize($modSettings['attachmentUploadDir']);

				// Create a subdirectory off the root or from an attachment directory?
				$modSettings['use_subdirectories_for_attachments'] = $this->auto == -1 ? 0 : 1;
				$modSettings['basedirectory_for_attachments'] = serialize($this->auto > 0 ? $tmpattachmentUploadDir[$this->auto] : [1 => $modSettings['basedirectory_for_attachments']]);

				// Finally, where do they need to go?
				$attachmentDirectory = new AttachmentsDirectory($modSettings, database());
				$attachmentDirectory->automanageCheckDirectory(true);
				$new_dir = $attachmentDirectory->currentDirectoryId();
			}
			// Or to a specified directory
			else
			{
				$new_dir = $this->to;
			}

			$modSettings['currentAttachmentUploadDir'] = $new_dir;
			$break = false;
			while (!$break)
			{
				detectServer()->setTimeLimit(300);

				// If limits are set, get the file count and size for the destination folder
				if ($dir_files <= 0 && (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit'])))
				{
					$current_dir = attachDirProperties($new_dir);
					$dir_files = $current_dir['files'];
					$dir_size = $current_dir['size'];
				}

				// Find some attachments to move
				[$tomove_count, $tomove] = findAttachmentsToMove($this->from, $start, $limit);

				// Nothing found to move
				if ($tomove_count === 0)
				{
					if ($current_progress === 0)
					{
						$results[] = $txt['attachment_transfer_no_find'];
					}

					break;
				}

				// No more to move after this batch than set the finished flag.
				if ($tomove_count < $limit)
				{
					$break = true;
				}

				// Move them
				$moved = [];
				$dir_size = empty($dir_size) ? 0 : $dir_size;
				$limiting_by_size_num = $attachmentsDir->hasSizeLimit() || $attachmentsDir->hasNumFilesLimit();

				foreach ($tomove as $row)
				{
					$source = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
					$dest = $attachmentsDir->getPathById($new_dir) . '/' . basename($source);

					// Size and file count check
					if ($limiting_by_size_num)
					{
						$dir_files++;
						$dir_size += empty($row['size']) ? filesize($source) : $row['size'];

						// If we've reached a directory limit. Do something if we are in auto mode, otherwise set an error.
						if ($attachmentsDir->remainingSpace($dir_size) === 0 || $attachmentsDir->remainingFiles($dir_files) === 0)
						{
							// Since we're in auto mode. Create a new folder and reset the counters.
							$create_result = $attachmentsDir->manageBySpace();
							if ($create_result === true)
							{
								$results[] = sprintf($txt['attachments_transfered'], $total_moved, $attachmentsDir->getPathById($new_dir));
								if ($total_not_moved !== 0)
								{
									$results[] = sprintf($txt['attachments_not_transfered'], $total_not_moved);
								}

								$dir_files = 0;
								$total_moved = 0;
								$total_not_moved = 0;

								$break = false;
							}
							// Hmm, not in auto. Time to bail out then...
							else
							{
								$results[] = $txt['attachment_transfer_no_room'];
								$break = true;
							}
							break;
						}
					}

					// Actually move the file
					if (@rename($source, $dest))
					{
						$total_moved++;
						$current_progress++;
						$moved[] = $row['id_attach'];
					}
					else
					{
						$total_not_moved++;
					}
				}

				// Update the database to reflect the new file location
				if (!empty($moved))
				{
					moveAttachments($moved, $new_dir);
				}

				$new_dir = $modSettings['currentAttachmentUploadDir'];

				// Create / update the progress bar.
				// @todo why was this done this way?
				if (!$break)
				{
					$percent_done = min(round($current_progress / $total_progress * 100), 100);
					$progressBar = '
						<div class="progress_bar">
							<div class="green_percent" style="width: ' . $percent_done . '%;">' . $percent_done . '%</div>
						</div>';

					// Write it to a file so it can be displayed
					$fp = fopen(BOARDDIR . '/progress.php', 'wb');
					fwrite($fp, $progressBar);
					fclose($fp);
					usleep(500000);
				}
			}

			$results[] = sprintf($txt['attachments_transfered'], $total_moved, $attachmentsDir->getPathById($new_dir));
			if ($total_not_moved !== 0)
			{
				$results[] = sprintf($txt['attachments_not_transfered'], $total_not_moved);
			}
		}

		// All done, time to clean up
		$_SESSION['results'] = $results;
		$this->file_functions->delete(BOARDDIR . '/progress.php');

		redirectexit('action=admin;area=manageattachments;sa=maintenance#transfer');
	}
}
