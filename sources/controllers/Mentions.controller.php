<?php

/**
 * Handles all the mentions actions so members are notified of mentionalbe actions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Mentions_Controller Class:  Add mention notificaions for various actions such
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
	 */
	protected $_validator = null;

	/**
	 * Holds the passed data for this instance, is passed through the validator
	 *
	 * @var array
	 */
	protected $_data = null;

	/**
	 * A set of functions that will be called passing the mentions retrieved from the db
	 * Are originally stored in $_known_mentions
	 *
	 * @var array
	 */
	protected $_callbacks = array();

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
	 * Determine if we are looking only at unread mentions or any kind of
	 *
	 * @var boolean
	 */
	protected $_all = false;

	/**
	 * Start things up, what else does a constructor do
	 */
	public function __construct()
	{
		global $modSettings;

		$this->_known_mentions = array(
			// mentions
			'men' => array(
				'callback' => array($this, 'prepareMentionMessage'),
				'enabled' => !empty($modSettings['mentions_enabled']),
			),
			// liked messages
			'like' => array(
				'callback' => array($this, 'prepareMentionMessage'),
				'enabled' => !empty($modSettings['likes_enabled']),
			),
			// likes removed
			'rlike' => array(
				'callback' => array($this, 'prepareMentionMessage'),
				'enabled' => !empty($modSettings['likes_enabled']) && empty($modSettings['mentions_dont_notify_rlike']),
			),
			// added as buddy
			'buddy' => array(
				'callback' => array($this, 'prepareMentionMessage'),
				'enabled' => !empty($modSettings['mentions_buddy']),
			),
		);

		$this->_known_status = array(
			'new' => 0,
			'read' => 1,
			'deleted' => 2,
			'unapproved' => 3,
		);

		call_integration_hook('integrate_add_mention', array(&$this->_known_mentions));
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
			fatal_lang_error('no_access', false);

		$this->_data = array(
			'type' => isset($_REQUEST['type']) ? $_REQUEST['type'] : null,
			'uid' => isset($_REQUEST['uid']) ? $_REQUEST['uid'] : null,
			'msg' => isset($_REQUEST['msg']) ? $_REQUEST['msg'] : null,
			'id_member_from' => isset($_REQUEST['from']) ? $_REQUEST['from'] : null,
			'log_time' => isset($_REQUEST['log_time']) ? $_REQUEST['log_time'] : null,
		);
	}

	/**
	 * The default action is to show the list of mentions
	 * This allows ?action=mention to be forwarded to action_list()
	 */
	public function action_index()
	{
		// default action to execute
		$this->action_list();
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
		require_once(SUBSDIR . '/GenericList.class.php');
		loadLanguage('Mentions');

		$this->_buildUrl();

		$list_options = array(
			'id' => 'list_mentions',
			'title' => empty($this->_all) ? $txt['my_unread_mentions'] : $txt['my_mentions'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=mentions;sa=list' . $this->_url_param,
			'default_sort_col' => 'log_time',
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
						'function' => create_function('$row', '
							global $settings, $scripturl;

							if (isset($settings[\'mentions\'][\'mentioner_template\']))
								return str_replace(
									array(
										\'{avatar_img}\',
										\'{mem_url}\',
										\'{mem_name}\',
									),
									array(
										$row[\'avatar\'][\'image\'],
										!empty($row[\'id_member_from\']) ? $scripturl . \'?action=profile;u=\' . $row[\'id_member_from\'] : \'\',
										$row[\'mentioner\'],
									),
									$settings[\'mentions\'][\'mentioner_template\']);
						')
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
						'function' => create_function('$row', '
							global $txt, $settings, $context;

							$opts = \'\';

							if (empty($row[\'status\']))
								$opts = \'<a href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=read;item=\' . $row[\'id_mention\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';"><img title="\' . $txt[\'mentions_markread\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/mark_read.png" alt="*" /></a>&nbsp;\';
							else
								$opts = \'<a href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=unread;item=\' . $row[\'id_mention\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';"><img title="\' . $txt[\'mentions_markunread\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/mark_unread.png" alt="*" /></a>&nbsp;\';

							return $opts . \'<a href="' . $scripturl . '?action=mentions;sa=updatestatus;mark=delete;item=\' . $row[\'id_mention\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';"><img title="\' . $txt[\'delete\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/delete.png" alt="*" /></a>\';
						'),
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
			),
		);

		foreach ($this->_known_mentions as $key => $mention)
		{
			if (!empty($mention['enabled']))
			{
				$list_options['list_menu']['links'][] = array(
					'href' => $scripturl . '?action=mentions;type=' . $key . (!empty($this->_all) ? ';all' : ''),
					'is_selected' => $this->_type === $key,
					'label' => $txt['mentions_type_' . $key]
				);
				$this->_callbacks[$key] = $mention['callback'];
			}
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

		while ($round < 2)
		{
			$possible_mentions = getUserMentions($start, $limit, $sort, $all, $type);

			// With only one type is enough to just call that (if it exists)
			if (!empty($type) && isset($this->_callbacks[$type]))
				$removed = call_user_func_array($this->_callbacks[$type], array(&$possible_mentions, $type));
			// Otherwise we have to test all we know...
			else
			{
				$removed = false;
				// @todo find a way to call only what is actually needed
				foreach ($this->_callbacks as $type => $callback)
					$removed = call_user_func_array($callback, array(&$possible_mentions, $type)) || $removed;
			}

			foreach ($possible_mentions as $mention)
			{
				if (count($mentions) < $limit)
					$mentions[] = $mention;
				else
					break;
			}
			$round++;

			// If nothing has been removed OR there are not enough
			if (!$removed || count($mentions) == $limit || ($totalMentions - $start < $limit))
				break;

			// Let's start a bit further into the list
			$start += $limit;
		}

		return $mentions;
	}

	/**
	 * Callback used to prepare the mention message for mentions, likes, removed likes and buddies
	 *
	 * @param mixed[] $mentions : Mentions retrieved from the database by getUserMentions
	 * @param string $type : the type of the mention
	 */
	public function prepareMentionMessage(&$mentions, $type)
	{
		global $txt, $scripturl, $context, $modSettings, $user_info;

		$boards = array();
		$removed = false;

		foreach ($mentions as $key => $row)
		{
			// To ensure it is not done twice
			if ($row['mention_type'] != $type)
				continue;

			// These things are associated to messages and require permission checks
			if (in_array($row['mention_type'], array('men', 'like', 'rlike')))
				$boards[$key] = $row['id_board'];

			$mentions[$key]['message'] = str_replace(
				array(
					'{msg_link}',
					'{msg_url}',
					'{subject}',
				),
				array(
					'<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';mentionread;mark=read;' . $context['session_var'] . '=' . $context['session_id'] . ';item=' . $row['id_mention'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
					$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';mentionread;' . $context['session_var'] . '=' . $context['session_id'] . 'item=' . $row['id_mention'] . '#msg' . $row['id_msg'],
					$row['subject'],
				),
				$txt['mention_' . $row['mention_type']]);
		}

		// Do the permissions checks and replace inappropriate messages
		if (!empty($boards))
		{
			require_once(SUBSDIR . '/Boards.subs.php');

			$accessibleBoards = accessibleBoards($boards);

			foreach ($boards as $key => $board)
			{
				// You can't see the board where this mention is, so we drop it from the results
				if (!in_array($board, $accessibleBoards))
				{
					$removed = true;
					unset($mentions[$key]);
				}
			}
		}

		// If some of these mentions are no longer visable, we need to do some maintenance
		if ($removed)
		{
			if (!empty($modSettings['user_access_mentions']))
				$modSettings['user_access_mentions'] = @unserialize($modSettings['user_access_mentions']);
			else
				$modSettings['user_access_mentions'] = array();

			$modSettings['user_access_mentions'][$user_info['id']] = 0;
			updateSettings(array('user_access_mentions' => serialize($modSettings['user_access_mentions'])));
			scheduleTaskImmediate('user_access_mentions');
		}

		return $removed;
	}

	/**
	 * We will we will notify you
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
	 * Politley remove a mention when a post like is taken back
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
	 */
	public function action_updatestatus()
	{
		checkSession('request');

		$this->setData(array(
			'id_mention' => $_REQUEST['item'],
			'mark' => $_REQUEST['mark'],
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
			}
		}

		redirectexit('action=mentions;sa=list' . $this->_url_param);
	}

	/**
	 * Builds the link back so you return to the right list of mentions
	 */
	protected function _buildUrl()
	{
		$this->_all = isset($_REQUEST['all']);
		$this->_type = isset($_REQUEST['type']) && isset($this->_known_mentions[$_REQUEST['type']]) ? $_REQUEST['type'] : '';
		$this->_page = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';

		$this->_url_param = ($this->_all ? ';all' : '') . (!empty($this->_type) ? ';type=' . $this->_type : '') . (isset($_REQUEST['start']) ? ';start=' . $_REQUEST['start'] : '');
	}

	/**
	 * Check if the user can access the mention
	 */
	protected function _isAccessible()
	{
		require_once(SUBSDIR . '/DataValidator.class.php');
		require_once(SUBSDIR . '/Mentions.subs.php');

		$this->_validator = new Data_Validator();
		$sanitization = array(
			'id_mention' => 'intval',
			'mark' => 'trim',
		);
		$validation = array(
			'id_mention' => 'validate_ownmention',
			'mark' => 'contains[read,unread,delete]',
		);

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		return $this->_validator->validate($this->_data);
	}

	/**
	 * Check if the user can do what he is supposed to do, and validates the input
	 */
	protected function _isValid()
	{
		require_once(SUBSDIR . '/DataValidator.class.php');
		$this->_validator = new Data_Validator();
		$sanitization = array(
			'type' => 'trim',
			'msg' => 'intval',
		);
		$validation = array(
			'type' => 'required|contains[' . implode(',', array_keys($this->_known_mentions)) . ']',
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