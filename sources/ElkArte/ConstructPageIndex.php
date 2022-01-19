<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Constructs a page list.
 *
 * What it does:
 *
 * - Builds the page list, e.g. 1 ... 6 7 [8] 9 10 ... 15.
 * - Flexible_start causes it to use "url.page" instead of "url;start=page".
 * - Very importantly, cleans up the start value passed, and forces it to
 *   be a multiple of num_per_page.
 * - Checks that start is not more than max_value.
 * - Base_url should be the URL without any start parameter on it.
 * - Uses the compactTopicPagesEnable and compactTopicPagesContiguous
 *   settings to decide how to display the menu.
 * - Substitutes {scripturl} to $scripturl and {base_link} based on Flexible_start
 *
 * @param string $base_url The base URL to be used for each link.
 * @param int &$start The start position, by reference. If this is not a multiple of the number
 * of items per page, it is sanitized to be so and the value will persist upon the function's return.
 * @param int $max_value The total number of items you are paginating for.
 * @param int $num_per_page The number of items to be displayed on a given page.
 * @param bool $flexible_start = false Use "url.page" instead of "url;start=page"
 * @param mixed[] $show associative array of option => boolean paris
 *
 * @return string
 * @example $pageindex = constructPageIndex({scripturl} . '?board=' . $board, $_REQUEST['start'], $num_messages,
 *     $maxindex, true);
 */
class ConstructPageIndex extends AbstractModel
{
	/** @var int stating page */
	private $start;

	/** @var array holds the desired options for the index all, prev_next, all_selected */
	private $show;

	/** @var int max page # to show */
	private $max_value;

	/** @var int  */
	private $num_per_page;

	/** @var bool  */
	private $flexible_start;

	/** @var string  */
	private $base_url;

	/** @var string */
	private $base_link;

	/** @var bool If we have tried to navigate off the end */
	private $start_invalid;

	/** @var int */
	private $counter;

	/** @var mixed|string What we are after */
	private $pageindex;

	/** @var int the maximum number of pages in the index */
	private $maxPages;

	/**
	 * ConstructPageIndex constructor.
	 *
	 * @param string $base_url
	 * @param int $start
	 * @param int $max_value
	 * @param int $num_per_page
	 * @param bool $flexible_start
	 * @param array $show
	 */
	public function __construct($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show = [])
	{
		$this->start = (int) $start;
		$this->show = array_merge(['prev_next' => true, 'all' => false], $show);
		$this->max_value = (int) $max_value;
		$this->num_per_page = (int) $num_per_page;
		$this->flexible_start = $flexible_start;
		$this->base_url = $base_url;

		// Need to pull in modsettings, db and user
		parent::__construct();

		// Do it!
		$this->createPageIndex();
		$start = $this->start;
	}

	/**
	 * Does what it says, creates the handy pageindex navigation bar
	 */
	public function createPageIndex()
	{
		global $context;

		$this->setStart();
		$context['current_page'] = $this->start / $this->num_per_page;

		$this->setBaseLink();

		// Compact pages is off or on?
		if (empty($this->_modSettings['compactTopicPagesEnable']))
		{
			$pageindex = $this->simplelLinks();
		}
		else
		{
			$pageindex = $this->compactLinks();
		}

		// Here it is, use getPageIndex to fetch it
		$this->pageindex = $this->showAll($pageindex);
	}

	/**
	 * Returns the completed page index
	 *
	 * @return string
	 */
	public function getPageIndex()
	{
		return $this->pageindex;
	}

	/**
	 * Validate and sets the page starting point
	 *
	 * @return int
	 */
	public function setStart()
	{
		// Save whether $start was less than 0 or not.
		$this->start_invalid = $this->start < 0;

		// Make sure $start is a proper variable - not less than 0.
		if ($this->start_invalid)
		{
			return $this->start = 0;
		}

		// Not greater than the upper bound.
		if ($this->start >= $this->max_value)
		{
			$upper = $this->max_value % $this->num_per_page === 0
				? $this->num_per_page
				: $this->max_value % $this->num_per_page;

			return $this->start = max(0, $this->max_value - $upper);
		}

		// And it has to be a multiple of $num_per_page!
		return $this->start = max(0, $this->start - ($this->start % $this->num_per_page));
	}

	/**
	 * Sets the base url that navigation will start from.
	 * will replace {base_link} and {scripturl} as needed.
	 * Uses ['page_index_template']['base_link'] template
	 */
	private function setBaseLink()
	{
		global $scripturl, $settings;

		$base_link = str_replace('{base_link}', ($this->flexible_start
			? $this->base_url
			: strtr($this->base_url, array('%' => '%%')) . ';start=%1$d'), $settings['page_index_template']['base_link']);

		$this->base_link = str_replace('{scripturl}', $scripturl, $base_link);
	}

	/**
	 * Simple prev 1 2 3 4 next style links
	 *
	 * @return string
	 */
	private function simplelLinks()
	{
		$pageindex = $this->setLeftNavigation();
		$pageindex .= $this->setAll();
		$pageindex .= $this->setRightNavigation();

		return $pageindex;
	}

	/**
	 * AKA previous button for simple navigation index
	 * uses ['page_index_template']['previous_page'] template
	 *
	 * @return string
	 */
	private function setLeftNavigation()
	{
		global $settings, $txt;

		// Previous page language substitution into the page index template
		$previous = str_replace('{prev_txt}', $txt['prev'], $settings['page_index_template']['previous_page']);

		return ($this->start === 0 || !$this->show['prev_next'])
			? ' '
			: sprintf($this->base_link, $this->start - $this->num_per_page, $previous);
	}

	/**
	 * AKA all the pages 1 2 3 4 5 for simple navigation
	 * Uses ['page_index_template']['current_page'] template
	 *
	 * @return string
	 */
	private function setAll()
	{
		global $settings;

		// Show all the pages.
		$display_page = 1;
		$pageindex = '';
		for ($counter = 0; $counter < $this->max_value; $counter += $this->num_per_page)
		{
			$pageindex .= $this->start === $counter && !$this->start_invalid && empty($this->show['all_selected'])
				? sprintf($settings['page_index_template']['current_page'], $display_page++)
				: sprintf($this->base_link, $counter, $display_page++);
		}

		$this->counter = $counter;

		return $pageindex;
	}

	/**
	 * AKA the next button for simple navigation.  Uses 'page_index_template']['next_page']
	 * template
	 *
	 * @return string
	 */
	private function setRightNavigation()
	{
		global $settings, $txt;

		$pageindex = '';
		$display_page = ($this->start + $this->num_per_page) > $this->max_value
			? $this->max_value
			: $this->start + $this->num_per_page;

		if ($this->start !== $this->counter - $this->max_value && !$this->start_invalid
			&& $this->show['prev_next'] && empty($this->show['all_selected']))
		{
			$next = str_replace('{next_txt}', $txt['next'], $settings['page_index_template']['next_page']);

			$pageindex .= $display_page > $this->counter - $this->num_per_page
				? ' '
				: sprintf($this->base_link, $display_page, $next);
		}

		return $pageindex;
	}

	/**
	 * Compact links, good for displaying a many available pages bar
	 * prev page >1< ... 6 7 [8] 9 10 ... 15)
	 *
	 * @return string
	 */
	private function compactLinks()
	{
		$pageindex = '';

		// If they didn't enter an odd value, pretend they did.
		$PageContiguous = (int) ($this->_modSettings['compactTopicPagesContiguous'] - ($this->_modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		// Start with previous, if there is one
		$pageindex .= $this->compactPreviousNavigation();

		// Show the first page. (prev page >1< ... 6 7 [8] 9 10 ... 15)
		if ($this->start > $this->num_per_page * $PageContiguous)
		{
			$pageindex .= sprintf($this->base_link, 0, '1');
		}

		// Show the ... after the first page.  (prev page 1 >...< 6 7 [8] 9 10 ... 15 next page)
		if ($this->start > $this->num_per_page * ($PageContiguous + 1))
		{
			$pageindex .= $this->compactContinuation($PageContiguous, 'before');
		}

		// Show a few pages before the current one. (prev page 1 ... >6 7< [8] 9 10 ... 15 next page)
		$pageindex .= $this->compactBeforeCurrent($PageContiguous);

		// Show the current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
		$pageindex .= $this->compactCurrent();

		// Show a few pages after the current one... (prev page 1 ... 6 7 [8] >9 10< ... 15 next page)
		$pageindex .= $this->compactAfterCurrent($PageContiguous);

		// Show the '...' part near the end. (prev page 1 ... 6 7 [8] 9 10 >...< 15 next page)
		if ($this->start + $this->num_per_page * ($PageContiguous + 1) < $this->getMaxPages())
		{
			$pageindex .= $this->compactContinuation($PageContiguous, 'after');
		}

		// Show the last number in the list. (prev page 1 ... 6 7 [8] 9 10 ... >15<  next page)
		if ($this->start + $this->num_per_page * $PageContiguous < $this->getMaxPages())
		{
			$pageindex .= sprintf($this->base_link, $this->getMaxPages(), $this->getMaxPages() / $this->num_per_page + 1);
		}

		// Show the "next page" link. (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
		$pageindex .= $this->compactNextNavigation();

		return $pageindex;
	}

	/**
	 * Shows the previous page link
	 * Uses ['page_index_template']['previous_page' template
	 *
	 * @return string
	 */
	private function compactPreviousNavigation()
	{
		global $settings, $txt;

		// Show the "prev page" link. (>prev page< 1 ... 6 7 [8] 9 10 ... 15 next page)
		if (!empty($this->start) && $this->show['prev_next'])
		{
			$previous = str_replace('{prev_txt}', $txt['prev'], $settings['page_index_template']['previous_page']);

			return sprintf($this->base_link, $this->start - $this->num_per_page, $previous);
		}

		return '';
	}

	/**
	 * Shows the ... continuation link, helper function for before or after
	 *
	 * @param int $PageContiguous
	 * @param string $position before or after
	 * @return string|string[]
	 */
	private function compactContinuation($PageContiguous, $position)
	{
		global $settings;

		$first = ($this->start + $this->num_per_page * ($PageContiguous + 1));
		$last = $this->getMaxPages();
		$firstpage = $position === 'after' ? $first : $last;
		$lastpage = $position === 'after' ? $last : $first;

		return str_replace(
			'{custom}',
			'data-baseurl="' . htmlspecialchars(JavaScriptEscape(
				strtr($this->flexible_start
					? $this->base_url
					: strtr($this->base_url, ['%' => '%%']) . ';start=%1$d', ['{scripturl}' => '']
				)
			), ENT_COMPAT, 'UTF-8') .
			'" data-perpage="' . $this->num_per_page .
			'" data-firstpage="' . $firstpage .
			'" data-lastpage="' . $lastpage . '"',
			$settings['page_index_template']['expand_pages']
		);
	}

	/**
	 * The maximum number of pages for this index
	 *
	 * @return int
	 */
	private function tmpMaxPages()
	{
		return (int) (($this->max_value - 1) / $this->num_per_page) * $this->num_per_page;
	}

	/**
	 * Simply returns the maxpages for the index
	 *
	 * @return int
	 */
	private function getMaxPages()
	{
		$this->maxPages = $this->maxPages ?? $this->tmpMaxPages();

		return $this->maxPages;
	}

	/**
	 * The numbered pages before the current one. (prev page 1 ... >6 7< [8] 9 10 ... 15 next page)
	 *
	 * @param $PageContiguous
	 * @return string
	 */
	private function compactBeforeCurrent($PageContiguous)
	{
		$pageindex = '';
		for ($nCont = $PageContiguous; $nCont >= 1; $nCont--)
		{
			if ($this->start >= $this->num_per_page * $nCont)
			{
				$tmpStart = $this->start - $this->num_per_page * $nCont;
				$pageindex .= sprintf($this->base_link, $tmpStart, $tmpStart / $this->num_per_page + 1);
			}
		}

		return $pageindex;
	}

	/**
	 * The current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
	 * Uses ['page_index_template']['current_page'] template
	 *
	 * @return string
	 */
	private function compactCurrent()
	{
		global $settings;

		if (!$this->start_invalid && empty($this->show['all_selected']))
		{
			return sprintf($settings['page_index_template']['current_page'], ($this->start / $this->num_per_page + 1));
		}

		return sprintf($this->base_link, $this->start, $this->start / $this->num_per_page + 1);
	}

	/**
	 * The pages after the current one... (prev page 1 ... 6 7 [8] >9 10< ... 15 next page)
	 *
	 * @param $PageContiguous
	 * @return string
	 */
	private function compactAfterCurrent($PageContiguous)
	{
		$pageindex = '';
		for ($nCont = 1; $nCont <= $PageContiguous; $nCont++)
		{
			if ($this->start + $this->num_per_page * $nCont <= $this->getMaxPages())
			{
				$tmpStart = $this->start + $this->num_per_page * $nCont;
				$pageindex .= sprintf($this->base_link, $tmpStart, $tmpStart / $this->num_per_page + 1);
			}
		}

		return $pageindex;
	}

	/**
	 * Show the "next page" link. (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
	 * Uses ['page_index_template']['next_page'] template
	 *
	 * @return string
	 */
	private function compactNextNavigation()
	{
		global $settings, $txt;

		if ($this->start !== $this->getMaxPages() && $this->show['prev_next'] && empty($this->show['all_selected']))
		{
			$next = str_replace('{next_txt}', $txt['next'], $settings['page_index_template']['next_page']);

			return sprintf($this->base_link, $this->start + $this->num_per_page, $next);
		}

		return '';
	}

	/**
	 * The show all button if requested/
	 * Uses 'page_index_template']['current_page'] template
	 *
	 * @param $pageindex
	 * @return mixed|string
	 */
	private function showAll($pageindex)
	{
		global $settings, $txt;

		// The "all" button
		if ($this->show['all'])
		{
			if (!empty($this->show['all_selected']))
			{
				$pageindex .= sprintf($settings['page_index_template']['current_page'], $txt['all']);
			}
			else
			{
				$all = str_replace('{all_txt}', $txt['all'], $settings['page_index_template']['all']);
				$pageindex .= sprintf(str_replace('%1$d', '%1$s', $this->base_link), '0;all', $all);
			}
		}

		return $pageindex;
	}
}