<?php

/**
 * Provides ways to add information to your website by linking to and capturing output
 * from ElkArte
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.10
 *
 */

// Don't do anything if ElkArte is already loaded.
if (defined('ELK'))
	return true;

define('ELK', 'SSI');

// Shortcut for the browser cache stale
define('CACHE_STALE', '?1010');

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
global $boardurl, $webmaster_email, $cookiename;
global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
global $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd;
global $sourcedir, $boarddir;

// Remember the current configuration so it can be set back.
$ssi_magic_quotes_runtime = function_exists('get_magic_quotes_gpc') && get_magic_quotes_runtime();
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime(0);
$time_start = microtime(true);

// Just being safe...
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable]);

// Get the forum's settings for database and file paths.
require_once(dirname(__FILE__) . '/Settings.php');

// Fix for using the current directory as a path.
if (substr($sourcedir, 0, 1) == '.' && substr($sourcedir, 1, 1) != '.')
	$sourcedir = dirname(__FILE__) . substr($sourcedir, 1);

// Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . '/agreement.txt'))
	$boarddir = dirname(__FILE__);
if (!file_exists($sourcedir) && file_exists($boarddir . '/sources'))
	$sourcedir = $boarddir . '/sources';

// Check that directories which didn't exist in past releases are initialized.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';
if ((empty($extdir) || !file_exists($extdir)) && file_exists($sourcedir . '/ext'))
	$extdir = $sourcedir . '/ext';
if ((empty($languagedir) || !file_exists($languagedir)) && file_exists($boarddir . '/themes/default/languages'))
	$languagedir = $boarddir . '/themes/default/languages';

// Time to forget about variables and go with constants!
DEFINE('BOARDDIR', $boarddir);
DEFINE('CACHEDIR', $cachedir);
DEFINE('EXTDIR', $extdir);
DEFINE('LANGUAGEDIR', $languagedir);
DEFINE('SOURCEDIR', $sourcedir);
DEFINE('ADMINDIR', $sourcedir . '/admin');
DEFINE('CONTROLLERDIR', $sourcedir . '/controllers');
DEFINE('SUBSDIR', $sourcedir . '/subs');
unset($boarddir, $cachedir, $sourcedir, $languagedir, $extdir);

$ssi_error_reporting = error_reporting(E_ALL | E_STRICT);
/**
 * Set this to one of three values depending on what you want to happen in the case of a fatal error.
 *  - false: Default, will just load the error sub template and die - not putting any theme layers around it.
 *  - true: Will load the error sub template AND put the template layers around it (Not useful if on total custom pages).
 *  - string: Name of a callback function to call in the event of an error to allow you to define your own methods. Will die after function returns.
 */
$ssi_on_error_method = false;

// Don't do john didley if the forum's been shut down competely.
if ($maintenance == 2 && (!isset($ssi_maintenance_off) || $ssi_maintenance_off !== true))
	die($mmessage);

// Load the important includes.
require_once(SOURCEDIR . '/QueryString.php');
require_once(SOURCEDIR . '/Session.php');
require_once(SOURCEDIR . '/Subs.php');
require_once(SOURCEDIR . '/Errors.php');
require_once(SOURCEDIR . '/Logging.php');
require_once(SOURCEDIR . '/Load.php');
require_once(SUBSDIR . '/Cache.subs.php');
require_once(SOURCEDIR . '/Security.php');
require_once(SOURCEDIR . '/BrowserDetector.class.php');
require_once(SOURCEDIR . '/ErrorContext.class.php');
require_once(SUBSDIR . '/Util.class.php');
require_once(SUBSDIR . '/TemplateLayers.class.php');
require_once(SOURCEDIR . '/Action.controller.php');

// Clean the request variables.
cleanRequest();

// Initiate the database connection and define some database functions to use.
loadDatabase();

// Load settings from the database.
reloadSettings();

// Seed the random generator?
elk_seed_generator();

// Check on any hacking attempts.
if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
	die('Hacking attempt...');
elseif (isset($_REQUEST['ssi_theme']) && (int) $_REQUEST['ssi_theme'] == (int) $ssi_theme)
	die('Hacking attempt...');
elseif (isset($_COOKIE['ssi_theme']) && (int) $_COOKIE['ssi_theme'] == (int) $ssi_theme)
	die('Hacking attempt...');
elseif (isset($_REQUEST['ssi_layers'], $ssi_layers) && (@get_magic_quotes_gpc() ? stripslashes($_REQUEST['ssi_layers']) : $_REQUEST['ssi_layers']) == $ssi_layers)
	die('Hacking attempt...');
if (isset($_REQUEST['context']))
	die('Hacking attempt...');

// Gzip output? (because it must be boolean and true, this can't be hacked.)
if (isset($ssi_gzip) && $ssi_gzip === true && ini_get('zlib.output_compression') != '1' && ini_get('output_handler') != 'ob_gzhandler' && version_compare(PHP_VERSION, '4.2.0', '>='))
	ob_start('ob_gzhandler');
else
	$modSettings['enableCompressedOutput'] = '0';

// Primarily, this is to fix the URLs...
ob_start('ob_sessrewrite');

// Start the session... known to scramble SSI includes in cases...
if (!headers_sent())
	loadSession();
else
{
	if (isset($_COOKIE[session_name()]) || isset($_REQUEST[session_name()]))
	{
		// Make a stab at it, but ignore the E_WARNINGs generated because we can't send headers.
		$temp = error_reporting(error_reporting() & !E_WARNING);
		loadSession();
		error_reporting($temp);
	}

	if (!isset($_SESSION['session_value']))
	{
		$_SESSION['session_var'] = substr(md5(mt_rand() . session_id() . mt_rand()), 0, rand(7, 12));
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
	}
	$sc = $_SESSION['session_value'];
	// This is here only to avoid session errors in PHP7
	// microtime effectively forces the replacing of the session in the db each
	// time the page is loaded
	$_SESSION['mictrotime'] = microtime();
}

// Get rid of $board and $topic... do stuff loadBoard would do.
unset($board, $topic);
$user_info['is_mod'] = false;
$context['user']['is_mod'] = &$user_info['is_mod'];
$context['linktree'] = array();

// Load the user and their cookie, as well as their settings.
loadUserSettings();

// Load the current user's permissions....
loadPermissions();

// Load BadBehavior functions
loadBadBehavior();

// Load the current or SSI theme. (just use $ssi_theme = id_theme;)
loadTheme(isset($ssi_theme) ? (int) $ssi_theme : 0);

// @todo: probably not the best place, but somewhere it should be set...
if (!headers_sent())
	header('Content-Type: text/html; charset=UTF-8');

// Take care of any banning that needs to be done.
if (isset($_REQUEST['ssi_ban']) || (isset($ssi_ban) && $ssi_ban === true))
	is_not_banned();

// Do we allow guests in here?
if (empty($ssi_guest_access) && empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && basename($_SERVER['PHP_SELF']) != 'SSI.php')
{
	require_once(CONTROLLERDIR . '/Auth.controller.php');
	$controller = new Auth_Controller();
	$controller->action_kickguest();
	obExit(null, true);
}

// Load the stuff like the menu bar, etc.
if (isset($ssi_layers))
{
	$template_layers = Template_Layers::getInstance();
	$template_layers->removeAll();
	foreach ($ssi_layers as $layer)
		$template_layers->addBegin($layer);
	template_header();
}
else
	setupThemeContext();

// We need to set up user agent, and make more checks on the request
$req = request();

// Make sure they didn't muss around with the settings... but only if it's not cli.
if (isset($_SERVER['REMOTE_ADDR']) && session_id() == '')
	trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);

// Without visiting the forum this session variable might not be set on submit.
if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
	$_SESSION['USER_AGENT'] = $req->user_agent();

// Have the ability to easily add functions to SSI.
call_integration_hook('integrate_SSI');

// Call a function passed by GET.
if (isset($_GET['ssi_function']) && function_exists('ssi_' . $_GET['ssi_function']) && (!empty($modSettings['allow_guestAccess']) || !$user_info['is_guest']))
{
	call_user_func('ssi_' . $_GET['ssi_function']);
	exit;
}

if (isset($_GET['ssi_function']))
	exit;
// You shouldn't just access SSI.php directly by URL!!
elseif (basename($_SERVER['PHP_SELF']) == 'SSI.php')
	die(sprintf($txt['ssi_not_direct'], $user_info['is_admin'] ? '\'' . addslashes(__FILE__) . '\'' : '\'SSI.php\''));

error_reporting($ssi_error_reporting);
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime($ssi_magic_quotes_runtime);

return true;

/**
 * This shuts down the SSI and shows the footer.
 */
function ssi_shutdown()
{
	if (!isset($_GET['ssi_function']) || $_GET['ssi_function'] != 'shutdown')
		template_footer();
}

/**
 * Display a welcome message, like:
 * "Hey, User, you have 0 messages, 0 are new."
 *
 * @param string $output_method
 */
function ssi_welcome($output_method = 'echo')
{
	global $context, $txt, $scripturl;

	if ($output_method == 'echo')
	{
		if ($context['user']['is_guest'])
			echo replaceBasicActionUrl($txt[$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest']);
		else
			echo $txt['hello_member'], ' <strong>', $context['user']['name'], '</strong>', allowedTo('pm_read') ? ', ' . (empty($context['user']['messages']) ? $txt['msg_alert_no_messages'] : (($context['user']['messages'] == 1 ? sprintf($txt['msg_alert_one_message'], $scripturl . '?action=pm') : sprintf($txt['msg_alert_many_message'], $scripturl . '?action=pm', $context['user']['messages'])) . ', ' . ($context['user']['unread_messages'] == 1 ? $txt['msg_alert_one_new'] : sprintf($txt['msg_alert_many_new'], $context['user']['unread_messages'])))) : '';
	}
	// Don't echo... then do what?!
	else
		return $context['user'];
}

/**
 * Display a menu bar, like is displayed at the top of the forum.
 *
 * @param string $output_method
 */
function ssi_menubar($output_method = 'echo')
{
	global $context;

	if ($output_method == 'echo')
		template_menu();
	// What else could this do?
	else
		return $context['menu_buttons'];
}

/**
 * Show a logout link.
 *
 * @param string $redirect_to
 * @param string $output_method = 'echo'
 */
function ssi_logout($redirect_to = '', $output_method = 'echo')
{
	global $context, $txt, $scripturl;

	if ($redirect_to != '')
		$_SESSION['logout_url'] = $redirect_to;

	// Guests can't log out.
	if ($context['user']['is_guest'])
		return false;

	$link = '<a href="' . $scripturl . '?action=logout;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['logout'] . '</a>';

	if ($output_method == 'echo')
		echo $link;
	else
		return $link;
}

/**
 * Recent post list:
 *  [board] Subject by Poster Date
 *
 * @todo this may use getLastPosts with some modification
 *
 * @param int $num_recent
 * @param int[]|null $exclude_boards
 * @param int[]|null $include_boards
 * @param string $output_method
 * @param bool $limit_body
 */
function ssi_recentPosts($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo', $limit_body = true)
{
	global $modSettings;

	// Excluding certain boards...
	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

	// What about including certain boards - note we do some protection here as pre-2.0 didn't have this parameter.
	if (is_array($include_boards) || (int) $include_boards === $include_boards)
	{
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	}
	elseif ($include_boards != null)
	{
		$include_boards = array();
	}

	// Let's restrict the query boys (and girls)
	$query_where = '
		m.id_msg >= {int:min_message_id}
		' . (empty($exclude_boards) ? '' : '
		AND b.id_board NOT IN ({array_int:exclude_boards})') . '
		' . ($include_boards === null ? '' : '
		AND b.id_board IN ({array_int:include_boards})') . '
		AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
		AND m.approved = {int:is_approved}' : '');

	$query_where_params = array(
		'is_approved' => 1,
		'include_boards' => $include_boards === null ? '' : $include_boards,
		'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
		'min_message_id' => $modSettings['maxMsgID'] - 25 * min($num_recent, 5),
	);

	// Past to this simpleton of a function...
	return ssi_queryPosts($query_where, $query_where_params, $num_recent, 'm.id_msg DESC', $output_method, $limit_body);
}

/**
 * Fetch a post with a particular ID.
 * By default will only show if you have permission
 *  to the see the board in question - this can be overriden.
 *
 * @todo this may use getRecentPosts with some modification
 *
 * @param int[] $post_ids
 * @param bool $override_permissions
 * @param string $output_method = 'echo'
 */
function ssi_fetchPosts($post_ids = array(), $override_permissions = false, $output_method = 'echo')
{
	global $modSettings;

	if (empty($post_ids))
		return;

	// Allow the user to request more than one - why not?
	$post_ids = is_array($post_ids) ? $post_ids : array($post_ids);

	// Restrict the posts required...
	$query_where = '
		m.id_msg IN ({array_int:message_list})' . ($override_permissions ? '' : '
			AND {query_wanna_see_board}') . ($modSettings['postmod_active'] ? '
			AND m.approved = {int:is_approved}' : '');
	$query_where_params = array(
		'message_list' => $post_ids,
		'is_approved' => 1,
	);

	// Then make the query and dump the data.
	return ssi_queryPosts($query_where, $query_where_params, '', 'm.id_msg DESC', $output_method, false, $override_permissions);
}

/**
 * This removes code duplication in other queries
 *  - don't call it direct unless you really know what you're up to.
 *
 * @todo if ssi_recentPosts and ssi_fetchPosts will use Recent.subs.php this can be removed
 *
 * @param string $query_where
 * @param mixed[] $query_where_params
 * @param int $query_limit
 * @param string $query_order
 * @param string $output_method = 'echo'
 * @param bool $limit_body
 * @param bool $override_permissions
 */
function ssi_queryPosts($query_where = '', $query_where_params = array(), $query_limit = 10, $query_order = 'm.id_msg DESC', $output_method = 'echo', $limit_body = false, $override_permissions = false)
{
	global $scripturl, $txt, $user_info, $modSettings;

	$db = database();

	// Find all the posts. Newer ones will have higher IDs.
	$request = $db->query('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg, m.id_board, b.name AS board_name,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', ' . ($limit_body ? 'SUBSTRING(m.body, 1, 384) AS body' : 'm.body') . ', m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})' : '') . '
		WHERE 1=1 ' . ($override_permissions ? '' : '
			AND {query_wanna_see_board}') . ($modSettings['postmod_active'] ? '
			AND m.approved = {int:is_approved}' : '') . '
		' . (empty($query_where) ? '' : 'AND ' . $query_where) . '
		ORDER BY ' . $query_order . '
		' . ($query_limit == '' ? '' : 'LIMIT {int:query_limit}'),
		array_merge($query_where_params, array(
			'current_member' => $user_info['id'],
			'is_approved' => 1,
			'query_limit' => $query_limit,
		))
	);
	$posts = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// Censor it!
		censorText($row['subject']);
		censorText($row['body']);

		$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));

		// Build the array.
		$posts[] = array(
			'id' => $row['id_msg'],
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => Util::shorten_text($row['subject'], !empty($modSettings['ssi_subject_length']) ? $modSettings['ssi_subject_length'] : 24),
			'preview' => Util::shorten_text($preview, !empty($modSettings['ssi_preview_length']) ? $modSettings['ssi_preview_length'] : 128),
			'body' => $row['body'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'new' => !empty($row['is_read']),
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
		);
	}
	$db->free_result($request);

	// Just return it.
	if ($output_method != 'echo' || empty($posts))
		return $posts;

	echo '
		<table class="ssi_table">';
	foreach ($posts as $post)
		echo '
			<tr>
				<td class="righttext">
					[', $post['board']['link'], ']
				</td>
				<td class="top">
					<a href="', $post['href'], '">', $post['subject'], '</a>
					', $txt['by'], ' ', $post['poster']['link'], '
					', $post['is_new'] ? '<a href="' . $scripturl . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . ';topicseen#new" rel="nofollow"><span class="new_posts">' . $txt['new'] . '</span></a>' : '', '
				</td>
				<td class="righttext">
					', $post['time'], '
				</td>
			</tr>';
	echo '
		</table>';
}

/**
 * Recent topic list:
 *  [board] Subject by Poster Date
 *
 * @param int $num_recent
 * @param int[]|null $exclude_boards
 * @param bool|null $include_boards
 * @param string $output_method = 'echo'
 */
function ssi_recentTopics($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo')
{
	global $settings, $scripturl, $txt, $user_info, $modSettings;

	$db = database();

	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

	// Only some boards?.
	if (is_array($include_boards) || (int) $include_boards === $include_boards)
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	elseif ($include_boards != null)
	{
		$output_method = $include_boards;
		$include_boards = array();
	}

	require_once(SUBSDIR . '/MessageIndex.subs.php');
	$icon_sources = MessageTopicIcons();

	// Find all the posts in distinct topics. Newer ones will have higher IDs.
	$request = $db->query('', '
		SELECT
			t.id_topic, b.id_board, b.name AS board_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_last_msg >= {int:min_message_id}' . (empty($exclude_boards) ? '' : '
			AND b.id_board NOT IN ({array_int:exclude_boards})') . '' . (empty($include_boards) ? '' : '
			AND b.id_board IN ({array_int:include_boards})') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND ml.approved = {int:is_approved}' : '') . '
		ORDER BY t.id_last_msg DESC
		LIMIT {int:num_recent}',
		array(
			'include_boards' => empty($include_boards) ? '' : $include_boards,
			'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
			'min_message_id' => $modSettings['maxMsgID'] - 35 * min($num_recent, 5),
			'is_approved' => 1,
			'num_recent' => $num_recent,
		)
	);
	$topics = array();
	while ($row = $db->fetch_assoc($request))
		$topics[$row['id_topic']] = $row;
	$db->free_result($request);

	// Did we find anything? If not, bail.
	if (empty($topics))
		return array();

	// Find all the posts in distinct topics. Newer ones will have higher IDs.
	$request = $db->query('substring', '
		SELECT
			ml.poster_time, mf.subject, ml.id_member, ml.id_msg, t.id_topic, t.num_replies, t.num_views, mg.online_color,
			IFNULL(mem.real_name, ml.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= ml.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', SUBSTRING(ml.body, 1, 384) AS body, ml.smileys_enabled, ml.icon
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)' . (!$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' : '') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE t.id_topic IN ({array_int:topic_list})
		ORDER BY t.id_last_msg DESC',
		array(
			'current_member' => $user_info['id'],
			'topic_list' => array_keys($topics),
		)
	);
	$posts = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		if (Util::strlen($row['body']) > 128)
			$row['body'] = Util::substr($row['body'], 0, 128) . '...';

		// Censor the subject.
		censorText($row['subject']);
		censorText($row['body']);

		if (!empty($modSettings['messageIconChecks_enable']) && !isset($icon_sources[$row['icon']]))
			$icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.png') ? 'images_url' : 'default_images_url';

		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $topics[$row['id_topic']]['id_board'],
				'name' => $topics[$row['id_topic']]['board_name'],
				'href' => $scripturl . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0">' . $topics[$row['id_topic']]['board_name'] . '</a>',
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'replies' => $row['num_replies'],
			'views' => $row['num_views'],
			'short_subject' => Util::shorten_text($row['subject'], 25),
			'preview' => $row['body'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new" rel="nofollow">' . $row['subject'] . '</a>',
			// Retained for compatibility - is technically incorrect!
			'new' => !empty($row['is_read']),
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
			'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.png" style="vertical-align: middle;" alt="' . $row['icon'] . '" />',
		);
	}
	$db->free_result($request);

	// Just return it.
	if ($output_method != 'echo' || empty($posts))
		return $posts;

	echo '
		<table class="ssi_table">';
	foreach ($posts as $post)
		echo '
			<tr>
				<td class="righttext top">
					[', $post['board']['link'], ']
				</td>
				<td class="top">
					<a href="', $post['href'], '">', $post['subject'], '</a>
					', $txt['by'], ' ', $post['poster']['link'], '
					', !$post['is_new'] ? '' : '<a href="' . $scripturl . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . ';topicseen#new" rel="nofollow"><span class="new_posts">' . $txt['new'] . '</span></a>', '
				</td>
				<td class="righttext">
					', $post['time'], '
				</td>
			</tr>';
	echo '
		</table>';
}

/**
 * Show the top poster's name and profile link.
 *
 * @param int $topNumber
 * @param string $output_method = 'echo'
 */
function ssi_topPoster($topNumber = 1, $output_method = 'echo')
{
	require_once(SUBSDIR . '/Stats.subs.php');
	$top_posters = topPosters($topNumber);

	// Just return all the top posters.
	if ($output_method != 'echo')
		return $top_posters;

	// Make a quick array to list the links in.
	$temp_array = array();
	foreach ($top_posters as $member)
		$temp_array[] = $member['link'];

	echo implode(', ', $temp_array);
}

/**
 * Show boards by activity.
 *
 * @param int $num_top
 * @param string $output_method = 'echo'
 */
function ssi_topBoards($num_top = 10, $output_method = 'echo')
{
	global $txt;

	require_once(SUBSDIR . '/Stats.subs.php');

	// Find boards with lots of posts.
	$boards = topBoards($num_top, true);

	foreach ($boards as $id => $board)
		$boards[$id]['new'] = empty($board['is_read']);

	// If we shouldn't output or have nothing to output, just jump out.
	if ($output_method != 'echo' || empty($boards))
		return $boards;

	echo '
		<table class="ssi_table">
			<tr>
				<th class="lefttext">', $txt['board'], '</th>
				<th class="righttext">', $txt['board_topics'], '</th>
				<th class="righttext">', $txt['posts'], '</th>
			</tr>';
	foreach ($boards as $board)
		echo '
			<tr>
				<td>', $board['new'] ? ' <a href="' . $board['href'] . '"><span class="new_posts">' . $txt['new'] . '</span></a> ' : '', $board['link'], '</td>
				<td class="righttext">', $board['num_topics'], '</td>
				<td class="righttext">', $board['num_posts'], '</td>
			</tr>';
	echo '
		</table>';
}

/**
 * Shows the top topics.
 *
 * @param string $type
 * @param 10 $num_topics
 * @param string $output_method = 'echo'
 */
function ssi_topTopics($type = 'replies', $num_topics = 10, $output_method = 'echo')
{
	global $txt, $scripturl;

	require_once(SUBSDIR . '/Stats.subs.php');

	if (function_exists('topTopic' . ucfirst($type)))
		$function = 'topTopic' . ucfirst($type);
	else
		$function = 'topTopicReplies';

	$topics = $function($num_topics);

	foreach ($topics as $topic_id => $row)
	{
		censorText($row['subject']);

		$topics[$topic_id]['href'] = $scripturl . '?topic=' . $row['id'] . '.0';
		$topics[$topic_id]['link'] = '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0">' . $row['subject'] . '</a>';
	}

	if ($output_method != 'echo' || empty($topics))
		return $topics;

	echo '
		<table class="top_topic ssi_table">
			<tr>
				<th class="link"></th>
				<th class="views">', $txt['views'], '</th>
				<th class="num_replies">', $txt['replies'], '</th>
			</tr>';
	foreach ($topics as $topic)
		echo '
			<tr>
				<td class="link">
					', $topic['link'], '
				</td>
				<td class="views">', $topic['num_views'], '</td>
				<td class="num_replies">', $topic['num_replies'], '</td>
			</tr>';
	echo '
		</table>';
}

/**
 * Shows the top topics, by replies.
 *
 * @param int $num_topics = 10
 * @param string $output_method = 'echo'
 */
function ssi_topTopicsReplies($num_topics = 10, $output_method = 'echo')
{
	return ssi_topTopics('replies', $num_topics, $output_method);
}

/**
 * Shows the top topics, by views.
 *
 * @param int $num_topics = 10
 * @param string $output_method = 'echo'
 */
function ssi_topTopicsViews($num_topics = 10, $output_method = 'echo')
{
	return ssi_topTopics('views', $num_topics, $output_method);
}

/**
 * Show a link to the latest member:
 *  Please welcome, Someone, out latest member.
 *
 * @param string $output_method = 'echo'
 */
function ssi_latestMember($output_method = 'echo')
{
	global $txt, $context;

	if ($output_method == 'echo')
		echo '
	', sprintf($txt['welcome_newest_member'], $context['common_stats']['latest_member']['link']), '<br />';
	else
		return $context['common_stats']['latest_member'];
}

/**
 * Fetch a random member - if type set to 'day' will only change once a day!
 *
 * @param string $random_type = ''
 * @param string $output_method = 'echo'
 */
function ssi_randomMember($random_type = '', $output_method = 'echo')
{
	global $modSettings;

	// If we're looking for something to stay the same each day then seed the generator.
	if ($random_type == 'day')
	{
		// Set the seed to change only once per day.
		mt_srand(floor(time() / 86400));
	}

	// Get the lowest ID we're interested in.
	$member_id = mt_rand(1, $modSettings['latestMember']);

	$result = ssi_queryMembers('member_greater_equal', $member_id, 1, 'id_member ASC', $output_method);

	// If we got nothing do the reverse - in case of unactivated members.
	if (empty($result))
		$result = ssi_queryMembers('member_lesser_equal', $member_id, 1, 'id_member DESC', $output_method);

	// Just to be sure put the random generator back to something... random.
	if ($random_type != '')
		mt_srand(time());

	return $result;
}

/**
 * Fetch a specific member.
 *
 * @param int[] $member_ids = array()
 * @param string $output_method = 'echo'
 */
function ssi_fetchMember($member_ids = array(), $output_method = 'echo')
{
	if (empty($member_ids))
		return;

	// Can have more than one member if you really want...
	$member_ids = is_array($member_ids) ? $member_ids : array($member_ids);

	// Then make the query and dump the data.
	return ssi_queryMembers('members', $member_ids, '', 'id_member', $output_method);
}

/**
 * Fetch a specific member.
 *
 * @param null $group_id
 * @param string $output_method = 'echo'
 */
function ssi_fetchGroupMembers($group_id = null, $output_method = 'echo')
{
	if ($group_id === null)
		return;

	return ssi_queryMembers('group_list', is_array($group_id) ? $group_id : array($group_id), '', 'real_name', $output_method);
}

/**
 * Fetch some member data!
 *
 * @param string|null $query_where
 * @param string|string[] $query_where_params
 * @param string $query_limit
 * @param string $query_order
 * @param string $output_method
 */
function ssi_queryMembers($query_where = null, $query_where_params = array(), $query_limit = '', $query_order = 'id_member DESC', $output_method = 'echo')
{
	global $memberContext;

	if ($query_where === null)
		return;

	require_once(SUBSDIR . '/Members.subs.php');
	$members_data = retrieveMemberData(array(
		$query_where => $query_where_params,
		'limit' => !empty($query_limit) ? (int) $query_limit : 10,
		'order_by' => $query_order,
		'activated_status' => 1,
	));

	$members = array();
	foreach ($members_data['member_info'] as $row)
		$members[] = $row['id'];

	if (empty($members))
		return array();

	// Load the members.
	loadMemberData($members);

	// Draw the table!
	if ($output_method == 'echo')
		echo '
		<table class="ssi_table">';

	$query_members = array();
	foreach ($members as $member)
	{
		// Load their context data.
		if (!loadMemberContext($member))
			continue;

		// Store this member's information.
		$query_members[$member] = $memberContext[$member];

		// Only do something if we're echo'ing.
		if ($output_method == 'echo')
			echo '
			<tr>
				<td class="centertext">
					', $query_members[$member]['link'], '
					<br />', $query_members[$member]['blurb'], '
					<br />', $query_members[$member]['avatar']['image'], '
				</td>
			</tr>';
	}

	// End the table if appropriate.
	if ($output_method == 'echo')
		echo '
		</table>';

	// Send back the data.
	return $query_members;
}

/**
 * Show some basic stats:  Total This: XXXX, etc.
 *
 * @param string $output_method
 */
function ssi_boardStats($output_method = 'echo')
{
	global $txt, $scripturl, $modSettings;

	if (!allowedTo('view_stats'))
		return;

	require_once(SUBSDIR . '/Boards.subs.php');
	require_once(SUBSDIR . '/Stats.subs.php');

	$totals = array(
		'members' => $modSettings['totalMembers'],
		'posts' => $modSettings['totalMessages'],
		'topics' => $modSettings['totalTopics'],
		'boards' => countBoards(),
		'categories' => numCategories(),
	);

	if ($output_method != 'echo')
		return $totals;

	echo '
		', $txt['total_members'], ': <a href="', $scripturl . '?action=memberlist">', comma_format($totals['members']), '</a><br />
		', $txt['total_posts'], ': ', comma_format($totals['posts']), '<br />
		', $txt['total_topics'], ': ', comma_format($totals['topics']), ' <br />
		', $txt['total_cats'], ': ', comma_format($totals['categories']), '<br />
		', $txt['total_boards'], ': ', comma_format($totals['boards']);
}

/**
 * Shows a list of online users:
 *  YY Guests, ZZ Users and then a list...
 *
 * @param string $output_method
 */
function ssi_whosOnline($output_method = 'echo')
{
	global $user_info, $txt, $settings;

	require_once(SUBSDIR . '/MembersOnline.subs.php');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
	);
	$return = getMembersOnlineStats($membersOnlineOptions);

	// Add some redundancy for backwards compatibility reasons.
	if ($output_method != 'echo')
		return $return + array(
			'users' => $return['users_online'],
			'guests' => $return['num_guests'],
			'hidden' => $return['num_users_hidden'],
			'buddies' => $return['num_buddies'],
			'num_users' => $return['num_users_online'],
			'total_users' => $return['num_users_online'] + $return['num_guests'] + $return['num_spiders'],
		);

	echo '
		', comma_format($return['num_guests']), ' ', $return['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($return['num_users_online']), ' ', $return['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	$bracketList = array();
	if (!empty($user_info['buddies']))
		$bracketList[] = comma_format($return['num_buddies']) . ' ' . ($return['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($return['num_spiders']))
		$bracketList[] = comma_format($return['num_spiders']) . ' ' . ($return['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($return['num_users_hidden']))
		$bracketList[] = comma_format($return['num_users_hidden']) . ' ' . $txt['hidden'];

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo '<br />
			', implode(', ', $return['list_users_online']);

	// Showing membergroups?
	if (!empty($settings['show_group_key']) && !empty($return['membergroups']))
		echo '<br />
			[' . implode(']&nbsp;&nbsp;[', $return['membergroups']) . ']';
}

/**
 * Just like whosOnline except it also logs the online presence.
 *
 * @param string $output_method
 */
function ssi_logOnline($output_method = 'echo')
{
	writeLog();

	if ($output_method != 'echo')
		return ssi_whosOnline($output_method);
	else
		ssi_whosOnline($output_method);
}

/**
 * Shows a login box.
 *
 * @param string $redirect_to = ''
 * @param string $output_method = 'echo'
 */
function ssi_login($redirect_to = '', $output_method = 'echo')
{
	global $scripturl, $txt, $user_info, $modSettings, $context, $settings;

	if ($redirect_to != '')
		$_SESSION['login_url'] = $redirect_to;

	if ($output_method != 'echo' || !$user_info['is_guest'])
		return $user_info['is_guest'];

	$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($_POST['user'], ENT_COMPAT, 'UTF-8')) : '';

	echo '
		<script src="', $settings['default_theme_url'], '/scripts/sha256.js"></script>

		<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
		<div class="login">
			<div class="roundframe">';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		echo '
			<p class="errorbox">', implode('<br />', $context['login_errors']), '</p><br />';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
				<p class="description">', $context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20" value="', $context['default_username'], '" class="input_text" autofocus="autofocus" placeholder="', $txt['username'], '" /></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" value="" size="20" class="input_password" placeholder="', $txt['password'], '" /></dd>
				</dl>';

	if (!empty($modSettings['enableOpenID']))
		echo '<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd><input type="text" name="openid_identifier" class="input_text openid_login" size="17" />&nbsp;<a href="', $scripturl, '?action=quickhelp;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" class="centericon" /></a></dd>
				</dl>';

	echo '
				<p><input type="submit" value="', $txt['login'], '" class="button_submit" /></p>
				<p class="smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
				<input type="hidden" name="hash_passwrd" value="" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
			</div>
		</div>
		</form>';

	// Focus on the correct input - username or password.
	echo '
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.', isset($context['default_username']) && $context['default_username'] != '' ? 'passwrd' : 'user', '.focus();
		// ]]></script>';

}

/**
 * Show the most-voted-in poll.
 *
 * @param string $output_method = 'echo'
 */
function ssi_topPoll($output_method = 'echo')
{
	// Just use recentPoll, no need to duplicate code...
	return ssi_recentPoll(true, $output_method);
}

/**
 * Show the most recently posted poll.
 *
 * @param bool $topPollInstead = false
 * @param string $output_method = string
 */
function ssi_recentPoll($topPollInstead = false, $output_method = 'echo')
{
	global $txt, $settings, $boardurl, $user_info, $context, $modSettings;

	$boardsAllowed = array_intersect(boardsAllowedTo('poll_view'), boardsAllowedTo('poll_vote'));

	if (empty($boardsAllowed))
		return array();

	$db = database();

	$request = $db->query('', '
		SELECT p.id_poll, p.question, t.id_topic, p.max_votes, p.guest_vote, p.hide_results, p.expire_time
		FROM {db_prefix}polls AS p
			INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' . ($topPollInstead ? '
			INNER JOIN {db_prefix}poll_choices AS pc ON (pc.id_poll = p.id_poll)' : '') . '
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member > {int:no_member} AND lp.id_member = {int:current_member})
		WHERE p.voting_locked = {int:voting_opened}
			AND (p.expire_time = {int:no_expiration} OR {int:current_time} < p.expire_time)
			AND ' . ($user_info['is_guest'] ? 'p.guest_vote = {int:guest_vote_allowed}' : 'lp.id_choice IS NULL') . '
			AND {query_wanna_see_board}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_list})' : '') . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_enable}' : '') . '
		ORDER BY ' . ($topPollInstead ? 'pc.votes' : 'p.id_poll') . ' DESC
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'boards_allowed_list' => $boardsAllowed,
			'is_approved' => 1,
			'guest_vote_allowed' => 1,
			'no_member' => 0,
			'voting_opened' => 0,
			'no_expiration' => 0,
			'current_time' => time(),
			'recycle_enable' => $modSettings['recycle_board'],
		)
	);
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	// This user has voted on all the polls.
	if ($row == false)
		return array();

	// If this is a guest who's voted we'll through ourselves to show poll to show the results.
	if ($user_info['is_guest'] && (!$row['guest_vote'] || (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote'])))))
		return ssi_showPoll($row['id_topic'], $output_method);

	$request = $db->query('', '
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = $db->fetch_row($request);
	$db->free_result($request);

	$request = $db->query('', '
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$options = array();
	while ($rowChoice = $db->fetch_assoc($request))
	{
		censorText($rowChoice['label']);

		$options[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
	}
	$db->free_result($request);

	// Can they view it?
	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || $is_expired;

	$return = array(
		'id' => $row['id_poll'],
		'image' => 'poll',
		'question' => $row['question'],
		'total_votes' => $total,
		'is_locked' => false,
		'topic' => $row['id_topic'],
		'allow_view_results' => $allow_view_results,
		'options' => array()
	);

	// Calculate the percentages and bar lengths...
	$divisor = $return['total_votes'] == 0 ? 1 : $return['total_votes'];
	foreach ($options as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$barWide = $bar == 0 ? 1 : floor(($bar * 5) / 3);
		$return['options'][$i] = array(
			'id' => 'options-' . ($topPollInstead ? 'top-' : 'recent-') . $i,
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<span style="white-space: nowrap;"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.png" alt="" /><img src="' . $settings['images_url'] . '/poll_middle.png" style="width:' . $barWide . 'px; height:12px;" alt="-" /><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.png" alt="" /></span>',
			'option' => parse_bbc($option[0]),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . ($topPollInstead ? 'top-' : 'recent-') . $i . '" value="' . $i . '" class="input_' . ($row['max_votes'] > 1 ? 'check' : 'radio') . '" />'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($options), $row['max_votes'])) : '';

	if ($output_method != 'echo')
		return $return;

	if ($allow_view_results)
	{
		echo '
		<form class="ssi_poll" action="', $boardurl, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="UTF-8">
			<strong>', $return['question'], '</strong><br />
			', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br />' : '';

		foreach ($return['options'] as $option)
			echo '
			<label for="', $option['id'], '">', $option['vote_button'], ' ', $option['option'], '</label><br />';

		echo '
			<input type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
			<input type="hidden" name="poll" value="', $return['id'], '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
	}
	else
		echo $txt['poll_cannot_see'];
}

/**
 * Show a poll.
 * It is possible to use this function in combination with the template
 * template_display_poll_above from Display.template.php, the only part missing
 * is the definition of the poll moderation button array (see Display.controller.php
 * for details).
 *
 * @param int|null $topicID = null
 * @param string $output_method = 'echo'
 */
function ssi_showPoll($topicID = null, $output_method = 'echo')
{
	global $txt, $user_info, $context, $scripturl;
	global $board;
	static $last_board = null;

	require_once(SUBSDIR . '/Poll.subs.php');
	require_once(SUBSDIR . '/Topic.subs.php');

	if ($topicID === null && isset($_REQUEST['ssi_topic']))
		$topicID = (int) $_REQUEST['ssi_topic'];
	else
		$topicID = (int) $topicID;

	if (empty($topicID))
		return array();

	// Get the topic starter information.
	$topicinfo = getTopicInfo($topicID, 'starter');

	$boards_can_poll = boardsAllowedTo('poll_view');

	// If:
	//  - is not allowed to see poll in any board,
	//  - or:
	//     - is not allowed in the specific board, and
	//     - is not an admin
	// fail
	if (empty($boards_can_poll) || (!in_array($topicinfo['id_board'], $boards_can_poll) && !in_array(0, $boards_can_poll)))
		return array();

	$context['user']['started'] = $user_info['id'] == $topicinfo['id_member'] && !$user_info['is_guest'];

	$poll_id = associatedPoll($topicID);
	loadPollContext($poll_id);

	if (empty($context['poll']))
		return array();

	// For "compatibility" sake
	// @deprecated since 1.0
	$context['poll']['allow_vote'] = $context['allow_vote'];
	$context['poll']['allow_view_results'] = $context['allow_poll_view'];
	$context['poll']['topic'] = $topicID;

	if ($output_method != 'echo')
		return $context['poll'];

	echo '
		<div class="content" id="poll_options">
			<h4 id="pollquestion">
				', $context['poll']['question'], '
			</h4>';

	if ($context['poll']['allow_vote'])
	{
		echo '
			<form action="', $scripturl, '?action=poll;sa=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="UTF-8">';

		// Show a warning if they are allowed more than one option.
		if ($context['poll']['allowed_warning'])
			echo '
				<p>', $context['poll']['allowed_warning'], '</p>';

		echo '
				<ul class="options">';

		// Show each option with its button - a radio likely.
		foreach ($context['poll']['options'] as $option)
			echo '
					<li>', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';

		echo '
				</ul>
				<div class="submitbutton">
					<input type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</form>';
		// Is the clock ticking?
		if (!empty($context['poll']['expire_time']))
			echo '
			<p><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</p>';

	}
	elseif ($context['poll']['allow_view_results'])
	{
		echo '
			<ul class="options">';

		// Show each option with its corresponding percentage bar.
		foreach ($context['poll']['options'] as $option)
		{
			echo '
				<li', $option['voted_this'] ? ' class="voted"' : '', '>', $option['option'], '
					<div class="results">';

			if ($context['allow_poll_view'])
				echo '
						<div class="statsbar"> ', $option['bar_ndt'], '</div>
						<span class="percentage">', $option['votes'], ' (', $option['percent'], '%)</span>';

			echo '
					</div>
				</li>';
		}

		echo '
			</ul>';

		if ($context['allow_poll_view'])
			echo '
			<p><strong>', $txt['poll_total_voters'], ':</strong> ', $context['poll']['total_votes'], '</p>';
		// Is the clock ticking?
		if (!empty($context['poll']['expire_time']))
			echo '
			<p><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</p>';
	}
	// Cannot see it I'm afraid!
	else
		echo $txt['poll_cannot_see'];

	echo '
			</div>';
}

/**
 * Takes care of voting - don't worry, this is done automatically.
 */
function ssi_pollVote()
{
	global $context, $sc, $topic, $board;

	$pollID = isset($_POST['poll']) ? (int) $_POST['poll'] : 0;

	if (empty($pollID) || !isset($_POST[$context['session_var']]) || $_POST[$context['session_var']] != $sc || empty($_POST['options']))
	{
		echo '<!DOCTYPE html>
<html>
<head>
	<script><!-- // --><![CDATA[
		history.go(-1);
	// ]]></script>
</head>
<body>&laquo;</body>
</html>';
		return;
	}

	require_once(CONTROLLERDIR . '/Poll.controller.php');
	require_once(SUBSDIR . '/Poll.subs.php');
	// We have to fake we are in a topic so that we can use the proper controller
	list ($topic, $board) = topicFromPoll($pollID);
	loadBoard();

	$poll_action = new Poll_Controller();

	// The controller takes already care of redirecting properly or fail
	$poll_action->action_vote();
}

/**
 * Show a search box.
 *
 * @param string $output_method = 'echo'
 */
function ssi_quickSearch($output_method = 'echo')
{
	global $scripturl, $txt;

	if (!allowedTo('search_posts'))
		return;

	if ($output_method != 'echo')
		return $scripturl . '?action=search';

	echo '
		<form action="', $scripturl, '?action=search;sa=results" method="post" accept-charset="UTF-8">
			<input type="hidden" name="advanced" value="0" /><input type="text" name="search" size="30" class="input_text" /> <input type="submit" value="', $txt['search'], '" class="button_submit" />
		</form>';
}

/**
 * Show what would be the forum news.
 *
 * @param string $output_method = 'echo'
 */
function ssi_news($output_method = 'echo')
{
	global $context;

	if ($output_method != 'echo')
		return $context['random_news_line'];

	echo $context['random_news_line'];
}

/**
 * Show today's birthdays.
 *
 * @param string $output_method = 'echo'
 */
function ssi_todaysBirthdays($output_method = 'echo')
{
	global $scripturl, $modSettings, $user_info;

	if (empty($modSettings['cal_enabled']) || !allowedTo('calendar_view') || !allowedTo('profile_view_any'))
		return;

	$eventOptions = array(
		'include_birthdays' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'subs/Calendar.subs.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return['calendar_birthdays'];

	foreach ($return['calendar_birthdays'] as $member)
		echo '
			<a href="', $scripturl, '?action=profile;u=', $member['id'], '"><span class="fix_rtl_names">' . $member['name'] . '</span>' . (isset($member['age']) ? ' (' . $member['age'] . ')' : '') . '</a>' . (!$member['is_last'] ? ', ' : '');
}

/**
 * Show today's holidays.
 *
 * @param string $output_method = 'echo'
 */
function ssi_todaysHolidays($output_method = 'echo')
{
	global $modSettings, $user_info;

	if (empty($modSettings['cal_enabled']) || !allowedTo('calendar_view'))
		return;

	$eventOptions = array(
		'include_holidays' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'subs/Calendar.subs.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return['calendar_holidays'];

	echo '
		', implode(', ', $return['calendar_holidays']);
}

/**
 * Show today's events.
 *
 * @param string $output_method = 'echo'
 */
function ssi_todaysEvents($output_method = 'echo')
{
	global $modSettings, $user_info;

	if (empty($modSettings['cal_enabled']) || !allowedTo('calendar_view'))
		return;

	$eventOptions = array(
		'include_events' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'subs/Calendar.subs.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return['calendar_events'];

	foreach ($return['calendar_events'] as $event)
	{
		if ($event['can_edit'])
			echo '
	<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
		echo '
	' . $event['link'] . (!$event['is_last'] ? ', ' : '');
	}
}

/**
 * Show all calendar entires for today. (birthdays, holidays, and events.)
 *
 * @param string $output_method = 'echo'
 */
function ssi_todaysCalendar($output_method = 'echo')
{
	global $modSettings, $txt, $scripturl, $user_info;

	if (empty($modSettings['cal_enabled']) || !allowedTo('calendar_view'))
		return;

	$eventOptions = array(
		'include_birthdays' => allowedTo('profile_view_any'),
		'include_holidays' => true,
		'include_events' => true,
		'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
	);
	$return = cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'subs/Calendar.subs.php', 'cache_getRecentEvents', array($eventOptions));

	if ($output_method != 'echo')
		return $return;

	if (!empty($return['calendar_holidays']))
		echo '
			<span class="holiday">' . $txt['calendar_prompt'] . ' ' . implode(', ', $return['calendar_holidays']) . '<br /></span>';

	if (!empty($return['calendar_birthdays']))
	{
		echo '
			<span class="birthday">' . $txt['birthdays_upcoming'] . '</span> ';
		foreach ($return['calendar_birthdays'] as $member)
			echo '
			<a href="', $scripturl, '?action=profile;u=', $member['id'], '"><span class="fix_rtl_names">', $member['name'], '</span>', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', !$member['is_last'] ? ', ' : '';
		echo '
			<br />';
	}

	if (!empty($return['calendar_events']))
	{
		echo '
			<span class="event">' . $txt['events_upcoming'] . '</span> ';
		foreach ($return['calendar_events'] as $event)
		{
			if ($event['can_edit'])
				echo '
			<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
			echo '
			' . $event['link'] . (!$event['is_last'] ? ', ' : '');
		}
	}
}

/**
 * Show the latest news, with a template... by board.
 *
 * @param int|null $board
 * @param int|null $limit
 * @param int|null $start
 * @param int|null $length
 * @param string $preview
 * @param string $output_method = 'echo'
 */
function ssi_boardNews($board = null, $limit = null, $start = null, $length = null, $preview = 'first', $output_method = 'echo')
{
	global $scripturl, $txt, $settings, $modSettings;

	loadLanguage('Stats');

	$db = database();

	// Must be integers....
	if ($limit === null)
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
	else
		$limit = (int) $limit;

	if ($start === null)
		$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
	else
		$start = (int) $start;

	if ($board !== null)
		$board = (int) $board;
	elseif (isset($_GET['board']))
		$board = (int) $_GET['board'];

	if ($length === null)
		$length = isset($_GET['length']) ? (int) $_GET['length'] : 500;
	else
		$length = (int) $length;

	$limit = max(0, $limit);
	$start = max(0, $start);

	// Make sure guests can see this board.
	$request = $db->query('', '
		SELECT id_board
		FROM {db_prefix}boards
		WHERE ' . ($board === null ? '' : 'id_board = {int:current_board}
			AND ') . 'FIND_IN_SET(-1, member_groups) != 0
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);
	if ($db->num_rows($request) == 0)
	{
		if ($output_method == 'echo')
			die($txt['ssi_no_guests']);
		else
			return array();
	}
	list ($board) = $db->fetch_row($request);
	$db->free_result($request);

	// Load the message icons - the usual suspects.
	require_once(SUBSDIR . '/MessageIndex.subs.php');
	$icon_sources = MessageTopicIcons();

	// Find the posts.
	$indexOptions = array(
		'only_approved' => true,
		'include_sticky' => false,
		'ascending' => false,
		'include_avatars' => false,
		'previews' => $length
	);
	$request = messageIndexTopics($board, 0, $start, $limit, 'first_post', 't.id_topic', $indexOptions);

	if (empty($request))
		return;

	$return = array();
	foreach ($request as $row)
	{
		if (!isset($row[$preview . '_body']))
			$preview = 'first';

		$row['body'] = $row[$preview . '_body'];
		$row['subject'] = $row[$preview . '_subject'];
		$row['id_msg'] = $row['id_' . $preview . '_msg'];
		$row['icon'] = $row[$preview . '_icon'];
		$row['id_member'] = $row[$preview . '_id_member'];
		$row['smileys_enabled'] = $row[$preview . '_smileys'];
		$row['poster_time'] = $row[$preview . '_poster_time'];
		$row['poster_name'] = $row[$preview . '_display_name'];
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// Check that this message icon is there...
		if (!empty($modSettings['messageIconChecks_enable']) && !isset($icon_sources[$row['icon']]))
			$icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.png') ? 'images_url' : 'default_images_url';

		censorText($row['subject']);
		censorText($row['body']);

		$return[] = array(
			'id' => $row['id_topic'],
			'message_id' => $row['id_msg'],
			'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.png" alt="' . $row['icon'] . '" />',
			'subject' => $row['subject'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => $row['body'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['num_replies'] . ' ' . ($row['num_replies'] == 1 ? $txt['ssi_comment'] : $txt['ssi_comments']) . '</a>',
			'replies' => $row['num_replies'],
			'comment_href' => !empty($row['locked']) ? '' : $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'],
			'comment_link' => !empty($row['locked']) ? '' : '<a href="' . $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'new_comment' => !empty($row['locked']) ? '' : '<a href="' . $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'link' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name']
			),
			'locked' => !empty($row['locked']),
			'is_last' => false
		);
	}

	$return[count($return) - 1]['is_last'] = true;

	if ($output_method != 'echo')
		return $return;

	foreach ($return as $news)
	{
		echo '
			<div class="news_item">
				<h3 class="news_header">
					', $news['icon'], '
					<a href="', $news['href'], '">', $news['subject'], '</a>
				</h3>
				<div class="news_timestamp">', $news['time'], ' ', $txt['by'], ' ', $news['poster']['link'], '</div>
				<div class="news_body" style="padding: 2ex 0;">', $news['body'], '</div>
				', $news['link'], $news['locked'] ? '' : ' | ' . $news['comment_link'], '
			</div>';

		if (!$news['is_last'])
			echo '
			<hr />';
	}
}

/**
 * Show the most recent events.
 *
 * @param int $max_events
 * @param string $output_method = 'echo'
 */
function ssi_recentEvents($max_events = 7, $output_method = 'echo')
{
	global $modSettings, $txt;

	if (empty($modSettings['cal_enabled']) || !allowedTo('calendar_view'))
		return;

	require_once(SUBSDIR . '/Calendar.subs.php');

	// Find all events which are happening in the near future that the member can see.
	$date = strftime('%Y-%m-%d', forum_time(false));
	$events = getEventRange($date, $date, true, $max_events);

	$return = array();
	$duplicates = array();
	foreach ($events as $date => $day_events)
	{
		foreach ($day_events as $row)
		{
			// Check if we've already come by an event linked to this same topic with the same title... and don't display it if we have.
			if (!empty($duplicates[$row['title'] . $row['id_topic']]))
				continue;

			$return[$date][] = $row;

			// Let's not show this one again, huh?
			$duplicates[$row['title'] . $row['id_topic']] = true;
		}
	}

	foreach ($return as $mday => $array)
		$return[$mday][count($array) - 1]['is_last'] = true;

	if ($output_method != 'echo' || empty($return))
		return $return;

	// Well the output method is echo.
	echo '
			<span class="event">' . $txt['events'] . '</span> ';
	foreach ($return as $mday => $array)
		foreach ($array as $event)
		{
			if ($event['can_edit'])
				echo '
				<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';

			echo '
				' . $event['link'] . (!$event['is_last'] ? ', ' : '');
		}
}

/**
 * Check the passed id_member/password.
 *  If $is_username is true, treats $id as a username.
 *
 * @param int|null $id
 * @param string|null $password
 * @param bool $is_username
 */
function ssi_checkPassword($id = null, $password = null, $is_username = false)
{
	// If $id is null, this was most likely called from a query string and should do nothing.
	if ($id === null)
		return;

	require_once(SUBSDIR . '/Auth.subs.php');

	$member = loadExistingMember($id, !$is_username);

	return validateLoginPassword($password, $member['passwd'], $member['member_name']) && $member['is_activated'] == 1;
}

/**
 * We want to show the recent attachments outside of the forum.
 *
 * @param int $num_attachments = 10
 * @param string[] $attachment_ext = array()
 * @param string $output_method = 'echo'
 */
function ssi_recentAttachments($num_attachments = 10, $attachment_ext = array(), $output_method = 'echo')
{
	global $modSettings, $scripturl, $txt, $settings;

	// We want to make sure that we only get attachments for boards that we can see *if* any.
	$attachments_boards = boardsAllowedTo('view_attachments');

	// No boards?  Adios amigo.
	if (empty($attachments_boards))
		return array();

	$db = database();

	// Is it an array?
	if (!is_array($attachment_ext))
		$attachment_ext = array($attachment_ext);

	// Lets build the query.
	$request = $db->query('', '
		SELECT
			att.id_attach, att.id_msg, att.filename, IFNULL(att.size, 0) AS filesize, att.downloads, mem.id_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.id_topic, m.subject, t.id_board, m.poster_time,
			att.width, att.height' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ', IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
		FROM {db_prefix}attachments AS att
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = att.id_msg)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = att.id_thumb)') . '
		WHERE att.attachment_type = 0' . ($attachments_boards === array(0) ? '' : '
			AND m.id_board IN ({array_int:boards_can_see})') . (!empty($attachment_ext) ? '
			AND att.fileext IN ({array_string:attachment_ext})' : '') .
			(!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}
			AND att.approved = {int:is_approved}') . '
		ORDER BY att.id_attach DESC
		LIMIT {int:num_attachments}',
		array(
			'boards_can_see' => $attachments_boards,
			'attachment_ext' => $attachment_ext,
			'num_attachments' => $num_attachments,
			'is_approved' => 1,
		)
	);

	// We have something.
	$attachments = array();
	while ($row = $db->fetch_assoc($request))
	{
		$filename = preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($row['filename'], ENT_COMPAT, 'UTF-8'));

		// Is it an image?
		$attachments[$row['id_attach']] = array(
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
			),
			'file' => array(
				'filename' => $filename,
				'filesize' => round($row['filesize'] / 1024, 2) . $txt['kilobyte'],
				'downloads' => $row['downloads'],
				'href' => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'],
				'link' => '<img src="' . $settings['images_url'] . '/icons/clip.png" alt="" /> <a href="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . '">' . $filename . '</a>',
				'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($modSettings['attachmentShowImages']),
			),
			'topic' => array(
				'id' => $row['id_topic'],
				'subject' => $row['subject'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
			),
		);

		// Images.
		if ($attachments[$row['id_attach']]['file']['is_image'])
		{
			$id_thumb = empty($row['id_thumb']) ? $row['id_attach'] : $row['id_thumb'];
			$attachments[$row['id_attach']]['file']['image'] = array(
				'id' => $id_thumb,
				'width' => $row['width'],
				'height' => $row['height'],
				'img' => '<img src="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image" alt="' . $filename . '" />',
				'thumb' => '<img src="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '" />',
				'href' => $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image',
				'link' => '<a href="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image"><img src="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '" /></a>',
			);
		}
	}
	$db->free_result($request);

	// So you just want an array?  Here you can have it.
	if ($output_method == 'array' || empty($attachments))
		return $attachments;

	// Give them the default.
	echo '
		<table class="ssi_downloads" cellpadding="2">
			<tr>
				<th align="left">', $txt['file'], '</th>
				<th align="left">', $txt['posted_by'], '</th>
				<th align="left">', $txt['downloads'], '</th>
				<th align="left">', $txt['filesize'], '</th>
			</tr>';

	foreach ($attachments as $attach)
		echo '
			<tr>
				<td>', $attach['file']['link'], '</td>
				<td>', $attach['member']['link'], '</td>
				<td align="center">', $attach['file']['downloads'], '</td>
				<td>', $attach['file']['filesize'], '</td>
			</tr>';
	echo '
		</table>';
}
