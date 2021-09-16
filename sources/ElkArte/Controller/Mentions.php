<?php

/**
 * Handles all the mentions actions so members are notified of mentionable actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
<<<<<<< HEAD:sources/ElkArte/Controller/Mentions.php
 * @version 2.0 dev
=======
 * @version 1.1.8
>>>>>>> 5667d796a (! fix #3499):sources/controllers/Mentions.controller.php
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\DataValidator;
use ElkArte\Exceptions\Exception;
use ElkArte\Mentions\Mentioning;
use ElkArte\Themes\ThemeLoader;

/**
 * as liking a post, adding a buddy, @ calling a member in a post
 *
 * @package Mentions
 */
class Mentions extends AbstractController
{
	/**
	 * Will hold all available mention types
	 *
	 * @var array
	 */
	protected $_known_mentions = array();

	/**
	 * The type of the mention we are looking at (if empty means all of them)
	 *
	 * @var string
	 */
	protected $_type = '';

	/**
	 * The url of the display mentions button (all, unread, etc)
	 *
	 * @var string
	 */
	protected $_url_param = '';

	/**
	 * Used for pagenation, keeps track of the current start point
	 *
	 * @var int
	 */
	protected $_page = 0;

	/**
	 * Number of items per page
	 *
	 * @var int
	 */
	protected $_items_per_page = 20;

	/**
	 * Default sorting column
	 *
	 * @var string
	 */
	protected $_default_sort = 'log_time';

	/**
	 * User chosen sorting column
	 *
	 * @var string
	 */
	protected $_sort = '';

	/**
	 * The sorting methods we know
	 *
	 * @var string[]
	 */
	protected $_known_sorting = array();

	/**
	 * Determine if we are looking only at unread mentions or any kind of
	 *
	 * @var bool
	 */
	protected $_all = false;

	/**
	 *
	 * @param \ElkArte\EventManager $eventManager
	 */
	public function __construct($eventManager)
	{
		$this->_known_sorting = array('id_member_from', 'type', 'log_time');

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
			return array();
		}

<<<<<<< HEAD:sources/ElkArte/Controller/Mentions.php
		return array_filter(array_unique(explode(',', $modSettings['enabled_mentions'])));
=======
		$this->_known_mentions = $this->_findMentionTypes();

		require_once(SUBSDIR . '/Mentions.subs.php');
>>>>>>> 5667d796a (! fix #3499):sources/controllers/Mentions.controller.php
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
		if (empty($lastsent) && !empty($this->_req->session->notifications_lastseen))
		{
			$lastsent = (int) $this->_req->session->notifications_lastseen;
		}

		// We only know AJAX for this particular action
		$context['json_data'] = array(
			'timelast' => getTimeLastMention($this->user->id)
		);

		if (!empty($modSettings['usernotif_favicon_enable']))
		{
			$context['json_data']['mentions'] = (int) $this->user->mentions;
		}

		if (!empty($modSettings['usernotif_desktop_enable']))
		{
			$context['json_data']['desktop_notifications'] = array(
				'new_from_last' => getNewMentions($this->user->id, $lastsent),
				'title' => sprintf($txt['forum_notification'], strip_tags(un_htmlspecialchars($context['forum_name']))),
				'link' => '/index.php?action=mentions',
			);
			$context['json_data']['desktop_notifications']['message'] = sprintf($txt[$lastsent == 0 ? 'unread_notifications' : 'new_from_last_notifications'], $context['json_data']['desktop_notifications']['new_from_last']);
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
		ThemeLoader::loadLanguageFile('Mentions');

		$this->_buildUrl();

		$list_options = array(
			'id' => 'list_mentions',
			'title' => empty($this->_all) ? $txt['my_unread_mentions'] : $txt['my_mentions'],
			'items_per_page' => $this->_items_per_page,
			'base_href' => $scripturl . '?action=mentions;sa=list' . $this->_url_param,
			'default_sort_col' => $this->_default_sort,
			'default_sort_dir' => 'default',
			'no_items_label' => $this->_all ? $txt['no_mentions_yet'] : $txt['no_new_mentions'],
			'get_items' => array(
				'function' => array($this, 'list_loadMentions'),
				'params' => array(
					$this->_all,
					$this->_type,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getMentionCount'),
				'params' => array(
					$this->_all,
					$this->_type,
				),
			),
			'columns' => array(
				'id_member_from' => array(
					'header' => array(
						'value' => $txt['mentions_from'],
					),
					'data' => array(
						'function' => function ($row) {
							global $settings;

							if (isset($settings['mentions']['mentioner_template']))
							{
								return str_replace(
									array(
										'{avatar_img}',
										'{mem_url}',
										'{mem_name}',
									),
									array(
										$row['avatar']['image'],
										!empty($row['id_member_from']) ? getUrl('action', ['action' => 'profile', 'u' => $row['id_member_from']]) : '#',
										$row['mentioner'],
									),
									$settings['mentions']['mentioner_template']);
							}

							return '';
						},
					),
					'sort' => array(
						'default' => 'mtn.id_member_from',
						'reverse' => 'mtn.id_member_from DESC',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['mentions_what'],
					),
					'data' => array(
						'db' => 'message',
					),
					'sort' => array(
						'default' => 'mtn.mention_type',
						'reverse' => 'mtn.mention_type DESC',
					),
				),
				'log_time' => array(
					'header' => array(
						'value' => $txt['mentions_when'],
						'class' => 'mention_log_time',
					),
					'data' => array(
						'db' => 'log_time',
						'timeformat' => 'html_time',
						'class' => 'mention_log_time',
					),
					'sort' => array(
						'default' => 'mtn.log_time DESC',
						'reverse' => 'mtn.log_time',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['mentions_action'],
						'class' => 'listaction grid8',
					),
					'data' => array(
						'function' => function ($row) {
							global $txt, $settings;

							$mark = empty($row['status']) ? 'read' : 'unread';
							$opts = '<a href="' . getUrl('action', ['action' => 'mentions', 'sa' => 'updatestatus', 'mark' => $mark, 'item' => $row['id_mention'] . '{session_data}']) . '"><img title="' . $txt['mentions_mark' . $mark] . '" src="' . $settings['images_url'] . '/icons/mark_' . $mark . '.png" alt="*" /></a>&nbsp;';

							return $opts . '<a href="' . getUrl('action', ['action' => 'mentions', 'sa' => 'updatestatus', 'mark' => 'delete', 'item' => $row['id_mention'], '{session_data}']) . '"><i class="icon i-remove" title="' . $txt['delete'] . '"></i></a>';
						},
						'class' => 'listaction grid8',
					),
				),
			),
			'list_menu' => array(
				'show_on' => 'top',
				'links' => array(
					array(
						'href' => getUrl('action', ['action' => 'mentions'] + (!empty($this->_all) ? ['all'] : [])),
						'is_selected' => empty($this->_type),
						'label' => $txt['mentions_type_all']
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'above_column_headers',
					'class' => 'flow_flex_right',
					'value' => '<a class="linkbutton" href="' . $scripturl . '?action=mentions' . (!empty($this->_all) ? '' : ';all') . str_replace(';all', '', $this->_url_param) . '">' . (!empty($this->_all) ? $txt['mentions_unread'] : $txt['mentions_all']) . '</a>',
				),
				array(
					'class' => 'submitbutton',
					'position' => 'bottom_of_list',
					'value' => '<a class="linkbutton" href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=readall' . str_replace(';all', '', $this->_url_param) . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['mentions_mark_all_read'] . '</a>',
				),
			),
		);

		foreach ($this->_known_mentions as $mention)
		{
			$list_options['list_menu']['links'][] = array(
				'href' => getUrl('action', ['action' => 'mentions', 'type' => $mention] + (!empty($this->_all) ? ['all'] : [])),
				'is_selected' => $this->_type === $mention,
				'label' => $txt['mentions_type_' . $mention]
			);
		}

		createList($list_options);

		$context['page_title'] = $txt['my_mentions'] . (!empty($this->_page) ? ' - ' . sprintf($txt['my_mentions_pages'], $this->_page) : '');
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'mentions']),
			'name' => $txt['my_mentions'],
		);

		if (!empty($this->_type))
		{
			$context['linktree'][] = array(
				'url' => getUrl('action', ['action' => 'mentions', 'type' => $this->_type]),
				'name' => $txt['mentions_type_' . $this->_type],
			);
		}
	}

	/**
	 * Builds the link back so you return to the right list of mentions
	 */
	protected function _buildUrl()
	{
		$this->_all = $this->_req->getQuery('all') !== null;
		$this->_sort = in_array($this->_req->getQuery('sort', 'trim'), $this->_known_sorting) ? $this->_req->getQuery('sort', 'trim') : $this->_default_sort;
		$this->_type = in_array($this->_req->getQuery('type', 'trim'), $this->_known_mentions) ? $this->_req->getQuery('type', 'trim') : '';
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
	 * Used in Display.controller.php, it may be merged to action_updatestatus
	 * though that would require to add an optional parameter to avoid the redirect
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
				ThemeLoader::loadLanguageFile('Mentions');
				$mentions = $this->list_loadMentions((int) $this->_page, $this->_items_per_page, $this->_sort, $this->_all, $this->_type);
				$mentioning->markread($mentions);
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
		$mentions = array();
		$round = 0;
		ThemeLoader::loadLanguageFile('Mentions');

		$this->_registerEvents($type);

		while ($round < 2)
		{
			$possible_mentions = getUserMentions($start, $limit, $sort, $all, $type);
			$count_possible = count($possible_mentions);

			$this->_events->trigger('view_mentions', array($type, &$possible_mentions));

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
			if (count($mentions) !== $count_possible || count($mentions) === $limit || ($totalMentions - $start < $limit))
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
			$to_register = array('\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($type));
		}
		else
		{
			$to_register = array_map(function ($name) {
				return '\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($name);
			}, $this->_known_mentions);
		}

		$this->_registerEvent('view_mentions', 'view', $to_register);
	}
}
