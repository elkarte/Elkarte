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
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\MembersList;
use ElkArte\Themes\ThemeLoader;
use ElkArte\Util;

/**
 * News Controller class
 */
class News extends AbstractController
{
	/**
	 * Holds news specific version board query for news feeds
	 *
	 * @var string
	 */
	private $_query_this_board = null;

	/**
	 * Holds the limit for the number of items to get
	 *
	 * @var int
	 */
	private $_limit;

	/**
	 * {@inheritdoc }
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
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// do... something, of your favorite.
		// $this->action_xmlnews();
	}

	/**
	 * Outputs xml data representing recent information or a profile.
	 *
	 * What it does:
	 *
	 * - Can be passed 4 subactions which decide what is output:
	 *     * 'recent' for recent posts,
	 *     * 'news' for news topics,
	 *     * 'members' for recently registered members,
	 *     * 'profile' for a member's profile.
	 * - To display a member's profile, a user id has to be given. (;u=1) e.g. ?action=.xml;sa=profile;u=1;type=atom
	 * - Outputs an feed based on the the 'type'
	 *       * parameter is 'rss', 'rss2', 'rdf', 'atom'.
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
	public function action_showfeed()
	{
		global $board, $board_info, $context, $txt, $modSettings;

		// If it's not enabled, die.
		if (empty($modSettings['xmlnews_enable']))
		{
			obExit(false);
		}

		ThemeLoader::loadLanguageFile('Stats');
		$txt['xml_rss_desc'] = replaceBasicActionUrl($txt['xml_rss_desc']);

		// Default to latest 5.  No more than whats defined in the ACP or 255
		$limit = empty($modSettings['xmlnews_limit']) ? 5 : min($modSettings['xmlnews_limit'], 255);
		$this->_limit = empty($this->_req->query->limit) || (int) $this->_req->query->limit < 1 ? $limit : min((int) $this->_req->query->limit, $limit);

		// Handle the cases where a board, boards, or category is asked for.
		$this->_query_this_board = '1=1';
		$context['optimize_msg'] = array(
			'highest' => 'm.id_msg <= b.id_last_msg',
		);

		// Specifying specific categories only?
		if (!empty($this->_req->query->c) && empty($board))
		{
			$categories = array_map('intval', explode(',', $this->_req->query->c));

			if (count($categories) === 1)
			{
				require_once(SUBSDIR . '/Categories.subs.php');
				$feed_title = categoryName($categories[0]);
				$feed_title = ' - ' . strip_tags($feed_title);
			}

			require_once(SUBSDIR . '/Boards.subs.php');
			$boards_posts = boardsPosts(array(), $categories);
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
		elseif (!empty($this->_req->query->boards))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$query_boards = array_map('intval', explode(',', $this->_req->query->boards));

			$boards_data = fetchBoardsInfo(array('boards' => $query_boards), array('selects' => 'detailed'));

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
			$boards_data = fetchBoardsInfo(array('boards' => $board), array('selects' => 'posts'));

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

		// If format isn't set, or is wrong, rss2 is default
		$xml_format = $this->_req->getQuery('type', 'trim', 'rss2');
		if (!in_array($xml_format, array('rss', 'rss2', 'atom', 'rdf')))
		{
			$xml_format = 'rss2';
		}

		// List all the different types of data they can pull.
		$subActions = array(
			'recent' => array('action_xmlrecent'),
			'news' => array('action_xmlnews'),
			'members' => array('action_xmlmembers'),
			'profile' => array('action_xmlprofile'),
		);

		// Easy adding of sub actions
		call_integration_hook('integrate_xmlfeeds', array(&$subActions));

		$subAction = $this->_req->getQuery('sa', 'strtolower', 'recent');
		$subAction = isset($subActions[$subAction]) ? $subAction : 'recent';

		// We only want some information, not all of it.
		$cachekey = array($xml_format, $this->_req->query->action, $this->_limit, $subAction);
		foreach (array('board', 'boards', 'c') as $var)
		{
			if (isset($this->_req->query->{$var}))
			{
				$cachekey[] = $this->_req->query->{$var};
			}
		}

		$cachekey = md5(serialize($cachekey) . (!empty($this->_query_this_board) ? $this->_query_this_board : ''));
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

		$context['feed_title'] = encode_special(strip_tags(un_htmlspecialchars($context['forum_name']) . (isset($feed_title) ? $feed_title : '')));

		// We send a feed with recent posts, and alerts for PMs for logged in users
		$context['recent_posts_data'] = $xml;
		$context['xml_format'] = $xml_format;

		obStart(!empty($modSettings['enableCompressedOutput']));

		$headers = \ElkArte\Http\Headers::instance();
		// This is an xml file....
		if (isset($this->_req->query->debug))
		{
			$headers->contentType('text/xml', 'UTF-8');
		}
		elseif ($xml_format === 'rss' || $xml_format === 'rss2')
		{
			$headers->contentType('application/rss+xml', 'UTF-8');
		}
		elseif ($xml_format === 'atom')
		{
			$headers->contentType('application/atom+xml', 'UTF-8');
		}
		elseif ($xml_format === 'rdf')
		{
			$headers->contentType('application/rdf+xml', 'UTF-8');
		}

		theme()->getTemplates()->load('Xml');
		theme()->getLayers()->removeAll();

		// Are we outputting an rss feed or one with more information?
		if ($xml_format === 'rss' || $xml_format === 'rss2')
		{
			$context['sub_template'] = 'feedrss';
		}
		elseif ($xml_format === 'atom')
		{
			$url_parts = array();
			foreach (array('board', 'boards', 'c') as $var)
			{
				if (isset($this->_req->query->{$var}))
				{
					$url_parts[] = $var . '=' . (is_array($this->_req->query->{$var}) ? implode(',', $this->_req->query->{$var}) : $this->_req->query->{$var});
				}
			}

			$context['url_parts'] = !empty($url_parts) ? implode(';', $url_parts) : '';
			$context['sub_template'] = 'feedatom';
		}
		// rdf by default
		else
		{
			$context['sub_template'] = 'rdf';
		}
	}

	/**
	 * Retrieve the list of members from database.
	 * The array will be generated to match the format.
	 *
	 * @param string $xml_format
	 * @return mixed[]
	 */
	public function action_xmlmembers($xml_format)
	{
		global $scripturl;

		// Not allowed, then you get nothing
		if (!allowedTo('view_mlist'))
		{
			return array();
		}

		// Find the most recent members.
		require_once(SUBSDIR . '/Members.subs.php');
		$members = recentMembers((int) $this->_limit);

		// No data yet
		$data = array();

		foreach ($members as $member)
		{
			// Make the data look rss-ish.
			if ($xml_format === 'rss' || $xml_format === 'rss2')
			{
				$data[] = array(
					'title' => cdata_parse($member['real_name']),
					'link' => $scripturl . '?action=profile;u=' . $member['id_member'],
					'comments' => $scripturl . '?action=pm;sa=send;u=' . $member['id_member'],
					'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $member['date_registered']),
					'guid' => $scripturl . '?action=profile;u=' . $member['id_member'],
				);
			}
			elseif ($xml_format === 'rdf')
			{
				$data[] = array(
					'title' => cdata_parse($member['real_name']),
					'link' => $scripturl . '?action=profile;u=' . $member['id_member'],
				);
			}
			elseif ($xml_format === 'atom')
			{
				$data[] = array(
					'title' => cdata_parse($member['real_name']),
					'link' => $scripturl . '?action=profile;u=' . $member['id_member'],
					'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member['date_registered']),
					'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member['last_login']),
					'id' => $scripturl . '?action=profile;u=' . $member['id_member'],
				);
			}
			// More logical format for the data, but harder to apply.
			else
			{
				$data[] = array(
					'name' => cdata_parse($member['real_name']),
					'time' => htmlspecialchars(strip_tags(standardTime($member['date_registered'])), ENT_COMPAT, 'UTF-8'),
					'id' => $member['id_member'],
					'link' => $scripturl . '?action=profile;u=' . $member['id_member']
				);
			}
		}

		return $data;
	}

	/**
	 * Get the latest topics information from a specific board, to display later.
	 * The returned array will be generated to match the xmf_format.
	 *
	 * @param string $xml_format one of rss, rss2, rdf, atom
	 * @return mixed[] array of topics
	 */
	public function action_xmlnews($xml_format)
	{
		global $scripturl, $modSettings, $board;

		// Get the latest topics from a board
		require_once(SUBSDIR . '/News.subs.php');
		$results = getXMLNews($this->_query_this_board, $board, $this->_limit);

		// Prepare it for the feed in the format chosen (rss, atom, etc)
		$data = array();
		$bbc_parser = ParserWrapper::instance();

		foreach ($results as $row)
		{
			// Limit the length of the message, if the option is set.
			if (!empty($modSettings['xmlnews_maxlen']) && Util::strlen(str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			{
				$row['body'] = strtr(Util::shorten_text(str_replace('<br />', "\n", $row['body']), $modSettings['xmlnews_maxlen'], true), array("\n" => '<br />'));
			}

			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// Dirty mouth?
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			// Being news, this actually makes sense in rss format.
			if ($xml_format === 'rss' || $xml_format === 'rss2')
			{
				$data[] = array(
					'title' => cdata_parse($row['subject']),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'description' => cdata_parse(strtr(un_htmlspecialchars($row['body']), '&', '&#x26;')),
					'author' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] . ' (' . un_htmlspecialchars($row['poster_name']) . ')' : '<![CDATA[none@noreply.net (' . un_htmlspecialchars($row['poster_name']) . ')]]>',
					'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					'category' => '<![CDATA[' . $row['bname'] . ']]>',
					'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				);

				// Add the poster name on if we are rss2
				if ($xml_format === 'rss2')
				{
					$data[count($data) - 1]['dc:creator'] = $row['poster_name'];
					unset($data[count($data) - 1]['author']);
				}
			}
			// RDF Format anyone
			elseif ($xml_format === 'rdf')
			{
				$data[] = array(
					'title' => cdata_parse($row['subject']),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'description' => cdata_parse($row['body']),
				);
			}
			// Atom feed
			elseif ($xml_format === 'atom')
			{
				$data[] = array(
					'title' => cdata_parse($row['subject']),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'summary' => cdata_parse($row['body']),
					'category' => $row['bname'],
					'author' => array(
						'name' => $row['poster_name'],
						'email' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
						'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
					),
					'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					'modified' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					'id' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				);
			}
			// The biggest difference here is more information.
			else
			{
				$data[] = array(
					'time' => htmlspecialchars(strip_tags(standardTime($row['poster_time'])), ENT_COMPAT, 'UTF-8'),
					'id' => $row['id_topic'],
					'subject' => cdata_parse($row['subject']),
					'body' => cdata_parse($row['body']),
					'poster' => array(
						'name' => cdata_parse($row['poster_name']),
						'id' => $row['id_member'],
						'link' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
					),
					'topic' => $row['id_topic'],
					'board' => array(
						'name' => cdata_parse($row['bname']),
						'id' => $row['id_board'],
						'link' => $scripturl . '?board=' . $row['id_board'] . '.0',
					),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				);
			}
		}

		return $data;
	}

	/**
	 * Get the recent topics to display.
	 * The returned array will be generated to match the xml_format.
	 *
	 * @param string $xml_format one of rss, rss2, rdf, atom
	 * @return mixed[] of recent posts
	 */
	public function action_xmlrecent($xml_format)
	{
		global $scripturl, $modSettings, $board;

		// Get the latest news
		require_once(SUBSDIR . '/News.subs.php');
		$results = getXMLRecent($this->_query_this_board, $board, $this->_limit);

		// Loop on the results and prepare them in the format requested
		$data = array();
		$bbc_parser = ParserWrapper::instance();

		foreach ($results as $row)
		{
			// Limit the length of the message, if the option is set.
			if (!empty($modSettings['xmlnews_maxlen']) && Util::strlen(str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			{
				$row['body'] = strtr(Util::shorten_text(str_replace('<br />', "\n", $row['body']), $modSettings['xmlnews_maxlen'], true), array("\n" => '<br />'));
			}

			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// You can't say that
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			// Doesn't work as well as news, but it kinda does..
			if ($xml_format === 'rss' || $xml_format === 'rss2')
			{
				$data[] = array(
					'title' => $row['subject'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'description' => cdata_parse(strtr(un_htmlspecialchars($row['body']), '&', '&#x26;')),
					'author' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] . ' (' . un_htmlspecialchars($row['poster_name']) . ')' : '<![CDATA[none@noreply.net (' . un_htmlspecialchars($row['poster_name']) . ')]]>',
					'category' => cdata_parse($row['bname']),
					'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
				);

				// Add the poster name on if we are rss2
				if ($xml_format === 'rss2')
				{
					$data[count($data) - 1]['dc:creator'] = $row['poster_name'];
					unset($data[count($data) - 1]['author']);
				}
			}
			elseif ($xml_format === 'rdf')
			{
				$data[] = array(
					'title' => $row['subject'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'description' => cdata_parse($row['body']),
				);
			}
			elseif ($xml_format === 'atom')
			{
				$data[] = array(
					'title' => $row['subject'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'summary' => cdata_parse($row['body']),
					'category' => $row['bname'],
					'author' => array(
						'name' => $row['poster_name'],
						'email' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
						'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
					),
					'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					'id' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				);
			}
			// A lot of information here.  Should be enough to please the rss-ers.
			else
			{
				$data[] = array(
					'time' => htmlspecialchars(strip_tags(standardTime($row['poster_time'])), ENT_COMPAT, 'UTF-8'),
					'id' => $row['id_msg'],
					'subject' => cdata_parse($row['subject']),
					'body' => cdata_parse($row['body']),
					'starter' => array(
						'name' => cdata_parse($row['first_poster_name']),
						'id' => $row['id_first_member'],
						'link' => !empty($row['id_first_member']) ? $scripturl . '?action=profile;u=' . $row['id_first_member'] : ''
					),
					'poster' => array(
						'name' => cdata_parse($row['poster_name']),
						'id' => $row['id_member'],
						'link' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
					),
					'topic' => array(
						'subject' => cdata_parse($row['first_subject']),
						'id' => $row['id_topic'],
						'link' => $scripturl . '?topic=' . $row['id_topic'] . '.new#new'
					),
					'board' => array(
						'name' => cdata_parse($row['bname']),
						'id' => $row['id_board'],
						'link' => $scripturl . '?board=' . $row['id_board'] . '.0'
					),
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
				);
			}
		}

		return $data;
	}

	/**
	 * Get the profile information for member into an array,
	 * which will be generated to match the xml_format.
	 *
	 * @param string $xml_format one of rss, rss2, rdf, atom
	 * @return mixed[] array of profile data.
	 */
	public function action_xmlprofile($xml_format)
	{
		global $scripturl, $modSettings, $language;

		// You must input a valid user....
		if (empty($this->_req->query->u))
		{
			return array();
		}

		// Make sure the id is a number and not "I like trying to hack the database".
		$uid = (int) $this->_req->query->u;

		// You must input a valid user....
		if (MembersList::load($uid) === false)
		{
			return array();
		}

		// Load the member's contextual information!
		if (!allowedTo('profile_view_any'))
		{
			return array();
		}

		$member = MembersList::get($uid);
		$member->loadContext();

		// No feed data yet
		$data = array();

		if ($xml_format === 'rss' || $xml_format === 'rss2')
		{
			$data = array(array(
							  'title' => cdata_parse($member['name']),
							  'link' => $scripturl . '?action=profile;u=' . $member['id'],
							  'description' => cdata_parse(isset($member['group']) ? $member['group'] : $member['post_group']),
							  'comments' => $scripturl . '?action=pm;sa=send;u=' . $member['id'],
							  'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $member->date_registered),
							  'guid' => $scripturl . '?action=profile;u=' . $member['id'],
						  ));
		}
		elseif ($xml_format === 'rdf')
		{
			$data = array(array(
							  'title' => cdata_parse($member['name']),
							  'link' => $scripturl . '?action=profile;u=' . $member['id'],
							  'description' => cdata_parse(isset($member['group']) ? $member['group'] : $member['post_group']),
						  ));
		}
		elseif ($xml_format === 'atom')
		{
			$data[] = array(
				'title' => cdata_parse($member['name']),
				'link' => $scripturl . '?action=profile;u=' . $member['id'],
				'summary' => cdata_parse(isset($member['group']) ? $member['group'] : $member['post_group']),
				'author' => array(
					'name' => $member['real_name'],
					'email' => in_array(showEmailAddress(!empty($member['hide_email']), $member['id']), array('yes', 'yes_permission_override')) ? $member['email'] : null,
					'uri' => !empty($member['website']) ? $member['website']['url'] : ''
				),
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member->date_registered),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $member->last_login),
				'id' => $scripturl . '?action=profile;u=' . $member['id'],
				'logo' => !empty($member['avatar']) ? $member['avatar']['url'] : '',
			);
		}
		else
		{
			$data = array(
				'username' => $this->user->is_admin || $this->user->id == $member['id'] ? cdata_parse($member['username']) : '',
				'name' => cdata_parse($member['name']),
				'link' => $scripturl . '?action=profile;u=' . $member['id'],
				'posts' => $member['posts'],
				'post-group' => cdata_parse($member['post_group']),
				'language' => cdata_parse(!empty($member['language']) ? $member['language'] : Util::ucwords(strtr($language, array('_' => ' ', '-utf8' => '')))),
				'last-login' => gmdate('D, d M Y H:i:s \G\M\T', $member->last_login),
				'registered' => gmdate('D, d M Y H:i:s \G\M\T', $member->date_registered)
			);

			// Everything below here might not be set, and thus maybe shouldn't be displayed.
			if ($member['avatar']['name'] != '')
			{
				$data['avatar'] = $member['avatar']['url'];
			}

			// If they are online, show an empty tag... no reason to put anything inside it.
			if ($member['online']['is_online'])
			{
				$data['online'] = '';
			}

			if ($member['signature'] != '')
			{
				$data['signature'] = cdata_parse($member['signature']);
			}

			if ($member['title'] != '')
			{
				$data['title'] = cdata_parse($member['title']);
			}

			if ($member['website']['title'] != '')
			{
				$data['website'] = array(
					'title' => cdata_parse($member['website']['title']),
					'link' => $member['website']['url']
				);
			}

			if ($member['group'] != '')
			{
				$data['position'] = cdata_parse($member['group']);
			}

			if (!empty($modSettings['karmaMode']))
			{
				$data['karma'] = array(
					'good' => $member['karma']['good'],
					'bad' => $member['karma']['bad']
				);
			}

			if (in_array($member['show_email'], array('yes', 'yes_permission_override')))
			{
				$data['email'] = $member['email'];
			}

			if (!empty($member['birth_date']) && substr($member['birth_date'], 0, 4) !== '0000')
			{
				list ($birth_year, $birth_month, $birth_day) = sscanf($member['birth_date'], '%d-%d-%d');
				$datearray = getdate(forum_time());
				$data['age'] = $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] === $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);
			}
		}

		// Save some memory.
		MembersList::unset($uid);

		return $data;
	}
}

/**
 * Called to convert data to xml
 * Finds urls for local site and sanitizes them
 *
 * @param string $val
 *
 * @return null|string|string[]
 */
function fix_possible_url($val)
{
	global $scripturl;

	if (substr($val, 0, strlen($scripturl)) != $scripturl)
	{
		return $val;
	}

	call_integration_hook('integrate_fix_url', array(&$val));

	return $val;
}

/**
 * For highest feed compatibility, some special characters should be provided
 * as character entities and not html entities
 *
 * @param string $data
 *
 * @return string
 */
function encode_special($data)
{
	return strtr($data, array('>' => '&#x3E;', '&' => '&#x26;', '<' => '&#x3C;'));
}

/**
 * Ensures supplied data is properly encapsulated in cdata xml tags
 * Called from action_xmlprofile in News.controller.php
 *
 * @param string $data
 * @param string $ns
 * @param string $override
 *
 * @return string
 */
function cdata_parse($data, $ns = '', $override = null)
{
	static $cdata_override = false;

	if ($override !== null)
	{
		$cdata_override = (bool) $override;
	}

	// Are we not doing it?
	if (!empty($cdata_override))
	{
		return $data;
	}

	$cdata = '<![CDATA[';

	for ($pos = 0, $n = Util::strlen($data); $pos < $n; null)
	{
		$positions = array(
			Util::strpos($data, '&', $pos),
			Util::strpos($data, ']]>', $pos),
		);

		if ($ns !== '')
		{
			$positions[] = Util::strpos($data, '<', $pos);
		}

		foreach ($positions as $k => $dummy)
		{
			if ($dummy === false)
			{
				unset($positions[$k]);
			}
		}

		$old = $pos;
		$pos = empty($positions) ? $n : min($positions);

		if ($pos - $old > 0)
		{
			$cdata .= Util::substr($data, $old, $pos - $old);
		}

		if ($pos >= $n)
		{
			break;
		}

		if (Util::substr($data, $pos, 1) === '<')
		{
			$pos2 = Util::strpos($data, '>', $pos);
			if ($pos2 === false)
			{
				$pos2 = $n;
			}

			if (Util::substr($data, $pos + 1, 1) === '/')
			{
				$cdata .= ']]></' . $ns . ':' . Util::substr($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
			}
			else
			{
				$cdata .= ']]><' . $ns . ':' . Util::substr($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
			}

			$pos = $pos2 + 1;
		}
		elseif (Util::substr($data, $pos, 3) == ']]>')
		{
			$cdata .= ']]]]><![CDATA[>';
			$pos = $pos + 3;
		}
		elseif (Util::substr($data, $pos, 1) === '&')
		{
			$pos2 = Util::strpos($data, ';', $pos);

			if ($pos2 === false)
			{
				$pos2 = $n;
			}

			$ent = Util::substr($data, $pos + 1, $pos2 - $pos - 1);

			if (Util::substr($data, $pos + 1, 1) === '#')
			{
				$cdata .= ']]>' . Util::substr($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			}
			elseif (in_array($ent, array('amp', 'lt', 'gt', 'quot')))
			{
				$cdata .= ']]>' . Util::substr($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			}

			$pos = $pos2 + 1;
		}
	}

	$cdata .= ']]>';

	return strtr($cdata, array('<![CDATA[]]>' => ''));
}
