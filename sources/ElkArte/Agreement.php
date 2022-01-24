<?php

/**
 * This class takes care of the registration agreement
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use BBC\ParserWrapper;
use Elkarte\Util;

/**
 * Class Agreement
 *
 * A simple class to take care of the registration agreement
 */
class Agreement
{
	/**
	 * Default language of the agreement.
	 *
	 * @var string
	 */
	protected $_language = '';

	/**
	 * The directory where backups are stored
	 *
	 * @var string
	 */
	protected $_backup_dir = '';

	/**
	 * The name of the file where the agreement is stored
	 *
	 * @var string
	 */
	protected $_file_name = 'Agreement';

	/**
	 * The name of the directory where the backup will be saved
	 *
	 * @var string
	 */
	protected $_backupdir_name = 'agreements';

	/**
	 * The name of the log table
	 *
	 * @var string
	 */
	protected $_log_table_name = '{db_prefix}log_agreement_accept';

	/**
	 * The database object
	 *
	 * @var Object
	 */
	protected $_db = null;

	/**
	 * Everything starts here.
	 *
	 * @param string $language the wanted language of the agreement.
	 * @param string $backup_dir where to store the backup of the agreements.
	 */
	public function __construct($language, $backup_dir = null)
	{
		$this->_language = ucfirst(strtr($language, array('.' => '')));

		if ($backup_dir === null || !file_exists($backup_dir))
		{
			$backup_dir = BOARDDIR . '/packages/backups/' . $this->_backupdir_name;
		}
		$this->_backup_dir = $backup_dir;
		$this->_db = database();
	}

	/**
	 * Stores a text into the agreement file.
	 * It stores strictly on the *language* agreement, no fallback.
	 * If the language passed to the class is empty, then it uses Agreement/English.txt.
	 *
	 * @param string $text the language of the agreement we want.
	 * @param bool $update_backup if store a copy of the text of the agreements.
	 * @return bool|string
	 */
	public function save($text, $update_backup = false)
	{
		$backup_id = '';
		if ($update_backup)
		{
			$backup_id = $this->storeBackup();
		}

		// Off it goes to the agreement file.
		$fp = fopen($this->buildName($this->_language), 'w');
		fwrite($fp, str_replace("\r", '', $text));
		fclose($fp);

		return $backup_id;
	}

	/**
	 * Creates a backup of the current version of the agreement/s.
	 *
	 * @return string|bool the backup_id if successful, false if creating the backup fails
	 */
	public function storeBackup()
	{
		$backup_id = $this->_backupId();
		if (!$this->_createBackup($backup_id))
		{
			$backup_id = false;
		}

		return $backup_id;
	}

	/**
	 * Retrieves the plain text version of the agreement directly from
	 * the file that contains it.
	 *
	 * It uses the language, but if the localized version doesn't exist
	 * then it may return the english version.
	 *
	 * @param bool $fallback if fallback to the English version (default true).
	 *
	 * @return bool|string
	 */
	public function getPlainText($fallback = true, $language = null)
	{
		$language = $language ?? $this->_language;
		$file = $this->buildName($language);

		// Have we got a localized one?
		if (file_exists($file))
		{
			return trim(file_get_contents($file));
		}

		if ($fallback)
		{
			return $this->getPlainText(false, 'English');
		}

		return '';
	}

	/**
	 * Retrieves the BBC-parsed version of the agreement.
	 *
	 * It uses the language, but if the localized version doesn't exist
	 * then it may return the english version.
	 *
	 * @param bool $fallback if fallback to the English version (default true).
	 *
	 * @return string
	 */
	public function getParsedText($fallback = true)
	{
		$bbc_parser = ParserWrapper::instance();

		return $bbc_parser->parseAgreement($this->getPlainText($fallback));
	}

	/**
	 * Retrieves the BBC-parsed version of the agreement.
	 *
	 * If the language passed to the class is empty, then it uses Agreement/English.txt.
	 */
	public function isWritable()
	{
		$filename = $this->buildName($this->_language);

		return file_exists($filename) && is_writable($filename);
	}

	/**
	 * Test if the user accepted the current agreement or not.
	 *
	 * @param int $id_member The id of the member
	 * @param string $version The date of the agreement
	 */
	public function checkAccepted($id_member, $version)
	{
		$accepted = $this->_db->fetchQuery('
			SELECT 1
			FROM ' . $this->_log_table_name . '
			WHERE version = {string:version}
				AND id_member = {int:id_member}',
			array(
				'id_member' => $id_member,
				'version' => $version,
			)
		);

		return !empty($accepted);
	}

	/**
	 * Log that the member accepted the agreement
	 *
	 * @param int $id_member
	 * @param string $ip
	 * @param string $version
	 * @throws \Exception
	 */
	public function accept($id_member, $ip, $version)
	{
		$db = database();

		$db->insert('ignore',
			$this->_log_table_name,
			array(
				'version' => 'string-20',
				'id_member' => 'int',
				'accepted_date' => 'date',
				'accepted_ip' => 'string-255',
			),
			array(
				array(
					'version' => $version,
					'id_member' => $id_member,
					'accepted_date' => Util::strftime('%Y-%m-%d', forum_time(false)),
					'accepted_ip' => $ip,
				)
			),
			array('version', 'id_member')
		);
	}

	protected function _backupId()
	{
		$backup_id = Util::strftime('%Y-%m-%d', forum_time(false));
		$counter = '';
		$merger = '';

		while (file_exists($this->_backup_dir . '/' . $backup_id . $merger . $counter . '/'))
		{
			$counter++;
			$merger = '_';
		}

		return $backup_id . $merger . $counter;
	}

	/**
	 * Creates a full backup of all the agreements.
	 *
	 * @param string $backup_id the name of the directory of the backup
	 * @return bool true if successful, false if fails to create the directory
	 */
	protected function _createBackup($backup_id)
	{
		$destination = $this->_backup_dir . '/' . $backup_id . '/';
		if (!file_exists($this->_backup_dir))
		{
			@mkdir($this->_backup_dir);
		}

		if (!@mkdir($destination))
		{
			return false;
		}

		$glob = new \GlobIterator(SOURCEDIR . '/ElkArte/Languages/' . $this->_file_name . '/*.txt', \FilesystemIterator::SKIP_DOTS);
		foreach ($glob as $file)
		{
			copy($file->getPathname(), $destination . $file->getBasename());
		}

		return true;
	}

	protected function buildName($language)
	{
		return SOURCEDIR . '/ElkArte/Languages/' . $this->_file_name . '/' . $language . '.txt';
	}
}
