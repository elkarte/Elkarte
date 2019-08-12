<?php

require_once dirname(__FILE__) . '/Michelf/MarkdownExtra.inc.php';

function Markdown($text) {
	return \Michelf\MarkdownExtra::defaultTransform($text);
}