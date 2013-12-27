<?php
require_once(BOARDDIR . '/sources/database/Db.php');
require_once(BOARDDIR . '/sources/Errors.php');
require_once(BOARDDIR . '/sources/subs/Cache.subs.php');
require_once(BOARDDIR . '/sources/database/Database.subs.php');

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
				$result = $this->_db->query('', $query . "\n" . substr($part, 0, -1), array('security_override' => true));
				if ($result === false)
					echo 'Query failed: ' . "\n" . $query . "\n" . substr($part, 0, -1) . "\n";

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
		$this->_queries = str_replace('{$db_prefix}', $this->_db_prefix, file_get_contents($file));
		$this->_queries_parts = explode("\n", $this->_queries);
		$this->fix_query_string();
	}

	public function fix_query_string()
	{
		foreach ($this->_queries_parts as $line)
			if (!empty($line[0]) && $line[0] != '#')
				$this->_clean_queries_parts[] = str_replace(
					array(
						'{$current_time}', '{$sched_task_offset}',
						'{BOARDDIR}', '{$boardurl}'
					),
					array(
						time(), '1',
						BOARDDIR, $this->_boardurl
					),
					$line
				);
	}

	public function prepare_settings()
	{
		$file = file_get_contents(BOARDDIR . '/Settings.php');
		$file = str_replace(array(
			'$boardurl = \'http://127.0.0.1/elkarte\';',
			'$db_type = \'mysql\';',
			'$db_name = \'elkarte\';',
			'$db_user = \'root\';',
			'$db_prefix = \'elkarte_\';',
			'$db_passwd = \'\';',
		),
		array(
			'$boardurl = \'' . $this->_boardurl . '\';',
			'$db_type = \'' . $this->_db_type . '\';',
			'$db_name = \'' . $this->_db_name . '\';',
			'$db_user = \'' . $this->_db_user . '\';',
			'$db_prefix = \'' . $this->_db_prefix . '\';',
			'$db_passwd = \'' . $this->_db_passwd . '\';',
		),
		$file);
		if (strpos($file, 'if (file_exist') !== false)
			$file = substr($file, 0, strpos($file, 'if (file_exist'));
		$file .= "\n" . '$test_enabled = 1;';
		$file .= "\n" . 'DEFINE(\'SKIPINSTALL\', 1);';

		file_put_contents(BOARDDIR . '/Settings.php', $file);
	}

	public function update()
	{
		global $settings, $db_type;
		global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
		global $boardurl, $webmaster_email, $cookiename;
		global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
		global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
		global $smcFunc, $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd;
		global $sourcedir, $boarddir;

		define('SUBSDIR', BOARDDIR . '/sources/subs');

		require(BOARDDIR . '/Settings.php');
		require(BOARDDIR . '/sources/Subs.php');
		require(BOARDDIR . '/sources/Load.php');
		require_once(SUBSDIR . '/Util.class.php');

		$settings['theme_dir'] = $settings['default_theme_dir'] = BOARDDIR . '/Themes/default';
		$settings['theme_url'] = $settings['default_theme_url'] = $boardurl . '/themes/default';

		// Create a member
		$db = database();

		$request = $db->insert('',
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

		$server_offset = @mktime(0, 0, 0, 1, 1, 1970);
		$timezone_id = 'Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600);
		if (date_default_timezone_set($timezone_id))
			$db->insert('',
				$db_prefix . 'settings',
				array(
					'variable' => 'string-255', 'value' => 'string-65534',
				),
				array(
					'default_timezone', $timezone_id,
				),
				array('variable')
			);

		updateStats('member');
		updateStats('message');
		updateStats('topic');
		loadLanguage('Install');
		updateStats('subject', 1, htmlspecialchars($txt['default_topic_subject']));
	}
}