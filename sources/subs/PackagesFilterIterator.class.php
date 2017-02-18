<?php

/**
 * FilterIterator to identify files that are packages.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Extends RecursiveFilterIterator to filter anything that looks like a package.
 *
 * @package Packages
 */
class PackagesFilterIterator extends \FilterIterator
{
	/**
	 * Identify if a file is a valid package type.
	 *
	 * @return bool
	 */
	public function accept()
	{
		$current = $this->current();
		$filename = $current->getFilename();

		// Skip hidden files and directories.
		if ($filename[0] === '.')
		{
			return false;
		}

		// The temp directory that may or may not be present.
		if ($current->isDir() && ($filename === 'temp' || $filename === 'backup'))
		{
			return false;
		}

		// Anything that, once extracted, doesn't contain a package-info.xml.
		if ($current->isDir())
		{
			return file_exists($current->getPathname() . '/package-info.xml');
		}

		// And finally, accept anything that has a "package-like" extension.
		return
			substr(strtolower($filename), -7) === '.tar.gz'
			|| substr(strtolower($filename), -4) === '.tgz'
			|| substr(strtolower($filename), -4) === '.zip';
	}
}
