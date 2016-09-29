<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * This performs a table alter, but does it unbuffered so the script can time out professionally.
 *
 * @param string $change
 * @param int $substep
 * @param boolean $is_test
 */
function protected_alter($change, $substep, $is_test = false)
{
	global $db_prefix;

	$table = db_table();
	$db = database();

	// Firstly, check whether the current index/column exists.
	$found = false;
	if ($change['type'] === 'column')
	{
		$columns = $table->db_list_columns('{db_prefix}' . $change['table'], true);
		foreach ($columns as $column)
		{
			// Found it?
			if ($column['name'] === $change['name'])
			{
				$found |= 1;

				// Do some checks on the data if we have it set.
				if (isset($change['col_type']))
					$found &= $change['col_type'] === $column['type'];
				if (isset($change['null_allowed']))
					$found &= $column['null'] == $change['null_allowed'];
				if (isset($change['default']))
					$found &= $change['default'] === $column['default'];
			}
		}
	}
	elseif ($change['type'] === 'index')
	{
		$request = upgrade_query( '
			SHOW INDEX
			FROM ' . $db_prefix . $change['table']);
		if ($request !== false)
		{
			$cur_index = array();

			while ($row = $db->fetch_assoc($request))
				if ($row['Key_name'] === $change['name'])
					$cur_index[(int) $row['Seq_in_index']] = $row['Column_name'];

			ksort($cur_index, SORT_NUMERIC);
			$found = array_values($cur_index) === $change['target_columns'];

			$db->free_result($request);
		}
	}

	// If we're trying to add and it's added, we're done.
	if ($found && in_array($change['method'], array('add', 'change')))
		return true;
	// Otherwise if we're removing and it wasn't found we're also done.
	elseif (!$found && in_array($change['method'], array('remove', 'change_remove')))
		return true;
	// Otherwise is it just a test?
	elseif ($is_test)
		return false;

	// Not found it yet? Bummer! How about we see if we're currently doing it?
	$running = false;
	$found = false;
	while (1 == 1)
	{
		$request = upgrade_query('
			SHOW FULL PROCESSLIST');
		while ($row = $db->fetch_assoc($request))
		{
			if (strpos($row['Info'], 'ALTER TABLE ' . $db_prefix . $change['table']) !== false && strpos($row['Info'], $change['text']) !== false)
				$found = true;
		}

		// Can't find it? Then we need to run it fools!
		if (!$found && !$running)
		{
			$db->free_result($request);

			$success = upgrade_query('
				ALTER TABLE ' . $db_prefix . $change['table'] . '
				' . $change['text'], true) !== false;

			if (!$success)
				return false;

			// Return
			$running = true;
		}
		// What if we've not found it, but we'd ran it already? Must of completed.
		elseif (!$found)
		{
			$db->free_result($request);
			return true;
		}

		// Pause execution for a sec or three.
		sleep(3);

		// Can never be too well protected.
		nextSubstep($substep);
	}

	// Protect it.
	nextSubstep($substep);
}

/**
 * Performs the actual query against the db
 * Checks for errors so it can inform of issues
 *
 * @param string $string
 * @param boolean $unbuffered
 */
function upgrade_query($string, $unbuffered = false)
{
	global $db_connection, $db_server, $db_user, $db_passwd, $db_type, $command_line, $upcontext, $upgradeurl, $modSettings;
	global $db_name;

	// Retrieve our database
	$db = load_database();

	// Get the query result - working around some specific security - just this once!
	$modSettings['disableQueryCheck'] = true;
	$db->setUnbuffered($unbuffered);
	$result = $db->query('', $string, array('security_override' => true, 'db_error_skip' => true));
	$db->setUnbuffered(false);

	// Failure?!
	if ($result !== false)
		return $result;

	// Grab the error message and see if its failure worthy
	$db_error_message = $db->last_error($db_connection);

	// If MySQL we do something more clever.
	if ($db_type == 'mysql')
	{
		$mysql_errno = mysqli_errno($db_connection);
		$error_query = in_array(substr(trim($string), 0, 11), array('INSERT INTO', 'UPDATE IGNO', 'ALTER TABLE', 'DROP TABLE ', 'ALTER IGNOR'));

		// Error numbers:
		//    1016: Can't open file '....MYI'
		//    1050: Table already exists.
		//    1054: Unknown column name.
		//    1060: Duplicate column name.
		//    1061: Duplicate key name.
		//    1062: Duplicate entry for unique key.
		//    1068: Multiple primary keys.
		//    1072: Key column '%s' doesn't exist in table.
		//    1091: Can't drop key, doesn't exist.
		//    1146: Table doesn't exist.
		//    2013: Lost connection to server during query.
		if ($mysql_errno == 1016)
		{
			if (preg_match('~\'([^\.\']+)~', $db_error_message, $match) != 0 && !empty($match[1]))
				mysqli_query( '
					REPAIR TABLE `' . $match[1] . '`');

			$result = mysqli_query($string);
			if ($result !== false)
				return $result;
		}
		elseif ($mysql_errno == 2013)
		{
			$db_connection = mysqli_connect($db_server, $db_user, $db_passwd, $db_name);

			if ($db_connection)
			{
				$result = mysqli_query($string);

				if ($result !== false)
					return $result;
			}
		}
		// Duplicate column name... should be okay ;).
		elseif (in_array($mysql_errno, array(1060, 1061, 1068, 1091)))
			return false;
		// Duplicate insert... make sure it's the proper type of query ;).
		elseif (in_array($mysql_errno, array(1054, 1062, 1146)) && $error_query)
			return false;
		// Creating an index on a non-existent column.
		elseif ($mysql_errno == 1072)
			return false;
		elseif ($mysql_errno == 1050 && substr(trim($string), 0, 12) == 'RENAME TABLE')
			return false;
	}
	// If a table already exists don't go potty.
	else
	{
		if (in_array(substr(trim($string), 0, 8), array('CREATE T', 'CREATE S', 'DROP TABL', 'ALTER TA', 'CREATE I')))
		{
			if (strpos($db_error_message, 'exist') !== false)
				return true;
			// SQLite
			if (strpos($db_error_message, 'missing') !== false)
				return true;
		}
		elseif (strpos(trim($string), 'INSERT ') !== false)
		{
			if (strpos($db_error_message, 'duplicate') !== false)
				return true;
		}
	}

	// Get the query string so we pass everything.
	$query_string = '';
	foreach ($_GET as $k => $v)
		$query_string .= ';' . $k . '=' . $v;

	if (strlen($query_string) != 0)
		$query_string = '?' . substr($query_string, 1);

	if ($command_line)
	{
		echo 'Unsuccessful!  Database error message:', "\n", $db_error_message, "\n";
		die;
	}

	// Bit of a bodge - do we want the error?
	if (!empty($upcontext['return_error']))
	{
		$upcontext['error_message'] = $db_error_message;
		return false;
	}

	// Otherwise we have to display this somewhere appropriate if possible.
	$upcontext['forced_error_message'] = '
			<strong>Unsuccessful!</strong><br />

			<div style="margin: 2ex;">
				This query:
				<blockquote><span style="font-family: monospace;">' . nl2br(htmlspecialchars(trim($string))) . ';</span></blockquote>

				Caused the error:
				<blockquote>' . nl2br(htmlspecialchars($db_error_message)) . '</blockquote>
			</div>

			<form action="' . $upgradeurl . $query_string . '" method="post">
				<input type="submit" value="Try again" class="button_submit" />
			</form>
		</div>';

	upgradeExit();
}
