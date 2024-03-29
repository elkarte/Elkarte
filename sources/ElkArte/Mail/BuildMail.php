<?php

/**
 * This class deals with the creation of email headers and email message encodings
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mail;

use ElkArte\Converters\Html2Md;

class BuildMail extends BaseMail
{
	/** @var array All the required headers to send */
	public $headers = [];

	/** @var string Mime message composed of various sections/boundaries */
	public $message;

	/** @var array Optional method to allow template replacements array insertion */
	public $replacements = [];

	/**
	 * This function builds an email and its headers.
	 *
	 * It uses the mail_type settings and webmaster_email variable.
	 *
	 * @param string[]|string $to - the email(s) to send to
	 * @param string $subject - email subject, expected to have entities, and slashes, but not be parsed
	 * @param string $message - email body, expected to have slashes, no htmlentities
	 * @param string|null $from = null - the address to use for replies
	 * @param string|null $message_id = null - if specified, it will be used as local part of the Message-ID header.
	 * @param bool $send_html = false, if the message is HTML vs. plain text
	 * @param int $priority = 3 Useful when the queue is enabled.
	 *  - 0 will bypass any queue and send now,
	 * - 4 (digest),
	 * - 5 (newsletter) will bypass any PBE settings.
	 * - 3 normal email,
	 * - 1/2 registrations etc.
	 * @param bool $is_private redacts names from appearing in the mail queue
	 * @param string|null $from_wrapper - used to provide envelope from wrapper based on if we share a
	 * users display name
	 * @param int|null $reference - The parent topic id for use in a References header
	 * @return bool If the email was accepted properly.
	 * @package Mail
	 */
	public function buildEmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $is_private = false, $from_wrapper = null, $reference = null)
	{
		global $modSettings;

		$priority = (int) $priority;

		// Use maillist styles ?
		$this->setMailList($from_wrapper, $message_id, $priority);

		// Set line breaks as required by OS and Transport
		$message = $this->setMessageLineBreak($message);

		// If the recipient list isn't an array, make it one.
		$to_array = is_array($to) ? $to : [$to];

		// Get rid of entities in the subject line
		$subject = un_htmlspecialchars($subject);

		// Make the message use the proper line breaks.
		$message = str_replace(["\r", "\n"], ['', $this->lineBreak], $message);

		// Support Basic DMARC Compliance when in MLM mode
		$from = $this->setDMARCFrom($from, $from_wrapper);

		// Take care of from / subject encodings
		$from_name = $this->setFromName($from);
		[$subject] = $this->mimeSpecialChars($subject);

		// Construct from / replyTo mail headers, based on if we show a users name
		$this->setFromHeaders($from, $from_name, $from_wrapper, $reference);

		// Return path, date, mailer
		if ($this->useSendmail)
		{
			// The final destination SMTP server should be setting this value, not us.
			$this->setReturnPath();
			$this->headers[] = 'Return-Path: <' . $this->returnPath . '>';
		}

		$this->headers[] = 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000';
		$this->headers[] = 'X-Mailer: ELK';

		// For maillist, digests or newsletters we include a few more headers for compliance
		$this->setDigestHeaders($priority);

		// Pass this to the integration before we start modifying the output -- it'll make it easier later.
		if (in_array(false, call_integration_hook('integrate_outgoing_email', [&$subject, &$message, &$this->headers]), true))
		{
			return false;
		}

		// The mime boundary separates the different alternative versions, like plain text, base64, html
		// For strict compliance we keep this line to 78 charters, (one could flow the headers too)
		$mime_boundary = 'ELK-' . substr(md5(uniqid(mt_rand(), true) . microtime()), 0, 28);

		// Using mime, as it allows to send a plain unencoded alternative.
		$this->headers[] = 'Mime-Version: 1.0';
		$this->headers[] = 'Content-Type: multipart/alternative; boundary="' . $mime_boundary . '"';
		$this->headers[] = 'Content-Transfer-Encoding: 7bit';

		// Generate our completed `standard` header string
		$headers = implode($this->lineBreak, $this->headers);

		// Now build our message with various encodings
		$message = $this->getMessage($send_html, $mime_boundary, $message, $subject);

		// Are we using the mail queue, if so this is where we butt in...
		if (!empty($modSettings['mail_queue']) && $priority !== 0)
		{
			return AddMailQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private, $message_id);
		}

		// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
		if (!empty($modSettings['mail_queue']) && !empty($modSettings['mail_period_limit']))
		{
			[$last_mail_time, $mails_this_minute] = @explode('|', $modSettings['mail_recent']);
			if (empty($mails_this_minute) || time() > (int) $last_mail_time + 60)
			{
				$new_queue_stat = time() . '|' . 1;
			}
			else
			{
				$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);
			}

			updateSettings(['mail_recent' => $new_queue_stat]);
		}

		// SMTP or sendmail?
		$mail = new Mail();
		$mail->mailList = $this->mailList;
		$mail_result = $mail->sendMail($to_array, $subject, $headers, $message, $message_id);

		// Clear out the stat cache.
		trackStats();

		// Everything go smoothly?
		return $mail_result;
	}

	/**
	 * Sets the message line break to what is required for the transport in use
	 *
	 * @param string $message
	 * @return string
	 */
	public function setMessageLineBreak($message)
	{
		// Make the message use the proper line breaks.
		return str_replace(["\r", "\n"], ['', $this->lineBreak], $message);
	}

	/**
	 * Cleans the header when using PBE/Maillist functions to prevent message from field
	 * failing DMARC checking.
	 *
	 * @param string $from
	 * @param string $from_wrapper
	 * @return string
	 */
	public function setDMARCFrom($from, $from_wrapper)
	{
		global $modSettings;

		$dmarc_from = $from;

		// Requirements (draft) for Mail List Message (MLM) to Support Basic DMARC Compliance
		// https://www.dmarc.org/supplemental/mailman-project-mlm-dmarc-reqs.html
		if ($this->mailList && $from !== null && $from_wrapper !== null)
		{
			// Be sure there is never an email in the from name when using maillist styles
			if (filter_var($dmarc_from, FILTER_VALIDATE_EMAIL))
			{
				$dmarc_from = str_replace(strstr($dmarc_from, '@'), '', $dmarc_from);
			}

			// Add in the 'via' if desired, helps prevent email clients from learning/replacing legit names/emails
			if (!empty($modSettings['maillist_sitename']) && empty($modSettings['dmarc_spec_standard']))
			{
				// @memo (2014) "via" is still a draft, and it's not yet clear if it will be localized or not.
				// To play safe, we are keeping it hard-coded, but the string is available for translation.
				return $dmarc_from . ' ' . /* $txt['via'] */ 'via' . ' ' . $modSettings['maillist_sitename'];
			}

			return $dmarc_from;
		}

		return $dmarc_from;
	}

	/**
	 * Set a from name for an email.  Adjusts this to use PBE settings if enabled.
	 *
	 * @param string $from
	 * @return string
	 */
	public function setFromName($from)
	{
		global $modSettings, $mbname;

		$from_name = empty($from) ? (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $mbname) : ($from);
		$from_name = addcslashes($from_name, '<>()\'\\"');

		[$from_name, $from_encoding] = $this->mimeSpecialChars($from_name);
		if ($from_encoding !== 'base64')
		{
			return '"' . $from_name . '"';
		}

		return $from_name;
	}

	/**
	 * Prepare text strings for sending as email body or header.
	 *
	 * What it does:
	 *
	 * - In case there are higher ASCII characters in the given string, this
	 * function will attempt the transport method 'base64'.
	 * - Otherwise the transport method '7bit' is used.
	 *
	 * @param string $string
	 * @return string[] an array containing the converted string and the transport method.
	 * @package Mail
	 */
	public function mimeSpecialChars($string)
	{
		// Ensure any HTML entities are in a valid range
		$string = $this->getValidUTF8String($string);

		// We don't need to mess with the line if no special characters were in it..
		if (preg_match('~([^\x09\x0A\x0D\x20-\x7F])~', $string) === 1)
		{
			// Base64 encode.
			$string = base64_encode($string);
			$string = '=?UTF-8?B?' . $string . '?=';

			return [$string, 'base64'];
		}

		return [$string, '7bit'];
	}

	/**
	 * Replaces any valid &#123; entities with their UTF-8 chr() equivalent
	 *
	 * @param $string
	 * @return string
	 */
	public function getValidUTF8String($string)
	{
		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

		// Replace any HTML entities, in a valid utf-8 range, with their character
		if (preg_match('~&#(\d{3,7});~', $string) !== 0)
		{
			return preg_replace_callback('~&#(\d{3,7});~', 'fixchar__callback', $string);
		}

		return $string;
	}

	/**
	 * Sets both From: and Reply-To: headers
	 *
	 * - If passed $reference then a References: header will also be set
	 *
	 * @param string $from an email address
	 * @param string $from_name a more common name for the address
	 * @param string|null $from_wrapper email address of the $from_name, irrespective of the envelope name
	 * @param string|null $reference
	 * @return void
	 */
	public function setFromHeaders($from, $from_name, $from_wrapper = null, $reference = null)
	{
		global $webmaster_email, $context, $modSettings;

		if ($from_wrapper !== null)
		{
			$this->headers[] = 'From: ' . $from_name . ' <' . $from_wrapper . '>';

			// If they reply where is it going to be sent?
			$this->headers[] = 'Reply-To: "' . (empty($modSettings['maillist_sitename']) ? $context['forum_name'] : $modSettings['maillist_sitename']) . '" <' . (empty($modSettings['maillist_sitename_address']) ? (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']) : ($modSettings['maillist_sitename_address'])) . '>';
			if ($reference !== null)
			{
				$this->headers[] = 'References: <' . $reference .
					strstr(empty($modSettings['maillist_mail_from'])
						? $webmaster_email
						: $modSettings['maillist_mail_from'], '@')
					. '>';
			}
		}
		else
		{
			// Standard ElkArte headers
			$this->headers[] = 'From: ' . $from_name . ' <' . (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']) . '>';
			if ($from !== null && strpos($from, '@') !== false)
			{
				$this->headers[] = 'Reply-To: <' . $from . '>';
			}
		}
	}

	/**
	 * Sets a few specialized digest headers to
	 *
	 * - Prevent auto replying to notifications
	 * - Identify as a list server to help with anti-spam measures
	 *
	 * @param int $priority
	 */
	public function setDigestHeaders($priority)
	{
		global $modSettings, $boardurl, $webmaster_email, $mbname;

		// For maillist, digests or newsletters we include a few more headers for compliance
		if ($this->mailList || $priority > 3)
		{
			// Try to avoid auto replies
			$this->headers[] = 'X-Auto-Response-Suppress: All';
			$this->headers[] = 'Auto-Submitted: auto-replied';

			// Indicate it is a list server to avoid spam tagging and to help client filters
			// http://www.ietf.org/rfc/rfc2369.txt
			// List-Id: Notifications <listname.forumsite.tld>
			$listId = (!empty($modSettings['maillist_sitename_address'])
				? $modSettings['maillist_sitename_address']
				: (empty($modSettings['maillist_mail_from'])
					? $webmaster_email
					: $modSettings['maillist_mail_from']));
			$listId = str_replace('@', '.', $listId);
			$this->headers[] = 'List-Id: Notifications <' . $listId . '>';

			// List-Unsubscribe: <https://www.forumsite.tld/index.php?action=profile;area=notification>
			$this->headers[] = 'List-Unsubscribe: <' . $boardurl . '/index.php?action=profile;area=notification>';

			// List-Owner: <mailto:help@forumsite.tld> (Site Name)
			$this->headers[] = 'List-Owner: <mailto:' . (!empty($modSettings['maillist_sitename_help'])
					? $modSettings['maillist_sitename_help']
					: (empty($modSettings['maillist_mail_from'])
						? $webmaster_email
						: $modSettings['maillist_mail_from'])) . '> (' . (!empty($modSettings['maillist_sitename'])
					? $modSettings['maillist_sitename']
					: $mbname) . ')';
		}
	}

	/**
	 * Creates 3 complete message sections
	 *
	 * - Plain Ascii text.  Characters >127 are converted to entities &#123; All control characters removed. Any
	 * html tags are stripped.
	 * - Base64 encoded.  Control characters (<31) are dropped.  Entities are converted to utf-8 characters.
	 * The result is base64-encoded and chunk split for email compliance.
	 * - Quoted-Printable encoded.  Control characters (<31) are dropped.  Entities are converted
	 * to utf-8 characters.  The result is then encoded with quoted printable which does the needed line
	 * flowing.  This will be marked as text/plain or text/html based on $send_html flag
	 *
	 * @param bool $send_html
	 * @param string $mime_boundary
	 * @param string $orig_message
	 * @param string $subject
	 * @return string
	 */
	public function getMessage($send_html, $mime_boundary, $orig_message, $subject)
	{
		$plain_text = $send_html ? $this->getPlainFromHTML($orig_message) : $orig_message;
		$ascii_message = $this->get7bitVersion($plain_text);

		// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
		$message = $ascii_message . $this->lineBreak . '--' . $mime_boundary . $this->lineBreak;

		// This is base64 message, more accurate than plain as it true UTF-8
		$mine_message = $this->getBase64Version($plain_text);
		$message .= 'Content-Type: text/plain; charset=UTF-8' . $this->lineBreak;
		$message .= 'Content-Transfer-Encoding: base64' . $this->lineBreak . $this->lineBreak;
		$message .= $mine_message . $this->lineBreak . '--' . $mime_boundary . $this->lineBreak;

		// This is the actual HTML message, prim and proper.
		$html_message = $send_html ? $this->getEmailWrapper($orig_message, $subject) : $orig_message;
		$html_message = $this->getQuotedPrintableVersion($html_message);
		$message .= 'Content-Type: text/' . ($send_html ? 'html' : 'plain') . '; charset=UTF-8' . $this->lineBreak;
		$message .= 'Content-Transfer-Encoding: Quoted-Printable' . $this->lineBreak . $this->lineBreak;

		return $message . ($html_message . $this->lineBreak . '--' . $mime_boundary . '--');
	}

	/**
	 * All characters will be represented with 7 bits (ASCII characters 0-127) and thus don’t need to
	 * be encoded. This is fine for the simplest of emails.
	 *
	 * @param string $string
	 * @return string
	 */
	public function get7bitVersion($string)
	{
		// Drop any control characters other than tab, lf and cr
		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

		// Convert all 'special' characters (anything above 127) into HTML entities to maintain 7bit compliance
		return preg_replace_callback('~([\x80-\x{10FFFF}])~u', 'entityConvert', $string);
	}

	/**
	 * When supplied an HTML message, this 'converts' it to plain text for the ascii section of the
	 * mime email
	 *
	 * @param string $string
	 * @return string
	 */
	public function getPlainFromHTML($string)
	{
		// Remove any basic control characters, allowing only for tab, LF and CR
		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

		// Convert to markdown, provides some intent should the receiver only accept plain text
		$mark_down = new Html2Md($string);
		$string = $mark_down->get_markdown();

		// No html in plain text
		return un_htmlspecialchars(strip_tags($string));
	}

	/**
	 * Base64 is a safe encoding that takes an entire string and transforms to a six-bit ASCII alphabet
	 * (consisting of uppercase letters, lowercase letters, numerals, and the “+” and “/” characters).
	 * This allows all contents to be safely sent and consumed by modern email software.
	 *
	 * @param string $string
	 * @return string
	 */
	public function getBase64Version($string)
	{
		// Convert valid &#12345; HTML entities into UTF8 characters
		$string = $this->getValidUTF8String($string);

		// Base64 encode.
		$string = base64_encode($string);

		return chunk_split($string, 76, $this->lineBreak);
	}

	/**
	 * Quoted-Printable is an alternative encoding to Base64 which only encodes high-byte characters,
	 * which can be detected by an equals sign followed by the hexadecimal representation of the
	 * byte (e.g., “=D0”). This allows most of the text to remain human-readable, with clear
	 * exceptions wherever equal signs are encountered.
	 *
	 * @param string $string
	 * @return string
	 */
	public function getQuotedPrintableVersion($string)
	{
		// Get a pure UTF8 character string
		$string = $this->getValidUTF8String($string);

		// Quoted Printable encode.
		return quoted_printable_encode($string);
	}

	/**
	 * Callback for the preg_replace in mimespecialchars
	 *
	 * @param array $match
	 *
	 * @return string
	 * @package Mail
	 *
	 */
	public function mimespecialchars_callback($match)
	{
		return chr($match[1]);
	}

	/**
	 * Makes a basic HTML email from plain text
	 *
	 * @param $string
	 * @return array|string|string[]|null
	 */
	public function getBasicHTMLVersion($string)
	{
		global $scripturl;

		$string = strtr($string, [$this->lineBreak => '<br />' . $this->lineBreak]);

		return preg_replace('~\b(' . preg_quote($scripturl, '~') . '(?:[?/][\w\-%.,?@!:&;=#]+)?)~', '<a href="$1" target="_blank" rel="noopener">$1</a>', $string);
	}

	/**
	 * Builds a body wrapper for the HTML message, because HTML Email design is in the dark ages.
	 *
	 * - Email clients provide inconsistent CSS support, that is why we have a special CSS style
	 * and layout. Not all clients support all CSS properties, for example here is Gmail`s list
	 * https://developers.google.com/gmail/design/reference/supported_css
	 * - Due to the numerous email clients and devices, the email will be rendered by a variety of engines
	 * including WebKit, IE, MS Word, Blink plus clients will add their own styles "to help"
	 * - In general, use tables over divs, CSS2, HTML4, HTML attributes instead of CSS, go old school
	 *
	 * @return string
	 */
	public function getEmailWrapper($message, $subject)
	{
		global $settings;

		$replacements = [
			'TOPICSUBJECT' => $subject,
			'MESSAGE' => $message,
			'EMAILCSS' => file_get_contents($settings['default_theme_dir'] . '/css/email.css'),
		] + $this->replacements;

		$emaildata = loadEmailTemplate('notify_html_email', $replacements);

		return $emaildata['body'];
	}

	/**
	 * Provide a way to supply the template replacement values
	 *
	 * @param array $replacements
	 */
	public function setEmailReplacements($replacements)
	{
		$this->replacements = $replacements;
	}
}
