<?php

/**
 * This file contains the files necessary to display news as an XML feed.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\Emoji;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\Mail\PreparseMail;
use ElkArte\MembersList;

/**
 * News Controller class
 */
class News extends AbstractController
{
	/** @var string Holds news specific version board query for news feeds */
	private $_query_this_board;

	/** @var int Holds the limit for the number of items to get */
	private $_limit;

	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = '')
	{
		if ($action === 'action_showfeed')
		{
			return false;
		}

		return parent::trackStats($action);
	}

	/**
	 * Dispatcher. Forwards to the action to execute.
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		// do... something, of your favorite.
		// $this->action_xmlnews();
	}

	/**
	 * Outputs XML data representing recent information or a profile.
	 *
	 * What it does:
	 *
	 * - Can be passed 4 subactions which decide what is output:
	 *     * 'recent' for recent posts,
	 *     * 'news' for news topics,
	 *     * 'members' for recently registered members,
	 *     * 'profile' for a member's profile.
	 * - To display a member's profile, a user id has to be given. (;u=1) e.g. ?action=.xml;sa=profile;u=1;type=atom
	 * - Outputs a feed based on the 'type' parameter: 'rss2' or 'atom'.
	 *     * Legacy values 'rss' and 'rdf' are redirected to 'rss2'.
	 * - Several sub action options are respected
	 *     * limit=x - display the "x" most recent posts
	 *     * board=y - display only the recent posts from board "y"
	 *     * boards=x,y,z - display only the recent posts from the specified boards
	 *     * c=x or c=x,y,z - display only the recent posts from boards in the specified category/categories
	 *     * action=.xml;sa=recent;board=2;limit=10
	 * - Accessed via ?action=.xml
	 * - Does not use any templates, sub templates, or template layers.
	 * - Use ;debug to view output for debugging feeds
	 *
	 * @uses Stats language file.
	 */
	public function action_showfeed(): void
	{
		global $board, $board_info, $context, $txt, $modSettings, $db_show_debug, $mbname;

		// If it's not enabled, die.
		if (empty($modSettings['xmlnews_enable']))
		{
			obExit(false);
		}

		// This is just here to make it easier for the developers :P
		$db_show_debug = false;

		require_once(SUBSDIR . '/News.subs.php');

		Txt::load('Stats');
		$txt['xml_rss_desc'] = replaceBasicActionUrl($txt['xml_rss_desc']);

		// Default to latest 10.  No more than what is defined in the ACP or 255
		$limit = empty($modSettings['xmlnews_limit']) ? 10 : min($modSettings['xmlnews_limit'], 255);
		$this->_limit = min($this->_req->getQuery('limit', 'intval', $limit), $limit);

		// Handle the cases where a board, boards, or category is asked for.
		$this->_query_this_board = '1=1';
		$context['optimize_msg'] = [
			'highest' => 'm.id_msg <= b.id_last_msg',
		];

		// Specifying specific categories only?
		if ($this->_req->hasQuery('c') && empty($board))
		{
			$c_param = $this->_req->getQuery('c', 'trim|strval', '');
			$categories = $c_param === '' ? [] : array_map('intval', explode(',', $c_param));

			if (count($categories) === 1)
			{
				require_once(SUBSDIR . '/Categories.subs.php');
				$feed_title = categoryName($categories[0]);
				$feed_title = ' - ' . strip_tags($feed_title);
			}

			require_once(SUBSDIR . '/Boards.subs.php');
			$boards_posts = boardsPosts([], $categories);
			$total_cat_posts = array_sum($boards_posts);
			$boards = array_keys($boards_posts);

			if (!empty($boards))
			{
				$this->_query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';
			}

			// Try to limit the number of messages we look through.
			if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
			{
				$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 400 - $this->_limit * 5);
			}
		}
		// Maybe they only want to see feeds form some certain boards?
		elseif ($this->_req->hasQuery('boards'))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$boards_param = $this->_req->getQuery('boards', 'trim|strval', '');
			$query_boards = $boards_param === '' ? [] : array_map('intval', explode(',', $boards_param));

			$boards_data = fetchBoardsInfo(['boards' => $query_boards], ['selects' => 'detailed']);

			// Either the board specified doesn't exist or you have no access.
			$num_boards = count($boards_data);
			if ($num_boards === 0)
			{
				throw new Exception('no_board');
			}

			$total_posts = 0;
			$boards = array_keys($boards_data);
			foreach ($boards_data as $row)
			{
				if ($num_boards === 1)
				{
					$feed_title = ' - ' . strip_tags($row['name']);
				}

				$total_posts += $row['num_posts'];
			}

			$this->_query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';

			// The more boards, the more we're going to look through...
			if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			{
				$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 500 - $this->_limit * 5);
			}
		}
		// Just a single board
		elseif (!empty($board))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$boards_data = fetchBoardsInfo(['boards' => $board], ['selects' => 'posts']);

			$feed_title = ' - ' . strip_tags($board_info['name']);

			$this->_query_this_board = 'b.id_board = ' . $board;

			// Try to look through just a few messages, if at all possible.
			if ($boards_data[(int) $board]['num_posts'] > 80 && $boards_data[(int) $board]['num_posts'] > $modSettings['totalMessages'] / 10)
			{
				$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $this->_limit * 5);
			}
		}
		else
		{
			$this->_query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != ' . $modSettings['recycle_board'] : '');
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $this->_limit * 5);
		}

		// If format isn't set, or is wrong, rss2 is default. Legacy 'rss' and 'rdf' map to rss2.
		$xml_format = $this->_req->getQuery('type', 'trim', 'rss2');
		if (!in_array($xml_format, ['rss2', 'atom']))
		{
			$xml_format = 'rss2';
		}

		// List all the different types of data they can pull.
		$subActions = [
			'recent' => ['action_xmlrecent'],
			'news' => ['action_xmlnews'],
			'members' => ['action_xmlmembers'],
			'profile' => ['action_xmlprofile'],
		];

		// Easy adding of sub actions
		call_integration_hook('integrate_xmlfeeds', [&$subActions]);

		$subAction = $this->_req->getQuery('sa', 'strtolower', 'news');
		$subAction = isset($subActions[$subAction]) ? $subAction : 'news';

		// We only want some information, not all of it.
		$cache_action = $this->_req->getQuery('action', 'trim|strval', '');
		$cachekey = [$xml_format, $cache_action, $this->_limit, $subAction];
		foreach (['board', 'boards', 'c'] as $var)
		{
			$val = $this->_req->getQuery($var, 'trim|strval');
			if ($val !== null)
			{
				$cachekey[] = $val;
			}
		}

		$cachekey = md5(serialize($cachekey) . (empty($this->_query_this_board) ? '' : $this->_query_this_board));
		$cache_t = microtime(true);
		$cache = Cache::instance();

		// Get the associative array representing the xml.
		if ($this->user->is_guest === false || $cache->levelHigherThan(2))
		{
			$xml = $cache->get('xmlfeed-' . $xml_format . ':' . ($this->user->is_guest ? '' : $this->user->id . '-') . $cachekey, 240);
		}

		if (empty($xml))
		{
			$xml = $this->{$subActions[$subAction][0]}($xml_format);

			if ($cache->isEnabled() && (($this->user->is_guest && $cache->levelHigherThan(2)) || ($this->user->is_guest === false && (microtime(true) - $cache_t > 0.2))))
			{
				$cache->put('xmlfeed-' . $xml_format . ':' . ($this->user->is_guest ? '' : $this->user->id . '-') . $cachekey, $xml, 240);
			}
		}

		$context['feed_title'] = encode_special(strip_tags(un_htmlspecialchars($context['forum_name']) . ($feed_title ?? '')));
		$context['feed_copyright'] = '© ' . date('Y') . ' ' . $mbname;

		// We send a feed with recent posts, and alerts for PMs for logged-in users
		$context['recent_posts_data'] = $xml;
		$context['xml_format'] = $xml_format;
		$context['feed_subaction'] = $subAction;

		// Build the board/category URL params used in self-referencing feed links
		$url_parts = [];
		foreach (['board', 'boards', 'c'] as $var)
		{
			$val = $this->_req->getQuery($var, 'trim|strval');
			if ($val !== null)
			{
				$url_parts[] = $var . '=' . $val;
			}
		}

		$context['url_parts'] = empty($url_parts) ? '' : implode(';', $url_parts);

		obStart(!empty($modSettings['enableCompressedOutput']));

		// This is an XML file....
		$headers = Headers::instance();
		if ($this->_req->hasQuery('debug'))
		{
			$headers->contentType('text/xml', 'UTF-8');
		}
		elseif ($xml_format === 'rss2')
		{
			$headers->contentType('application/rss+xml', 'UTF-8');
		}
		elseif ($xml_format === 'atom')
		{
			$headers->contentType('application/atom+xml', 'UTF-8');
		}

		// Set our own 30min cache control so auto-readers know how often to check in
		$context['no_last_modified'] = true;
		$headers
			->header('Cache-Control', 'max-age=' . (3600 * .5) . ', private')
			->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');

		theme()->getTemplates()->load('Xml');
		theme()->getLayers()->removeAll();

		// Are we outputting a rss2 feed or atom?
		if ($xml_format === 'rss2')
		{
			$context['sub_template'] = 'feedrss';
		}
		else
		{
			$context['sub_template'] = 'feedatom';
		}
	}

	/**
	 * Retrieve the list of members from database.
	 * The array will be generated to match the format.
	 *
	 * @param string $xml_format
	 * @return array
	 */
	public function action_xmlmembers($xml_format): array
	{
		global $scripturl;

		// Not allowed, then you get nothing
		if (!allowedTo('view_mlist'))
		{
			return [];
		}

		// Find the most recent members.
		require_once(SUBSDIR . '/Members.subs.php');
		$members = recentMembers((int) $this->_limit);

		// No data yet
		$data = [];

		require_once(SUBSDIR . '/News.subs.php');
		foreach ($members as $member)
		{
			// Make the data look rss-ish.
			if ($xml_format === 'rss2')
			{
				$data[] = [
					'title' => cdata_parse($member['real_name']),
					'link' => $scripturl . '?action=profile;u=' . $member['id_member'],
					'comments' => $scripturl . '?action=pm;sa=send;u=' . $member['id_member'],
					'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $member['date_registered']),
					'guid' => $scripturl . '?action=profile;u=' . $member['id_member'],
				];
			}
			elseif ($xml_format === 'atom')
			{
				$data[] = [
					'title' => cdata_parse($member['real_name']),
					'link' => $scripturl . '?action=profile;u=' . $member['id_member'],
					'published' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member['date_registered']),
					'updated' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member['last_login']),
					'id' => $scripturl . '?action=profile;u=' . $member['id_member'],
				];
			}
		}

		return $data;
	}

	/**
	 * Get the latest topics information from a specific board, to display later.
	 * The returned array will be generated to match the xmf_format.
	 *
	 * @param string $xml_format one of rss2, atom
	 * @return array array of topics
	 */
	public function action_xmlnews($xml_format): array
	{
		global $scripturl, $modSettings, $board;

		// Get the latest topics from a board
		require_once(SUBSDIR . '/News.subs.php');
		$results = getXMLNews($this->_query_this_board, $board, $this->_limit);

		// Prepare it for the feed in the format chosen (rss, atom)
		$data = [];

		foreach ($results as $row)
		{
			$row['body'] = $this->prepareFeed($row['body'], $row['smileys_enabled']);

			// Limit the length of the message, if the option is set.
			if (!empty($modSettings['xmlnews_maxlen']))
			{
				$row['body'] = Util::shorten_html($row['body'], $modSettings['xmlnews_maxlen']);
			}

			// Dirty mouth?
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			// Being news, this actually makes sense in rss format.
			if ($xml_format === 'rss2')
			{
				$data[] = [
					'title' => cdata_parse($row['subject']),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'description' => cdata_parse(str_replace('&', '&#x26;', un_htmlspecialchars($row['body']))),
					'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					'category' => '<![CDATA[' . $row['bname'] . ']]>',
					'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'dc:creator' => $row['poster_name'],
				];
			}
			elseif ($xml_format === 'atom')
			{
				$author = ['name' => $row['poster_name']];
				if (!empty($row['id_member']))
				{
					$author['uri'] = $scripturl . '?action=profile;u=' . $row['id_member'];
				}

				$data[] = [
					'title' => cdata_parse(un_htmlspecialchars($row['subject'])),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'summary' => cdata_parse($row['body']),
					'category' => $row['bname'],
					'author' => $author,
					'published' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					'updated' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					'id' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				];
			}
		}

		return $data;
	}

	/**
	 * Get the recent topics to display.
	 * The returned array will be generated to match the xml_format.
	 *
	 * @param string $xml_format one of rss2, atom
	 * @return array of recent posts
	 */
	public function action_xmlrecent($xml_format): array
	{
		global $scripturl, $modSettings, $board;

		// Get the latest news
		require_once(SUBSDIR . '/News.subs.php');
		$results = getXMLRecent($this->_query_this_board, $board, $this->_limit);

		// Loop on the results and prepare them in the format requested
		$data = [];
		$bbc_parser = ParserWrapper::instance();

		foreach ($results as $row)
		{
			// Limit the length of the message, if the option is set.
			if (!empty($modSettings['xmlnews_maxlen']) && Util::strlen(str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			{
				$row['body'] = strtr(Util::shorten_text(str_replace('<br />', "\n", $row['body']), $modSettings['xmlnews_maxlen'], true), ["\n" => '<br />']);
			}

			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// You can't say that
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			// Doesn't work as well as news, but it kinda does..
			if ($xml_format === 'rss2')
			{
				$data[] = [
					'title' => $row['subject'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'description' => cdata_parse(str_replace('&', '&#x26;', un_htmlspecialchars($row['body']))),
					'category' => cdata_parse($row['bname']),
					'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'dc:creator' => $row['poster_name'],
				];
			}
			elseif ($xml_format === 'atom')
			{
				$author = ['name' => $row['poster_name']];
				if (!empty($row['id_member']))
				{
					$author['uri'] = $scripturl . '?action=profile;u=' . $row['id_member'];
				}

				$data[] = [
					'title' => $row['subject'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'summary' => cdata_parse($row['body']),
					'category' => $row['bname'],
					'author' => $author,
					'published' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					'updated' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					'id' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				];
			}
		}

		return $data;
	}

	/**
	 * Get the profile information for member into an array,
	 * which will be generated to match the xml_format.
	 *
	 * @param string $xml_format one of rss2, atom
	 * @return array array of profile data.
	 */
	public function action_xmlprofile($xml_format): array
	{
		global $scripturl, $modSettings, $language;

		// You must input a valid user....
		if (empty($this->_req->query->u))
		{
			return [];
		}

		// Make sure the id is a number and not "I like trying to hack the database".
		$uid = (int) $this->_req->query->u;

		// You must input a valid user....
		if (MembersList::load($uid) === false)
		{
			return [];
		}

		// Load the member's contextual information!
		if (!allowedTo('profile_view_any'))
		{
			return [];
		}

		$member = MembersList::get($uid);
		$member->loadContext();

		// No feed data yet
		$data = [];

		require_once(SUBSDIR . '/News.subs.php');
		if ($xml_format === 'rss2')
		{
			$data = [[
				'title' => cdata_parse($member['name']),
				'link' => $scripturl . '?action=profile;u=' . $member['id'],
				'description' => cdata_parse($member['group'] ?? $member['post_group']),
				'comments' => $scripturl . '?action=pm;sa=send;u=' . $member['id'],
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $member->date_registered),
				'guid' => $scripturl . '?action=profile;u=' . $member['id'],
			]];
		}
		elseif ($xml_format === 'atom')
		{
			$author = ['name' => $member['real_name']];
			if (!empty($member['website']['url']))
			{
				$author['uri'] = $member['website']['url'];
			}

			$data[] = [
				'title' => cdata_parse($member['name']),
				'link' => $scripturl . '?action=profile;u=' . $member['id'],
				'summary' => cdata_parse($member['group'] ?? $member['post_group']),
				'author' => $author,
				'published' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member->date_registered),
				'updated' => Util::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member->last_login),
				'id' => $scripturl . '?action=profile;u=' . $member['id'],
				'logo' => empty($member['avatar']) ? '' : $member['avatar']['url'],
			];
		}

		// Save some memory.
		MembersList::unset($uid);

		return $data;
	}

	/**
	 * Prepares a post so that it is better suited for RSS feeds.  Feed readers will silently ignore
	 * HTML they don't accept, so we do some work to make sure the posts look good in feeds.
	 *
	 * - Pre-converts select bbc tags to HTML, so they are more generic
	 * - Uses parse-bbc to convert remaining bbc to HTML
	 * - Hides code blocks so they don't get converted by the bbc parsing, and restores them at the end
	 * - Converts smileys to images with inline size attributes
	 * - Strips onclick events from quote and code tags
	 *
	 * @param string $message the post in glorious BBC format
	 * @return string HTML text
	 */
	public function prepareFeed($message, $smileys_enabled): string
	{
		// <br /> back to newlines for easier processing
		$message = str_replace('<br />', "\n", $message);

		// Convert bbc [quotes] before we go to parsebbc
		$message = preg_replace_callback('~\[quote[^]]*?]~iu', fn(array $matches): string => $this->quoteCallback($matches), $message);
		$message = str_replace('[/quote]', '</blockquote>', $message);

		// Prevent img tags from getting linked
		$message = preg_replace('~\[img](.*?)\[/img]~is', '`&lt;img src="\\1">', $message);

		// Hide code tags so they don't get messed with by the bbc parsing, we'll restore them at the end
		$preparse = PreparseCode::instance('');
		$message = $preparse->tokenizeCodeBlocks($message);

		// Allow addons to account for their own unique bbc additions e.g., gallery's etc.
		call_integration_hook('integrate_rss_pre_parsebbc', [&$message]);

		// Convert the remaining BBC to HTML
		$bbc_wrapper = ParserWrapper::instance();
		$md_wrapper = $bbc_wrapper->getMarkdownParser();
		$message = $bbc_wrapper->parseMessage(trim($message), $smileys_enabled);

		// Add size to smiley/emoji images, strip class attribute.  Note that the style attribute is stripped by
		// feed readers, so we have to use width and height attributes to ensure they look right.
		$message = preg_replace(
			'~(<img\s[^>]*)class="[^"]*(?:smiley|emoji)[^"]*"([^>]*)(/?>)~',
			'$1width="16" height="16"$2$3',
			$message
		);

		// Drop the quote-show-more input box, and add a newline after the cite for better formatting in feeds
		$message = str_replace(['<input type="checkbox" title="show" class="quote-show-more">', '</cite>'], ['', "</cite>\n"], $message);

		// Allow addons to account for their own unique bbc additions e.g., gallery's etc.
		call_integration_hook('integrate_rss_post_parsebbc', [&$message]);

		// Restore code blocks and convert newlines back to <br />
		$message = $preparse->restoreCodeBlocks($message);
		$message = str_replace("\n", '<br />', $message);

		// Convert Markdown code tags to BBC code tags
		$message = $md_wrapper->inlineCodeTags($message);

		// Simple code tags for the feeds
		$message = preg_replace('~\[code(.*?)](.*?)\[/code]~is', '<code$1>$2</code>', $message);
		$message = preg_replace('~\[icode](.*?)\[/icode]~is', '<span>[ $1 ]</span>', $message);

		return strtr($message, ['&#91;' => '[', '&#93;' => ']', '`&lt;' => '<']);
	}

	/**
	 * Replace full bbc quote tags with HTML blockquote version where the cite line
	 * is used as the first line of the quote.
	 *
	 * - Callback for preparseHtml
	 * - Only replaces opening [quote] tags, the closing /quote is replaced back in
	 * the main function
	 *
	 * @param string[] $matches array of matches from the regex in the preg_replace
	 * @return string
	 */
	private function quoteCallback($matches): string
	{
		global $txt;

		$date = '';
		$author = $txt['quote'];

		if (preg_match('~date=(\d{8,10})~ui', $matches[0], $match) === 1)
		{
			$date = $txt['email_on'] . ': ' . date('D M j, Y', $match[1]);
		}

		if (preg_match('~author=([^<>\n]+?)(?=(?:link=|date=|\]))~ui', $matches[0], $match) === 1)
		{
			$author = $match[1] . $txt['email_wrote'] . ': ';
		}

		return '<blockquote><cite>' . $date . ' ' . $author . '</cite><hr>';
	}
}
