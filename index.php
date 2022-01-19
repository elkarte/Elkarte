<?php

/**
 * This, as you have probably guessed, is the crux for all functions.
 * Everything should start here, so all the setup and security is done
 * properly.
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

use ElkArte\Controller\ScheduledTasks;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;

// Bootstrap the system
require_once(dirname(__FILE__) . '/bootstrap.php');
new Bootstrap(false);

// Turn on output buffering if it isn't already on (via php.ini for example)
if (!ob_get_level())
{
	ob_start();
}

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if (isset($_GET['scheduled']))
{
	// Don't make people wait on us if we can help it.
	if (function_exists('fastcgi_finish_request'))
	{
		fastcgi_finish_request();
	}

	$controller = new ScheduledTasks(new EventManager());
	$controller->action_autotask();
}

// Check if compressed output is enabled, supported, and not already being done.
if (!empty($modSettings['enableCompressedOutput']) && !headers_sent())
{
	// If zlib is being used, turn off output compression.
	if (detectServer()->outPutCompressionEnabled())
	{
		$modSettings['enableCompressedOutput'] = 0;
	}
	else
	{
		@ob_end_clean();
		ob_start('ob_gzhandler');
	}
}

// Register error & exception handlers.
new ElkArte\Errors\ErrorHandler();

// Start the session. (assuming it hasn't already been.)
loadSession();

// Pre-dispatch
elk_main();

// Call obExit specially; we're coming from the main area ;).
obExit(null, null, true);

/**
 * The main dispatcher.
 * This delegates to each area.
 */
function elk_main()
{
	global $modSettings, $context;

	// A safer way to work with our form globals
	// @todo Use dependency injection
	$_req = HttpReq::instance();

	// What shall we do?
	$dispatcher = new ElkArte\SiteDispatcher($_req);

	if ($dispatcher->needSecurity())
	{
		// We should set our security headers now.
		frameOptionsHeader();
		securityOptionsHeader();

		// Load the user's cookie (or set as guest) and load their settings.
		User::load(true);
		$dispatcher->setUser(User::$info);

		// Load the current board's information.
		loadBoard();

		// Load the current user's permissions.
		loadPermissions();

		// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
		if ($dispatcher->needTheme())
		{
			// Do our BadBehavior checking before we go any further
			if (runBadBehavior())
			{
				// Not much to say, 403 and gone
				sleep(10);
				\ElkArte\Errors\Errors::instance()->display_403_error(true);
			}

			new ElkArte\Themes\ThemeLoader();

			// The parser is not an object just yet
			loadBBCParsers();
		}

		// Check if the user should be disallowed access.
		is_not_banned();

		// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
		if ($dispatcher->trackStats())
		{
			// I see you!
			writeLog();

			// Track forum statistics and hits...?
			if (!empty($modSettings['hitStats']))
			{
				trackStats(array('hits' => '+'));
			}
		}

		// Show where we came from, and go
		$context['site_action'] = $dispatcher->site_action();
	}

	$dispatcher->dispatch();
}
