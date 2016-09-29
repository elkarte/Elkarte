<?php

/**
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 2
 *
 */

namespace BBC;

/**
 * Class Autolink
 *
 * Class to change url and email text to BBC link codes [url] [email]
 *
 * @package BBC
 */
class Autolink
{
	/** @var Codes  */
	protected $bbc;
	/** @var bool  */
	protected $url_enabled;
	/** @var bool  */
	protected $email_enabled;
	/** @var bool  */
	protected $possible_link;
	/** @var bool  */
	protected $possible_email;
	/** @var array of search regex for urls  */
	protected $search;
	/** @var array of bbc url coded links  */
	protected $replace;
	/** @var array of search regex for email  */
	protected $email_search;
	/** @var array of bbc email coded links  */
	protected $email_replace;

	/**
	 * Autolink constructor.
	 *
	 * @param Codes $bbc
	 */
	public function __construct(Codes $bbc)
	{
		$this->bbc = $bbc;

		$this->url_enabled = !$this->bbc->isDisabled('url');
		$this->email_enabled = !$this->bbc->isDisabled('email');

		$this->load();
	}

	/**
	 * Load the autolink regular expressions to be used in autoLink()
	 */
	protected function load()
	{
		$search_url = array(
			'~(?<=[\s>\.(;\'"]|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\p{L}\p{N}\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\p{L}\p{N}\-_\~%@\?;=#}\\\\])~ui',
			'~(?<=[\s>(\'<]|^)(www(?:\.[\w\-_]+)+(?::\d+)?(?:/[\p{L}\p{N}\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\p{L}\p{N}\-_\~%@\?;=#}\\\\])~ui'
		);
		$replace_url = array(
			//'[url_auto=$1]$1[/url_auto]',
			//'[url_auto=$1]$1[/url_auto]',
			'[url]$1[/url]',
			'[url=http://$1]$1[/url]',
		);

		$search_email = array(
			'~(?<=[\?\s\x{A0}\[\]()*\\\;>]|^)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?,\s\x{A0}\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br />))~u',
			'~(?<=<br />)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?\.,;\s\x{A0}\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;)~u',
		);
		$replace_email = array(
			//'[email_auto]$1[/email_auto]',
			//'[email_auto]$1[/email_auto]',
			'[email]$1[/email]',
			'[email]$1[/email]',
		);

		// Allow integration an option to add / remove linking code
		call_integration_hook('integrate_autolink_load', array(&$search_url, &$replace_url, &$search_email, &$replace_email, $this->bbc));

		// Load them to the class
		$this->search = $search_url;
		$this->replace = $replace_url;

		if (empty($search_url) || empty($replace_url))
		{
			$this->url_enabled = false;
		}

		$this->email_search = $search_email;
		$this->email_replace = $replace_email;

		if (empty($search_email) || empty($replace_email))
		{
			$this->email_enabled = false;
		}
	}

	/**
	 * Parse links and emails in the data
	 *
	 * @param string $data
	 */
	public function parse($data)
	{
		if ($this->hasLinks($data))
		{
			$data = $this->parseLinks($data);
		}

		if ($this->hasEmails($data))
		{
			$data = $this->parseEmails($data);
		}

		call_integration_hook('integrate_autolink_area', array(&$data, $this->bbc));

		return $data;
	}

	/**
	 * Checks if the string has links of any form, http:// www.xxx
	 *
	 * @param string $data
	 *
	 * @return bool
	 */
	public function hasLinks($data)
	{
		return $this->hasPossibleLink() && (strpos($data, '://') !== false || strpos($data, 'www.') !== false);
	}

	/**
	 * Return if the message has possible urls to autolink
	 *
	 * @return bool
	 */
	public function hasPossibleLink()
	{
		return $this->possible_link;
	}

	/**
	 * Parse any URLs found in the data
	 *
	 * - Have to get rid of the @ problems some things cause... stupid email addresses.
	 *
	 * @param $data
	 */
	public function parseLinks($data)
	{
		// Switch out quotes really quick because they can cause problems.
		$data = strtr($data, array('&#039;' => '\'', '&nbsp;' => "\xC2\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));

		$result = preg_replace($this->search, $this->replace, $data);

		// Only do this if the preg survives.
		if (is_string($result))
		{
			$data = $result;
		}

		// Switch those quotes back
		return strtr($data, array('\'' => '&#039;', "\xC2\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
	}

	/**
	 * Validates if the data contains email address that need to be parsed
	 * @param string $data
	 *
	 * @return bool
	 */
	public function hasEmails($data)
	{
		return $this->hasPossibleEmail() && strpos($data, '@') !== false;
	}

	/**
	 * Return if the message has possible emails to autolink
	 *
	 * @return bool
	 */
	public function hasPossibleEmail()
	{
		return $this->possible_email;
	}

	/**
	 * Search and replace plain email address with bbc [email][/email]
	 *
	 * @param string $data
	 */
	public function parseEmails($data)
	{
		// Next, emails...
		return preg_replace($this->email_search, $this->email_replace, $data);
	}

	/**
	 * Quickly determine if the supplied message has potential linking code
	 *
	 * @param string $message
	 */
	public function setPossibleAutolink($message)
	{
		$possible_link = $this->url_enabled && (strpos($message, '://') !== false || strpos($message, 'www.') !== false);
		$possible_email = $this->email_enabled && strpos($message, '@') !== false;

		// Your autolink integration might use something like tel.123456789.call. This makes that possible.
		call_integration_hook('integrate_possible_autolink', array(&$possible_link, &$possible_email));

		$this->possible_link = $possible_link;
		$this->possible_email = $possible_email;
	}

	/**
	 * Return if the message has any possible links (email or url)
	 *
	 * @return bool
	 */
	public function hasPossible()
	{
		return $this->hasPossibleLink() || $this->hasPossibleEmail();
	}
}