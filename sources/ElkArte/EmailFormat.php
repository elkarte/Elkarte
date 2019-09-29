<?php

/**
 * class that will reflow/format an email message to make it look like a post again
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Attempts, though an outrageous set of assumptions, to reflow/format an email message
 * that was previously wrapped by an email client (so it would look good on
 * a 80x24 screen)
 *
 * Removes extra spaces and newlines
 * Fixes some common punctuation errors seen in emails
 * Joins lines back together, where needed, to undo the 78 - 80 char wrap in email
 *
 * Really this is built on a house of cards and should generally be viewed
 * as an unfortunate evil if you want a post to *not* look like its an email.
 * It's always the innocent bystanders who suffer most.
 *
 * Load class
 * Initiate as
 *  - $formatter = new \ElkArte\EmailFormat();
 *
 * Make the call, accepts a string of data and returns it formatted
 * - $body = $formatter->reflow($body);
 *
 * @package Maillist
 */
class EmailFormat
{
	/**
	 * The full message section we will return
	 *
	 * @var string
	 */
	private $_body = null;

	/**
	 * The full message section broken in to parts
	 *
	 * @var mixed[]
	 */
	private $_body_array = array();

	/**
	 * Holds the current quote level we are in
	 *
	 * @var int
	 */
	private $_in_quote = 0;

	/**
	 * Holds the current code block level we are in
	 *
	 * @var int
	 */
	private $_in_code = 0;

	/**
	 * Holds the level of bbc list we are in
	 *
	 * @var int
	 */
	private $_in_bbclist = 0;

	/**
	 * Holds the level of plain list we are in
	 *
	 * @var int
	 */
	private $_in_plainlist = 0;

	/**
	 * Holds if we are in a plain text list
	 *
	 * @var int
	 */
	private $_in_list = 0;

	/**
	 * Set if we have entered an area of the message that is a signature block
	 *
	 * @var boolean
	 */
	private $_found_sig = false;

	/**
	 * Holds the members display name, used for signature check etc.
	 *
	 * @var string
	 */
	private $_real_name = null;

	/**
	 * Tuning value (fudge) used to decide if a line is short
	 * change with care, used to help figure out wrapping decisions
	 *
	 * @var int
	 */
	private $_maillist_short_line = null;

	/**
	 * Extra items to removed, defined in the acp
	 *
	 * @var string
	 */
	private $_maillist_leftover_remove = null;

	/**
	 * Items that may indicate the start of a signature line, defined in the acp
	 *
	 * @var string
	 */
	private $_maillist_sig_keys = null;

	/**
	 * Tuning delta value (fudge) to help indicate the last line in a paragraph
	 * change with care
	 *
	 * @var int
	 */
	private $_para_check = 25;

	/**
	 * tuning value used to define a long line in a signature line
	 * change with care
	 *
	 * @var int
	 */
	private $_sig_longline = 67;

	/**
	 * Main routine, calls the need functions in the order needed
	 *
	 * - Returns a formatted string
	 *
	 * @param string $data
	 * @param string $real_name
	 * @param string $charset
	 * @param bool $bbc_br
	 *
	 * @return string
	 */
	public function reflow($data, $real_name = '', $charset = 'UTF-8', $bbc_br = true)
	{
		global $modSettings;

		// load some acp settings in to the class
		$this->_maillist_short_line = empty($modSettings['maillist_short_line']) ? 33 : $modSettings['maillist_short_line'];
		$this->_maillist_leftover_remove = empty($modSettings['maillist_leftover_remove']) ? '' : $modSettings['maillist_leftover_remove'];
		$this->_maillist_sig_keys = empty($modSettings['maillist_sig_keys']) ? '' : $modSettings['maillist_sig_keys'];

		$this->_real_name = $real_name;
		$this->_prep_data($data, $bbc_br);
		$this->_fix_body();
		$this->_clean_up($charset);

		return $this->_body;
	}

	/**
	 * Takes a string of data and creates a line by line array broken on newlines
	 *
	 * - Builds all needed details for each array element, including length, if its
	 * in a quote (&depth) code (&depth) or list (bbc or plain) etc.
	 *
	 * @param string $data
	 * @param boolean $bbc_br
	 */
	private function _prep_data($data, $bbc_br)
	{
		// Un-wordwrap the email, create a line by line array broken on the newlines
		if ($bbc_br)
		{
			$data = str_replace('[br]', "\n", $data);
		}
		$temps = explode("\n", $data);

		// Remove any 'stuck' whitespace using the trim value function on all lines
		array_walk($temps, function (&$value) {
			$this->_trim_value($value);
		});

		foreach ($temps as $i => $temp)
		{
			$this->_body_array[$i]['content'] = $temp;
			$this->_body_array[$i]['length'] = Util::strlen($temp);

			// Text lists a) 1. etc
			$this->_body_array[$i]['list_item'] = $this->_in_plainlist($temp);

			// [quote]
			$this->_in_quote($temp);
			$this->_body_array[$i]['in_quote'] = $this->_in_quote;

			// [code]
			$this->_in_code($temp);
			$this->_body_array[$i]['in_code'] = $this->_in_code;

			// [list]
			$this->_in_bbclist($temp);
			$this->_body_array[$i]['in_bbclist'] = $this->_in_bbclist;
		}

		// Reset our index values
		$this->_in_bbclist = 0;
		$this->_in_code = 0;
		$this->_in_quote = 0;
		$this->_in_list = 0;
	}

	/**
	 * Callback function for array_walk to remove spaces
	 *
	 * - &nbsp; can be translated to 0xA0, or in UTF8 as chr(0xC2).chr(0xA0)
	 * this function looks to remove all of those in any form.  Needed because
	 * email is often has its character set mangled.
	 *
	 * @param string $value
	 */
	private function _trim_value(&$value)
	{
		$value = trim($value);
		$value = trim($value, chr(0xC2) . chr(0xA0));
		$value = trim($value, "\xA0");
		$value = trim($value);
	}

	/**
	 * Checks if a string starts with a plain list tag
	 * like 1) 1. a) b.
	 *
	 * @param string $var
	 *
	 * @return bool
	 */
	private function _in_plainlist($var)
	{
		// Starting a list like a) 1. 1) etc ...
		$temp = $this->_in_plainlist;

		if (preg_match('~^[a-j](\.|\)|-)\s~i', $var) || preg_match('~^[1-9](\.|\)|-)\s?~', $var) || preg_match('~' . chr(187) . '~', $var))
		{
			$this->_in_plainlist++;
		}

		return $this->_in_plainlist !== $temp;
	}

	/**
	 * Checks if a string is the start or end of a bbc [quote] line
	 *
	 * - Keeps track of the tag depth
	 *
	 * @param string $var
	 */
	private function _in_quote($var)
	{
		// In a quote?
		if (preg_match('~\[quote( author=.*)?\]?~', $var))
		{
			// Make sure it is not a single line quote
			if (!preg_match('~\[/quote\]?~', $var))
			{
				$this->_in_quote++;
			}
		}
		elseif (preg_match('~\[/quote\]?~', $var))
		{
			$this->_in_quote--;
		}
	}

	/**
	 * Checks if a string is the start or end of a bbc [code] tag
	 *
	 * - Keeps track of the tag depth
	 *
	 * @param string $var
	 */
	private function _in_code($var)
	{
		// In a code block?
		if (preg_match('~\[code\]?~', $var))
		{
			// Make sure it is not a single line code
			if (!preg_match('~\[/code\]?~', $var))
			{
				$this->_in_code++;
			}
		}
		elseif (preg_match('~\[/code\]?~', $var))
		{
			$this->_in_code--;
		}
	}

	/**
	 * Checks if a string is the start or end of a bbc [list] tag
	 *
	 * - Keeps track of the tag depth
	 *
	 * @param string $var
	 */
	private function _in_bbclist($var)
	{
		// Starting a bbc list
		if (preg_match('~\[list\]?~', $var))
		{
			$this->_in_bbclist++;
		}
		// Ending a bbc list
		elseif (preg_match('~\[\/list\]?~', $var))
		{
			$this->_in_bbclist--;
		}
	}

	/**
	 * Goes through the message array and only inserts line feeds (breaks) where
	 * they are needed, allowing all other text to flow in one line.
	 *
	 * - Insets breaks at blank lines, around bbc quote/code/list, text lists,
	 * signature lines and end of paragraphs ... all assuming it can figure or
	 * best guess those areas.
	 */
	private function _fix_body()
	{
		// Go line by line and put in line breaks *only* where (we often erroneously assume) they are needed
		for ($i = 0, $num = count($this->_body_array); $i < $num; $i++)
		{
			// We are already in a text list, and this current line does not start the next list item
			if ($this->_in_list && !$this->_body_array[$i]['list_item'])
			{
				// Are we at the last known list item?, if so we can turn wrapping off
				if (isset($this->_body_array[$i + 1]) && $this->_in_list === $this->_in_plainlist)
				{
					$this->_body_array[$i - 1]['content'] = $this->_body_array[$i - 1]['content'] . "\n";
					$this->_in_list = 0;
				}
				else
				{
					$this->_body_array[$i]['content'] = ' ' . trim($this->_body_array[$i]['content']);
				}
			}

			// Long line in a sig ... but not a link then lets bail out might be a ps or something
			if ($this->_found_sig && ($this->_body_array[$i]['length'] > $this->_sig_longline) && (substr($this->_body_array[$i]['content'], 0, 4) !== 'www.'))
			{
				$this->_found_sig = false;
			}

			// Blank line, if its not two in a row and not the start of a bbc code then insert a newline
			if ($this->_body_array[$i]['content'] == '')
			{
				if ((isset($this->_body_array[$i - 1])) && ($this->_body_array[$i - 1]['content'] !== "\n") && (substr($this->_body_array[$i - 1]['content'], 0, 1) !== '[') && ($this->_body_array[$i - 1]['length'] > $this->_maillist_short_line))
				{
					$this->_body_array[$i]['content'] = "\n\n";
				}
			}
			// Lists like a. a) 1. 1)
			elseif ($this->_body_array[$i]['list_item'])
			{
				$this->_in_list++;
				$this->_body_array[$i]['content'] = "\n" . $this->_body_array[$i]['content'];
			}
			// Signature line start as defined in the ACP, i.e. best, regards, thanks
			elseif ($this->_in_sig($i))
			{
				$this->_body_array[$i]['content'] = "\n\n\n" . $this->_body_array[$i]['content'];
				$this->_found_sig = true;
			}
			// Message stuff which should not be here any longer (as defined in the ACP) i.e. To: From: Subject:
			elseif (!empty($this->_maillist_leftover_remove) && preg_match('~^((\[b\]){0,2}(' . $this->_maillist_leftover_remove . ')(\[\/b\]){0,2})~', $this->_body_array[$i]['content']))
			{
				if ($this->_in_quote)
				{
					$this->_body_array[$i]['content'] = "\n";
				}
				else
				{
					$this->_body_array[$i]['content'] = $this->_body_array[$i]['content'] . "\n";
				}
			}
			// Line starts with a link .....
			elseif (in_array(substr($this->_body_array[$i]['content'], 0, 4), array('www.', 'WWW.', 'http', 'HTTP')))
			{
				$this->_body_array[$i]['content'] = "\n" . $this->_body_array[$i]['content'];
			}
			// Previous line ended in a break already
			elseif (isset($this->_body_array[$i - 1]['content']) && substr(trim($this->_body_array[$i - 1]['content']), -4) == '[br]')
			{
				// Nothing to do then
				$this->_body_array[$i]['content'] .= '';
			}
			// OK, we can't seem to think of other obvious reasons this should not be on the same line
			// and these numbers are quite frankly subjective, but so is how we got here, final "check"
			else
			{
				// Its a wrap ... maybe
				if ($i > 0)
				{
					$para_check = $this->_body_array[$i]['length'] - $this->_body_array[$i - 1]['length'];
				}
				else
				{
					$para_check = 1;
				}

				// If this line is longer than the line above it we need to do some extra checks
				if (($i > 0) && ($this->_body_array[$i - 1]['length'] > $this->_maillist_short_line) && !$this->_found_sig && !$this->_in_code && !$this->_in_bbclist)
				{
					// If the previous short line did not end in a period or it did and the next line does not start
					// with a capital and passes para check then it wraps
					if ((substr($this->_body_array[$i - 1]['content'], -1) !== '.') || (substr($this->_body_array[$i - 1]['content'], -1) === '.' && $para_check < $this->_para_check && ($this->_body_array[$i]['content'][0] !== strtoupper($this->_body_array[$i]['content'][0]))))
					{
						$this->_body_array[$i]['content'] .= '';
					}
					else
					{
						$this->_body_array[$i]['content'] = "\n" . $this->_body_array[$i]['content'];
					}
				}
				elseif ($para_check < 5)
				{
					$this->_body_array[$i]['content'] = "\n" . $this->_body_array[$i]['content'];
				}
				// A very short line (but not a empty one) followed by a very long line
				elseif (isset($this->_body_array[$i - 1]) && !empty($this->_body_array[$i - 1]['content']) && $para_check > $this->_sig_longline && $this->_body_array[$i - 1]['length'] < 3)
				{
					$this->_body_array[$i]['content'] .= '';
				}
				else
				{
					$this->_body_array[$i]['content'] = "\n\n" . $this->_body_array[$i]['content'];
				}
			}
		}

		// Close any open quotes we may have left behind
		for ($quotes = 1; $quotes <= $this->_in_quote; $quotes++)
		{
			$this->_body_array[$i + $quotes]['content'] = '[/quote]';
		}

		// Join the message back together while dropping null index's
		$temp = array();
		foreach ($this->_body_array as $key => $values)
		{
			$temp[] = $values['content'];
		}
		$this->_body = trim(implode(' ', array_values($temp)));
	}

	/**
	 * Checks if a string is the potentially the start of a signature line
	 *
	 * @param int $i
	 *
	 * @return bool
	 */
	private function _in_sig($i)
	{
		// Not in a sig yet, the line starts with a sig key as defined by the ACP, and its a short line of text
		if (!$this->_found_sig && !empty($this->_maillist_sig_keys) && (preg_match('~^(' . $this->_maillist_sig_keys . ')~i', $this->_body_array[$i]['content']) && ($this->_body_array[$i]['length'] < $this->_maillist_short_line)))
		{
			return true;
		}
		// The line is simply just their name
		elseif (($this->_body_array[$i]['content'] === $this->_real_name) && !$this->_found_sig)
		{
			return true;
		}
		// check for universal sig dashes
		elseif (!$this->_found_sig && preg_match('~^-- \n~m', $this->_body_array[$i]['content']))
		{
			return true;
		}

		return false;
	}

	/**
	 * Repairs common problems either caused by the reflow or just things found
	 * in emails.
	 *
	 * @param string $charset
	 */
	private function _clean_up($charset)
	{
		// Remove any chitta chatta from either end
		$tag = '(>([^a-zA-Z0-9_\[\s]){0,3}){1}';
		$this->_body = preg_replace("~\n" . $tag . '~', "\n", $this->_body);

		// Clean up double breaks found between bbc formatting tags, msoffice loves to do this
		$this->_body = preg_replace('~\]\s*\[br\]\s*\[br\]\s*\[~s', '][br][', $this->_body);

		// Repair the &nbsp; in its various states and any other chaff
		$this->_body = strtr($this->_body, array(' &nbsp;' => ' ', '&nbsp; ' => ' ', "\xc2\xa0" => ' ', "\xe2\x80\xa8" => "\n"));

		// Trailing space before an end quote
		$this->_body = preg_replace('~\s*\n\s*\[/quote\]~', '[/quote]', $this->_body);

		// Any number of spaces (including none), followed by newlines, followed by any number of spaces (including none),
		$this->_body = preg_replace("~(\s*[\n]\s*){2,}~", "\n\n", $this->_body);

		// Whats with multiple commas ??
		$this->_body = preg_replace('~(\s*[,]\s*){2,}~', ', ', $this->_body);

		// commas ,in ,the ,wrong ,place? ... find a space then a word starting with a comma broken on word boundary's
		$this->_body = preg_replace('~(?:^|\s),(\w+)\b~', ', $1', $this->_body);

		// Punctuation missing a space like about.This ... should be about. This or about,this should be about, this
		// ... did no one go to school? OK it probably is from our parser :P ...
		// Look for a word boundary, any number of word characters, a single lower case, a period a single uppercase
		// any number of word characters and a boundary
		$this->_body = preg_replace('~(\b\w+[a-z])\.([A-Z]\w+)\b~', '$1. $2', $this->_body);
		$this->_body = preg_replace('~(\b\w+[A-z])\,([A-z]\w+)\b~', '$1, $2', $this->_body);
		$this->_body = preg_replace('~(\b\w+[A-z])\,([A-Z]\w+)\b~', '$1, $2', $this->_body);
		$this->_body = preg_replace('~(\b\w+[a-z])\,([a-z]\w+)\b~', '$1, $2', $this->_body);
		$this->_body = preg_replace('~(\b\w+[a-z])\s\.([A-Z]\w+)\b~', '$1. $2', $this->_body);

		// Some tags often end up as just dummy tags, bla bla bla you have read this before yes?
		$this->_body = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $this->_body);
		$this->_body = preg_replace('~\[quote\]\s*\[/quote\]~', '', $this->_body);

		// Make sure an email did not end up as the authors name .... [quote author=Joe Blow [email]joeblow@gmail.com[/email]]
		$this->_body = preg_replace('~\[quote (author=.*)\[email].*\[/email\]\]~', '[quote $1]', $this->_body);

		// Any htmlenties that we want to remove, like ms smart ones?
		if (preg_match('~&#8220;|&#8221;|&#8211;|&#8212;|&#8216|&#8217;~', $this->_body))
		{
			$this->_body = html_entity_decode($this->_body, ENT_QUOTES, 'UTF-8');
		}

		// Avoid double encoding later on
		$this->_body = htmlspecialchars_decode($this->_body, ENT_QUOTES);

		// Convert other characters like MS "smart" quotes both uf8
		$this->_body = strtr($this->_body, array("\xe2\x80\x98" => "'", "\xe2\x80\x99" => "'", "\xe2\x80\x9c" => '"', "\xe2\x80\x9d" => '"', "\xe2\x80\x93" => '-', "\xe2\x80\x94" => '--', "\xe2\x80\xa6" => '...'));

		// And its 1252 variants
		if ($charset !== 'UTF-8')
		{
			$this->_body = strtr($this->_body, array(chr(145) => "'", chr(146) => "'", chr(147) => '"', chr(148) => '"', chr(150) => '-', chr(151) => '--', chr(133) => '...'));
		}
	}
}
