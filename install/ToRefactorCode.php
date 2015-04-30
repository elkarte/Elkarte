<?php

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

