<?php

function database()
{
	static $db = null;

	if ($db === null)
	{
		$db = \ElkArte\Tests\Dummies\Database::db();
	}

	return $db;
}