<?php

/**
 * Dummy
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

class ParseQuery
{
	protected $parsers = ['b' => 'board', 't' => 'topic', 'p' => 'profile', 's' => 'standard'];
	protected $separator = ';';

	public function parse($query)
	{
		if (isset($this->parsers[$query[0]]))
		{
			$call = $this->parsers[$query[0]];
		}
		else
		{
			$call = $this->parsers['s'];
		}

		return $this->{$call}($query);
	}

	protected function board($query)
	{
		return 'board=' . $this->process($query);
	}

	protected function process($query)
	{
		$match = [];
		$parts = explode('/', $query);
		$split_query = explode('?', $parts[isset($parts[2]) ? 2 : 1]);

		if (isset($parts[2]))
		{
			$real_query = substr($parts[1], strrpos($parts[1], '-') + 1) . '.' . substr($split_query[0], 5);
		}
		else
		{
			$real_query = substr($split_query[0], strrpos($split_query[0], '-') + 1);
		}
		$real_query .= $this->separator . (isset($split_query[1]) ? $split_query[1] : '');

		return $real_query;
	}

	protected function topic($query)
	{
		return 'topic=' . $this->process($query);
	}

	protected function profile($query)
	{
		return 'action=profile;u=' . $this->process($query);
	}

	protected function standard($query)
	{
		return $query;
	}
}
