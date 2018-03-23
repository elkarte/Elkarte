<?php

namespace ElkArte\sources\subs\MessagesCallback\BodyParser;

interface BodyParserInterface
{
	public function __construct($highlight, $use_partial_words);

	public function prepare($body, $smileys_enabled);

	public function getSearchArray();
}