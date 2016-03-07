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
 * @version 1.1 beta 1
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
		return $this->table = $table;
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
				'function' => function($db, $db_table)
				{
					foreach (array('current-version.js', 'detailed-version.js', 'latest-news.js', 'latest-smileys.js', 'latest-versions.txt') as $file)
					{
						$db->query('', '
							DELETE FROM {db_prefix}admin_info_files
							WHERE file = {string:current_file}',
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
							$db_table->db_drop_table('{db_prefix}admin_info_files');
					}
				}
			),
		);
	}

	public function adding_opt_title()
	{
		return 'Adding two-factor authentication...';
	}

	public function adding_opt($title = false)
	{
		return array(
			array(
				'debug_title' => 'Adding new columns to members table...',
				'function' => function($db, $db_table)
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
				'debug_title' => 'Separate visibility from accessibility...',
				'function' => function($db, $db_table)
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
				'function' => function($db, $db_table)
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
						SET mention_type = mentionmem
						WHERE mention_type = men',
						array()
					);

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET mention_type = likemsg
						WHERE mention_type = like',
						array()
					);

					$db->query('', '
						UPDATE {db_prefix}log_mentions
						SET mention_type = rlikemsg
						WHERE mention_type = rlike',
						array()
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
							$enabled_mentions[] = $toggle;
						else
							$enabled_mentions = array_diff($enabled_mentions, array($toggle));
					}
					updateSettings(array('enabled_mentions' => implode(',', $enabled_mentions)));
				}
			),
			array(
				'debug_title' => 'Make mentions generic and not message-centric...',
				'function' => function($db, $db_table)
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
				'function' => function($db, $db_table)
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
						Hooks::get()->enableIntegration('Calendar_Integrate');
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

	public function introducing_notifications_title()
	{
		return 'Introducing notifications...';
	}

	public function introducing_notifications()
	{
		return array(
			array(
				'debug_title' => 'Adding new tables...',
				'function' => function($db, $db_table)
				{
					$db_table->db_create_table('{db_prefix}pending_notifications',
						array(
							array('name' => 'notification_type', 'type' => 'varchar', 'size' => 10),
							array('name' => 'id_member',         'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
							array('name' => 'log_time',          'type' => 'int', 'size' => 10, 'default' => 0),
							array('name' => 'frequency',         'type' => 'varchar', 'size' => 1, 'default' => ''),
							array('name' => 'snippet',           'type' => 'text'),
						),
						array(
							array('name' => 'types_member', 'columns' => array('notification_type', 'id_member'), 'type' => 'unique'),
						),
						array(),
						'ignore'
					);

					$db_table->db_create_table('{db_prefix}notifications_pref',
						array(
							array('name' => 'id_member',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
							array('name' => 'notification_level', 'type' => 'tinyint', 'size' => 1, 'default' => 1),
							array('name' => 'mention_type',       'type' => 'varchar', 'size' => 12, 'default' => ''),
						),
						array(
							array('name' => 'mention_member', 'columns' => array('id_member', 'mention_type'), 'type' => 'unique'),
						),
						array(),
						'ignore'
					);

					updateSettings(array('notification_methods' => 'a:4:{s:5:"buddy";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}s:7:"likemsg";a:1:{s:12:"notification";s:1:"1";}s:10:"mentionmem";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}s:9:"quotedmem";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}}'));
				}
			)
		);
	}

	public function preparing_postbyemail_title()
	{
		return 'Doing changes to post-by-email handling...';
	}

	public function preparing_postbyemail()
	{
		return array(
			array(
				'debug_title' => 'Splitting email_id into it\'s components in postby_emails...',
				'function' => function($db, $db_table)
				{
					if ($db_table->column_exists('{db_prefix}postby_emails', 'id_email') === true)
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
								'default' => ''
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
						$db_table->db_add_index('{db_prefix}postby_emails', array('name' => 'id_email', 'columns' => array('id_email'), 'type' => 'primary'));
					}
				}
			),
			array(
				'debug_title' => 'Naming consistency for postby_emails_error table columns...',
				'function' => function($db, $db_table)
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
				'debug_title' => 'Adding new columns...',
				'function' => function($db, $db_table)
				{
					if ($db_table->column_exists('{db_prefix}log_reported', 'type') === true)
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
				'function' => function($db, $db_table)
				{
					$columns = $db_table->db_remove_index('{db_prefix}log_online', true);

					$column_name = 'ip';

					foreach ($columns as $column)
					{
						if ($column_name == $column['name'] && $column['type'] == 'varchar')
						{
							return true;
						}
					}

					$db->query('','
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
	
	public function expand_attachments_title()
	{
		return 'Expand the attachments table...';
	}

	public function expand_attachments()
	{
		$columns = $this->table->db_list_columns('{db_prefix}attachments', true);

		return array(
			array(
				'debug_title' => 'Remove the id_msg no more necessary KEY and replace it with attach_source...',
				'function' => function($db, $db_table)
				{
					$db_table->db_remove_index('{db_prefix}attachments', 'id_msg');
					$db_table->db_add_index('{db_prefix}attachments', array('name' => 'attach_source', 'columns' => array('id_msg', 'attach_source'), 'type' => 'key'));
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
				'function' => function($db, $db_table) {
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
				'function' => function($db, $db_table) {
					$result = $db->query('', 'SELECT id_field, default_value FROM {db_prefix}custom_fields WHERE field_type="textarea"');

					while ($row = mysqli_fetch_assoc($result))
					{
						$vals = explode(',', $row['default_value']);
						$rows = (int) $vals[0];
						$cols = (int) $vals[1];

						if (count($vals) === 2 && $rows && $cols)
						{
							$db->query('', 'UPDATE {db_prefix}custom_fields SET rows=' . $rows . ' , cols=' . $cols . ' WHERE id_field=' . $row['id_field']);
						}
					}
				}
			),
		);
	}
}
