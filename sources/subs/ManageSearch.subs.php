<?php

/**
 * Support functions for setting up the search features and creating search index's
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Checks if the message table already has a fulltext index created and returns the key name
 * Determines if a db is capable of creating a fulltext index
 *
 * @package Search
 */
function detectFulltextIndex()
{
	global $context, $db_prefix;

	$db = database();

	$request = $db->query('', '
		SHOW INDEX
		FROM {db_prefix}messages',
		array()
	);
	$context['fulltext_index'] = '';
	if ($request !== false || $db->num_rows($request) != 0)
	{
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['Column_name'] === 'body' && (isset($row['Index_type']) && $row['Index_type'] === 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] === 'FULLTEXT'))
			{
				$context['fulltext_index'][] = $row['Key_name'];
			}
		}
		$db->free_result($request);

		if (is_array($context['fulltext_index']))
		{
			$context['fulltext_index'] = array_unique($context['fulltext_index']);
		}
	}

	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
	{
		$request = $db->query('', '
			SHOW TABLE STATUS
			FROM {string:database_name}
			LIKE {string:table_name}',
			array(
				'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
				'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
			)
		);
	}
	else
	{
		$request = $db->query('', '
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
			)
		);
	}

	if ($request !== false)
	{
		while ($row = $db->fetch_assoc($request))
		{
			if ((isset($row['Type']) && strtolower($row['Type']) !== 'myisam') || (isset($row['Engine']) && strtolower($row['Engine']) !== 'myisam'))
			{
				$context['cannot_create_fulltext'] = true;
			}
		}

		$db->free_result($request);
	}
}

/**
 * Attempts to determine the version of the Sphinx damon
 */
function SphinxVersion()
{
	$version = '0.0.0';

	// Can we get the version that is running/installed?
	@exec('searchd --help', $sphver);
	if (!empty($sphver) && preg_match('~Sphinx (\d\.\d\.\d\d?)~', $sphver[0], $match))
	{
		$version = $match[1];
	}

	return $version;
}

/**
 * Creates and outputs the Sphinx configuration file
 *
 * @package Search
 */
function createSphinxConfig()
{
	global $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $modSettings;

	$version = SphinxVersion();

	// Set up to output a file to the users browser
	while (ob_get_level() > 0)
		@ob_end_clean();

	header('Content-Encoding: none');
	header('Pragma: no-cache');
	header('Cache-Control: no-cache');
	header('Connection: close');
	header('Content-Disposition: attachment; filename="sphinx.conf"');
	header('Content-Type: application/octet-stream');

	$weight_factors = array(
		'age',
		'length',
		'first_message',
		'sticky',
		'likes',
	);

	$weight = array();
	$weight_total = 0;
	foreach ($weight_factors as $weight_factor)
	{
		$weight[$weight_factor] = empty($modSettings['search_weight_' . $weight_factor]) ? 0 : (int) $modSettings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// Weightless, then use defaults
	if ($weight_total === 0)
	{
		$weight = array(
			'age' => 25,
			'length' => 25,
			'first_message' => 25,
			'sticky' => 15,
			'likes' => 10
		);
		$weight_total = 100;
	}

	// Check paths are set, if not use some defaults
	$modSettings['sphinx_data_path'] = empty($modSettings['sphinx_data_path']) ? '/var/sphinx/data' : $modSettings['sphinx_data_path'];
	$modSettings['sphinx_log_path'] = empty($modSettings['sphinx_log_path']) ? '/var/sphinx/log' : $modSettings['sphinx_log_path'];

	// Output our minimal configuration file to get them started
	echo '#
# Sphinx configuration file (sphinx.conf), configured for ElkArte
#
# This is the minimum needed clean, simple, functional
#
# By default the location of this file would probably be:
# /usr/local/etc/sphinx.conf or /etc/sphinxsearch/sphinx.conf
#

source elkarte_source
{
	type				= mysql
	sql_host			= ', $db_server, '
	sql_user			= ', $db_user, '
	sql_pass			= ', $db_passwd, '
	sql_db				= ', $db_name, '
	sql_port			= 3306
	sql_query_pre		= SET NAMES utf8
	# If you do not have query_cache enabled in my.cnf, then you can comment out the next line
	sql_query_pre		= SET SESSION query_cache_type=OFF
	sql_query_pre		= \
		REPLACE INTO ', $db_prefix, 'settings (variable, value) \
		SELECT \'sphinx_indexed_msg_until\', MAX(id_msg) \
		FROM ', $db_prefix, 'messages
	sql_query_range		= \
		SELECT 1, value \
		FROM ', $db_prefix, 'settings \
		WHERE variable	= \'sphinx_indexed_msg_until\'
	sql_range_step		= 1000
	sql_query			= \
		SELECT \
			m.id_msg, m.id_topic, m.id_board, IF(m.id_member = 0, 4294967295, m.id_member) AS id_member, m.poster_time, m.body, m.subject, \
			t.num_replies + 1 AS num_replies, CEILING(1000000 * ( \
				IF(m.id_msg < 0.7 * s.value, 0, (m.id_msg - 0.7 * s.value) / (0.3 * s.value)) * ' . $weight['age'] . ' + \
				IF(t.num_replies < 50, t.num_replies / 50, 1) * ' . $weight['length'] . ' + \
				IF(m.id_msg = t.id_first_msg, 1, 0) * ' . $weight['first_message'] . ' + \
				IF(t.num_likes < 10, t.num_likes / 10, 1) * ' . $weight['likes'] . ' + \
				IF(t.is_sticky = 0, 0, 100) * ' . $weight['sticky'] . ' \
			) / ' . $weight_total . ') AS relevance \
		FROM ', $db_prefix, 'messages AS m, ', $db_prefix, 'topics AS t, ', $db_prefix, 'settings AS s \
		WHERE t.id_topic = m.id_topic \
			AND s.variable = \'maxMsgID\' \
			AND m.id_msg BETWEEN $start AND $end
	sql_attr_uint		= id_topic
	sql_attr_uint		= id_board
	sql_attr_uint		= id_member
	sql_attr_timestamp	= poster_time
	sql_attr_uint		= relevance
	sql_attr_uint		= num_replies
}

source elkarte_delta_source : elkarte_source
{
	sql_query_pre = SET NAMES utf8
	# If you do not have query_cache enabled in my.cnf, then you can comment out the next line
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_range	= \
		SELECT s1.value, s2.value \
		FROM ', $db_prefix, 'settings AS s1, ', $db_prefix, 'settings AS s2 \
		WHERE s1.variable = \'sphinx_indexed_msg_until\' \
			AND s2.variable = \'maxMsgID\'
}

index elkarte_base_index
{
	html_strip		= 1
	source			= elkarte_source
	path			= ', $modSettings['sphinx_data_path'], '/elkarte_sphinx_base.index', empty($modSettings['sphinx_stopword_path']) ? '' : '
	stopwords		= ', $modSettings['sphinx_stopword_path'], '
	min_word_len	= 2', version_compare($version, '2.2.2') < 0 ? '
	charset_type	= utf-8' : '', '
	charset_table	= 0..9, A..Z->a..z, _, a..z, U+451->U+435, U+401->U+435, U+410..U+42F->U+430..U+44F, U+430..U+44F
	ignore_chars	= -, U+AD
}

index elkarte_delta_index : elkarte_base_index
{
	source			= elkarte_delta_source
	path			= ', $modSettings['sphinx_data_path'], '/elkarte_sphinx_delta.index
}

index elkarte_index
{
	type			= distributed
	local			= elkarte_base_index
	local			= elkarte_delta_index
}

indexer
{
	mem_limit		= ', (empty($modSettings['sphinx_indexer_mem']) ? 128 : (int) $modSettings['sphinx_indexer_mem']), 'M
}

searchd
{
	listen					= ', (empty($modSettings['sphinx_searchd_port']) ? 9312 : (int) $modSettings['sphinx_searchd_port']), '
	listen					= ', (empty($modSettings['sphinxql_searchd_port']) ? 9306 : (int) $modSettings['sphinxql_searchd_port']), ':mysql41
	log						= ', $modSettings['sphinx_log_path'], '/searchd.log
	query_log				= ', $modSettings['sphinx_log_path'], '/query.log
	read_timeout			= 5
	max_children			= 30
	pid_file				= ', $modSettings['sphinx_data_path'], '/searchd.pid', version_compare($version, '2.2.3') < 0 ? '
	max_matches				= ' . (empty($modSettings['sphinx_max_results']) ? 2000 : (int) $modSettings['sphinx_max_results']) : '', '
}
';
	obExit(false, false);
}

/**
 * Drop one or more indexes from a table and adds them back if specified
 *
 * @package Search
 * @param string $table
 * @param string[]|string $indexes
 * @param boolean $add
 */
function alterFullTextIndex($table, $indexes, $add = false)
{
	$db = database();

	$indexes = is_array($indexes) ? $indexes : array($indexes);

	// Make sure it's gone before creating it.
	$db->query('', '
		ALTER TABLE ' . $table . '
		DROP INDEX ' . implode(',
		DROP INDEX ', $indexes),
		array(
			'db_error_skip' => true,
		)
	);

	if ($add)
	{
		foreach ($indexes as $index)
			$db->query('', '
				ALTER TABLE ' . $table . '
				ADD FULLTEXT {raw:name} ({raw:name})',
				array(
					'name' => $index
				)
			);
	}
}

/**
 * Creates a custom search index
 *
 * @package Search
 * @param int $start
 * @param int $messages_per_batch
 * @param string $column_size_definition
 * @param mixed[] $index_settings array containing specifics of what to create e.g. bytes per word
 */
function createSearchIndex($start, $messages_per_batch, $column_size_definition, $index_settings)
{
	global $modSettings;

	$db = database();
	$db_search = db_search();
	$step = 1;

	// Starting a new index we set up for the run
	if ($start === 0)
	{
		drop_log_search_words();

		$db_search->create_word_search($column_size_definition);

		// Temporarily switch back to not using a search index.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'custom')
			updateSettings(array('search_index' => ''));

		// Don't let simultaneous processes be updating the search index.
		if (!empty($modSettings['search_custom_index_config']))
			updateSettings(array('search_custom_index_config' => ''));
	}

	$num_messages = array(
		'done' => 0,
		'todo' => 0,
	);

	$request = $db->query('', '
		SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
		FROM {db_prefix}messages
		GROUP BY todo',
		array(
			'starting_id' => $start,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];

	// Done with indexing the messages, on to the next step
	if (empty($num_messages['todo']))
	{
		$step = 2;
		$percentage = 80;
		$start = 0;
	}
	// Still on step one, inserting all the indexed words.
	else
	{
		// Number of seconds before the next step.
		$stop = time() + 3;
		while (time() < $stop)
		{
			$inserts = array();
			$request = $db->query('', '
				SELECT id_msg, body
				FROM {db_prefix}messages
				WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
				LIMIT {int:limit}',
				array(
					'starting_id' => $start,
					'ending_id' => $start + $messages_per_batch - 1,
					'limit' => $messages_per_batch,
				)
			);
			$forced_break = false;
			$number_processed = 0;
			while ($row = $db->fetch_assoc($request))
			{
				// In theory it's possible for one of these to take friggin ages so add more timeout protection.
				if ($stop < time())
				{
					$forced_break = true;
					break;
				}

				$number_processed++;
				foreach (text2words($row['body'], $index_settings['bytes_per_word'], true) as $id_word)
					$inserts[] = array($id_word, $row['id_msg']);
			}
			$num_messages['done'] += $number_processed;
			$num_messages['todo'] -= $number_processed;
			$db->free_result($request);

			$start += $forced_break ? $number_processed : $messages_per_batch;

			if (!empty($inserts))
				$db->insert('ignore',
					'{db_prefix}log_search_words',
					array('id_word' => 'int', 'id_msg' => 'int'),
					$inserts,
					array('id_word', 'id_msg')
				);

			// Done then set up for the next step, set up for the next loop.
			if ($num_messages['todo'] === 0)
			{
				$step = 2;
				$start = 0;
				break;
			}
			else
				updateSettings(array('search_custom_index_resume' => serialize(array_merge($index_settings, array('resume_at' => $start)))));
		}

		// Since there are still steps to go, 80% is the maximum here.
		$percentage = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
	}

	return array($start, $step, $percentage);
}

/**
 * Removes common stop words from the index as they inhibit search performance
 *
 * @package Search
 * @param int $start
 * @param mixed[] $column_definition
 */
function removeCommonWordsFromIndex($start, $column_definition)
{
	global $modSettings;

	$db = database();

	$stop_words = $start === 0 || empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
	$stop = time() + 3;
	$max_messages = ceil(60 * $modSettings['totalMessages'] / 100);
	$complete = false;

	while (time() < $stop)
	{
		$request = $db->query('', '
			SELECT id_word, COUNT(id_word) AS num_words
			FROM {db_prefix}log_search_words
			WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
			GROUP BY id_word
			HAVING COUNT(id_word) > {int:minimum_messages}',
			array(
				'starting_id' => $start,
				'ending_id' => $start + $column_definition['step_size'] - 1,
				'minimum_messages' => $max_messages,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$stop_words[] = $row['id_word'];
		$db->free_result($request);

		updateSettings(array('search_stopwords' => implode(',', $stop_words)));

		if (!empty($stop_words))
			$db->query('', '
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word in ({array_int:stop_words})',
				array(
					'stop_words' => $stop_words,
				)
			);

		$start += $column_definition['step_size'];
		if ($start > $column_definition['max_size'])
		{
			$complete = true;
			break;
		}
	}

	return array($start, $complete);
}

/**
 * Drops the log search words table(s)
 *
 * @package Search
 */
function drop_log_search_words()
{
	$db_table = db_table();

	$db_table->db_drop_table('{db_prefix}log_search_words');
}