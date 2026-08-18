<?php

/**
 * This file is meant for controlling the search actions related to personal messages.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\PersonalMessage;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;

/**
 * Class Search
 * It allows searching in personal messages
 *
 * @package ElkArte\PersonalMessage
 */
class Search extends AbstractController
{
	/**
	 * @var array $_search_params Will carry all settings that differ from the default.
	 * That way, the URLs involved in a search page will be kept as short as possible.
	 */
	private $_search_params = [];

	/** @var array $_searchq_parameters will carry all the values needed by S_search_params */
	private $_searchq_parameters = [];

	/**
	 * Default action for the class
	 */
	public function action_index()
	{
		$this->action_search();
	}

	/**
	 * Actually do the search of personal messages and show the results
	 *
	 * What it does:
	 *
	 * - Accessed with ?action=pm;sa=search2
	 * - Checks user input and searches the pm table for messages matching the query.
	 * - Uses the search_results sub template of the PersonalMessage template.
	 * - Show the results of the search query.
	 */
	public function action_search2(): ?bool
	{
		global $scripturl, $modSettings, $context, $txt;

		$context['display_mode'] = PmHelper::getDisplayMode();

		// Make sure the server is able to do this right now
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
		{
			throw new Exception('loadavg_search_disabled', false);
		}

		// Some useful general permissions.
		$context['can_send_pm'] = allowedTo('pm_send');

		// Extract all the search parameters if coming in from pagination, etc.
		$this->_searchParamsFromString();

		// Set a start for pagination
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);

		// Set/clean search criteria
		$this->_prepareSearchParams();

		$context['folder'] = empty($this->_search_params['sent_only']) ? 'inbox' : 'sent';

		// Searching for specific members
		$userQuery = $this->_setUserQuery();

		// Set up the sorting variables...
		$this->_setSortParams();

		// Sort out any labels we may be searching for.
		$labelQuery = $this->_setLabelQuery();

		// Unfortunately, searching for words like this is going to be slow, so we're blocking them.
		$blocklist_words = ['quote', 'the', 'is', 'it', 'are', 'if', 'in'];

		// What are we actually searching for?
		if (empty($this->_search_params['search']))
		{
			$this->_search_params['search'] = $this->_req->getPost('search', 'trim|strval', '');
		}

		// If nothing is left to search on - we set an error!
		if (!isset($this->_search_params['search']) || $this->_search_params['search'] === '')
		{
			$context['search_errors']['invalid_search_string'] = true;
		}

		// Change non-word characters into spaces.
		$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $this->_search_params['search']);

		// Make the query lower case since it will case-insensitive anyway.
		$stripped_query = un_htmlspecialchars(Util::strtolower($stripped_query));

		// Extract phrase parts first (e.g., some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $this->_search_params['search']);
		$wordArray = explode(' ', Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

		// A minus sign in front of a word excludes the word.... so...
		$excludedWords = [];

		// Check for things like -"some words", but not "-some words".
		foreach ($matches[1] as $index => $word)
		{
			if ($word === '-')
			{
				if (($word = trim($phraseArray[$index], "-_' ")) !== '' && !in_array($word, $blocklist_words))
				{
					$excludedWords[] = $word;
				}

				unset($phraseArray[$index]);
			}
		}

		// Now we look for -test, etc.
		foreach ($wordArray as $index => $word)
		{
			if (str_starts_with(trim($word), '-'))
			{
				if (($word = trim($word, "-_' ")) !== '' && !in_array($word, $blocklist_words))
				{
					$excludedWords[] = $word;
				}

				unset($wordArray[$index]);
			}
		}

		// The remaining words and phrases are all included.
		$searchArray = array_merge($phraseArray, $wordArray);

		// Trim everything and make sure there are no words that are the same.
		foreach ($searchArray as $index => $value)
		{
			// Skip anything that's close to empty.
			if (($searchArray[$index] = trim($value, "-_' ")) === '')
			{
				unset($searchArray[$index]);
			}
			// Skip blocked words. Make sure to note we skipped them as well
			elseif (in_array($searchArray[$index], $blocklist_words))
			{
				$foundBlockListedWords = true;
				unset($searchArray[$index]);

			}

			if (isset($searchArray[$index]))
			{
				$searchArray[$index] = Util::strtolower(trim($value));

				if ($searchArray[$index] === '')
				{
					unset($searchArray[$index]);
				}
				else
				{
					// Sort out entities first.
					$searchArray[$index] = Util::htmlspecialchars($searchArray[$index]);
				}
			}
		}

		$searchArray = array_slice(array_unique($searchArray), 0, 10);

		// This contains *everything*
		$searchWords = array_merge($searchArray, $excludedWords);

		// Make sure at least one word is being searched for.
		if (empty($searchArray))
		{
			$context['search_errors']['invalid_search_string' . (empty($foundBlockListedWords) ? '' : '_blocklist')] = true;
		}

		// Sort out the search query so the user can edit it - if they want.
		$context['search_params'] = $this->_search_params;
		if (isset($context['search_params']['search']))
		{
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		}

		if (isset($context['search_params']['userspec']))
		{
			$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);
		}

		// Now we have all the parameters, combine them together for pagination and the like...
		$context['params'] = $this->_compileURLparams();

		// Compile the subject query part.
		$andQueryParts = [];
		foreach ($searchWords as $index => $word)
		{
			if ($word === '')
			{
				continue;
			}

			if ($this->_search_params['subject_only'])
			{
				$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
			}
			else
			{
				$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
			}

			$this->_searchq_parameters ['search_' . $index] = '%' . strtr($word, ['_' => '\\_', '%' => '\\%']) . '%';
		}

		$searchQuery = ' 1=1';
		if (!empty($andQueryParts))
		{
			$searchQuery = implode(!empty($this->_search_params['searchtype']) && $this->_search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);
		}

		// Age limits?
		$timeQuery = '';
		if (!empty($this->_search_params['minage']))
		{
			$timeQuery .= ' AND pm.msgtime < ' . (time() - $this->_search_params['minage'] * 86400);
		}

		if (!empty($this->_search_params['maxage']))
		{
			$timeQuery .= ' AND pm.msgtime > ' . (time() - $this->_search_params['maxage'] * 86400);
		}

		// If we have errors - return back to the first screen...
		if (!empty($context['search_errors']))
		{
			$this->_req->post->params = $context['params'];

			$this->action_search();

			return false;
		}

		// Get the number of results.
		$numResults = numPMSeachResults($userQuery, $labelQuery, $timeQuery, $searchQuery, $this->_searchq_parameters);

		// Get all the matching message ids, senders and head pm nodes
		[$foundMessages, $posters, $head_pms] = loadPMSearchMessages($userQuery, $labelQuery, $timeQuery, $searchQuery, $this->_searchq_parameters, $this->_search_params);

		// Find the real head pm when in the conversation view
		if ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION && !empty($head_pms))
		{
			$real_pm_ids = loadPMSearchHeads($head_pms);
		}

		// Load the found user data
		$posters = array_unique($posters);
		if (!empty($posters))
		{
			MembersList::load($posters);
		}

		// Sort out the page index.
		$context['page_index'] = constructPageIndex('{scripturl}?action=pm;sa=search2;params=' . $context['params'], $context['start'], $numResults, $modSettings['search_results_per_page']);

		$context['message_labels'] = [];
		$context['message_replied'] = [];
		$context['personal_messages'] = [];
		$context['first_label'] = [];

		// If we have results, we have work to do!
		if (!empty($foundMessages))
		{
			$recipients = [];
			[$context['message_labels'], $context['message_replied'], $context['message_unread'], $context['first_label']] = loadPMRecipientInfo($foundMessages, $recipients, $context['folder'], true);

			// Prepare for the callback!
			$search_results = loadPMSearchResults($foundMessages, $this->_search_params);
			$counter = 0;
			$bbc_parser = ParserWrapper::instance();
			foreach ($search_results as $row)
			{
				// If there's no subject, use the default.
				$row['subject'] = $row['subject'] === '' ? $txt['no_subject'] : $row['subject'];

				// Load this poster context info, if not there, then fill in the essentials...
				$member = MembersList::get($row['id_member_from']);
				$member->loadContext();
				if ($member->isEmpty())
				{
					$member['name'] = $row['from_name'];
					$member['id'] = 0;
					$member['group'] = $txt['guest_title'];
					$member['link'] = $row['from_name'];
					$member['email'] = '';
					$member['show_email'] = showEmailAddress(0);
					$member['is_guest'] = true;
				}

				// Censor anything we don't want to see...
				$row['body'] = censor($row['body']);
				$row['subject'] = censor($row['subject']);

				// Parse out any BBC...
				$row['body'] = $bbc_parser->parsePM($row['body']);

				// Highlight the hits
				$body_highlighted = '';
				$subject_highlighted = '';
				foreach ($searchArray as $query)
				{
					// Fix the international characters in the keyword too.
					$query = un_htmlspecialchars($query);
					$query = trim($query, '\*+');
					$query = strtr(Util::htmlspecialchars($query), ['\\\'' => "'"]);

					$body_highlighted = preg_replace_callback('/((<[^>]*)|' . preg_quote(strtr($query, ["'" => '&#039;']), '/') . ')/iu',
						fn($matches) => $this->_highlighted_callback($matches), $row['body']);
					$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $row['subject']);
				}

				// Set a link using the first label information
				$href = $scripturl . '?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION && isset($real_pm_ids[$head_pms[$row['id_pm']]]) && $context['folder'] === 'inbox' ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg_' . $row['id_pm'];

				$context['personal_messages'][] = [
					'id' => $row['id_pm'],
					'member' => $member,
					'subject' => $subject_highlighted,
					'body' => $body_highlighted,
					'time' => standardTime($row['msgtime']),
					'html_time' => htmlTime($row['msgtime']),
					'timestamp' => forum_time(true, $row['msgtime']),
					'recipients' => &$recipients[$row['id_pm']],
					'labels' => &$context['message_labels'][$row['id_pm']],
					'fully_labeled' => (empty($context['message_labels'][$row['id_pm']]) ? 0 : count($context['message_labels'][$row['id_pm']])) === count($context['labels']),
					'is_replied_to' => &$context['message_replied'][$row['id_pm']],
					'href' => $href,
					'link' => '<a href="' . $href . '">' . $subject_highlighted . '</a>',
					'counter' => ++$counter,
					'pmbuttons' => $this->_setSearchPmButtons($row['id_pm'], $member),
				];
			}
		}

		// Finish off the context.
		$context['page_title'] = $txt['pm_search_title'];
		$context['sub_template'] = 'search_results';
		$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'search';
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'search']),
			'name' => $txt['pm_search_bar_title'],
		];

		return true;
	}

	/**
	 * Return buttons for search results, used when viewing full message as result
	 *
	 * @param int $id of the PM
	 * @param ValuesContainer $member member information
	 * @return array[]
	 */
	private function _setSearchPmButtons($id, $member): array
	{
		global $context;

		$pmButtons = [
			// Reply, Quote
			'reply_button' => [
				'text' => 'reply',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $id, 'u' => $member['id']]) . ($context['current_label_id'] !== "-1" ? ';l=' . $context['current_label_id'] : ''),
				'class' => 'reply_button',
				'icon' => 'modify',
				'enabled' => !$member['is_guest'] && $context['can_send_pm'],
			],
			'quote_button' => [
				'text' => 'quote',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $id, 'quote' => '']) . ($context['current_label_id'] !== "-1" ? ';l=' . $context['current_label_id'] : '') . ($context['folder'] === 'sent' ? '' : ';u=' . $member['id']),
				'class' => 'quote_button',
				'icon' => 'quote',
				'enabled' => !$member['is_guest'] && $context['can_send_pm'],
			],
			// This is for "forwarding" - even if the member is gone.
			'reply_quote_button' => [
				'text' => 'reply_quote',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $id, 'quote' => '']) . ($context['current_label_id'] !== "-1" ? ';l=' . $context['current_label_id'] : ''),
				'class' => 'reply_button',
				'icon' => 'modify',
				'enabled' => $member['is_guest'] && $context['can_send_pm'],
			]
		];

		// Drop any non-enabled ones
		return array_filter($pmButtons, static fn($button) => !isset($button['enabled']) || (bool) $button['enabled']);
	}

	/**
	 * Extract search params from a string
	 *
	 * What it does:
	 *
	 * - When paging search results, reads and decodes the passed parameters
	 * - Places what it finds back in search_params
	 */
	private function _searchParamsFromString(): array
	{
		$this->_search_params = [];

		// Read encoded params from either GET or POST using helper
		$temp_params = $this->_req->getRequest('params', 'trim|strval');
		if ($temp_params !== null && $temp_params !== '')
		{
			// Decode and replace the uri safe characters we added
			$temp_params = base64_decode(str_replace(['-', '_', '.'], ['+', '/', '='], $temp_params));

			$temp_params = explode('|"|', $temp_params);
			foreach ($temp_params as $data)
			{
				[$k, $v] = array_pad(explode("|'|", $data), 2, '');
				$this->_search_params[$k] = $v;
			}
		}

		return $this->_search_params;
	}

	/**
	 * Sets the search params for the query
	 *
	 * What it does:
	 *
	 * - Uses existing ones if coming from pagination or uses those passed from the search pm form
	 * - Validates passed params are valid
	 */
	private function _prepareSearchParams(): void
	{
		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($this->_search_params['advanced']))
		{
			$this->_search_params['advanced'] = $this->_req->hasPost('advanced') ? 1 : 0;
		}

		// 1 => 'allwords' (default, don't set as param), 2 => 'anywords'.
		$searchtypePost = $this->_req->getPost('searchtype', 'intval', 1);
		if (!empty($this->_search_params['searchtype']) || $searchtypePost == 2)
		{
			$this->_search_params['searchtype'] = 2;
		}

		// Minimum age of messages. Default to zero (don't set param in that case).
		$minagePost = $this->_req->getPost('minage', 'intval', 0);
		if (!empty($this->_search_params['minage']) || ($minagePost > 0))
		{
			$this->_search_params['minage'] = empty($this->_search_params['minage']) ? $minagePost : (int) $this->_search_params['minage'];
		}

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		$maxagePost = $this->_req->getPost('maxage', 'intval', 9999);
		if (!empty($this->_search_params['maxage']) || ($maxagePost < 9999))
		{
			$this->_search_params['maxage'] = empty($this->_search_params['maxage']) ? $maxagePost : (int) $this->_search_params['maxage'];
		}

		// Default the username to a wildcard matching every user (*).
		$userspecPost = $this->_req->getPost('userspec', 'trim|strval', '*');
		if (!empty($this->_search_params['userspec']) || ($userspecPost !== '*'))
		{
			$this->_search_params['userspec'] = $this->_search_params['userspec'] ?? $userspecPost;
		}

		// Search modifiers
		$this->_search_params['subject_only'] = !empty($this->_search_params['subject_only']) || $this->_req->hasPost('subject_only');
		$this->_search_params['show_complete'] = !empty($this->_search_params['show_complete']) || $this->_req->hasPost('show_complete');
		$this->_search_params['sent_only'] = !empty($this->_search_params['sent_only']) || $this->_req->hasPost('sent_only');
	}

	/**
	 * Handles the parameters when searching for specific users
	 *
	 * What it does:
	 *
	 * - Returns the user query for use in the main search query
	 * - Sets the parameters for use in the query
	 *
	 * @return string
	 */
	private function _setUserQuery(): string
	{
		global $context;

		// Hardcoded variables that can be tweaked if required.
		$maxMembersToSearch = 500;

		// Init to not be searching based on members
		$userQuery = '';

		// If there's no specific user, then don't mention it in the main query.
		if (!empty($this->_search_params['userspec']))
		{
			// Set up, so we can search by username, wildcards, like, etc.
			$userString = strtr(Util::htmlspecialchars($this->_search_params['userspec'], ENT_QUOTES), ['&quot;' => '"']);
			$userString = strtr($userString, ['%' => '\%', '_' => '\_', '*' => '%', '?' => '_']);

			preg_match_all('~"([^"]+)"~', $userString, $matches);
			$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

			// Who matches those criteria?
			require_once(SUBSDIR . '/Members.subs.php');
			$members = membersBy('member_names', ['member_names' => $possible_users]);

			foreach ($possible_users as $key => $possible_user)
			{
				$this->_searchq_parameters['guest_user_name_implode_' . $key] = '{string_case_insensitive:' . $possible_user . '}';
			}

			// Simply do nothing if there are too many members matching the criteria.
			if (count($members) > $maxMembersToSearch)
			{
				$userQuery = '';
			}
			elseif (count($members) === 0)
			{
				if ($context['folder'] === 'inbox')
				{
					$uq = [];
					$name = '{column_case_insensitive:pm.from_name}';
					foreach (array_keys($possible_users) as $key)
					{
						$uq[] = 'AND pm.id_member_from = 0 AND (' . $name . ' LIKE {string:guest_user_name_implode_' . $key . '})';
					}

					$userQuery = implode(' ', $uq);
					$this->_searchq_parameters['pm_from_name'] = $name;
				}
				else
				{
					$userQuery = '';
				}
			}
			else
			{
				$memberlist = [];
				foreach ($members as $id)
				{
					$memberlist[] = $id;
				}

				// Use the name as sent from or sent to
				if ($context['folder'] === 'inbox')
				{
					$uq = [];
					$name = '{column_case_insensitive:pm.from_name}';

					foreach (array_keys($possible_users) as $key)
					{
						$uq[] = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND (' . $name . ' LIKE {string:guest_user_name_implode_' . $key . '})))';
					}

					$userQuery = implode(' ', $uq);
				}
				else
				{
					$userQuery = 'AND (pmr.id_member IN ({array_int:member_list}))';
				}

				$this->_searchq_parameters['pm_from_name'] = '{column_case_insensitive:pm.from_name}';
				$this->_searchq_parameters['member_list'] = $memberlist;
			}
		}

		return $userQuery;
	}

	/**
	 * Read / Set the sort parameters for the results listing
	 */
	private function _setSortParams(): void
	{
		$sort_columns = [
			'pm.id_pm',
		];

		if (empty($this->_search_params['sort']) && !empty($this->_req->post->sort))
		{
			[$this->_search_params['sort'], $this->_search_params['sort_dir']] = array_pad(explode('|', $this->_req->post->sort), 2, '');
		}

		$this->_search_params['sort'] = !empty($this->_search_params['sort']) && in_array($this->_search_params['sort'], $sort_columns) ? $this->_search_params['sort'] : 'pm.id_pm';
		$this->_search_params['sort_dir'] = !empty($this->_search_params['sort_dir']) && $this->_search_params['sort_dir'] === 'asc' ? 'asc' : 'desc';
	}

	/**
	 * Handles the parameters when searching for specific labels
	 *
	 * What it does:
	 *
	 * - Returns the label query for use in the main search query
	 * - Sets the parameters for use in the query
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function _setLabelQuery(): string
	{
		global $context;

		$db = database();

		$labelQuery = '';

		if ($context['folder'] === 'inbox' && !empty($this->_search_params['advanced']) && $context['currently_using_labels'])
		{
			// Came here from pagination?  Put them back into $_REQUEST for sanitation.
			if (isset($this->_search_params['labels']))
			{
				$this->_req->post->searchlabel = explode(',', $this->_search_params['labels']);
			}

			// Assuming we have some labels - make them all integers.
			if (!empty($this->_req->post->searchlabel) && is_array($this->_req->post->searchlabel))
			{
				$this->_req->post->searchlabel = array_map('intval', $this->_req->post->searchlabel);
			}
			else
			{
				$this->_req->post->searchlabel = [];
			}

			// Now that everything is cleaned up a bit, make the labels a param.
			$this->_search_params['labels'] = implode(',', $this->_req->post->searchlabel);

			// No labels selected? That must be an error!
			if (empty($this->_req->post->searchlabel))
			{
				$context['search_errors']['no_labels_selected'] = true;
			}
			// Otherwise prepare the query!
			elseif (count($this->_req->post->searchlabel) !== count($context['labels']))
			{
				$labelQuery = '
				AND {raw:label_implode}';

				$labelStatements = [];
				foreach ($this->_req->post->searchlabel as $label)
				{
					$labelStatements[] = $db->quote('FIND_IN_SET({string:label}, pmr.labels) != 0', ['label' => $label,]);
				}

				$this->_searchq_parameters ['label_implode'] = '(' . implode(' OR ', $labelStatements) . ')';
			}
		}

		return $labelQuery;
	}

	/**
	 * Encodes search params in a URL-compatible way
	 *
	 * @return string - the encoded string to be appended to the URL
	 */
	private function _compileURLparams(): string
	{
		$encoded = [];

		// Now we have all the parameters, combine them together for pagination and the like...
		foreach ($this->_search_params as $k => $v)
		{
			$encoded[] = $k . "|'|" . $v;
		}

		// Base64 encode, then replace +/= with uri safe ones that can be reverted
		return str_replace(['+', '/', '='], ['-', '_', '.'], base64_encode(implode('|"|', $encoded)));
	}

	/**
	 * Allows searching personal messages.
	 *
	 * What it does:
	 *
	 * - Accessed with ?action=pm;sa=search
	 * - Shows the screen to search PMs (?action=pm;sa=search)
	 * - Uses the search sub template of the PersonalMessage template.
	 * - Decodes and loads search parameters given in the URL (if any).
	 * - The form redirects to index.php?action=pm;sa=search2.
	 *
	 * @uses search sub template
	 */
	public function action_search(): void
	{
		global $context, $txt;

		$context['display_mode'] = PmHelper::getDisplayMode();

		// If they provided some search parameters, we need to extract them
		if ($this->_req->hasPost('params'))
		{
			$context['search_params'] = $this->_searchParamsFromString();
		}

		// Set up the search criteria, type, what, age, etc.
		if ($this->_req->hasPost('search'))
		{
			$context['search_params']['search'] = un_htmlspecialchars($this->_req->getPost('search', 'trim', ''));
			$context['search_params']['search'] = htmlspecialchars($context['search_params']['search'], ENT_COMPAT);
		}

		if (isset($context['search_params']['userspec']))
		{
			$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec'], ENT_COMPAT);
		}

		// 1 => 'allwords' / 2 => 'anywords'.
		if (!empty($context['search_params']['searchtype']))
		{
			$context['search_params']['searchtype'] = 2;
		}

		// Minimum and Maximum age of the message
		if (!empty($context['search_params']['minage']))
		{
			$context['search_params']['minage'] = (int) $context['search_params']['minage'];
		}

		if (!empty($context['search_params']['maxage']))
		{
			$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];
		}

		$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
		$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

		// Create the array of labels to be searched.
		$context['search_labels'] = [];
		$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : [];
		foreach ($context['labels'] as $label)
		{
			$context['search_labels'][] = [
				'id' => $label['id'],
				'name' => $label['name'],
				'checked' => empty($searchedLabels) || in_array($label['id'], $searchedLabels),
			];
		}

		// Are all the labels checked?
		$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) === count($searchedLabels);

		// Load the error text strings if there were errors in the search.
		if (!empty($context['search_errors']))
		{
			Txt::load('Errors');
			$context['search_errors']['messages'] = [];
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error === 'messages')
				{
					continue;
				}

				$context['search_errors']['messages'][] = $txt['error_' . $search_error] ?? ($txt[$search_error] ?? $search_error);
			}
		}

		$context['page_title'] = $txt['pm_search_title'];
		$context['sub_template'] = 'search';
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'search']),
			'name' => $txt['pm_search_bar_title'],
		];
	}

	/**
	 * Used to highlight body text with strings that match the search term
	 *
	 * - Callback function used in $body_highlighted
	 *
	 * @param string[] $matches
	 *
	 * @return string
	 */
	public function _highlighted_callback($matches): string
	{
		return isset($matches[2]) && $matches[2] === $matches[1] ? stripslashes($matches[1]) : '<strong class="highlight">' . $matches[1] . '</strong>';
	}
}
