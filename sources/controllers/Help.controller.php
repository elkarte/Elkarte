<?php

/**
 * This file has the job of taking care of help messages and the help center.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Help_Controller Class
 * Handles the help page and boxes
 */
class Help_Controller extends Action_Controller
{
	/**
	 * Pre Dispatch, called before other methods.  Loads integration hooks.
	 */
	public function pre_dispatch()
	{
		Hooks::get()->loadIntegrationsSettings();
	}

	/**
	 * Default action handler: just help.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// I need help!
		$this->action_help();
	}

	/**
	 * Prepares the help page.
	 *
	 * What it does:
	 *
	 * - It is accessed by ?action=help.
	 *
	 * @uses Help template and Manual language file.
	 */
	public function action_help()
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
	 *
	 * What it does
	 * - It handles both administrative or user help.
	 * - Data: $_GET['help'] parameter, it holds what string to display
	 * and where to get the string from. ($helptxt or $txt)
	 * - It is accessed via ?action=quickhelp;help=?.
	 *
	 * @uses ManagePermissions language file, if the help starts with permissionhelp.
	 * @uses Help template, 'popup' sub-template.
	 */
	public function action_quickhelp()
	{
		global $txt, $helptxt, $context, $scripturl;

		if (!isset($this->_req->query->help) || !is_string($this->_req->query->help))
			throw new Elk_Exception('no_access', false);

		if (!isset($helptxt))
			$helptxt = array();

		$help_str = Util::htmlspecialchars($this->_req->query->help);

		// Load the admin help language file and template.
		loadLanguage('Help');

		// Load permission specific help
		if (substr($help_str, 0, 14) == 'permissionhelp')
			loadLanguage('ManagePermissions');

		// Load our template
		loadTemplate('Help');

		// Allow addons to load their own language file here.
		call_integration_hook('integrate_quickhelp');

		// Set the page title to something relevant.
		$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

		// Only show the 'popup' sub-template, no layers.
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'popup';

		$helps = explode('+', $help_str);
		$context['help_text'] = '';

		// Find what to display: the string will be in $helptxt['help'] or in $txt['help]
		foreach ($helps as $help)
		{
			if (isset($helptxt[$help]))
				$context['help_text'] .= $helptxt[$help];
			elseif (isset($txt[$help]))
				$context['help_text'] .= $txt[$help];
			else
				// nothing :(
				$context['help_text'] .= $help;
		}

		// Link to the forum URL, and include session id.
		if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
			$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
	}
}
