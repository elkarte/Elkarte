<?php

namespace ElkArte\sources\subs\MessagesCallback\BodyParser;

class Normal implements BodyParserInterface
{
	protected $_bbc_parser = null;

	public function __construct($highlight, $use_partial_words)
	{
		$this->_searchArray = $highlight;
		$this->_use_partial_words = $use_partial_words;
		$this->_highlight = !empty($highlight);
		$this->_bbc_parser = \BBC\ParserWrapper::instance();
	}

	public function getSearchArray()
	{
		return $this->_searchArray;
	}


	public function prepare($body, $smileys_enabled)
	{
		$body = censor($body);

		// Run BBC interpreter on the message.
		return $this->_bbc_parser->parseMessage($body, $smileys_enabled);
	}
}