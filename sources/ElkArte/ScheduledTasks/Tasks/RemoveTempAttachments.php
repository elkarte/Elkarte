<?php

/**
 * Check for un-posted attachments is something we can do once in a while :P
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

namespace ElkArte\ScheduledTasks\Tasks;

use ElkArte\Attachments\AttachmentsDirectory;
use ElkArte\Errors\Errors;
use ElkArte\Helper\FileFunctions;
use ElkArte\Languages\Txt;

/**
 * Class Remove_Temp_Attachments - Check for un-posted attachments is something we can
 * do once in a while :P
 *
 * - This function uses \FilesystemIterator cycling through all the attachments
 *
 * @package ScheduledTasks
 */
class RemoveTempAttachments implements ScheduledTaskInterface
{
	/**
	 * Clean up the file system by removing up-posted or failed attachments
	 *
	 * @return bool
	 * @return bool
	 */
	public function run()
	{
		global $modSettings;

		$success = true;

		// We need to know where this thing is going.
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());
		$attach_dirs = $attachmentsDir->getPaths();

		foreach ($attach_dirs as $attach_dir)
		{
			if (!$this->removeTempFiles($attach_dir, 'post_tmp_'))
			{
				$success = false;
			}
		}

		// Any avatar temp files?
		if (!empty($modSettings['custom_avatar_dir']) && is_dir($modSettings['custom_avatar_dir']))
		{
			if (!$this->removeTempFiles($modSettings['custom_avatar_dir'], 'avatar_tmp_', true))
			{
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Removes temporary files from a directory.
	 *
	 * @param string $dir The directory to clean up.
	 * @param string $pattern The pattern to match.
	 * @param bool $noExtension If true, only files without extensions will be removed.
	 *
	 * @return bool
	 */
	private function removeTempFiles($dir, $pattern, $noExtension = false)
	{
		global $context, $txt;

		try
		{
			$fileFunc = FileFunctions::instance();
			$files = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
			foreach ($files as $file)
			{
				if (!str_contains($file->getFilename(), $pattern))
				{
					continue;
				}

				if ($noExtension && !empty($file->getExtension()))
				{
					continue;
				}

				// Temp file is more than 5 hours old!
				if ($file->getMTime() >= time() - 18000)
				{
					continue;
				}

				$fileFunc->delete($file->getPathname());
			}
		}
		catch (\UnexpectedValueException $e)
		{
			Txt::load('Post');

			$context['scheduled_errors']['remove_temp_attachments'][] = $txt['cant_access_upload_path'] . ' (' . $dir . ')';
			Errors::instance()->log_error($txt['cant_access_upload_path'] . ' (' . $e->getMessage() . ')', 'critical');

			return false;
		}

		return true;
	}
}
