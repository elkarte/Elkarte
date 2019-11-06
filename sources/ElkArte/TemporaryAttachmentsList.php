<?php

/**
 *
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\ValuesContainer;
use ElkArte\Exceptions\Exception as ElkException;
use \Exception as Exception;
use ElkArte\Graphics\Image;
use ElkArte\AttachmentsDirectory;

/**
 *
 */
class TemporaryAttachmentsList extends ValuesContainer
{
	const ID = 'temp_attachments';
	const TMPNAME_TPL = 'post_tmp_{user}_{hash}';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		if (!isset($_SESSION[static::ID]))
		{
			$_SESSION[static::ID] = [];
		}

		$this->data = &$_SESSION[static::ID];
	}

	/**
	 * Removes all the temporary attachments of the user
	 *
	 * @param int $userId
	 */
	public function removeAll($userId)
	{
		$prefix = $this->getFileName($userId, '');
		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, $prefix) !== false)
			{
				@unlink($attachment['tmp_name']);
				@unlink($attachment['tmp_name'] . '_thumb');
			}
		}
	}

	/**
	 * Deletes a temporary attachment from the $_SESSION (and the filesystem)
	 *
	 * @param string $attach_id the temporary name generated when a file is uploaded
	 *               and used in $_SESSION to help identify the attachment itself
	 * @param bool $fatal
	 */
	public function removeById($attachID, $fatal = true)
	{
		if ($fatal && !isset($this->data[$attachID]))
		{
			throw new \Exception('attachment_not_found');
		}

		if ($fatal && !file_exists($attach['tmp_name']))
		{
			throw new \Exception('attachment_not_found');
		}

		@unlink($this->data[$attachID]['tmp_name']);
		unset($this->data[$attachID]);
	}

	public function belongToBoard($board)
	{
		return empty($this->data['post']['msg']) && $this->data['post']['board'] == $board;
	}

	/**
	 * Checks if at least one temporary file for a certain user exists in the
	 * file system.
	 *
	 * @param int $userId
	 */
	public function fileExists($userId)
	{
		$prefix = $this->getFileName($userId, '');
		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, $prefix) === false)
			{
				continue;
			}

			if (file_exists($attachment['tmp_name']))
			{
				unset($this->data['post']['files']);
				return true;
			}
		}
		return false;
	}

	public function removeExcept($keep, $userId)
	{
		$prefix = $this->getFileName($userId, '');
		foreach ($this->data as $attachID => $attachment)
		{
			if ((isset($this->data['post']['files'], $attachment['name']) && in_array($attachment['name'], $this->data['post']['files'])) || in_array($attachID, $keep) || strpos($attachID, $prefix) === false)
			{
				continue;
			}

			unset($this->data[$attachID]);
			@unlink($attachment['tmp_name']);
		}
	}

	/**
	 * Returns an array of names of temporary attachments for the specified user.
	 *
	 * @param int $userId
	 */
	public function getFileNames($userId)
	{
		$prefix = $this->getFileName($userId, '');
		$file_list = [];

		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, $prefix) !== false)
			{
				$this->data['post']['files'][] = $attachment['name'];
			}
		}

		return $this->data['post']['files'];
	}

	/**
	 * Returns a single file name.
	 *
	 * @param int $userId
	 * @param string $hash
	 */
	public function getFileName($userId, $hash = '')
	{
		return str_replace(['{user}', '{hash}'], [$userId, $hash], static::TMPNAME_TPL);
	}

	public function hasPostData()
	{
		return isset($this->data['post']);
	}

	public function addAttachment($data)
	{
		$data['name'] = htmlspecialchars($data['name'], ENT_COMPAT, 'UTF-8');
		$data['public_attachid'] = $this->getFileName($data['user_id'], md5(mt_rand()));
		$this->data[$data['attachid']] = $data;
	}

	public function setAttachError($attachID, $error)
	{
		$this->data[$attachID]['errors'][] = $error;
	}

	public function hasAttachErrors($attachID)
	{
		return !empty($this->data[$attachID]['errors']);
	}

	public function getAttachErrors($attachID)
	{
		return $this->data[$attachID]['errors'];
	}

	public function getAttachName($attachID)
	{
		return $this->data[$attachID]['name'];
	}

	public function areLostAttachments()
	{
		return empty($this->data['post']['msg']);
	}

	public function getPostParam($idx)
	{
		return $this->data['post'][$idx] ?? null;
	}

	public function setPostParam(array $vals)
	{
		if (!isset($this->data['post']))
		{
			$this->data['post'] = [];
		}
		$this->data['post'] = array_merge($this->data['post'], $vals);
	}

	public function belongToMsg($msg)
	{
		return $this->data['post']['msg'] == $msg;
	}

	/**
	 * Checks if there is any attachment that has been processed
	 */
	public function hasAttachments()
	{
		return $this->count() > 0;
	}

	/**
	 * Finds and return a temporary attachment by its id
	 *
	 * @param string $attach_id the temporary name generated when a file is uploaded
	 *  and used in $_SESSION to help identify the attachment itself
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	function getTempAttachById($attach_id, $attachmentsDir, $userId)
	{
		$attach_real_id = null;

		if ($this->hasAttachments() === false)
		{
			throw new Exception('no_access');
		}

		foreach ($this->data as $attachID => $val)
		{
			if ($attachID === 'post')
			{
				continue;
			}

			if ($val['public_attachid'] === $attach_id)
			{
				$attach_real_id = $attachID;
				break;
			}
		}

		if (empty($attach_real_id))
		{
			throw new Exception('no_access');
		}

		// The common name form is "post_tmp_123_0ac9a0b1fc18604e8704084656ed5f09"
		$id_attach = preg_replace('~[^0-9a-zA-Z_]~', '', $attach_real_id);

		// Permissions: only temporary attachments
		if (substr($id_attach, 0, 8) !== 'post_tmp')
		{
			throw new Exception('no_access');
		}

		// Permissions: only author is allowed.
		$pieces = explode('_', substr($id_attach, 9));

		if (!isset($pieces[0]) || $pieces[0] != $userId)
		{
			throw new Exception('no_access');
		}

		$attach_dir = $attachmentsDir->getCurrent();

		if (file_exists($attach_dir . '/' . $attach_real_id) && isset($this->data[$attach_real_id]))
		{
			return $this->data[$attach_real_id];
		}

		throw new Exception('no_access');
	}

	/**
	 * Finds an attachment id from its public id
	 *
	 * @param string $public_attachid
	 *
	 * @return string
	 */
	function getIdFromPublic($public_attachid)
	{
		if ($this->hasAttachments() === false)
		{
			return $public_attachid;
		}

		foreach ($this->data as $key => $val)
		{
			if (isset($val['public_attachid']) && $val['public_attachid'] === $public_attachid)
			{
				return $key;
			}
		}

		return $public_attachid;
	}

	/**
	 * Performs various checks on an uploaded file.
	 *
	 * @param int $attachID id of the attachment to check
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception attach_check_nag
	 *
	 */
	public function attachmentChecks($attachID)
	{
		global $modSettings, $context;

		// If there were no errors to this point, we apply some additional checks
		if (!empty($this->data[$attachID]['errors']))
		{
			return;
		}
		// No data or missing data .... Not necessarily needed, but in case a mod author missed something.
		if (empty($this->data[$attachID]))
		{
			$error = '$_SESSION[\'temp_attachments\'][$attachID]';
		}
		elseif (empty($attachID))
		{
			$error = '$attachID';
		}
		elseif (empty($context['attachments']))
		{
			$error = '$context[\'attachments\']';
		}

		// Let's get their attention.
		if (!empty($error))
		{
			throw new ElkException('attach_check_nag', 'debug', array($error));
		}

		// Just in case this slipped by the first checks, we stop it here and now
		if ($this->data[$attachID]['size'] == 0)
		{
			$this->data['errors'][] = 'attach_0_byte_file';

			return false;
		}

		// First, the dreaded security check. Sorry folks, but this should't be avoided
		$image = new Image($this->data[$attachID]['tmp_name']);
		$size = $image->getSize($this->data[$attachID]['tmp_name']);
		$valid_mime = getValidMimeImageType($size[2]);

		if ($valid_mime !== '')
		{
			if (!$image->checkImageContents($this->data[$attachID]['tmp_name']))
			{
				// It's bad. Last chance, maybe we can re-encode it?
				if (empty($modSettings['attachment_image_reencode']) || (!$image->reencodeImage($this->data[$attachID]['tmp_name'])))
				{
					// Nothing to do: not allowed or not successful re-encoding it.
					$this->data[$attachID]['errors'][] = 'bad_attachment';

					return false;
				}
				else
				{
					$this->data[$attachID]['size'] = $image->getFilesize();
				}
			}
		}

		$attachmentDirectory = new AttachmentsDirectory($modSettings, database());
		try
		{
			$this->data[$attachID] = $attachmentDirectory->checkDirSpace($this->data[$attachID], $attachID);
		}
		catch (Exception $e)
		{
			$this->data[$attachID]['errors'][] = $e->getMessage();
		}

		// Is the file too big?
		if (!empty($modSettings['attachmentSizeLimit']) && $this->data[$attachID]['size'] > $modSettings['attachmentSizeLimit'] * 1024)
		{
			$this->data[$attachID]['errors'][] = array('file_too_big', array(comma_format($modSettings['attachmentSizeLimit'], 0)));
		}

		// Check the total upload size for this post...
		$context['attachments']['total_size'] += $this->data[$attachID]['size'];
		if (!empty($modSettings['attachmentPostLimit']) && $context['attachments']['total_size'] > $modSettings['attachmentPostLimit'] * 1024)
		{
			$this->data[$attachID]['errors'][] = array('attach_max_total_file_size', array(comma_format($modSettings['attachmentPostLimit'], 0), comma_format($modSettings['attachmentPostLimit'] - (($context['attachments']['total_size'] - $this->data[$attachID]['size']) / 1024), 0)));
		}

		// Have we reached the maximum number of files we are allowed?
		$context['attachments']['quantity']++;

		// Set a max limit if none exists
		if (empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] >= 50)
		{
			$modSettings['attachmentNumPerPostLimit'] = 50;
		}

		if (!empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] > $modSettings['attachmentNumPerPostLimit'])
		{
			$this->data[$attachID]['errors'][] = array('attachments_limit_per_post', array($modSettings['attachmentNumPerPostLimit']));
		}

		// File extension check
		if (!empty($modSettings['attachmentCheckExtensions']))
		{
			$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
			foreach ($allowed as $k => $dummy)
			{
				$allowed[$k] = trim($dummy);
			}

			if (!in_array(strtolower(substr(strrchr($this->data[$attachID]['name'], '.'), 1)), $allowed))
			{
				$allowed_extensions = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
				$this->data[$attachID]['errors'][] = array('cant_upload_type', array($allowed_extensions));
			}
		}

		// Undo the math if there's an error
		if (!empty($this->data[$attachID]['errors']))
		{
			if (isset($context['dir_size']))
			{
				$context['dir_size'] -= $this->data[$attachID]['size'];
			}
			if (isset($context['dir_files']))
			{
				$context['dir_files']--;
			}

			$context['attachments']['total_size'] -= $this->data[$attachID]['size'];
			$context['attachments']['quantity']--;

			return false;
		}

		return true;
	}

	public function autoRotate($attachID)
	{
		if (empty($this->data[$attachID]['errors']) && substr($this->data[$attachID]['type'], 0, 5) === 'image')
		{
			$image = new Image($this->data[$attachID]['tmp_name']);
			if ($image->autoRotateImage())
			{
				$image->saveImage($this->data[$attachID]['tmp_name'], IMAGETYPE_JPEG, 95);
				$this->data[$attachID]['size'] = filesize($this->data[$attachID]['tmp_name']);
			}
		}
	}

	/**
	 * Destroies all the attachments data in $_SESSION
	 */
	public function unset()
	{
		$this->data = [];
	}
}
