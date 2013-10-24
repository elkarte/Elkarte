<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Checks if the message table already has a fulltext index created and returns the key name
 * Determines if a db is capable of creating a fulltext index
 */
function detectFulltextIndex()
{
	global $context, $db_prefix;

	$db = database();

	$request = $db->query('', '
		SHOW INDEX
		FROM {db_prefix}messages',
		array(
		)
	);
	$context['fulltext_index'] = '';
	if ($request !== false || $db->num_rows($request) != 0)
	{
		while ($row = $db->fetch_assoc($request))
			if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
				$context['fulltext_index'][] = $row['Key_name'];
		$db->free_result($request);

		if (is_array($context['fulltext_index']))
			$context['fulltext_index'] = array_unique($context['fulltext_index']);
	}

	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		$request = $db->query('', '
			SHOW TABLE STATUS
			FROM {string:database_name}
			LIKE {string:table_name}',
			array(
				'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
				'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
			)
		);
	else
		$request = $db->query('', '
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
			)
		);

	if ($request !== false)
	{
		while ($row = $db->fetch_assoc($request))
			if ((isset($row['Type']) && strtolower($row['Type']) != 'myisam') || (isset($row['Engine']) && strtolower($row['Engine']) != 'myisam'))
				$context['cannot_create_fulltext'] = true;
			
		$db->free_result($request);
	}
}

/**
 * Creates and outputs the Sphinx configuration file
 */
function createSphinxConfig()
{
	global $context, $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_character_set, $modSettings;

	// set up to ouput a file to the users browser
	ob_end_clean();
	header('Pragma: ');
	if (!$context['browser']['is_gecko'])
		header('Content-Transfer-Encoding: binary');
	header('Connection: close');
	header('Content-Disposition: attachment; filename="sphinx.conf"');
	header('Content-Type: application/octet-stream');

	$weight_factors = array(
		'age',
		'length',
		'first_message',
		'sticky',
	);

	$weight = array();
	$weight_total = 0;
	foreach ($weight_factors as $weight_factor)
	{
		$weight[$weight_factor] = empty($modSettings['search_weight_' . $weight_factor]) ? 0 : (int) $modSettings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// weightless, then use defaults
	if ($weight_total === 0)
	{
		$weight = array(
			'age' => 25,
			'length' => 25,
			'first_message' => 25,
			'sticky' => 25,
		);
		$weight_total = 100;
	}

	// check paths are set, if not use some defaults
	$modSettings['sphinx_data_path'] = empty($modSettings['sphinx_data_path']) ? '/var/sphinx/data' : $modSettings['sphinx_data_path'];
	$modSettings['sphinx_log_path'] = empty($modSettings['sphinx_log_path']) ? '/var/sphinx/log' : $modSettings['sphinx_log_path'];

	// output our minimal configuration file to get them started
	echo '#
# Sphinx configuration file (sphinx.conf), configured for ElkArte
#
# This is the minimum needed clean, simple, functional
#
# By default the location of this file would probably be:
# /usr/local/etc/sphinx.conf
#

source elkarte_source
{
	type				= mysql
	sql_host 			= ', $db_server, '
	sql_user			= ', $db_user, '
	sql_pass			= ', $db_passwd, '
	sql_db				= ', $db_name, '
	sql_port			= 3306', empty($db_character_set) ? '' : '
	sql_query_pre		= SET NAMES ' . $db_character_set, '
	sql_query_pre		=	\
		REPLACE INTO ', $db_prefix, 'settings (variable, value) \
		SELECT \'sphinx_indexed_msg_until\', MAX(id_msg) \
		FROM ', $db_prefix, 'messages
	sql_query_range		= \
		SELECT 1, value \
		FROM ', $db_prefix, 'settings \
		WHERE variable = \'sphinx_indexed_msg_until\'
	sql_range_step		= 1000
	sql_query			= \
		SELECT \
			m.id_msg, m.id_topic, m.id_board, IF(m.id_member = 0, 4294967295, m.id_member) AS id_member, m.poster_time, m.body, m.subject, \
			t.num_replies + 1 AS num_replies, CEILING(1000000 * ( \
				IF(m.id_msg < 0.7 * s.value, 0, (m.id_msg - 0.7 * s.value) / (0.3 * s.value)) * ' . $weight['age'] . ' + \
				IF(t.num_replies < 200, t.num_replies / 200, 1) * ' . $weight['length'] . ' + \
				IF(m.id_msg = t.id_first_msg, 1, 0) * ' . $weight['first_message'] . ' + \
				IF(t.is_sticky = 0, 0, 1) * ' . $weight['sticky'] . ' \
			) / ' . $weight_total . ') AS relevance \
		FROM ', $db_prefix, 'messages AS m, ', $db_prefix, 'topics AS t, ', $db_prefix, 'settings AS s \
		WHERE t.id_topic = m.id_topic \
			AND s.variable = \'maxMsgID\' \
			AND m.id_msg BETWEEN $start AND $end
	sql_attr_uint		= id_topic
	sql_attr_uint		= id_board
	sql_attr_uint		= id_member
	sql_attr_timestamp	= poster_time
	sql_attr_timestamp	= relevance
	sql_attr_timestamp	= num_replies
	sql_query_info		= \
		SELECT * \
		FROM ', $db_prefix, 'messages \
		WHERE id_msg = $id
}

source elkarte_delta_source : elkarte_source
{
	sql_query_pre	= ', isset($db_character_set) ? 'SET NAMES ' . $db_character_set : '', '
	sql_query_range	= \
		SELECT s1.value, s2.value \
		FROM ', $db_prefix, 'settings AS s1, ', $db_prefix, 'settings AS s2 \
		WHERE s1.variable = \'sphinx_indexed_msg_until\' \
			AND s2.variable = \'maxMsgID\'
}

index elkarte_base_index
{
	html_strip 		= 1
	source 			= elkarte_source
	path 			= ', $modSettings['sphinx_data_path'], '/elkarte_sphinx_base.index', empty($modSettings['sphinx_stopword_path']) ? '' : '
	stopwords 		= ' . $modSettings['sphinx_stopword_path'], '
	min_word_len 	= 2
	charset_type 	= utf-8
	charset_table 	= 0..9, A..Z->a..z, _, a..z
}

index elkarte_delta_index : elkarte_base_index
{
	source 			= elkarte_delta_source
	path 			= ', $modSettings['sphinx_data_path'], '/elkarte_sphinx_delta.index
}

index elkarte_index
{
	type			= distributed
	local			= elkarte_base_index
	local			= elkarte_delta_index
}

indexer
{
	mem_limit 		= ', (empty($modSettings['sphinx_indexer_mem']) ? 32 : (int) $modSettings['sphinx_indexer_mem']), 'M
}

searchd
{
	listen 			= ', (empty($modSettings['sphinx_searchd_port']) ? 3312 : (int) $modSettings['sphinx_searchd_port']), '
	listen 			= ', (empty($modSettings['sphinxql_searchd_port']) ? 3313 : (int) $modSettings['sphinxql_searchd_port']), ':mysql41
	log 			= ', $modSettings['sphinx_log_path'], '/searchd.log
	query_log 		= ', $modSettings['sphinx_log_path'], '/query.log
	read_timeout 	= 5
	max_children 	= 30
	pid_file 		= ', $modSettings['sphinx_data_path'], '/searchd.pid
	max_matches 	= ', (empty($modSettings['sphinx_max_results']) ? 3312 : (int) $modSettings['sphinx_max_results']), '
}
';
	obExit(false, false);
}

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
				ADD FULLTEXT {string:name} ({string:name})',
				array(
					'name' => $index
				)
			);
	}
}