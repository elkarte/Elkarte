<?php

require_once dirname(__FILE__) . '/Michelf/MarkdownExtra.inc.php';

function Markdown($text) {
	$parser = new \Michelf\MarkdownExtra;
	$parser->hashtag_protection = true;

	return $parser->transform($text);
}