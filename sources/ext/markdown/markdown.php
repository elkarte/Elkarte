<?php

require_once dirname(__FILE__) . '/Michelf/Markdown.inc.php';

function Markdown($text) {
	return \Michelf\Markdown::defaultTransform($text);
}