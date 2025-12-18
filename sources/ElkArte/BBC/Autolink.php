<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
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
	/** @var Codes */
	protected $bbc;

	/** @var bool */
	protected $url_enabled;

	/** @var bool */
	protected $email_enabled;

	/** @var bool */
	protected $possible_link;

	/** @var bool */
	protected $possible_email;

	/** @var array search regex for urls */
	protected $search;

	/** @var array bbc url coded links */
	protected $replace;

	/** @var array search regex for email */
	protected $email_search;

	/** @var array bbc email coded links */
	protected $email_replace;

	/**
	 * Autolink constructor.
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
	protected function load(): void
	{
		$search_url = [
			'~(?<=[\s>\.(;\'"]|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\p{L}\p{N}\-_\~%\.@!,\?&;=*#(){}+:\'\\\\]*)*[/\p{L}\p{N}\-_\~%@\?;=#}\\\\])~ui',
			'~(?<=[\s>(\'<]|^)(www(?:\.[\w\-_]+)+(?::\d+)?(?:/[\p{L}\p{N}\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\p{L}\p{N}\-_\~%@\?;=#}\\\\])~ui'
		];
		$replace_url = [
			//'[url_auto=$1]$1[/url_auto]',
			//'[url_auto=$1]$1[/url_auto]',
			'[url]$1[/url]',
			'[url=https://$1]$1[/url]',
		];

		$search_email = [
			'~(?<=[\?\s\x{A0}\[\]()*\\\;>]|^)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?,\s\x{A0}\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br />))~u',
			'~(?<=<br />)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?\.,;\s\x{A0}\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;)~u',
		];
		$replace_email = [
			//'[email_auto]$1[/email_auto]',
			//'[email_auto]$1[/email_auto]',
			'[email]$1[/email]',
			'[email]$1[/email]',
		];

		// Allow integration an option to add / remove linking code
		call_integration_hook('integrate_autolink_load', [&$search_url, &$replace_url, &$search_email, &$replace_email, $this->bbc]);

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
	 *
	 * @return null|string|string[]
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

		call_integration_hook('integrate_autolink_area', [&$data, $this->bbc]);

		return $data;
	}

	/**
	 * Checks if the string has links of any form, http:// www.xxx
	 *
	 * @param string $data
	 *
	 * @return bool
	 */
	public function hasLinks($data): bool
	{
		return $this->hasPossibleLink() && (str_contains($data, '://') || str_contains($data, 'www.'));
	}

	/**
	 * Return if the message has possible urls to autolink
	 *
	 * @return bool
	 */
	public function hasPossibleLink(): bool
	{
		return $this->possible_link;
	}

	/**
	 * Parse any URLs found in the data
	 *
	 * - Have to get rid of the @ problems some things cause... stupid email addresses.
	 *
	 * @param $data
	 *
	 * @return string
	 */
	public function parseLinks($data): string
	{
		// Switch out quotes really quickly because they can cause problems.
		$data = strtr($data, ['&#039;' => "'", '&nbsp;' => "\xC2\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<']);

		$result = preg_replace($this->search, $this->replace, $data);

		// Only do this if the preg survives.
		if (is_string($result))
		{
			$data = $result;
		}

		// Switch those quotes back
		return strtr($data, ["'" => '&#039;', "\xC2\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;']);
	}

	/**
	 * Validates if the data contains email address that needs to be parsed
	 *
	 * @param string $data
	 *
	 * @return bool
	 */
	public function hasEmails($data): bool
	{
		return $this->hasPossibleEmail() && str_contains($data, '@');
	}

	/**
	 * Return if the message has possible emails to autolink
	 *
	 * @return bool
	 */
	public function hasPossibleEmail(): bool
	{
		return $this->possible_email;
	}

	/**
	 * Search and replace plain email address with bbc [email][/email]
	 *
	 * @param string $data
	 *
	 * @return null|string|string[]
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
	public function setPossibleAutolink($message): void
	{
		$possible_link = $this->url_enabled && (str_contains($message, '://') || str_contains($message, 'www.'));
		$possible_email = $this->email_enabled && str_contains($message, '@');

		// Your autolink integration might use something like tel.123456789.call. This makes that possible.
		call_integration_hook('integrate_possible_autolink', [&$possible_link, &$possible_email]);

		$this->possible_link = $possible_link;
		$this->possible_email = $possible_email;
	}

	/**
	 * Return if the message has any possible links (email or url)
	 *
	 * @return bool
	 */
	public function hasPossible(): bool
	{
		if ($this->hasPossibleLink())
		{
			return true;
		}

		return $this->hasPossibleEmail();
	}
}
