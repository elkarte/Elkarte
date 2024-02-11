<?php

/**
 * Handle all searching from here.
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

use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\MessagesCallback\BodyParser\Compact;
use ElkArte\MessagesCallback\BodyParser\Normal;
use ElkArte\MessagesCallback\SearchRenderer;
use ElkArte\MessageTopicIcons;
use ElkArte\Search\SearchApiWrapper;
use ElkArte\Search\SearchParams;
use ElkArte\Search\WeightFactors;
use ElkArte\Util;
use ElkArte\ValuesContainer;
use ElkArte\VerificationControls\VerificationControlsIntegrate;

/**
 * Handle the searching for the site
 *
 * @package Search
 */
class Search extends AbstractController
{
	/**
	 * Holds the search object
	 *
	 * @var \ElkArte\Search\Search
	 */
	protected $_search = null;

	/**
	 * The class that takes care of rendering the message icons (\ElkArte\MessageTopicIcons)
	 *
	 * @var null|\ElkArte\MessageTopicIcons
	 */
	protected $_icon_sources = null;

	/**
	 *
	 * @var array
	 */
	protected $_participants = [];

	/**
	 * Called before any other action method in this class.
	 *
	 * - If coming from the quick reply allows to route to the proper action
	 * - if needed (for example external search engine or members search
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		// Coming from quick search box and going to some custom place?
		$search_selection = $this->_req->getRequest('search_selection', 'trim');
		$search = $this->_req->getRequest('search', 'trim');
		if (isset($search_selection) && !empty($modSettings['additional_search_engines']))
		{
			$engines = prepareSearchEngines();
			if (isset($engines[$search_selection]))
			{
				$engine = $engines[$search_selection];
				redirectexit($engine['url'] . urlencode(implode($engine['separator'], explode(' ', $search))));
			}
		}

		// If coming from the quick search box, and we want to search on members, well we need to do that ;)
		if (isset($search_selection) && $search_selection === 'members')
		{
			redirectexit('action=memberlist;sa=search;fields=name,email;search=' . urlencode($search));
		}

		// If load management is on and the load is high, no need to even show the form.
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
		{
			throw new Exception('loadavg_search_disabled', false);
		}
	}

	/**
	 * Intended entry point for this class.
	 *
	 * - The default action for no sub-action is... present the search screen
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// Call the right method.
		$this->action_search();
	}

	/**
	 * Ask the user what they want to search for.
	 *
	 * What it does:
	 *
	 * - Shows the screen to search forum posts (action=search),
	 * - Uses the main sub template of the Search template.
	 * - Uses the Search language file.
	 * - Requires the search_posts permission.
	 * - Decodes and loads search parameters given in the URL (if any).
	 * - The form redirects to index.php?action=search;sa=results.
	 *
	 * @uses Search language file and Errors language when needed
	 * @uses Search template, searchform sub template
	 *
	 * @throws \ElkArte\Exceptions\Exception loadavg_search_disabled
	 */
	public function action_search()
	{
		global $txt, $modSettings, $context;

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
		{
			throw new Exception('loadavg_search_disabled', false);
		}

		Txt::load('Search');

		// Don't load this in XML mode.
		if ($this->getApi() === false)
		{
			theme()->getTemplates()->load('Search');
			$context['sub_template'] = 'searchform';
			loadJavascriptFile('suggest.js', array('defer' => true));
		}

		// Check the user's permissions.
		isAllowedTo('search_posts');

		// Link tree....
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'search']),
			'name' => $txt['search']
		);

		// This is hard coded maximum string length.
		$context['search_string_limit'] = 100;

		$context['require_verification'] = $this->user->is_guest && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);
		if ($context['require_verification'])
		{
			// Build a verification control for the form
			$verificationOptions = array(
				'id' => 'search',
			);

			$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		// If you got back from search;sa=results by using the linktree, you get your original search parameters back.
		$params = $this->_req->getQuery('params');
		if ($this->_search === null && isset($params))
		{
			$search_params = new SearchParams($params);

			$context['search_params'] = $search_params->get();
		}

		$search = $this->_req->getRequest('search', 'un_htmlspecialchars|trim');
		if (isset($search))
		{
			$context['search_params']['search'] = $search;
		}

		if (isset($context['search_params']['search']))
		{
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		}

		if (isset($context['search_params']['userspec']))
		{
			$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec'], ENT_COMPAT, 'UTF-8');
		}

		if (!empty($context['search_params']['searchtype']))
		{
			$context['search_params']['searchtype'] = 2;
		}

		if (!empty($context['search_params']['minage']))
		{
			$context['search_params']['minage'] = date("Y-m-d", strtotime('-' . $context['search_params']['minage'] . ' days'));
		}

		if (!empty($context['search_params']['maxage']))
		{
			if ($context['search_params']['maxage'] === 9999)
			{
				$context['search_params']['maxage'] = 0;
			}
			else
			{
				$context['search_params']['maxage'] = date("Y-m-d", strtotime('-' . $context['search_params']['maxage'] . ' days'));
			}
		}

		$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
		$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

		// Load the error text strings if there were errors in the search.
		if (!empty($context['search_errors']))
		{
			Txt::load('Errors');
			$context['search_errors']['messages'] = array();
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error === 'messages')
				{
					continue;
				}

				if ($search_error === 'string_too_long')
				{
					$txt['error_string_too_long'] = sprintf($txt['error_string_too_long'], $context['search_string_limit']);
				}

				$context['search_errors']['messages'][] = $txt['error_' . $search_error];
			}
		}

		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList(array('not_redirection' => true));

		$context['boards_in_category'] = array();
		foreach ($context['categories'] as $cat => &$category)
		{
			$context['boards_in_category'][$cat] = count($category['boards']);
			$category['child_ids'] = array_keys($category['boards']);
			foreach ($category['boards'] as &$board)
			{
				$board['selected'] = (empty($context['search_params']['brd']) && (empty($modSettings['recycle_enable']) || $board['id'] != $modSettings['recycle_board']) && !in_array($board['id'], (array) $this->user->ignoreboards)) || (!empty($context['search_params']['brd']) && in_array($board['id'], $context['search_params']['brd']));
			}
		}

		$topic = $this->_req->getRequest('topic', 'intval', 0);
		if (!empty($topic))
		{
			$context['search_params']['topic'] = $topic;
			$context['search_params']['show_complete'] = true;
		}

		if (!empty($context['search_params']['topic']))
		{
			$context['search_params']['topic'] = (int) $context['search_params']['topic'];

			$context['search_topic'] = array(
				'id' => $context['search_params']['topic'],
				'href' => getUrl('action', ['topic' => $context['search_params']['topic'] . '.0']),
			);

			require_once(SUBSDIR . '/Topic.subs.php');
			$context['search_topic']['subject'] = getSubject($context['search_params']['topic']);
			$context['search_topic']['link'] = '<a href="' . $context['search_topic']['href'] . '">' . $context['search_topic']['subject'] . '</a>';
		}

		$context['page_title'] = $txt['set_parameters'];
		$context['search_params'] = $this->_fill_default_search_params($context['search_params']);

		// Start guest off collapsed
		if ($context['user']['is_guest'] && !isset($context['minmax_preferences']['asearch']))
		{
			$context['minmax_preferences']['asearch'] = 1;
		}

		call_integration_hook('integrate_search');
	}

	/**
	 * Fills the empty spaces in an array with the default values for search params
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	private function _fill_default_search_params($array)
	{
		$default = array(
			'search' => '',
			'userspec' => '*',
			'searchtype' => 0,
			'show_complete' => 0,
			'subject_only' => 0,
			'minage' => 0,
			'maxage' => 9999,
			'sort' => 'relevance',
		);

		$array = array_merge($default, $array);
		if (empty($array['userspec']))
		{
			$array['userspec'] = '*';
		}
		$array['show_complete'] = (int) $array['show_complete'];
		$array['subject_only'] = (int) $array['subject_only'];

		return $array;
	}

	/**
	 * Gather the results and show them.
	 *
	 * What it does:
	 *
	 * - Checks user input and searches the messages table for messages matching the query.
	 * - Requires the search_posts permission.
	 * - Uses the results sub template of the Search template.
	 * - Uses the Search language file.
	 * - Stores the results into the search cache.
	 * - Show the results of the search query.
	 */
	public function action_results()
	{
		global $modSettings, $txt, $settings, $context, $options, $messages_request;

		// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
		stop_prefetching();

		// These vars don't require an interface, they're just here for tweaking.
		$recentPercentage = 0.30;

		// Message length used to tweak messages relevance of the results
		$humungousTopicPosts = 200;
		$shortTopicPosts = 5;
		$maxMembersToSearch = 500;

		// Maximum number of results
		$maxMessageResults = empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] * 5;

		// Start with no errors.
		$context['search_errors'] = array();

		// Number of pages hard maximum - normally not set at all.
		$modSettings['search_max_results'] = empty($modSettings['search_max_results']) ? 200 * $modSettings['search_results_per_page'] : (int) $modSettings['search_max_results'];

		// Maximum length of the string.
		$context['search_string_limit'] = 100;

		Txt::load('Search');
		if ($this->getApi() === false)
		{
			theme()->getTemplates()->load('Search');
		}
		// If we're doing XML we need to use the results template regardless really.
		else
		{
			$context['sub_template'] = 'results';
		}

		// Are you allowed?
		isAllowedTo('search_posts');

		$this->_search = new \ElkArte\Search\Search();
		$this->_search->setWeights(new WeightFactors($modSettings, $this->user->is_admin));

		$params = $this->_req->getRequest('params', '', '');
		$search_params = new SearchParams($params);
		$search_params->merge((array) $this->_req->post, $recentPercentage, $maxMembersToSearch);
		$this->_search->setParams($search_params, !empty($modSettings['search_simple_fulltext']));

		$context['compact'] = $this->_search->isCompact();

		// Nothing??
		if ($this->_search->param('search') === false || $this->_search->param('search') === '')
		{
			$context['search_errors']['invalid_search_string'] = true;
		}
		// Too long?
		elseif (Util::strlen($this->_search->param('search')) > $context['search_string_limit'])
		{
			$context['search_errors']['string_too_long'] = true;
		}

		// Build the search array
		// $modSettings ['search_simple_fulltext'] is an hidden setting that will
		// do fulltext searching in the most basic way.
		$searchArray = $this->_search->getSearchArray();

		// This is used to remember words that will be ignored (because too short usually)
		$context['search_ignored'] = $this->_search->getIgnored();

		// Make sure at least one word is being searched for.
		if (empty($searchArray))
		{
			if (!empty($context['search_ignored']))
			{
				$context['search_errors']['search_string_small_words'] = true;
			}
			else
			{
				$context['search_errors']['invalid_search_string' . ($this->_search->foundBlockListedWords() ? '_blocklist' : '')] = true;
			}

			// Don't allow duplicate error messages if one string is too short.
			if (isset($context['search_errors']['search_string_small_words'], $context['search_errors']['invalid_search_string']))
			{
				unset($context['search_errors']['invalid_search_string']);
			}
		}

		// Let the user adjust the search query, should they wish?
		$context['search_params'] = (array) $this->_search->getSearchParams(true);
		if (isset($context['search_params']['search']))
		{
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		}

		if (isset($context['search_params']['userspec']))
		{
			$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);
		}

		if (empty($context['search_params']['minage']))
		{
			$context['search_params']['minage'] = 0;
		}

		if (empty($context['search_params']['maxage']))
		{
			$context['search_params']['maxage'] = 9999;
		}

		$context['search_params'] = $this->_fill_default_search_params($context['search_params']);

		$this->_controlVerifications();

		$context['params'] = $this->_search->compileURLparams();

		// ... and add the links to the link tree.
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'search', 'params' => $context['params']]),
			'name' => $txt['search']
		);

		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'search', 'sa' => 'results', 'params' => $context['params']]),
			'name' => $txt['search_results']
		);

		// Start guest off collapsed
		if ($context['user']['is_guest'] && !isset($context['minmax_preferences']['asearch']))
		{
			$context['minmax_preferences']['asearch'] = 1;
		}

		// *** A last error check
		call_integration_hook('integrate_search_errors');

		// One or more search errors? Go back to the first search screen.
		if (!empty($context['search_errors']))
		{
			return $this->action_search();
		}

		// Spam me not, Spam-a-lot?
		if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] !== $this->_search->param('search'))
		{
			spamProtection('search');
		}

		// Store the last search string to allow pages of results to be browsed.
		$_SESSION['last_ss'] = $this->_search->param('search');

		try
		{
			$search_config = new ValuesContainer(array(
				'humungousTopicPosts' => $humungousTopicPosts,
				'shortTopicPosts' => $shortTopicPosts,
				'maxMessageResults' => $maxMessageResults,
				'search_index' => !empty($modSettings['search_index']) ? $modSettings['search_index'] : '',
				'banned_words' => empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']),
			));
			$context['topics'] = $this->_search->searchQuery(
				new SearchApiWrapper($search_config, $this->_search->getSearchParams())
			);
		}
		catch (\Exception $e)
		{
			$context['search_errors'][$e->getMessage()] = true;

			return $this->action_search();
		}

		// Did we find anything?
		if (!empty($context['topics']))
		{
			// Create an array for the permissions.
			$boards_can = boardsAllowedTo(array('post_reply_own', 'post_reply_any', 'mark_any_notify'), true, false);

			// How's about some quick moderation?
			if (!empty($options['display_quick_mod']))
			{
				$boards_can = array_merge($boards_can, boardsAllowedTo(array('lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any'), true, false));

				$context['can_lock'] = in_array(0, $boards_can['lock_any']);
				$context['can_sticky'] = in_array(0, $boards_can['make_sticky']);
				$context['can_move'] = in_array(0, $boards_can['move_any']);
				$context['can_remove'] = in_array(0, $boards_can['remove_any']);
				$context['can_merge'] = in_array(0, $boards_can['merge_any']);
			}

			// What messages are we using?
			$msg_list = array_keys($context['topics']);
			$posters = $this->_search->loadPosters($msg_list, count($context['topics']));

			call_integration_hook('integrate_search_message_list', array(&$msg_list, &$posters));

			if (!empty($posters))
			{
				MembersList::load(array_unique($posters));
			}

			// Get the messages out for the callback - select enough that it can be made to look just like Display.
			$messages_request = $this->_search->loadMessagesRequest($msg_list, count($context['topics']));

			// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
			if ($this->_search->noMessages($messages_request))
			{
				$context['topics'] = array();
			}

			$this->_prepareParticipants(!empty($modSettings['enableParticipation']), (int) $this->user->id);
		}

		// Now that we know how many results to expect we can start calculating the page numbers.
		$start = $this->_req->getRequest('start', 'intval', 0);
		$context['page_index'] = constructPageIndex('{scripturl}?action=search;sa=results;params=' . $context['params'], $start, $this->_search->getNumResults(), $modSettings['search_results_per_page'], false);

		// Consider the search complete!
		Cache::instance()->remove('search_start:' . ($this->user->is_guest ? $this->user->ip : $this->user->id));

		$context['sub_template'] = 'results';
		$context['page_title'] = $txt['search_results'];

		$this->_icon_sources = new MessageTopicIcons(!empty($modSettings['messageIconChecks_enable']), $settings['theme_dir']);

		// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
		// This will be called from the template.
		if ($this->_search->isCompact())
		{
			$bodyParser = new Compact($this->_search->getSearchArray(), empty($modSettings['search_method']));
		}
		else
		{
			$bodyParser = new Normal($this->_search->getSearchArray(), empty($modSettings['search_method']));
		}

		$opt = new ValuesContainer([
			'icon_sources' => $this->_icon_sources,
			'show_signatures' => false,
			'boards_can' => $boards_can ?? [],
		]);
		$renderer = new SearchRenderer($messages_request, $this->user, $bodyParser, $opt);
		$renderer->setParticipants($this->_participants);

		$context['topic_starter_id'] = 0;
		$context['get_topics'] = [$renderer, 'getContext'];

		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => addslashes(un_htmlspecialchars($txt['select_destination'])),
		);

		loadJavascriptFile('topic.js');
		$this->buildQuickModerationButtons();
	}

	/**
	 * Show an anti spam verification control
	 */
	protected function _controlVerifications()
	{
		global $modSettings, $context;

		// Do we have captcha enabled?
		if ($this->user->is_guest && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] !== $this->_search->param('search')))
		{
			$verificationOptions = [
				'id' => 'search',
			];
			$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions, true);

			if (is_array($context['require_verification']))
			{
				foreach ($context['require_verification'] as $error)
				{
					$context['search_errors'][$error] = true;
				}
			}
			// Don't keep asking for it - they've proven themselves worthy.
			else
			{
				$_SESSION['ss_vv_passed'] = true;
			}
		}
	}

	protected function _prepareParticipants($participationEnabled, $user_id)
	{
		// If we want to know who participated in what then load this now.
		if ($participationEnabled === true && $user_id !== 0)
		{
			$this->_participants = $this->_search->getParticipants();

			require_once(SUBSDIR . '/MessageIndex.subs.php');
			$topics_participated_in = topicsParticipation($user_id, array_keys($this->_participants));

			foreach ($topics_participated_in as $topic)
			{
				$this->_participants[$topic['id_topic']] = true;
			}
		}
	}

	/**
	 * Loads into $context the moderation button array for template use.
	 * Call integrate_message_index_mod_buttons hook
	 */
	protected function buildQuickModerationButtons()
	{
		global $context;

		// Build the mod button array with buttons that are valid for, at least some, of the messages
		$context['mod_buttons'] = [
			'move' => [
				'test' => 'can_move',
				'text' => 'move_topic',
				'id' => 'move',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'remove' => [
				'test' => 'can_remove',
				'text' => 'remove_topic',
				'id' => 'remove',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'lock' => [
				'test' => 'can_lock',
				'text' => 'set_lock',
				'id' => 'lock',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'sticky' => [
				'test' => 'can_sticky',
				'text' => 'set_sticky',
				'id' => 'sticky',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'markread' => [
				'test' => 'can_markread',
				'text' => 'mark_read_short',
				'id' => 'markread',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
		];

		// Allow adding new buttons easily.
		call_integration_hook('integrate_search_quickmod_buttons');

		$context['mod_buttons'] = array_reverse($context['mod_buttons']);
	}
}
