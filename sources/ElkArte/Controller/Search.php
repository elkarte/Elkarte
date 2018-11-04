<?php

/**
 * Handle all of the searching from here.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Handle all of the searching for the site
 *
 * @package Search
 */
class Search extends \ElkArte\AbstractController
{
	/**
	 * Holds the search object
	 * @var \ElkArte\Search\Search
	 */
	protected $_search = null;

	/**
	 * The class that takes care of rendering the message icons (MessageTopicIcons)
	 * @var null|MessageTopicIcons
	 */
	protected $_icon_sources = null;

	/**
	 *
	 * @var mixed[]
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
		global $modSettings, $scripturl;

		// Coming from quick search box and going to some custom place?
		if (isset($_REQUEST['search_selection']) && !empty($modSettings['additional_search_engines']))
		{
			$engines = prepareSearchEngines();
			if (isset($engines[$_REQUEST['search_selection']]))
			{
				$engine = $engines[$_REQUEST['search_selection']];
				redirectexit($engine['url'] . urlencode(implode($engine['separator'], explode(' ', $_REQUEST['search']))));
			}
		}

		// If coming from the quick search box, and we want to search on members, well we need to do that ;)
		if (isset($_REQUEST['search_selection']) && $_REQUEST['search_selection'] === 'members')
		{
			redirectexit('action=memberlist;sa=search;fields=name,email;search=' . urlencode($_REQUEST['search']));
		}

		// If load management is on and the load is high, no need to even show the form.
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
		{
			throw new Elk_Exception('loadavg_search_disabled', false);
		}
		Elk_Autoloader::instance()->register(SUBSDIR . '/Search', '\\ElkArte\\Search');
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
	 */
	public function action_search()
	{
		global $txt, $scripturl, $modSettings, $user_info, $context;

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
			throw new Elk_Exception('loadavg_search_disabled', false);

		theme()->getTemplates()->loadLanguageFile('Search');

		// Don't load this in XML mode.
		if (!isset($_REQUEST['xml']))
		{
			theme()->getTemplates()->load('Search');
			$context['sub_template'] = 'searchform';
			loadJavascriptFile('suggest.js', array('defer' => true));
		}

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
			// Build a verification control for the form
			$verificationOptions = array(
				'id' => 'search',
			);

			$context['require_verification'] = VerificationControls_Integrate::create($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		// If you got back from search;sa=results by using the linktree, you get your original search parameters back.
		if ($this->_search === null && isset($_REQUEST['params']))
		{
			$search_params = new \ElkArte\Search\SearchParams($_REQUEST['params'] ?? '');

			$context['search_params'] = $search_params->get();
		}

		if (isset($_REQUEST['search']))
			$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);
		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec'], ENT_COMPAT, 'UTF-8');
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
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['search_errors']['messages'] = array();
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error === 'messages')
					continue;

				if ($search_error === 'string_too_long')
					$txt['error_string_too_long'] = sprintf($txt['error_string_too_long'], $context['search_string_limit']);

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

			require_once(SUBSDIR . '/Topic.subs.php');
			$context['search_topic']['subject'] = getSubject($context['search_params']['topic']);
			$context['search_topic']['link'] = '<a href="' . $context['search_topic']['href'] . '">' . $context['search_topic']['subject'] . '</a>';
		}

		$context['page_title'] = $txt['set_parameters'];
		$context['search_params'] = $this->_fill_default_search_params($context['search_params']);

		// Start guest off collapsed
		if ($context['user']['is_guest'] && !isset($context['minmax_preferences']['asearch']))
			$context['minmax_preferences']['asearch'] = 1;

		call_integration_hook('integrate_search');
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
		global $scripturl, $modSettings, $txt, $settings;
		global $user_info, $context, $options, $messages_request, $boards_can;

		// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
		stop_prefetching();

		// These vars don't require an interface, they're just here for tweaking.
		$recentPercentage = 0.30;
		// Message length used to tweak messages relevance of the results
		$humungousTopicPosts = 200;
		$maxMembersToSearch = 500;
		// Maximum number of results
		$maxMessageResults = empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] * 5;

		// Start with no errors.
		$context['search_errors'] = array();

		// Number of pages hard maximum - normally not set at all.
		$modSettings['search_max_results'] = empty($modSettings['search_max_results']) ? 200 * $modSettings['search_results_per_page'] : (int) $modSettings['search_max_results'];

		// Maximum length of the string.
		$context['search_string_limit'] = 100;

		theme()->getTemplates()->loadLanguageFile('Search');
		if (!isset($_REQUEST['xml']))
			theme()->getTemplates()->load('Search');
		// If we're doing XML we need to use the results template regardless really.
		else
			$context['sub_template'] = 'results';

		// Are you allowed?
		isAllowedTo('search_posts');

		$this->_search = new \ElkArte\Search\Search();
		$this->_search->setWeights(new \ElkArte\Search\WeightFactors($modSettings, $user_info['is_admin']));
		$search_params = new \ElkArte\Search\SearchParams($_REQUEST['params'] ?? '');
		$search_params->merge($_REQUEST, $recentPercentage, $maxMembersToSearch);
		$this->_search->setParams($search_params, !empty($modSettings['search_simple_fulltext']));

		$context['compact'] = $this->_search->isCompact();

		// Nothing??
		if ($this->_search->param('search') === false || $this->_search->param('search') === '')
			$context['search_errors']['invalid_search_string'] = true;
		// Too long?
		elseif (Util::strlen($this->_search->param('search')) > $context['search_string_limit'])
			$context['search_errors']['string_too_long'] = true;

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
				$context['search_errors']['search_string_small_words'] = true;
			else
				$context['search_errors']['invalid_search_string' . ($this->_search->foundBlackListedWords() ? '_blacklist' : '')] = true;

			// Don't allow duplicate error messages if one string is too short.
			if (isset($context['search_errors']['search_string_small_words'], $context['search_errors']['invalid_search_string']))
				unset($context['search_errors']['invalid_search_string']);
		}

		// *** Spell checking?
		if (!empty($modSettings['enableSpellChecking']) && function_exists('pspell_new'))
		{
			$context['did_you_mean'] = '';
			$context['did_you_mean_params'] = '';
			// @todo maybe move the html to a $settings
			$this->loadSuggestions($context['did_you_mean'], $context['did_you_mean_params'], '<em><strong>{word}</strong></em>');
		}

		// Let the user adjust the search query, should they wish?
		$context['search_params'] = (array) $this->_search->getSearchParams(true);
		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);
		if (empty($context['search_params']['minage']))
			$context['search_params']['minage'] = 0;
		if (empty($context['search_params']['maxage']))
			$context['search_params']['maxage'] = 9999;

		$context['search_params'] = $this->_fill_default_search_params($context['search_params']);

		$this->_controlVerifications();

		$context['params'] = $this->_search->compileURLparams();

		// ... and add the links to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=search;params=' . $context['params'],
			'name' => $txt['search']
		);

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=search;sa=results;params=' . $context['params'],
			'name' => $txt['search_results']
		);

		// Start guest off collapsed
		if ($context['user']['is_guest'] && !isset($context['minmax_preferences']['asearch']))
			$context['minmax_preferences']['asearch'] = 1;

		// *** A last error check
		call_integration_hook('integrate_search_errors');

		// One or more search errors? Go back to the first search screen.
		if (!empty($context['search_errors']))
			return $this->action_search();

		// Spam me not, Spam-a-lot?
		if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $this->_search->param('search'))
			spamProtection('search');

		// Store the last search string to allow pages of results to be browsed.
		$_SESSION['last_ss'] = $this->_search->param('search');

		try
		{
			$search_config = new \ElkArte\ValuesContainer(array(
				'humungousTopicPosts' => $humungousTopicPosts,
				'maxMessageResults' => $maxMessageResults,
				'search_index' => !empty($modSettings['search_index']) ? $modSettings['search_index'] : '',
				'banned_words' => empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']),
			));
			$context['topics'] = $this->_search->searchQuery(
				new \ElkArte\Search\SearchApiWrapper($search_config, $this->_search->getSearchParams())
			);
		}
		catch (\Exception $e)
		{
			$context['search_errors'][$e->getMessage()] = true;
			return $this->action_search();
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
				loadMemberData(array_unique($posters));

			// Get the messages out for the callback - select enough that it can be made to look just like Display.
			$messages_request = $this->_search->loadMessagesRequest($msg_list, count($context['topics']));

			// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
			if ($this->_search->noMessages($messages_request))
				$context['topics'] = array();

			$this->_prepareParticipants(!empty($modSettings['enableParticipation']), $user_info['is_guest'] ? $user_info['id'] : 0);
		}

		// Now that we know how many results to expect we can start calculating the page numbers.
		$context['page_index'] = constructPageIndex($scripturl . '?action=search;sa=results;params=' . $context['params'], $_REQUEST['start'], $this->_search->getNumResults(), $modSettings['search_results_per_page'], false);

		// Consider the search complete!
		Cache::instance()->remove('search_start:' . ($user_info['is_guest'] ? $user_info['ip'] : $user_info['id']));

		$context['sub_template'] = 'results';
		$context['page_title'] = $txt['search_results'];

		Elk_Autoloader::instance()->register(SUBSDIR . '/MessagesCallback', '\\ElkArte\\sources\\subs\\MessagesCallback');

		$this->_icon_sources = new MessageTopicIcons(!empty($modSettings['messageIconChecks_enable']), $settings['theme_dir']);

		// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
		// This will be called from the template.
		$bodyParser = new \ElkArte\sources\subs\MessagesCallback\BodyParser\Normal($this->_search->getSearchArray(), empty($modSettings['search_method']));
		$opt = new \ElkArte\ValuesContainer([
			'icon_sources' => $this->_icon_sources,
			'show_signatures' => false,
		]);
		$renderer = new \ElkArte\sources\subs\MessagesCallback\SearchRenderer($messages_request, $bodyParser, $opt);
		$renderer->setParticipants($this->_participants);

		$context['topic_starter_id'] = 0;
		$context['get_topics'] = array($renderer, 'getContext');

		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => addslashes(un_htmlspecialchars($txt['select_destination'])),
		);
	}

	protected function _controlVerifications()
	{
		global $user_info, $modSettings, $context;

		// Do we have captcha enabled?
		if ($user_info['is_guest'] && !empty($modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $this->_search->param('search')))
		{
			// If we come from another search box tone down the error...
			if (!isset($_REQUEST['search_vv']))
			{
				$context['search_errors']['need_verification_code'] = true;
			}
			else
			{
				$verificationOptions = array(
					'id' => 'search',
				);
				$context['require_verification'] = VerificationControls_Integrate::create($verificationOptions, true);

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
	}

	/**
	 * Setup spellchecking suggestions and load them into the two variable
	 * passed by ref
	 *
	 * @param string $suggestion_display - the string to display in the template
	 * @param string $suggestion_param - a param string to be used in a url
	 * @param string $display_highlight - a template to enclose in each suggested word
	 */
	protected function loadSuggestions(&$suggestion_display = '', &$suggestion_param = '', $display_highlight = '')
	{
		global $txt;

		// Windows fix.
		ob_start();
		$old = error_reporting(0);

		pspell_new('en');
		$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', 'utf-8', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		if (!$pspell_link)
		{
			$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);
		}

		error_reporting($old);
		@ob_end_clean();

		if (empty($pspell_link))
		{
			return;
		}

		$did_you_mean = array('search' => array(), 'display' => array());
		$found_misspelling = false;
		foreach ($this->_search->getSearchArray() as $word)
		{
			// Don't check phrases.
			if (preg_match('~^\w+$~', $word) === 0)
			{
				$did_you_mean['search'][] = '"' . $word . '"';
				$did_you_mean['display'][] = '&quot;' . \Util::htmlspecialchars($word) . '&quot;';
				continue;
			}
			// For some strange reason spell check can crash PHP on decimals.
			elseif (preg_match('~\d~', $word) === 1)
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = \Util::htmlspecialchars($word);
				continue;
			}
			elseif (pspell_check($pspell_link, $word))
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = \Util::htmlspecialchars($word);
				continue;
			}

			$suggestions = pspell_suggest($pspell_link, $word);
			foreach ($suggestions as $i => $s)
			{
				// Search is case insensitive.
				if (\Util::strtolower($s) == \Util::strtolower($word))
				{
					unset($suggestions[$i]);
				}
				// Plus, don't suggest something the user thinks is rude!
				elseif ($suggestions[$i] != censor($s))
				{
					unset($suggestions[$i]);
				}
			}

			// Anything found?  If so, correct it!
			if (!empty($suggestions))
			{
				$suggestions = array_values($suggestions);
				$did_you_mean['search'][] = $suggestions[0];
				$did_you_mean['display'][] = str_replace('{word}', \Util::htmlspecialchars($suggestions[0]), $display_highlight);
				$found_misspelling = true;
			}
			else
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = \Util::htmlspecialchars($word);
			}
		}

		if ($found_misspelling)
		{
			// Don't spell check excluded words, but add them still...
			$temp_excluded = array('search' => array(), 'display' => array());
			foreach ($this->_search->getExcludedWords() as $word)
			{
				if (preg_match('~^\w+$~', $word) == 0)
				{
					$temp_excluded['search'][] = '-"' . $word . '"';
					$temp_excluded['display'][] = '-&quot;' . \Util::htmlspecialchars($word) . '&quot;';
				}
				else
				{
					$temp_excluded['search'][] = '-' . $word;
					$temp_excluded['display'][] = '-' . \Util::htmlspecialchars($word);
				}
			}

			$did_you_mean['search'] = array_merge($did_you_mean['search'], $temp_excluded['search']);
			$did_you_mean['display'] = array_merge($did_you_mean['display'], $temp_excluded['display']);

			// Provide the potential correct spelling term in the param
			$suggestion_param = $this->_search->compileURLparams($did_you_mean['search']);
			$suggestion_display = implode(' ', $did_you_mean['display']);
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
				$this->_participants[$topic['id_topic']] = true;
		}
	}

	/**
	 * Fills the empty spaces in an array with the default values for search params
	 *
	 * @param mixed[] $array
	 *
	 * @return mixed[]
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
}
