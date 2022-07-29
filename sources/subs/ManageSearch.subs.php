<?php

/**
 * Support functions for setting up the search features and creating search index's
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

use ElkArte\Http\Headers;

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

	$fulltext_index = array();
	$db->fetchQuery('
		SHOW INDEX
		FROM {db_prefix}messages',
		array()
	)->fetch_callback(
		function ($row) use (&$fulltext_index) {
			if (($row['Column_name'] === 'body' || $row['Column_name'] === 'subject')
				&& (isset($row['Index_type']) && $row['Index_type'] === 'FULLTEXT'
					|| isset($row['Comment']) && $row['Comment'] === 'FULLTEXT'))
			{
				$fulltext_index[] = $row['Key_name'];
			}
		}
	);

	$fulltext_index = array_unique($fulltext_index);

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

	// innodb (since 5.6.4 and myisam) both support fulltext index
	if ($request === false)
	{
		$context['cannot_create_fulltext'] = true;
	}
	$request->free_result();

	return $fulltext_index;
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

	// Set up to output a file to the users browser
	while (ob_get_level() > 0)
	{
		@ob_end_clean();
	}

	Headers::instance()
		->header('Content-Encoding', 'none')
		->header('Pragma', 'no-cache')
		->header('Cache-Control', 'no-cache')
		->header('Connection', 'close')
		->header('Content-Disposition', 'attachment; filename="sphinx.conf"')
		->contentType('application/octet-stream', '')
		->sendHeaders();

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
	$prefix = (!empty($modSettings['sphinx_index_prefix']) ? $modSettings['sphinx_index_prefix'] : 'elkarte');

	// Output our minimal configuration file to get them started
	echo '#
# Sphinx configuration file (sphinx.conf), configured for ElkArte
#
# This is the minimum needed clean, simple, functional
#
# By default the location of this file would probably be:
# /usr/local/etc/sphinx.conf or /etc/sphinxsearch/sphinx.conf
#

## data source definition
source ', $prefix, '_source
{
	type				= mysql
	sql_host			= ', $db_server, '
	sql_user			= ', $db_user, '
	sql_pass			= ', $db_passwd, '
	sql_db				= ', $db_name, '
	sql_port			= 3306
	sql_query_pre		= SET NAMES utf8
	sql_query_pre		= SET CHARACTER_SET_RESULTS=utf8
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
			m.id_msg, m.id_topic, m.id_board, CASE WHEN m.id_member = 0 THEN 4294967295 ELSE m.id_member END AS id_member, \
			m.poster_time, m.body, m.subject, t.num_replies + 1 AS num_replies, t.num_likes, t.is_sticky, \
			1 - ((m.id_msg - t.id_first_msg) / (t.id_last_msg - t.id_first_msg)) AS position, \		
			CEILING(10 * ( \
				CASE WHEN m.id_msg < 0.6 * s.value THEN 0 ELSE (m.id_msg - 0.6 * s.value) / (0.4 * s.value) END * ' . $weight['age'] . ' + \
				CASE WHEN t.num_replies < 50 THEN t.num_replies / 50 ELSE 1 END * ' . $weight['length'] . ' + \
				CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END * ' . $weight['first_message'] . ' + \
				CASE WHEN t.num_likes < 20 THEN t.num_likes / 20 ELSE 1 END * ' . $weight['likes'] . ' + \
				CASE WHEN t.is_sticky = 0 THEN 0 ELSE 1 END * ' . $weight['sticky'] . ' \
			) * 100/' . $weight_total . ') AS acprel \
		FROM ', $db_prefix, 'messages AS m \
			INNER JOIN ', $db_prefix, 'topics AS t ON (m.id_topic = t.id_topic) \
			INNER JOIN ', $db_prefix, 'settings AS s \
		WHERE t.id_topic = m.id_topic \
			AND s.variable = \'maxMsgID\' \
			AND m.id_msg BETWEEN $start AND $end
	sql_attr_uint		= id_topic
	sql_attr_uint		= id_board
	sql_attr_uint		= id_member
	sql_attr_uint		= poster_time
	sql_attr_uint		= acprel
	sql_attr_uint		= num_replies
	sql_attr_uint		= num_likes
	sql_attr_bool		= is_sticky
	sql_attr_float		= position	
}

source ', $prefix, '_delta_source : ', $prefix, '_source
{
	sql_query_pre = SET NAMES utf8
	sql_query_pre		= SET CHARACTER_SET_RESULTS=utf8
	# If you do not have query_cache enabled in my.cnf, then you can comment out the next line
	sql_query_pre = SET SESSION query_cache_type=OFF
	sql_query_range	= \
		SELECT s1.value, s2.value \
		FROM ', $db_prefix, 'settings AS s1, ', $db_prefix, 'settings AS s2 \
		WHERE s1.variable = \'sphinx_indexed_msg_until\' \
			AND s2.variable = \'maxMsgID\'
}

## index definition
index ', $prefix, '_base_index
{
	html_strip			= 1
	min_prefix_len		= 3
	min_stemming_len	= 4
	stopwords_unstemmed	= 1
	index_exact_words	= 1
	index_field_lengths	= 1
	expand_keywords		= 1
	regexp_filter		= \b(\d+)[.-/]+(\d+)\b => \1_\2
	blend_chars			= +, &, U+23, -, !, @
	blend_mode			= trim_head, trim_none
	source				= ', $prefix, '_source
	path				= ', $modSettings['sphinx_data_path'], '/', $prefix, '_sphinx_base.index', (empty($modSettings['sphinx_stopword_path']) ? '' : '
	stopwords			= ' . $modSettings['sphinx_stopword_path']), '
	# The default is 1.  Changing from that on Sphinx 3 stalls/fails the indexer with blended chars enabled.
	min_word_len		= 1
	charset_table		= 0..9, A..Z->a..z, _, a..z, U+451->U+435, U+401->U+435, U+410..U+42F->U+430..U+44F, U+430..U+44F
	ignore_chars		= U+AD
	morphology			= stem_en, soundex
}

index ', $prefix, '_delta_index : ', $prefix, '_base_index
{
	source			= ', $prefix, '_delta_source
	path			= ', $modSettings['sphinx_data_path'], '/', $prefix, '_sphinx_delta.index
}

index ', $prefix, '_index
{
	type			= distributed
	local			= ', $prefix, '_base_index
	local			= ', $prefix, '_delta_index
}

## indexer settings
indexer
{
	mem_limit		= ', (empty($modSettings['sphinx_indexer_mem']) ? 256 : (int) $modSettings['sphinx_indexer_mem']), 'M
}

## searchd definition
searchd
{
	listen					= ', (empty($modSettings['sphinx_searchd_port']) ? 9312 : (int) $modSettings['sphinx_searchd_port']), '
	listen					= ', (empty($modSettings['sphinxql_searchd_port']) ? 9306 : (int) $modSettings['sphinxql_searchd_port']), ':mysql41
	log						= ', $modSettings['sphinx_log_path'], '/searchd.log
	query_log				= ', $modSettings['sphinx_log_path'], '/query.log
	read_timeout			= 5
	max_children			= 30
	pid_file				= ', $modSettings['sphinx_data_path'], '/searchd.pid
}
';
	obExit(false, false);
}

/**
 * Drop one or more indexes from a table and adds them back if specified
 *
 * @param string $table
 * @param string[]|string $indexes
 * @param bool $add
 * @package Search
 */
function alterFullTextIndex($table, $indexes, $add = false)
{
	$db = database();

	$indexes = is_array($indexes) ? $indexes : array($indexes);

	// Make sure it's gone before creating it.
	$db->skip_next_error();
	$db->query('', '
		ALTER TABLE ' . $table . '
		DROP INDEX ' . implode(',
		DROP INDEX ', $indexes),
		array()
	);

	if ($add)
	{
		foreach ($indexes as $index)
		{
			$name = str_replace(',', '_', $index);
			$db->query('', '
				ALTER TABLE ' . $table . '
				ADD FULLTEXT {raw:name} ({raw:index})',
				array(
					'index' => $index,
					'name'	=> $name
				)
			);
		}
	}
}

/**
 * Creates a custom search index
 *
 * @param int $start
 * @param int $messages_per_batch
 *
 * @return array
 * @package Search
 *
 */
function createSearchIndex($start, $messages_per_batch)
{
	global $modSettings;

	$db = database();
	$db_search = db_search();
	$step = 1;

	// Starting a new index we set up for the run
	if ($start === 0)
	{
		drop_log_search_words();

		$db_search->create_word_search();

		// Temporarily switch back to not using a search index.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'custom')
		{
			updateSettings(array('search_index' => ''));
		}

		// Don't let simultaneous processes be updating the search index.
		if (!empty($modSettings['search_custom_index_config']))
		{
			updateSettings(array('search_custom_index_config' => ''));
		}
	}

	$num_messages = array(
		'done' => 0,
		'todo' => 0,
	);

	$db->fetchQuery('
		SELECT 
			id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
		FROM {db_prefix}messages
		GROUP BY todo',
		array(
			'starting_id' => $start,
		)
	)->fetch_callback(
		function ($row) use (&$num_messages) {
			$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];
		}
	);

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
			$forced_break = false;
			$number_processed = 0;
			$db->fetchQuery('
				SELECT 
					id_msg, body
				FROM {db_prefix}messages
				WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
				LIMIT {int:limit}',
				array(
					'starting_id' => $start,
					'ending_id' => $start + $messages_per_batch - 1,
					'limit' => $messages_per_batch,
				)
			)->fetch_callback(
				function ($row) use (&$forced_break, &$number_processed, &$inserts, $stop) {
					// In theory it's possible for one of these to take friggin ages so add more timeout protection.
					if ($stop < time() || $forced_break)
					{
						$forced_break = true;
						return;
					}

					$number_processed++;
					foreach (text2words($row['body'], true) as $id_word)
					{
						$inserts[] = array($id_word, $row['id_msg']);
					}
				}
			);
			$num_messages['done'] += $number_processed;
			$num_messages['todo'] -= $number_processed;

			$start += $forced_break ? $number_processed : $messages_per_batch;

			if (!empty($inserts))
			{
				$db->insert('ignore',
					'{db_prefix}log_search_words',
					array('id_word' => 'int', 'id_msg' => 'int'),
					$inserts,
					array('id_word', 'id_msg')
				);
			}

			// Done then set up for the next step, set up for the next loop.
			if ($num_messages['todo'] === 0)
			{
				$step = 2;
				$start = 0;
				break;
			}
			else
			{
				updateSettings(array('search_custom_index_resume' => serialize(array('resume_at' => $start))));
			}
		}

		// Since there are still steps to go, 90% is the maximum here.
		$percentage = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 2) * 90;
	}

	return array($start, $step, $percentage);
}

/**
 * Removes common stop words from the index as they inhibit search performance
 *
 * @param int $start
 *
 * @return array
 * @package Search
 */
function removeCommonWordsFromIndex($start)
{
	global $modSettings;

	$db = database();

	$stop_words = $start === 0 || empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
	$stop = time() + 3;
	$max_occurrences = ceil(60 * $modSettings['totalMessages'] / 100);
	$complete = false;
	$step_size = 100000000;
	$max_size = 4294967295; // FFFF FFFF

	while (time() < $stop)
	{
		// Find indexed words that appear to often
		$db->fetchQuery('
			SELECT
				id_word, COUNT(id_word) AS num_words, id_msg
			FROM {db_prefix}log_search_words
			WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
			GROUP BY id_word
			HAVING COUNT(id_word) > {int:minimum_messages}',
			array(
				'starting_id' => $start,
				'ending_id' => min($start + $step_size - 1, $max_size),
				'minimum_messages' => $max_occurrences,
			)
		)->fetch_callback(
			function ($row) use (&$stop_words) {
				$stop_words[] = $row['id_word'];
			}
		);

		// Add them to the stopwords list since we are removing them as to common
		updateSettings(array('search_stopwords' => implode(',', $stop_words)));

		// Pfft ... commoners
		if (!empty($stop_words))
		{
			$db->query('', '
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word in ({array_int:stop_words})',
				array(
					'stop_words' => $stop_words,
				)
			);
		}

		$start += $step_size;
		if ($start >= $max_size)
		{
			$complete = true;
			break;
		}
	}

	$percentage = 90 + (min(round($start / $max_size, 2), 1) * 10);

	return array($start, $complete, $percentage);
}

/**
 * Drops the log search words table(s)
 *
 * @package Search
 */
function drop_log_search_words()
{
	$db_table = db_table();

	$db_table->drop_table('{db_prefix}log_search_words');
}
