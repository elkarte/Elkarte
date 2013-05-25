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
 * Handle all of the searching from here.
 *
 */
if (!defined('ELKARTE'))
	die('No access...');

// This defines two version types for checking the API's are compatible with this version of the software.
$GLOBALS['search_versions'] = array(
	// This is the forum version but is repeated due to some people rewriting $forum_version.
	'forum_version' => 'ELKARTE 1.0 Alpha',
	// This is the minimum version of ELKARTE that an API could have been written for to work. (strtr to stop accidentally updating version on release)
	'search_version' => strtr('ELKARTE 1+0=Alpha', array('+' => '.', '=' => ' ')),
);

/**
 * Ask the user what they want to search for.
 * What it does:
 * - shows the screen to search forum posts (action=search), and uses the simple version if the simpleSearch setting is enabled.
 * - uses the main sub template of the Search template.
 * - uses the Search language file.
 * - requires the search_posts permission.
 * - decodes and loads search parameters given in the URL (if any).
 * - the form redirects to index.php?action=search2.
 */
function action_plushsearch1()
{
	global $txt, $scripturl, $modSettings, $user_info, $context;

	$db = database();

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	loadLanguage('Search');

	// Don't load this in XML mode.
	if (!isset($_REQUEST['xml']))
		loadTemplate('Search');

	// Check the user's permissions.
	isAllowedTo('search_posts');

	// Link tree....
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search',
		'name' => $txt['search']
	);

	// This is hard coded maximum string length.
	$context['search_string_limit'] = 100;

	$context['require_verification'] = $user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);
	if ($context['require_verification'])
	{
		require_once(SUBSDIR . '/Editor.subs.php');
		$verificationOptions = array(
			'id' => 'search',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// If you got back from search2 by using the linktree, you get your original search parameters back.
	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));

		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}

		if (isset($context['search_params']['brd']))
			$context['search_params']['brd'] = $context['search_params']['brd'] == '' ? array() : explode(',', $context['search_params']['brd']);
	}

	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);
	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;
	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];
	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

	// Load the error text strings if there were errors in the search.
	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error === 'messages')
				continue;

			if ($search_error == 'string_too_long')
				$txt['error_string_too_long'] = sprintf($txt['error_string_too_long'], $context['search_string_limit']);

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	require_once(SUBSDIR . '/Boards.subs.php');
	$context += getBoardList(array('use_permissions' => true, 'not_redirection' => true));
	foreach ($context['categories'] as &$category)
	{
		$category['child_ids'] = array_keys($category['boards']);
		foreach ($category['boards'] as &$board)
			$board['selected'] = (empty($context['search_params']['brd']) && (empty($modSettings['recycle_enable']) || $board['id'] != $modSettings['recycle_board']) && !in_array($board['id'], $user_info['ignoreboards'])) || (!empty($context['search_params']['brd']) && in_array($board['id'], $context['search_params']['brd']));
	}

	if (!empty($_REQUEST['topic']))
	{
		$context['search_params']['topic'] = (int) $_REQUEST['topic'];
		$context['search_params']['show_complete'] = true;
	}

	if (!empty($context['search_params']['topic']))
	{
		$context['search_params']['topic'] = (int) $context['search_params']['topic'];

		$context['search_topic'] = array(
			'id' => $context['search_params']['topic'],
			'href' => $scripturl . '?topic=' . $context['search_params']['topic'] . '.0',
		);

		$request = $db->query('', '
			SELECT ms.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_board}' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved_true}' : '') . '
			LIMIT 1',
			array(
				'is_approved_true' => 1,
				'search_topic_id' => $context['search_params']['topic'],
			)
		);

		if ($db->num_rows($request) == 0)
			fatal_lang_error('topic_gone', false);

		list ($context['search_topic']['subject']) = $db->fetch_row($request);
		$db->free_result($request);

		$context['search_topic']['link'] = '<a href="' . $context['search_topic']['href'] . '">' . $context['search_topic']['subject'] . '</a>';
	}

	// Simple or not?
	$context['simple_search'] = isset($context['search_params']['advanced']) ? empty($context['search_params']['advanced']) : !empty($modSettings['simpleSearch']) && !isset($_REQUEST['advanced']);
	$context['page_title'] = $txt['set_parameters'];

	call_integration_hook('integrate_search');
}

/**
 * Gather the results and show them.
 * What it does:
 * - checks user input and searches the messages table for messages matching the query.
 * - requires the search_posts permission.
 * - uses the results sub template of the Search template.
 * - uses the Search language file.
 * - stores the results into the search cache.
 * - show the results of the search query.
 */
function action_plushsearch2()
{
	global $scripturl, $modSettings, $txt;
	global $user_info, $context, $options, $messages_request, $boards_can;
	global $excludedWords, $participants;

	// We shouldn't be working with the db, but we do :P
	$db = database();
	$db_search = db_search();

	// if coming from the quick search box, and we want to search on members, well we need to do that ;)
	// Coming from quick search box and going to some custome place?
	if (isset($_REQUEST['search_selection']) && !empty($modSettings['additional_search_engines']))
	{
		$engines = prepareSearchEngines();
		if (isset($engines[$_REQUEST['search_selection']]))
		{
			$engine = $engines[$_REQUEST['search_selection']];
			redirectexit($engine['url'] . urlencode(implode($engine['separator'], explode(' ', $_REQUEST['search']))));
		}
	}

	// if comming from the quick search box, and we want to search on members, well we need to do that ;)
	if (isset($_REQUEST['search_selection']) && $_REQUEST['search_selection'] === 'members')
		redirectexit($scripturl . '?action=memberlist;sa=search;fields=name,email;search=' . urlencode($_REQUEST['search']));

	if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Forbidden');
		die;
	}

	$weight_factors = array(
		'frequency' => array(
			'search' => 'COUNT(*) / (MAX(t.num_replies) + 1)',
			'results' => '(t.num_replies + 1)',
		),
		'age' => array(
			'search' => 'CASE WHEN MAX(m.id_msg) < {int:min_msg} THEN 0 ELSE (MAX(m.id_msg) - {int:min_msg}) / {int:recent_message} END',
			'results' => 'CASE WHEN t.id_first_msg < {int:min_msg} THEN 0 ELSE (t.id_first_msg - {int:min_msg}) / {int:recent_message} END',
		),
		'length' => array(
			'search' => 'CASE WHEN MAX(t.num_replies) < {int:huge_topic_posts} THEN MAX(t.num_replies) / {int:huge_topic_posts} ELSE 1 END',
			'results' => 'CASE WHEN t.num_replies < {int:huge_topic_posts} THEN t.num_replies / {int:huge_topic_posts} ELSE 1 END',
		),
		'subject' => array(
			'search' => 0,
			'results' => 0,
		),
		'first_message' => array(
			'search' => 'CASE WHEN MIN(m.id_msg) = MAX(t.id_first_msg) THEN 1 ELSE 0 END',
		),
		'sticky' => array(
			'search' => 'MAX(t.is_sticky)',
			'results' => 't.is_sticky',
		),
	);

	call_integration_hook('integrate_search_weights', array(&$weight_factors));

	$weight = array();
	$weight_total = 0;
	foreach ($weight_factors as $weight_factor => $value)
	{
		$weight[$weight_factor] = empty($modSettings['search_weight_' . $weight_factor]) ? 0 : (int) $modSettings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// Zero weight.  Weightless :P.
	if (empty($weight_total))
		fatal_lang_error('search_invalid_weights');

	// These vars don't require an interface, they're just here for tweaking.
	$recentPercentage = 0.30;
	$humungousTopicPosts = 200;
	$maxMembersToSearch = 500;
	$maxMessageResults = empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] * 5;

	// Start with no errors.
	$context['search_errors'] = array();

	// Number of pages hard maximum - normally not set at all.
	$modSettings['search_max_results'] = empty($modSettings['search_max_results']) ? 200 * $modSettings['search_results_per_page'] : (int) $modSettings['search_max_results'];

	// Maximum length of the string.
	$context['search_string_limit'] = 100;

	loadLanguage('Search');
	if (!isset($_REQUEST['xml']))
		loadTemplate('Search');
	// If we're doing XML we need to use the results template regardless really.
	else
		$context['sub_template'] = 'results';

	// Are you allowed?
	isAllowedTo('search_posts');

	require_once(CONTROLLERDIR . '/Display.controller.php');
	require_once(SUBSDIR . '/Package.subs.php');
	require_once(SUBSDIR . '/Search.subs.php');

	// Load up the search API we are going to use.
	$searchAPI = findSearchAPI();

	// $search_params will carry all settings that differ from the default search parameters.
	// That way, the URLs involved in a search page will be kept as short as possible.
	$search_params = array();

	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));

		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', (!empty($temp_params2) ? $temp_params2 : $temp_params));

		foreach ($temp_params as $i => $data)
		{
			@list($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}

		if (isset($search_params['brd']))
			$search_params['brd'] = empty($search_params['brd']) ? array() : explode(',', $search_params['brd']);
	}

	// Store whether simple search was used (needed if the user wants to do another query).
	if (!isset($search_params['advanced']))
		$search_params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;

	// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;

	// Minimum age of messages. Default to zero (don't set param in that case).
	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

	// Maximum age of messages. Default to infinite (9999 days: param not set).
	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	// Searching a specific topic?
	if (!empty($_REQUEST['topic']) || (!empty($_REQUEST['search_selection']) && $_REQUEST['search_selection'] == 'topic'))
	{
		$search_params['topic'] = empty($_REQUEST['search_selection']) ? (int) $_REQUEST['topic'] : (isset($_REQUEST['sd_topic']) ? (int) $_REQUEST['sd_topic'] : '');
		$search_params['show_complete'] = true;
	}
	elseif (!empty($search_params['topic']))
		$search_params['topic'] = (int) $search_params['topic'];

	if (!empty($search_params['minage']) || !empty($search_params['maxage']))
	{
		$request = $db->query('', '
			SELECT ' . (empty($search_params['maxage']) ? '0, ' : 'IFNULL(MIN(id_msg), -1), ') . (empty($search_params['minage']) ? '0' : 'IFNULL(MAX(id_msg), -1)') . '
			FROM {db_prefix}messages
			WHERE 1=1' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved_true}' : '') . (empty($search_params['minage']) ? '' : '
				AND poster_time <= {int:timestamp_minimum_age}') . (empty($search_params['maxage']) ? '' : '
				AND poster_time >= {int:timestamp_maximum_age}'),
			array(
				'timestamp_minimum_age' => empty($search_params['minage']) ? 0 : time() - 86400 * $search_params['minage'],
				'timestamp_maximum_age' => empty($search_params['maxage']) ? 0 : time() - 86400 * $search_params['maxage'],
				'is_approved_true' => 1,
			)
		);
		list ($minMsgID, $maxMsgID) = $db->fetch_row($request);
		if ($minMsgID < 0 || $maxMsgID < 0)
			$context['search_errors']['no_messages_in_time_frame'] = true;
		$db->free_result($request);
	}

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr(Util::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}

		// Create a list of database-escaped search names.
		$realNameMatches = array();
		foreach ($possible_users as $possible_user)
			$realNameMatches[] = $db->quote(
				'{string:possible_user}',
				array(
					'possible_user' => $possible_user
				)
			);

		// Retrieve a list of possible members.
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE {raw:match_possible_users}',
			array(
				'match_possible_users' => 'real_name LIKE ' . implode(' OR real_name LIKE ', $realNameMatches),
			)
		);

		// Simply do nothing if there're too many members matching the criteria.
		if ($db->num_rows($request) > $maxMembersToSearch)
			$userQuery = '';
		elseif ($db->num_rows($request) == 0)
		{
			$userQuery = $db->quote(
				'm.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})',
				array(
					'id_member_guest' => 0,
					'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
				)
			);
		}
		else
		{
			$memberlist = array();
			while ($row = $db->fetch_assoc($request))
				$memberlist[] = $row['id_member'];
			$userQuery = $db->quote(
				'(m.id_member IN ({array_int:matched_members}) OR (m.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})))',
				array(
					'matched_members' => $memberlist,
					'id_member_guest' => 0,
					'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
				)
			);
		}
		$db->free_result($request);
	}

	// Ensure that boards are an array of integers (or nothing).
	if (!empty($search_params['brd']) && is_array($search_params['brd']))
		$query_boards = array_map('intval', $search_params['brd']);
	elseif (!empty($_REQUEST['brd']) && is_array($_REQUEST['brd']))
		$query_boards = array_map('intval', $_REQUEST['brd']);
	elseif (!empty($_REQUEST['brd']))
		$query_boards = array_map('intval', explode(',', $_REQUEST['brd']));
	elseif (isset($_REQUEST['sd_brd']) && (int) $_REQUEST['sd_brd'] !== 0)
		$query_boards = array((int) $_REQUEST['sd_brd']);
	else
		$query_boards = array();

	// Special case for boards: searching just one topic?
	if (!empty($search_params['topic']))
	{
		$request = $db->query('', '
			SELECT b.id_board
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_board}' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved_true}' : '') . '
			LIMIT 1',
			array(
				'search_topic_id' => $search_params['topic'],
				'is_approved_true' => 1,
			)
		);

		if ($db->num_rows($request) == 0)
			fatal_lang_error('topic_gone', false);

		$search_params['brd'] = array();
		list ($search_params['brd'][0]) = $db->fetch_row($request);
		$db->free_result($request);
	}
	// Select all boards you've selected AND are allowed to see.
	elseif ($user_info['is_admin'] && (!empty($search_params['advanced']) || !empty($query_boards)))
		$search_params['brd'] = $query_boards;
	else
	{
		require_once(SUBSDIR . '/Boards.subs.php');
		$search_params['brd'] = array_keys(fetchBoardsInfo(array('boards' => $query_boards), array('exclude_recycle' => true, 'exclude_redirects' => true, 'wanna_see_board' => empty($search_params['advanced']))));

		// This error should pro'bly only happen for hackers.
		if (empty($search_params['brd']))
			$context['search_errors']['no_boards_selected'] = true;
	}

	if (count($search_params['brd']) != 0)
	{
		foreach ($search_params['brd'] as $k => $v)
			$search_params['brd'][$k] = (int) $v;

		// If we've selected all boards, this parameter can be left empty.
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}boards
			WHERE redirect = {string:empty_string}',
			array(
				'empty_string' => '',
			)
		);
		list ($num_boards) = $db->fetch_row($request);
		$db->free_result($request);

		if (count($search_params['brd']) == $num_boards)
			$boardQuery = '';
		elseif (count($search_params['brd']) == $num_boards - 1 && !empty($modSettings['recycle_board']) && !in_array($modSettings['recycle_board'], $search_params['brd']))
			$boardQuery = '!= ' . $modSettings['recycle_board'];
		else
			$boardQuery = 'IN (' . implode(', ', $search_params['brd']) . ')';
	}
	else
		$boardQuery = '';

	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);
	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);

	$context['compact'] = !$search_params['show_complete'];

	// Get the sorting parameters right. Default to sort by relevance descending.
	$sort_columns = array(
		'relevance',
		'num_replies',
		'id_msg',
	);
	call_integration_hook('integrate_search_sort_columns', array(&$sort_columns));
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');

	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'relevance';

	if (!empty($search_params['topic']) && $search_params['sort'] === 'num_replies')
		$search_params['sort'] = 'id_msg';

	// Sorting direction: descending unless stated otherwise.
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Determine some values needed to calculate the relevance.
	$minMsg = (int) ((1 - $recentPercentage) * $modSettings['maxMsgID']);
	$recentMsg = $modSettings['maxMsgID'] - $minMsg;

	// *** Parse the search query
	call_integration_hook('integrate_search_params', array(&$search_params));

	// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
	// @todo Setting to add more here?
	// @todo Maybe only blacklist if they are the only word, or "any" is used?
	$blacklisted_words = array('img', 'url', 'quote', 'www', 'http', 'the', 'is', 'it', 'are', 'if');
	call_integration_hook('integrate_search_blacklisted_words', array(&$blacklisted_words));

	// What are we searching for?
	if (empty($search_params['search']))
	{
		if (isset($_GET['search']))
			$search_params['search'] = un_htmlspecialchars($_GET['search']);
		elseif (isset($_POST['search']))
			$search_params['search'] = $_POST['search'];
		else
			$search_params['search'] = '';
	}

	// Nothing??
	if (!isset($search_params['search']) || $search_params['search'] == '')
		$context['search_errors']['invalid_search_string'] = true;
	// Too long?
	elseif (Util::strlen($search_params['search']) > $context['search_string_limit'])
	{
		$context['search_errors']['string_too_long'] = true;
	}

	// Change non-word characters into spaces.
	$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $search_params['search']);

	// Make the query lower case. It's gonna be case insensitive anyway.
	$stripped_query = un_htmlspecialchars(Util::strtolower($stripped_query));

	// This (hidden) setting will do fulltext searching in the most basic way.
	if (!empty($modSettings['search_simple_fulltext']))
		$stripped_query = strtr($stripped_query, array('"' => ''));

	$no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
	$phraseArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']);
	$wordArray = explode(' ', Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

	// A minus sign in front of a word excludes the word.... so...
	$excludedWords = array();
	$excludedIndexWords = array();
	$excludedSubjectWords = array();
	$excludedPhrases = array();

	// .. first, we check for things like -"some words", but not "-some words".
	foreach ($matches[1] as $index => $word)
	{
		if ($word === '-')
		{
			if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($phraseArray[$index]);
		}
	}

	// Now we look for -test, etc.... normaller.
	foreach ($wordArray as $index => $word)
	{
		if (strpos(trim($word), '-') === 0)
		{
			if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($wordArray[$index]);
		}
	}

	// The remaining words and phrases are all included.
	$searchArray = array_merge($phraseArray, $wordArray);

	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		// Skip anything practically empty.
		if (($searchArray[$index] = trim($value, '-_\' ')) === '')
			unset($searchArray[$index]);
		// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
		elseif (in_array($searchArray[$index], $blacklisted_words))
		{
			$foundBlackListedWords = true;
			unset($searchArray[$index]);
		}
		// Don't allow very, very short words.
		elseif (Util::strlen($value) < 2)
		{
			$context['search_errors']['search_string_small_words'] = true;
			unset($searchArray[$index]);
		}
		else
			$searchArray[$index] = $searchArray[$index];
	}
	$searchArray = array_slice(array_unique($searchArray), 0, 10);

	// Create an array of replacements for highlighting.
	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

	// Initialize two arrays storing the words that have to be searched for.
	$orParts = array();
	$searchWords = array();

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
		$context['search_errors']['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;
	// All words/sentences must match.
	elseif (empty($search_params['searchtype']))
		$orParts[0] = $searchArray;
	// Any word/sentence must match.
	else
		foreach ($searchArray as $index => $value)
			$orParts[$index] = array($value);

	// Don't allow duplicate error messages if one string is too short.
	if (isset($context['search_errors']['search_string_small_words'], $context['search_errors']['invalid_search_string']))
		unset($context['search_errors']['invalid_search_string']);
	// Make sure the excluded words are in all or-branches.
	foreach ($orParts as $orIndex => $andParts)
		foreach ($excludedWords as $word)
			$orParts[$orIndex][] = $word;

	// Determine the or-branches and the fulltext search words.
	foreach ($orParts as $orIndex => $andParts)
	{
		$searchWords[$orIndex] = array(
			'indexed_words' => array(),
			'words' => array(),
			'subject_words' => array(),
			'all_words' => array(),
			'complex_words' => array(),
		);

		// Sort the indexed words (large words -> small words -> excluded words).
		if ($searchAPI->supportsMethod('searchSort'))
			usort($orParts[$orIndex], 'searchSort');

		foreach ($orParts[$orIndex] as $word)
		{
			$is_excluded = in_array($word, $excludedWords);
			$searchWords[$orIndex]['all_words'][] = $word;
			$subjectWords = text2words($word);
			if (!$is_excluded || count($subjectWords) === 1)
			{
				$searchWords[$orIndex]['subject_words'] = array_merge($searchWords[$orIndex]['subject_words'], $subjectWords);
				if ($is_excluded)
					$excludedSubjectWords = array_merge($excludedSubjectWords, $subjectWords);
			}
			else
				$excludedPhrases[] = $word;

			// Have we got indexes to prepare?
			if ($searchAPI->supportsMethod('prepareIndexes'))
				$searchAPI->prepareIndexes($word, $searchWords[$orIndex], $excludedIndexWords, $is_excluded);
		}

		// Search_force_index requires all AND parts to have at least one fulltext word.
		if (!empty($modSettings['search_force_index']) && empty($searchWords[$orIndex]['indexed_words']))
		{
			$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}
		elseif ($search_params['subject_only'] && empty($searchWords[$orIndex]['subject_words']) && empty($excludedSubjectWords))
		{
			$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}
		// Make sure we aren't searching for too many indexed words.
		else
		{
			$searchWords[$orIndex]['indexed_words'] = array_slice($searchWords[$orIndex]['indexed_words'], 0, 7);
			$searchWords[$orIndex]['subject_words'] = array_slice($searchWords[$orIndex]['subject_words'], 0, 7);
			$searchWords[$orIndex]['words'] = array_slice($searchWords[$orIndex]['words'], 0, 4);
		}
	}

	// *** Spell checking
	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
	if ($context['show_spellchecking'])
	{
		// Windows fix.
		ob_start();
		$old = error_reporting(0);

		pspell_new('en');
		$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', 'utf-8', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		if (!$pspell_link)
			$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		error_reporting($old);
		ob_end_clean();

		$did_you_mean = array('search' => array(), 'display' => array());
		$found_misspelling = false;
		foreach ($searchArray as $word)
		{
			if (empty($pspell_link))
				continue;

			// Don't check phrases.
			if (preg_match('~^\w+$~', $word) === 0)
			{
				$did_you_mean['search'][] = '"' . $word . '"';
				$did_you_mean['display'][] = '&quot;' . Util::htmlspecialchars($word) . '&quot;';
				continue;
			}
			// For some strange reason spell check can crash PHP on decimals.
			elseif (preg_match('~\d~', $word) === 1)
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = Util::htmlspecialchars($word);
				continue;
			}
			elseif (pspell_check($pspell_link, $word))
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = Util::htmlspecialchars($word);
				continue;
			}

			$suggestions = pspell_suggest($pspell_link, $word);
			foreach ($suggestions as $i => $s)
			{
				// Search is case insensitive.
				if (Util::strtolower($s) == Util::strtolower($word))
					unset($suggestions[$i]);
				// Plus, don't suggest something the user thinks is rude!
				elseif ($suggestions[$i] != censorText($s))
					unset($suggestions[$i]);
			}

			// Anything found?  If so, correct it!
			if (!empty($suggestions))
			{
				$suggestions = array_values($suggestions);
				$did_you_mean['search'][] = $suggestions[0];
				$did_you_mean['display'][] = '<em><strong>' . Util::htmlspecialchars($suggestions[0]) . '</strong></em>';
				$found_misspelling = true;
			}
			else
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = Util::htmlspecialchars($word);
			}
		}

		if ($found_misspelling)
		{
			// Don't spell check excluded words, but add them still...
			$temp_excluded = array('search' => array(), 'display' => array());
			foreach ($excludedWords as $word)
			{
				if (preg_match('~^\w+$~', $word) == 0)
				{
					$temp_excluded['search'][] = '-"' . $word . '"';
					$temp_excluded['display'][] = '-&quot;' . Util::htmlspecialchars($word) . '&quot;';
				}
				else
				{
					$temp_excluded['search'][] = '-' . $word;
					$temp_excluded['display'][] = '-' . Util::htmlspecialchars($word);
				}
			}

			$did_you_mean['search'] = array_merge($did_you_mean['search'], $temp_excluded['search']);
			$did_you_mean['display'] = array_merge($did_you_mean['display'], $temp_excluded['display']);

			$temp_params = $search_params;
			$temp_params['search'] = implode(' ', $did_you_mean['search']);
			if (isset($temp_params['brd']))
				$temp_params['brd'] = implode(',', $temp_params['brd']);

			$context['params'] = array();
			foreach ($temp_params as $k => $v)
				$context['did_you_mean_params'][] = $k . '|\'|' . $v;
			$context['did_you_mean_params'] = base64_encode(implode('|"|', $context['did_you_mean_params']));
			$context['did_you_mean'] = implode(' ', $did_you_mean['display']);
		}
	}

	// Let the user adjust the search query, should they wish?
	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);

	// Do we have captcha enabled?
	if ($user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search']))
	{
		// If we come from another search box tone down the error...
		if (!isset($_REQUEST['search_vv']))
			$context['search_errors']['need_verification_code'] = true;
		else
		{
			require_once(SUBSDIR . '/Editor.subs.php');
			$verificationOptions = array(
				'id' => 'search',
			);
			$context['require_verification'] = create_control_verification($verificationOptions, true);

			if (is_array($context['require_verification']))
			{
				foreach ($context['require_verification'] as $error)
					$context['search_errors'][$error] = true;
			}
			// Don't keep asking for it - they've proven themselves worthy.
			else
				$_SESSION['ss_vv_passed'] = true;
		}
	}

	// *** Encode all search params
	// All search params have been checked, let's compile them to a single string... made less simple by PHP 4.3.9 and below.
	$temp_params = $search_params;
	if (isset($temp_params['brd']))
		$temp_params['brd'] = implode(',', $temp_params['brd']);
	$context['params'] = array();
	foreach ($temp_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;

	if (!empty($context['params']))
	{
		// Due to old IE's 2083 character limit, we have to compress long search strings
		$params = @gzcompress(implode('|"|', $context['params']));

		// Gzcompress failed, use try non-gz
		if (empty($params))
			$params = implode('|"|', $context['params']);

		// Base64 encode, then replace +/= with uri safe ones that can be reverted
		$context['params'] = str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode($params));
	}

	// ... and add the links to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search;params=' . $context['params'],
		'name' => $txt['search']
	);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search2;params=' . $context['params'],
		'name' => $txt['search_results']
	);

	// *** A last error check
	call_integration_hook('integrate_search_errors');

	// One or more search errors? Go back to the first search screen.
	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
		return action_plushsearch1();
	}

	// Spam me not, Spam-a-lot?
	if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search'])
		spamProtection('search');

	// Store the last search string to allow pages of results to be browsed.
	$_SESSION['last_ss'] = $search_params['search'];

	// *** Reserve an ID for caching the search results.
	$query_params = array_merge($search_params, array(
		'min_msg_id' => isset($minMsgID) ? (int) $minMsgID : 0,
		'max_msg_id' => isset($maxMsgID) ? (int) $maxMsgID : 0,
		'memberlist' => !empty($memberlist) ? $memberlist : array(),
	));

	// Can this search rely on the API given the parameters?
	if ($searchAPI->supportsMethod('searchQuery', $query_params))
	{
		$participants = array();
		$searchArray = array();
		$num_results = $searchAPI->searchQuery($query_params, $searchWords, $excludedIndexWords, $participants, $searchArray);
	}

	// Update the cache if the current search term is not yet cached.
	else
	{
		$update_cache = empty($_SESSION['search_cache']) || ($_SESSION['search_cache']['params'] != $context['params']);
		if ($update_cache)
		{
			// Increase the pointer...
			$modSettings['search_pointer'] = empty($modSettings['search_pointer']) ? 0 : (int) $modSettings['search_pointer'];
			// ...and store it right off.
			updateSettings(array('search_pointer' => $modSettings['search_pointer'] >= 255 ? 0 : $modSettings['search_pointer'] + 1));
			// As long as you don't change the parameters, the cache result is yours.
			$_SESSION['search_cache'] = array(
				'id_search' => $modSettings['search_pointer'],
				'num_results' => -1,
				'params' => $context['params'],
			);

			// Clear the previous cache of the final results cache.
			$db_search->search_query('delete_log_search_results', '
				DELETE FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}',
				array(
					'search_id' => $_SESSION['search_cache']['id_search'],
				)
			);

			if ($search_params['subject_only'])
			{
				// We do this to try and avoid duplicate keys on databases not supporting INSERT IGNORE.
				$inserts = array();
				foreach ($searchWords as $orIndex => $words)
				{
					$subject_query_params = array();
					$subject_query = array(
						'from' => '{db_prefix}topics AS t',
						'inner_join' => array(),
						'left_join' => array(),
						'where' => array(),
					);

					if ($modSettings['postmod_active'])
						$subject_query['where'][] = 't.approved = {int:is_approved}';

					$numTables = 0;
					$prev_join = 0;
					$numSubjectResults = 0;
					foreach ($words['subject_words'] as $subjectWord)
					{
						$numTables++;
						if (in_array($subjectWord, $excludedSubjectWords))
						{
							$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
							$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
						}
						else
						{
							$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
							$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');
							$prev_join = $numTables;
						}
						$subject_query_params['subject_words_' . $numTables] = $subjectWord;
						$subject_query_params['subject_words_' . $numTables . '_wild'] = '%' . $subjectWord . '%';
					}

					if (!empty($userQuery))
					{
						if ($subject_query['from'] != '{db_prefix}messages AS m')
						{
							$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_topic = t.id_topic)';
						}
						$subject_query['where'][] = $userQuery;
					}
					if (!empty($search_params['topic']))
						$subject_query['where'][] = 't.id_topic = ' . $search_params['topic'];
					if (!empty($minMsgID))
						$subject_query['where'][] = 't.id_first_msg >= ' . $minMsgID;
					if (!empty($maxMsgID))
						$subject_query['where'][] = 't.id_last_msg <= ' . $maxMsgID;
					if (!empty($boardQuery))
						$subject_query['where'][] = 't.id_board ' . $boardQuery;
					if (!empty($excludedPhrases))
					{
						if ($subject_query['from'] != '{db_prefix}messages AS m')
						{
							$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
						}

						$count = 0;
						foreach ($excludedPhrases as $phrase)
						{
							$subject_query['where'][] = 'm.subject NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:excluded_phrases_' . $count . '}';
							$subject_query_params['excluded_phrases_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
						}
					}
					call_integration_hook('integrate_subject_only_search_query', array(&$subject_query, &$subject_query_params));

					// Build the search relevance query
					$relevance = '1000 * (';
					foreach ($weight_factors as $type => $value)
					{
						if (isset($value['results']))
						{
							$relevance .= $weight[$type];
							if (!empty($value['results']))
								$relevance .= ' * ' . $value['results'];
							$relevance .= ' + ';
						}
					}
					$relevance = substr($relevance, 0, -3) . ') / ' . $weight_total . ' AS relevance';

					$ignoreRequest = $db_search->search_query('insert_log_search_results_subject',
						($db->support_ignore() ? '
						INSERT IGNORE INTO {db_prefix}log_search_results
							(id_search, id_topic, relevance, id_msg, num_matches)' : '') . '
						SELECT
							{int:id_search},
							t.id_topic,
							' . $relevance . ',
							' . (empty($userQuery) ? 't.id_first_msg' : 'm.id_msg') . ',
							1
						FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
							INNER JOIN ' . implode('
							INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
							LEFT JOIN ' . implode('
							LEFT JOIN ', $subject_query['left_join'])) . '
						WHERE ' . implode('
							AND ', $subject_query['where']) . (empty($modSettings['search_max_results']) ? '' : '
						LIMIT ' . ($modSettings['search_max_results'] - $numSubjectResults)),
						array_merge($subject_query_params, array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
							'is_approved' => 1,
						))
					);

					// If the database doesn't support IGNORE to make this fast we need to do some tracking.
					if (!$db->support_ignore())
					{
						while ($row = $db->fetch_row($ignoreRequest))
						{
							// No duplicates!
							if (isset($inserts[$row[1]]))
								continue;

							foreach ($row as $key => $value)
								$inserts[$row[1]][] = (int) $row[$key];
						}
						$db->free_result($ignoreRequest);
						$numSubjectResults = count($inserts);
					}
					else
						$numSubjectResults += $db->affected_rows();

					if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
						break;
				}

				// If there's data to be inserted for non-IGNORE databases do it here!
				if (!empty($inserts))
				{
					$db->insert('',
						'{db_prefix}log_search_results',
						array('id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'int', 'id_msg' => 'int', 'num_matches' => 'int'),
						$inserts,
						array('id_search', 'id_topic')
					);
				}

				$_SESSION['search_cache']['num_results'] = $numSubjectResults;
			}
			else
			{
				$main_query = array(
					'select' => array(
						'id_search' => $_SESSION['search_cache']['id_search'],
						'relevance' => '0',
					),
					'weights' => array(),
					'from' => '{db_prefix}topics AS t',
					'inner_join' => array(
						'{db_prefix}messages AS m ON (m.id_topic = t.id_topic)'
					),
					'left_join' => array(),
					'where' => array(),
					'group_by' => array(),
					'parameters' => array(
						'min_msg' => $minMsg,
						'recent_message' => $recentMsg,
						'huge_topic_posts' => $humungousTopicPosts,
						'is_approved' => 1,
					),
				);

				if (empty($search_params['topic']) && empty($search_params['show_complete']))
				{
					$main_query['select']['id_topic'] = 't.id_topic';
					$main_query['select']['id_msg'] = 'MAX(m.id_msg) AS id_msg';
					$main_query['select']['num_matches'] = 'COUNT(*) AS num_matches';
					$main_query['weights'] = $weight_factors;
					$main_query['group_by'][] = 't.id_topic';
				}
				else
				{
					// This is outrageous!
					$main_query['select']['id_topic'] = 'm.id_msg AS id_topic';
					$main_query['select']['id_msg'] = 'm.id_msg';
					$main_query['select']['num_matches'] = '1 AS num_matches';

					$main_query['weights'] = array(
						'age' => array(
							'search' => '((m.id_msg - t.id_first_msg) / CASE WHEN t.id_last_msg = t.id_first_msg THEN 1 ELSE t.id_last_msg - t.id_first_msg END)',
						),
						'first_message' => array(
							'search' => 'CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END',
						),
					);

					if (!empty($search_params['topic']))
					{
						$main_query['where'][] = 't.id_topic = {int:topic}';
						$main_query['parameters']['topic'] = $search_params['topic'];
					}

					if (!empty($search_params['show_complete']))
						$main_query['group_by'][] = 'm.id_msg, t.id_first_msg, t.id_last_msg';
				}

				// *** Get the subject results.
				$numSubjectResults = 0;
				if (empty($search_params['topic']))
				{
					$inserts = array();
					// Create a temporary table to store some preliminary results in.
					$db_search->search_query('drop_tmp_log_search_topics', '
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_topics',
						array(
							'db_error_skip' => true,
						)
					);
					$createTemporary = $db_search->search_query('create_tmp_log_search_topics', '
						CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_topics (
							id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
							PRIMARY KEY (id_topic)
						) TYPE=HEAP',
						array(
							'string_zero' => '0',
							'db_error_skip' => true,
						)
					) !== false;

					// Clean up some previous cache.
					if (!$createTemporary)
						$db_search->search_query('delete_log_search_topics', '
							DELETE FROM {db_prefix}log_search_topics
							WHERE id_search = {int:search_id}',
							array(
								'search_id' => $_SESSION['search_cache']['id_search'],
							)
						);

					foreach ($searchWords as $orIndex => $words)
					{
						$subject_query = array(
							'from' => '{db_prefix}topics AS t',
							'inner_join' => array(),
							'left_join' => array(),
							'where' => array(),
							'params' => array(),
						);

						$numTables = 0;
						$prev_join = 0;
						$count = 0;
						$excluded = false;
						foreach ($words['subject_words'] as $subjectWord)
						{
							$numTables++;
							if (in_array($subjectWord, $excludedSubjectWords))
							{
								if (($subject_query['from'] != '{db_prefix}messages AS m') && !$excluded)
								{
									$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
									$excluded = true;
								}
								$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
								$subject_query['params']['subject_not_' . $count] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

								$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
								$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:body_not_' . $count . '}';
								$subject_query['params']['body_not_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($subjectWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $subjectWord), '\\\'') . '[[:>:]]';
							}
							else
							{
								$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
								$subject_query['where'][] = 'subj' . $numTables . '.word LIKE {string:subject_like_' . $count . '}';
								$subject_query['params']['subject_like_' . $count++] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;
								$prev_join = $numTables;
							}
						}

						if (!empty($userQuery))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
							{
								$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
							}
							$subject_query['where'][] = '{raw:user_query}';
							$subject_query['params']['user_query'] = $userQuery;
						}
						if (!empty($search_params['topic']))
						{
							$subject_query['where'][] = 't.id_topic = {int:topic}';
							$subject_query['params']['topic'] = $search_params['topic'];
						}
						if (!empty($minMsgID))
						{
							$subject_query['where'][] = 't.id_first_msg >= {int:min_msg_id}';
							$subject_query['params']['min_msg_id'] = $minMsgID;
						}
						if (!empty($maxMsgID))
						{
							$subject_query['where'][] = 't.id_last_msg <= {int:max_msg_id}';
							$subject_query['params']['max_msg_id'] = $maxMsgID;
						}
						if (!empty($boardQuery))
						{
							$subject_query['where'][] = 't.id_board {raw:board_query}';
							$subject_query['params']['board_query'] = $boardQuery;
						}
						if (!empty($excludedPhrases))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
							{
								$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
							}
							$count = 0;
							foreach ($excludedPhrases as $phrase)
							{
								$subject_query['where'][] = 'm.subject NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:exclude_phrase_' . $count . '}';
								$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:exclude_phrase_' . $count . '}';
								$subject_query['params']['exclude_phrase_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
							}
						}
						call_integration_hook('integrate_subject_search_query', array(&$subject_query));

						// Nothing to search for?
						if (empty($subject_query['where']))
							continue;

						$ignoreRequest = $db_search->search_query('insert_log_search_topics', ($db->support_ignore() ? ( '
							INSERT IGNORE INTO {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics
								(' . ($createTemporary ? '' : 'id_search, ') . 'id_topic)') : '') . '
							SELECT ' . ($createTemporary ? '' : $_SESSION['search_cache']['id_search'] . ', ') . 't.id_topic
							FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
								INNER JOIN ' . implode('
								INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
								LEFT JOIN ' . implode('
								LEFT JOIN ', $subject_query['left_join'])) . '
							WHERE ' . implode('
								AND ', $subject_query['where']) . (empty($modSettings['search_max_results']) ? '' : '
							LIMIT ' . ($modSettings['search_max_results'] - $numSubjectResults)),
							$subject_query['params']
						);
						// Don't do INSERT IGNORE? Manually fix this up!
						if (!$db->support_ignore())
						{
							while ($row = $db->fetch_row($ignoreRequest))
							{
								$ind = $createTemporary ? 0 : 1;
								// No duplicates!
								if (isset($inserts[$row[$ind]]))
									continue;

								$inserts[$row[$ind]] = $row;
							}
							$db->free_result($ignoreRequest);
							$numSubjectResults = count($inserts);
						}
						else
							$numSubjectResults += $db->affected_rows();

						if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
							break;
					}

					// Got some non-MySQL data to plonk in?
					if (!empty($inserts))
					{
						$db->insert('',
							('{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics'),
							$createTemporary ? array('id_topic' => 'int') : array('id_search' => 'int', 'id_topic' => 'int'),
							$inserts,
							$createTemporary ? array('id_topic') : array('id_search', 'id_topic')
						);
					}

					if ($numSubjectResults !== 0)
					{
						$main_query['weights']['subject']['search'] = 'CASE WHEN MAX(lst.id_topic) IS NULL THEN 0 ELSE 1 END';
						$main_query['left_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (' . ($createTemporary ? '' : 'lst.id_search = {int:id_search} AND ') . 'lst.id_topic = t.id_topic)';
						if (!$createTemporary)
							$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
					}
				}

				$indexedResults = 0;

				// We building an index?
				if ($searchAPI->supportsMethod('indexedWordQuery', $query_params))
				{
					$inserts = array();
					$db_search->search_query('drop_tmp_log_search_messages', '
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_messages',
						array(
							'db_error_skip' => true,
						)
					);

					$createTemporary = $db_search->search_query('create_tmp_log_search_messages', '
						CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_messages (
							id_msg int(10) unsigned NOT NULL default {string:string_zero},
							PRIMARY KEY (id_msg)
						) TYPE=HEAP',
						array(
							'string_zero' => '0',
							'db_error_skip' => true,
						)
					) !== false;

					// Clear, all clear!
					if (!$createTemporary)
						$db_search->search_query('delete_log_search_messages', '
							DELETE FROM {db_prefix}log_search_messages
							WHERE id_search = {int:id_search}',
							array(
								'id_search' => $_SESSION['search_cache']['id_search'],
							)
						);

					foreach ($searchWords as $orIndex => $words)
					{
						// Search for this word, assuming we have some words!
						if (!empty($words['indexed_words']))
						{
							// Variables required for the search.
							$search_data = array(
								'insert_into' => ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
								'no_regexp' => $no_regexp,
								'max_results' => $maxMessageResults,
								'indexed_results' => $indexedResults,
								'params' => array(
									'id_search' => !$createTemporary ? $_SESSION['search_cache']['id_search'] : 0,
									'excluded_words' => $excludedWords,
									'user_query' => !empty($userQuery) ? $userQuery : '',
									'board_query' => !empty($boardQuery) ? $boardQuery : '',
									'topic' => !empty($search_params['topic']) ? $search_params['topic'] : 0,
									'min_msg_id' => !empty($minMsgID) ? $minMsgID : 0,
									'max_msg_id' => !empty($maxMsgID) ? $maxMsgID : 0,
									'excluded_phrases' => !empty($excludedPhrases) ? $excludedPhrases : array(),
									'excluded_index_words' => !empty($excludedIndexWords) ? $excludedIndexWords : array(),
									'excluded_subject_words' => !empty($excludedSubjectWords) ? $excludedSubjectWords : array(),
								),
							);

							$ignoreRequest = $searchAPI->indexedWordQuery($words, $search_data);

							if (!$db->support_ignore())
							{
								while ($row = $db->fetch_row($ignoreRequest))
								{
									// No duplicates!
									if (isset($inserts[$row[0]]))
										continue;

									$inserts[$row[0]] = $row;
								}
								$db->free_result($ignoreRequest);
								$indexedResults = count($inserts);
							}
							else
								$indexedResults += $db->affected_rows();

							if (!empty($maxMessageResults) && $indexedResults >= $maxMessageResults)
								break;
						}
					}

					// More non-MySQL stuff needed?
					if (!empty($inserts))
					{
						$db->insert('',
							'{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
							$createTemporary ? array('id_msg' => 'int') : array('id_msg' => 'int', 'id_search' => 'int'),
							$inserts,
							$createTemporary ? array('id_msg') : array('id_msg', 'id_search')
						);
					}

					if (empty($indexedResults) && empty($numSubjectResults) && !empty($modSettings['search_force_index']))
					{
						$context['search_errors']['query_not_specific_enough'] = true;
						$_REQUEST['params'] = $context['params'];
						return action_plushsearch1();
					}
					elseif (!empty($indexedResults))
					{
						$main_query['inner_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages AS lsm ON (lsm.id_msg = m.id_msg)';
						if (!$createTemporary)
						{
							$main_query['where'][] = 'lsm.id_search = {int:id_search}';
							$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
						}
					}
				}
				// Not using an index? All conditions have to be carried over.
				else
				{
					$orWhere = array();
					$count = 0;
					foreach ($searchWords as $orIndex => $words)
					{
						$where = array();
						foreach ($words['all_words'] as $regularWord)
						{
							$where[] = 'm.body' . (in_array($regularWord, $excludedWords) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
							if (in_array($regularWord, $excludedWords))
								$where[] = 'm.subject NOT' . (empty($modSettings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
							$main_query['parameters']['all_word_body_' . $count++] = empty($modSettings['search_match_words']) || $no_regexp ? '%' . strtr($regularWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $regularWord), '\\\'') . '[[:>:]]';
						}
						if (!empty($where))
							$orWhere[] = count($where) > 1 ? '(' . implode(' AND ', $where) . ')' : $where[0];
					}
					if (!empty($orWhere))
						$main_query['where'][] = count($orWhere) > 1 ? '(' . implode(' OR ', $orWhere) . ')' : $orWhere[0];

					if (!empty($userQuery))
					{
						$main_query['where'][] = '{raw:user_query}';
						$main_query['parameters']['user_query'] = $userQuery;
					}
					if (!empty($search_params['topic']))
					{
						$main_query['where'][] = 'm.id_topic = {int:topic}';
						$main_query['parameters']['topic'] = $search_params['topic'];
					}
					if (!empty($minMsgID))
					{
						$main_query['where'][] = 'm.id_msg >= {int:min_msg_id}';
						$main_query['parameters']['min_msg_id'] = $minMsgID;
					}
					if (!empty($maxMsgID))
					{
						$main_query['where'][] = 'm.id_msg <= {int:max_msg_id}';
						$main_query['parameters']['max_msg_id'] = $maxMsgID;
					}
					if (!empty($boardQuery))
					{
						$main_query['where'][] = 'm.id_board {raw:board_query}';
						$main_query['parameters']['board_query'] = $boardQuery;
					}
				}
				call_integration_hook('integrate_main_search_query', array(&$main_query));

				// Did we either get some indexed results, or otherwise did not do an indexed query?
				if (!empty($indexedResults) || !$searchAPI->supportsMethod('indexedWordQuery', $query_params))
				{
					$relevance = '1000 * (';
					$new_weight_total = 0;
					foreach ($main_query['weights'] as $type => $value)
					{
						$relevance .= $weight[$type];
						if (!empty($value['search']))
							$relevance .= ' * ' . $value['search'];
						$relevance .= ' + ';
						$new_weight_total += $weight[$type];
					}
					$main_query['select']['relevance'] = substr($relevance, 0, -3) . ') / ' . $new_weight_total . ' AS relevance';

					$ignoreRequest = $db_search->search_query('insert_log_search_results_no_index', ($db->support_ignore() ? ( '
						INSERT IGNORE INTO ' . '{db_prefix}log_search_results
							(' . implode(', ', array_keys($main_query['select'])) . ')') : '') . '
						SELECT
							' . implode(',
							', $main_query['select']) . '
						FROM ' . $main_query['from'] . (empty($main_query['inner_join']) ? '' : '
							INNER JOIN ' . implode('
							INNER JOIN ', $main_query['inner_join'])) . (empty($main_query['left_join']) ? '' : '
							LEFT JOIN ' . implode('
							LEFT JOIN ', $main_query['left_join'])) . (!empty($main_query['where']) ? '
						WHERE ' : '') . implode('
							AND ', $main_query['where']) . (empty($main_query['group_by']) ? '' : '
						GROUP BY ' . implode(', ', $main_query['group_by'])) . (empty($modSettings['search_max_results']) ? '' : '
						LIMIT ' . $modSettings['search_max_results']),
						$main_query['parameters']
					);

					// We love to handle non-good databases that don't support our ignore!
					if (!$db->support_ignore())
					{
						$inserts = array();
						while ($row = $db->fetch_row($ignoreRequest))
						{
							// No duplicates!
							if (isset($inserts[$row[2]]))
								continue;

							foreach ($row as $key => $value)
								$inserts[$row[2]][] = (int) $row[$key];
						}
						$db->free_result($ignoreRequest);

						// Now put them in!
						if (!empty($inserts))
						{
							$query_columns = array();
							foreach ($main_query['select'] as $k => $v)
								$query_columns[$k] = 'int';

							$db->insert('',
								'{db_prefix}log_search_results',
								$query_columns,
								$inserts,
								array('id_search', 'id_topic')
							);
						}
						$_SESSION['search_cache']['num_results'] += count($inserts);
					}
					else
						$_SESSION['search_cache']['num_results'] = $db->affected_rows();
				}

				// Insert subject-only matches.
				if ($_SESSION['search_cache']['num_results'] < $modSettings['search_max_results'] && $numSubjectResults !== 0)
				{
					$relevance = '1000 * (';
					foreach ($weight_factors as $type => $value)
						if (isset($value['results']))
						{
							$relevance .= $weight[$type];
							if (!empty($value['results']))
								$relevance .= ' * ' . $value['results'];
							$relevance .= ' + ';
						}
					$relevance = substr($relevance, 0, -3) . ') / ' . $weight_total . ' AS relevance';

					$usedIDs = array_flip(empty($inserts) ? array() : array_keys($inserts));
					$ignoreRequest = $db_search->search_query('insert_log_search_results_sub_only', ($db->support_ignore() ? ( '
						INSERT IGNORE INTO {db_prefix}log_search_results
							(id_search, id_topic, relevance, id_msg, num_matches)') : '') . '
						SELECT
							{int:id_search},
							t.id_topic,
							' . $relevance . ',
							t.id_first_msg,
							1
						FROM {db_prefix}topics AS t
							INNER JOIN {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (lst.id_topic = t.id_topic)'
						. ($createTemporary ? '' : ' WHERE lst.id_search = {int:id_search}')
						. (empty($modSettings['search_max_results']) ? '' : '
						LIMIT ' . ($modSettings['search_max_results'] - $_SESSION['search_cache']['num_results'])),
						array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
						)
					);
					// Once again need to do the inserts if the database don't support ignore!
					if (!$db->support_ignore())
					{
						$inserts = array();
						while ($row = $db->fetch_row($ignoreRequest))
						{
							// No duplicates!
							if (isset($usedIDs[$row[1]]))
								continue;

							$usedIDs[$row[1]] = true;
							$inserts[] = $row;
						}
						$db->free_result($ignoreRequest);

						// Now put them in!
						if (!empty($inserts))
						{
							$db->insert('',
								'{db_prefix}log_search_results',
								array('id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'float', 'id_msg' => 'int', 'num_matches' => 'int'),
								$inserts,
								array('id_search', 'id_topic')
							);
						}
						$_SESSION['search_cache']['num_results'] += count($inserts);
					}
					else
						$_SESSION['search_cache']['num_results'] += $db->affected_rows();
				}
				elseif ($_SESSION['search_cache']['num_results'] == -1)
					$_SESSION['search_cache']['num_results'] = 0;
			}
		}

		// *** Retrieve the results to be shown on the page
		$participants = array();
		$request = $db_search->search_query('', '
			SELECT ' . (empty($search_params['topic']) ? 'lsr.id_topic' : $search_params['topic'] . ' AS id_topic') . ', lsr.id_msg, lsr.relevance, lsr.num_matches
			FROM {db_prefix}log_search_results AS lsr' . ($search_params['sort'] == 'num_replies' ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = lsr.id_topic)' : '') . '
			WHERE lsr.id_search = {int:id_search}
			ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
			LIMIT ' . (int) $_REQUEST['start'] . ', ' . $modSettings['search_results_per_page'],
			array(
				'id_search' => $_SESSION['search_cache']['id_search'],
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$context['topics'][$row['id_msg']] = array(
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => array(),
			);
			// By default they didn't participate in the topic!
			$participants[$row['id_topic']] = false;
		}
		$db->free_result($request);

		$num_results = $_SESSION['search_cache']['num_results'];
	}

	if (!empty($context['topics']))
	{
		// Create an array for the permissions.
		$boards_can = boardsAllowedTo(array('post_reply_own', 'post_reply_any', 'mark_any_notify'), true, false);

		// How's about some quick moderation?
		if (!empty($options['display_quick_mod']))
		{
			$boards_can = array_merge($boards_can, boardsAllowedTo(array('lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any'), true, false));

			$context['can_lock'] = in_array(0, $boards_can['lock_any']);
			$context['can_sticky'] = in_array(0, $boards_can['make_sticky']) && !empty($modSettings['enableStickyTopics']);
			$context['can_move'] = in_array(0, $boards_can['move_any']);
			$context['can_remove'] = in_array(0, $boards_can['remove_any']);
			$context['can_merge'] = in_array(0, $boards_can['merge_any']);
		}

		// What messages are we using?
		$msg_list = array_keys($context['topics']);

		// Load the posters...
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_member != {int:no_member}
				AND id_msg IN ({array_int:message_list})
			LIMIT ' . count($context['topics']),
			array(
				'message_list' => $msg_list,
				'no_member' => 0,
			)
		);
		$posters = array();
		while ($row = $db->fetch_assoc($request))
			$posters[] = $row['id_member'];
		$db->free_result($request);

		call_integration_hook('integrate_search_message_list', array(&$msg_list, &$posters));

		if (!empty($posters))
			loadMemberData(array_unique($posters));

		// Get the messages out for the callback - select enough that it can be made to look just like Display.
		$messages_request = $db->query('', '
			SELECT
				m.id_msg, m.subject, m.poster_name, m.poster_email, m.poster_time, m.id_member,
				m.icon, m.poster_ip, m.body, m.smileys_enabled, m.modified_time, m.modified_name,
				first_m.id_msg AS first_msg, first_m.subject AS first_subject, first_m.icon AS first_icon, first_m.poster_time AS first_poster_time,
				first_mem.id_member AS first_member_id, IFNULL(first_mem.real_name, first_m.poster_name) AS first_member_name,
				last_m.id_msg AS last_msg, last_m.poster_time AS last_poster_time, last_mem.id_member AS last_member_id,
				IFNULL(last_mem.real_name, last_m.poster_name) AS last_member_name, last_m.icon AS last_icon, last_m.subject AS last_subject,
				t.id_topic, t.is_sticky, t.locked, t.id_poll, t.num_replies, t.num_views,
				b.id_board, b.name AS board_name, c.id_cat, c.name AS cat_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				INNER JOIN {db_prefix}messages AS first_m ON (first_m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS last_m ON (last_m.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS first_mem ON (first_mem.id_member = first_m.id_member)
				LEFT JOIN {db_prefix}members AS last_mem ON (last_mem.id_member = first_m.id_member)
			WHERE m.id_msg IN ({array_int:message_list})' . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY FIND_IN_SET(m.id_msg, {string:message_list_in_set})
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'is_approved' => 1,
				'message_list_in_set' => implode(',', $msg_list),
				'limit' => count($context['topics']),
			)
		);

		// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
		if ($db->num_rows($messages_request) == 0)
			$context['topics'] = array();

		// If we want to know who participated in what then load this now.
		if (!empty($modSettings['enableParticipation']) && !$user_info['is_guest'])
		{
			require_once(SUBSDIR . '/MessageIndex.subs.php');
			$topics_participated_in = topicsParticipation($user_info['id'], array_keys($participants));
			foreach ($topics_participated_in as $topic)
				$participants[$topic['id_topic']] = true;
		}
	}

	// Now that we know how many results to expect we can start calculating the page numbers.
	$context['page_index'] = constructPageIndex($scripturl . '?action=search2;params=' . $context['params'], $_REQUEST['start'], $num_results, $modSettings['search_results_per_page'], false);

	// Consider the search complete!
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
		cache_put_data('search_start:' . ($user_info['is_guest'] ? $user_info['ip'] : $user_info['id']), null, 90);

	$context['key_words'] = &$searchArray;

	// Setup the default topic icons... for checking they exist and the like!
	$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved', 'recycled', 'wireless', 'clip');
	$context['icon_sources'] = array();
	foreach ($stable_icons as $icon)
		$context['icon_sources'][$icon] = 'images_url';

	$context['sub_template'] = 'results';
	$context['page_title'] = $txt['search_results'];
	$context['get_topics'] = 'prepareSearchContext';

	$context['jump_to'] = array(
		'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
		'board_name' => addslashes(un_htmlspecialchars($txt['select_destination'])),
	);
}

/**
 * Allows to search through personal messages.
 * ?action=pm;sa=search
 * What it does:
 * - shows the screen to search pm's (?action=pm;sa=search)
 * - uses the search sub template of the PersonalMessage template.
 * - decodes and loads search parameters given in the URL (if any).
 * - the form redirects to index.php?action=pm;sa=search2.
 */
function MessageSearch()
{
	global $context, $txt, $scripturl, $modSettings;

	$db = database();

	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}
	}

	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);
	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;
	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];
	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

	// Create the array of labels to be searched.
	$context['search_labels'] = array();
	$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : array();
	foreach ($context['labels'] as $label)
	{
		$context['search_labels'][] = array(
			'id' => $label['id'],
			'name' => $label['name'],
			'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
		);
	}

	// Are all the labels checked?
	$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) == count($searchedLabels);

	// Load the error text strings if there were errors in the search.
	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error === 'messages')
				continue;

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	$context['simple_search'] = isset($context['search_params']['advanced']) ? empty($context['search_params']['advanced']) : !empty($modSettings['simpleSearch']) && !isset($_REQUEST['advanced']);
	$context['page_title'] = $txt['pm_search_title'];
	$context['sub_template'] = 'search';
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=search',
		'name' => $txt['pm_search_bar_title'],
	);
}

/**
 * Actually do the search of personal messages and show the results
 * ?action=pm;sa=search2
 * What it does:
 * - checks user input and searches the pm table for messages matching the query.
 * - uses the search_results sub template of the PersonalMessage template.
 * - show the results of the search query.
 */
function MessageSearch2()
{
	global $scripturl, $modSettings, $user_info, $context, $txt;
	global $memberContext;

	$db = database();

	if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	// Some useful general permissions.
	$context['can_send_pm'] = allowedTo('pm_send');

	// Some hardcoded veriables that can be tweaked if required.
	$maxMembersToSearch = 500;

	// Extract all the search parameters.
	$search_params = array();
	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}
	}

	$context['start'] = isset($_GET['start']) ? (int) $_GET['start'] : 0;

	// Store whether simple search was used (needed if the user wants to do another query).
	if (!isset($search_params['advanced']))
		$search_params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;

	// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;

	// Minimum age of messages. Default to zero (don't set param in that case).
	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

	// Maximum age of messages. Default to infinite (9999 days: param not set).
	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	// Search modifiers
	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);
	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);
	$search_params['sent_only'] = !empty($search_params['sent_only']) || !empty($_REQUEST['sent_only']);
	$context['folder'] = empty($search_params['sent_only']) ? 'inbox' : 'sent';

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// This will be full of all kinds of parameters!
	$searchq_parameters = array();

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr(Util::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}

		// Who matches those criteria?
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE real_name LIKE {raw:real_name_implode}',
			array(
				'real_name_implode' => '\'' . implode('\' OR real_name LIKE \'', $possible_users) . '\'',
			)
		);
		// Simply do nothing if there're too many members matching the criteria.
		if ($db->num_rows($request) > $maxMembersToSearch)
			$userQuery = '';
		elseif ($db->num_rows($request) == 0)
		{
			if ($context['folder'] === 'inbox')
				$userQuery = 'AND pm.id_member_from = 0 AND (pm.from_name LIKE {raw:guest_user_name_implode})';
			else
				$userQuery = '';

			$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR pm.from_name LIKE \'', $possible_users) . '\'';
		}
		else
		{
			$memberlist = array();
			while ($row = $db->fetch_assoc($request))
				$memberlist[] = $row['id_member'];

			// Use the name as as sent from or sent to
			if ($context['folder'] === 'inbox')
				$userQuery = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND (pm.from_name LIKE {raw:guest_user_name_implode})))';
			else
				$userQuery = 'AND (pmr.id_member IN ({array_int:member_list}))';

			$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR pm.from_name LIKE \'', $possible_users) . '\'';
			$searchq_parameters['member_list'] = $memberlist;
		}
		$db->free_result($request);
	}

	// Setup the sorting variables...
	$sort_columns = array(
		'pm.id_pm',
	);
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'pm.id_pm';
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Sort out any labels we may be searching by.
	$labelQuery = '';
	if ($context['folder'] == 'inbox' && !empty($search_params['advanced']) && $context['currently_using_labels'])
	{
		// Came here from pagination?  Put them back into $_REQUEST for sanitization.
		if (isset($search_params['labels']))
			$_REQUEST['searchlabel'] = explode(',', $search_params['labels']);

		// Assuming we have some labels - make them all integers.
		if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel']))
		{
			foreach ($_REQUEST['searchlabel'] as $key => $id)
				$_REQUEST['searchlabel'][$key] = (int) $id;
		}
		else
			$_REQUEST['searchlabel'] = array();

		// Now that everything is cleaned up a bit, make the labels a param.
		$search_params['labels'] = implode(',', $_REQUEST['searchlabel']);

		// No labels selected? That must be an error!
		if (empty($_REQUEST['searchlabel']))
			$context['search_errors']['no_labels_selected'] = true;
		// Otherwise prepare the query!
		elseif (count($_REQUEST['searchlabel']) != count($context['labels']))
		{
			$labelQuery = '
			AND {raw:label_implode}';

			$labelStatements = array();
			foreach ($_REQUEST['searchlabel'] as $label)
				$labelStatements[] = $db->quote('FIND_IN_SET({string:label}, pmr.labels) != 0', array(
					'label' => $label,
				));

			$searchq_parameters['label_implode'] = '(' . implode(' OR ', $labelStatements) . ')';
		}
	}

	// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
	$blacklisted_words = array('quote', 'the', 'is', 'it', 'are', 'if');

	// What are we actually searching for?
	$search_params['search'] = !empty($search_params['search']) ? $search_params['search'] : (isset($_REQUEST['search']) ? $_REQUEST['search'] : '');

	// If we ain't got nothing - we should error!
	if (!isset($search_params['search']) || $search_params['search'] == '')
		$context['search_errors']['invalid_search_string'] = true;

	// Change non-word characters into spaces.
	$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $search_params['search']);

	// Make the query lower case since it will case insensitive anyway.
	$stripped_query = un_htmlspecialchars(Util::strtolower($stripped_query));

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
	$phraseArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']);
	$wordArray = explode(' ', Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

	// A minus sign in front of a word excludes the word.... so...
	$excludedWords = array();

	// Check for things like -"some words", but not "-some words".
	foreach ($matches[1] as $index => $word)
	{
		if ($word === '-')
		{
			if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($phraseArray[$index]);
		}
	}

	// Now we look for -test, etc
	foreach ($wordArray as $index => $word)
	{
		if (strpos(trim($word), '-') === 0)
		{
			if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($wordArray[$index]);
		}
	}

	// The remaining words and phrases are all included.
	$searchArray = array_merge($phraseArray, $wordArray);

	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		// Skip anything thats close to empty.
		if (($searchArray[$index] = trim($value, '-_\' ')) === '')
			unset($searchArray[$index]);
		// Skip blacklisted words. Make sure to note we skipped them as well
		elseif (in_array($searchArray[$index], $blacklisted_words))
		{
			$foundBlackListedWords = true;
			unset($searchArray[$index]);
		}

		$searchArray[$index] = Util::strtolower(trim($value));
		if ($searchArray[$index] == '')
			unset($searchArray[$index]);
		else
		{
			// Sort out entities first.
			$searchArray[$index] = Util::htmlspecialchars($searchArray[$index]);
		}
	}
	$searchArray = array_slice(array_unique($searchArray), 0, 10);

	// Create an array of replacements for highlighting.
	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

	// This contains *everything*
	$searchWords = array_merge($searchArray, $excludedWords);

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
		$context['search_errors']['invalid_search_string'] = true;

	// Sort out the search query so the user can edit it - if they want.
	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);

	// Now we have all the parameters, combine them together for pagination and the like...
	$context['params'] = array();
	foreach ($search_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;
	$context['params'] = base64_encode(implode('|"|', $context['params']));

	// Compile the subject query part.
	$andQueryParts = array();
	foreach ($searchWords as $index => $word)
	{
		if ($word == '')
			continue;

		if ($search_params['subject_only'])
			$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
		else
			$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';

		$searchq_parameters['search_' . $index] = '%' . strtr($word, array('_' => '\\_', '%' => '\\%')) . '%';
	}

	$searchQuery = ' 1=1';
	if (!empty($andQueryParts))
		$searchQuery = implode(!empty($search_params['searchtype']) && $search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);

	// Age limits?
	$timeQuery = '';
	if (!empty($search_params['minage']))
		$timeQuery .= ' AND pm.msgtime < ' . (time() - $search_params['minage'] * 86400);
	if (!empty($search_params['maxage']))
		$timeQuery .= ' AND pm.msgtime > ' . (time() - $search_params['maxage'] * 86400);

	// If we have errors - return back to the first screen...
	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
		return MessageSearch();
	}

	// Get the amount of results.
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')',
		array_merge($searchq_parameters, array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		))
	);
	list ($numResults) = $db->fetch_row($request);
	$db->free_result($request);

	// Get all the matching messages... using standard search only (No caching and the like!)
	$request = $db->query('', '
		SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')
		ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
		LIMIT ' . $context['start'] . ', ' . $modSettings['search_results_per_page'],
		array_merge($searchq_parameters, array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		))
	);
	$foundMessages = array();
	$posters = array();
	$head_pms = array();
	while ($row = $db->fetch_assoc($request))
	{
		$foundMessages[] = $row['id_pm'];
		$posters[] = $row['id_member_from'];
		$head_pms[$row['id_pm']] = $row['id_pm_head'];
	}
	$db->free_result($request);

	// Find the real head pms!
	if ($context['display_mode'] == 2 && !empty($head_pms))
	{
		$request = $db->query('', '
			SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
			WHERE pm.id_pm_head IN ({array_int:head_pms})
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			GROUP BY pm.id_pm_head
			LIMIT {int:limit}',
			array(
				'head_pms' => array_unique($head_pms),
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'limit' => count($head_pms),
			)
		);
		$real_pm_ids = array();
		while ($row = $db->fetch_assoc($request))
			$real_pm_ids[$row['id_pm_head']] = $row['id_pm'];
		$db->free_result($request);
	}

	// Load the users...
	$posters = array_unique($posters);
	if (!empty($posters))
		loadMemberData($posters);

	// Sort out the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=search2;params=' . $context['params'], $_GET['start'], $numResults, $modSettings['search_results_per_page'], false);

	$context['message_labels'] = array();
	$context['message_replied'] = array();
	$context['personal_messages'] = array();

	if (!empty($foundMessages))
	{
		// Now get recipients (but don't include bcc-recipients for your inbox, you're not supposed to know :P!)
		$request = $db->query('', '
			SELECT
				pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name,
				pmr.bcc, pmr.labels, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:message_list})',
			array(
				'message_list' => $foundMessages,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($row['id_member_to'] == $user_info['id'] && $context['folder'] != 'sent')
			{
				$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);
				// This is a special need for linking to messages.
				foreach ($row['labels'] as $v)
				{
					if (isset($context['labels'][(int) $v]))
						$context['message_labels'][$row['id_pm']][(int) $v] = array('id' => $v, 'name' => $context['labels'][(int) $v]['name']);

					// Here we find the first label on a message - for linking to posts in results
					if (!isset($context['first_label'][$row['id_pm']]) && !in_array('-1', $row['labels']))
						$context['first_label'][$row['id_pm']] = (int) $v;
				}
			}
		}

		// Prepare the query for the callback!
		$request = $db->query('', '
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_pm IN ({array_int:message_list})
			ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
			LIMIT ' . count($foundMessages),
			array(
				'message_list' => $foundMessages,
			)
		);
		$counter = 0;
		while ($row = $db->fetch_assoc($request))
		{
			// If there's no subject, use the default.
			$row['subject'] = $row['subject'] == '' ? $txt['no_subject'] : $row['subject'];

			// Load this posters context info, if it ain't there then fill in the essentials...
			if (!loadMemberContext($row['id_member_from'], true))
			{
				$memberContext[$row['id_member_from']]['name'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['id'] = 0;
				$memberContext[$row['id_member_from']]['group'] = $txt['guest_title'];
				$memberContext[$row['id_member_from']]['link'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['email'] = '';
				$memberContext[$row['id_member_from']]['show_email'] = showEmailAddress(true, 0);
				$memberContext[$row['id_member_from']]['is_guest'] = true;
			}

			// Censor anything we don't want to see...
			censorText($row['body']);
			censorText($row['subject']);

			// Parse out any BBC...
			$row['body'] = parse_bbc($row['body'], true, 'pm' . $row['id_pm']);

			// Highlight the hits
			foreach ($searchArray as $query)
			{
				// Fix the international characters in the keyword too.
				$query = un_htmlspecialchars($query);
				$query = trim($query, "\*+");
				$query = strtr(Util::htmlspecialchars($query), array('\\\'' => '\''));

				$body_highlighted = preg_replace('/((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '/') . ')/ieu', "'\$2' == '\$1' ? stripslashes('\$1') : '<strong class=\"highlight\">\$1</strong>'", $row['body']);
				$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $row['subject']);
			}

			$href = $scripturl . '?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] == 2 && isset($real_pm_ids[$head_pms[$row['id_pm']]]) && $context['folder'] == 'inbox' ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg' . $row['id_pm'];

			$context['personal_messages'][] = array(
				'id' => $row['id_pm'],
				'member' => &$memberContext[$row['id_member_from']],
				'subject' => $subject_highlighted,
				'body' => $body_highlighted,
				'time' => relativeTime($row['msgtime']),
				'recipients' => &$recipients[$row['id_pm']],
				'labels' => &$context['message_labels'][$row['id_pm']],
				'fully_labeled' => count($context['message_labels'][$row['id_pm']]) == count($context['labels']),
				'is_replied_to' => &$context['message_replied'][$row['id_pm']],
				'href' => $href,
				'link' => '<a href="' . $href . '">' . $subject_highlighted . '</a>',
				'counter' => ++$counter,
			);
		}
		$db->free_result($request);
	}

	// Finish off the context.
	$context['page_title'] = $txt['pm_search_title'];
	$context['sub_template'] = 'search_results';
	$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'search';
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=search',
		'name' => $txt['pm_search_bar_title'],
	);
}

/**
 * Callback to return messages - saves memory.
 * @todo Fix this, update it, whatever... from Display.controller.php mainly.
 * Note that the call to loadAttachmentContext() doesn't work:
 * this function doesn't fulfill the pre-condition to fill $attachments global...
 * So all it does is to fallback and return.
 *
 * What it does:
 * - callback function for the results sub template.
 * - loads the necessary contextual data to show a search result.
 *
 * @param $reset = false
 * @return array
 */
function prepareSearchContext($reset = false)
{
	global $txt, $modSettings, $scripturl, $user_info;
	global $memberContext, $context, $settings, $options, $messages_request;
	global $boards_can, $participants;

	// Remember which message this is.  (ie. reply #83)
	static $counter = null;
	if ($counter == null || $reset)
		$counter = $_REQUEST['start'] + 1;

	// we need this
	$db = database();

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Start from the beginning...
	if ($reset)
		return $db->data_seek($messages_request, 0);

	// Attempt to get the next message.
	$message = $db->fetch_assoc($messages_request);
	if (!$message)
		return false;

	// Can't have an empty subject can we?
	$message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

	$message['first_subject'] = $message['first_subject'] != '' ? $message['first_subject'] : $txt['no_subject'];
	$message['last_subject'] = $message['last_subject'] != '' ? $message['last_subject'] : $txt['no_subject'];

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member']))
	{
		// Notice this information isn't used anywhere else.... *cough guest table cough*.
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		$memberContext[$message['id_member']]['link'] = $message['poster_name'];
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
	}
	$memberContext[$message['id_member']]['ip'] = $message['poster_ip'];

	// Do the censor thang...
	censorText($message['body']);
	censorText($message['subject']);
	censorText($message['first_subject']);
	censorText($message['last_subject']);

	// Shorten this message if necessary.
	if ($context['compact'])
	{
		// Set the number of characters before and after the searched keyword.
		$charLimit = 50;

		$message['body'] = strtr($message['body'], array("\n" => ' ', '<br />' => "\n"));
		$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);
		$message['body'] = strip_tags(strtr($message['body'], array('</div>' => '<br />', '</li>' => '<br />')), '<br>');

		if (Util::strlen($message['body']) > $charLimit)
		{
			if (empty($context['key_words']))
				$message['body'] = Util::substr($message['body'], 0, $charLimit) . '<strong>...</strong>';
			else
			{
				$matchString = '';
				$force_partial_word = false;
				foreach ($context['key_words'] as $keyword)
				{
					$keyword = un_htmlspecialchars($keyword);
					$keyword = preg_replace('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', 'entity_fix__callback', strtr($keyword, array('\\\'' => '\'', '&' => '&amp;')));

					if (preg_match('~[\'\.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) != 0 || preg_match('~^[\'\.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword) != 0)
						$force_partial_word = true;
					$matchString .= strtr(preg_quote($keyword, '/'), array('\*' => '.+?')) . '|';
				}
				$matchString = un_htmlspecialchars(substr($matchString, 0, -1));

				$message['body'] = un_htmlspecialchars(strtr($message['body'], array('&nbsp;' => ' ', '<br />' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@')));

				if (empty($modSettings['search_method']) || $force_partial_word)
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/isu', $message['body'], $matches);
				else
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/isu', $message['body'], $matches);

				$message['body'] = '';
				foreach ($matches[0] as $index => $match)
				{
					$match = strtr(htmlspecialchars($match, ENT_QUOTES), array("\n" => '&nbsp;'));
					$message['body'] .= '<strong>......</strong>&nbsp;' . $match . '&nbsp;<strong>......</strong>';
				}
			}

			// Re-fix the international characters.
			$message['body'] = preg_replace('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', 'entity_fix__callback', $message['body']);
		}
	}
	else
	{
		// Run BBC interpreter on the message.
		$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);
	}

	// Make sure we don't end up with a practically empty message body.
	$message['body'] = preg_replace('~^(?:&nbsp;)+$~', '', $message['body']);

	// Sadly, we need to check that the icon is not broken.
	if (!empty($modSettings['messageIconChecks_enable']))
	{
		if (!isset($context['icon_sources'][$message['first_icon']]))
			$context['icon_sources'][$message['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
		if (!isset($context['icon_sources'][$message['last_icon']]))
			$context['icon_sources'][$message['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		if (!isset($context['icon_sources'][$message['icon']]))
			$context['icon_sources'][$message['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['icon'] . '.png') ? 'images_url' : 'default_images_url';
	}
	else
	{
		if (!isset($context['icon_sources'][$message['first_icon']]))
			$context['icon_sources'][$message['first_icon']] = 'images_url';
		if (!isset($context['icon_sources'][$message['last_icon']]))
			$context['icon_sources'][$message['last_icon']] = 'images_url';
		if (!isset($context['icon_sources'][$message['icon']]))
			$context['icon_sources'][$message['icon']] = 'images_url';
	}

	// Do we have quote tag enabled?
	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

	$output = array_merge($context['topics'][$message['id_msg']], array(
		'id' => $message['id_topic'],
		'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($message['is_sticky']),
		'is_locked' => !empty($message['locked']),
		'is_poll' => $modSettings['pollMode'] == '1' && $message['id_poll'] > 0,
		'is_hot' => $message['num_replies'] >= $modSettings['hotTopicPosts'],
		'is_very_hot' => $message['num_replies'] >= $modSettings['hotTopicVeryPosts'],
		'posted_in' => !empty($participants[$message['id_topic']]),
		'views' => $message['num_views'],
		'replies' => $message['num_replies'],
		'can_reply' => in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any']),
		'can_quote' => (in_array($message['id_board'], $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any'])) && $quote_enabled,
		'can_mark_notify' => in_array($message['id_board'], $boards_can['mark_any_notify']) || in_array(0, $boards_can['mark_any_notify']) && !$context['user']['is_guest'],
		'first_post' => array(
			'id' => $message['first_msg'],
			'time' => relativeTime($message['first_poster_time']),
			'timestamp' => forum_time(true, $message['first_poster_time']),
			'subject' => $message['first_subject'],
			'href' => $scripturl . '?topic=' . $message['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $message['id_topic'] . '.0">' . $message['first_subject'] . '</a>',
			'icon' => $message['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$message['first_icon']]] . '/post/' . $message['first_icon'] . '.png',
			'member' => array(
				'id' => $message['first_member_id'],
				'name' => $message['first_member_name'],
				'href' => !empty($message['first_member_id']) ? $scripturl . '?action=profile;u=' . $message['first_member_id'] : '',
				'link' => !empty($message['first_member_id']) ? '<a href="' . $scripturl . '?action=profile;u=' . $message['first_member_id'] . '" title="' . $txt['profile_of'] . ' ' . $message['first_member_name'] . '">' . $message['first_member_name'] . '</a>' : $message['first_member_name']
			)
		),
		'last_post' => array(
			'id' => $message['last_msg'],
			'time' => relativeTime($message['last_poster_time']),
			'timestamp' => forum_time(true, $message['last_poster_time']),
			'subject' => $message['last_subject'],
			'href' => $scripturl . '?topic=' . $message['id_topic'] . ($message['num_replies'] == 0 ? '.0' : '.msg' . $message['last_msg']) . '#msg' . $message['last_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $message['id_topic'] . ($message['num_replies'] == 0 ? '.0' : '.msg' . $message['last_msg']) . '#msg' . $message['last_msg'] . '">' . $message['last_subject'] . '</a>',
			'icon' => $message['last_icon'],
			'icon_url' => $settings[$context['icon_sources'][$message['last_icon']]] . '/post/' . $message['last_icon'] . '.png',
			'member' => array(
				'id' => $message['last_member_id'],
				'name' => $message['last_member_name'],
				'href' => !empty($message['last_member_id']) ? $scripturl . '?action=profile;u=' . $message['last_member_id'] : '',
				'link' => !empty($message['last_member_id']) ? '<a href="' . $scripturl . '?action=profile;u=' . $message['last_member_id'] . '" title="' . $txt['profile_of'] . ' ' . $message['last_member_name'] . '">' . $message['last_member_name'] . '</a>' : $message['last_member_name']
			)
		),
		'board' => array(
			'id' => $message['id_board'],
			'name' => $message['board_name'],
			'href' => $scripturl . '?board=' . $message['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $message['id_board'] . '.0">' . $message['board_name'] . '</a>'
		),
		'category' => array(
			'id' => $message['id_cat'],
			'name' => $message['cat_name'],
			'href' => $scripturl . '#c' . $message['id_cat'],
			'link' => '<a href="' . $scripturl . '#c' . $message['id_cat'] . '">' . $message['cat_name'] . '</a>'
		)
	));
	determineTopicClass($output);

	if ($output['posted_in'])
		$output['class'] = 'my_' . $output['class'];

	$body_highlighted = $message['body'];
	$subject_highlighted = $message['subject'];

	if (!empty($options['display_quick_mod']))
	{
		$started = $output['first_post']['member']['id'] == $user_info['id'];

		$output['quick_mod'] = array(
			'lock' => in_array(0, $boards_can['lock_any']) || in_array($output['board']['id'], $boards_can['lock_any']) || ($started && (in_array(0, $boards_can['lock_own']) || in_array($output['board']['id'], $boards_can['lock_own']))),
			'sticky' => (in_array(0, $boards_can['make_sticky']) || in_array($output['board']['id'], $boards_can['make_sticky'])) && !empty($modSettings['enableStickyTopics']),
			'move' => in_array(0, $boards_can['move_any']) || in_array($output['board']['id'], $boards_can['move_any']) || ($started && (in_array(0, $boards_can['move_own']) || in_array($output['board']['id'], $boards_can['move_own']))),
			'remove' => in_array(0, $boards_can['remove_any']) || in_array($output['board']['id'], $boards_can['remove_any']) || ($started && (in_array(0, $boards_can['remove_own']) || in_array($output['board']['id'], $boards_can['remove_own']))),
		);

		$context['can_lock'] |= $output['quick_mod']['lock'];
		$context['can_sticky'] |= $output['quick_mod']['sticky'];
		$context['can_move'] |= $output['quick_mod']['move'];
		$context['can_remove'] |= $output['quick_mod']['remove'];
		$context['can_merge'] |= in_array($output['board']['id'], $boards_can['merge_any']);
		$context['can_markread'] = $context['user']['is_logged'];

		$context['qmod_actions'] = array('remove', 'lock', 'sticky', 'move', 'markread');
		call_integration_hook('integrate_quick_mod_actions_search');
	}

	foreach ($context['key_words'] as $query)
	{
		// Fix the international characters in the keyword too.
		$query = un_htmlspecialchars($query);
		$query = trim($query, "\*+");
		$query = strtr(Util::htmlspecialchars($query), array('\\\'' => '\''));

		$body_highlighted = preg_replace('/((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '/') . ')/ieu', "'\$2' == '\$1' ? stripslashes('\$1') : '<strong class=\"highlight\">\$1</strong>'", $body_highlighted);
		$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $subject_highlighted);
	}

	$output['matches'][] = array(
		'id' => $message['id_msg'],
		'attachment' => loadAttachmentContext($message['id_msg']),
		'alternate' => $counter % 2,
		'member' => &$memberContext[$message['id_member']],
		'icon' => $message['icon'],
		'icon_url' => $settings[$context['icon_sources'][$message['icon']]] . '/post/' . $message['icon'] . '.png',
		'subject' => $message['subject'],
		'subject_highlighted' => $subject_highlighted,
		'time' => relativeTime($message['poster_time']),
		'timestamp' => forum_time(true, $message['poster_time']),
		'counter' => $counter,
		'modified' => array(
			'time' => relativeTime($message['modified_time']),
			'timestamp' => forum_time(true, $message['modified_time']),
			'name' => $message['modified_name']
		),
		'body' => $message['body'],
		'body_highlighted' => $body_highlighted,
		'start' => 'msg' . $message['id_msg']
	);
	$counter++;

	call_integration_hook('integrate_search_message_context', array($counter, &$output));

	return $output;
}

/**
 * This function compares the length of two strings plus a little.
 * What it does:
 * - callback function for usort used to sort the fulltext results.
 * - passes sorting duty to the current API.
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function searchSort($a, $b)
{
	global $searchAPI;

	return $searchAPI->searchSort($a, $b);
}
