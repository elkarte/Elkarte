<?php

/**
 * Handles all the mentions actions so members are notified of mentionable actions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

/**
 * Mentions_Controller Class:  Add mention notifications for various actions such
 * as liking a post, adding a buddy, @ calling a member in a post
 *
 * @package Mentions
 */
class Mentions_Controller extends Action_Controller
{
	/**
	 * Will hold all available mention types
	 *
	 * @var array
	 */
	protected $_known_mentions = array();

	/**
	 * Will hold all available mention status
	 * 'new' => 0, 'read' => 1, 'deleted' => 2, 'unapproved' => 3,
	 *
	 * @var array
	 */
	protected $_known_status = array();

	/**
	 * Holds the instance of the data validation class
	 *
	 * @var object
	 * @deprecated since 1.1
	 */
	protected $_validator = null;

	/**
	 * Holds the passed data for this instance, is passed through the validator
	 *
	 * @var array
	 * @deprecated since 1.1
	 */
	protected $_data = null;

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
	 * @var boolean
	 */
	protected $_all = false;

	/**
	 * Mentions_Controller constructor.
	 *
	 * @param Event_Manager|null $eventManager
	 */
	public function __construct($eventManager = null)
	{
		$this->_known_status = array(
			'new' => Mentioning::MNEW,
			'read' => Mentioning::READ,
			'deleted' => Mentioning::DELETED,
			'unapproved' => Mentioning::UNAPPROVED,
		);

		$this->_known_sorting = array('id_member_from', 'type', 'log_time');

		parent::__construct($eventManager);
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
			return array();

		return array_filter(array_unique(explode(',', $modSettings['enabled_mentions'])));
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
			Errors::instance()->fatal_lang_error('no_access', false);

		Elk_Autoloader::getInstance()->register(SUBSDIR . '/MentionType', '\\ElkArte\\sources\\subs\\MentionType');

		// @deprecated since 1.1
		$this->_data = array(
			'type' => $this->_req->getPost('type'),
			'uid' => $this->_req->getPost('uid'),
			'msg' => $this->_req->getPost('msg'),
			'id_member_from' => $this->_req->getPost('from'),
			'log_time' => $this->_req->getPost('log_time'),
		);

		$this->_known_mentions = $this->_findMentionTypes();
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
		loadLanguage('Mentions');

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
							global $settings, $scripturl;

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
										!empty($row['id_member_from']) ? $scripturl . '?action=profile;u=' . $row['id_member_from'] : '#',
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
						'class' => 'listaction',
					),
					'data' => array(
						'function' => function ($row) {
							global $txt, $settings, $context, $scripturl;

							$mark = empty($row['status']) ? 'read' : 'unread';
							$opts = '<a href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=' . $mark . ';item=' . $row['id_mention'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';"><img title="' . $txt['mentions_mark' . $mark] . '" src="' . $settings['images_url'] . '/icons/mark_' . $mark . '.png" alt="*" /></a>&nbsp;';

							return $opts . '<a href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=delete;item=' . $row['id_mention'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';"><i class="icon i-remove" title="' . $txt['delete'] . '"></i></a>';
						},
						'class' => 'listaction',
					),
				),
			),
			'list_menu' => array(
				'show_on' => 'top',
				'links' => array(
					array(
						'href' => $scripturl . '?action=mentions' . (!empty($this->_all) ? ';all' : ''),
						'is_selected' => empty($this->_type),
						'label' => $txt['mentions_type_all']
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'top_of_list',
					'value' => '<a class="floatright linkbutton" href="' . $scripturl . '?action=mentions' . (!empty($this->_all) ? '' : ';all') . str_replace(';all', '', $this->_url_param) . '">' . (!empty($this->_all) ? $txt['mentions_unread'] : $txt['mentions_all']) . '</a>',
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
				'href' => $scripturl . '?action=mentions;type=' . $mention . (!empty($this->_all) ? ';all' : ''),
				'is_selected' => $this->_type === $mention,
				'label' => $txt['mentions_type_' . $mention]
			);
		}

		createList($list_options);

		$context['page_title'] = $txt['my_mentions'] . (!empty($this->_page) ? ' - ' . sprintf($txt['my_mentions_pages'], $this->_page) : '');
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=mentions',
			'name' => $txt['my_mentions'],
		);

		if (!empty($this->_type))
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=mentions;type=' . $this->_type,
				'name' => $txt['mentions_type_' . $this->_type],
			);
	}

	/**
	 * Fetches number of notifications and number of recently added ones for use
	 * in favicon and desktop notifications.
	 *
	 * @todo probably should be placed somewhere else.
	 */
	public function action_fetch()
	{
		global $user_info, $context, $txt, $modSettings;

		if (empty($modSettings['usernotif_favicon_enable']) && empty($modSettings['usernotif_desktop_enable']))
			die();

		loadTemplate('Json');
		$context['sub_template'] = 'send_json';
		$template_layers = Template_Layers::getInstance();
		$template_layers->removeAll();
		require_once(SUBSDIR . '/Mentions.subs.php');

		$lastsent = $this->_req->getQuery('lastsent', 'intval', 0);
		if (empty($lastsent) && !empty($this->_req->session->notifications_lastseen))
		{
			$lastsent = (int) $this->_req->session->notifications_lastseen;
		}

		// We only know AJAX for this particular action
		$context['json_data'] = array(
			'timelast' => getTimeLastMention($user_info['id'])
		);

		if (!empty($modSettings['usernotif_favicon_enable']))
		{
			$context['json_data']['mentions'] = !empty($user_info['mentions']) ? $user_info['mentions'] : 0;
		}

		if (!empty($modSettings['usernotif_desktop_enable']))
		{
			$context['json_data']['desktop_notifications'] = array(
				'new_from_last' => getNewMentions($user_info['id'], $lastsent),
				'title' => sprintf($txt['forum_notification'], $context['forum_name']),
			);
			$context['json_data']['desktop_notifications']['message'] = sprintf($txt[$lastsent == 0 ? 'unread_notifications' : 'new_from_last_notifications'], $context['json_data']['desktop_notifications']['new_from_last']);
		}

		$_SESSION['notifications_lastseen'] = $context['json_data']['timelast'];
	}

	/**
	 * Callback for createList(),
	 * Returns the number of mentions of $type that a member has
	 *
	 * @param bool $all : if true counts all the mentions, otherwise only the unread
	 * @param string $type : the type of mention
	 */
	public function list_getMentionCount($all, $type)
	{
		return countUserMentions($all, $type);
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
	 */
	public function list_loadMentions($start, $limit, $sort, $all, $type)
	{
		$totalMentions = countUserMentions($all, $type);
		$mentions = array();
		$round = 0;
		loadLanguage('Mentions');

		$this->_registerEvents($type);

		while ($round < 2)
		{
			$possible_mentions = getUserMentions($start, $limit, $sort, $all, $type);
			$count_possible = count($possible_mentions);

			$this->_events->trigger('view_mentions', array($type, &$possible_mentions));

			foreach ($possible_mentions as $mention)
			{
				if (count($mentions) < $limit)
					$mentions[] = $mention;
				else
					break;
			}
			$round++;

			// If nothing has been removed OR there are not enough
			if (count($mentions) != $count_possible || count($mentions) == $limit || ($totalMentions - $start < $limit))
				break;

			// Let's start a bit further into the list
			$start += $limit;
		}

		if ($round !== 0)
			countUserMentions();

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
			$to_register = array('\\ElkArte\\sources\\subs\\MentionType\\' . ucfirst($type) . '_Mention');
		}
		else
		{
			$to_register = array_map(function($name) {
				return '\\ElkArte\\sources\\subs\\MentionType\\' . ucfirst($name) . '_Mention';
			}, $this->_known_mentions);
		}

		$this->_registerEvent('view_mentions', 'view', $to_register);
	}

	/**
	 * We will, we will notify you
	 * @deprecated since 1.1 - Use Notifications::create instead
	 */
	public function action_add()
	{
		global $user_info;

		// Common checks to determine if we can go on
		if (!$this->_isValid())
			return;

		// Cleanup, validate and remove the invalid values (0 and $user_info['id'])
		$id_target = array_diff(array_map('intval', array_unique($this->_validator->uid)), array(0, $user_info['id']));

		if (empty($id_target))
			return false;

		addMentions($user_info['id'], $id_target, $this->_validator->msg, $this->_validator->type, $this->_validator->log_time, $this->_data['status']);
	}

	/**
	 * Politely remove a mention when a post like is taken back
	 * @deprecated since 1.1 - Use Notifications::create instead
	 */
	public function action_rlike()
	{
		global $user_info;

		// Common checks to determine if we can go on
		if (!$this->_isValid())
			return;

		// Cleanup, validate and remove the invalid values (0 and $user_info['id'])
		$id_target = array_diff(array_map('intval', array_unique($this->_validator->uid)), array(0, $user_info['id']));

		if (empty($id_target))
			return false;

		rlikeMentions($user_info['id'], $id_target, $this->_validator->msg);
	}

	/**
	 * Sets the specifics of a mention call in this instance
	 *
	 * @param mixed[] $data must contain uid, type and msg at a minimum
	 * @deprecated since 1.1
	 */
	public function setData($data)
	{
		if (isset($data['id_member']))
		{
			$this->_data = array(
				'uid' => is_array($data['id_member']) ? $data['id_member'] : array($data['id_member']),
				'type' => $data['type'],
				'msg' => $data['id_msg'],
				'status' => isset($data['status']) && in_array($data['status'], $this->_known_status) ? $this->_known_status[$data['status']] : 0,
			);

			if (isset($data['id_member_from']))
				$this->_data['id_member_from'] = $data['id_member_from'];

			if (isset($data['log_time']))
				$this->_data['log_time'] = $data['log_time'];
		}
		else
			$this->_data = $data;
	}

	/**
	 * Did you read the mention? Then let's move it to the graveyard.
	 * Used in Display.controller.php, it may be merged to action_updatestatus
	 * though that would require to add an optional parameter to avoid the redirect
	 * @deprecated since 1.1 - Use Mentioning::markread instead
	 */
	public function action_markread()
	{
		checkSession('request');

		// Common checks to determine if we can go on
		if (!$this->_isAccessible())
			return;

		$this->_buildUrl();

		changeMentionStatus($this->_validator->id_mention, $this->_known_status['read']);
	}

	/**
	 * Updating the status from the listing?
	 * @deprecated since 1.1 - Use Mentioning::updateStatus instead
	 */
	public function action_updatestatus()
	{
		checkSession('request');

		$this->setData(array(
			'id_mention' => $this->_req->getQuery('item', 'intval', 0),
			'mark' => $this->_req->getQuery('mark'),
		));

		// Make sure its all good
		if ($this->_isAccessible())
		{
			$this->_buildUrl();

			switch ($this->_validator->mark)
			{
				case 'read':
					changeMentionStatus($this->_validator->id_mention, $this->_known_status['read']);
					break;
				case 'unread':
					changeMentionStatus($this->_validator->id_mention, $this->_known_status['new']);
					break;
				case 'delete':
					changeMentionStatus($this->_validator->id_mention, $this->_known_status['deleted']);
					break;
				case 'readall':
					loadLanguage('Mentions');
					$mentions = $this->list_loadMentions((int) $this->_page, $this->_items_per_page, $this->_sort, $this->_all, $this->_type);
					$this->_markMentionsRead($mentions);
					break;
			}
		}

		redirectexit('action=mentions;sa=list' . $this->_url_param);
	}

	/**
	 * Marks an array of mentions as read
	 *
	 * @param int[] $mention_ids An array of mention ids. Each of them will be
	 *              validated independently
	 * @deprecated since 1.1
	 */
	protected function _markMentionsRead($mention_ids)
	{
		if (empty($mention_ids))
			return;

		foreach ($mention_ids as $mention)
		{
			$this->setData(array(
				'id_mention' => $mention['id_mention'],
				'mark' => 'read',
			));

			if ($this->_isAccessible())
				changeMentionStatus($this->_validator->id_mention, $this->_known_status['read']);
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
	 * Check if the user can access the mention
	 * @deprecated since 1.1
	 */
	protected function _isAccessible()
	{
		require_once(SUBSDIR . '/Mentions.subs.php');

		$this->_validator = new Data_Validator();
		$sanitization = array(
			'id_mention' => 'intval',
			'mark' => 'trim',
		);
		$validation = array(
			'id_mention' => 'validate_ownmention',
			'mark' => 'contains[read,unread,delete,readall]',
		);

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		return $this->_validator->validate($this->_data);
	}

	/**
	 * Check if the user can do what he is supposed to do, and validates the input
	 * @deprecated since 1.1
	 */
	protected function _isValid()
	{
		$this->_validator = new Data_Validator();
		$sanitization = array(
			'type' => 'trim',
			'msg' => 'intval',
		);
		$validation = array(
			'type' => 'required|contains[' . implode(',', $this->_known_mentions) . ']',
			'uid' => 'isarray',
		);

		// Any optional fields we need to check?
		if (isset($this->_data['id_member_from']))
		{
			$sanitization['id_member_from'] = 'intval';
			$validation['id_member_from'] = 'required|notequal[0]';
		}
		if (isset($this->_data['log_time']))
		{
			$sanitization['log_time'] = 'intval';
			$validation['log_time'] = 'required|notequal[0]';
		}

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		if (!$this->_validator->validate($this->_data))
			return false;

		// If everything is fine, let's include our helper functions and prepare for the fun!
		require_once(SUBSDIR . '/Mentions.subs.php');
		loadLanguage('Mentions');

		return true;
	}
}