<?php

/**
 * Represents a list of temporary attachments for managing attachments in a session,
 * including operations to add, remove, and validate them.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\Attachments;

use ElkArte\Exceptions\Exception;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\ValuesContainer;

/**
 * Overall List bag for interfacing/finding individual TemporaryAttachment bags
 */
class TemporaryAttachmentsList extends ValuesContainer
{
	public const ID = 'temp_attachments';

	/** @var string name we store temporary attachments under */
	public const TMPNAME_TPL = 'post_tmp_{user}_{hash}';

	/** @var string System level error, such as permissions issue to a folder */
	protected string $sysError = '';

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

		parent::__construct($this->data);
	}

	/**
	 * Removes all the temporary attachments of the user
	 *
	 * @param int|null $userId
	 */
	public function removeAll(int $userId = null): void
	{
		$prefix = $userId === null ? $this->getTplName('')[0] : $this->getTplName($userId);

		foreach ($this->data as $attachID => $attachment)
		{
			if (str_contains($attachID, $prefix))
			{
				$path = $attachment['tmp_name'] ?? '';
				if ($path !== '')
				{
					$this->remove($path);
					$this->remove($path . '_thumb');
				}
			}
		}
	}

	/**
	 * Deletes a temporary attachment from the filesystem
	 *
	 * @param string $file
	 * @return bool
	 */
	public function remove(string $file): bool
	{
		// Must exist and have edit permissions
		if ($file === '')
		{
			return false;
		}

		return FileFunctions::instance()->delete($file);
	}

	/**
	 * Sets the error message of a problem that prevents any attachment to be uploaded or saved
	 *
	 * @param string $msg
	 */
	public function setSystemError(string $msg): void
	{
		$this->sysError = $msg;
	}

	/**
	 * Returns the error message of the problem that stops attachments
	 *
	 * @return string
	 */
	public function getSystemError(): string
	{
		return $this->sysError;
	}

	/**
	 * Is there any error that prevents the system to upload any attachment?
	 *
	 * @return bool
	 */
	public function hasSystemError(): bool
	{
		return !empty($this->sysError);
	}

	/**
	 * Deletes a temporary attachment from the TemporaryAttachment array (and the filesystem)
	 *
	 * @param string $attachID the temporary name generated when a file is uploaded
	 *               and used in $_SESSION to help identify the attachment itself
	 * @param bool $fatal
	 * @throws Exception if fatal is true
	 */
	public function removeById(string $attachID, bool $fatal = true): void
	{
		if ($fatal && !isset($this->data[$attachID]))
		{
			throw new Exception('attachment_not_found');
		}

		if ($fatal && !$this->data[$attachID]->fileExists())
		{
			throw new Exception('attachment_not_found');
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
	public function belongToBoard(int $board): bool
	{
		return empty($this->data['post']['msg']) && (int) $this->data['post']['board'] === $board;
	}

	/**
	 * Checks if at least one temporary file for a certain user exists in the file system.
	 *
	 * @param int $userId
	 * @return bool
	 */
	public function filesExist(int $userId): bool
	{
		$prefix = $this->getTplName($userId);
		/** @var TemporaryAttachment $attachment */
		foreach ($this->data as $attachID => $attachment)
		{
			if (!str_contains($attachID, $prefix))
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
	public function removeExcept(array $keep, int $userId): void
	{
		$prefix = $this->getTplName($userId);

		foreach ($this->data as $attachID => $attachment)
		{
			if ((isset($this->data['post']['files'], $attachment['name']) && in_array($attachment['name'], $this->data['post']['files'], true))
				|| in_array($attachID, $keep)
				|| !str_contains($attachID, $prefix))
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
	public function getFileNames(int $userId): mixed
	{
		$prefix = $this->getTplName($userId);

		foreach ($this->data as $attachID => $attachment)
		{
			if (str_contains($attachID, $prefix))
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
	 * @return string
	 */
	public function getTplName(int $userId, string $hash = ''): string
	{
		return str_replace(['{user}', '{hash}'], [$userId, $hash], static::TMPNAME_TPL);
	}

	/**
	 * If there is any post-data available
	 *
	 * @return bool
	 */
	public function hasPostData(): bool
	{
		return isset($this->data['post']);
	}

	/**
	 * Add file data, for those that passed upload tests, to the data attachid key
	 *
	 * @param TemporaryAttachment $data
	 */
	public function addAttachment(TemporaryAttachment $data): void
	{
		$this->data[$data['attachid']] = $data;
	}

	/**
	 * Retrieves the attachment data.
	 *
	 * @return array
	 */
	public function getAttachment(): array
	{
		return $this->data;
	}

	/**
	 * Checks if there are any lost attachments
	 *
	 * @return bool
	 */
	public function areLostAttachments(): bool
	{
		return empty($this->data['post']['msg']);
	}

	/**
	 * Return a post-parameter like files, last_msg, topic, msg
	 *
	 * @param $idx
	 * @return mixed|null
	 */
	public function getPostParam($idx): mixed
	{
		return $this->data['post'][$idx] ?? null;
	}

	/**
	 * Add post-values to the data array in the post-key
	 *
	 * @param array $vals
	 */
	public function setPostParam(array $vals): void
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
	public function belongToMsg(int $msg): bool
	{
		return (int) $this->data['post']['msg'] === $msg;
	}

	/**
	 * Checks if there is any attachment that has been processed
	 */
	public function hasAttachments(): bool
	{
		return $this->count() > 0;
	}

	/**
	 * Finds a temporary attachment by id
	 *
	 * @param string $attach_id the temporary name generated when a file is uploaded
	 *  and used in $_SESSION to help identify the attachment itself
	 * @param string $attachmentsDir
	 * @param int $userId
	 * @return mixed
	 * @throws Exception
	 */
	public function getTempAttachById(string $attach_id, $attachmentsDir, $userId): mixed
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
		if (!str_starts_with($id_attach, 'post_tmp'))
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

		if (isset($this->data[$attach_real_id]) && file_exists($attach_dir . '/' . $attach_real_id))
		{
			return $this->data[$attach_real_id];
		}

		throw new Exception('no_access');
	}

	/**
	 * Finds our private attachment id from its public id
	 *
	 * @param string $public_attachid
	 *
	 * @return string
	 */
	public function getIdFromPublic(string $public_attachid): string
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
	public function unset(): void
	{
		$this->data = [];
	}
}
