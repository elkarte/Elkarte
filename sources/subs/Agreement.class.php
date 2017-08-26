<?php

/**
 * This class takes care of the registration agreement
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
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
	 * Everything starts here.
	 *
	 * @param string $language the wanted language of the agreement.
	 */
	public function __construct($language)
	{
		$this->_language = strtr($language, array('.' => ''));
	}

	/**
	 * Stores a text into the agreement file.
	 * It stores strictly on the *language* agreement, no fallback.
	 * If the language passed to the class is empty, then it uses agreement.txt.
	 *
	 * @param string $text the language of the agreement we want.
	 */
	public function save($text)
	{
		// Off it goes to the agreement file.
		$fp = fopen(BOARDDIR . '/agreement' . $this->normalizeLanguage() . '.txt', 'w');
		fwrite($fp, str_replace("\r", '', $text));
		fclose($fp);
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
	 * Takes care of the edge-case of the default agreement that doesn't have
	 * the language in the name, and the fact that the admin panels loads it
	 * as an empty language.
	 */
	protected function normalizeLanguage()
	{
		return $this->_language === '' ? '' : '.' . $this->_language;
	}
}