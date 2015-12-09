<?php

/**
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 *
 */

namespace BBC;

class Autolink
{
	protected $bbc;
	protected $url_enabled;
	protected $email_enabled;
	protected $possible_link;
	protected $possible_email;
	protected $search;
	protected $replace;
	protected $email_search;
	protected $email_replace;

	public function __construct(Codes $bbc)
	{
		$this->bbc = $bbc;

		$this->url_enabled = !$this->bbc->isDisabled('url');
		$this->email_enabled = !$this->bbc->isDisabled('email');

		$this->load();
	}

	public function parse(&$data)
	{
		if ($this->hasLinks($data))
		{
			$this->parseLinks($data);
		}

		if ($this->hasEmails($data))
		{
			$this->parseEmails($data);
		}

		call_integration_hook('integrate_autolink_area', array(&$data, $this->bbc));
	}

	public function hasLinks($data)
	{
		return $this->hasPossibleLink() && (strpos($data, '://') !== false || strpos($data, 'www.') !== false);
	}

	// Parse any URLs.... have to get rid of the @ problems some things cause... stupid email addresses.
	public function parseLinks(&$data)
	{
		// Switch out quotes really quick because they can cause problems.
		$data = str_replace(array('&#039;', '&nbsp;', '&quot;', '"', '&lt;'), array('\'', "\xC2\xA0", '>">', '<"<', '<lt<'), $data);

		$result = preg_replace($this->search, $this->replace, $data);

		// Only do this if the preg survives.
		if (is_string($result))
		{
			$data = $result;
		}

		// Switch those quotes back
		$data = str_replace(array('\'', "\xC2\xA0", '>">', '<"<', '<lt<'), array('&#039;', '&nbsp;', '&quot;', '"', '&lt;'), $data);
	}

	public function hasEmails($data)
	{
		return $this->hasPossibleEmail() && strpos($data, '@') !== false;
	}

	public function parseEmails(&$data)
	{
		// Next, emails...
		$data = preg_replace($this->email_search, $this->email_replace, $data);
	}

	/**
	 * Load the autolink regular expression to be used in autoLink()
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

		call_integration_hook('integrate_autolink_load', array(&$search_url, &$replace_url, &$search_email, &$replace_email, $this->bbc));

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

	public function setPossibleAutolink($message)
	{
		$possible_link = $this->url_enabled && (strpos($message, '://') !== false || strpos($message, 'www.') !== false);
		$possible_email = $this->email_enabled && strpos($message, '@') !== false;

		// Your autolink integration might use something like tel.123456789.call. This makes that possible.
		call_integration_hook('integrate_possible_autolink', array(&$possible_link, &$possible_email));

		$this->possible_link = $possible_link;
		$this->possible_email = $possible_email;
	}

	public function hasPossible()
	{
		return $this->hasPossibleLink() || $this->hasPossibleEmail();
	}

	public function hasPossibleLink()
	{
		return $this->possible_link;
	}

	public function hasPossibleEmail()
	{
		return $this->possible_email;
	}
}