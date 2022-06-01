<?php

/**
 * The avatar is incredibly complicated, what with the options... and what not.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\Graphics\Image;
use ElkArte\HttpReq;
use ElkArte\Languages\Txt;
use ElkArte\TokenHash;

/**
 * Everything to do with avatar handling / processing
 *
 * What it does:
 *
 * - Handles the downloading of an avatar
 * - Handles the uploading of avatars
 * - Process avatar changes external, gravatar, uploaded, server supplied, etc
 */
class Avatars
{
	/** @var \ElkArte\HttpReq */
	private $req;

	/** @var \ElkArte\FileFunctions */
	private $file_functions;

	/** @var boolean */
	private $downloadedExternalAvatar;

	/** @var int */
	private $memID;

	/** @var string */
	private $uploadDir;

	/**
	 * Constructor, initialize processValue this with $value and let it get to work
	 */
	public function __construct()
	{
		global $context, $modSettings;

		// Some help from the bystanders
		require_once(SUBSDIR . '/Attachments.subs.php');
		require_once(SUBSDIR . '/ManageAttachments.subs.php');

		// Set our class values and objects
		$this->file_functions = FileFunctions::instance();
		$this->req = HttpReq::instance();
		$this->_setDownloadedExternalAvatar(false);
		$this->memID = (int) $context['id_member'];
		$this->uploadDir = $modSettings['custom_avatar_dir'];
	}

	/**
	 * Determines which action to call based on what was requested and what is
	 * actually available for use
	 *
	 * @param string $value
	 */
	public function processValue($value)
	{
		global $modSettings;

		// See if we need to process and external url that we save as a file
		$check = false;
		if ($value === 'external'
			&& !empty($modSettings['avatar_external_enabled'])
			&& ($this->_isValidHttp() || $this->_isValidHttps())
			&& !empty($modSettings['avatar_download_external']))
		{
			$check = $this->processExternalStored();
		}

		// The big ol' if/else/other
		if ($value === 'none')
		{
			$check = $this->processNone();
		}
		elseif ($value === 'server_stored' && !empty($modSettings['avatar_stored_enabled']))
		{
			$check = $this->processServerStored();
		}
		elseif ($value === 'gravatar' && !empty($modSettings['avatar_gravatar_enabled']))
		{
			$check = $this->processGravatar();
		}
		elseif ($value === 'external'
			&& !empty($modSettings['avatar_external_enabled'])
			&& ($this->_isValidHttp() || $this->_isValidHttps())
			&& empty($modSettings['avatar_download_external']))
		{
			$check = $this->processExternalUrl();
		}
		elseif (($value === 'upload' && !empty($modSettings['avatar_upload_enabled']))
			|| $this->_getDownloadedExternalAvatar())
		{
			$check = $this->processUploaded();
		}

		// Delete any temporary file if it still exists
		if (isset($_FILES['attachment']['tmp_name']))
		{
			$this->file_functions->delete($_FILES['attachment']['tmp_name']);
		}

		return $check ? true : 'bad_avatar';
	}

	/**
	 * Supplying an external url for your avatar that we download
	 *
	 * @throws \ElkArte\Exceptions\Exception attachments_no_write
	 */
	public function processExternalStored()
	{
		Txt::load('Post');

		// Download, save to temp name, and later processes it via processUploaded()
		require_once(SUBSDIR . '/Package.subs.php');
		$url = parse_url($this->req->getPost('userpicpersonal', 'trim', ''));
		$contents = fetch_web_data((empty($url['scheme']) ? 'https://' : $url['scheme'] . '://') . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path'])));
		if ($contents !== false)
		{
			// If the avatar custom upload directory is defunct, we end here
			if (!$this->file_functions->isWritable($this->uploadDir))
			{
				throw new Exception('Post.attachments_no_write', 'critical');
			}

			// Create a hashed name to save
			$tokenizer = new TokenHash();
			$avatar_name = 'avatar_tmp_' . $this->memID . '_' . $tokenizer->generate_hash(16);
			if (file_put_contents($this->uploadDir . '/' . $avatar_name, $contents) !== false)
			{
				// Flag it for latter processing
				$_FILES['attachment']['tmp_name'] = $this->uploadDir . '/' . $avatar_name;
				$this->_setdownloadedExternalAvatar(true);

				return true;
			}
		}

		return false;
	}

	/**
	 * Do nothing, a favored opportunity for many
	 */
	public function processNone()
	{
		global $profile_vars;

		// Don't want an avatar, remove anything saved
		$profile_vars['avatar'] = '';

		return $this->_resetAvatarData(true);
	}

	/**
	 * Use one of the many fantastic avatars provided by ElkArte or the Admin
	 */
	public function processServerStored()
	{
		global $modSettings, $profile_vars;

		// Use one that is available on the server
		$cat = $this->req->getPost('cat', 'trim', '');
		$file = $this->req->getPost('file', 'trim', '');

		$profile_vars['avatar'] = strtr(empty($file) ? $cat : $file, array('&amp;' => '&'));
		$profile_vars['avatar'] = preg_match('~^([\w _!@%*=\-#()\[\]&.,]+/)?[\w _!@%*=\-#()\[\]&.,]+$~', $profile_vars['avatar']) === 1
			&& preg_match('/\.\./', $profile_vars['avatar']) === 0
			&& $this->file_functions->fileExists($modSettings['avatar_directory'] . '/' . $profile_vars['avatar'])
				? ($profile_vars['avatar'] === 'blank.png' ? '' : $profile_vars['avatar']) : '';

		return $this->_resetAvatarData(true);
	}

	/**
	 * Use a Gravatar image based on your email address
	 */
	public function processGravatar()
	{
		global $profile_vars;

		// Gravatar is where its at
		$profile_vars['avatar'] = 'gravatar';

		return $this->_resetAvatarData(true);
	}

	/**
	 * Use an external URL to display your ugly mug.
	 */
	public function processExternalUrl()
	{
		global $profile_vars, $modSettings;

		// Supplying an external url for use
		$this->_resetAvatarData(true);

		$userPicPersonal = $this->req->getPost('userpicpersonal', 'trim', '');
		$profile_vars['avatar'] = str_replace(' ', '%20', preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $userPicPersonal));
		if (preg_match('~^https?:///?$~i', $profile_vars['avatar']) === 1)
		{
			$profile_vars['avatar'] = '';
		}
		// Trying to make us do something we'll regret?
		elseif ((!$this->_isValidHttp() && !$this->_isValidHttps()) || ($this->_isValidHttp() && detectServer()->supportsSSL()))
		{
			return false;
		}
		// Should we check dimensions?
		elseif (!empty($modSettings['avatar_max_height']) || !empty($modSettings['avatar_max_width']))
		{
			// Now let's validate the avatar is an image and accessible
			$sizes = url_image_size($profile_vars['avatar']);
			if (is_array($sizes) && (($sizes[0] > $modSettings['avatar_max_width'] && !empty($modSettings['avatar_max_width'])) || ($sizes[1] > $modSettings['avatar_max_height'] && !empty($modSettings['avatar_max_height']))))
			{
				// Houston, we have a problem. The avatar is too large!!
				if ($modSettings['avatar_action_too_large'] === 'option_refuse')
				{
					return false;
				}

				// Are we allowed to download and resize?
				if ($modSettings['avatar_action_too_large'] === 'option_download_and_resize')
				{
					if (!saveAvatar($profile_vars['avatar'], $this->memID, $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
					{
						return false;
					}

					return $this->_resetAvatarData();
				}
			}
		}

		return true;
	}

	/**
	 * Process an uploaded avatar, similar functionality to attaching an image in a post
	 *
	 * @return boolean
	 */
	public function processUploaded()
	{
		global $modSettings, $profile_vars;

		// Selected the upload avatar option and had one already uploaded before or didn't upload one.
		if (empty($_FILES['attachment']['name']) && !$this->_getDownloadedExternalAvatar())
		{
			$profile_vars['avatar'] = '';

			return true;
		}

		// Fresh upload, lets move it with a temp name, for a downloaded external this step has
		// already been completed.
		if (!$this->_getDownloadedExternalAvatar())
		{
			$this->_moveTempAvatar();
		}

		// If we can't load it, or it is not an image, remove it.
		$this->file_functions->chmod($_FILES['attachment']['tmp_name']);
		$image = new Image($_FILES['attachment']['tmp_name']);
		if (!$image->isImageLoaded())
		{
			unset($image);

			return false;
		}

		// Do any security checks now, although a re-encode could occur in subsequent steps
		// it is simpler and cleaner to do it now.
		if (!$image->checkImageContents())
		{
			// It's bad. Last chance, maybe we can re-encode it?
			if (empty($modSettings['avatar_reencode']) || (!$image->reEncodeImage()))
			{
				unset($image);

				return false;
			}
		}

		// Do any size manipulations as required
		$sizes = $image->getImageDimensions();
		if (!$this->prepareAvatarImage($sizes, $image))
		{
			return false;
		}

		$profile_vars['avatar'] = '';

		return true;
	}

	/**
	 * If the image resize options are set and the image is over limits, resize it.
	 *
	 * What it does:
	 * - Already the correct size, save it
	 * - Oversize, and we want the CSS to deal with it, save it
	 * - To big, and we want to reject it, reject it
	 * - To big, and we want to resize it, resize/save it
	 *
	 * @param array $sizes
	 * @param \ElkArte\Graphics\Image $image
	 * @return boolean
	 */
	public function prepareAvatarImage($sizes, $image)
	{
		global $modSettings;

		// Check whether the image is too large.
		if ((!empty($modSettings['avatar_max_width']) && $sizes[0] > $modSettings['avatar_max_width'])
			|| (!empty($modSettings['avatar_max_height']) && $sizes[1] > $modSettings['avatar_max_height']))
		{
			// To big and the option is to refuse it?
			if (empty($modSettings['avatar_action_too_large']) || $modSettings['avatar_action_too_large'] === 'option_refuse')
			{
				return false;
			}

			// To big and we can download and resize?
			if ($modSettings['avatar_action_too_large'] === 'option_download_and_resize')
			{
				// Attempt to resize and save it
				unset($image);
				if (!saveAvatar($_FILES['attachment']['tmp_name'], $this->memID, $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
				{
					return false;
				}

				return $this->_resetAvatarData();
			}
		}

		// To be here, the image is either under the size limit, or over with "CSS to resize" option.
		// Either way try to save as is.  This is the only path to keep an animated gif
		if (!$this->_saveUploadedAvatar($image))
		{
			return false;
		}

		return $this->_resetAvatarData();
	}

	/**
	 * Validates and saves an image as a users avatar
	 *
	 * Performs many similar functions as saveAvatar but unlike the former will
	 * not resize and allows saving in formats other than just png or jpg.
	 *
	 * Image has been loaded, validated as an image, and gone through security checks.
	 *
	 * @param \ElkArte\Graphics\Image $image
	 * @return boolean
	 */
	private function _saveUploadedAvatar($image)
	{
		global $modSettings;

		$db = database();

		$valid_avatar_extensions = [
			IMAGETYPE_GIF => 'gif',
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG => 'png',
			IMAGETYPE_BMP => 'bmp',
			IMAGETYPE_WBMP => 'bmp',
			IMAGETYPE_WEBP => 'webp'
		];

		// We only support a subset of image types, after all, it's only an avatar
		$sizes = $image->getImageDimensions();
		$extension = $valid_avatar_extensions[$sizes[2]] ?? '';
		if (empty($extension))
		{
			$extension = 'png';
			$sizes[2] = IMAGETYPE_PNG;
		}
		$preferred_format = (int) array_search($extension, $valid_avatar_extensions);
		$mime_type = getValidMimeImageType($sizes[2]);

		// Generate the final name
		$tokenizer = new TokenHash();
		$destName = 'avatar_' . $this->memID . '_' . $tokenizer->generate_hash(16) . '.' . $extension;
		$destinationPath = $this->uploadDir . '/' . $destName;

		// Booker T's Iconic Spinaroonie
		if (!empty($modSettings['attachment_autorotate']) && $extension === 'jpg')
		{
			$image->autoRotate();
		}

		// Since GD does not work with animated gif, we have some ugliness
		if ($extension === 'gif' && $image->getManipulator() === 'GD')
		{
			$success = rename($_FILES['attachment']['tmp_name'], $destinationPath);
			$file_size = FileFunctions::instance()->fileSize($destinationPath);
		}
		else
		{
			// The format *may* change depending on what was uploaded
			$success = $image->saveImage($destinationPath, $preferred_format);
			$file_size = $image->getFilesize();
			$sizes = $image->getImageDimensions();
		}

		// Done with this now
		unset($image);

		if ($success)
		{
			// Remove previous attachments this member might have had.
			removeAttachments(array('id_member' => $this->memID));

			$db->insert('',
				'{db_prefix}attachments',
				array(
					'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'fileext' => 'string', 'size' => 'int',
					'width' => 'int', 'height' => 'int', 'mime_type' => 'string', 'id_folder' => 'int',
				),
				array(
					$this->memID, 1, $destName, '', $extension, $file_size,
					(int) $sizes[0], (int) $sizes[1], $mime_type, 1,
				),
				array('id_attach')
			);

			// Retain this globally in case the script wants it.
			$modSettings['new_avatar_data'] = array(
				'id' => $db->insert_id('{db_prefix}attachments'),
				'filename' => $destName,
				'type' => 1,
			);

			return true;
		}

		return false;
	}

	/**
	 * Reset attachment avatar data after a successful save.
	 * $modSettings['new_avatar_data'] is set via the saveAvatar function
	 *
	 * @param boolean $reset true to set empty values
	 */
	private function _resetAvatarData($reset = false)
	{
		global $modSettings, $cur_profile;

		// Reset attachment avatar data.
		$cur_profile['id_attach'] = $reset ? 0 : $modSettings['new_avatar_data']['id'];
		$cur_profile['filename'] = $reset ? '' : $modSettings['new_avatar_data']['filename'];
		$cur_profile['attachment_type'] = $reset ? 0 : $modSettings['new_avatar_data']['type'];

		if ($reset)
		{
			removeAttachments(array('id_member' => $this->memID));
		}

		return true;
	}

	/**
	 * Move an uploaded avatar with a secure temp name to the custom avatar directory
	 * or die trying
	 *
	 * @throws \ElkArte\Exceptions\Exception attachments_no_write, attach_timeout
	 */
	private function _moveTempAvatar()
	{
		if (!$this->file_functions->isWritable($this->uploadDir))
		{
			throw new Exception('Post.attachments_no_write', 'critical');
		}

		$tokenizer = new TokenHash();
		$avatar_name = 'avatar_tmp_' . $this->memID . '_' . $tokenizer->generate_hash(16);
		if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $this->uploadDir . '/' . $avatar_name))
		{
			throw new Exception('Post.attach_timeout', 'critical');
		}

		$_FILES['attachment']['tmp_name'] = $this->uploadDir . '/' . $avatar_name;
	}

	/**
	 * Sets a value to downloadedExternalAvatar
	 *
	 * @param boolean $value
	 */
	private function _setDownloadedExternalAvatar($value)
	{
		$this->downloadedExternalAvatar = $value;
	}

	/**
	 * Returns the current value held in downloadedExternalAvatar
	 *
	 * @return bool
	 */
	private function _getDownloadedExternalAvatar()
	{
		return $this->downloadedExternalAvatar;
	}

	/**
	 * Helper to see if this is a valid http location
	 *
	 * @return bool
	 */
	private function _isValidHttp()
	{
		$userPicPersonal = $this->req->getPost('userpicpersonal', 'trim', '');

		return !empty($userPicPersonal)
			&& strpos($userPicPersonal, 'http://') === 0
			&& strlen($userPicPersonal) > 7;
	}

	/**
	 * Helper to see if this is a valid httpS location
	 *
	 * @return bool
	 */
	private function _isValidHttps()
	{
		$userPicPersonal = $this->req->getPost('userpicpersonal', 'trim', '');

		return !empty($userPicPersonal)
			&& strpos($userPicPersonal, 'https://') === 0
			&& strlen($userPicPersonal) > 8;
	}
}