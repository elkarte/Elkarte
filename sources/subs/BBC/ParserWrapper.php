<?php

namespace BBC;

/**
 * Class ParserWrapper
 *
 * Wrap around the BBC parsers before we implement a DIC.
 * Deprecate in future versions in favor of a DIC
 */
class ParserWrapper
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

    public static $instance;

    /**
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
        return empty($modSettings['enableBBC']);
    }

    /**
     * @param bool $toggle
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
     * @return array
     */
    public function getMessageParsers()
    {
        return $this->getParsersByArea('message');
    }

    /**
     * @return array
     */
    public function getSignatureParser()
    {
        return $this->getParsersByArea('signature');
    }

    /**
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

        if (!$this->isEnabled())
        {
            if ($this->smileys_enabled)
            {
                $parsers['smiley']->parse($message);
            }

            return $message;
        }

        $message = $parsers['bbc']->parse($message);

        if ($this->smileys_enabled)
        {
            $parsers['smiley']->parse($message);
        }

        return $message;
    }

    /**
     * Parse the BBC and smileys in messages
     *
     * @param string $message
     * @param bool   $smileys_enabled
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
     * @param string $smileys_enabled
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
     * @return string
     */
    public function parseNews($news)
    {
        return $this->enableSmileys(true)->parse('news', $news);
    }

    /**
     * Parse the BBC and smileys in emails
     *
     * @param string $message
     * @return string
     */
    public function parseEmail($message)
    {
        return $this->enableSmileys(false)->parse('email', $message);
    }

    /**
     * Parse the BBC and smileys in custom profile fields
     * @param string $field
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
     * @param string $field
     * @return string
     */
    public function parsePoll($field)
    {
        return $this->enableSmileys(true)->parse('poll', $field);
    }

    /**
     * Parse the BBC and smileys in the registration agreement
     *
     * @param string $agreement
     * @return string
     */
    public function parseAgreement($agreement)
    {
        return $this->enableSmileys(true)->parse('agreement', $agreement);
    }

    /**
     * Parse the BBC and smileys in personal messages
     *
     * @param string $message
     * @return string
     */
    public function parsePM($message)
    {
        return $this->enableSmileys(true)->parse('pm', $message);
    }

    /**
     * Parse the BBC and smileys in user submitted reports
     *
     * @param string $report
     * @return string
     */
    public function parseReport($report)
    {
        return $this->enableSmileys(true)->parse('report', $report);
    }

    /**
     * Parse the BBC and smileys in package descriptions
     *
     * @param string $report
     * @return string
     */
    public function parsePackage($string)
    {
        return $this->enableSmileys(true)->parse('package', $string);
    }

    /**
     * Parse the BBC and smileys in user verification controls
     *
     * @param string $question
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
     * @return string
     */
    public function parseBoard($board)
    {
        return $this->enableSmileys(true)->parse('board', $board);
    }

    /**
     * Set the disabled tags
     * (usually from $modSettings['disabledBBC'])
     * @param array $disabled
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
     * @return Codes
     */
    public function getCodes()
    {
        if ($this->codes === null)
        {
            require_once(SUBSDIR . '/BBC/Codes.class.php');
            $this->codes = new \BBC\Codes(array(), $this->disabled);
        }

        return $this->codes;
    }

    /**
     * @return BBCParser
     */
    public function getBBCParser()
    {
        if ($this->bbc_parser === null)
        {
            require_once(SUBSDIR . '/BBC/BBCParser.class.php');
            $this->bbc_parser = new \BBC\BBCParser($this->getCodes(), $this->getAutolinkParser());
        }

        return $this->bbc_parser;
    }

    /**
     * @return Autolink
     */
    public function getAutolinkParser()
    {
        if ($this->autolink_parser === null)
        {
            require_once(SUBSDIR . '/BBC/Autolink.class.php');
            $this->autolink_parser = new \BBC\Autolink($this->getCodes());
        }

        return $this->autolink_parser;
    }

    /**
     * @return SmileyParser
     */
    public function getSmileyParser()
    {
        if ($this->smiley_parser === null)
        {
            require_once(SUBSDIR . '/BBC/SmileyParser.class.php');
            $this->smiley_parser = new \BBC\SmileyParser;
        }

        return $this->smiley_parser;
    }

    /**
     * @return HtmlParser
     */
    public function getHtmlParser()
    {
        if ($this->html_parser === null)
        {
            require_once(SUBSDIR . '/BBC/HtmlParser.class.php');
            $this->html_parser = new \BBC\HtmlParser;
        }

        return $this->html_parser;
    }
}