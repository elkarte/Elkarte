<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
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
	protected $disabled = [];

	/** @var Codes */
	protected $codes;

	/** @var  BBCParser */
	protected $bbc_parser;

	/** @var  SmileyParser */
	protected $smiley_parser;

	/** @var  HtmlParser */
	protected $html_parser;

	/** @var  Autolink */
	protected $autolink_parser;

	/** @var MarkdownParser */
	protected $markdown_parser;

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
	public static function instance(): ParserWrapper
	{
		if (self::$instance === null)
		{
			self::$instance = new ParserWrapper();
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
	protected function checkLoad(): bool
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
	protected function isEnabled(): bool
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
	public function enableSmileys($toggle): self
	{
		$this->smileys_enabled = (bool) $toggle;

		return $this;
	}

	/**
	 * Return if smileys are enabled for this instance of the parser
	 *
	 * @return bool
	 */
	public function getSmileysEnabled(): bool
	{
		return $this->smileys_enabled;
	}

	/**
	 * Get parsers based on where it will be used
	 *
	 * @param string $area Where it is being called from
	 * @return array
	 */
	protected function getParsersByArea($area): array
	{
		$parsers = [
			'autolink' => false,
			'html' => false,
			'bbc' => false,
			'smiley' => false,
			'markdown' => false,
		];

		// First, see if any hooks set a parser.
		foreach ($parsers as $parser_type => &$parser)
		{
			call_integration_hook('integrate_' . $area . '_' . $parser_type . '_parser', [&$parser, $this]);

			// If not, use the default one
			$parser = $this->{'get' . ucfirst($parser_type) . 'Parser'}($area);
		}

		return $parsers;
	}

	/**
	 * Return the current message parsers
	 *
	 * @return array
	 */
	public function getMessageParser(): array
	{
		return $this->getParsersByArea('message');
	}

	/**
	 * Return the current signature parsers
	 *
	 * @return array
	 */
	public function getSignatureParser(): array
	{
		return $this->getParsersByArea('signature');
	}

	/**
	 * Return the news parsers
	 *
	 * @return array
	 */
	public function getNewsParser(): array
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
	protected function parse($area, $message): string
	{
		// If the load average is too high, don't parse the BBC.
		if (!$this->checkLoad())
		{
			return $message;
		}

		$parsers = $this->getParsersByArea($area);
		$smileys_enabled = $this->smileys_enabled
			&& $GLOBALS['context']['smiley_set'] !== 'none'
			&& empty($GLOBALS['options']['show_no_smileys']);

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
	public function parseMessage($message, $smileys_enabled): string
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
	public function parseSignature($signature, $smileys_enabled): string
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
	public function parseNews($news): string
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
	public function parseEmail($email): string
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
	public function parseCustomFields($field): string
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
	public function parsePoll($poll): string
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
	public function parseAgreement($agreement): string
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
	public function parsePM($pm): string
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
	public function parseReport($report): string
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
	public function parsePackage($package): string
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
	public function parseVerificationControls($question): string
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
	public function parseNotice($notice): string
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
	public function parseBoard($board): string
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
	public function setDisabled(array $disabled): self
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
	public function getCodes(): Codes
	{
		if ($this->codes === null)
		{
			$additional_bbc = [];
			call_integration_hook('integrate_additional_bbc', [&$additional_bbc]);
			$this->codes = new Codes($additional_bbc, array_keys($this->disabled));
		}

		return $this->codes;
	}

	/**
	 * Return an instance of the bbc parser
	 *
	 * @return BBCParser
	 */
	public function getBBCParser(): BBCParser
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
	public function getAutolinkParser(): Autolink
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
	public function getSmileyParser(): SmileyParser
	{
		global $context;

		if ($this->smiley_parser === null)
		{
			if (!isset($context['smiley_path']))
			{
				loadUserContext();
			}

			$this->smiley_parser = new SmileyParser($context['smiley_set']);
			$this->smiley_parser->setEnabled($context['smiley_enabled']);
		}

		return $this->smiley_parser;
	}

	/**
	 * Return an, that's right not and, just an, like a single instance of the HTML parser
	 *
	 * @return HtmlParser
	 */
	public function getHtmlParser(): HtmlParser
	{
		if ($this->html_parser === null)
		{
			$this->html_parser = new HtmlParser();
		}

		return $this->html_parser;
	}

	/**
	 * Return an, that's right not and, just an, like a single instance of the Markdown parser
	 *
	 * @return MarkdownParser
	 */
	public function getMarkdownParser(): MarkdownParser
	{
		if ($this->markdown_parser === null)
		{
			$this->markdown_parser = new MarkdownParser();
		}

		return $this->markdown_parser;
	}
}
