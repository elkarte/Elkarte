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
use ElkArte\TemporaryAttachment;

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
		$prefix = $this->getTplName($userId, '');
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

		$this->data[$attachID]->remove($fatal);
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
	public function filesExist($userId)
	{
		$prefix = $this->getTplName($userId, '');
		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, $prefix) === false)
			{
				continue;
			}

			if ($attachment->fileExists())
			{
				unset($this->data['post']['files']);
				return true;
			}
		}
		return false;
	}

	public function removeExcept($keep, $userId)
	{
		$prefix = $this->getTplName($userId, '');
		foreach ($this->data as $attachID => $attachment)
		{
			if ((isset($this->data['post']['files'], $attachment['name']) && in_array($attachment['name'], $this->data['post']['files'])) || in_array($attachID, $keep) || strpos($attachID, $prefix) === false)
			{
				continue;
			}

			$attachment->remove(false);
			unset($this->data[$attachID]);
		}
	}

	/**
	 * Returns an array of names of temporary attachments for the specified user.
	 *
	 * @param int $userId
	 */
	public function getFileNames($userId)
	{
		$prefix = $this->getTplName($userId, '');

		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, $prefix) !== false)
			{
				$this->data['post']['files'][] = $attachment->getName();
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
	public function getTplName($userId, $hash = '')
	{
		return str_replace(['{user}', '{hash}'], [$userId, $hash], static::TMPNAME_TPL);
	}

	public function hasPostData()
	{
		return isset($this->data['post']);
	}

	public function addAttachment(TemporaryAttachment $data)
	{
		$this->data[$data['attachid']] = $data;
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
	public function getTempAttachById($attach_id, $attachmentsDir, $userId)
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
			if ($val['public_attachid'] === $public_attachid)
			{
				return $key;
			}
		}

		return $public_attachid;
	}

	/**
	 * Destroies all the attachments data in $_SESSION
	 */
	public function unset()
	{
		$this->data = [];
	}
}
