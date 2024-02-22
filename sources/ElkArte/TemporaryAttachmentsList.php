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

/**
 * Overall List bag for interfacing/finding individual TemporaryAttachment bags
 */
class TemporaryAttachmentsList extends ValuesContainer
{
	public const ID = 'temp_attachments';

	/** @var string name we store temporary attachments under */
	public const TMPNAME_TPL = 'post_tmp_{user}_{hash}';

	/** @var string System level error, such as permissions issue to a folder */
	protected $sysError = '';

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
	public function removeAll($userId = null)
	{
		$prefix = $userId === null ? $this->getTplName('', '')[0] : $this->getTplName($userId, '');

		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, (string) $prefix) !== false)
			{
				$this->remove($attachment['tmp_name']);
				$this->remove($attachment['tmp_name'] . '_thumb');
			}
		}
	}

	/**
	 * Deletes a temporary attachment from the filesystem
	 *
	 * @param string $file
	 * @return bool
	 */
	public function remove($file)
	{
		// Must exist and have edit permissions
		return FileFunctions::instance()->delete($file);
	}

	/**
	 * Sets the error message of a problem that prevents any attachment to be uploaded or saved
	 *
	 * @param string $msg
	 */
	public function setSystemError($msg)
	{
		$this->sysError = $msg;
	}

	/**
	 * Returns the error message of the problem that stops attachments
	 *
	 * @return string
	 */
	public function getSystemError()
	{
		return $this->sysError;
	}

	/**
	 * Is there any error that prevents the system to upload any attachment?
	 *
	 * @return bool
	 */
	public function hasSystemError()
	{
		return !empty($this->sysError);
	}

	/**
	 * Deletes a temporary attachment from the TemporaryAttachment array (and the filesystem)
	 *
	 * @param string $attachID the temporary name generated when a file is uploaded
	 *               and used in $_SESSION to help identify the attachment itself
	 * @param bool $fatal
	 * @throws \Exception if fatal is true
	 */
	public function removeById($attachID, $fatal = true)
	{
		if ($fatal && !isset($this->data[$attachID]))
		{
			throw new \Exception('attachment_not_found');
		}

		if ($fatal && !$this->data[$attachID]->fileExists())
		{
			throw new \Exception('attachment_not_found');
		}

		$this->data[$attachID]->remove($fatal);
		unset($this->data[$attachID]);
	}

	/**
	 * Validates that a new message is bound to a given board
	 *
	 * @param int $board
	 * @return bool
	 */
	public function belongToBoard($board)
	{
		return empty($this->data['post']['msg']) && (int) $this->data['post']['board'] === (int) $board;
	}

	/**
	 * Checks if at least one temporary file for a certain user exists in the
	 * file system.
	 *
	 * @param int $userId
	 * @return bool
	 */
	public function filesExist($userId)
	{
		$prefix = $this->getTplName($userId, '');
		/** @var TemporaryAttachment $attachment */
		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, (string) $prefix) === false)
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

	/**
	 * Remove attachment files that we do not want to keep
	 *
	 * @param string[] $keep
	 * @param int $userId
	 */
	public function removeExcept($keep, $userId)
	{
		$prefix = $this->getTplName($userId, '');

		foreach ($this->data as $attachID => $attachment)
		{
			if ((isset($this->data['post']['files'], $attachment['name']) && in_array($attachment['name'], $this->data['post']['files'], true))
				|| in_array($attachID, $keep)
				|| strpos($attachID, (string) $prefix) === false)
			{
				continue;
			}

			// Remove this one from our data array and the filesystem
			$attachment->remove(false);
			unset($this->data[$attachID]);
		}
	}

	/**
	 * Returns an array of names of temporary attachments for the specified user.
	 *
	 * @param int $userId
	 * @return mixed
	 */
	public function getFileNames($userId)
	{
		$prefix = $this->getTplName($userId, '');

		foreach ($this->data as $attachID => $attachment)
		{
			if (strpos($attachID, (string) $prefix) !== false)
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
	 * @return string|string[]
	 */
	public function getTplName($userId, $hash = '')
	{
		return str_replace(['{user}', '{hash}'], [$userId, $hash], static::TMPNAME_TPL);
	}

	/**
	 * If there is any post data available
	 *
	 * @return bool
	 */
	public function hasPostData()
	{
		return isset($this->data['post']);
	}

	/**
	 * Add file data, for those that passed upload tests, to the data attachid key
	 *
	 * @param TemporaryAttachment $data
	 */
	public function addAttachment($data)
	{
		$this->data[$data['attachid']] = $data;
	}

	public function getAttachment()
	{
		return $this->data;
	}

	public function areLostAttachments()
	{
		return empty($this->data['post']['msg']);
	}

	/**
	 * Return a post parameter like files, last_msg, topic, msg
	 *
	 * @param $idx
	 * @return mixed|null
	 */
	public function getPostParam($idx)
	{
		return $this->data['post'][$idx] ?? null;
	}

	/**
	 * Add post values to the data array in the post key
	 *
	 * @param array $vals
	 */
	public function setPostParam(array $vals)
	{
		if (!isset($this->data['post']))
		{
			$this->data['post'] = [];
		}

		$this->data['post'] = array_merge($this->data['post'], $vals);
	}

	/**
	 * If a temporary attachment is for this specific message
	 *
	 * @param int $msg
	 * @return bool
	 */
	public function belongToMsg($msg)
	{
		return (int) $this->data['post']['msg'] === (int) $msg;
	}

	/**
	 * Checks if there is any attachment that has been processed
	 */
	public function hasAttachments()
	{
		return $this->count() > 0;
	}

	/**
	 * Finds a temporary attachment by id
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
			throw new \Exception('no_access');
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
			throw new \Exception('no_access');
		}

		// The common name form is "post_tmp_123_0ac9a0b1fc18604e8704084656ed5f09"
		$id_attach = preg_replace('~[^0-9a-zA-Z_]~', '', $attach_real_id);

		// Permissions: only temporary attachments
		if (substr($id_attach, 0, 8) !== 'post_tmp')
		{
			throw new \Exception('no_access');
		}

		// Permissions: only author is allowed.
		$pieces = explode('_', substr($id_attach, 9));

		if (!isset($pieces[0]) || $pieces[0] != $userId)
		{
			throw new \Exception('no_access');
		}

		$attach_dir = $attachmentsDir->getCurrent();

		if (file_exists($attach_dir . '/' . $attach_real_id) && isset($this->data[$attach_real_id]))
		{
			return $this->data[$attach_real_id];
		}

		throw new \Exception('no_access');
	}

	/**
	 * Finds our private attachment id from its public id
	 *
	 * @param string $public_attachid
	 *
	 * @return string
	 */
	public function getIdFromPublic($public_attachid)
	{
		if ($this->hasAttachments() === false)
		{
			return $public_attachid;
		}

		foreach ($this->data as $key => $val)
		{
			if ($key === 'post')
			{
				continue;
			}

			$val = $val->toArray();
			if (!isset($val['public_attachid']))
			{
				continue;
			}

			if ($val['public_attachid'] !== $public_attachid)
			{
				continue;
			}

			return $key;
		}

		return $public_attachid;
	}

	/**
	 * Destroy all the attachment data in $_SESSION
	 * Maybe it should also do some cleanup?
	 */
	public function unset()
	{
		$this->data = [];
	}
}
