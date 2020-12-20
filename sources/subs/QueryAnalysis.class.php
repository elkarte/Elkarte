<?php

/**
 * A class to analyse and extract information from queries
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

class Query_Analysis
{
	/**
	 * The SELECT statement of the query (if any)
	 * @var string
	 */
	protected $_select;

	/**
	 * Analyze the text of a query and the execution time of a query
	 *
	 * @param mixed[] $query_data array of information regarding the query
	 * @return string[] - 'text', 'is_select', 'position_time'
	 */
	public function extractInfo($query_data)
	{
		global $txt;

		// Fix the indentation....
		$query_data['q'] = $this->_normalize_query_indent($query_data['q']);

		// Make the filenames look a bit better.
		if (isset($query_data['f']))
			$query_data['f'] = preg_replace('~^' . preg_quote(BOARDDIR, '~') . '~', '...', $query_data['f']);

		$query_info = array(
			'text' => nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars($query_data['q'], ENT_COMPAT, 'UTF-8'))),
			'is_select' => $this->_is_select_query($query_data['q']),
			'position_time' => '',
		);

		if (!empty($query_data['f']) && !empty($query_data['l']))
			$query_info['position_time'] = sprintf($txt['debug_query_in_line'], $query_data['f'], $query_data['l']);

		if (isset($query_data['s'], $query_data['t']) && isset($txt['debug_query_which_took_at']))
			$query_info['position_time'] .= sprintf($txt['debug_query_which_took_at'], round($query_data['t'], 8), round($query_data['s'], 8));
		else
			$query_info['position_time'] .= sprintf($txt['debug_query_which_took'], round($query_data['t'], 8));

		return $query_info;
	}

	/**
	 * Analyze the text of a query and the execution time of a query
	 *
	 * @return string[] - 'text', 'is_select', 'position_time'
	 */

	/**
	 * Does the EXPLAIN of a query
	 *
	 * @return string[] an array with the results of the EXPLAIN with two
	 *                  possible structures depending if the EXPLAIN is
	 *                  successful or fails.
	 *                  If successful:
	 *                  array(
	 *                    'headers' => array( ..list of headers.. )
	 *                    'body' => array(
	 *                      array( ..cells.. ) // one row
	 *                    )
	 *                  )
	 *                  If th EXPLAIN fails:
	 *                  array(
	 *                    'is_error' => true
	 *                    'error_text' => the error message
	 *                  )
	 */
	public function doExplain()
	{
		if (empty($this->_select))
			return array();

		// db work...
		$db = database();

		$result = $db->query('', '
			EXPLAIN ' . $this->_select,
			array(
			)
		);

		if ($result === false)
		{
			$explain = array(
				'is_error' => true,
				'error_text' => $db->last_error($db->connection()),
			);
		}
		else
		{
			$row = $db->fetch_assoc($result);
			$explain = array(
				'headers' => array_keys($row),
				'body' => array()
			);

			$db->data_seek($result, 0);
			while ($row = $db->fetch_assoc($result))
				$explain['body'][] = $row;
		}

		return $explain;
	}

	/**
	 * Fix query indentation
	 *
	 * @param string $query_data - The query string
	 */
	protected function _normalize_query_indent($query_data)
	{
		$query_data = ltrim(str_replace("\r", '', $query_data), "\n");
		$query = explode("\n", $query_data);
		$min_indent = 0;

		foreach ($query as $line)
		{
			preg_match('/^(\t*)/', $line, $temp);
			if (strlen($temp[0]) < $min_indent || $min_indent == 0)
				$min_indent = strlen($temp[0]);
		}
		foreach ($query as $l => $dummy)
			$query[$l] = substr($dummy, $min_indent);

		return implode("\n", $query);
	}

	/**
	 * Determines is the query has a SELECT statement and if so it is returned
	 *
	 * @param string $query_data - The query string
	 * @return false|string false if the query doesn't have a SELECT, otherwise
	 *                      returns the SELECT itself
	 */
	protected function _is_select_query($query_data)
	{
		$is_select_query = substr(trim($query_data), 0, 6) == 'SELECT' || substr(trim($query_data), 0, 4) == 'WITH';
		$this->_select = '';

		if ($is_select_query)
			$this->_select = $query_data;
		elseif (preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+(SELECT .+)$~s', trim($query_data), $matches) != 0)
		{
			$is_select_query = true;
			$this->_select = $matches[1];
		}
		elseif (preg_match('~^CREATE TEMPORARY TABLE .+?(SELECT .+)$~s', trim($query_data), $matches) != 0)
		{
			$is_select_query = true;
			$this->_select = $matches[1];
		}

		// Temporary tables created in earlier queries are not explainable.
		if ($is_select_query)
		{
			foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
			{
				if (strpos($this->_select, $tmp) !== false)
				{
					$is_select_query = false;
					break;
				}
			}
		}

		return $is_select_query;
	}
}