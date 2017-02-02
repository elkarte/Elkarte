<?php

/**
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */

namespace BBC;

/**
 * Class ParserWrapper
 *
 * Wrap around the BBC parsers before we implement a DIC.
 * Deprecate in future versions in favor of a DIC
 */
final class ParserWrapper
{
	/** @var array Disabled tags */
	protected $disabled = array();
	/** @var \BBC\Codes */
	protected $codes;
	/** @var  \BBC\BBCParser */
	protected $bbc_parser;
	/** @var  \BBC\SmileyParser */
	protected $smiley_parser;
	/** @var  \BBC\HtmlParser */
	protected $html_parser;
	/** @var  \BBC\Autolink */
	protected $autolink_parser;
	/** @var bool If smileys are enabled */
	protected $smileys_enabled = true;
	/** @var ParserWrapper */
	public static $instance;

	/**
	 * Find and return ParserWrapper instance if it exists,
	 * or create a new instance
	 *
	 * @return ParserWrapper
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = new ParserWrapper;
		}

		return self::$instance;
	}

	/**
	 * ParserWrapper constructor.
	 */
	private function __construct()
	{

	}

	/**
	 * Check if the server load is too high to execute BBC parsing
	 *
	 * @return bool If the parser can execute
	 */
	protected function checkLoad()
	{
		global $modSettings, $context;

		if (!empty($modSettings['bbc']) && $modSettings['current_load'] >= $modSettings['bbc'])
		{
			$context['disabled_parse_bbc'] = true;
			return false;
		}

		return true;
	}

	/**
	 * Is BBC parsing enabled
	 *
	 * @return bool
	 */
	protected function isEnabled()
	{
		global $modSettings;

		return !empty($modSettings['enableBBC']);
	}

	/**
	 * Enable or disable smileys
	 *
	 * @param bool|int $toggle
	 *
	 * @return $this
	 */
	public function enableSmileys($toggle)
	{
		$this->smileys_enabled = (bool) $toggle;
		return $this;
	}

	/**
	 * Get parsers based on where it will be used
	 *
	 * @param string $area Where it is being called from
	 * @return array
	 */
	protected function getParsersByArea($area)
	{
		$parsers = array(
			'autolink' => false,
			'html' => false,
			'bbc' => false,
			'smiley' => false,
		);

		// First see if any hooks set a parser.
		foreach ($parsers as $parser_type => &$parser)
		{
			call_integration_hook('integrate_' . $area . '_' . $parser_type . '_parser', array(&$parser, $this));

			// If not, use the default one
			if ($parser === false)
			{
				$parser = call_user_func(array($this, 'get' . ucfirst($parser_type) . 'Parser'), $area);
			}
		}

		return $parsers;
	}

	/**
	 * Return the current message parsers
	 *
	 * @return array
	 */
	public function getMessageParser()
	{
		return $this->getParsersByArea('message');
	}

	/**
	 * Return the current signature parsers
	 *
	 * @return array
	 */
	public function getSignatureParser()
	{
		return $this->getParsersByArea('signature');
	}

	/**
	 * Return the news parsers
	 *
	 * @return array
	 */
	public function getNewsParser()
	{
		return $this->getParsersByArea('news');
	}

	/**
	 * Parse a string based on where it's being called from
	 *
	 * @param string $area Where this is being called from
	 * @param string $message The message to be parsed
	 *
	 * @return string The Parsed message
	 */
	protected function parse($area, $message)
	{
		// If the load average is too high, don't parse the BBC.
		if (!$this->checkLoad())
		{
			return $message;
		}

		$parsers = $this->getParsersByArea($area);
		$smileys_enabled = $this->smileys_enabled && $GLOBALS['user_info']['smiley_set'] !== 'none';

		if (!$this->isEnabled())
		{
			// You need to run the smiley parser to get rid of the markers
			return $parsers['smiley']
				->setEnabled($smileys_enabled)
				->parse($message);
		}

		$message = $parsers['bbc']->parse($message);

		return $parsers['smiley']
			->setEnabled($smileys_enabled)
			->parse($message);
	}

	/**
	 * Parse the BBC and smileys in messages
	 *
	 * @param string $message
	 * @param bool|int $smileys_enabled
	 *
	 * @return string
	 */
	public function parseMessage($message, $smileys_enabled)
	{
		return $this->enableSmileys($smileys_enabled)->parse('message', $message);
	}

	/**
	 * Parse the BBC and smileys in signatures
	 *
	 * @param string $signature
	 * @param bool $smileys_enabled
	 *
	 * @return string
	 */
	public function parseSignature($signature, $smileys_enabled)
	{
		return $this->enableSmileys($smileys_enabled)->parse('signature', $signature);
	}

	/**
	 * Parse the BBC and smileys in news items
	 *
	 * @param string $news
	 *
	 * @return string
	 */
	public function parseNews($news)
	{
		return $this->enableSmileys(true)->parse('news', $news);
	}

	/**
	 * Parse the BBC and smileys in emails
	 *
	 * @param string $email
	 *
	 * @return string
	 */
	public function parseEmail($email)
	{
		return $this->enableSmileys(false)->parse('email', $email);
	}

	/**
	 * Parse the BBC and smileys in custom profile fields
	 *
	 * @param string $field
	 *
	 * @return string
	 */
	public function parseCustomFields($field)
	{
		// @todo this should account for which field is being parsed and hook on that

		return $this->enableSmileys(true)->parse('customfields', $field);
	}

	/**
	 * Parse the BBC and smileys in poll questions/answers
	 *
	 * @param string $poll
	 *
	 * @return string
	 */
	public function parsePoll($poll)
	{
		return $this->enableSmileys(true)->parse('poll', $poll);
	}

	/**
	 * Parse the BBC and smileys in the registration agreement
	 *
	 * @param string $agreement
	 *
	 * @return string
	 */
	public function parseAgreement($agreement)
	{
		return $this->enableSmileys(true)->parse('agreement', $agreement);
	}

	/**
	 * Parse the BBC and smileys in personal messages
	 *
	 * @param string $pm
	 *
	 * @return string
	 */
	public function parsePM($pm)
	{
		return $this->enableSmileys(true)->parse('pm', $pm);
	}

	/**
	 * Parse the BBC and smileys in user submitted reports
	 *
	 * @param string $report
	 *
	 * @return string
	 */
	public function parseReport($report)
	{
		return $this->enableSmileys(true)->parse('report', $report);
	}

	/**
	 * Parse the BBC and smileys in package descriptions
	 *
	 * @param string $package
	 *
	 * @return string
	 */
	public function parsePackage($package)
	{
		return $this->enableSmileys(true)->parse('package', $package);
	}

	/**
	 * Parse the BBC and smileys in user verification controls
	 *
	 * @param string $question
	 *
	 * @return string
	 */
	public function parseVerificationControls($question)
	{
		return $this->enableSmileys(true)->parse('package', $question);
	}

	/**
	 * Parse the BBC and smileys in moderator notices to users
	 *
	 * @param string $notice
	 *
	 * @return string
	 */
	public function parseNotice($notice)
	{
		return $this->enableSmileys(true)->parse('notice', $notice);
	}

	/**
	 * Parse the BBC and smileys in board descriptions
	 *
	 * @param string $board
	 *
	 * @return string
	 */
	public function parseBoard($board)
	{
		return $this->enableSmileys(true)->parse('board', $board);
	}

	/**
	 * Set the disabled tags
	 *
	 * @param string[] $disabled (usually from $modSettings['disabledBBC'])
	 *
	 * @return $this
	 */
	public function setDisabled(array $disabled)
	{
		foreach ($disabled as $tag)
		{
			$this->disabled[trim($tag)] = true;
		}

		return $this;
	}

	/**
	 * Return the bbc code definitions for the parser
	 *
	 * @return Codes
	 */
	public function getCodes()
	{
		if ($this->codes === null)
		{
			$additional_bbc = array();
			call_integration_hook('integrate_additional_bbc', array(&$additional_bbc));
			$this->codes = new Codes($additional_bbc, $this->disabled);
		}

		return $this->codes;
	}

	/**
	 * Return an instance of the bbc parser
	 *
	 * @return BBCParser
	 */
	public function getBBCParser()
	{
		if ($this->bbc_parser === null)
		{
			$this->bbc_parser = new BBCParser($this->getCodes(), $this->getAutolinkParser());
		}

		return $this->bbc_parser;
	}

	/**
	 * Return an, that's right not and, just an, like a single instance of the autolink parser
	 *
	 * @return Autolink
	 */
	public function getAutolinkParser()
	{
		if ($this->autolink_parser === null)
		{
			$this->autolink_parser = new Autolink($this->getCodes());
		}

		return $this->autolink_parser;
	}

	/**
	 * Return an, that's right not and, just an, like a single instance of the Smiley parser
	 *
	 * @return SmileyParser
	 */
	public function getSmileyParser()
	{
		global $context;

		if ($this->smiley_parser === null)
		{
			if (!isset($context['user']['smiley_path']))
			{
				loadUserContext();
			}

			$this->smiley_parser = new \BBC\SmileyParser($context['user']['smiley_path']);
			$this->smiley_parser->setEnabled($context['smiley_enabled']);
		}

		return $this->smiley_parser;
	}

	/**
	 * Return an, that's right not and, just an, like a single instance of the HTML parser
	 *
	 * @return HtmlParser
	 */
	public function getHtmlParser()
	{
		if ($this->html_parser === null)
		{
			$this->html_parser = new HtmlParser;
		}

		return $this->html_parser;
	}
}