<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * Notify members.
 *
 */

if (!defined('ELK'))
	die('No access...');

class Mentions_Controller extends Action_Controller
{
	/**
	 * Will hold all available mention types
	 *
	 * @var array
	 */
	private $_known_mentions = array();

	/**
	 * Will hold all available mention status
	 * 'new' => 0, 'read' => 1, 'deleted' => 2, 'unapproved' => 3,
	 *
	 * @var array
	 */
	private $_known_status = array();

	/**
	 * Holds the instance of the data validation class
	 *
	 * @var object
	 */
	private $_validator = null;

	/**
	 * Holds the passed data for this instance, is passed through the validator
	 *
	 * @var array
	 */
	private $_data = null;

	/**
	 * Start things up, what else does a contructor do
	 */
	public function __construct()
	{
		$this->_known_mentions = array(
			'men', // mention
			'like', // message liked
			'rlike', // like removed
			'buddy', // added as buddy
		);
		$this->_known_status = array(
			'new' => 0,
			'read' => 1,
			'deleted' => 2,
			'unapproved' => 3,
		);

		// @todo is it okay to have it here?
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
		if (empty($modSettings['notifications_enabled']))
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
		require_once(SUBSDIR . '/List.class.php');
		loadLanguage('Mentions');

		$this->_buildUrl();

		$list_options = array(
			'id' => 'list_mentions',
			'title' => empty($this->_all) ? $txt['my_unread_notifications'] : $txt['my_notifications'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=notification;sa=list' . $this->_url_param,
			'default_sort_col' => 'log_time',
			'default_sort_dir' => 'default',
			'no_items_label' => $this->_all ? $txt['no_notifications_yet'] : $txt['no_new_notifications'],
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
						'value' => $txt['notification_from'],
					),
					'data' => array(
						'function' => create_function('$row', '
							global $settings, $scripturl;

							if (isset($settings[\'notifications\'][\'notifier_template\']))
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
									$settings[\'notifications\'][\'notifier_template\']);
						')
					),
					'sort' => array(
						'default' => 'n.id_member_from',
						'reverse' => 'n.id_member_from DESC',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['notification_what'],
					),
					'data' => array(
						'function' => create_function('$row', '
							global $txt, $scripturl, $context;

							return str_replace(array(
								\'{msg_link}\',
								\'{msg_url}\',
								\'{subject}\',
							),
							array(
								\'<a href="\' . $scripturl . \'?topic=\' . $row[\'id_topic\'] . \'.msg\' . $row[\'id_msg\'] . \';notifread;mark=read;\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';item=\' . $row[\'id_notification\'] . \'#msg\' . $row[\'id_msg\'] . \'">\' . $row[\'subject\'] . \'</a>\',
								$scripturl . \'?topic=\' . $row[\'id_topic\'] . \'.msg\' . $row[\'id_msg\'] . \';notifread;\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'item=\' . $row[\'id_notification\'] . \'#msg\' . $row[\'id_msg\'] . \'\',
								$row[\'subject\'],
							), $txt[\'notification_\' . $row[\'notif_type\']]);
						')
					),
					'sort' => array(
						'default' => 'n.notif_type',
						'reverse' => 'n.notif_type DESC',
					),
				),
				'log_time' => array(
					'header' => array(
						'value' => $txt['notification_when'],
					),
					'data' => array(
						'db' => 'log_time',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'n.log_time DESC',
						'reverse' => 'n.log_time',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['notification_action'],
					),
					'data' => array(
						'function' => create_function('$row', '
							global $txt, $settings, $context;

							$opts = \'\';

							if (empty($row[\'status\']))
								$opts = \'<a href="?action=notification;sa=updatestatus;mark=read;item=\' . $row[\'id_notification\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';"><img style="width:16px;height:16px" title="\' . $txt[\'notification_markread\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/mark_read.png" alt="*" /></a>&nbsp;\';
							else
								$opts = \'<a href="?action=notification;sa=updatestatus;mark=unread;item=\' . $row[\'id_notification\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';"><img style="width:16px;height:16px" title="\' . $txt[\'notification_markunread\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/mark_unread.png" alt="*" /></a>&nbsp;\';

							return $opts . \'<a href="?action=notification;sa=updatestatus;mark=delete;item=\' . $row[\'id_notification\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \';"><img style="width:16px;height:16px" title="\' . $txt[\'delete\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/delete.png" alt="*" /></a>\';
						'),
					),
				),
			),
			'list_menu' => array(
				'show_on' => 'top',
				'links' => array(
					array(
						'href' => $scripturl . '?action=notification' . (!empty($this->_all) ? ';all' : ''),
						'is_selected' => empty($this->_type),
						'label' => $txt['notification_type_all']
					),
					array(
						'href' => $scripturl . '?action=notification;type=men' . (!empty($this->_all) ? ';all' : ''),
						'is_selected' => $this->_type === 'men',
						'label' => $txt['notification_type_men']
					),
					array(
						'href' => $scripturl . '?action=notification;type=like' . (!empty($this->_all) ? ';all' : ''),
						'is_selected' => $this->_type === 'like',
						'label' => $txt['notification_type_like']
					),
					array(
						'href' => $scripturl . '?action=notification;type=rlike' . (!empty($this->_all) ? ';all' : ''),
						'is_selected' => $this->_type === 'rlike',
						'label' => $txt['notification_type_rlike']
					),
					array(
						'href' => $scripturl . '?action=notification;type=buddy' . (!empty($this->_all) ? ';all' : ''),
						'is_selected' => $this->_type === 'buddy',
						'label' => $txt['notification_type_buddy']
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'top_of_list',
					'value' => '<a class="floatright linkbutton" href="' . $scripturl . '?action=notification' . (!empty($this->_all) ? '' : ';all') . str_replace(';all', '', $this->_url_param) . '">' . (!empty($this->_all) ? $txt['notification_unread'] : $txt['notification_all']) . '</a>',
				),
			),
		);

		createList($list_options);

		$context['page_title'] = $txt['my_notifications'] . (!empty($this->_page) ? ' - ' . sprintf($txt['my_notifications_pages'], $this->_page) : '');
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=notification',
			'name' => $txt['my_notifications'],
		);

		if (!empty($this->_type))
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=notification;type=' . $this->_type,
				'name' => $txt['notification_type_' . $this->_type],
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
	 * @param int $items_per_page how many to show on a page
	 * @param string $sort which direction are we showing this
	 * @param bool $all : if true load all the mentions or type, otherwise only the unread
	 * @param string $type : the type of mention
	 */
	public function list_loadMentions($start, $limit, $sort, $all, $type)
	{
		return getUserMentions($start, $limit, $sort, $all, $type);
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
	 * Sets the specifics of a mention call in this instance
	 *
	 * @param array $data must contain uid, type and msg at a minimum
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

		changeMentionStatus($this->_validator->id_notification, $this->_known_status['read']);
	}

	/**
	 * Updating the status from the listing?
	 */
	public function action_updatestatus()
	{
		checkSession('request');

		$this->setData(array(
			'id_notification' => $_REQUEST['item'],
			'mark' => $_REQUEST['mark'],
		));

		// Make sure its all good
		if ($this->_isAccessible())
		{
			$this->_buildUrl();

			switch ($this->_validator->mark)
			{
				case 'read':
					changeMentionStatus($this->_validator->id_notification, $this->_known_status['read']);
					break;
				case 'unread':
					changeMentionStatus($this->_validator->id_notification, $this->_known_status['new']);
					break;
				case 'delete':
					changeMentionStatus($this->_validator->id_notification, $this->_known_status['deleted']);
					break;
			}
		}

		redirectexit('action=notification;sa=list' . $this->_url_param);
	}

	/**
	 * Builds the link back so you return to the right list of mentions
	 */
	private function _buildUrl()
	{
		$this->_all = isset($_REQUEST['all']);
		$this->_type = isset($_REQUEST['type']) && in_array($_REQUEST['type'], $this->_known_mentions) ? $_REQUEST['type'] : '';
		$this->_page = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';

		$this->_url_param = ($this->_all ? ';all' : '') . (!empty($this->_type) ? ';type=' . $this->_type : '') . (isset($_REQUEST['start']) ? ';start=' . $_REQUEST['start'] : '');
	}

	/**
	 * Check if the user can access the mention
	 */
	private function _isAccessible()
	{
		require_once(SUBSDIR . '/DataValidator.class.php');
		require_once(SUBSDIR . '/Mentions.subs.php');

		$this->_validator = new Data_Validator();
		$sanitization = array(
			'id_notification' => 'intval',
			'mark' => 'trim',
		);
		$validation = array(
			'id_notification' => 'validate_ownmention',
			'mark' => 'trim|contains[read,unread,delete]',
		);

		$this->_validator->sanitation_rules($sanitization);
		$this->_validator->validation_rules($validation);

		if (!$this->_validator->validate($this->_data))
			return false;

		return true;
	}

	/**
	 * Check if the user can do what he is supposed to do, and validates the input
	 */
	private function _isValid()
	{
		require_once(SUBSDIR . '/DataValidator.class.php');
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