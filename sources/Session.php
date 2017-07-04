<?php

/**
 * Implementation of PHP's session API.
 *
 * What it does:
 *
 *  - It handles the session data in the database (more scalable.)
 *  - It uses the databaseSession_lifetime setting for garbage collection.
 *  - The custom session handler is set by loadSession().
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Attempt to start the session, unless it already has been.
 */
function loadSession()
{
	global $modSettings, $boardurl, $sc;

	// Attempt to change a few PHP settings.
	@ini_set('session.use_cookies', true);
	@ini_set('session.use_only_cookies', false);
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', false);
	@ini_set('arg_separator.output', '&amp;');

	if (!empty($modSettings['globalCookies']))
	{
		$parsed_url = parse_url($boardurl);

		if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			@ini_set('session.cookie_domain', '.' . $parts[1]);
	}

	// @todo Set the session cookie path?
	// If it's already been started... probably best to skip this.
	if ((ini_get('session.auto_start') == 1 && !empty($modSettings['databaseSession_enable'])) || session_id() == '')
	{
		// Attempt to end the already-started session.
		if (ini_get('session.auto_start') == 1)
			session_write_close();

		// This is here to stop people from using bad junky PHPSESSIDs.
		if (isset($_REQUEST[session_name()]) && preg_match('~^[A-Za-z0-9,-]{16,64}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()]))
		{
			$tokenizer = new Token_Hash();
			$session_id = hash('md5', hash('md5', 'elk_sess_' . time()) . $tokenizer->generate_hash(8));
			$_REQUEST[session_name()] = $session_id;
			$_GET[session_name()] = $session_id;
			$_POST[session_name()] = $session_id;
		}

		// Use database sessions?
		if (!empty($modSettings['databaseSession_enable']))
		{
			@ini_set('session.serialize_handler', 'php');
			@ini_set('session.gc_probability', '1');

			$handler = new ElkArte\sources\subs\SessionHandler\DatabaseHandler(database());
			session_set_save_handler(
				array($handler, 'open'),
				array($handler, 'close'),
				array($handler, 'read'),
				array($handler, 'write'),
				array($handler, 'destroy'),
				array($handler, 'gc')
			);

			/*
			 * Avoid unexpected side-effects from the way PHP
			 * internally destroys objects on shutdown.
			 *
			 * See notes on http://php.net/manual/en/function.session-set-save-handler.php
			 */
			register_shutdown_function('session_write_close');
		}
		elseif (ini_get('session.gc_maxlifetime') <= 1440 && !empty($modSettings['databaseSession_lifetime']))
		{
			@ini_set('session.gc_maxlifetime', max($modSettings['databaseSession_lifetime'], 60));

			// APC destroys static class members before sessions can be written.  To work around this we
			// explicitly call session_write_close on script end/exit bugs.php.net/bug.php?id=60657
			if (extension_loaded('apc') && ini_get('apc.enabled') && !extension_loaded('apcu'))
				register_shutdown_function('session_write_close');
		}

		// Start the session
		session_start();

		// Change it so the cache settings are a little looser than default.
		if (!empty($modSettings['databaseSession_loose']) || (isset($_REQUEST['action']) && $_REQUEST['action'] == 'search'))
			header('Cache-Control: private');
	}

	// Set the randomly generated code.
	if (!isset($_SESSION['session_var']))
	{
		$tokenizer = new Token_Hash();
		$_SESSION['session_value'] = $tokenizer->generate_hash(32, session_id());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', $tokenizer->generate_hash(16, session_id())), 0, rand(7, 12));
	}

	$sc = $_SESSION['session_value'];
}
