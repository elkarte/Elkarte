<?php

/**
 * This is file part of the handling of attachments
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

namespace ElkArte\Attachments;

/**
 * Right now just a shell to wrap some constants I'm not sure where to put.
 */
class Attachment
{
	const DL_TYPE_AVATAR = 'avatar';
	const DL_TYPE_THUMB = 'thumb';

	const DB_TYPE_ATTACH = 0;
	const DB_TYPE_AVATAR = 1;
	const DB_TYPE_THUMB = 3;
}