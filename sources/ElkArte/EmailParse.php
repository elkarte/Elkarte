<?php

/**
 * Class to parse and email in to its header and body parts for use in posting
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class to parse and email in to its header and body parts for use in posting
 *
 * What it does:
 *
 * - Can read from a supplied string, stdin or from the failed email database
 * - Parses and decodes headers, return them in a named array $headers
 * - Parses, decodes and translates message body returns body and plain_body sections
 * - Parses and decodes attachments returns attachments and inline_files
 *
 * Load class
 * Initiate as
 *  - $email_message = new EmailParse();
 *
 * Make the call, loads data and performs all need parsing
 * - $email_message->read_email(true); // Read data and parse it, prefer html section
 *
 * Just load data:
 * - $email_message->read_data(); // load data from stdin
 * - $email_message->read_data($data); // load data from a supplied string
 *
 * Get some email details:
 * - $email_message->headers // All the headers in an array
 * - $email_message->body // The decoded / translated message
 * - $email_message->raw_message // The entire message w/headers as read
 * - $email_message->plain_body // The plain text version of the message
 * - $email_message->attachments // Any attachments with key = filename
 *
 * Optional functions:
 * - $email_message->load_address(); // Returns array with to/from/cc addresses
 * - $email_message->load_key(); // Returns the security key is found, also sets
 * message_key, message_type and message_id
 * - $email_message->load_spam(); // Returns boolean on if spam headers are set
 * - $email_message->load_ip(); // Set ip origin of the email if available
 * - $email_message->load_returnpath(); // Load the message return path
 *
 * @package Maillist
 */
class EmailParse
{
	/**
	 * The full message section (headers, body, etc) we are working on
	 * @var string
	 */
	public $raw_message = null;

	/**
	 * Attachments found after the message
	 * @var string[]
	 */
	public $attachments = array();

	/**
	 * Attachments that we designated as inline with the text
	 * @var string[]
	 */
	public $inline_files = array();

	/**
	 * Parsed and decoded message body, may be plain text or html
	 * @var string
	 */
	public $body = null;

	/**
	 * Parsed and decoded message body, only plain text version
	 * @var string
	 */
	public $plain_body = null;

	/**
	 * All of the parsed message headers
	 * @var mixed[]
	 */
	public $headers = array();

	/**
	 * Full security key
	 * @var string
	 */
	public $message_key_id = null;

	/**
	 * Message hex-code
	 * @var string
	 */
	public $message_key = null;

	/**
	 * Message type of the key p, m or t
	 * @var string
	 */
	public $message_type = null;

	/**
	 * If an html was found in the message
	 * @var boolean
	 */
	public $html_found = false;

	/**
	 * If any positive spam headers were found in the message
	 * @var boolean
	 */
	public $spam_found = false;

	/**
	 * Message id of the key
	 * @var int
	 */
	public $message_id = null;

	/**
	 * Holds the return path as set in the email header
	 * @var string
	 */
	public $return_path = null;

	/**
	 * Holds the message subject
	 * @var string
	 */
	public $subject = null;

	/**
	 * Holds the email to from & cc emails and names
	 * @var mixed[]
	 */
	public $email = array();

	/**
	 * Holds the sending ip of the email
	 * @var string|boolean
	 */
	public $ip = false;

	/**
	 * If the file was converted to utf8
	 * @var boolean
	 */
	public $_converted_utf8 = false;

	/**
	 * Whether the message is a DSN (Delivery Status Notification - aka "bounce"),
	 * indicating failed delivery
	 * @var boolean
	 */
	public $_is_dsn = false;

	/**
	 * Holds the field/value/type report codes from DSN messages
	 * Accessible as [$field]['type'] and [$field]['value']
	 * @var mixed[]
	 */
	public $_dsn = null;

	/**
	 * Holds the current email address, to, from, cc
	 * @var string
	 */
	private $_email_address = null;

	/**
	 * Holds the current email name
	 * @var string
	 */
	private $_email_name = null;

	/**
	 * Holds each boundary section of the message
	 * @var string[]
	 */
	private $_boundary_section = array();

	/**
	 * The total number of boundary sections
	 * @var int
	 */
	private $_boundary_section_count = 0;

	/**
	 * The message header block
	 * @var string
	 */
	private $_header_block = null;

	/**
	 * Loads an email message from stdin, file or from a supplied string
	 *
	 * @param string $data optional, if supplied must be a full headers+body email string
	 * @param string $location optional, used for debug
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function read_data($data = '', $location = '')
	{
		// Supplied a string of data, simply use it
		if ($data !== null)
		{
			$this->raw_message = !empty($data) ? $data : false;
		}
		// Not running from the CLI, must be from the ACP
		elseif (!defined('STDIN'))
		{
			$this->_readFailed($location);
		}
		// Load file data straight from the pipe
		else
		{
			$this->raw_message = file_get_contents('php://stdin');
		}
	}

	/**
	 * Load a message for parsing by reading it from the DB or from a debug file
	 *
	 * - Must have admin permissions
	 *
	 * @param string $location
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _readFailed($location)
	{
		// Called from the ACP, you must have approve permissions
		if (isset($_POST['item']))
		{
			isAllowedTo(array('admin_forum', 'approve_emails'));

			// Read in the file from the failed log table
			$this->raw_message = $this->_query_load_email($_POST['item']);
		}
		// Debugging file, just used for testing
		elseif (file_exists($location . '/elk-test.eml'))
		{
			isAllowedTo('admin_forum');
			$this->raw_message = file_get_contents($location . '/elk-test.eml');
		}
	}

	/**
	 * Loads an email message from the database
	 *
	 * @param int $id id of the email to retrieve from the failed log
	 *
	 * @return string
	 */
	private function _query_load_email($id)
	{
		$db = database();

		// Nothing to load then
		if (empty($id))
		{
			return '';
		}

		$request = $db->query('', '
			SELECT message
			FROM {db_prefix}postby_emails_error
			WHERE id_email = {int:id}
			LIMIT 1',
			array(
				'id' => $id
			)
		);
		list ($message) = $db->fetch_row($request);
		$db->free_result($request);

		return $message;
	}

	/**
	 * Main email routine, calls the needed functions to parse the data so that
	 * its available.
	 *
	 * What it does:
	 *
	 * - read/load data
	 * - split headers from the body
	 * - break header string in to individual header keys
	 * - determine content type and character encoding
	 * - convert message body's
	 *
	 * @param boolean $html - flag to determine if we are saving html or not
	 * @param string $data - full header+message string
	 * @param string $location - optional, used for debug
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function read_email($html = false, $data = '', $location = '')
	{
		// Main, will read, split, parse, decode an email
		$this->read_data($data, $location);

		if ($this->raw_message)
		{
			$this->_split_headers();
			$this->_parse_headers();
			$this->_parse_content_headers();
			$this->_parse_body($html);
			$this->load_subject();
			$this->_is_dsn = $this->_check_dsn();
		}
	}

	/**
	 * Separate the email message headers from the message body
	 *
	 * The header is separated from the body by
	 *  - 1 the first empty line or
	 *  - 2 a line that does not start with a tab, a field name followed by a colon or a space
	 */
	private function _split_headers()
	{
		$this->_header_block = '';
		$match = array();

		// Do we even start with a header in this boundary section?
		if (!preg_match('~^[\w-]+:[ ].*?\r?\n~i', $this->raw_message))
		{
			return;
		}

		// The header block ends based on condition (1) or (2)
		if (!preg_match('~^(.*?)\r?\n(?:\r?\n|(?!(\t|[\w-]+:|[ ])))(.*)~s', $this->raw_message, $match))
		{
			return;
		}

		$this->_header_block = $match[1];
		$this->body = $match[3];
	}

	/**
	 * Takes the header block created with _split_headers and separates it
	 * in to header keys => value pairs
	 */
	private function _parse_headers()
	{
		// Remove windows style \r's
		$this->_header_block = str_replace("\r\n", "\n", $this->_header_block);

		// unfolding multi-line headers, a CRLF immediately followed by a LWSP-char is equivalent to the LWSP-char
		$this->_header_block = preg_replace("~\n(\t| )+~", ' ', $this->_header_block);

		// Build the array of headers
		$headers = explode("\n", trim($this->_header_block));
		foreach ($headers as $header)
		{
			$pos = strpos($header, ':');
			$header_key = substr($header, 0, $pos);
			$pos++;

			// Invalid, empty or generally malformed header
			if (!$header_key || $pos === strlen($header) || ($header[$pos] !== ' ' && $header[$pos] !== "\t"))
			{
				continue;
			}

			// The header key (standardized) and value
			$header_value = substr($header, $pos + 1);
			$header_key = strtolower(trim($header_key));

			// Decode and add it in to our headers array
			if (!isset($this->headers[$header_key]))
			{
				$this->headers[$header_key] = $this->_decode_header($header_value);
			}
			else
			{
				$this->headers[$header_key] .= ' ' . $this->_decode_header($header_value);
			}
		}
	}

	/**
	 * Content headers need to be set so we can properly decode the message body.
	 *
	 * What it does:
	 *
	 * - Content headers often use the optional parameter value syntax which need to be
	 * specially processed.
	 * - Parses or sets defaults for the following:
	 * content-type, content-disposition, content-transfer-encoding
	 */
	private function _parse_content_headers()
	{
		// What kind of message content do we have
		if (isset($this->headers['content-type']))
		{
			$this->_parse_content_header_parameters($this->headers['content-type'], 'content-type');
			if (empty($this->headers['x-parameters']['content-type']['charset']))
			{
				$this->headers['x-parameters']['content-type']['charset'] = 'UTF-8';
			}
		}
		else
		{
			// No content header given so we assume plain text
			$this->headers['content-type'] = 'text/plain';
			$this->headers['x-parameters']['content-type']['charset'] = 'UTF-8';
		}

		// Any special content or assume standard inline
		if (isset($this->headers['content-disposition']))
		{
			$this->_parse_content_header_parameters($this->headers['content-disposition'], 'content-disposition');
		}
		else
		{
			$this->headers['content-disposition'] = 'inline';
		}

		// How this message been encoded, utf8, quoted printable, other??, if none given assume standard 7bit
		if (isset($this->headers['content-transfer-encoding']))
		{
			$this->_parse_content_header_parameters($this->headers['content-transfer-encoding'], 'content-transfer-encoding');
		}
		else
		{
			$this->headers['content-transfer-encoding'] = '7bit';
		}
	}

	/**
	 * Checks if a given header has any optional parameter values
	 *
	 * A header like Content-type: text/plain; charset=iso-8859-1 will become
	 * - headers[Content-type] = text/plain
	 * - headers['x-parameters'][charset] = iso-8859-1
	 *
	 * If parameters are found, sets the primary value to the given key and the additional
	 * values are placed to our catch all x-parameters key. Done this way to prevent
	 * overwriting a primary header key with a secondary one
	 *
	 * @param string $value
	 * @param string $key
	 */
	private function _parse_content_header_parameters($value, $key)
	{
		$matches = array();

		// Does the header key contain parameter values?
		$pos = strpos($value, ';');
		if ($pos !== false)
		{
			// Assign the primary value to the key
			$this->headers[$key] = strtolower(trim(substr($value, 0, $pos)));

			// Place any parameter values in the x-parameters key
			$parameters = ltrim(substr($value, $pos + 1));
			if (!empty($parameters) && preg_match_all('~([A-Za-z-]+)="?(.*?)"?\s*(?:;|$)~', $parameters, $matches))
			{
				$count = count($matches[0]);
				for ($i = 0; $i < $count; $i++)
				{
					$this->headers['x-parameters'][$key][strtolower($matches[1][$i])] = $matches[2][$i];
				}
			}
		}
		// No parameters associated with this header
		else
		{
			$this->headers[$key] = strtolower(trim($value));
		}
	}

	/**
	 * Based on the the message content type, determine how to best proceed
	 *
	 * @param boolean $html
	 */
	private function _parse_body($html = false)
	{
		// based on the content type for this body, determine what do do
		switch ($this->headers['content-type'])
		{
			// The text/plain content type is the generic subtype for plain text. It is the default specified by RFC 822.
			case 'text/plain':
				$this->body = $this->_decode_string($this->body, $this->headers['content-transfer-encoding'], $this->headers['x-parameters']['content-type']['charset']);
				$this->plain_body = $this->body;
				break;
			// The text/html content type is an Internet Media Type as well as a MIME content type.
			case 'text/html':
				$this->html_found = true;
				$this->body = $this->_decode_string($this->body, $this->headers['content-transfer-encoding'], $this->headers['x-parameters']['content-type']['charset']);
				break;
			// We don't process the following, noted here so people know why
			//
			// multipart/digest - used to send collections of plain-text messages
			// multipart/byteranges - defined as a part of the HTTP message protocol. It includes two or more parts,
			// each with its own Content-Type and Content-Range fields
			// multipart/form-data - intended to allow information providers to express file upload requests uniformly
			// text/enriched - Uses a very limited set of formatting commands all with <command name></command name>
			// text/richtext - Obsolete version of the above
			//
			case 'multipart/digest':
			case 'multipart/byteranges':
			case 'multipart/form-data':
			case 'text/enriched':
			case 'text/richtext':
				break;
			// The following are considered multi part messages, as such they *should* contain several sections each
			// representing the same message in various ways such as plain text (mandatory), html section, and
			// encoded section such as quoted printable as well as attachments both as files and inline
			//
			// multipart/alternative - the same information is presented in different body parts in different forms.
			// The body parts are ordered by increasing complexity and accuracy
			// multipart/mixed -  used when the body parts are independent and need to be bundled in a particular order
			// multipart/parallel - display all of the parts simultaneously on hardware and software that can do so (image with audio)
			// multipart/related - used for compound documents, those messages in which the separate body parts are intended to work
			// together to provide the full meaning of the message
			// multipart/report - defined for returning delivery status reports, with optional included messages
			// multipart/signed -provides a security framework for MIME parts
			// multipart/encrypted - as above provides a security framework for MIME parts
			// message/rfc822 - used to enclose a complete message within a message
			//
			case 'multipart/alternative':
			case 'multipart/mixed':
			case 'multipart/parallel':
			case 'multipart/related':
			case 'multipart/report':
			case 'multipart/signed':
			case 'multipart/encrypted':
			case 'message/rfc822':
				if (!isset($this->headers['x-parameters']['content-type']['boundary']))
				{
					// No boundary's but presented as multipart?, then we must have a incomplete message
					$this->body = '';
					return;
				}

				// Break up the message on the boundary --sections, each boundary section will have its
				// own Content Type and Encoding and we will process each as such
				$this->_boundary_split($this->headers['x-parameters']['content-type']['boundary'], $html);

				// Some multi-part messages ... are singletons :P
				if ($this->_boundary_section_count === 1)
				{
					$this->body = $this->_boundary_section[0]->body;
					$this->headers['x-parameters'] = $this->_boundary_section[0]->headers['x-parameters'];
				}
				// We found multiple sections, lets go through each
				elseif ($this->_boundary_section_count > 1)
				{
					$html_ids = array();
					$text_ids = array();
					$this->body = '';
					$this->plain_body = empty($this->plain_body) ? '' : $this->plain_body;
					$bypass = array('application/pgp-encrypted', 'application/pgp-signature', 'application/pgp-keys');

					// Go through each boundary section
					for ($i = 0; $i < $this->_boundary_section_count; $i++)
					{
						// Stuff we can't or don't want to process
						if (in_array($this->_boundary_section[$i]->headers['content-type'], $bypass))
						{
							continue;
						}
						// HTML sections
						elseif ($this->_boundary_section[$i]->headers['content-type'] === 'text/html')
						{
							$html_ids[] = $i;
						}
						// Plain section
						elseif ($this->_boundary_section[$i]->headers['content-type'] === 'text/plain')
						{
							$text_ids[] = $i;
						}
						// Message is a DSN (Delivery Status Notification)
						elseif ($this->_boundary_section[$i]->headers['content-type'] === 'message/delivery-status')
						{
							$this->_process_DSN($i);
						}

						// Attachments, we love em
						$this->_process_attachments($i);
					}

					// We always return a plain text version for use
					if (!empty($text_ids))
					{
						foreach ($text_ids as $id)
						{
							$this->plain_body .= $this->_boundary_section[$id]->body;
						}
					}
					elseif (!empty($html_ids))
					{
						// This should never run as emails should always have a plain text section to be valid, still ...
						foreach ($html_ids as $id)
						{
							$this->plain_body .= $this->_boundary_section[$id]->body;
						}

						$this->plain_body = str_ireplace('<p>', "\n\n", $this->plain_body);
						$this->plain_body = str_ireplace(array('<br />', '<br>', '</p>', '</div>'), "\n", $this->plain_body);
						$this->plain_body = strip_tags($this->plain_body);
					}
					$this->plain_body = $this->_decode_body($this->plain_body);

					// If they want the html section, and its available,  we need to set it
					if ($html && !empty($html_ids))
					{
						$this->html_found = true;
						$text_ids = $html_ids;
					}

					if (!empty($text_ids))
					{
						// For all the chosen sections
						foreach ($text_ids as $id)
						{
							$this->body .= $this->_boundary_section[$id]->body;

							// A section may have its own attachments if it had is own unique boundary sections
							// so we need to check and add them in as needed
							foreach ($this->_boundary_section[$id]->attachments as $key => $value)
							{
								$this->attachments[$key] = $value;
							}

							foreach ($this->_boundary_section[$id]->inline_files as $key => $value)
							{
								$this->inline_files[$key] = $value;
							}
						}
						$this->body = $this->_decode_body($this->body);

						// Return the right set of x-parameters and content type for the body we are returning
						if (isset($this->_boundary_section[$text_ids[0]]->headers['x-parameters']))
						{
							$this->headers['x-parameters'] = $this->_boundary_section[$text_ids[0]]->headers['x-parameters'];
						}

						$this->headers['content-type'] = $this->_boundary_section[$text_ids[0]]->headers['content-type'];
					}
				}
				break;
			default:
				// deal with all the rest (e.g. image/xyx) the standard way
				$this->body = $this->_decode_string($this->body, $this->headers['content-transfer-encoding'], $this->headers['x-parameters']['content-type']['charset']);
				break;
		}
	}

	/**
	 * If the boundary is a failed email response, set the DSN flag for the admin
	 *
	 * @param int $i The section being worked
	 */
	private function _process_DSN($i)
	{
		// These sections often have extra blank lines, so cannot be counted on to be
		// fully accessible in ->headers. The "body" of this section contains values
		// formatted by FIELD: [TYPE;] VALUE
		$dsn_body = array();
		foreach (explode("\n", str_replace("\r\n", "\n", $this->_boundary_section[$i]->body)) as $line)
		{
			$type = '';
			list($field, $rest) = explode(':', $line);

			if (strpos($line, ';'))
			{
				list ($type, $val) = explode(';', $rest);
			}
			else
			{
				$val = $rest;
			}

			$dsn_body[trim(strtolower($field))] = array('type' => trim($type), 'value' => trim($val));
		}

		switch ($dsn_body['action']['value'])
		{
			case 'delayed':
				// Remove this if we don't want to flag delayed delivery addresses as "dirty"
				// May be caused by temporary net failures, e.g. DNS outage
				// Lack of break is intentional
			case 'failed':
				// The email failed to be delivered.
				$this->_is_dsn = true;
				$this->_dsn = array('headers' => $this->_boundary_section[$i]->headers, 'body' => $dsn_body);
				break;
			default:
				$this->_is_dsn = false;
		}
	}

	/**
	 * If the boundary section is "attachment" or "inline", process and save the data
	 *
	 * - Data is saved in ->attachments or ->inline_files
	 *
	 * @param int $i The section being worked
	 */
	private function _process_attachments($i)
	{
		if ($this->_boundary_section[$i]->headers['content-disposition'] === 'attachment' || $this->_boundary_section[$i]->headers['content-disposition'] === 'inline' || isset($this->_boundary_section[$i]->headers['content-id']))
		{
			// Get the attachments file name
			if (isset($this->_boundary_section[$i]->headers['x-parameters']['content-disposition']['filename']))
			{
				$file_name = $this->_boundary_section[$i]->headers['x-parameters']['content-disposition']['filename'];
			}
			elseif (isset($this->_boundary_section[$i]->headers['x-parameters']['content-type']['name']))
			{
				$file_name = $this->_boundary_section[$i]->headers['x-parameters']['content-type']['name'];
			}
			else
			{
				return;
			}

			// Load the attachment data
			$this->attachments[$file_name] = $this->_boundary_section[$i]->body;

			// Inline attachments are a bit more complicated.
			if (isset($this->_boundary_section[$i]->headers['content-id']) && $this->_boundary_section[$i]->headers['content-disposition'] === 'inline')
			{
				$this->inline_files[$file_name] = trim($this->_boundary_section[$i]->headers['content-id'], ' <>');
			}
		}
	}

	/**
	 * Split up multipart messages and process each section separately
	 * as its own email object
	 *
	 * @param string $boundary
	 * @param boolean $html - flag to indicate html content
	 */
	private function _boundary_split($boundary, $html)
	{
		// Split this message up on its boundary sections
		$parts = explode('--' . $boundary, $this->body);
		foreach ($parts as $part)
		{
			$part = trim($part);

			// Nothing?
			if (empty($part))
			{
				continue;
			}

			// Parse this section just like its was a separate email
			$this->_boundary_section[$this->_boundary_section_count] = new EmailParse();
			$this->_boundary_section[$this->_boundary_section_count]->read_email($html, $part);

			$this->plain_body .= $this->_boundary_section[$this->_boundary_section_count]->plain_body;

			$this->_boundary_section_count++;
		}
	}

	/**
	 * Converts a header string to ascii/UTF8
	 *
	 * What it does:
	 *
	 * - Headers, mostly subject and names may be encoded as quoted printable or base64
	 * to allow for non ascii characters in those fields.
	 * - This encoding is separate from the message body encoding and must be
	 * determined since this encoding is not directly specified by the headers themselves
	 *
	 * @param string $val
	 * @param bool $strict
	 * @return string
	 */
	private function _decode_header($val, $strict = false)
	{
		// Check if this header even needs to be decoded.
		if (strpos($val, '=?') === false || strpos($val, '?=') === false)
		{
			return trim($val);
		}

		// If iconv mime is available just use it and be done
		if (function_exists('iconv_mime_decode'))
		{
			return iconv_mime_decode($val, $strict ? 1 : 2, 'UTF-8');
		}

		// The RFC 2047-3 defines an encoded-word as a sequence of characters that
		// begins with "=?", ends with "?=", and has two "?"s in between. After the first question mark
		// is the name of the character encoding being used; after the second question mark
		// is the manner in which it's being encoded into plain ASCII (Q=quoted printable, B=base64);
		// and after the third question mark is the text itself.
		// Subject: =?iso-8859-1?Q?=A1Hola,_se=F1or!?=
		$matches = array();
		if (preg_match_all('~(.*?)(=\?([^?]+)\?(Q|B)\?([^?]*)\?=)([^=\(]*)~i', $val, $matches))
		{
			$decoded = '';
			for ($i = 0, $num = count($matches[4]); $i < $num; $i++)
			{
				// [1]leading text, [2]=? to ?=, [3]character set, [4]Q or B, [5]the encoded text [6]trailing text
				$leading_text = $matches[1][$i];
				$encoded_charset = $matches[3][$i];
				$encoded_type = strtolower($matches[4][$i]);
				$encoded_text = $matches[5][$i];
				$trailing_text = $matches[6][$i];

				if ($strict)
				{
					// Technically the encoded word can only be by itself or in a cname
					$check = trim($leading_text);
					if ($i === 0 && !empty($check) && $check[0] !== '(')
					{
						$decoded .= $matches[0][$i];
						continue;
					}
				}

				// Decode and convert our string
				if ($encoded_type === 'q')
				{
					$decoded_text = $this->_decode_string(str_replace('_', ' ', $encoded_text), 'quoted-printable', $encoded_charset);
				}
				elseif ($encoded_type === 'b')
				{
					$decoded_text = $this->_decode_string($encoded_text, 'base64', $encoded_charset);
				}

				// Add back in anything after the closing ?=
				if (!empty($encoded_text))
				{
					$decoded_text .= $trailing_text;
				}

				// Add back in the leading text to the now decoded value
				if (!empty($leading_text))
				{
					$decoded_text = $leading_text . $decoded_text;
				}

				$decoded .= $decoded_text;
			}
			$val = $decoded;
		}

		return trim($val);
	}

	/**
	 * Checks the body text to see if it may need to be further decoded
	 *
	 * What it does:
	 *
	 * - Sadly whats in the body text is not always what the header claims, or the
	 * header is just wrong. Copy/paste in to email from other apps etc.
	 * This does an extra check for quoted printable DNA and if found decodes the
	 * message as such.
	 *
	 * @param string $val
	 * @return string
	 */
	private function _decode_body($val)
	{
		// The encoding tag can be missing in the headers or just wrong
		if (preg_match('~(?:=C2|=A0|=D2|=D4|=96){1}~s', $val))
		{
			// Remove /r/n to be just /n
			$val = preg_replace('~(=0D=0A)~', "\n", $val);

			// utf8 non breaking space which does not decode right
			$val = preg_replace('~(=C2=A0)~', ' ', $val);

			// Smart quotes they will decode to black diamonds or other, but if
			// UTF-8 these may be valid non smart quotes
			if ($this->headers['x-parameters']['content-type']['charset'] !== 'UTF-8')
			{
				$val = str_replace('=D4', "'", $val);
				$val = str_replace('=D5', "'", $val);
				$val = str_replace('=D2', '"', $val);
				$val = str_replace('=D3', '"', $val);
				$val = str_replace('=A0', '', $val);
			}
			$val = $this->_decode_string($val, 'quoted-printable');
		}
		// Lines end in the tell tail quoted printable ... wrap and decode
		elseif (preg_match('~\s=[\r?\n]{1}~s', $val))
		{
			$val = preg_replace('~\s=[\r?\n]{1}~', ' ', $val);
			$val = $this->_decode_string($val, 'quoted-printable');
		}
		// Lines end in = but not ==
		elseif (preg_match('~((?<!=)=[\r?\n])~s', $val))
		{
			$val = $this->_decode_string($val, 'quoted-printable');
		}

		return $val;
	}

	/**
	 * Checks the message components to determine if the message is a DSN
	 *
	 * What it does:
	 *
	 * - Checks the content of the message, looking for headers and values that
	 * correlate with the message being a DSN. _parse_body checks for the existence
	 * of a "message/delivery-status" header
	 * - As many, many daemons and providers do not adhere to the RFC 3464
	 * standard, this function will hold the "special cases"
	 *
	 * @return boolean|null
	 */
	private function _check_dsn()
	{
		// If we already know it's a DSN, bug out
		if ($this->_is_dsn)
		{
			return true;
		}

		/** Add non-header-based detection **/
	}

	/**
	 * Tries to find the original intended recipient that failed to deliver
	 *
	 * What it does:
	 *
	 * - Checks the headers of a DSN for the various ways that the intended recipient
	 *   Might have been included in the DSN headers
	 *
	 * @return string or null
	 */
	public function get_failed_dest()
	{
		/** Body->Original-Recipient Header **/
		if (isset($this->_dsn['body']['original-recipient']['value']))
		{
			return $this->_dsn['body']['original-recipient']['value'];
		}

		/** Body->Final-recipient Header **/
		if (isset($this->_dsn['body']['final-recipient']['value']))
		{
			return $this->_dsn['body']['final-recipient']['value'];
		}

		return null;
	}

	/**
	 * Find the message return_path and well return it
	 *
	 * @return string or null
	 */
	public function load_returnpath()
	{
		$matches = array();

		// Fetch the return path
		if (isset($this->headers['return-path']))
		{
			if (preg_match('~(.*?)<(.*?)>~', $this->headers['return-path'], $matches))
			{
				$this->return_path = trim($matches[2]);
			}
		}

		return $this->return_path;
	}

	/**
	 * Returns the decoded subject of the email
	 *
	 * - Makes sure the subject header is set, if not sets it to ''
	 *
	 * @return string or null
	 */
	public function load_subject()
	{
		// Account for those no-subject emails
		if (!isset($this->headers['subject']))
		{
			$this->headers['subject'] = '';
		}

		// Change it to a readable form ...
		$this->subject = htmlspecialchars($this->_decode_header($this->headers['subject']), ENT_COMPAT, 'UTF-8');

		return (string) $this->subject;
	}

	/**
	 * Check for the message security key in common headers, in-reply-to and references
	 *
	 * - If the key is not found in the header, will search the message body
	 * - If the key is still not found will search the entire input stream
	 * - returns the found key or false.  If found will also save it in the in-reply-to header
	 *
	 * @param string $key optional
	 * @return string of key or false on failure
	 */
	public function load_key($key = '')
	{
		$regex_key = '~(([a-z0-9]{32})\-(p|t|m)(\d+))~i';
		$match = array();

		// Supplied a key, lets check it
		if (!empty($key))
		{
			preg_match($regex_key, $key, $match);
		}
		// Otherwise we play find the key
		else
		{
			if (!$this->_load_key_from_headers($regex_key))
			{
				$this->_load_key_from_body();
			}
		}

		return !empty($this->message_key_id) ? $this->message_key_id : false;
	}

	/**
	 * Searches the most common locations for the security key
	 *
	 * - Normal return location would be in the in-reply-to header
	 * - Common for it to be shifted to a reference header
	 *
	 * @param string $regex_key
	 *
	 * @return bool is the security key is found or not
	 */
	private function _load_key_from_headers($regex_key)
	{
		$found_key = false;

		// Check our reply_to_msg_id based on in-reply-to and references, the key *should* be there.
		if (empty($this->headers['in-reply-to']) || preg_match($regex_key, $this->headers['in-reply-to'], $match) === 0)
		{
			// Check if references are set, sometimes email clients thread from there
			if (!empty($this->headers['references']))
			{
				// Maybe our security key is in the references
				$refs = explode(' ', $this->headers['references']);
				foreach ($refs as $ref)
				{
					if (preg_match($regex_key, $ref, $match))
					{
						// Found the key in the ref, set the in-reply-to
						$this->headers['in-reply-to'] = $match[1];
						$this->_load_key_details($match);
						$found_key = true;
						break;
					}
				}
			}
		}
		else
		{
			$this->_load_key_details($match);
			$found_key = true;
		}

		return $found_key;
	}

	/**
	 * Searches the message body or the raw email in search of the key
	 *
	 * - Not found in the headers, so lets search the body for the [key]
	 * as we insert that on outbound email just for this
	 */
	private function _load_key_from_body()
	{
		$regex_key = '~\[(([a-z0-9]{32})\-(p|t|m)(\d+))\]~i';
		$found_key = false;

		// Check the message body
		if (preg_match($regex_key, $this->body, $match) === 1)
		{
			$this->headers['in-reply-to'] = $match[1];
			$this->_load_key_details($match);
			$found_key = true;
		}
		// Grrr ... check everything!
		elseif (preg_match($regex_key, $this->raw_message, $match) === 1)
		{
			$this->headers['in-reply-to'] = $match[1];
			$this->_load_key_details($match);
			$found_key = true;
		}

		return $found_key;
	}

	/**
	 * Loads found key details for use in other functions
	 *
	 * @param string[] $match from regex 1=>full, 2=>key, 3=>p|t|m, 4=>12345
	 */
	private function _load_key_details($match)
	{
		if (!empty($match[1]))
		{
			// 1=>7738c27ae6c431495ad26587f30e2121-m29557, 2=>7738c27ae6c431495ad26587f30e2121, 3=>m, 4=>29557
			$this->message_key_id = $match[1];
			$this->message_key = $match[2];
			$this->message_type = $match[3];
			$this->message_id = (int) $match[4];
		}
	}

	/**
	 * Loads in the most email from, to and cc address
	 *
	 * - will attempt to return the name and address for fields "name:" <email>
	 * - will become email['to'] = email and email['to_name'] = name
	 *
	 * @return array of addresses
	 */
	public function load_address()
	{
		$this->email['to'] = array();
		$this->email['from'] = array();
		$this->email['cc'] = array();

		// Fetch the "From" email and if possibly the senders common name
		if (isset($this->headers['from']))
		{
			$this->_parse_address($this->headers['from']);
			$this->email['from'] = $this->_email_address;
			$this->email['from_name'] = $this->_email_name;
		}

		// Fetch the "To" email and if possible the recipients common name
		if (isset($this->headers['to']))
		{
			$to_addresses = explode(',', $this->headers['to']);
			for ($i = 0, $num = count($to_addresses); $i < $num; $i++)
			{
				$this->_parse_address($to_addresses[$i]);
				$this->email['to'][$i] = $this->_email_address;
				$this->email['to_name'][$i] = $this->_email_name;
			}
		}

		// Fetch the "cc" address if there is one and once again the real name as well
		if (isset($this->headers['cc']))
		{
			$cc_addresses = explode(',', $this->headers['cc']);
			for ($i = 0, $num = count($cc_addresses); $i < $num; $i++)
			{
				$this->_parse_address($cc_addresses[$i]);
				$this->email['cc'][$i] = $this->_email_address;
				$this->email['cc_name'][$i] = $this->_email_name;
			}
		}

		return $this->email;
	}

	/**
	 * Finds the message sending ip and returns it
	 *
	 * - will look in various header fields where the ip may reside
	 * - returns false if it can't find a valid IP4
	 *
	 * @return string|boolean on fail
	 */
	public function load_ip()
	{
		$this->ip = false;

		// The sending IP can be useful in spam prevention and making a post
		if (isset($this->headers['x-posted-by']))
		{
			$this->ip = $this->_parse_ip($this->headers['x-posted-by']);
		}
		elseif (isset($this->headers['x-originating-ip']))
		{
			$this->ip = $this->_parse_ip($this->headers['x-originating-ip']);
		}
		elseif (isset($this->headers['x-senderip']))
		{
			$this->ip = $this->_parse_ip($this->headers['x-senderip']);
		}
		elseif (isset($this->headers['x-mdremoteip']))
		{
			$this->ip = $this->_parse_ip($this->headers['x-mdremoteip']);
		}
		elseif (isset($this->headers['received']))
		{
			$this->ip = $this->_parse_ip($this->headers['received']);
		}

		return $this->ip;
	}

	/**
	 * Finds if any spam headers have been positively set and returns that flag
	 *
	 * - will look in various header fields where the spam status may reside
	 *
	 * @return boolean on fail
	 */
	public function load_spam()
	{
		// SpamAssassin (and others like rspamd)
		if (isset($this->headers['x-spam-flag']) && strtolower(substr($this->headers['x-spam-flag'], 0, 3)) === 'yes')
		{
			$this->spam_found = true;
		}
		// SpamStopper and other variants
		elseif (isset($this->headers['x-spam-status']) && strtolower(substr($this->headers['x-spam-status'], 0, 3)) === 'yes')
		{
			$this->spam_found = true;
		}
		// j-chkmail --  hi = likely spam lo = suspect ...
		elseif (isset($this->headers['x-j-chkmail-status']) && strtolower(substr($this->headers['x-j-chkmail-status'], 0, 2)) === 'hi')
		{
			$this->spam_found = true;
		}
		// Nucleus Mailscanner
		elseif (isset($this->headers['x-nucleus-mailscanner']) && strtolower($this->headers['x-nucleus-mailscanner']) !== 'found to be clean')
		{
			$this->spam_found = true;
		}

		return $this->spam_found;
	}

	/**
	 * Validates that the ip is a valid ip4 address
	 *
	 * @param string|null $string
	 * @return string
	 */
	private function _parse_ip($string)
	{
		if (preg_match('~\[?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\]?~', $string, $matches) !== 1)
		{
			return '';
		}

		$string = trim($matches[0], '[] ');

		// Validate it matches an ip4 standard
		if (filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
		{
			return $string;
		}
		else
		{
			return '';
		}
	}

	/**
	 * Take an email address and parse out the email address and email name
	 *
	 * @param string $val
	 */
	private function _parse_address($val)
	{
		$this->_email_address = '';
		$this->_email_name = '';

		if (preg_match('~(.*?)<(.*?)>~', $val, $matches))
		{
			// The email address, remove spaces and (comments)
			$this->_email_address = trim(str_replace(' ', '', $matches[2]));
			$this->_email_address = preg_replace('~\(.*?\)~', '', $this->_email_address);

			// Perhaps a common name as well "name:" <email>
			if (!empty($matches[1]))
			{
				$matches[1] = $this->_decode_header($matches[1]);
				if ($matches[1][0] === '"' && substr($matches[1], -1) === '"')
				{
					$this->_email_name = substr($matches[1], 1, -1);
				}
				else
				{
					$this->_email_name = $matches[1];
				}
			}
			else
			{
				$this->_email_name = $this->_email_address;
			}

			// Check the validity of the common name, if not sure set it to email user.
			if (!preg_match('~^\w+~', $this->_email_name))
			{
				$this->_email_name = substr($this->_email_address, 0, strpos($this->_email_address, '@'));
			}
		}
		else
		{
			// Just an sad lonely email address, so we use it as is
			$this->_email_address = trim(str_replace(' ', '', $val));
			$this->_email_address = preg_replace('~\(.*?\)~', '', $this->_email_address);
			$this->_email_name = substr($this->_email_address, 0, strpos($this->_email_address, '@'));
		}
	}

	/**
	 * Decodes base64 or quoted-printable strings
	 * Converts from one character set to utf-8
	 *
	 * @param string $string
	 * @param string $encoding
	 * @param string $charset
	 *
	 * @return bool|null|string|string[]
	 */
	private function _decode_string($string, $encoding, $charset = '')
	{
		// Decode if its quoted printable or base64 encoded
		if ($encoding === 'quoted-printable')
		{
			$string = quoted_printable_decode(preg_replace('~=\r?\n~', '', $string));
		}
		elseif ($encoding === 'base64')
		{
			$string = base64_decode($string);
		}

		// Convert this to utf-8 if needed.
		if (!empty($charset) && $charset !== 'UTF-8')
		{
			$string = $this->_charset_convert($string, strtoupper($charset), 'UTF-8');
		}

		return $string;
	}

	/**
	 * Pick the best possible function to convert a strings character set, if any exist
	 *
	 * @param string $string
	 * @param string $from
	 * @param string $to
	 *
	 * @return null|string|string[]
	 */
	private function _charset_convert($string, $from, $to)
	{
		// Lets assume we have one of the functions available to us
		$this->_converted_utf8 = true;
		$string_save = $string;

		// Use iconv if its available
		if (function_exists('iconv'))
		{
			$string = @iconv($from, $to . '//TRANSLIT//IGNORE', $string);
		}

		// No iconv or a false response from it
		if (!function_exists('iconv') || ($string === false))
		{
			if (function_exists('mb_convert_encoding'))
			{
				// Replace unknown characters with a space
				@ini_set('mbstring.substitute_character', '32');
				$string = @mb_convert_encoding($string, $to, $from);
			}
			elseif (function_exists('recode_string'))
			{
				$string = @recode_string($from . '..' . $to, $string);
			}
			else
			{
				$this->_converted_utf8 = false;
			}
		}

		unset($string_save);

		return $string;
	}
}
