<?php

Class Elk_Testing_Setup
{
	protected $_db;
	protected $_queries_parts;
	protected $_clean_queries_parts;
	protected $_queries;
	protected $_dbserver;
	protected $_name;
	protected $_user;
	protected $_passwd;
	protected $_prefix;

	public function run_queries()
	{
		$query = '';

		if (empty($this->_clean_queries_parts))
			$this->_clean_queries_parts = $this->_queries_parts;

		foreach ($this->_clean_queries_parts as $part)
		{
			if (substr($part, -1) == ';')
			{
				$result = $this->_db->query('', $query . "\n" . $part, array('security_override' => true));
				if ($result === false)
					echo 'Query failed: ' . "\n" . $query . "\n" . $part . "\n";

				$query = '';
			}
			else
			{
				$query .= "\n" . $part;
			}
		}
	}

	public function load_queries($file)
	{
		$this->_queries = str_replace('{$db_prefix}', $this->_prefix, file_get_contents($file));
		$this->_queries_parts = explode("\n", $this->_queries);
		$this->fix_query_string();
	}

	public function fix_query_string()
	{
		foreach ($this->_queries_parts as $line)
			if (!empty($line[0]) && $line[0] != '#')
				$this->_clean_queries_parts[] = str_replace(array('{$current_time}', '{$sched_task_offset}'), array(time(), '1'), $line);
	}

	public function prepare_settings()
	{
		$file = file_get_contents(BOARDDIR . '/Settings.php');
		$file = str_replace(array(
			'$boardurl = \'http://127.0.0.1/elkarte\';',
			'$db_type = \'mysql\';',
			'$db_name = \'elkarte\';',
			'$db_user = \'root\';',
			'$db_prefix = \'elkarte_\';'
		),
		array(
			'$boardurl = \'http://127.0.0.1\';',
			'$db_type = \'' . $this->_type . '\';',
			'$db_name = \'' . $this->_name . '\';',
			'$db_user = \'' . $this->_user . '\';',
			'$db_prefix = \'' . $this->_prefix . '\';'
		),
		$file);
		$file .= "\n" . '$test_enabled = 1;';

		file_put_contents(BOARDDIR . '/Settings.php', $file);
	}

	public function update()
	{
		global $settings, $context, $modSettings, $boardurl, $txt;

		require_once(BOARDDIR . '/Settings.php');

		$settings['theme_dir'] = $settings['default_theme_dir'] = BOARDDIR . '/Themes/default';
		$settings['theme_url'] = $settings['default_theme_url'] = $boardurl . '/themes/default';

		// Create a member
		$request = $this->_db->insert('',
			'{db_prefix}members',
			array(
				'member_name' => 'string-25', 'real_name' => 'string-25', 'passwd' => 'string', 'email_address' => 'string',
				'id_group' => 'int', 'posts' => 'int', 'date_registered' => 'int', 'hide_email' => 'int',
				'password_salt' => 'string', 'lngfile' => 'string', 'personal_text' => 'string', 'avatar' => 'string',
				'member_ip' => 'string', 'member_ip2' => 'string', 'buddy_list' => 'string', 'pm_ignore_list' => 'string',
				'message_labels' => 'string', 'website_title' => 'string', 'website_url' => 'string', 'location' => 'string',
				'signature' => 'string', 'usertitle' => 'string', 'secret_question' => 'string',
				'additional_groups' => 'string', 'ignore_boards' => 'string', 'openid_uri' => 'string',
			),
			array(
				'test_admin', 'test_admin', sha1(strtolower(stripslashes('test_admin')) . stripslashes('test_admin_pwd')), 'email@testadmin.tld',
				1, 0, time(), 0,
				substr(md5(mt_rand()), 0, 4), '', '', '',
				'123.123.123.123', '123.123.123.123', '', '',
				'', '', '', '',
				'', '', '',
				'', '', '',
			),
			array('id_member')
		);

		updateStats('member');
		updateStats('message');
		updateStats('topic');
		loadLanguage('Install');
		updateStats('subject', 1, htmlspecialchars($txt['default_topic_subject']));
	}
}