<?php

/**
 * This file has functions dealing with server config.
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

/**
 * Returns the current server load for nix systems
 *
 * - Used to enable / disable features based on current system overhead
 */
function detectServerLoad()
{
	if (stristr(PHP_OS, 'win'))
	{
		return false;
	}

	$cores = detectServerCores();

	// The internal function should always be available
	if (function_exists('sys_getloadavg'))
	{
		$sys_load = sys_getloadavg();

		return $sys_load[0] / $cores;
	}
	// Maybe someone has a custom compile
	else
	{
		$load_average = @file_get_contents('/proc/loadavg');

		if (!empty($load_average) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $load_average, $matches) != 0)
		{
			return (float) $matches[1] / $cores;
		}

		if (($load_average = @`uptime`) !== null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $load_average, $matches) != 0)
		{
			return (float) $matches[1] / $cores;
		}

		return false;
	}
}

/**
 * Determines the number of cpu cores available
 *
 * - Used to normalize server load based on cores
 *
 * @return int
 */
function detectServerCores()
{
	if (strpos(PHP_OS_FAMILY, 'Win') === 0)
	{
		$cores = getenv("NUMBER_OF_PROCESSORS") + 0;

		return $cores ?? 1;
	}

	$cores = @file_get_contents('/proc/cpuinfo');
	if (!empty($cores))
	{
		$cores = preg_match_all('~^physical id~m', $cores);
		if (!empty($cores))
		{
			return $cores;
		}
	}

	return 1;
}

/**
 * Return the total disk used and remaining free space.  Returns array as
 * 10.34MB, 110MB (used, free)
 *
 * @return bool|array
 */
function detectDiskUsage()
{
	global $boarddir;

	$diskTotal = disk_total_space($boarddir);
	$diskFree = disk_free_space($boarddir);

	if ($diskFree === false || $diskTotal === false)
	{
		return false;
	}

	return [thousands_format($diskTotal - $diskFree), thousands_format($diskFree)];
}

/**
 * Determine the number of days the server has been running since last reboot. *nix only
 *
 * @return bool|int
 */
function detectUpTime()
{
	if (strpos(PHP_OS_FAMILY, 'Win') === 0)
	{
		return false;
	}

	$upTime = trim(@file_get_contents('/proc/uptime'));
	if (!empty($upTime))
	{
		$upTime = (int) preg_replace('~\.\d+~', '', $upTime);

		return floor($upTime / 86400);
	}

	return 0;
}
