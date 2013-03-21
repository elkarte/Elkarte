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
 * Maintains all XML-based interaction (mainly XMLhttp)
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Main function for action=xmlhttp.
 */
function action_xmlhttp()
{
	loadTemplate('Xml');

	$subActions = array(
		'jumpto' => array('action_jumpto'),
		'messageicons' => array('action_messageicons'),
		'groupicons' => array('action_groupicons'),
		'corefeatures' => array('action_corefeatures', 'admin_forum'),
		'previews' => array('action_previews'),
	);

	// Easy adding of xml sub actions
 	call_integration_hook('integrate_xmlhttp', array(&$sub_actions));

	// Valid action?
	if (!isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		fatal_lang_error('no_access', false);

	// Permissions check in the subAction?
	if (isset($subActions[$_REQUEST['sa']][1]))
		isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Off we go then
	$subActions[$_REQUEST['sa']][0]();
}

/**
 * Get a list of boards and categories used for the jumpto dropdown.
 */
function action_jumpto()
{
	global $context;

	// Find the boards/categories they can see.
	require_once(SUBSDIR . '/MessageIndex.subs.php');
	$boardListOptions = array(
		'use_permissions' => true,
		'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
	);
	$context += getBoardList($boardListOptions);

	// Make the board safe for display.
	foreach ($context['categories'] as $id_cat => $cat)
	{
		$context['categories'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
		foreach ($cat['boards'] as $id_board => $board)
			$context['categories'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
	}

	$context['sub_template'] = 'jump_to';
}

/**
 * Get the message icons available for a given board
 */
function action_messageicons()
{
	global $context, $board;

	require_once(SUBSDIR . '/Editor.subs.php');
	$context['icons'] = getMessageIcons($board);

	$context['sub_template'] = 'message_icons';
}

/**
 * Get the member group icons
 */
function action_groupicons()
{
	global $context, $settings;

	// Only load images
	$allowedTypes = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
	$context['membergroup_icons'] = array();
	$directory = $settings['theme_dir'] . '/images/group_icons';

	// Get all the available member group icons
	$files = scandir($directory);
	foreach ($files as $id => $file)
	{
		if ($file === 'blank.png')
			continue;

		if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowedTypes))
		{
			$icons[$id] = array(
				'value' => $file,
				'name' => '',
				'url' => $settings['images_url'] . '/group_icons/' .  $file,
				'is_last' => false,
			);
		}
	}

	$context['icons'] = array_values($icons);
	$context['sub_template'] = 'message_icons';
}

/**
 * Turns on or off a core forum feature via ajax
 */
function action_corefeatures()
{
	global $context, $modSettings, $txt, $settings;

	$context['xml_data'] = array();

	// Just in case, maybe we don't need it
	loadLanguage('Errors');

	// We need (at least) this to ensure that mod files are included
	if (!empty($modSettings['integrate_admin_include']))
	{
		$admin_includes = explode(',', $modSettings['integrate_admin_include']);
		foreach ($admin_includes as $include)
		{
			$include = strtr(trim($include), array('BOARDDIR' => BOARDDIR, 'SOURCEDIR' => SOURCEDIR, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	$errors = array();
	$returns = array();
	$tokens = array();

	// You have to be allowed to do this of course
	$validation = validateSession();
	if (empty($validation))
	{
		require_once(ADMINDIR . '/ManageSettings.php');
		$result = ModifyCoreFeatures();

		// Load up the core features of the system
		if (empty($result))
		{
			$id = isset($_POST['feature_id']) ? $_POST['feature_id'] : '';

			// The feature being enabled does exist, no messing about
			if (!empty($id) && isset($context['features'][$id]))
			{
				$feature = $context['features'][$id];
				$returns[] = array(
					'value' => (!empty($_POST['feature_' . $id]) && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']),
				);

				createToken('admin-core', 'post');
				$tokens = array(
					array(
						'value' => $context['admin-core_token'],
						'attributes' => array('type' => 'token_var'),
					),
					array(
						'value' => $context['admin-core_token_var'],
						'attributes' => array('type' => 'token'),
					),
				);
			}
			else
				$errors[] = array('value' => $txt['feature_no_exists']);
		}
		// Some problem loading in the core feature set
		else
			$errors[] = array('value' => $txt[$result]);
	}
	// Failed session validation I'm afraid
	else
		$errors[] = array('value' => $txt[$validation]);


	// Return the response to the calling program
	$context['sub_template'] = 'generic_xml';
	$context['xml_data'] = array(
		'corefeatures' => array(
			'identifier' => 'corefeature',
			'children' => $returns,
		),
		'tokens' => array(
			'identifier' => 'token',
			'children' => $tokens,
		),
		'errors' => array(
			'identifier' => 'error',
			'children' => $errors,
		),
	);
}

/**
 * Returns a preview of an item for use in an ajax enabled template
 *  - Calls the correct function for the action
 */
function action_previews()
{
	global $context;

	$subActions = array(
		'newspreview' => array('action_newspreview'),
		'newsletterpreview' => array('action_newsletterpreview'),
		'sig_preview' => array('action_sig_preview'),
		'warning_preview' => array('action_warning_preview'),
	);

	$context['sub_template'] = 'generic_xml';

	// Valid action?
	if (!isset($_REQUEST['item'], $subActions[$_REQUEST['item']]))
		return false;

	// A preview it is then
	$subActions[$_REQUEST['item']][0]();
}

/**
 * Get a preview of the important forum news for review before use
 *  - Calls parse bbc to render bbc tags for the preview
 */
function action_newspreview()
{
	global $context, $smcFunc;

	// Needed for parse bbc
	require_once(SUBSDIR . '/Post.subs.php');

	$errors = array();
	$news = !isset($_POST['news']) ? '' : $smcFunc['htmlspecialchars']($_POST['news'], ENT_QUOTES);
	if (empty($news))
		$errors[] = array('value' => 'no_news');
	else
		preparsecode($news);

	// Return the xml response to the template
	$context['xml_data'] = array(
		'news' => array(
			'identifier' => 'parsedNews',
			'children' => array(
				array(
					'value' => parse_bbc($news),
				),
			),
		),
		'errors' => array(
			'identifier' => 'error',
			'children' => $errors
		),
	);
}

/**
 * Get a preview of a news letter before its sent on to the masses
 *  - Uses prepareMailingForPreview to create the actual preview
 */
function action_newsletterpreview()
{
	global $context, $txt;

	// needed to create the preview
	require_once(SUBSDIR . '/Mail.subs.php');
	loadLanguage('Errors');

	$context['post_error']['messages'] = array();
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

	// Let them know about any mistakes
	if (empty($_POST['subject']))
		$context['post_error']['messages'][] = $txt['error_no_subject'];
	if (empty($_POST['message']))
		$context['post_error']['messages'][] = $txt['error_no_message'];

	prepareMailingForPreview();

	$context['sub_template'] = 'pm';
}

/**
 * Let them see what their signature looks like before they use it like spam
 */
function action_sig_preview()
{
	global $context, $smcFunc, $txt, $user_info;

	require_once(SUBSDIR . '/Profile.subs.php');
	loadLanguage('Profile');
	loadLanguage('Errors');

	$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
	$is_owner = $user == $user_info['id'];

	// @todo Temporary
	// Borrowed from loadAttachmentContext in Display.controller.php
	$can_change = $is_owner ? allowedTo(array('profile_extra_any', 'profile_extra_own')) : allowedTo('profile_extra_any');

	$errors = array();
	if (!empty($user) && $can_change)
	{
		// Get the current signature
		$request = $smcFunc['db_query']('', '
			SELECT signature
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $user,
			)
		);
		list($current_signature) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		censorText($current_signature);
		$current_signature = parse_bbc($current_signature, true, 'sig' . $user);

		// And now what they want it to be
		$preview_signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
		$validation = profileValidateSignature($preview_signature);

		// An odd check for errors to be sure
		if ($validation !== true && $validation !== false)
			$errors[] = array('value' => $txt['profile_error_' . $validation], 'attributes' => array('type' => 'error'));

		censorText($preview_signature);
		$preview_signature = parse_bbc($preview_signature, true, 'sig' . $user);
	}
	// Sorry but you can't change the signature
	elseif (!$can_change)
	{
		if ($is_owner)
			$errors[] = array('value' => $txt['cannot_profile_extra_own'], 'attributes' => array('type' => 'error'));
		else
			$errors[] = array('value' => $txt['cannot_profile_extra_any'], 'attributes' => array('type' => 'error'));
	}
	else
		$errors[] = array('value' => $txt['no_user_selected'], 'attributes' => array('type' => 'error'));

	// Return the response for the template
	$context['xml_data']['signatures'] = array(
		'identifier' => 'signature',
		'children' => array()
	);

	if (isset($current_signature))
		$context['xml_data']['signatures']['children'][] = array(
			'value' => $current_signature,
			'attributes' => array('type' => 'current'),
		);

	if (isset($preview_signature))
		$context['xml_data']['signatures']['children'][] = array(
			'value' => $preview_signature,
			'attributes' => array('type' => 'preview'),
		);

	if (!empty($errors))
		$context['xml_data']['errors'] = array(
			'identifier' => 'error',
			'children' => array_merge(
					array(
				array(
					'value' => $txt['profile_errors_occurred'],
					'attributes' => array('type' => 'errors_occurred'),
				),
					), $errors
			),
		);
}

/**
 * Used to preview custom warning templates before they are saved to submitted to the user
 */
function action_warning_preview()
{
	global $context, $smcFunc, $txt, $user_info, $scripturl, $mbname;

	require_once(SUBSDIR . '/Post.subs.php');
	loadLanguage('Errors');
	loadLanguage('ModerationCenter');

	$context['post_error']['messages'] = array();

	// If you can't issue the warning, what are you doing here?
	if (allowedTo('issue_warning'))
	{
		$warning_body = !empty($_POST['body']) ? trim(censorText($_POST['body'])) : '';
		$context['preview_subject'] = !empty($_POST['title']) ? trim($smcFunc['htmlspecialchars']($_POST['title'])) : '';
		if (isset($_POST['issuing']))
		{
			if (empty($_POST['title']) || empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['warning_notify_blank'];
		}
		else
		{
			if (empty($_POST['title']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_title'];
			if (empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_body'];
			// Add in few replacements.
			/**
			 * These are the defaults:
			 * - {MEMBER} - Member Name. => current user for review
			 * - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
			 * - {FORUMNAME} - Forum Name.
			 * - {SCRIPTURL} - Web address of forum.
			 * - {REGARDS} - Standard email sign-off.
			 */
			$find = array(
				'{MEMBER}',
				'{FORUMNAME}',
				'{SCRIPTURL}',
				'{REGARDS}',
			);
			$replace = array(
				$user_info['name'],
				$mbname,
				$scripturl,
				$txt['regards_team'],
			);
			$warning_body = str_replace($find, $replace, $warning_body);
		}

		// Deal with any BBC so it looks good for the preview
		if (!empty($_POST['body']))
		{
			preparsecode($warning_body);
			$warning_body = parse_bbc($warning_body, true);
		}
		$context['preview_message'] = $warning_body;
	}
	else
		$context['post_error']['messages'][] = array('value' => $txt['cannot_issue_warning'], 'attributes' => array('type' => 'error'));

	$context['sub_template'] = 'pm';
}
