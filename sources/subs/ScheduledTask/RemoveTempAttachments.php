<?php

/**
 * Check for un-posted attachments is something we can do once in a while :P
 *
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
 * @version 1.0 Release Candidate 2
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

if (!defined('ELK'))
	die('No access...');

/**
 * Check for un-posted attachments is something we can do once in a while :P
 *
 * - This function uses opendir cycling through all the attachments
 *
 * @package ScheduledTasks
 */
class Remove_Temp_Attachments implements Scheduled_Task_Interface
{
	public function run()
	{
		global $context, $txt;

		// We need to know where this thing is going.
		require_once(SUBSDIR . '/ManageAttachments.subs.php');
		$attach_dirs = attachmentPaths();

		foreach ($attach_dirs as $attach_dir)
		{
			$dir = @opendir($attach_dir);
			if (!$dir)
			{
				loadEssentialThemeData();
				loadLanguage('Post');

				$context['scheduled_errors']['remove_temp_attachments'][] = $txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';
				Errors::instance()->log_error($txt['cant_access_upload_path'] . ' (' . $attach_dir . ')', 'critical');

				return false;
			}

			while ($file = readdir($dir))
			{
				if ($file == '.' || $file == '..')
					continue;

				if (strpos($file, 'post_tmp_') !== false)
				{
					// Temp file is more than 5 hours old!
					if (filemtime($attach_dir . '/' . $file) < time() - 18000)
						@unlink($attach_dir . '/' . $file);
				}
			}
			closedir($dir);
		}

		return true;
	}
}