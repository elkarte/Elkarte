<?php

class UpgradeInstructions_upgrade_1_1
{
	protected $db = null;
	protected $table = null;

	public function __construct($db, $table)
	{
		$this->db = $db;
		return $this->table = $table;
	}

	public function admin_info_file_title()
	{
		return 'Deprecating admin_info_file table...';
	}

	public function admin_info_file()
	{
		return array(
			array(
				'debug_title' => 'Remove any old file left and check if the table is empty...',
				'function' => function($db, $db_table)
				{
					foreach (array('current-version.js', 'detailed-version.js', 'latest-news.js', 'latest-smileys.js', 'latest-versions.txt') as $file)
					{
						$db->query('', '
							DELETE FROM {db_prefix}admin_info_file
							WHERE file = {string:current_file}',
							array(
								'current_file' => $file
							)
						);
					}
					$request = $db->query('', '
						SELECT COUNT(*)
						FROM {db_prefix}admin_info_file',
						array()
					);

					// Drop it only if it is empty
					if ($db->num_rows($request) == 0)
						$db_table->db_drop_table('{db_prefix}admin_info_file');
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
						enableModules('mention', 'post', 'display');
					}
					enableModules('poll', array('display'));
				}
			)
		);
	}
}