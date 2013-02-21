<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * functions for showing, repairing, deleting and bouncing failed emails
 * functions for adding / editing / removing filters
 * functions for adding / editing / removing parsers
 * functions for saving the general settings for the function
 *
 */

if (!defined('ELKARTE'))
	die('Hacking attempt...');

/**
 * Main dispatcher.
 * This function checks permissions and passes control to the sub action.
 */
function action_managemaillist()
{
	global $context, $txt;

	// You need to at least have approve_emails permission to do anything
	isAllowedTo(array('approve_emails'));

	// All the functions available
	$subactions = array(
		'emaillist' => array('action_unapproved_email', 'approve_emails'),
		'approve' => array('action_approve_email', 'approve_emails'),
		'delete' =>  array('action_delete_email', 'approve_emails'),
		'bounce' =>  array('action_bounce_email', 'approve_emails'),
		'view' =>  array('action_view_email', 'approve_emails'),
		'emailsettings' =>  array('action_settings', 'admin_forum'),
		'emailfilters' =>  array('action_list_filters', 'admin_forum'),
		'editfilter' =>  array('action_edit_filters', 'admin_forum'),
		'deletefilter' =>  array('action_delete_filters', 'admin_forum'),
		'emailparser' =>  array('action_list_parsers', 'admin_forum'),
		'editparser' =>  array('action_edit_parsers', 'admin_forum'),
		'deleteparser' =>  array('action_delete_parsers', 'admin_forum'),
	);

	// Default to sub action 'emaillist' as a default
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'emaillist';

	// Now you have chosen, so shall we check
	isAllowedTo($subactions[$_REQUEST['sa']][1]);

	// Template
	loadTemplate('Maillist');
	loadLanguage('Maillist');

	// Create the title area for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['ml_admin_configuration'],
		'help' => $txt['maillist_help_short'],
		'description' => $txt['ml_configuration_desc'],
	);

	// Go Play!
	$subactions[$_REQUEST['sa']][0]();
}

/**
 * Main listing of failed emails.
 *  - shows the sender, key and subject of the email
 *  - Will show the found key if it was missing or possible sender if it was wrong
 *  - icons to view, bounce, delete or approve a failure
 *
 */
function action_unapproved_email()
{
	global $context, $scripturl, $modSettings, $txt, $settings;

	// Set an id if none was supplied
	$id = (isset($_REQUEST['e_id']) ? (int) $_REQUEST['e_id'] : 0);
	if (empty($id) || $id <= 0)
		$id = 0;

	require_once(SUBSDIR . '/Maillist.subs.php');
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
			'function' => 'list_maillist_unapproved',
			'params' => array(
				$id,
			),
		),
		'get_count' => array(
			'function' => 'list_maillist_count_unapproved',
			'params' => array(
				$id,
			),
		),
		'columns' => array(
			'id_email' => array(
				'header' => array(
					'value' => $txt['id'],
				),
				'data' => array(
					'db' => 'id_email',
					'style' => 'width: 2.2em;',
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
					'db' => 'error',
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
				),
				'sort' => array(
					'default' => 'data_id',
					'reverse' => 'data_id DESC',
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
						'function' => create_function('$rowData', '
						global $txt;

						// Do we have a type?
						if (empty($rowData[\'type\']))
							return $txt[\'not_applicable\'];
						// Personal?
						elseif ($rowData[\'type\'] === \'p\')
							return $txt[\'personal_message\'];
						// New Topic?
						elseif ($rowData[\'type\'] === \'x\')
							return $txt[\'new_topic\'];
						// Ah a Reply then
						else
							return $txt[\'topic\'] . \' \' . $txt[\'reply\'];
					'),
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
					'sprintf' => array(
						'format' => '<a href="?action=admin;area=maillist;sa=approve;item=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-ml_token_var'] . '=' . $context['admin-ml_token'] . '"><img width="16" height="16" title="' . $txt['approve'] . '" src="' . $settings['images_url'] . '/icons/field_valid.png" alt="*" /></a>&nbsp;
						<a href="?action=admin;area=maillist;sa=delete;item=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-ml_token_var'] . '=' . $context['admin-ml_token'] . '" onclick="return confirm(' . JavaScriptEscape($txt['delete_warning']) . ') && submitThisOnce(this);" accesskey="d"><img width="16" height="16" title="' . $txt['delete'] . '" src="' . $settings['images_url'] . '/icons/quick_remove.png" alt="*" /></a><br />
						<a href="?action=admin;area=maillist;sa=bounce;item=%1$s;'. $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-ml_token_var'] . '=' . $context['admin-ml_token'] . '"><img width="16" height="16" title="' . $txt['bounce'] . '" src="' . $settings['images_url'] . '/icons/pm_replied.png" alt="*" /></a>&nbsp;
						<a href="?action=admin;area=maillist;sa=view;item=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-ml_token_var'] . '=' . $context['admin-ml_token'] . '"><img width="16" height="16" title="' . $txt['view'] . '" src="' . $settings['images_url'] . '/icons/pm_read.png" alt="*" /></a>',
						'params' => array(
							'id_email' => true,
						),
					),
					'class' => 'centercol',
				),
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
				'value' => isset($_SESSION['email_error']) ? '<div class="errorbox">' . $_SESSION['email_error'] . '</div>' : $txt['heading'],
				'class' => 'windowbg2',
			),
		),
	);

	// Clear any errors
	unset($_SESSION['email_error']);

	// Set the context values for the template
	$context['page_title'] = $txt['emailerror_title'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'view_email_errors';

	// Create the list.
	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);
}

/**
 * Show a failed email for review by the moderation team
 *  - Will not show a PM if it has been identified as such
 *
 */
function action_view_email()
{
	global $txt, $context;

	allowedTo('approve_emails');
	checkSession('get');
	validateToken('admin-ml', 'get');

	$id = (int) $_GET['item'];

	if (!empty($id))
	{
		require_once(SUBSDIR . '/Maillist.subs.php');

		// load up the email details, no funny biz ;)
		$temp_email = list_maillist_unapproved('', '', '', $id);

		if (!empty($temp_email))
		{
			if ($temp_email[0]['type'] !== 'p' && allowedTo('approve_emails'))
			{
				$data = (int) $temp_email[0]['body'];

				// Read/parse this message for viewing
				require_once(CONTROLLERDIR . '/Emailpost.controller.php');
				$result = pbe_preview($data);
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

	// prep and show the template with what we found
	$context['body'] = parse_bbc($text);
	$context['to'] = $txt['to'] . ' ' . (isset($email_to) ? $email_to : '');
	$context['notice_subject'] = (isset($temp_email[0]['subject']) ? $txt['subject'] . ': ' . $temp_email[0]['subject'] : '');
	$context['notice_from'] = (isset($temp_email[0]['from']) ? $txt['from'] . ': ' . $temp_email[0]['from'] : '');
	$context['page_title'] = $txt['show_notice'];
	$context['error_code'] = $txt[$temp_email[0]['error_code']];
	$context['sub_template'] = 'show_email';
}

/**
 * Deletes an entry from the database
 * Flushes the menu num cache to the menu numbers update
 */
function action_delete_email()
{
	allowedTo('approve_emails');
	checkSession('get');
	validateToken('admin-ml', 'get');

	$id = (int) $_GET['item'];
	if (!empty($id))
	{
		// remove this entry
		require_once(SUBSDIR . '/Maillist.subs.php');
		maillist_delete_entry($id);
	}

	// Flush the cache
	cache_put_data('num_menu_errors', null, 900);

	// Back to the failed list we go
	redirectexit('action=admin;area=maillist;sa=emaillist');
}

/**
 * Attempts to approve and post a failed email
 *  - Reviews the data to see if the email error function fixed typical issues like key and wrong id
 *  - Submits the fixed email to the main function which will post it or fail it again
 *  - If successful will remove the entry from the failed log
 *
 */
function action_approve_email()
{
	global $txt;

	allowedTo('approve_emails');
	checkSession('get');
	validateToken('admin-ml', 'get');

	// Get the id to approve
	$id = (int) $_GET['item'];

	if (!empty($id) && $id !== -1)
	{
		// load up the email data
		require_once(SUBSDIR . '/Maillist.subs.php');
		$temp_email = list_maillist_unapproved('', '', '', $id);
		if (!empty($temp_email))
		{
			// Do we have the needed data to approve this, after all it failed for a reason yes?
			if (!empty($temp_email[0]['key']) && (!in_array($temp_email[0]['error_code'], array('error_pm_not_found', 'error_no_message', 'error_not_find_board', 'error_topic_gone'))))
			{
				// Set up the details needed to get this posted
				$force = true;
				$key = $temp_email[0]['key'] . $temp_email[0]['type'] . $temp_email[0]['message'];
				$data = $temp_email[0]['body'];

				// Unknown from email?  Update the message ONLY if we found an appropriate one during the error checking process
				if ($temp_email[0]['error_code'] == 'error_not_find_member')
				{
					// did we actually find a potential correct name?
					$check_emails = explode('=>', $temp_email[0]['from']);
					if (isset($check_emails[1]))
						$data = str_ireplace('From: ' . trim($check_emails[0]), 'From: ' . trim($check_emails[1]), $data);
				}

				// Lets TRY AGAIN to make a post!
				include_once(CONTROLLERDIR . '/Emailpost.controller.php');
				$text = pbe_main($data, $force, $key);

				// Assuming all went well, remove this entry and file since we are done.
				if ($text === true)
				{
					maillist_delete_entry($id);

					// flush the cache
					cache_put_data('num_menu_errors', null, 900);
				}
				$_SESSION['email_error'] = $txt['approved'];
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
 * Allows the admin to choose from predefined templates and send a bounce notice
 * to the sender with the error that was generated.
 *
 */
function action_bounce_email()
{
	global $context, $txt;

	if (!isset($_REQUEST['bounce']))
	{
		checkSession('get');
		validateToken('admin-ml', 'get');
	}

	// Bounce templates
	$bounce = array('bounce', 'inform');

	require_once(SUBSDIR . '/Mail.subs.php');
	require_once(SUBSDIR . '/Maillist.subs.php');

	// We should have been sent an email ID
	if (isset($_REQUEST['item']))
	{
		// Needs to be an int!
		$id = (int) $_REQUEST['item'];

		// Load up the email details, no funny biz yall ;)
		$temp_email = list_maillist_unapproved('', '', '', $id);

		if (!empty($temp_email))
		{
			// set the options
			$_POST['item'] = (int) $temp_email[0]['id_email'];
			$fullerrortext = $txt[$temp_email[0]['error_code']];
			$shorterrortext = $temp_email[0]['error'];

			// Additional replacements.
			$replacements = array(
				'SUBJECT' => $temp_email[0]['subject'],
				'ERROR' => $fullerrortext,
				'MEMBER' => $temp_email[0]['name'],
			);

			foreach ($bounce as $k => $type)
			{
				$temp = loadEmailTemplate('bounce_' . $type, $replacements, $temp_email[0]['language']);
				$context['bounce_templates'][$k]['body'] = $temp['body'];
				$context['bounce_templates'][$k]['subject'] = $temp['subject'];
				$context['bounce_templates'][$k]['title'] = $txt['bounce_' . $type . '_title'];
			}
		}
		else
			$context['settings_message'] = $txt['badid'];
	}
	else
		$context['settings_message'] = $txt['badid'];

	// Check if they are sending the note
	if (isset($_REQUEST['bounce']))
	{
		checkSession('post');

		// They did check the box, how else could they have posted
		if (isset($_POST['warn_notify']))
		{
			// lets make sure we have the items to send a nice bounce mail
			$check_emails = explode('=>', $temp_email[0]['from']);
			$to = trim($check_emails[0]);
			$subject = trim($_POST['warn_sub']);
			$body = trim($_POST['warn_body']);

			if (empty($body) || empty($subject))
				$context['settings_message'] = $txt['bad_bounce'];
			else
			{
				// Time for someone to get a so sorry message!
				sendmail($to, $subject, $body, null, null, false, 5);
				redirectexit('action=admin;area=maillist;bounced');
			}
		}
	}

	// Prep and show the template
	$context['warning_data'] = array('notify' => '', 'notify_subject' => '', 'notify_body' => '');
	$context['body'] = parse_bbc($fullerrortext);
	$context['item'] = isset($_POST['item']) ? $_POST['item'] : '';
	$context['notice_to'] = $txt['to'] . ' ' . $temp_email[0]['from'];
	$context['page_title'] = $txt['bounce_title'];
	$context['sub_template'] = 'bounce_email';
}

/**
 * List all the filters in the system
 * - allows to add/edit or delete filters
 * - filters are used to alter text in a post, to remove crud that comes with emails
 * - filters can be defined as regex, the system will check it for valid syntax
 * - uses list_get_filter_parser for the data and list_count_filter_parser for the number
 *
 */
function action_list_filters()
{
	global $context, $scripturl, $txt, $settings, $modSettings;

	$id = 0;
	require_once(SUBSDIR . '/Maillist.subs.php');

	// build the listoption array to display the filters
	$listOptions = array(
		'id' => 'email_filter',
		'title' => $txt['filters'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_filters'],
		'base_href' => $scripturl . '?action=admin;area=maillist;sa=emailfilters',
		'default_sort_col' => 'name',
		'get_items' => array(
			'function' => 'list_get_filter_parser',
			'params' => array(
				$id,
				'filter'
			),
		),
		'get_count' => array(
			'function' => 'list_count_filter_parser',
			'params' => array(
				$id,
				'filter'
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['filter_name'],
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
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="?action=admin;area=mailist;sa=editfilter;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '"><img width="8" height="16" title="' . $txt['modify'] . '" src="' . $settings['images_url'] . '/icons/modify_small.png" alt="*" /></a>&nbsp;<a href="?action=admin;area=maillist;sa=deletefilter;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['filter_delete_warning']) . ') && submitThisOnce(this);" accesskey="d"><img width="16" height="16" title="' . $txt['delete'] . '" src="' . $settings['images_url'] . '/icons/delete.png" alt="*" /></a>',
						'params' => array(
							'id_filter' => true,
						),
					),
					'class' => 'centercol',
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
				'position' => 'after_title',
				'value' => isset($_GET['saved']) ? '<img align="top" src="' . $settings['images_url'] . '/icons/field_valid.png" alt="" />&nbsp;' . $txt['saved'] : $txt['filters_title'],
				'class' => 'windowbg2',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="addfilter" value="' . $txt['add_filter'] . '" class="button_submit" />',
			),
		),
	);

	// Set the context values
	$context['page_title'] = $txt['emailerror_title'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'email_filter';

	// Create the list.
	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);
}

/**
 * Edit or Add a filter
 *  - if regex will check for proper syntax before saving to the database
 *
 */
function action_edit_filters()
{
	global $context, $scripturl, $txt, $modSettings;

	// Editing an existing filter?
	if (isset($_REQUEST['f_id']))
	{
		// Needs to be an int!
		$id = (int) $_REQUEST['f_id'];
		if (empty($id) || $id <= 0)
			fatal_lang_error('error_no_id_filter');

		// Load it up and set it as the current values
		$row = maillist_load_filter_parser($id, 'filter');
		$modSettings['id_filter'] = $row['id_filter'];
		$modSettings['filter_type'] = $row['filter_type'];
		$modSettings['filter_to'] = $row['filter_to'];
		$modSettings['filter_from'] = $row['filter_from'];
		$modSettings['filter_name'] = $row['filter_name'];

		// Some items for the form
		$context['editing'] = true;
		$context['settings_message'] = array();
		$context['page_title'] = $txt['edit_filter'];
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

	// Set up the config_vars for the form
	$config_vars = array(
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

	// Yup, need to make sure these are available
	loadAdminClass('ManagePermissions.php');
	loadAdminClass('ManageServer.php');

	// Saving the new or edited entry?
	if (isset($_GET['save']))
	{
		checkSession();

		// Editing an entry?
		$editid = (isset($_GET['edit'])) ? (int) $_GET['edit'] : -1;
		$editname = (isset($_GET['edit'])) ? 'id_filter' : '';

		// If its regex we do a quick check to see if its valid or not
		if ($_POST['filter_type'] === 'regex')
		{
			$valid = (@preg_replace($_POST['filter_from'], $_POST['filter_to'], '12@$%^*(09#98&76') === null) ? false : true;
			if (!$valid)
			{
				// Seems to be bad ... reload the form, set the message
				$context['error_type'] = 'notice';
				$context['settings_message'][] =  $txt['regex_invalid'];
				$modSettings['filter_type'] = $_POST['filter_type'];
				$modSettings['filter_to'] = $_POST['filter_to'];
				$modSettings['filter_from'] = $_POST['filter_from'];
				$modSettings['filter_name'] = $_POST['filter_name'];
			}
		}

		if (empty($_POST['filter_type']) || empty($_POST['filter_from']))
		{
			$context['error_type'] = 'notice';
			$context['settings_message'][] = $txt['filter_invalid'];
		}

		// if we are good to save, so save it ;)
		if (empty($context['settings_message']))
		{
			saveTableSettings($config_vars, 'postby_emails_filters', array() ,$editid, $editname);
			redirectexit('action=admin;area=maillist;sa=emailfilters;saved');
		}
	}

	// Prepare some final context for the template
	$title = !empty($_GET['saved']) ? 'saved_filter' : ($context['editing'] == true ? 'edit_filter' : 'add_filter');
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
	prepareDBSettingContext($config_vars);
	loadTemplate('Admin', 'admin');
	$context['sub_template'] = 'show_settings';
}

/**
 * Deletes a filter from the system / database
 *
 */
function action_delete_filters()
{
	// Removing the filter?
	if (isset($_GET['f_id']))
	{
		checkSession('get');
		$id = (int) $_GET['f_id'];

		maillist_delete_filter_parser($id);
		redirectexit('action=admin;area=maillist;sa=emailfilters;deleted');
	}
}

/**
 * Show a list of all the parsers in the system
 * - allows to add/edit or delete parsers
 * - parsers are used to split a message at a line of text
 * - parsers can only be defined as regex, the system will check it for valid syntax
 * - uses list_get_filter_parser for the data and list_count_filter_parser for the number
 *
 */
function action_list_parsers()
{
	global $context, $scripturl, $txt, $settings, $modSettings;

	$id = 0;
	require_once(SUBSDIR . '/Maillist.subs.php');

	// build the listoption array to display the data
	$listOptions = array(
		'id' => 'email_parser',
		'title' => $txt['parsers'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_parsers'],
		'base_href' => $scripturl . '?action=admin;area=maillist;sa=emailparser',
		'default_sort_col' => 'name',
		'get_items' => array(
			'function' => 'list_get_filter_parser',
			'params' => array(
				$id,
				'parser'
			),
		),
		'get_count' => array(
			'function' => 'list_count_filter_parser',
			'params' => array(
				$id,
				'parser'
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['parser_name'],
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
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="?action=admin;area=maillist;sa=editparser;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '"><img  width="8" height="16" title="' . $txt['modify'] . '"src="' . $settings['images_url'] . '/icons/modify_small.gif" alt="*" /></a>&nbsp;<a href="?action=admin;area=maillist;sa=deleteparser;f_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['parser_delete_warning']) . ') && submitThisOnce(this);" accesskey="d"><img width="16" height="16" title="' . $txt['delete'] . '"  src="' . $settings['images_url'] . '/icons/delete.gif" alt="*" /></a>',
						'params' => array(
							'id' => true,
						),
					),
					'class' => 'centercol',
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
				'position' => 'after_title',
				'value' => isset($_GET['saved']) ? '<img align="top" src="' . $settings['images_url'] . '/icons/field_valid.gif" alt="" />&nbsp;' . $txt['saved'] : $txt['parsers_title'],
				'class' => 'windowbg2',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="addfilter" value="' . $txt['add_parser'] . '" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);

	// Set the context values
	$context['page_title'] = $txt['emailerror_title'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'email_parser';

	// Create the list.
	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);
}

/**
 * Adds or Edits an existing parser
 *  - all parsers are assumed regex
 *
 */
function action_edit_parsers()
{
	global $context, $scripturl, $txt, $modSettings;

	// editing an existing filter?
	if (isset($_REQUEST['f_id']))
	{
		// Needs to be an int!
		$id = (int) $_REQUEST['f_id'];
		if (empty($id) || $id < 0)
			fatal_lang_error('error_no_id_filter');

		$row = maillist_load_filter_parser($id, 'parser');
		$modSettings['id_filter'] = $row['id_filter'];
		$modSettings['filter_type'] = $row['filter_type'];
		$modSettings['filter_from'] = $row['filter_name'];
		$modSettings['filter_name'] = $row['filter_name'];

		$context['editing'] = true;
		$context['page_title'] = $txt['edit_parser'];
	}
	else
	{
		// Setup place holders for adding a new one instead
		$modSettings['filter_type'] = '';
		$modSettings['filter_name'] = '';
		$modSettings['filter_from'] = '';

		$context['page_title'] = $txt['add_parser'];
		$context['editing'] = false;
	}

	$config_vars = array(
		array('text', 'filter_name', 25, 'subtext' => $txt['parser_name_desc']),
		array('select', 'filter_type', 'subtext' => $txt['parser_type_desc'],
			array(
				'regex' => $txt['option_regex'],
			),
		),
		array('large_text', 'filter_from', 4, 'subtext' => $txt['parser_from_desc']),
	);

	// Load in some dependencies
	loadAdminClass('ManagePermissions.php');
	loadAdminClass('ManageServer.php');

	// Check if they are saving the changes
	if (isset($_GET['save']))
	{
		checkSession();

		// Editing an parser?
		$editid = isset($_GET['edit']) ? (int) $_GET['edit'] : -1;
		$editname = isset($_GET['edit']) ? 'id_filter' : '';

		// Test the regex
		if ($_POST['filter_type'] === 'regex' && !empty($_POST['filter_from']))
		{
			$valid = (preg_replace($_POST['filter_from'], '', '12@$%^*(09#98&76') === null) ? false : true;
			if (!$valid)
			{
				// regex did not compute
				$context['settings_message'] =  $txt['regex_invalid'];
				$context['error_type'] = 'notice';

				$modSettings['filter_type'] = $_POST['filter_type'];
				$modSettings['filter_from'] = $_POST['filter_from'];
				$modSettings['filter_name'] = $_POST['filter_name'];
			}
		}

		if (empty($_POST['filter_type']) || empty($_POST['filter_from']))
		{
			$context['error_type'] = 'notice';
			$context['settings_message'][] = $txt['filter_invalid'];
		}

		// All clear to save?
		if (empty($context['settings_message']))
		{
			saveTableSettings($config_vars, 'postby_emails_filters', array(), $editid, $editname);
			redirectexit('action=admin;area=maillist;sa=emailparser;saved');
		}
	}

	// Prepare the context for viewing
	$title = ((isset($_GET['saved']) && $_GET['saved'] == '1') ? 'saved_parser' : ($context['editing'] == true ? 'edit_parser' : 'add_parser'));
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
	prepareDBSettingContext($config_vars);
	loadTemplate('Admin', 'admin');
	$context['sub_template'] = 'show_settings';
}

/**
 * Removes a parser from the system and database
 *
 */
function action_delete_parsers()
{
	// Removing the filter?
	if (isset($_GET['f_id']))
	{
		checkSession('get');
		$id = (int) $_GET['f_id'];

		maillist_delete_filter_parser($id);
		redirectexit('action=admin;area=mailist;sa=emailparser;deleted');
	}
}

/**
 * All the post by email settings, used to control how the feature works
 *
 * @param bool $return_config
 */
function action_settings($return_config = false)
{
	global $scripturl, $context, $txt, $modSettings;

	// Be nice, show them we did something
	if (isset($_GET['saved']))
		$context['settings_message'] = $txt['saved'];

	// Templates and language
	loadLanguage('Admin');
	loadTemplate('Admin', 'admin');

	// Get the board selection list for the template
	require_once(SUBSDIR . '/Maillist.subs.php');
	$context['boards'] = maillist_board_list();

	// Load any existing email => board values used for new topic creation
	$context['maillist_from_to_board'] = array();
	$data = (!empty($modSettings['maillist_receiving_address'])) ? unserialize($modSettings['maillist_receiving_address']) : array();
	foreach ($data as $key => $addr)
	{
		$context['maillist_from_to_board'][$key] = array(
			'id' => $key,
			'emailfrom' => $addr[0],
			'boardto' => $addr[1],
		);
	}

	// Define the menu
	$config_vars = array(
		array('desc', 'maillist_help'),
		array('check', 'maillist_enabled'),
		array('check', 'pbe_post_enabled'),
		array('check', 'pbe_pm_enabled'),
		array('check', 'pbe_no_mod_notices', 'subtext' => $txt['pbe_no_mod_notices_desc'], 'postinput' => $txt['recommended']),

		array('title', 'maillist_outbound'),
		array('desc', 'maillist_outbound_desc'),
		array('check', 'maillist_group_mode'),
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
		$config_vars = array_merge($config_vars,array(
			array('title', 'maillist_imap_missing'),
		)
	);
	else
		$config_vars = array_merge($config_vars, array(
		array('title', 'maillist_imap'),
		array('title', 'maillist_imap_reason'),
		array('text', 'maillist_imap_host', 45, 'subtext' => $txt['maillist_imap_host_desc'], 'disabled' => !function_exists('imap_open')),
		array('text', 'maillist_imap_uid', 20, 'postinput' => $txt['maillist_imap_uid_desc'], 'disabled' => !function_exists('imap_open')),
		array('password', 'maillist_imap_pass', 20, 'postinput' => $txt['maillist_imap_pass_desc'], 'disabled' => !function_exists('imap_open')),
		array('check', 'maillist_imap_delete', 20, 'subtext' => $txt['maillist_imap_delete_desc'], 'disabled' => !function_exists('imap_open')),
		array('check', 'maillist_imap_cron', 20, 'subtext' => $txt['maillist_imap_cron_desc'], 'disabled' => !function_exists('imap_open')),
		)
	);

	if ($return_config)
		return $config_vars;

	// Need to have these available
	loadAdminClass('ManagePermissions.php');
	loadAdminClass('ManageServer.php');

	// Saving settings?
	if (isset($_GET['save']))
	{
		checkSession();

		$email_error = false;
		$board_error = false;
		$valid_email_regex = '~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~';

		// Basic checking of the email addresses
		if (preg_match($valid_email_regex, $_POST['maillist_sitename_address']) == 0)
			$email_error = $_POST['maillist_sitename_address'];
		if (!$email_error && !(empty($_POST['maillist_sitename_help'])) && preg_match($valid_email_regex, $_POST['maillist_sitename_help']) == 0)
			$email_error = $_POST['maillist_sitename_help'];
		if (!$email_error && !(empty($_POST['maillist_mail_from'])) && preg_match($valid_email_regex, $_POST['maillist_mail_from']) == 0)
			$email_error = $_POST['maillist_mail_from'];

		// Inbound email set up then we need to check for both basic email and board existence
		if (!$email_error && !empty($_POST['emailfrom']))
		{
			// Get the board ids for a quick check
			$boards = maillist_board_list();

			// check the receiving emails and the board id as well
			$maillist_receiving_address = array();
			$boardtocheck = !empty($_POST['boardto']) ? $_POST['boardto'] : array();
			$addresstocheck = !empty($_POST['emailfrom']) ? $_POST['emailfrom'] : array();

			foreach ($addresstocheck as $key => $checkme)
			{
				$checkme = trim($checkme);

				// Nada?
				if (empty($checkme))
					continue;

				// Valid email syntax
				if (preg_match($valid_email_regex, $checkme) == 0)
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

				// Decipher as [0]emailaddress and [1]board id
				$maillist_receiving_address[] = array($checkme, $boardtocheck[$key]);
			}
		}

		// Enable or disable the fake cron
		enable_maillist_imap_cron(!empty($_POST['maillist_imap_cron']));

		// Check and set any errors or give the go ahead to save
		if ($email_error)
			$context['settings_message'] = sprintf($txt['email_not_valid'], $email_error);
		elseif ($board_error)
			$context['settings_message'] = sprintf($txt['board_not_valid'], $board_error);
		else
		{
			// Clear the moderation count cache
			cache_put_data('num_menu_errors', null, 900);

			// Protect them from themselves
			$_POST['maillist_short_line'] = empty($_POST['maillist_short_line']) ? 33 : $_POST['maillist_short_line'];
			$_POST['maillist_key_active'] =  empty($_POST['maillist_key_active']) ? 21 : $_POST['maillist_key_active'];

			// Should be off if mail posting is on, we ignore it anyway but this at least updates the ACP
			if (!empty($_POST['maillist_enabled']))
				updateSettings(array('disallow_sendBody' => ''));

			updateSettings(array('maillist_receiving_address' => serialize($maillist_receiving_address)));
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=maillist;sa=emailsettings;saved');
		}
	}

	$context['settings_title'] = $txt['ml_emailsettings'];
	$context['page_title'] = $txt['ml_emailsettings'];
	$context['post_url'] = $scripturl . '?action=admin;area=maillist;sa=emailsettings;save';
	prepareDBSettingContext($config_vars);
	$context['sub_template'] = 'show_settings';
}