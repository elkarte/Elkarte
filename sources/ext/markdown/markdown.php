<?php

use Michelf\MarkdownExtra;

require_once __DIR__ . '/Michelf/MarkdownExtra.inc.php';

function Markdown($text) {
	$parser = new MarkdownExtra;
	$parser->hashtag_protection = true;

	return $parser->transform($text);
}
