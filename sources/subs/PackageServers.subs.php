<?php

/**
 * Functions to support adding/removing/listing package servers
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

/**
 * Fetches a list of package servers.
 *
 * @param int|null $server
 * @return array
 * @throws \Exception
 * @package Packages
 */
function fetchPackageServers($server = null)
{
	$db = database();

	$servers = array();

	// Load the list of servers.
	$db->fetchQuery('
		SELECT 
			id_server, name, url
		FROM {db_prefix}package_servers' .
		(!empty($server) ? ' WHERE id_server = {int:current_server}' : ''),
		array(
			'current_server' => $server,
		)
	)->fetch_callback(
		function ($row) use (&$servers) {
			$servers[] = array(
				'name' => $row['name'],
				'url' => $row['url'],
				'id' => $row['id_server'],
			);
		}
	);

	return $servers;
}

/**
 * Delete a package server
 *
 * @param int $id
 * @throws \ElkArte\Exceptions\Exception
 * @package Packages
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
 * @param string $name
 * @param string $url
 * @throws \Exception
 * @package Packages
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
