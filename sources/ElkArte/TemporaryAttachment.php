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

/**
 *
 */
class TemporaryAttachment extends ValuesContainer
{
	/**
	 * {@inheritDoc}
	 */
	public function __construct($data = null)
	{
		$data['errors'] = [];

		parent::__construct($data);
	}

	/**
	 * Deletes a temporary attachment from the $_SESSION (and the filesystem)
	 *
	 * @param string $attach_id the temporary name generated when a file is uploaded
	 *               and used in $_SESSION to help identify the attachment itself
	 * @param bool $fatal
	 */
	public function remove($fatal = true)
	{
		$this->data['size'] = 0;
		$this->data['type'] = '';

		if ($fatal && !file_exists($this->data['tmp_name']))
		{
			throw new Exception('attachment_not_found');
		}

		@unlink($this->data['tmp_name']);
	}

	/**
	 * Checks if the file exists in the file system.
	 */
	public function fileExists()
	{
			return file_exists($this->data['tmp_name']);
	}

	/**
	 * Returns an array of names of temporary attachments for the specified user.
	 *
	 * @param int $userId
	 */
	public function getName()
	{
		return $this->data['name'];
	}

	public function addError($error)
	{
		$this->data['errors'][] = array_merge($this->data['errors'], (array) $error);
	}

	public function hasErrors()
	{
		return !empty($this->data['errors']);
	}

	public function getErrors()
	{
		return $this->data['errors'];
	}

	public function getRealName()
	{
		return $this->data['name'];
	}

	public function getSize()
	{
		return $this->data['size'];
	}

	public function moveTo($file_path)
	{
		rename($this->data['tmp_name'], $file_path . '/' . $this->data['attachid']);
		$this->data['tmp_name'] = $file_path;
	}

	public function moveUploaded($file_path)
	{
		$destName = $file_path . '/'. $this->data['attachid'];

		// Move the file to the attachments folder with a temp name for now.
		if (@move_uploaded_file($this->data['tmp_name'], $destName))
		{
			@chmod($destName, 0644);
		}
		else
		{
			$this->addError('attach_timeout');
			if (file_exists($this->data['tmp_name']))
			{
				unlink($this->data['tmp_name']);
			}
		}
	}

	public function setIdFolder($id)
	{
		$this->data['id_folder'] = $id;
	}

	/**
	 * Performs various checks on an uploaded file.
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception attach_check_nag
	 *
	 */
	public function doChecks($attachmentDirectory)
	{
		global $modSettings, $context;

		// If there were no errors to this point, we apply some additional checks
		if (!empty($this->data['errors']))
		{
			return;
		}
		if (empty($this->data['attachid']))
		{
			$error = 'attachid';
		}
		// @TODO this needs to go away, not sure where though.
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
		if ($this->data['size'] == 0)
		{
			$this->addError('attach_0_byte_file');

			return false;
		}

		// First, the dreaded security check. Sorry folks, but this should't be avoided
		$image = new Image($this->data['tmp_name']);
		$size = $image->getSize($this->data['tmp_name']);
		$valid_mime = getValidMimeImageType($size[2]);

		if ($valid_mime !== '')
		{
			if (!$image->checkImageContents($this->data['tmp_name']))
			{
				// It's bad. Last chance, maybe we can re-encode it?
				if (empty($modSettings['attachment_image_reencode']) || (!$image->reencodeImage($this->data['tmp_name'])))
				{
					// Nothing to do: not allowed or not successful re-encoding it.
					$this->addError('bad_attachment');

					return false;
				}
				else
				{
					$this->data['size'] = $image->getFilesize();
				}
			}
		}

		try
		{
			$attachmentDirectory->checkDirSpace($this);
		}
		catch (Exception $e)
		{
			$this->addError($e->getMessage());
		}

		// Is the file too big?
		if (!empty($modSettings['attachmentSizeLimit']) && $this->data['size'] > $modSettings['attachmentSizeLimit'] * 1024)
		{
			$this->addError([
				'file_too_big', [
					comma_format($modSettings['attachmentSizeLimit'], 0)
				]
			]);
		}

		// Check the total upload size for this post...
		$context['attachments']['total_size'] += $this->data['size'];
		if (!empty($modSettings['attachmentPostLimit']) && $context['attachments']['total_size'] > $modSettings['attachmentPostLimit'] * 1024)
		{
			$this->addError([
				'attach_max_total_file_size', [
					comma_format($modSettings['attachmentPostLimit'], 0),
					comma_format($modSettings['attachmentPostLimit'] - (($context['attachments']['total_size'] - $this->data['size']) / 1024), 0)
				]
			]);
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
			$this->addError([
				'attachments_limit_per_post', [
					$modSettings['attachmentNumPerPostLimit']
				]
			]);
		}

		// File extension check
		if (!empty($modSettings['attachmentCheckExtensions']))
		{
			$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
			foreach ($allowed as $k => $dummy)
			{
				$allowed[$k] = trim($dummy);
			}

			if (!in_array(strtolower(substr(strrchr($this->data['name'], '.'), 1)), $allowed))
			{
				$allowed_extensions = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
				$this->addError([
					'cant_upload_type', [
						$allowed_extensions
					]
				]);
			}
		}

		// Undo the math if there's an error
		if ($this->hasErrors())
		{
			if (isset($context['dir_size']))
			{
				$context['dir_size'] -= $this->data['size'];
			}
			if (isset($context['dir_files']))
			{
				$context['dir_files']--;
			}

			$context['attachments']['total_size'] -= $this->data['size'];
			$context['attachments']['quantity']--;

			return false;
		}

		return true;
	}

	public function autoRotate()
	{
		if ($this->hasErrors() === false && substr($this->data['type'], 0, 5) === 'image')
		{
			$image = new Image($this->data['tmp_name']);
			if ($image->autoRotateImage())
			{
				$image->saveImage($this->data['tmp_name'], IMAGETYPE_JPEG, 95);
				$this->data['size'] = filesize($this->data['tmp_name']);
			}
		}
	}
}
