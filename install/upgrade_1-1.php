<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * This class is the core of the upgrade system.
 * Methods starting with "__" (double underscore) are not executed.
 * Each method that contains one or more actions is paired with a method
 * with the same name plus "_title", for example:
 *   - my_new_action
 *   - my_new_action_title
 * Methods whose name ends with "_title" are supposed to return a single
 * string representing the title of the step.
 * Methods containing the actions are supposed to return a multidimentional
 * array with the following structure:
 * array(
 *     array(
 *         'debug_title' => 'A string representing a title shown when debugging',
 *         'function' => function($db, $db_table) { // Code },
 *     ),
 *     [...],
 * );
 */
class UpgradeInstructions_upgrade_1_1
{
	protected $db = null;
	protected $table = null;

	public function __construct($db, $table)
	{
		$this->db = $db;
		$this->table = $table;
	}

	public function admin_info_files_title()
	{
		return 'Deprecating admin_info_files table...';
	}

	public function admin_info_files()
	{
		return array(
			array(
				'debug_title' => 'Remove any old file left and check if the table is empty...',
				'function' => function ($db, $db_table)
				{
					updateSettings(array('detailed-version.js' => 'https://elkarte.github.io/Elkarte/site/detailed-version.js'));

					if ($db_table->table_exists('{db_prefix}admin_info_files'))
					{
						foreach (array('current-version.js', 'detailed-version.js', 'latest-news.js', 'latest-smileys.js', 'latest-versions.txt') as $file)
						{
							$db->query('', '
							DELETE FROM {db_prefix}admin_info_files
							WHERE filename = {string:current_file}',
								array(
									'current_file' => $file
								)
							);
						}
						$request = $db->query('', '
							SELECT COUNT(*)
							FROM {db_prefix}admin_info_files',
							array()
						);
						if ($request)
						{
							// Drop it only if it is empty
							list ($count) = (int) $db->fetch_row($request);
							if ($count == 0)
							{
								$db_table->db_drop_table('{db_prefix}admin_info_files');
							}
						}
					}
				}
			),
		);
	}

	public function adding_twofactor_title()
	{
		return 'Adding two-factor authentication...';
	}

	public function adding_twofactor()
	{
		return array(
			array(
				'debug_title' => 'Adding new two factor columns to members table...',
				'function' => function ($db, $db_table)
				{
					$db_table->db_add_column('{db_prefix}members',
						array(
							'name' => 'otp_secret',
							'type' => 'varchar',
							'size' => 16,
							'default' => '',
						),
						array(),
						'ignore'
					);
					$db_table->db_add_column('{db_prefix}members',
						array(
							'name' => 'enable_otp',
							'type' => 'tinyint',
							'size' => 1,
							'default' => 0,
						),
						array(),
						'ignore'
					);
					$db_table->db_add_column('{db_prefix}members',
						array(
							'name' => 'otp_used',
							'type' => 'int',
							'size' => 6,
							'default' => 0,
						),
						array(),
						'ignore'
					);
				}
			)
		);
	}

	public function introducing_notifications_title()
	{
		return 'Introducing notifications...';
	}

	public function introducing_notifications()
	{
		return array(
			array(
				'debug_title' => 'Adding new tables for notifications...',
				'function' => function ($db, $db_table)
				{
					$db_table->db_create_table('{db_prefix}pending_notifications',
						array(
							array('name' => 'notification_type', 'type' => 'varchar', 'size' => 10),
							array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
							array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'default' => 0),
							array('name' => 'frequency', 'type' => 'varchar', 'size' => 1, 'default' => ''),
							array('name' => 'snippet', 'type' => 'text'),
						),
						array(
							array('name' => 'types_member', 'columns' => array('notification_type', 'id_member'), 'type' => 'unique'),
						),
						array(),
						'ignore'
					);

					$db_table->db_create_table('{db_prefix}notifications_pref',
						array(
							array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
							array('name' => 'notification_level', 'type' => 'tinyint', 'size' => 1, 'default' => 1),
							array('name' => 'mention_type', 'type' => 'varchar', 'size' => 12, 'default' => ''),
						),
						array(
							array('name' => 'mention_member', 'columns' => array('id_member', 'mention_type'), 'type' => 'unique'),
						),
						array(),
						'ignore'
					);

					updateSettings(array(
						'notification_methods' => 'a:4:{s:5:"buddy";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}s:7:"likemsg";a:1:{s:12:"notification";s:1:"1";}s:10:"mentionmem";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}s:9:"quotedmem";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}}',
					));
				}
			)
		);
	}

	public function mentions_title()
	{
		return 'Adapt mentions...';
	}

	public function mentions()
	{
		return array(
			array(
				'debug_title' => 'Separate mentions visibility from accessibility...',
				'function' => function ($db, $db_table)
				{
					$db_table->db_add_column('{db_prefix}log_mentions',
						array(
							'name' => 'is_accessible',
							'type' => 'tinyint',
							'size' => 1,
							'default' => 0
						)
					);

					$db_table->db_change_column('{db_prefix}log_mentions',
						'mention_type',
						array(
							'type' => 'varchar',
							'size' => 12,
							'default' => ''
						)
					);
				}
			),
			array(
				'debug_title' => 'Update mention logs...',
				'function' => function ($db, $db_table)
				{
					global $modSettings;

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET is_accessible = CASE WHEN status < 0 THEN 0 ELSE 1 END',
						array()
					);

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET status = -(status + 1)
						WHERE status < 0',
						array()
					);

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET mention_type = {string:to}
						WHERE mention_type = {string:from}',
						array(
							'from' => 'men',
							'to' => 'mentionmem'
						)
					);

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET mention_type = {string:to}
						WHERE mention_type = {string:from}',
						array(
							'from' => 'like',
							'to' => 'likemsg'
						)
					);

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET mention_type = {string:to}
						WHERE mention_type = {string:from}',
						array(
							'from' => 'rlike',
							'to' => 'rlikemsg'
						)
					);

					$enabled_mentions = !empty($modSettings['enabled_mentions']) ? explode(',', $modSettings['enabled_mentions']) : array();
					$known_settings = array(
						'mentions_enabled' => 'mentionmem',
						'likes_enabled' => 'likemsg',
						'mentions_dont_notify_rlike' => 'rlikemsg',
						'mentions_buddy' => 'buddy',
					);
					foreach ($known_settings as $setting => $toggle)
					{
						if (!empty($modSettings[$setting]))
						{
							$enabled_mentions[] = $toggle;
						}
						else
						{
							$enabled_mentions = array_diff($enabled_mentions, array($toggle));
						}

						$db->query('', '
							INSERT IGNORE INTO {db_prefix}notifications_pref
								(id_member, mention_type, notification_level)
							SELECT id_member, {string:mention_type}, {int:level}
							FROM {db_prefix}members',
							array(
								'mention_type' => $toggle,
								'level' => 1,
							)
						);
					}
					updateSettings(array('enabled_mentions' => implode(',', $enabled_mentions)));
				}
			),
			array(
				'debug_title' => 'Make mentions generic and not message-centric...',
				'function' => function ($db, $db_table)
				{
					$db_table->db_change_column('{db_prefix}log_mentions', 'id_msg',
						array(
							'name' => 'id_target',
						)
					);
				}
			),
		);
	}

	public function add_modules_support_title()
	{
		return 'Introducing modules...';
	}

	public function add_modules_support()
	{
		return array(
			array(
				'debug_title' => 'Converts settings to modules...',
				'function' => function ($db, $db_table)
				{
					global $modSettings;

					require_once(SUBSDIR . '/Admin.subs.php');
					if (!empty($modSettings['attachmentEnable']))
					{
						enableModules('attachments', array('post'));
					}

					if (!empty($modSettings['cal_enabled']))
					{
						enableModules('calendar', array('post', 'boardindex'));
					}

					if (!empty($modSettings['drafts_enabled']))
					{
						enableModules('drafts', array('post', 'display', 'profile', 'personalmessage'));
						Hooks::get()->enableIntegration('Drafts_Integrate');
					}

					if (!empty($modSettings['enabled_mentions']))
					{
						enableModules('mentions', array('post', 'display'));
					}

					enableModules('poll', array('display', 'post'));
					enableModules('verification', array('post', 'personalmessage', 'register'));
					enableModules('random', array('post', 'display'));
					Hooks::get()->enableIntegration('User_Notification_Integrate');
					Hooks::get()->enableIntegration('Ila_Integrate');
					updateSettings(array(
						'usernotif_favicon_bgColor' => '#ff0000',
						'usernotif_favicon_position' => 'up',
						'usernotif_favicon_textColor' => '#ffff00',
						'usernotif_favicon_type' => 'circle',
					));
				}
			)
		);
	}

	public function preparing_postbyemail_title()
	{
		return 'Performing updates to post-by-email handling...';
	}

	public function preparing_postbyemail()
	{
		return array(
			array(
				'debug_title' => 'Splitting email_id into it\'s components in postby_emails...',
				'function' => function ($db, $db_table)
				{
					if ($db_table->column_exists('{db_prefix}postby_emails', 'message_key') === false)
					{
						// Add the new columns
						$db_table->db_add_column('{db_prefix}postby_emails',
							array(
								'name' => 'message_key',
								'type' => 'varchar',
								'size' => 32,
								'default' => ''
							),
							array(),
							'ignore'
						);
						$db_table->db_add_column('{db_prefix}postby_emails',
							array(
								'name' => 'message_type',
								'type' => 'varchar',
								'size' => 10,
								'default' => ''
							),
							array(),
							'ignore'
						);
						$db_table->db_add_column('{db_prefix}postby_emails',
							array(
								'name' => 'message_id',
								'type' => 'mediumint',
								'size' => 8,
								'default' => 0
							),
							array(),
							'ignore'
						);

						// Move the data from the single column to the new three
						$db->query('',
							'UPDATE {db_prefix}postby_emails
							SET
								message_key = SUBSTRING(id_email FROM 1 FOR 32),
								message_type = SUBSTRING(id_email FROM 34 FOR 1),
								message_id = SUBSTRING(id_email FROM 35)'
						);

						// Do the cleanup
						$db_table->db_remove_column('{db_prefix}postby_emails', 'id_email');
						$db_table->db_remove_index('{db_prefix}postby_emails', 'id_email');
						$db_table->db_add_index('{db_prefix}postby_emails', array('name' => 'id_email', 'columns' => array('message_key', 'message_type', 'message_id'), 'type' => 'primary'));
					}
				}
			),
			array(
				'debug_title' => 'Naming consistency for postby_emails_error table columns...',
				'function' => function ($db, $db_table)
				{
					if ($db_table->column_exists('{db_prefix}postby_emails_error', 'data_id') === true)
					{
						// Rename some columns
						$db_table->db_change_column('{db_prefix}postby_emails_error',
							'data_id',
							array(
								'name' => 'message_key',
							)
						);
						$db_table->db_change_column('{db_prefix}postby_emails_error',
							'id_message',
							array(
								'name' => 'message_id',
							)
						);
					}
				}
			),
			array(
				'debug_title' => 'Updating PBE filter/parser table columns...',
				'function' => function ($db, $db_table)
				{
					// Filter type was 5 now needs to be 6
					$db_table->db_change_column('{db_prefix}postby_emails_filters',
						'filter_style',
						array(
							'type' => 'char',
							'size' => 6,
							'default' => ''
						)
					);
					// Update any filte to filter, and parse to parser
					$db->query('', '
						UPDATE {db_prefix}postby_emails_filters
						SET filter_style = {string:to}
						WHERE filter_style = {string:from}',
						array(
							'from' => 'filte',
							'to' => 'filter'
						)
					);
					$db->query('', '
						UPDATE {db_prefix}postby_emails_filters
						SET filter_style = {string:to}
						WHERE filter_style = {string:from}',
						array(
							'from' => 'parse',
							'to' => 'parser'
						)
					);
				}
			),
		);
	}

	public function pm_reporting_title()
	{
		return 'Enhancing PM reporting...';
	}

	public function pm_reporting()
	{
		return array(
			array(
				'debug_title' => 'Adding new columns for PM reporting...',
				'function' => function ($db, $db_table)
				{
					if ($db_table->column_exists('{db_prefix}log_reported', 'type') === false)
					{
						$db_table->db_add_column('{db_prefix}log_reported',
							array(
								'name' => 'type',
								'type' => 'varchar',
								'size' => 5,
								'default' => 'msg'
							),
							array(),
							'ignore'
						);
						$db_table->db_add_column('{db_prefix}log_reported',
							array(
								'name' => 'time_message',
								'type' => 'int',
								'size' => 10,
								'default' => 0
							),
							array(),
							'ignore'
						);
						$db_table->db_remove_index('{db_prefix}log_reported', 'id_msg');
						$db_table->db_add_index('{db_prefix}log_reported', array('name' => 'msg_type', 'columns' => array('type', 'id_msg'), 'type' => 'key'));
					}
				}
			)
		);
	}

	public function fix_ipv6_title()
	{
		return 'Fix database for some IPv6 issues...';
	}

	public function fix_ipv6()
	{
		return array(
			array(
				'debug_title' => 'Converting IP columns to varchar instead of int...',
				'function' => function ($db, $db_table)
				{
					$columns = $db_table->db_list_columns('{db_prefix}log_online', true);

					$column_name = 'ip';

					foreach ($columns as $column)
					{
						if ($column_name == $column['name'] && $column['type'] == 'varchar')
						{
							return true;
						}
					}

					$db->query('', '
						TRUNCATE TABLE {db_prefix}log_online');

					$db_table->db_change_column('{db_prefix}log_online',
						$column_name,
						array(
							'type' => 'varchar',
							'size' => 255,
							'default' => ''
						)
					);
				}
			)
		);
	}

	public function pm_count_column_title()
	{
		return 'Changing the pm count column to mediumint.';
	}

	public function pm_count_column()
	{
		return array(
			array(
				'debug_title' => 'Changing the pm count column to mediumint.',
				'function' => function ($db, $db_table)
				{
					$db_table->db_change_column('{db_prefix}members',
						'personal_messages',
						array(
							'type' => 'mediumint',
							'size' => 8,
							'default' => 0
						)
					);
				}
			)
		);
	}

	public function custom_field_updates_title()
	{
		return 'Some updates to custom fields.';
	}

	public function custom_field_updates()
	{
		return array(
			array(
				'debug_title' => 'Adding new custom field columns',
				'function' => function ($db, $db_table)
				{
					$db_table->db_add_column('{db_prefix}custom_fields',
						array(
							'name' => 'rows',
							'type' => 'smallint',
							'size' => 5,
							'default' => 4
						)
					);
					$db_table->db_add_column('{db_prefix}custom_fields',
						array(
							'name' => 'cols',
							'type' => 'smallint',
							'size' => 5,
							'default' => 30
						)
					);
				}
			),
			array(
				'debug_title' => 'Populating new custom field columns where needed',
				'function' => function ($db, $db_table)
				{
					$result = $db->query('', '
						SELECT id_field, default_value 
						FROM {db_prefix}custom_fields 
						WHERE field_type="textarea"');

					while ($row = $db->fetch_assoc($result))
					{
						$vals = explode(',', $row['default_value']);
						$rows = (int) $vals[0];
						$cols = (int) $vals[1];

						if (count($vals) === 2 && $rows && $cols)
						{
							$db->query('', '
								UPDATE {db_prefix}custom_fields 
								SET rows=' . $rows . ' , cols=' . $cols . ' 
								WHERE id_field=' . $row['id_field']);
						}
					}
				}
			),
			array(
				'debug_title' => 'Inserting custom fields for gender/location/personal text',
				'function' => function ($db, $db_table)
				{
					$db->insert('replace',
						'{db_prefix}custom_fields',
						array('col_name' => 'string', 'field_name' => 'string', 'field_desc' => 'string', 'field_type' => 'string', 'field_length' => 'int', 'field_options' => 'string', 'mask' => 'string', 'show_reg' => 'int', 'show_display' => 'int', 'show_profile' => 'string', 'private' => 'int', 'active' => 'int', 'bbc' => 'int', 'can_search' => 'int', 'default_value' => 'string', 'enclose' => 'string', 'placement' => 'int', 'rows' => 'int', 'cols' => 'int'),
						array(
							array('cust_gender', 'Gender', 'Your gender', 'radio', 15, 'undisclosed,male,female,genderless,nonbinary,transgendered', '', 0, 1, 'forumprofile', 0, 1, 0, 0, 'undisclosed', '<i class="icon i-{INPUT}" title="{INPUT}"><s>{INPUT}</s></i>', 0, 0, 0),
							array('cust_blurb', 'Personal Text', 'A custom bit of text for your postbit.', 'text', 120, '', '', 0, 0, 'forumprofile', 0, 1, 0, 0, 'Default Personal Text', '', 3, 0, 0),
							array('cust_locate', 'Location', 'Where you are', 'text', 32, '', '', 0, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0, 0, 0),
						),
						'id_member'
					);
				}
			),
			array(
				'debug_title' => 'Updating icon custom fields',
				'function' => function ($db, $db_table)
				{
					$result = $db->query('', '
						SELECT id_field, col_name 
						FROM {db_prefix}custom_fields 
						WHERE placement = 1');
					while ($row = $db->fetch_assoc($result))
					{
						switch ($row['col_name'])
						{
							case 'cust_skye':
								$db->query('', 'UPDATE {db_prefix}custom_fields SET enclose=\'<a href="skype:{INPUT}?call" class="icon i-skype icon-big" title="Skype call {INPUT}"><s>Skype call {INPUT}</s></a>\' WHERE id_field=' . $row['id_field']);
								break;
							case 'cust_fbook':
								$db->query('', 'UPDATE {db_prefix}custom_fields SET enclose=\'<a target="_blank" href="https://www.facebook.com/{INPUT}" class="icon i-facebook icon-big" title="Facebook"><s>Facebook</s></a>\' WHERE id_field=' . $row['id_field']);
								break;
							case 'cust_twitt':
								$db->query('', 'UPDATE {db_prefix}custom_fields SET enclose=\'<a target="_blank" href="https://www.twitter.com/{INPUT}" class="icon i-twitter icon-big" title="Twitter Profile"><s>Twitter Profile</s></a>\' WHERE id_field=' . $row['id_field']);
								break;
							case 'cust_linked':
								$db->query('', 'UPDATE {db_prefix}custom_fields SET enclose=\'<a href="{INPUT}" class="icon i-linkedin icon-big" title="Linkedin Profile"><s>Linkedin Profile</s></a>\' WHERE id_field=' . $row['id_field']);
								break;
							case 'cust_gplus':
								$db->query('', 'UPDATE {db_prefix}custom_fields SET enclose=\'<a target="_blank" href="{INPUT}" class="icon i-google-plus icon-big" title="G+ Profile"><s>G+ Profile</s></a>\' WHERE id_field=' . $row['id_field']);
								break;
							case 'cust_icq':
								$db->query('', 'UPDATE {db_prefix}custom_fields SET enclose=\'<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>\' WHERE id_field=' . $row['id_field']);
								break;
						}
					}
				}
			),
			array(
				'debug_title' => 'Converting gender data',
				'function' => function ($db, $db_table)
				{
					$result = $db->query('', '
						SELECT id_member, gender 
						FROM {db_prefix}members
						WHERE gender != ""');
					while ($row = $db->fetch_assoc($result))
					{
						$gender = 'undisclosed';

						switch ($row['gender'])
						{
							case 1:
								$gender = 'male';
								break;
							case 2:
								$gender = 'female';
								break;
						}

						$db->insert('replace',
							'{db_prefix}custom_fields_data',
							array('id_member' => 'int', 'variable' => 'string', 'value' => 'string'),
							array(
								array($row['id_member'], 'cust_gender', $gender),
							),
							'id_member'
						);
					}
				}
			),
			array(
				'debug_title' => 'Converting location',
				'function' => function ($db, $db_table)
				{
					$result = $db->query('', '
						SELECT id_member, location 
						FROM {db_prefix}members
						WHERE location != ""');
					while ($row = $db->fetch_assoc($result))
					{
						$db->insert('replace',
							'{db_prefix}custom_fields_data',
							array('id_member' => 'int', 'variable' => 'string', 'value' => 'string'),
							array(
								array($row['id_member'], 'cust_locate', $row['location']),
							),
							'id_member'
						);
					}
				}
			),
			array(
				'debug_title' => 'Converting personal text',
				'function' => function ($db, $db_table)
				{
					$result = $db->query('', '
						SELECT id_member, personal_text 
						FROM {db_prefix}members
						WHERE personal_text != ""');
					while ($row = $db->fetch_assoc($result))
					{
						$db->insert('replace',
							'{db_prefix}custom_fields_data',
							array('id_member' => 'int', 'variable' => 'string', 'value' => 'string'),
							array(
								array($row['id_member'], 'cust_blurb', $row['personal_text']),
							),
							'id_member'
						);
					}
				}
			),
		);
	}

	public function user_drafts_title()
	{
		return 'Flag for user saved drafts...';
	}

	public function user_drafts()
	{
		return array(
			array(
				'debug_title' => 'Adding new columns...',
				'function' => function ($db, $db_table)
				{
					if ($db_table->column_exists('{db_prefix}user_drafts', 'is_usersaved') === false)
					{
						$db_table->db_add_column('{db_prefix}user_drafts',
							array(
								'name' => 'is_usersaved',
								'type' => 'tinyint',
								'size' => 4,
								'default' => 0
							),
							array(),
							'ignore'
						);
					}
				}
			)
		);
	}

	public function mime_types_title()
	{
		return 'More space for MIME types...';
	}

	public function mime_types()
	{
		return array(
			array(
				'debug_title' => 'Altering column to varchar(255)...',
				'function' => function ($db, $db_table)
				{
					$db_table->db_change_column('{db_prefix}attachments',
						'mime_type',
						array(
							'type' => 'varchar',
							'size' => 255,
							'default' => ''
						)
					);
				}
			)
		);
	}

	public function update_settings_title()
	{
		return 'Updating needed settings...';
	}

	public function update_settings()
	{
		return array(
			array(
				'debug_title' => 'Adjusting cal_maxyear/cal_limityear...',
				'function' => function ($db, $db_table)
				{
					updateSettings(array(
						'cal_maxyear' => '2030',
						'cal_limityear' => '10',
						'avatar_max_height' => '65',
					));
				}
			)
		);
	}
}
