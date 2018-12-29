<?php

/**
 * Censor bad words and make them not-so-bad.
 * Does the same thing as the old censorText()
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class Censor
 */
class Censor
{
	const WHOLE_WORD        = 'censorWholeWord';
	const IGNORE_CASE       = 'censorIgnoreCase';
	const SHOW_NO_CENSORED  = 'show_no_censored';
	const ALLOW_NO_CENSORED = 'allow_no_censored';

	protected $vulgar = array();
	protected $proper = array();
	protected $options = array(
		self::WHOLE_WORD        => false,
		self::IGNORE_CASE       => false,
		self::SHOW_NO_CENSORED  => false,
		self::ALLOW_NO_CENSORED => false,
	);

	/**
	 * Censor constructor.
	 *
	 * @param array $vulgar
	 * @param array $proper
	 * @param array $options
	 */
	public function __construct(array $vulgar, array $proper, array $options = array())
	{
		if (count($vulgar) !== count($proper))
		{
			throw new \InvalidArgumentException('Censored vulgar and proper arrays must be equal sizes');
		}

		$this->setOptions($options);
		$this->setVulgarProper($vulgar, $proper);
	}

	/**
	 * Loads options to the class, such as ignoring case, etc
	 *
	 * @param array $options
	 */
	protected function setOptions(array $options)
	{
		$this->options = array_merge($this->options, $options);
	}

	/**
	 * Searches for naughty words
	 *
	 * @param array $vulgar
	 * @param array $proper
	 */
	protected function setVulgarProper(array $vulgar, array $proper)
	{
		// Quote them for use in regular expressions.
		if ($this->options[self::WHOLE_WORD])
		{
			for ($i = 0, $n = count($vulgar); $i < $n; $i++)
			{
				$vulgar[$i] = str_replace(array('\\\\\\*', '\\*', '&', '\''), array('[*]', '[^\s]*?', '&amp;', '&#039;'), preg_quote($vulgar[$i], '/'));
				$vulgar[$i] = '/(?<=^|\W)' . $vulgar[$i] . '(?=$|\W)/u' . (!$this->options[self::IGNORE_CASE] ? '' : 'i');
			}
		}

		$this->vulgar = $vulgar;
		$this->proper = $proper;
	}

	/**
	 * Censor a string
	 *
	 * @param string $text
	 * @param bool $force
	 * @return string
	 */
	public function censor($text, $force = false)
	{
		if (empty($this->vulgar) || (!$force && !$this->doCensor()))
		{
			return $text;
		}

		if (!$this->options[self::WHOLE_WORD])
		{
			$text = !$this->options[self::IGNORE_CASE] ? str_replace($this->vulgar, $this->proper, $text) : str_ireplace($this->vulgar, $this->proper, $text);
		}
		else
		{
			$text = preg_replace($this->vulgar, $this->proper, $text);
		}

		return $text;
	}

	/**
	 * Check if we should be censoring.
	 *
	 * @todo replace with inline checking at some point
	 *
	 * @return bool
	 */
	public function doCensor()
	{
		global $options, $modSettings;

		return !(!empty($options['show_no_censored']) && !empty($modSettings['allow_no_censored']));
	}
}
