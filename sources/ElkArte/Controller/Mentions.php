<?php

/**
 * Handles all the mentions actions so members are notified of mentionable actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\DataValidator;
use ElkArte\Exceptions\Exception;
use ElkArte\Mentions\Mentioning;
use ElkArte\Languages\Txt;

/**
 * as liking a post, adding a buddy, @ calling a member in a post
 *
 * @package Mentions
 */
class Mentions extends AbstractController
{
	/** @var array Will hold all available mention types */
	protected $_known_mentions = [];

	/** @var string The type of the mention we are looking at (if empty means all of them) */
	protected $_type = '';

	/** @var string The url of the display mentions button (all, unread, etc) */
	protected $_url_param = '';

	/** @var int Used for pagenation, keeps track of the current start point */
	protected $_page = 0;

	/** @var int Number of items per page */
	protected $_items_per_page = 20;

	/** @var string Default sorting column */
	protected $_default_sort = 'log_time';

	/** @var string User chosen sorting column */
	protected $_sort = '';

	/** @var string[] The sorting methods we know */
	protected $_known_sorting = [];

	/** @var bool Determine if we are looking only at unread mentions or any kind of */
	protected $_all = false;

	/**
	 * Good old constructor
	 *
	 * @param \ElkArte\EventManager $eventManager
	 */
	public function __construct($eventManager)
	{
		$this->_known_sorting = ['id_member_from', 'type', 'log_time'];

		parent::__construct($eventManager);
	}

	/**
	 * Set up the data for the mention based on what was requested
	 * This function is called before the flow is redirected to action_index().
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		// I'm not sure this is needed, though better have it. :P
		if (empty($modSettings['mentions_enabled']))
		{
			throw new Exception('no_access', false);
		}

		require_once(SUBSDIR . '/Mentions.subs.php');

		$this->_known_mentions = $this->_findMentionTypes();
	}

	/**
	 * Determines the enabled mention types.
	 *
	 * @return string[]
	 */
	protected function _findMentionTypes()
	{
		global $modSettings;

		if (empty($modSettings['enabled_mentions']))
		{
			return [];
		}

		return array_filter(array_unique(explode(',', $modSettings['enabled_mentions'])));
	}

	/**
	 * The default action is to show the list of mentions
	 * This allows ?action=mention to be forwarded to action_list()
	 */
	public function action_index()
	{
		if ($this->_req->getQuery('sa') === 'fetch')
		{
			$this->action_fetch();
		}
		else
		{
			// default action to execute
			$this->action_list();
		}
	}

	/**
	 * Fetches number of notifications and number of recently added ones for use
	 * in favicon and desktop notifications.
	 *
	 * @todo probably should be placed somewhere else.
	 */
	public function action_fetch()
	{
		global $context, $txt, $modSettings;

		if (empty($modSettings['usernotif_favicon_enable']) && empty($modSettings['usernotif_desktop_enable']))
		{
			die();
		}

		$template_layers = theme()->getLayers();
		$template_layers->removeAll();
		theme()->getTemplates()->load('Json');
		$context['sub_template'] = 'send_json';

		require_once(SUBSDIR . '/Mentions.subs.php');

		$lastsent = $this->_req->getQuery('lastsent', 'intval', 0);
		if (empty($lastsent) && !empty($_SESSION['notifications_lastseen']))
		{
			$lastsent = (int) $_SESSION['notifications_lastseen'];
		}

		// We only know AJAX for this particular action
		$context['json_data'] = [
			'timelast' => getTimeLastMention($this->user->id)
		];

		if (!empty($modSettings['usernotif_favicon_enable']))
		{
			$context['json_data']['mentions'] = (int) $this->user->mentions;
		}

		if (!empty($modSettings['usernotif_desktop_enable']))
		{
			$context['json_data']['desktop_notifications'] = [
				'new_from_last' => getNewMentions($this->user->id, $lastsent),
				'title' => sprintf($txt['forum_notification'], strip_tags(un_htmlspecialchars($context['forum_name']))),
				'link' => '/index.php?action=mentions',
			];
			$context['json_data']['desktop_notifications']['message'] = sprintf($txt[$lastsent === 0 ? 'unread_notifications' : 'new_from_last_notifications'], $context['json_data']['desktop_notifications']['new_from_last']);
		}

		$_SESSION['notifications_lastseen'] = $context['json_data']['timelast'];
	}

	/**
	 * Creates a list of mentions for the user
	 * Allows them to mark them read or unread
	 * Can sort the various forms of mentions, likes or @mentions
	 */
	public function action_list()
	{
		global $context, $txt, $scripturl;

		// Only registered members can be mentioned
		is_not_guest();

		require_once(SUBSDIR . '/Mentions.subs.php');
		Txt::load('Mentions');

		$this->_buildUrl();

		$list_options = [
			'id' => 'list_mentions',
			'title' => empty($this->_all) ? $txt['my_unread_mentions'] : $txt['my_mentions'],
			'items_per_page' => $this->_items_per_page,
			'base_href' => $scripturl . '?action=mentions;sa=list' . $this->_url_param,
			'default_sort_col' => $this->_default_sort,
			'default_sort_dir' => 'default',
			'no_items_label' => $this->_all ? $txt['no_mentions_yet'] : $txt['no_new_mentions'],
			'get_items' => [
				'function' => [$this, 'list_loadMentions'],
				'params' => [
					$this->_all,
					$this->_type,
				],
			],
			'get_count' => [
				'function' => [$this, 'list_getMentionCount'],
				'params' => [
					$this->_all,
					$this->_type,
				],
			],
			'columns' => [
				'id_member_from' => [
					'header' => [
						'value' => $txt['mentions_from'],
					],
					'data' => [
						'function' => function ($row) {
							global $settings;

							if (isset($settings['mentions']['mentioner_template']))
							{
								return str_replace(
									[
										'{avatar_img}',
										'{mem_url}',
										'{mem_name}',
									],
									[
										$row['avatar']['image'],
										!empty($row['id_member_from']) ? getUrl('action', ['action' => 'profile', 'u' => $row['id_member_from']]) : '#',
										$row['mentioner'],
									],
									$settings['mentions']['mentioner_template']);
							}

							return '';
						},
					],
					'sort' => [
						'default' => 'mtn.id_member_from',
						'reverse' => 'mtn.id_member_from DESC',
					],
				],
				'type' => [
					'header' => [
						'value' => $txt['mentions_what'],
					],
					'data' => [
						'db' => 'message',
					],
					'sort' => [
						'default' => 'mtn.mention_type',
						'reverse' => 'mtn.mention_type DESC',
					],
				],
				'log_time' => [
					'header' => [
						'value' => $txt['mentions_when'],
						'class' => 'mention_log_time',
					],
					'data' => [
						'db' => 'log_time',
						'timeformat' => 'html_time',
						'class' => 'mention_log_time',
					],
					'sort' => [
						'default' => 'mtn.log_time DESC',
						'reverse' => 'mtn.log_time',
					],
				],
				'action' => [
					'header' => [
						'value' => $txt['mentions_action'],
						'class' => 'listaction grid8',
					],
					'data' => [
						'function' => function ($row) {
							global $txt, $settings;

							$mark = empty($row['status']) ? 'read' : 'unread';
							$opts = '<a href="' . getUrl('action', ['action' => 'mentions', 'sa' => 'updatestatus', 'mark' => $mark, 'item' => $row['id_mention'], '{session_data}']) . '"><i class="icon i-mark_' . $mark . '" title="' . $txt['mentions_mark' . $mark] . '" /><s>' . $txt['mentions_mark' . $mark] . '</s></i></a>&nbsp;';

							return $opts . '<a href="' . getUrl('action', ['action' => 'mentions', 'sa' => 'updatestatus', 'mark' => 'delete', 'item' => $row['id_mention'], '{session_data}']) . '"><i class="icon i-remove" title="' . $txt['delete'] . '"><s>' . $txt['delete'] . '</s></i></a>';
						},
						'class' => 'listaction grid8',
					],
				],
			],
			'list_menu' => [
				'show_on' => 'top',
				'links' => [
					[
						'href' => getUrl('action', ['action' => 'mentions'] + (!empty($this->_all) ? ['all'] : [])),
						'is_selected' => empty($this->_type),
						'label' => $txt['mentions_type_all']
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'above_column_headers',
					'class' => 'flow_flex_right',
					'value' => '<a class="linkbutton" href="' . $scripturl . '?action=mentions' . (!empty($this->_all) ? '' : ';all') . str_replace(';all', '', $this->_url_param) . '">' . (!empty($this->_all) ? $txt['mentions_unread'] : $txt['mentions_all']) . '</a>',
				],
				[
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '<a class="linkbutton" href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=readall' . str_replace(';all', '', $this->_url_param) . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['mentions_mark_all_read'] . '</a>',
				],
			],
		];

		foreach ($this->_known_mentions as $mention)
		{
			$list_options['list_menu']['links'][] = [
				'href' => getUrl('action', ['action' => 'mentions', 'type' => $mention] + (!empty($this->_all) ? ['all'] : [])),
				'is_selected' => $this->_type === $mention,
				'label' => $txt['mentions_type_' . $mention]
			];
		}

		createList($list_options);

		$context['page_title'] = $txt['my_mentions'] . (!empty($this->_page) ? ' - ' . sprintf($txt['my_mentions_pages'], $this->_page) : '');
		$context['linktree'][] = [
			'url' => getUrl('action', ['action' => 'mentions']),
			'name' => $txt['my_mentions'],
		];

		if (!empty($this->_type))
		{
			$context['linktree'][] = [
				'url' => getUrl('action', ['action' => 'mentions', 'type' => $this->_type]),
				'name' => $txt['mentions_type_' . $this->_type],
			];
		}
	}

	/**
	 * Builds the link back so you return to the right list of mentions
	 */
	protected function _buildUrl()
	{
		$this->_all = $this->_req->getQuery('all') !== null;
		$this->_sort = in_array($this->_req->getQuery('sort', 'trim'), $this->_known_sorting, true) ? $this->_req->getQuery('sort', 'trim') : $this->_default_sort;
		$this->_type = in_array($this->_req->getQuery('type', 'trim'), $this->_known_mentions, true) ? $this->_req->getQuery('type', 'trim') : '';
		$this->_page = $this->_req->getQuery('start', 'trim', '');

		$this->_url_param = ($this->_all ? ';all' : '') . (!empty($this->_type) ? ';type=' . $this->_type : '') . ($this->_req->getQuery('start') !== null ? ';start=' . $this->_req->getQuery('start') : '');
	}

	/**
	 * Callback for createList(),
	 * Returns the number of mentions of $type that a member has
	 *
	 * @param bool $all : if true counts all the mentions, otherwise only the unread
	 * @param string $type : the type of mention
	 *
	 * @return mixed
	 */
	public function list_getMentionCount($all, $type)
	{
		return countUserMentions($all, $type);
	}

	/**
	 * Did you read the mention? Then let's move it to the graveyard.
	 * Used by Events registered to the prepare_context event of the Display controller
	 */
	public function action_markread()
	{
		global $modSettings;

		checkSession('request');

		$this->_buildUrl();

		$id_mention = $this->_req->getQuery('item', 'intval', 0);
		$mentioning = new Mentioning(database(), $this->user, new DataValidator(), $modSettings['enabled_mentions']);
		$mentioning->updateStatus($id_mention, 'read');
	}

	/**
	 * Updating the status from the listing?
	 */
	public function action_updatestatus()
	{
		global $modSettings;

		checkSession('request');

		$mentioning = new Mentioning(database(), $this->user, new DataValidator(), $modSettings['enabled_mentions']);

		$id_mention = $this->_req->getQuery('item', 'intval', 0);
		$mark = $this->_req->getQuery('mark');

		$this->_buildUrl();

		switch ($mark)
		{
			case 'read':
			case 'unread':
			case 'delete':
				$mentioning->updateStatus($id_mention, $mark);
				break;
			case 'readall':
				Txt::load('Mentions');
				$mentions = $this->list_loadMentions((int) $this->_page, $this->_items_per_page, $this->_sort, $this->_all, $this->_type);
				$mentioning->markread(array_column($mentions, 'id_mention'));
				break;
		}

		redirectexit('action=mentions;sa=list' . $this->_url_param);
	}

	/**
	 * Callback for createList(),
	 * Returns the mentions of a give type (like/mention) & (unread or all)
	 *
	 * @param int $start start list number
	 * @param int $limit how many to show on a page
	 * @param string $sort which direction are we showing this
	 * @param bool $all : if true load all the mentions or type, otherwise only the unread
	 * @param string $type : the type of mention
	 *
	 * @return array
	 */
	public function list_loadMentions($start, $limit, $sort, $all, $type)
	{
		$totalMentions = countUserMentions($all, $type);
		$mentions = [];
		$round = 0;
		Txt::load('Mentions');

		$this->_registerEvents($type);

		while ($round < 2)
		{
			$possible_mentions = getUserMentions($start, $limit, $sort, $all, $type);
			$count_possible = count($possible_mentions);

			$this->_events->trigger('view_mentions', [$type, &$possible_mentions]);

			foreach ($possible_mentions as $mention)
			{
				if (count($mentions) < $limit)
				{
					$mentions[] = $mention;
				}
				else
				{
					break;
				}
			}
			$round++;

			// If nothing has been removed OR there are not enough
			if (($totalMentions - $start < $limit) || count($mentions) !== $count_possible || count($mentions) === $limit)
			{
				break;
			}

			// Let's start a bit further into the list
			$start += $limit;
		}

		if ($round !== 0)
		{
			countUserMentions();
		}

		return $mentions;
	}

	/**
	 * Register the listeners for a mention type or for all the mentions.
	 *
	 * @param string|null $type Specific mention type
	 */
	protected function _registerEvents($type)
	{
		if (!empty($type))
		{
			$to_register = ['\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($type)];
		}
		else
		{
			$to_register = array_map(static function ($name) {
				return '\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($name);
			}, $this->_known_mentions);
		}

		$this->_registerEvent('view_mentions', 'view', $to_register);
	}
}
