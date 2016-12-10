<?php

/**
 * Functions to support adding/removing/listing package servers
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Fetches a list of package servers.
 *
 * @package Packages
 * @param int|null $server
 * @return array
 */
function fetchPackageServers($server = null)
{
	$db = database();

	$servers = array();

	// Load the list of servers.
	$request = $db->query('', '
		SELECT id_server, name, url
		FROM {db_prefix}package_servers' .
		(!empty($server) ? ' WHERE id_server = {int:current_server}' : ''),
		array(
			'current_server' => $server,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$servers[] = array(
			'name' => $row['name'],
			'url' => $row['url'],
			'id' => $row['id_server'],
		);
	}
	$db->free_result($request);

	return $servers;
}

/**
 * Delete a package server
 *
 * @package Packages
 * @param int $id
 */
function deletePackageServer($id)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}package_servers
		WHERE id_server = {int:current_server}',
		array(
			'current_server' => $id,
		)
	);
}

/**
 * Adds a new package server
 *
 * @package Packages
 * @param string $name
 * @param string $url
 */
function addPackageServer($name, $url)
{
	$db = database();

	$db->insert('',
		'{db_prefix}package_servers',
		array(
			'name' => 'string-255', 'url' => 'string-255',
		),
		array(
			$name, $url,
		),
		array('id_server')
	);
}