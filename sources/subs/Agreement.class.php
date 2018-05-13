<?php

/**
 * This class takes care of the registration agreement
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

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
		$this->_language = strtr($language, array('.' => ''));
		if ($backup_dir === null || file_exists($backup_dir) === false)
		{
			$backup_dir = BOARDDIR . '/packages/backups/agreements';
		}
		$this->_backup_dir = $backup_dir;
		$this->_db = database();
	}

	/**
	 * Stores a text into the agreement file.
	 * It stores strictly on the *language* agreement, no fallback.
	 * If the language passed to the class is empty, then it uses agreement.txt.
	 *
	 * @param string $text the language of the agreement we want.
	 * @param bool $update_backup if store a copy of the text of the agreements.
	 */
	public function save($text, $update_backup = false)
	{
		$backup_id = '';
		if ($update_backup === true)
		{
			$backup_id = strftime('%Y-%m-%d', forum_time(false));
			if ($this->_createBackup($backup_id) === false)
			{
				$backup_id = false;
			}
		}

		// Off it goes to the agreement file.
		$fp = fopen(BOARDDIR . '/agreement' . $this->normalizeLanguage() . '.txt', 'w');
		fwrite($fp, str_replace("\r", '', $text));
		fclose($fp);

		return $backup_id;
	}

	/**
	 * Retrieves the plain text version of the agreement directly from
	 * the file that contains it.
	 *
	 * It uses the language, but if the localized version doesn't exist
	 * then it may return the english version.
	 *
	 * @param boolean $fallback if fallback to the English version (default true).
	 */
	public function getPlainText($fallback = true)
	{
		// Have we got a localized one?
		if (file_exists(BOARDDIR . '/agreement' . $this->normalizeLanguage() . '.txt'))
		{
			$agreement = file_get_contents(BOARDDIR . '/agreement' . $this->normalizeLanguage() . '.txt');
		}
		elseif ($fallback === true && file_exists(BOARDDIR . '/agreement.txt'))
		{
			$agreement = file_get_contents(BOARDDIR . '/agreement.txt');
		}
		else
		{
			$agreement = '';
		}

		return $agreement;
	}

	/**
	 * Retrieves the BBC-parsed version of the agreement.
	 *
	 * It uses the language, but if the localized version doesn't exist
	 * then it may return the english version.
	 *
	 * @param boolean $fallback if fallback to the English version (default true).
	 */
	public function getParsedText($fallback = true)
	{
		$bbc_parser = \BBC\ParserWrapper::instance();

		return $bbc_parser->parseAgreement($this->getPlainText($fallback));
	}

	/**
	 * Retrieves the BBC-parsed version of the agreement.
	 *
	 * If the language passed to the class is empty, then it uses agreement.txt.
	 */
	public function isWritable()
	{
		$filename = BOARDDIR . '/agreement' . $this->normalizeLanguage() . '.txt';

		return file_exists($filename) && is_writable($filename);
	}

	/**
	 * Test if the user accepted the current agreement or not.
	 *
	 * @param int $id_member The id of the member
	 * @param string $agreement_date The date of the agreement
	 */
	public function checkAccepted($id_member, $agreement_date)
	{
		$accepted = $this->_db->fetchQuery('
			SELECT 1
			FROM {db_prefix}log_agreement_accept
			WHERE agreement_date = {date:agreement_date}
				AND id_member = {int:id_member}',
			array(
				'id_member' => $id_member,
				'agreement_date' => $agreement_date,
			)
		);

		return empty($accepted);
	}

	/**
	 * Takes care of the edge-case of the default agreement that doesn't have
	 * the language in the name, and the fact that the admin panels loads it
	 * as an empty language.
	 */
	protected function normalizeLanguage()
	{
		return $this->_language === '' ? '' : '.' . $this->_language;
	}

	/**
	 * Creates a full backup of all the agreements.
	 *
	 * @param string $backup_id the name of the directory of the backup
	 * @return bool true if successful, false if failes to create the directory
	 */
	protected function _createBackup($backup_id)
	{
		$destination = $this->_backup_dir . '/' . $backup_id . '/';
		if (file_exists($this->_backup_dir) === false)
		{
			mkdir($this->_backup_dir);
		}
		if (mkdir($destination) === false)
		{
			return false;
		}
		$glob = new GlobIterator(BOARDDIR . '/agreement*.txt', FilesystemIterator::SKIP_DOTS);
		foreach ($glob as $file)
		{
			copy($file->getPathname(), $destination . $file->getBasename());
		}
		return true;
	}
}