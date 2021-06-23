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
 * @version 2.0 dev
 *
 */

namespace ElkArte\ScheduledTasks\Tasks;

use ElkArte\Errors\Errors;
use ElkArte\Themes\ThemeLoader;

/**
 * Class Remove_Temp_Attachments - Check for un-posted attachments is something we can do once in a while :P
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
	 * @throws \Exception
	 */
	public function run()
	{
		global $context, $txt, $modSettings;

		// We need to know where this thing is going.
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());
		$attach_dirs = $attachmentsDir->getPaths();

		foreach ($attach_dirs as $attach_dir)
		{
			try
			{
				$files = new \FilesystemIterator($attach_dir, \FilesystemIterator::SKIP_DOTS);
				foreach ($files as $file)
				{
					if (strpos($file->getFilename(), 'post_tmp_') !== false)
					{
						// Temp file is more than 5 hours old!
						if ($file->getMTime() < time() - 18000)
						{
							@unlink($file->getPathname());
						}
					}
				}
			}
			catch (\UnexpectedValueException $e)
			{
				ThemeLoader::loadEssentialThemeData();
				ThemeLoader::loadLanguageFile('Post');

				$context['scheduled_errors']['remove_temp_attachments'][] = $txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';
				Errors::instance()->log_error($txt['cant_access_upload_path'] . ' (' . $e->getMessage() . ')', 'critical');

				return false;
			}
		}

		return true;
	}
}
