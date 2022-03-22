<?php

/**
 * The base class that defines the methods used to traverse an HTML DOM using
 * either DOMDocument or simple_html_dom
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Converters;

use ElkArte\Util;

/**
 * Class AbstractDomParser
 */
abstract class AbstractDomParser
{
	/** @var object The object that holds the dom */
	public $document;

	/** @var bool If we are using the internal or external parser */
	public $internalParser;

	/** @var string Line end character */
	public $line_end = "\n";

	/** @var string Line break character */
	public $line_break = "  \n\n";

	/** @var int Wordwrap output, set to 0 to skip wrapping */
	public $body_width = 76;

	/**
	 * For a given node, checks if it is anywhere nested inside a code block
	 *
	 *  - Prevents converting anything that's inside a code block
	 *
	 * @param object $node
	 *
	 * @return bool
	 */
	public static function hasParentCode($node, $internalParser)
	{
		$parent = $internalParser ? $node->parentNode : $node->parentNode();
		while ($parent)
		{
			// Anywhere nested inside a code/pre block we don't render tags
			if (in_array($internalParser ? $parent->nodeName : $parent->nodeName(), ['pre', 'code']))
			{
				return true;
			}

			// Back out another level, until we are done
			$parent = $internalParser ? $parent->parentNode : $parent->parentNode();
		}

		return false;
	}

	/**
	 * Set the DOM parser for class, loads the supplied HTML
	 */
	public function setParser()
	{
		$this->internalParser = true;

		// PHP built-in function not available?
		if (!class_exists('\\DOMDocument'))
		{
			$this->internalParser = false;
			require_once(EXTDIR . '/simple_html_dom.php');
		}
	}

	/**
	 * Loads a string of HTML into the parser for processing
	 *
	 * @param string $html
	 */
	public function loadHTML($html)
	{
		if ($this->internalParser)
		{
			// Set up basic parameters for DomDocument, including silencing structural errors
			$current = libxml_use_internal_errors(true);

			// Just the body text, we will wrap it with our own html/head/body to ensure proper loading
			$html = $this->getBodyText($html);

			// Set up processing details
			$this->document = new \DOMDocument();
			$this->document->preserveWhiteSpace = false;
			$this->document->encoding = 'UTF-8';
			$this->document->loadHTML('<?xml encoding="UTF-8"><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>' . $html . '</body></html>');

			// Set the error handle back, clear any errors
			libxml_use_internal_errors($current);
			libxml_clear_errors();
		}
		// Or using the external simple html parser
		else
		{
			$this->document = str_get_html($html, true, true, 'UTF-8', false);
		}
	}

	/**
	 * Returns just the body of a html document such that we are not dealing with head
	 * and any above head markup
	 *
	 * @param $text
	 *
	 * @return string
	 */
	public function getBodyText($text)
	{
		if (preg_match('~<body>(.*)</body>~su', $text, $body))
		{
			return $body[1];
		}

		if (preg_match('~<html>(.*)</html>~su', $text, $body))
		{
			return $body[1];
		}

		return $text;
	}

	/**
	 * Returns just the body of a dom object such that we are not dealing with head
	 * and any above head markup
	 *
	 * @return object
	 */
	public function getDOMBodyNode()
	{
		// First remove any head node
		$this->_removeHeadNode();

		// The body of the HTML is where it's at.
		if ($this->internalParser)
		{
			return $this->document->getElementsByTagName('body')->item(0);
		}

		return $this->document->find('body', 0) ?? $this->document->find('html', 0) ?? $this->document->root;
	}

	/**
	 * Remove any <head node from the DOM
	 *
	 * This is done due to poor structure of some received HTML via email ect
	 */
	private function _removeHeadNode()
	{
		$head = ($this->internalParser) ? $this->document->getElementsByTagName('head')->item(0) : $this->document->find('head', 0);

		if ($head !== null)
		{
			if ($this->internalParser)
			{
				$head->parentNode->removeChild($head);
			}
			else
			{
				$this->document->find('head', 0)->outertext = '';
			}
		}
	}

	/**
	 * Breaks a string up so its no more than width characters long
	 *
	 * - Will break at word boundaries
	 * - If no natural space is found will break mid-word
	 *
	 * @param string $string
	 * @param int $width
	 * @param string $break
	 * @return string
	 */
	public function utf8Wordwrap($string, $width = 76, $break = "\n")
	{
		if ($width < 76)
		{
			return $string;
		}

		$strings = explode($break, $string);
		$lines = array();

		foreach ($strings as $string)
		{
			$in_quote = isset($string[0]) && $string[0] === '>';
			if (empty($string))
			{
				$lines[] = '';
			}
			while (!empty($string))
			{
				// Get the next #width characters before a break (space, punctuation tab etc)
				if (preg_match('~^(.{1,' . $width . '})(?:\s|$|,|\.)~u', $string, $matches))
				{
					// Add the #width to the output and set up for the next pass
					$lines[] = ($in_quote && $matches[1][0] !== '>' ? '> ' : '') . $matches[1];
					$string = Util::substr($string, Util::strlen($matches[1]));
				}
				// Humm just a long word with no place to break, so we simply cut it after width characters
				else
				{
					$lines[] = ($in_quote && $string[0] !== '>' ? '> ' : '') . Util::substr($string, 0, $width);
					$string = Util::substr($string, $width);
				}
			}
		}

		// Join it all the shortened sections up on our break characters
		return implode($break, $lines);
	}

	/**
	 * Get the nesting level when inside a list
	 *
	 * @param object $node
	 *
	 * @return int
	 */
	public function hasParentList($node)
	{
		$depth = 0;

		$parent = $this->getParent($node);
		while ($parent)
		{
			// Anywhere nested inside a list we need to get the depth
			$tag = $this->getName($parent);
			if (in_array($tag, ['ul', 'ol']))
			{
				$depth++;
			}

			// Back out another level
			$parent = $this->getParent($parent);
		}

		return $depth;
	}

	/**
	 * Returns the parent node of another node
	 *
	 * @param $node
	 * @return object
	 */
	public function getParent($node)
	{
		if ($node === null)
		{
			return null;
		}

		return $this->internalParser ? $node->parentNode : $node->parentNode();
	}

	/**
	 * Returns the node Name of a node
	 *
	 * @param $node
	 * @return string
	 */
	public function getName($node)
	{
		if ($node === null)
		{
			return '';
		}

		return $this->internalParser ? $node->nodeName : $node->nodeName();
	}

	/**
	 * Returns the HTML of the document
	 *
	 * @return string
	 */
	public function getHTML()
	{
		if ($this->internalParser)
		{
			return html_entity_decode(htmlspecialchars_decode($this->document->saveHTML(), ENT_QUOTES), ENT_QUOTES, 'UTF-8');
		}

		return $this->document->save();
	}

	/**
	 * Gets a node object
	 *
	 * @param object $node
	 * @param int $item
	 * @return object
	 */
	public function getItem($node, $item)
	{
		return $this->internalParser ? $node->item($item) : $node[$item];
	}

	/**
	 * gets a node length
	 *
	 * @param object|array $node
	 * @return int
	 */
	public function getLength($node)
	{
		return $this->internalParser ? $node->length : count($node);
	}

	/**
	 * gets all children of a parent node
	 *
	 * @param object|array $node
	 * @return object
	 */
	public function getChildren($node)
	{
		return $this->internalParser ? $node->childNodes : $node->childNodes();
	}

	/**
	 * gets a specific child of a parent node
	 *
	 * @param object|array $node
	 * @param int child number to return
	 * @return object
	 */
	public function getChild($node, $child)
	{
		return $this->internalParser ? $node->childNodes->item($child) : $node->childNodes($child);
	}

	/**
	 * gets the next sibling of a node
	 *
	 * @param object|array $node
	 * @return object
	 */
	public function getSibling($node)
	{
		return $this->internalParser ? $node->nextSibling : $node->next_sibling();
	}

	/**
	 * gets a node value
	 *
	 * @param object $node
	 * @return string
	 */
	public function getValue($node)
	{
		if ($node === null)
		{
			return '';
		}

		if ($this->internalParser)
		{
			return $node->nodeValue;
		}

		return html_entity_decode(htmlspecialchars_decode($node->innertext, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Sets a node to a text value, replacing what was there
	 *
	 * @param $node
	 * @param $text
	 */
	public function setTextNode($node, $text)
	{
		if ($this->internalParser)
		{
			$text_node = $this->document->createTextNode($text);
			$node->parentNode->replaceChild($text_node, $node);
		}
		else
		{
			$node->outertext = $text;
		}
	}

	/**
	 * Gets the inner html of a node
	 *
	 * @param \DOMNode|object $node
	 * @return string
	 */
	public function getInnerHTML($node)
	{
		if ($this->internalParser)
		{
			$doc = new \DOMDocument();
			$doc->preserveWhiteSpace = true;
			$doc->appendChild($doc->importNode($node, true));
			$html = trim($doc->saveHTML());
			$tag = $node->nodeName;

			return preg_replace('@^<' . $tag . '[^>]*>|</' . $tag . '>$@', '', $html);
		}

		return $node->innertext;
	}

	/**
	 * Gets the outer html of a node
	 *
	 * @param \DOMNode|object $node
	 * @return string
	 */
	public function getOuterHTML($node)
	{
		return $this->internalParser ? htmlspecialchars_decode($this->document->saveHTML($node)) : $node->outertext;
	}

	/**
	 * Gets the inner html of a node
	 *
	 * @param \DOMNode|object $node
	 * @return string
	 */
	public function setInnerHTML($node)
	{
		if ($this->internalParser)
		{
			$doc = new \DOMDocument();
			$doc->appendChild($doc->importNode($node, true));
			$html = trim($doc->saveHTML());
			$tag = $node->nodeName;

			return preg_replace('@^<' . $tag . '[^>]*>|</' . $tag . '>$@', '', $html);
		}

		return $node->innertext;
	}
}