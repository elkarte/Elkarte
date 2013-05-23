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

class Help_Controller
{
	/**
	 * Redirect to the user help ;).
	 * It loads information needed for the help section.
	 * It is accessed by ?action=help.
	 * @uses Help template and Manual language file.
	 */
	function action_help()
	{
		global $scripturl, $context, $txt;

		loadTemplate('Help');
		loadLanguage('Manual');

		// We need to know where our wiki is.
		$context['wiki_url'] = 'https://github.com/elkarte/Elkarte/wiki';

		// Sections were are going to link...
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

		// Lastly, some minor template stuff.
		$context['page_title'] = $txt['manual_elkarte_user_help'];
		$context['sub_template'] = 'manual';
	}

	/**
	 * Show some of the more detailed help to give the admin an idea...
	 * It shows a popup for administrative or user help.
	 * It uses the help parameter to decide what string to display and where to get
	 * the string from. ($helptxt or $txt?)
	 * It is accessed via ?action=quickhelp;help=?.
	 * @uses ManagePermissions language file, if the help starts with permissionhelp.
	 * @uses Help template, popup sub template, no layers.
	 */
	function action_quickhelp()
	{
		global $txt, $helptxt, $context, $scripturl;

		if (!isset($_GET['help']) || !is_string($_GET['help']))
			fatal_lang_error('no_access', false);

		if (!isset($helptxt))
			$helptxt = array();

		// Load the admin help language file and template.
		loadLanguage('Help');

		// Permission specific help?
		if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp')
			loadLanguage('ManagePermissions');

		loadTemplate('Help');

		// Allow mods to load their own language file here
	 	call_integration_hook('integrate_quickhelp');

		// Set the page title to something relevant.
		$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

		// Don't show any template layers, just the popup sub template.
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'popup';

		// What help string should be used?
		if (isset($helptxt[$_GET['help']]))
			$context['help_text'] = $helptxt[$_GET['help']];
		elseif (isset($txt[$_GET['help']]))
			$context['help_text'] = $txt[$_GET['help']];
		else
			$context['help_text'] = $_GET['help'];

		// Does this text contain a link that we should fill in?
		if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
			$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
	}
}