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
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;

/**
 * Class Agreement
 *
 * A simple class to take care of the registration agreement
 */
class Agreement
{
	/** @var string Default language of the agreement. */
	protected $_language = '';

	/** @var string The directory where backups are stored */
	protected $_backup_dir = '';

	/** @var string The name of the file where the agreement is stored */
	protected $_file_name = 'Agreement';

	/** @var string The name of the directory where the backup will be saved */
	protected $_backupdir_name = 'agreements';

	/** @var string The name of the log table */
	protected $_log_table_name = '{db_prefix}log_agreement_accept';

	/** @var Object The database object */
	protected $_db;

	/** @var FileFunctions */
	protected $fileFunc;

	/**
	 * Everything starts here.
	 *
	 * @param string $language the wanted language of the agreement.
	 * @param string $backup_dir where to store the backup of the agreements.
	 */
	public function __construct($language, $backup_dir = null)
	{
		$this->fileFunc = FileFunctions::instance();
		$this->_language = ucfirst(strtr($language, array('.' => '')));

		if ($backup_dir === null || !$this->fileFunc->fileExists($backup_dir))
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
		$fp = fopen($this->buildName($this->_language), 'wb');
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
			return false;
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
	 * @return string
	 */
	public function getPlainText($fallback = true, $language = null)
	{
		$language = $language ?? $this->_language;
		$file = $this->buildName($language);

		// Have we got a localized one?
		if ($this->fileFunc->fileExists($file))
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
		return ParserWrapper::instance()->parseAgreement($this->getPlainText($fallback));
	}

	/**
	 * Checks to see if the file exists and is writable
	 *
	 * If the file does not exist, attempts to create it.
	 */
	public function isWritable()
	{
		$filename = $this->buildName($this->_language);

		if (!$this->fileFunc->fileExists($filename) && !$this->fileFunc->isDir($filename))
		{
			touch($filename);
			$this->fileFunc->chmod($filename);
		}

		return $this->fileFunc->fileExists($filename) && $this->fileFunc->isWritable($filename);
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

	/**
	 * Generates a backup ID
	 *
	 * This method generates a unique backup ID using the current timestamp.
	 * If a backup folder with the same ID already exists, a counter is appended to the ID
	 * until a unique backup ID is generated.
	 *
	 * @return string The generated backup ID
	 */
	protected function _backupId()
	{
		$backup_id = Util::strftime('%Y-%m-%d', forum_time(false));
		$counter = '';
		$merger = '';

		while ($this->fileFunc->fileExists($this->_backup_dir . '/' . $backup_id . $merger . $counter . '/'))
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
		if (!$this->fileFunc->fileExists($this->_backup_dir))
		{
			$this->fileFunc->createDirectory($this->_backup_dir, false);
		}

		if (!$this->fileFunc->createDirectory($destination, false))
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

	/**
	 * Builds the name and location of the file
	 *
	 * @param string $language
	 * @return string
	 */
	protected function buildName($language)
	{
		return SOURCEDIR . '/ElkArte/Languages/' . $this->_file_name . '/' . $language . '.txt';
	}
}
