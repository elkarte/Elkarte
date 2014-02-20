<?php

/**
 * This is used to find PHP files instead of using glob()
 */
class PHPFileIterator extends FilterIterator
{
	public function accept()
	{
		return 'php' === pathinfo(parent::current(), PATHINFO_EXTENSION);
	}
}