<?php

/**
 * This file contains maillist functions that are specifically done by administrators
 * and those with approve email permission
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

/**
 * This class is the administration maillist controller.
 *
 *  - handles maillist configuration
 *  - handles the showing, repairing, deleting and bouncing failed emails
 *  - handles the adding / editing / removing of both filters and parsers
 *
 * @package Maillist
 */
class ManageMaillist_Controller extends Action_Controller
{
	/**
	 * Basic feature settings form
	 * @var Settings_Form
	 */
	protected $_maillistSettings;

	/**
	 * Basic filter settings form
	 * @var Settings_Form
	 */
	protected $_filtersSettings;

	/**
	 * Parsers settings form
	 * @var Settings_Form
	 */
	protected $_parsersSettings;

	/**
	 * Main dispatcher.
	 *
	 * This function checks permissions and passes control to the sub action.
	 *
	 * @see Action_Controller::action_index()
	 * @uses Maillist template
	 */
	public function action_index()
	{
		global $context, $txt;

		// Template & language
		loadTemplate('Maillist');
		loadLanguage('Maillist');

		// All the functions available
		$subActions = array(
			'emaillist' => array($this, 'action_unapproved_email', 'permission' => 'approve_emails'),
			'approve' => array($this, 'action_approve_email', 'permission' => 'approve_emails'),
			'delete' => array($this, 'action_delete_email', 'permission' => 'approve_emails'),
			'bounce' => array($this, 'action_bounce_email', 'permission' => 'approve_emails'),
			'emailtemplates' => array($this, 'action_view_bounce_templates', 'permission' => 'approve_emails'),
			'view' => array($this, 'action_view_email', 'permission' => 'approve_emails'),
			'emailsettings' => array($this, 'action_settings', 'permission' => 'admin_forum'),
			'emailfilters' => array($this, 'action_list_filters', 'permission' => 'admin_forum'),
			'editfilter' => array($this, 'action_edit_filters', 'permission' => 'admin_forum'),
			'deletefilter' => array($this, 'action_delete_filters', 'permission' => 'admin_forum'),
			'emailparser' => array($this, 'action_list_parsers', 'permission' => 'admin_forum'),
			'editparser' => array($this, 'action_edit_parsers', 'permission' => 'admin_forum'),
			'deleteparser' => array($this, 'action_delete_parsers', 'permission' => 'admin_forum'),
			'sortparsers' => array($this, 'action_sort_parsers', 'permission' => 'admin_forum'),
			'sortfilters' => array($this, 'action_sort_filters', 'permission' => 'admin_forum'),
		);

		// Action Controller
		$action = new Action('manage_maillist');

		// Help is needed in most places, so load it up front
		require_once(SUBSDIR . '/Maillist.subs.php');

		// Create the title area for the template.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['ml_admin_configuration'],
			'help' => 'maillist_help_short',
			'description' => $txt['ml_configuration_desc'],
		);

		// Default to sub action 'emaillist' if none was given, call integrate_sa_manage_maillist
		$subAction = isset($this->_req->query->sa) && isset($subActions[$this->_req->query->sa]) && (empty($subActions[$this->_req->query->sa]['permission']) || allowedTo($subActions[$this->_req->query->sa]['permission'])) ? $this->_req->query->sa : 'emaillist';
		$subAction = $action->initialize($subActions, $subAction);

		// Final bits
		$context['page_title'] = $txt['ml_admin_configuration'];
		$context['sub_action'] = $subAction;

		// If you have the permissions, then go Play
		$action->dispatch($subAction);
	}

	/**
	 * Main listing of failed emails.
	 *
	 * What it does
	 * - shows the sender, key and subject of the email
	 * - Will show the found key if it was missing or possible sender if it was wrong
	 * - icons to view, bounce, delete or approve a failure
	 * - Accessed by ?action=admin;area=maillist;sa=emaillist
	 *
	 * @uses showlist sub template
	 */
	public function action_unapproved_email()
	{
		global $context, $scripturl, $modSettings, $txt;

		// Set an id if none was supplied
		$id = $this->_req->getQuery('e_id', 'intval', 0);
		if (empty($id) || $id <= 0)
			$id = 0;

		createToken('admin-ml', 'get');

		// Build the list option array to display the email data
		$listOptions = array(
			'id' => 'view_email_errors',
			'title' => $txt['ml_emailerror'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['ml_emailerror_none'],
			'base_href' => $scripturl . '?action=admin;area=maillist',
			'default_sort_col' => 'id_email',
			'get_items' => array(
				'function' => array($this, 'list_maillist_unapproved'),
				'params' => array(
					$id,
				),
			),
			'get_count' => array(
				'function' => 'list_maillist_count_unapproved',
			),
			'columns' => array(
				'id_email' => array(
					'header' => array(
						'value' => $txt['id'],
						'class' => 'nowrap',
					),
					'data' => array(
						'db' => 'id_email',
					),
					'sort' => array(
						'default' => 'id_email ',
						'reverse' => 'id_email DESC',
					),
				),
				'error' => array(
					'header' => array(
						'value' => $txt['error'],
					),
					'data' => array(
						'function' => function ($rowData) {
							$error = $rowData['error_code'];
							if ($error === 'error_pm_not_found')
								return '<span class="error">' . $rowData['error'] . '<span>';
							else
								return $rowData['error'];
						},
					),
					'sort' => array(
						'default' => 'error ',
						'reverse' => 'error DESC',
					),
				),
				'subject' => array(
					'header' => array(
						'value' => $txt['subject'],
					),
					'data' => array(
						'db' => 'subject',
					),
					'sort' => array(
						'default' => 'subject',
						'reverse' => 'subject DESC',
					),
				),
				'key' => array(
					'header' => array(
						'value' => $txt['key'],
					),
					'data' => array(
						'db' => 'key',
						'class' => 'wordbreak'
					),
					'sort' => array(
						'default' => 'message_key',
						'reverse' => 'message_key DESC',
					),
				),
				'message' => array(
					'header' => array(
						'value' => $txt['message_id'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="%1$s">%2$s</a>',
							'params' => array(
								'link' => true,
								'message' => true,
							),
						),
					),
					'sort' => array(
						'default' => 'message_id',
						'reverse' => 'message_id DESC',
					),
				),
				'from' => array(
					'header' => array(
						'value' => $txt['from'],
					),
					'data' => array(
						'db' => 'from',
					),
					'sort' => array(
						'default' => 'email_from',
						'reverse' => 'email_from DESC',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['message_type'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							// Do we have a type?
							if (empty($rowData['type']))
								return $txt['not_applicable'];
							// Personal?
							elseif ($rowData['type'] === 'p')
								return $txt['personal_message'];
							// New Topic?
							elseif ($rowData['type'] === 'x')
								return $txt['new_topic'];
							// Ah a Reply then
							else
								return $txt['topic'] . ' ' . $txt['reply'];
						},
					),
					'sort' => array(
						'default' => 'message_type',
						'reverse' => 'message_type DESC',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['message_action'],
					),
					'data' => array(
						'function' => function($rowData) {
							global $context, $txt;

							$id = $rowData['id_email'] . ';';
							$commands = array();
							$security = $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-ml_token_var'] . '=' . $context['admin-ml_token'];

							if ($rowData['error_code'] === 'error_pm_not_found')
								$commands[] = '<a href="?action=admin;area=maillist;sa=approve;item=' . $id . $security . '" onclick="return confirm(' . JavaScriptEscape($txt['pm_approve_warning']) . ') && submitThisOnce(this);"><i class="icon i-check" title="' . $txt['approve'] . '"></i></a>&nbsp;';
							else
								$commands[] = '<a href="?action=admin;area=maillist;sa=approve;item=' . $id . $security . '"><i class="icon i-check" title="' . $txt['approve'] . '"></i></a>&nbsp;';

							$commands[] = '<a href="?action=admin;area=maillist;sa=delete;item=' . $id . $security . '" onclick="return confirm(' . JavaScriptEscape($txt['delete_warning']) . ') && submitThisOnce(this);" accesskey="d"><i class="icon i-delete" title="' . $txt['delete'] . '"></i></a><br />';
							$commands[] = '<a href="?action=admin;area=maillist;sa=bounce;item=' . $id . $security . '"><i class="icon i-sign-out" title="' . $txt['bounce'] . '"></i></a>&nbsp;';
							$commands[] = '<a href="?action=admin;area=maillist;sa=view;item=' . $id . $security . '"><i class="icon i-view" title="' . $txt['view'] . '"></i></a>';

							return implode('', $commands);
						},
					),
					'class' => 'listaction',
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
				'include_sort' => true,
				'include_start' => true,
			),
			'additional_rows' => array(
				array(
					'position' => 'top_of_list',
					'value' => isset($this->_req->session->email_error) ? '<div class="' . (isset($this->_req->session->email_error_type) ? 'successbox' : 'errorbox') . '">' . $this->_req->session->email_error . '</div>' : $txt['heading'],
				),
			),
		);

		// Clear any errors
		unset($_SESSION['email_error'], $_SESSION['email_error_type']);

		// Set the context values for the template
		$context['page_title'] = $txt['emailerror_title'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_email_errors';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Show a failed email for review by the moderation team
	 *
	 * - Will not show a PM if it has been identified as such
	 * - Accessed by ?action=admin;area=maillist;sa=view;item=?
	 *
	 * @uses show_email sub template
	 */
	public function action_view_email()
	{
		global $txt, $context;

		allowedTo('approve_emails');
		checkSession('get');
		validateToken('admin-ml', 'get');

		$id = (int) $this->_req->query->item;
		if (!empty($id))
		{
			// Load up the email details, no funny biz ;)
			$temp_email = list_maillist_unapproved($id);

			if (!empty($temp_email))
			{
				if ($temp_email[0]['type'] !== 'p' && allowedTo('approve_emails'))
				{
					// The raw email that failed
					$data = $temp_email[0]['body'];

					// Read/parse this message for viewing
					$controller = new Emailpost_Controller();
					$result = $controller->action_pbe_preview($data);
					$text = isset($result['body']) ? $result['body'] : '';
					$email_to = isset($result['to']) ? $result['to'] : '';
				}
				else
				{
					// PM's mean just that ...
					$text = $txt['noaccess'];
					$email_to = $txt['private'];
				}
			}
			else
				$text = $txt['badid'];
		}
		else
			$text = $txt['badid'];

		$parser = \BBC\ParserWrapper::getInstance();
		// Prep and show the template with what we found
		$context['body'] = $parser->parseEmail($text);
		$context['to'] = $txt['to'] . ' ' . (isset($email_to) ? $email_to : '');
		$context['notice_subject'] = isset($temp_email[0]['subject']) ? $txt['subject'] . ': ' . $temp_email[0]['subject'] : '';
		$context['notice_from'] = isset($temp_email[0]['from']) ? $txt['from'] . ': ' . $temp_email[0]['from'] : '';
		$context['page_title'] = $txt['show_notice'];
		$context['error_code'] = isset($temp_email[0]['error_code']) && isset($txt[$temp_email[0]['error_code']]) ? $txt[$temp_email[0]['error_code']] : '';
		$context['sub_template'] = 'show_email';
	}

	/**
	 * Deletes an entry from the database
	 *
	 * - Flushes the moderator menu todo numbers so the menu numbers update
	 * - Accessed by ?action=admin;area=maillist;sa=delete;item=?'
	 * - Redirects to ?action=admin;area=maillist;sa=emaillist
	 */
	public function action_delete_email()
	{
		allowedTo('approve_emails');
		checkSession('get');
		validateToken('admin-ml', 'get');

		$id = (int) $this->_req->query->item;

		// Remove this entry
		if (!empty($id))
			maillist_delete_error_entry($id);

		// Flush the cache
		Cache::instance()->remove('num_menu_errors');

		// Back to the failed list we go
		redirectexit('action=admin;area=maillist;sa=emaillist');
	}

	/**
	 * Attempts to approve and post a failed email
	 *
	 * - Reviews the data to see if the email error function fixed typical issues like key and wrong id
	 * - Submits the fixed email to the main function which will post it or fail it again
	 * - If successful will remove the entry from the failed log
	 * - Accessed by ?action=admin;area=maillist;sa=approve;item=?'
	 * - Redirects to action=admin;area=maillist;sa=emaillist
	 */
	public function action_approve_email()
	{
		global $txt;

		allowedTo('approve_emails');
		checkSession('get');
		validateToken('admin-ml', 'get');

		// Get the id to approve
		$id = (int) $this->_req->query->item;

		if (!empty($id) && $id !== -1)
		{
			// Load up the email data
			$temp_email = list_maillist_unapproved($id);
			if (!empty($temp_email))
			{
				// Do we have the needed data to approve this, after all it failed for a reason yes?
				if (!empty($temp_email[0]['key']) && (!in_array($temp_email[0]['error_code'], array('error_no_message', 'error_not_find_board', 'error_topic_gone'))))
				{
					// Set up the details needed to get this posted
					$force = true;
					$key = $temp_email[0]['key'];
					$data = $temp_email[0]['body'];

					// Unknown from email?  Update the message ONLY if we found an appropriate one during the error checking process
					if (in_array($temp_email[0]['error_code'], array('error_not_find_member', 'error_key_sender_match')))
					{
						// did we actually find a potential correct name, if so we post from the valid member
						$check_emails = array_pad(explode('=>', $temp_email[0]['from']), 2, '');

						if (!empty($check_emails[1]))
							$data = preg_replace('~(From: )(.*<)?(' . preg_quote(trim($check_emails[0])) . ')(>)?(\n)~i', '$1$2' . trim($check_emails[1]) . '$4$5', $data);
					}

					// Lets TRY AGAIN to make a post!
					include_once(CONTROLLERDIR . '/Emailpost.controller.php');
					$controller = new Emailpost_Controller();
					$text = $controller->action_pbe_post($data, $force, $key);

					// Assuming all went well, remove this entry and file since we are done.
					if ($text === true)
					{
						maillist_delete_error_entry($id);

						// Flush the menu count cache
						Cache::instance()->remove('num_menu_errors');

						$_SESSION['email_error'] = $txt['approved'];
						$_SESSION['email_error_type'] = 1;
					}
					else
						$_SESSION['email_error'] = $txt['error_approved'];
				}
				else
					$_SESSION['email_error'] = $txt['cant_approve'];
			}
			else
				$_SESSION['email_error'] = $txt['badid'];
		}
		else
			$_SESSION['email_error'] = $txt['badid'];

		// back to the list we go
		redirectexit('action=admin;area=maillist;sa=emaillist');
	}

	/**
	 * Allows the admin to choose from predefined and custom templates
	 *
	 * - Uses the selected template to send a bounce notification with
	 * details as specified by the template
	 * - Accessed by ?action=admin;area=maillist;sa=bounce;item=?'
	 * - Redirects to action=admin;area=maillist;sa=bounced
	 *
	 * @uses bounce_email sub-template
	 */
	public function action_bounce_email()
	{
		global $context, $txt, $modSettings, $scripturl, $mbname;

		if (!isset($this->_req->query->bounce))
		{
			checkSession('get');
			validateToken('admin-ml', 'get');
		}

		require_once(SUBSDIR . '/Mail.subs.php');

		// We should have been sent an email ID
		if (isset($this->_req->query->item))
		{
			// Needs to be an int!
			$id = (int) $this->_req->query->item;

			// Load up the email details, no funny biz yall ;)
			$temp_email = list_maillist_unapproved($id);

			if (!empty($temp_email))
			{
				// Set the options
				$this->_req->post->item = (int) $temp_email[0]['id_email'];
				$fullerrortext = $txt[$temp_email[0]['error_code']];

				// Build the template selection area, first the standard ones
				$bounce = array('bounce', 'inform');
				foreach ($bounce as $k => $type)
				{
					$context['bounce_templates'][$k]['body'] = $txt['ml_' . $type . '_body'];
					$context['bounce_templates'][$k]['subject'] = $txt['ml_' . $type . '_subject'];
					$context['bounce_templates'][$k]['title'] = $txt['ml_' . $type . '_title'];
				}

				// And now any custom ones available for this moderator
				$context['bounce_templates'] += array_merge($context['bounce_templates'], maillist_templates('bnctpl', $txt['ml_bounce_template_subject_default']));

				// Replace all the variables in the templates
				foreach ($context['bounce_templates'] as $k => $name)
				{
					$context['bounce_templates'][$k]['body'] = strtr($name['body'], array(
						'{MEMBER}' => un_htmlspecialchars($temp_email[0]['name']),
						'{SCRIPTURL}' => $scripturl,
						'{FORUMNAME}' => $mbname,
						'{REGARDS}' => replaceBasicActionUrl($txt['regards_team']),
						'{SUBJECT}' => $temp_email[0]['subject'],
						'{ERROR}' => $fullerrortext,
						'{FORUMNAMESHORT}' => (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $mbname),
						'{EMAILREGARDS}' => (!empty($modSettings['maillist_sitename_regards']) ? $modSettings['maillist_sitename_regards'] : ''),
					));
				}
			}
			else
				$context['settings_message'] = $txt['badid'];
		}
		else
			$context['settings_message'] = $txt['badid'];

		// Check if they are sending the notice
		if (isset($this->_req->query->bounce) && isset($temp_email))
		{
			checkSession('post');
			validateToken('admin-ml');

			// They did check the box, how else could they have posted
			if (isset($this->_req->post->warn_notify))
			{
				// lets make sure we have the items to send it
				$check_emails = explode('=>', $temp_email[0]['from']);
				$to = trim($check_emails[0]);
				$subject = trim($this->_req->post->warn_sub);
				$body = trim($this->_req->post->warn_body);

				if (empty($body) || empty($subject))
					$context['settings_message'] = $txt['bad_bounce'];
				else
				{
					// Time for someone to get a we're so sorry message!
					sendmail($to, $subject, $body, null, null, false, 5);
					redirectexit('action=admin;area=maillist;bounced');
				}
			}
		}

		// Prepare and show the template
		createToken('admin-ml');
		$context['warning_data'] = array('notify' => '', 'notify_subject' => '', 'notify_body' => '');
		$context['body'] = isset($fullerrortext) ? \BBC\ParserWrapper::getInstance()->parseEmail($fullerrortext) : '';
		$context['item'] = isset($this->_req->post->item) ? $this->_req->post->item : '';
		$context['notice_to'] = $txt['to'] . ' ' . isset($temp_email[0]['from']) ? $temp_email[0]['from'] : '';
		$context['page_title'] = $txt['bounce_title'];
		$context['sub_template'] = 'bounce_email';
	}

	/**
	 * List all the filters in the system
	 *
	 * - Allows to add/edit or delete filters
	 * - Filters are used to alter text in a post, to remove crud that comes with emails
	 * - Filters can be defined as regex, the system will check it for valid syntax
	 * - Accessed by ?action=admin;area=maillist;sa=emailfilters;
	 */
	public function action_list_filters()
	{
		global $context, $scripturl, $txt, $settings, $modSettings;

		$id = 0;

		// Build the listoption array to display the filters
		$listOptions = array(
			'id' => 'email_filter',
			'title' => $txt['filters'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['no_filters'],
			'base_href' => $scripturl . '?action=admin;area=maillist;sa=emailfilters',
			'default_sort_col' => 'name',
			'get_items' => array(
				'function' => array($this, 'load_filter_parser'),
				'params' => array(
					$id,
					'filter'
				),
			),
			'get_count' => array(
				'function' => array($this, 'count_filter_parser'),
				'params' => array(
					$id,
					'filter'
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['filter_name'],
						'style' => 'white-space: nowrap;'
					),
					'data' => array(
						'db' => 'filter_name',
					),
					'sort' => array(
						'default' => 'filter_name, id_filter',
						'reverse' => 'filter_name DESC, id_filter DESC',
					),
				),
				'from' => array(
					'header' => array(
						'value' => $txt['filter_from'],
					),
					'data' => array(
						'db' => 'filter_from',
					),
					'sort' => array(
						'default' => 'filter_from, id_filter',
						'reverse' => 'filter_from DESC, id_filter DESC',
					),
				),
				'to' => array(
					'header' => array(
						'value' => $txt['filter_to'],
						'style' => 'width:10em;',
					),
					'data' => array(
						'db' => 'filter_to',
					),
					'sort' => array(
						'default' => 'filter_to, id_filter',
						'reverse' => 'filter_to DESC, id_filter DESC',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['filter_type'],
					),
					'data' => array(
						'db' => 'filter_type',
					),
					'sort' => array(
						'default' => 'filter_type, id_filter',
						'reverse' => 'filter_type DESC, id_filter DESC',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['message_action'],
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="?action=admin;area=maillist;sa=editfilter;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '">
										<img title="' . $txt['modify'] . '" src="' . $settings['images_url'] . '/buttons/modify.png" alt="*" />
									</a>
									<a href="?action=admin;area=maillist;sa=deletefilter;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['filter_delete_warning']) . ') && submitThisOnce(this);" accesskey="d">
										<img title="' . $txt['delete'] . '" src="' . $settings['images_url'] . '/buttons/delete.png" alt="*" />
									</a>',
							'params' => array(
								'id_filter' => true,
							),
						),
						'class' => 'centertext',
						'style' => 'white-space:nowrap;',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=maillist;sa=editfilter;',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => isset($this->_req->query->saved) ? 'top_of_list' : 'after_title',
					'value' => isset($this->_req->query->saved) ? '<div class="successbox">' . $txt['saved'] . '</div>' : $txt['filters_title'],
				),
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '<input type="submit" name="addfilter" value="' . $txt['add_filter'] . '" />
						<a class="linkbutton" href="' . $scripturl . '?action=admin;area=maillist;sa=sortfilters">' . $txt['sort_filter'] . '</a>',
				),
			),
		);

		// Set the context values
		$context['page_title'] = $txt['filters'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'email_filter';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Show a full list of all the filters in the system for drag/drop sorting
	 */
	public function action_sort_filters()
	{
		global $context, $scripturl, $txt;

		$id = 0;
		$token = createToken('admin-sort');

		// build the listoption array to display the data
		$listOptions = array(
			'id' => 'sort_email_fp',
			'title' => $txt['sort_filter'],
			'sortable' => true,
			'items_per_page' => 0,
			'no_items_label' => $txt['no_filters'],
			'base_href' => $scripturl . '?action=admin;area=maillist;sa=sortfilters',
			'get_items' => array(
				'function' => array($this, 'load_filter_parser'),
				'params' => array(
					$id,
					'filter'
				),
			),
			'get_count' => array(
				'function' => array($this, 'count_filter_parser'),
				'params' => array(
					$id,
					'filter'
				),
			),
			'columns' => array(
				'filterorder' => array(
					'header' => array(
						'value' => '',
						'class' => 'hide',
					),
					'data' => array(
						'db' => 'filter_order',
						'class' => 'hide',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['filter_name'],
						'style' => 'white-space: nowrap;width: 10em'
					),
					'data' => array(
						'db' => 'filter_name',
					),
				),
				'from' => array(
					'header' => array(
						'value' => $txt['filter_from'],
					),
					'data' => array(
						'db' => 'filter_from',
					),
				),
				'to' => array(
					'header' => array(
						'value' => $txt['filter_to'],
						'style' => 'width:10em;',
					),
					'data' => array(
						'db' => 'filter_to',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['filter_type'],
					),
					'data' => array(
						'db' => 'filter_type',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=maillist;sa=sortfilters',
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['filter_sort_description'],
				),
			),
			'javascript' => '
				$().elkSortable({
					sa: "parserorder",
					placeholder: "ui-state-highlight",
					containment: "#sort_email_fp",
					error: "' . $txt['admin_order_error'] . '",
					title: "' . $txt['admin_order_title'] . '",
					href: "?action=admin;area=maillist;sa=sortfilters",
					token: {token_var: "' . $token['admin-sort_token_var'] . '", token_id: "' . $token['admin-sort_token'] . '"}
				});
			',
		);

		// Set the context values
		$context['page_title'] = $txt['filters'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'sort_email_fp';
		$context[$context['admin_menu_name']]['current_subsection'] = 'emailfilters';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Returns the number of filters or parsers in the system
	 *
	 * - Callback for createList()
	 *
	 * @param int $id 0 for all of a certain style
	 * @param string $style one of filter or parser
	 */
	public function count_filter_parser($id, $style)
	{
		return list_count_filter_parser($id, $style);
	}

	/**
	 * Returns the details for the filters or parsers in the system
	 *
	 * - Callback for createList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $id
	 * @param string $style
	 */
	public function load_filter_parser($start, $items_per_page, $sort, $id, $style)
	{
		return list_get_filter_parser($start, $items_per_page, $sort, $id, $style);
	}

	/**
	 * Edit or Add a filter
	 *
	 * - If regex will check for proper syntax before saving to the database
	 */
	public function action_edit_filters()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Editing an existing filter?
		if (isset($this->_req->query->f_id))
		{
			// Needs to be an int!
			$id = (int) $this->_req->query->f_id;
			if (empty($id) || $id <= 0)
				Errors::instance()->fatal_lang_error('error_no_id_filter');

			// Load it up and set it as the current values
			$row = maillist_load_filter_parser($id, 'filter');
			$modSettings['id_filter'] = $row['id_filter'];
			$modSettings['filter_type'] = $row['filter_type'];
			$modSettings['filter_to'] = $row['filter_to'];
			$modSettings['filter_from'] = $row['filter_from'];
			$modSettings['filter_name'] = $row['filter_name'];

			// Some items for the form
			$context['page_title'] = $txt['edit_filter'];
			$context['editing'] = true;
			$context['settings_message'] = array();
		}
		else
		{
			// Setup place holders for adding a new one instead
			$modSettings['filter_type'] = '';
			$modSettings['filter_to'] = '';
			$modSettings['filter_from'] = '';
			$modSettings['filter_name'] = '';

			$context['page_title'] = $txt['add_filter'];
			$context['editing'] = false;
			$context['settings_message'] = array();
		}

		// Saving the new or edited entry?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_filter_settings');

			// Editing an entry?
			$editId = (isset($this->_req->query->edit)) ? (int) $this->_req->query->edit : -1;
			$editName = (isset($this->_req->query->edit)) ? 'id_filter' : '';

			// If its regex we do a quick check to see if its valid or not
			if ($this->_req->post->filter_type === 'regex')
			{
				$valid = (@preg_replace($this->_req->post->filter_from, $this->_req->post->filter_to, 'ElkArte') === null) ? false : true;
				if (!$valid)
				{
					// Seems to be bad ... reload the form, set the message
					$context['error_type'] = 'notice';
					$context['settings_message'][] = $txt['regex_invalid'];
					$modSettings['filter_type'] = $this->_req->post->filter_type;
					$modSettings['filter_to'] = $this->_req->post->filter_to;
					$modSettings['filter_from'] = $this->_req->post->filter_from;
					$modSettings['filter_name'] = $this->_req->post->filter_name;
				}
			}

			if (empty($this->_req->post->filter_type) || empty($this->_req->post->filter_from))
			{
				$context['error_type'] = 'notice';
				$context['settings_message'][] = $txt['filter_invalid'];
			}

			// if we are good to save, so save it ;)
			if (empty($context['settings_message']))
			{
				// And ... its a filter
				$configVars[] = array('text', 'filter_style');
				$this->_req->post->filter_style = 'filter';

				Email_Settings::saveTableSettings($configVars, 'postby_emails_filters', $this->_req->post, array('id_filter'), $editId, $editName);
				writeLog();
				redirectexit('action=admin;area=maillist;sa=emailfilters;saved');
			}
		}

		// Prepare some final context for the template
		$title = !empty($this->_req->query->saved) ? 'saved_filter' : ($context['editing'] == true ? 'edit_filter' : 'add_filter');
		$context['post_url'] = $scripturl . '?action=admin;area=maillist;sa=editfilter' . ($context['editing'] ? ';edit=' . $modSettings['id_filter'] : ';new') . ';save';
		$context['settings_title'] = $txt[$title];
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=maillist;sa=editfilter',
			'name' => ($context['editing']) ? $txt['edit_filter'] : $txt['add_filter'],
		);
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt[$title],
			'description' => $txt['filters_title'],
		);
		$context[$context['admin_menu_name']]['current_subsection'] = 'emailfilters';

		// Load and show
		Email_Settings::prepare_db($configVars);
		loadTemplate('Admin');
		loadCSSFile('admin.css');
		$context['sub_template'] = 'show_settings';
	}

	/**
	 * Initialize Mailist settings form.
	 */
	private function _initFiltersSettingsForm()
	{
		global $txt;

		// Instantiate the extended parser form
		$settingsForm = new Email_Settings();

		// Set up the configVars for the form
		$configVars = array(
			array('text', 'filter_name', 25, 'subtext' => $txt['filter_name_desc']),
			array('select', 'filter_type',
				array(
					'standard' => $txt['option_standard'],
					'regex' => $txt['option_regex'],
				),
			),
			array('large_text', 'filter_from', 4, 'subtext' => $txt['filter_from_desc']),
			array('text', 'filter_to', 25, 'subtext' => $txt['filter_to_desc']),
		);

		call_integration_hook('integrate_modify_maillist_filter_settings', array(&$configVars));

		$settingsForm->settings($configVars);
	}

	/**
	 * Deletes a filter from the system / database
	 */
	public function action_delete_filters()
	{
		// Removing the filter?
		if (isset($this->_req->query->f_id))
		{
			checkSession('get');
			$id = (int) $this->_req->query->f_id;

			maillist_delete_filter_parser($id);
			redirectexit('action=admin;area=maillist;sa=emailfilters;deleted');
		}
	}

	/**
	 * Show a list of all the parsers in the system
	 *
	 * - Allows to add/edit or delete parsers
	 * - Parsers are used to split a message at a line of text
	 * - Parsers can only be defined as regex, the system will check it for valid syntax
	 * - Accessed by ?action=admin;area=maillist;sa=emailparser;
	 */
	public function action_list_parsers()
	{
		global $context, $scripturl, $txt, $settings, $modSettings;

		$id = 0;

		// Build the listoption array to display the data
		$listOptions = array(
			'id' => 'email_parser',
			'title' => $txt['parsers'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['no_parsers'],
			'base_href' => $scripturl . '?action=admin;area=maillist;sa=emailparser',
			'get_items' => array(
				'function' => array($this, 'load_filter_parser'),
				'params' => array(
					$id,
					'parser'
				),
			),
			'get_count' => array(
				'function' => array($this, 'count_filter_parser'),
				'params' => array(
					$id,
					'parser'
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['parser_name'],
						'style' => 'white-space: nowrap;'
					),
					'data' => array(
						'db' => 'filter_name',
					),
					'sort' => array(
						'default' => 'filter_name',
						'reverse' => 'filter_name DESC',
					),
				),
				'from' => array(
					'header' => array(
						'value' => $txt['parser_from'],
					),
					'data' => array(
						'db' => 'filter_from',
					),
					'sort' => array(
						'default' => 'filter_from',
						'reverse' => 'filter_from DESC',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['parser_type'],
					),
					'data' => array(
						'db' => 'filter_type',
					),
					'sort' => array(
						'default' => 'filter_type',
						'reverse' => 'filter_type DESC',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['message_action'],
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="?action=admin;area=maillist;sa=editparser;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '">
										<img title="' . $txt['modify'] . '" src="' . $settings['images_url'] . '/buttons/modify.png" alt="*" />
									</a>
									<a href="?action=admin;area=maillist;sa=deleteparser;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['parser_delete_warning']) . ') && submitThisOnce(this);" accesskey="d">
										<img title="' . $txt['delete'] . '" src="' . $settings['images_url'] . '/buttons/delete.png" alt="*" />
									</a>',
							'params' => array(
								'id_filter' => true,
							),
						),
						'class' => 'centertext',
						'style' => 'white-space:nowrap;',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=maillist;sa=editparser',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => isset($this->_req->query->saved) ? 'top_of_list' : 'after_title',
					'value' => isset($this->_req->query->saved) ? '<div class="successbox">' . $txt['saved'] . '</div>' : $txt['parsers_title'],
				),
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '
						<input type="submit" name="addparser" value="' . $txt['add_parser'] . '" class="right_submit" />
						<a class="linkbutton" href="' . $scripturl . '?action=admin;area=maillist;sa=sortparsers">' . $txt['sort_parser'] . '</a>',
					),
			),
		);

		// Set the context values
		$context['page_title'] = $txt['parsers'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'email_parser';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Show a full list of all the parsers in the system for drag/drop sorting
	 */
	public function action_sort_parsers()
	{
		global $context, $scripturl, $txt;

		$id = 0;
		$token = createToken('admin-sort');

		// Build the listoption array to display the data
		$listOptions = array(
			'id' => 'sort_email_fp',
			'title' => $txt['sort_parser'],
			'sortable' => true,
			'items_per_page' => 0,
			'no_items_label' => $txt['no_parsers'],
			'base_href' => $scripturl . '?action=admin;area=maillist;sa=sortparsers',
			'default_sort_col' => 'filterorder',
			'get_items' => array(
				'function' => array($this, 'load_filter_parser'),
				'params' => array(
					$id,
					'parser'
				),
			),
			'get_count' => array(
				'function' => array($this, 'count_filter_parser'),
				'params' => array(
					$id,
					'parser'
				),
			),
			'columns' => array(
				'filterorder' => array(
					'header' => array(
						'value' => '',
						'class' => 'hide',
					),
					'data' => array(
						'db' => 'filter_order',
						'class' => 'hide',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['parser_name'],
						'style' => 'white-space: nowrap;width: 10em'
					),
					'data' => array(
						'db' => 'filter_name',
					),
				),
				'from' => array(
					'header' => array(
						'value' => $txt['parser_from'],
					),
					'data' => array(
						'db' => 'filter_from',
					),
				),
				'type' => array(
					'header' => array(
						'value' => $txt['parser_type'],
					),
					'data' => array(
						'db' => 'filter_type',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=maillist;sa=sortparsers',
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['parser_sort_description'],
				),
			),
			'javascript' => '
				$().elkSortable({
					sa: "parserorder",
					placeholder: "ui-state-highlight",
					containment: "#sort_email_fp",
					error: "' . $txt['admin_order_error'] . '",
					title: "' . $txt['admin_order_title'] . '",
					href: "?action=admin;;area=maillist;sa=sortparsers",
					token: {token_var: "' . $token['admin-sort_token_var'] . '", token_id: "' . $token['admin-sort_token'] . '"}
				});
			',
		);

		// Set the context values
		$context['page_title'] = $txt['parsers'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'sort_email_fp';
		$context[$context['admin_menu_name']]['current_subsection'] = 'emailparser';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Adds or Edits an existing parser
	 *
	 * - All parsers are assumed regex
	 */
	public function action_edit_parsers()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Editing an existing filter?
		if (isset($this->_req->query->f_id))
		{
			// Needs to be an int!
			$id = (int) $this->_req->query->f_id;
			if (empty($id) || $id < 0)
				Errors::instance()->fatal_lang_error('error_no_id_filter');

			// Load this filter so we can edit it
			$row = maillist_load_filter_parser($id, 'parser');

			$modSettings['id_filter'] = $row['id_filter'];
			$modSettings['filter_type'] = $row['filter_type'];
			$modSettings['filter_from'] = $row['filter_from'];
			$modSettings['filter_name'] = $row['filter_name'];

			$context['page_title'] = $txt['edit_parser'];
			$context['editing'] = true;
		}
		else
		{
			// Setup place holders for adding a new one instead
			$modSettings['filter_type'] = '';
			$modSettings['filter_name'] = '';
			$modSettings['filter_from'] = '';

			// To the template we go
			$context['page_title'] = $txt['add_parser'];
			$context['editing'] = false;
		}

		// Initialize the mailparser settings form
		$this->_initParsersSettingsForm();

		// Initialize it with our settings
		$configVars = $settingsForm->settings();

		// Check if they are saving the changes
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_parser_settings');

			// Editing a parser?
			$editId = isset($this->_req->query->edit) ? (int) $this->_req->query->edit : -1;
			$editName = isset($this->_req->query->edit) ? 'id_filter' : '';

			// Test the regex
			if ($this->_req->post->filter_type === 'regex' && !empty($this->_req->post->filter_from))
			{
				$valid = (preg_replace($this->_req->post->filter_from, '', 'ElkArte') === null) ? false : true;
				if (!$valid)
				{
					// Regex did not compute .. Danger, Will Robinson
					$context['settings_message'] = $txt['regex_invalid'];
					$context['error_type'] = 'notice';

					$modSettings['filter_type'] = $this->_req->post->filter_type;
					$modSettings['filter_from'] = $this->_req->post->filter_from;
					$modSettings['filter_name'] = $this->_req->post->filter_name;
				}
			}

			if (empty($this->_req->post->filter_type) || empty($this->_req->post->filter_from))
			{
				$context['error_type'] = 'notice';
				$context['settings_message'][] = $txt['filter_invalid'];
			}

			// All clear to save?
			if (empty($context['settings_message']))
			{
				// Shhh ... its really a parser
				$configVars[] = array('text', 'filter_style');
				$this->_req->post->filter_style = 'parser';

				// Save, log, show
				Email_Settings::saveTableSettings($configVars, 'postby_emails_filters', $this->_req->post, array('id_filter'), $editId, $editName);
				writeLog();
				redirectexit('action=admin;area=maillist;sa=emailparser;saved');
			}
		}

		// Prepare the context for viewing
		$title = ((isset($this->_req->query->saved) && $this->_req->query->saved == '1') ? 'saved_parser' : ($context['editing'] == true ? 'edit_parser' : 'add_parser'));
		$context['settings_title'] = $txt[$title];
		$context['post_url'] = $scripturl . '?action=admin;area=maillist;sa=editparser' . ($context['editing'] ? ';edit=' . $modSettings['id_filter'] : ';new') . ';save';
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=maillist;sa=editparser',
			'name' => ($context['editing']) ? $txt['edit_parser'] : $txt['add_parser'],
		);
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt[$title],
			'description' => $txt['parsers_title'],
		);
		$context[$context['admin_menu_name']]['current_subsection'] = 'emailparser';

		// prep it, load it, show it
		Email_Settings::prepare_db($configVars);
		loadTemplate('Admin');
		loadCSSFile('admin.css');
		$context['sub_template'] = 'show_settings';
	}

	/**
	 * Initialize Mailist settings form.
	 */
	private function _initParsersSettingsForm()
	{
		global $txt;

		// Instantiate the extended parser form
		$settingsForm = new Email_Settings();

		// Define the menu array
		$configVars = array(
			array('text', 'filter_name', 25, 'subtext' => $txt['parser_name_desc']),
			array('select', 'filter_type', 'subtext' => $txt['parser_type_desc'],
				array(
					'regex' => $txt['option_regex'],
					'standard' => $txt['option_standard'],
				),
			),
			array('large_text', 'filter_from', 4, 'subtext' => $txt['parser_from_desc']),
		);

		call_integration_hook('integrate_modify_maillist_parser_settings', array(&$configVars));

		$settingsForm->settings($configVars);
	}

	/**
	 * Removes a parser from the system and database
	 */
	public function action_delete_parsers()
	{
		// Removing the filter?
		if (isset($this->_req->query->f_id))
		{
			checkSession('get');
			$id = (int) $this->_req->query->f_id;

			maillist_delete_filter_parser($id);
			redirectexit('action=admin;area=maillist;sa=emailparser;deleted');
		}
	}

	/**
	 * All the post by email settings, used to control how the feature works
	 *
	 * @uses Admin language
	 */
	public function action_settings()
	{
		global $scripturl, $context, $txt, $modSettings;

		// Be nice, show them we did something
		if (isset($this->_req->query->saved))
			$context['settings_message'] = $txt['saved'];

		// Templates and language
		loadLanguage('Admin');
		loadTemplate('Admin');
		loadCSSFile('admin.css');

		// Load any existing email => board values used for new topic creation
		$context['maillist_from_to_board'] = array();
		$data = (!empty($modSettings['maillist_receiving_address'])) ? Util::unserialize($modSettings['maillist_receiving_address']) : array();
		foreach ($data as $key => $addr)
		{
			$context['maillist_from_to_board'][$key] = array(
				'id' => $key,
				'emailfrom' => $addr[0],
				'boardto' => $addr[1],
			);
		}

		// Initialize the maillist settings form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$configVars = $this->_settings();

		$settingsForm->settings($configVars);

		// Saving settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_maillist_settings');

			$email_error = false;
			$board_error = false;
			$maillist_receiving_address = array();

			// Basic checking of the email addresses
			if (!Data_Validator::is_valid($this->_req->post, array('maillist_sitename_address' => 'valid_email'), array('maillist_sitename_address' => 'trim')))
				$email_error = $this->_req->post->maillist_sitename_address;
			if (!Data_Validator::is_valid($this->_req->post, array('maillist_sitename_help' => 'valid_email'), array('maillist_sitename_help' => 'trim')))
				$email_error = $this->_req->post->maillist_sitename_help;
			if (!Data_Validator::is_valid($this->_req->post, array('maillist_mail_from' => 'valid_email'), array('maillist_mail_from' => 'trim')))
				$email_error = $this->_req->post->maillist_mail_from;

			// Inbound email set up then we need to check for both valid email and valid board
			if (!$email_error && !empty($this->_req->post->emailfrom))
			{
				// Get the board ids for a quick check
				$boards = maillist_board_list();

				// Check the receiving emails and the board id as well
				$boardtocheck = !empty($this->_req->post->boardto) ? $this->_req->post->boardto : array();
				$addresstocheck = !empty($this->_req->post->emailfrom) ? $this->_req->post->emailfrom : array();

				foreach ($addresstocheck as $key => $checkme)
				{
					// Valid email syntax
					if (!Data_Validator::is_valid($addresstocheck, array($key => 'valid_email'), array($key => 'trim')))
					{
						$email_error = $checkme;
						$context['error_type'] = 'notice';
						continue;
					}

					// Valid board id?
					if (!isset($boardtocheck[$key]) || !isset($boards[$key]))
					{
						$board_error = $checkme;
						$context['error_type'] = 'notice';
						continue;
					}

					// Decipher as [0] emailaddress and [1] board id
					$maillist_receiving_address[] = array($checkme, $boardtocheck[$key]);
				}
			}

			// Enable or disable the fake cron
			enable_maillist_imap_cron(!empty($this->_req->post->maillist_imap_cron));

			// Check and set any errors or give the go ahead to save
			if ($email_error)
				$context['settings_message'] = sprintf($txt['email_not_valid'], $email_error);
			elseif ($board_error)
				$context['settings_message'] = sprintf($txt['board_not_valid'], $board_error);
			else
			{
				// Clear the moderation count cache
				Cache::instance()->remove('num_menu_errors');

				// Should be off if mail posting is on, we ignore it anyway but this at least updates the ACP
				if (!empty($this->_req->post->maillist_enabled))
					updateSettings(array('disallow_sendBody' => ''));

				updateSettings(array('maillist_receiving_address' => serialize($maillist_receiving_address)));
				$settingsForm->setConfigValues((array) $this->_req->post);
				$settingsForm->save();
				writeLog();
				redirectexit('action=admin;area=maillist;sa=emailsettings;saved');
			}
		}

		// Javascript vars for the "add more" buttons in the receive_email callback
		$board_list = maillist_board_list();
		$script = '';
		$i = 0;

		// Create the board selection list
		foreach ($board_list as $board_id => $board_name)
			$script .= $i++ . ': {id:' . $board_id . ', name:' . JavaScriptEscape($board_name) . '},';

		addInlineJavascript('
		var sEmailParent = \'add_more_email_placeholder\',
			oEmailOptionsdt = {size: \'50\', name: \'emailfrom[]\', class: \'input_text\'},
			oEmailOptionsdd = {size: \'1\', type: \'select\', name: \'boardto[]\', class: \'input_select\'},
			oEmailSelectData = {' . $script . '};

			document.getElementById(\'add_more_board_div\').style.display = \'block\';', true
		);

		$context['boards'] = $board_list;
		$context['settings_title'] = $txt['ml_emailsettings'];
		$context['page_title'] = $txt['ml_emailsettings'];
		$context['post_url'] = $scripturl . '?action=admin;area=maillist;sa=emailsettings;save';
		$context['sub_template'] = 'show_settings';
		$settingsForm->prepare();
	}

	/**
	 * Load up the config var array for settings display etc.
	 */
	private function _settings()
	{
		global $txt;

		// Define the menu
		$configVars = array(
				array('desc', 'maillist_help'),
				array('check', 'maillist_enabled'),
				array('check', 'pbe_post_enabled'),
				array('check', 'pbe_pm_enabled'),
				array('check', 'pbe_no_mod_notices', 'subtext' => $txt['pbe_no_mod_notices_desc'], 'postinput' => $txt['recommended']),
				array('check', 'pbe_bounce_detect', 'subtext' => $txt['pbe_bounce_detect_desc'], 'postinput' => $txt['experimental']),
				array('check', 'pbe_bounce_record', 'subtext' => $txt['pbe_bounce_record_desc'], 'postinput' => $txt['experimental']),
			array('title', 'maillist_outbound'),
				array('desc', 'maillist_outbound_desc'),
				array('check', 'maillist_group_mode'),
				array('check', 'maillist_digest_enabled'),
				array('text', 'maillist_sitename', 40, 'subtext' => $txt['maillist_sitename_desc'], 'postinput' => $txt['maillist_sitename_post']),
				array('text', 'maillist_sitename_address', 40, 'subtext' => $txt['maillist_sitename_address_desc'], 'postinput' => $txt['maillist_sitename_address_post']),
				array('text', 'maillist_mail_from', 40, 'subtext' => $txt['maillist_mail_from_desc'], 'postinput' => $txt['maillist_mail_from_post']),
				array('text', 'maillist_sitename_help', 40, 'subtext' => $txt['maillist_sitename_help_desc'], 'postinput' => $txt['maillist_sitename_help_post']),
				array('text', 'maillist_sitename_regards', 40, 'subtext' => $txt['maillist_sitename_regards_desc']),
			array('title', 'maillist_inbound'),
				array('desc', 'maillist_inbound_desc'),
				array('check', 'maillist_newtopic_change'),
				array('check', 'maillist_newtopic_needsapproval', 'subtext' => $txt['maillist_newtopic_needsapproval_desc'], 'postinput' => $txt['recommended']),
				array('callback', 'maillist_receive_email_list'),
			array('title', 'misc'),
				array('check', 'maillist_allow_attachments'),
				array('int', 'maillist_key_active', 2, 'subtext' => $txt['maillist_key_active_desc']),
			'',
				array('text', 'maillist_leftover_remove', 40, 'subtext' => $txt['maillist_leftover_remove_desc']),
				array('text', 'maillist_sig_keys', 40, 'subtext' => $txt['maillist_sig_keys_desc']),
				array('int', 'maillist_short_line', 2, 'subtext' => $txt['maillist_short_line_desc']),
		);

		// Imap?
		if (!function_exists('imap_open'))
			$configVars = array_merge($configVars,
				array(
					array('title', 'maillist_imap_missing'),
				)
			);
		else
			$configVars = array_merge($configVars,
				array(
					array('title', 'maillist_imap'),
					array('desc', 'maillist_imap_reason'),
						array('text', 'maillist_imap_host', 45, 'subtext' => $txt['maillist_imap_host_desc'], 'disabled' => !function_exists('imap_open')),
						array('text', 'maillist_imap_mailbox', 20, 'postinput' => $txt['maillist_imap_mailbox_desc'], 'disabled' => !function_exists('imap_open')),
						array('text', 'maillist_imap_uid', 20, 'postinput' => $txt['maillist_imap_uid_desc'], 'disabled' => !function_exists('imap_open')),
						array('password', 'maillist_imap_pass', 20, 'postinput' => $txt['maillist_imap_pass_desc'], 'disabled' => !function_exists('imap_open')),
						array('select', 'maillist_imap_connection',
							array(
								'imap' => $txt['maillist_imap_unsecure'],
								'pop3' => $txt['maillist_pop3_unsecure'],
								'imaptls' => $txt['maillist_imap_tls'],
								'imapssl' => $txt['maillist_imap_ssl'],
								'pop3tls' => $txt['maillist_pop3_tls'],
								'pop3ssl' => $txt['maillist_pop3_ssl']
							), 'postinput' => $txt['maillist_imap_connection_desc'], 'disabled' => !function_exists('imap_open'),
						),
						array('check', 'maillist_imap_delete', 20, 'subtext' => $txt['maillist_imap_delete_desc'], 'disabled' => !function_exists('imap_open')),
						array('check', 'maillist_imap_cron', 20, 'subtext' => $txt['maillist_imap_cron_desc'], 'disabled' => !function_exists('imap_open')),
				)
			);

		call_integration_hook('integrate_modify_maillist_settings', array(&$configVars));

		return $configVars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * View all the custom email bounce templates.
	 *
	 * - Shows all the bounce templates in the system available to this user
	 * - Provides for actions to add or delete them
	 * - Accessed by ?action=admin;area=maillist;sa=emailtemplates;
	 */
	public function action_view_bounce_templates()
	{
		global $modSettings, $context, $txt, $scripturl;

		// We'll need this, because bounce templates are stored with warning templates.
		require_once(SUBSDIR . '/Moderation.subs.php');

		// Submitting a new one or editing an existing one then pass this request off
		if (isset($this->_req->post->add) || isset($this->_req->post->save) || isset($this->_req->query->tid))
			return $this->action_modify_bounce_templates();
		// Deleting and existing one
		elseif (isset($this->_req->post->delete) && !empty($this->_req->post->deltpl))
		{
			checkSession('post');
			validateToken('mod-mlt');
			removeWarningTemplate($this->_req->post->deltpl, 'bnctpl');
		}

		// This is all the information required for showing the email templates.
		$listOptions = array(
			'id' => 'bounce_template_list',
			'title' => $txt['ml_bounce_templates_title'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['ml_bounce_templates_none'],
			'base_href' => $scripturl . '?action=admin;area=maillist;sa=emailtemplates;' . $context['session_var'] . '=' . $context['session_id'],
			'default_sort_col' => 'title',
			'get_items' => array(
				'function' => array($this, 'list_getBounceTemplates'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getBounceTemplateCount'),
				'params' => array('bnctpl'),
			),
			'columns' => array(
				'title' => array(
					'header' => array(
						'value' => $txt['ml_bounce_templates_name'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=maillist;sa=emailtemplates;tid=%1$d">%2$s</a>',
							'params' => array(
								'id_comment' => false,
								'title' => false,
								'body' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'template_title',
						'reverse' => 'template_title DESC',
					),
				),
				'creator' => array(
					'header' => array(
						'value' => $txt['ml_bounce_templates_creator'],
					),
					'data' => array(
						'db' => 'creator',
					),
					'sort' => array(
						'default' => 'creator_name',
						'reverse' => 'creator_name DESC',
					),
				),
				'time' => array(
					'header' => array(
						'value' => $txt['ml_bounce_templates_time'],
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 4%;',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($rowData) {
							return '<input type="checkbox" name="deltpl[]" value="' . $rowData['id_comment'] . '" class="input_check" />';
						},
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=maillist;sa=emailtemplates',
				'token' => 'mod-mlt',
			),
			'additional_rows' => array(
				array(
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '
					<input type="submit" name="delete" value="' . $txt['ml_bounce_template_delete'] . '" onclick="return confirm(\'' . $txt['ml_bounce_template_delete_confirm'] . '\');" class="right_submit" />
					<input type="submit" name="add" value="' . $txt['ml_bounce_template_add'] . '" class="right_submit" />',
				),
			),
		);

		// Create the template list.
		$context['page_title'] = $txt['ml_bounce_templates_title'];
		createToken('mod-mlt');

		createList($listOptions);

		// Show the list
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'bounce_template_list';
	}

	/**
	 * Edit a 'it bounced' template.
	 *
	 * @uses bounce_template sub template
	 */
	public function action_modify_bounce_templates()
	{
		global $context, $txt, $user_info;

		require_once(SUBSDIR . '/Moderation.subs.php');

		$context['id_template'] = isset($this->_req->query->tid) ? (int) $this->_req->query->tid : 0;
		$context['is_edit'] = (bool) $context['id_template'];

		// Standard template things, you know the drill
		$context['page_title'] = $context['is_edit'] ? $txt['ml_bounce_template_modify'] : $txt['ml_bounce_template_add'];
		$context['sub_template'] = 'bounce_template';
		$context[$context['admin_menu_name']]['current_subsection'] = 'templates';

		// Defaults to show
		$context['template_data'] = array(
			'title' => '',
			'body' => $txt['ml_bounce_template_body_default'],
			'subject' => $txt['ml_bounce_template_subject_default'],
			'personal' => false,
			'can_edit_personal' => true,
		);

		// If it's an edit load it.
		if ($context['is_edit'])
			modLoadTemplate($context['id_template'], 'bnctpl');

		// Wait, we are saving?
		if (isset($this->_req->post->save))
		{
			checkSession('post');
			validateToken('mod-mlt');

			// To check the BBC is good...
			require_once(SUBSDIR . '/Post.subs.php');

			// Bit of cleaning!
			$template_body = trim($this->_req->post->template_body);
			$template_title = trim($this->_req->post->template_title);

			// Need something in both boxes.
			if (!empty($template_body) && !empty($template_title))
			{
				// Safety first.
				$template_title = Util::htmlspecialchars($template_title);

				// Clean up BBC.
				preparsecode($template_body);

				// But put line breaks back!
				$template_body = strtr($template_body, array('<br />' => "\n"));

				// Is this personal?
				$recipient_id = !empty($this->_req->post->make_personal) ? $user_info['id'] : 0;

				// Updating or adding ?
				if ($context['is_edit'])
				{
					// Simple update...
					modAddUpdateTemplate($recipient_id, $template_title, $template_body, $context['id_template'], true, 'bnctpl');

					// If it wasn't visible and now is they've effectively added it.
					if ($context['template_data']['personal'] && !$recipient_id)
						logAction('add_bounce_template', array('template' => $template_title));
					// Conversely if they made it personal it's a delete.
					elseif (!$context['template_data']['personal'] && $recipient_id)
						logAction('delete_bounce_template', array('template' => $template_title));
					// Otherwise just an edit.
					else
						logAction('modify_bounce_template', array('template' => $template_title));
				}
				else
				{
					modAddUpdateTemplate($recipient_id, $template_title, $template_body, $context['id_template'], false, 'bnctpl');
					logAction('add_bounce_template', array('template' => $template_title));
				}

				// Get out of town...
				redirectexit('action=admin;area=maillist;sa=emailtemplates');
			}
			else
			{
				$context['warning_errors'] = array();
				$context['template_data']['title'] = !empty($template_title) ? $template_title : '';
				$context['template_data']['body'] = !empty($template_body) ? $template_body : $txt['ml_bounce_template_body_default'];
				$context['template_data']['personal'] = !empty($this->_req->post->make_personal);

				if (empty($template_title))
					$context['warning_errors'][] = $txt['ml_bounce_template_error_no_title'];

				if (empty($template_body))
					$context['warning_errors'][] = $txt['ml_bounce_template_error_no_body'];
			}
		}

		createToken('mod-mlt');
	}

	/**
	 * Get all the bounce templates from the system
	 *
	 * - Callback for createList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page  The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 */
	public function list_getBounceTemplates($start, $items_per_page, $sort)
	{
		return warningTemplates($start, $items_per_page, $sort, 'bnctpl');
	}

	/**
	 * Get the number of bounce templates in the system
	 *
	 * - Callback for createList() to warningTemplateCount
	 */
	public function list_getBounceTemplateCount()
	{
		return warningTemplateCount('bnctpl');
	}

	/**
	 * Get the number of unapproved emails
	 *
	 * - Callback for createList() to list_maillist_unapproved
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $id = 0
	 */
	public function list_maillist_unapproved($start, $items_per_page, $sort = '', $id = 0)
	{
		return list_maillist_unapproved($id, $start, $items_per_page, $sort);
	}
}
