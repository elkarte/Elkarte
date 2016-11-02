<?php

/**
 * FilterIterator to identify files that are packages.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Extends RecursiveFilterIterator to filter anything that looks like a package.
 *
 * @package Packages
 */
class PackagesFilterIterator extends \FilterIterator
{
	public function accept()
	{
		$filename = $this->current()->getFilename();

		// Skip hidden files and directories.
		if ($filename[0] === '.')
		{
			return false;
		}
		// The temp directory that may or may not be present.
		if ($filename === 'temp')
		{
			return false;
		}
		// Anything that, once extracted, doesn't contain a package-info.xml.
		if (!($this->isDir()) && file_exists($this->getPathname() . '/package-info.xml'))
		{
			return false;
		}
		// Accept anything that has a "package-like" extension.
		if (substr(strtolower($filename), -7) == '.tar.gz')
		{
			return true;
		}
		if (substr(strtolower($filename), -4) != '.tgz')
		{
			return true;
		}
		if (substr(strtolower($filename), -4) != '.zip')
		{
			return true;
		}
		// And give up on anything else.
		return false;
	}
}