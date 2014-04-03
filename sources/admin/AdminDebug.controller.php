<?php

/**
 * Functions concerned with viewing queries, and is used for debugging.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Admin class for interfacing with the debug function viewquery
 */
class AdminDebug_Controller extends Action_Controller
{
	/**
	 * Main dispatcher.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// what to do first... viewquery! What, it'll work or it won't.
		// $this->action_viewquery();
	}

	/**
	 * Show the database queries for debugging
	 * What this does:
	 * - Toggles the session variable 'view_queries'.
	 * - Views a list of queries and analyzes them.
	 * - Requires the admin_forum permission.
	 * - Is accessed via ?action=viewquery.
	 * - Strings in this function have not been internationalized.
	 */
	public function action_viewquery()
	{
		global $context, $txt, $db_show_debug;

		// We should have debug mode enabled, as well as something to display!
		if (!isset($db_show_debug) || $db_show_debug !== true || !isset($_SESSION['debug']))
			fatal_lang_error('no_access', false);

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		// If we're just hiding/showing, do it now.
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'hide')
		{
			$_SESSION['view_queries'] = $_SESSION['view_queries'] == 1 ? 0 : 1;

			if (strpos($_SESSION['old_url'], 'action=viewquery') !== false)
				redirectexit();
			else
				redirectexit($_SESSION['old_url']);
		}

		$query_id = isset($_REQUEST['qq']) ? (int) $_REQUEST['qq'] - 1 : -1;

		// Just to stay on the safe side, better remove any layer and add back only html
		$layers = Template_Layers::getInstance();
		$layers->removeAll();
		$layers->add('html');
		loadTemplate('Admin');

		$context['sub_template'] = 'viewquery';
		$context['queries_data'] = array();

		foreach ($_SESSION['debug'] as $q => $query_data)
		{
			// Fix the indentation....
			$query_data['q'] = $this->_normalize_query_indent($query_data['q']);

			// Make the filenames look a bit better.
			if (isset($query_data['f']))
				$query_data['f'] = preg_replace('~^' . preg_quote(BOARDDIR, '~') . '~', '...', $query_data['f']);

			$select_query = $this->_is_select_query($query_data['q']);
			$context['queries_data'][$q] = array(
				'text' => nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars($query_data['q'], ENT_COMPAT, 'UTF-8'))),
				'is_select' => !empty($select_query),
				'position_time' => '',
				'explain' => array(),
			);

			if (!empty($query_data['f']) && !empty($query_data['l']))
				$context['queries_data'][$q]['position_time'] = sprintf($txt['debug_query_in_line'], $query_data['f'], $query_data['l']);

			if (isset($query_data['s'], $query_data['t']) && isset($txt['debug_query_which_took_at']))
				$context['queries_data'][$q]['position_time'] .= sprintf($txt['debug_query_which_took_at'], round($query_data['t'], 8), round($query_data['s'], 8));
			else
				$context['queries_data'][$q]['position_time'] .= sprintf($txt['debug_query_which_took'], round($query_data['t'], 8));

			// Explain the query.
			if ($query_id == $q && $select_query)
			{
				$context['queries_data'][$q]['explain'] = $this->_explain_query($select_query);
			}
		}
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
	 *                      returnes the SELECT itself
	 */
	protected function _is_select_query($query_data)
	{
		$is_select_query = substr(trim($query_data), 0, 6) == 'SELECT';
		$select = false;

		if ($is_select_query)
			$select = $query_data;
		elseif (preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+(SELECT .+)$~s', trim($query_data), $matches) != 0)
		{
			$is_select_query = true;
			$select = $matches[1];
		}
		elseif (preg_match('~^CREATE TEMPORARY TABLE .+?(SELECT .+)$~s', trim($query_data), $matches) != 0)
		{
			$is_select_query = true;
			$select = $matches[1];
		}
		// Temporary tables created in earlier queries are not explainable.
		if ($is_select_query)
		{
			foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
				if (strpos($select, $tmp) !== false)
				{
					$is_select_query = false;
					break;
				}
		}

		return $select;
	}

	/**
	 * Does the EXPLAIN of a query
	 *
	 * @param string $query_data - The query string
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
	protected function _explain_query($select_query)
	{
		// db work...
		$db = database();

		$result = $db->query('', '
			EXPLAIN ' . $select_query,
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
	 * Get admin information from the database.
	 * Accessed by ?action=viewadminfile.
	 */
	public function action_viewadminfile()
	{
		global $modSettings;

		require_once(SUBSDIR . '/AdminDebug.subs.php');

		// Don't allow non-administrators.
		isAllowedTo('admin_forum');

		setMemoryLimit('32M');

		if (empty($_REQUEST['filename']) || !is_string($_REQUEST['filename']))
			fatal_lang_error('no_access', false);

		$file = adminInfoFile($_REQUEST['filename']);

		// @todo Temp
		// Figure out if sesc is still being used.
		if (strpos($file['file_data'], ';sesc=') !== false)
			$file['file_data'] = '
if (!(\'elkForum_sessionvar\' in window))
	window.elkForum_sessionvar = \'sesc\';
' . strtr($file['file_data'], array(';sesc=' => ';\' + window.elkForum_sessionvar + \'='));

		Template_Layers::getInstance()->removeAll();

		// Lets make sure we aren't going to output anything nasty.
		@ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			@ob_start();

		// Make sure they know what type of file we are.
		header('Content-Type: ' . $file['filetype']);
		echo $file['file_data'];
		obExit(false);
	}
}