<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mail;

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\Emoji;
use ElkArte\Languages\Txt;

class PreparseMail extends BaseMail
{
	/**
	 * Constructor, do anything needed
	 */
	public function __construct()
	{
		parent::__construct();

		Txt::load('Maillist');
	}

	/**
	 * Prepares a post/pm HTML, such that it is better suited for HTML email and
	 * conversion to markdown / plain text
	 *
	 * - Censors everything it will send
	 * - Pre-converts select bbc tags to html, so they are more generic
	 * - Uses parse-bbc to convert remaining bbc to html
	 *
	 * @param string $message the post in glorious html format
	 * @return string html text suitable for html2md or html output
	 */
	public function preparseHtml($message)
	{
		// Clean it up, can't have naughty words in an email
		$message = censor($message);

		// Convert bbc [quotes] before we go to parsebbc so they are easier to plain-textify later
		$message = preg_replace_callback('~\[quote[^]]*?]~iu', [$this, 'quoteCallback'], $message);
		$message = str_replace('[/quote]', '</blockquote>', $message);

		// Prevent img tags from getting linked
		$message = preg_replace('~\[img](.*?)\[/img]~is', '`&lt;img src="\\1">', $message);

		// Protect code tags from parseBBC
		$preparse = PreparseCode::instance('');
		$message = $preparse->tokenizeCodeBlocks($message);

		// Convert :emoji: tags to html versions
		$emoji = Emoji::instance();
		$message = $emoji->emojiNameToImage($message, true, false);

		// Allow addons to account for their own unique bbc additions e.g. gallery's etc.
		call_integration_hook('integrate_mailist_pre_parsebbc', [&$message]);

		// Convert the remaining bbc to html
		$bbc_wrapper = ParserWrapper::instance();
		$message = $bbc_wrapper->parseMessage(trim($message), false);

		// Drop quote-show-more input box
		$message = str_replace('<input type="checkbox" title="show" class="quote-show-more">', '', $message);

		// Change list style to something standard to make text conversion easier
		$message = preg_replace('~<ul class="bbc_list" style="list-style-type: decimal;">(.*?)</ul>~si', '<ol>\\1</ol>', $message);

		// Do we have any tables? if so we add in th's based on the number of cols.
		$message = $this->preparseTables($message);

		// Allow addons to account for their own unique bbc additions e.g. gallery's etc.
		call_integration_hook('integrate_mailist_pre_markdown', [&$message]);

		// Restore code blocks
		$message = $preparse->restoreCodeBlocks($message);
		$message = preg_replace('~\[code(.*?)](.*?)\[/code]~is', '<code$1>$2</code>', $message);
		$message = preg_replace('~\[icode](.*?)\[/icode]~is', '<span class="bbc_code_inline">$1</span>', $message);

		// Convert the protected (hidden) entities back for the final conversion
		return strtr($message, ['&#91;' => '[', '&#93;' => ']', '`&lt;' => '<']);
	}

	/**
	 * Replace full bbc quote tags with html blockquote version where the cite line
	 * is used as the first line of the quote.
	 *
	 * - Callback for preparseHtml
	 * - Only replaces opening [quote] tags, the closing /quote is replaced back in
	 * the main function
	 *
	 * @param string[] $matches array of matches from the regex in the preg_replace
	 * @return string
	 */
	private function quoteCallback($matches)
	{
		global $txt;

		$date = '';
		$author = $txt['quote'];

		if (preg_match('~date=(\d{8,10})~ui', $matches[0], $match) === 1)
		{
			$date = $txt['email_on'] . ': ' . date('D M j, Y', $match[1]);
		}

		if (preg_match('~author=([^<>\n]+?)(?=(?:link=|date=|\]))~ui', $matches[0], $match) === 1)
		{
			$author = $match[1] .  $txt['email_wrote'] . ': ';
		}

		return '<blockquote><cite>' . $date . ' ' . $author . '</cite><hr>';
	}

	/**
	 * Checks if a table has the required <th> line, such that markdown will convert it properly.  If
	 * it is missing it will add a simple numerical value for each col in the table.
	 *
	 * @param string $message
	 * @return string
	 */
	private function preparseTables($message)
	{
		// Do we have any tables? if so we may need to add in th's based on the number of cols.
		$table_content = [];
		if (preg_match_all('~<table class="bbc_table">(.*?)</tr>.*?</table>~si', $message, $table_content, PREG_SET_ORDER))
		{
			// The answer is yes ... work on each one
			foreach ($table_content as $table_temp)
			{
				$cols = substr_count($table_temp[1], '<td>');
				$table_header = '';

				// Build the th line for this table
				for ($i = 1; $i <= $cols; $i++)
				{
					$table_header .= '<th>- ' . $i . ' -</th>';
				}

				// Insert it in to the table tag
				$table_header = '<tr>' . $table_header . '</tr>';
				$new_table = str_replace('<table class="bbc_table">', '<br /><table>' . $table_header, $table_temp[0]);

				// Replace the old table with the new th enabled one
				$message = str_replace($table_temp[0], $new_table, $message);
			}
		}

		return $message;
	}

	/**
	 * You have some filthy mouth ! Censor the subject
	 *
	 * @param $subject
	 * @return string
	 */
	public function preparseSubject($subject)
	{
		// What are you trying to do, get the IP blocked?
		return censor(un_htmlspecialchars($subject));
	}

	/**
	 * Ugh, the signature.  Strip tags, adding in newlines where needed to maintain some
	 * basic formatting.
	 *
	 * @param $signature
	 * @return string
	 */
	public function preparseSignature($signature)
	{
		// The signature goes as just plain text
		if ($signature !== '')
		{
			// You would like to say that, but its spam, like most signatures
			$signature = censor($signature);

			call_integration_hook('integrate_mailist_pre_sig_parsebbc', [&$signature]);

			$bbc_wrapper = ParserWrapper::instance();
			$signature = $bbc_wrapper->parseSignature($signature, false);

			// No html in plain text, but insert some block level line breaks
			$trans = [
				'</div>' => "\n",
				'</tr>' => "\n",
				'</li>' => "\n",
				'</p>' => "\n",
				'<br>' => "\n",
				'<br />' => "\n",
				'</blockquote>' => "\n",
				'&#91;' => '[',
				'&#93;' => ']'
			];

			$signature = trim(un_htmlspecialchars(strip_tags(strtr($signature, $trans))));
			$signature = '<hr />' . str_replace("\n", '<br />', $signature);
		}

		return $signature;
	}
}