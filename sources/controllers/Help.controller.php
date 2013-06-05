<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file has the important job of taking care of help messages and the help center.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Class to handle the help page and boxes
 */
class Help_Controller
{
	/**
	 * Prepares the help page.
	 * Uses Help template and Manual language file.
	 * It is accessed by ?action=help.
	 */
	function action_help()
	{
		global $scripturl, $context, $txt;

		loadTemplate('Help');
		loadLanguage('Manual');

		// We need to know where our wiki is.
		$context['wiki_url'] = 'https://github.com/elkarte/Elkarte/wiki';

		// Sections we are going to link...
		$context['manual_sections'] = array(
			'registering' => 'Registering',
			'logging_in' => 'Logging_In',
			'profile' => 'Profile',
			'search' => 'Search',
			'posting' => 'Posting',
			'bbc' => 'Bulletin_board_code',
			'personal_messages' => 'Personal_messages',
			'memberlist' => 'Memberlist',
			'calendar' => 'Calendar',
			'features' => 'Features',
		);

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=help',
			'name' => $txt['help'],
		);

		// Lastly, set up some template stuff.
		$context['page_title'] = $txt['manual_elkarte_user_help'];
		$context['sub_template'] = 'manual';
	}

	/**
	 * Show boxes with more detailed help on items, when the user clicks on their help icon.
	 * It handles both administrative or user help.
	 * Data: $_GET['help'] parameter, it holds what string to display
	 * and where to get the string from. ($helptxt or $txt)
	 * It is accessed via ?action=quickhelp;help=?.
	 * @uses ManagePermissions language file, if the help starts with permissionhelp.
	 * @uses Help template, 'popup' sub-template.
	 */
	function action_quickhelp()
	{
		global $txt, $helptxt, $context, $scripturl;

		if (!isset($_GET['help']) || !is_string($_GET['help']))
			fatal_lang_error('no_access', false);

		if (!isset($helptxt))
			$helptxt = array();

		$help = $_GET['help'];

		// Load the admin help language file and template.
		loadLanguage('Help');

		// Load permission specific help
		if (substr($help, 0, 14) == 'permissionhelp')
			loadLanguage('ManagePermissions');

		// Load our template
		loadTemplate('Help');

		// Allow mods to load their own language file here
	 	call_integration_hook('integrate_quickhelp');

		// Set the page title to something relevant.
		$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

		// Only show the 'popup' sub-template, no layers.
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'popup';

		// Find what to display: the string will be in $helptxt['help'] or in $txt['help]
		if (isset($helptxt[$help]))
			$context['help_text'] = $helptxt[$help];
		elseif (isset($txt[$help]))
			$context['help_text'] = $txt[$help];
		else
			// nothing :(
			$context['help_text'] = $help;

		// Link to the forum URL, and include session id.
		if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
			$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
	}
}