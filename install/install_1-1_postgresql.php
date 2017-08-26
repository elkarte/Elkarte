<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Install script for PostgreSQL 8.3+
 *
 *
 * Create PostgreSQL functions.
 * Some taken from http://www.xach.com/aolserver/mysql-functions.sql and http://pgfoundry.org/projects/mysqlcompat/.
 * IP Regex in inet_aton from http://www.mkyong.com/database/regular-expression-in-postgresql/.
 */

class InstallInstructions_install_1_1_postgresql
{
	protected $db = null;
	protected $table = null;

	public function __construct($db, $table)
	{
		$this->db = $db;
		return $this->table = $table;
	}

	public function create_functions()
	{
		$this->db->query('', '
		CREATE OR REPLACE FUNCTION FROM_UNIXTIME(integer) RETURNS timestamp AS
			\'SELECT timestamp \'\'epoch\'\' + $1 * interval \'\'1 second\'\' AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION IFNULL (text, text) RETURNS text AS
			\'SELECT COALESCE($1, $2) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION IFNULL (int4, int4) RETURNS int4 AS
			\'SELECT COALESCE($1, $2) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION IFNULL (int8, int8) RETURNS int8 AS
			\'SELECT COALESCE($1, $2) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION IFNULL (character varying, character varying) RETURNS character varying AS
			\'SELECT COALESCE($1, $2) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION IFNULL (character varying, boolean) RETURNS character varying AS
			\'SELECT COALESCE($1, CAST(CAST($2 AS int) AS varchar)) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION IFNULL (int, boolean) RETURNS int AS
			\'SELECT COALESCE($1, CAST($2 AS int)) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION INET_ATON(text) RETURNS bigint AS \'
			SELECT
			CASE WHEN
				$1 !~ \'\'^[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?$\'\' THEN 0
			ELSE
				split_part($1, \'\'.\'\', 1)::int8 * (256 * 256 * 256) +
				split_part($1, \'\'.\'\', 2)::int8 * (256 * 256) +
				split_part($1, \'\'.\'\', 3)::int8 * 256 +
				split_part($1, \'\'.\'\', 4)::int8
			END AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION INET_NTOA(bigint) RETURNS text AS \'
			SELECT
				(($1 >> 24) & 255::int8) || \'\'.\'\' ||
				(($1 >> 16) & 255::int8) || \'\'.\'\' ||
				(($1 >> 8) & 255::int8) || \'\'.\'\' ||
				($1 & 255::int8) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION FIND_IN_SET(needle text, haystack text) RETURNS integer AS \'
			SELECT i AS result
			FROM generate_series(1, array_upper(string_to_array($2,\'\',\'\'), 1)) AS g(i)
			WHERE (string_to_array($2,\'\',\'\'))[i] = $1
				UNION ALL
			SELECT 0
			LIMIT 1\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION FIND_IN_SET(needle integer, haystack text) RETURNS integer AS \'
			SELECT i AS result
			FROM generate_series(1, array_upper(string_to_array($2,\'\',\'\'), 1)) AS g(i)
			WHERE  (string_to_array($2,\'\',\'\'))[i] = CAST($1 AS text)
				UNION ALL
			SELECT 0
			LIMIT 1\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION FIND_IN_SET(needle smallint, haystack text) RETURNS integer AS \'
			SELECT i AS result
			FROM generate_series(1, array_upper(string_to_array($2,\'\',\'\'), 1)) AS g(i)
			WHERE  (string_to_array($2,\'\',\'\'))[i] = CAST($1 AS text)
				UNION ALL
			SELECT 0
			LIMIT 1\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION LEFT (text, int4) RETURNS text AS
			\'SELECT SUBSTRING($1 FROM 0 FOR $2) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION add_num_text (text, integer) RETURNS text AS
			\'SELECT CAST ((CAST($1 AS integer) + $2) AS text) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION YEAR (timestamp) RETURNS integer AS
			\'SELECT CAST (EXTRACT(YEAR FROM $1) AS integer) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION MONTH (timestamp) RETURNS integer AS
			\'SELECT CAST (EXTRACT(MONTH FROM $1) AS integer) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION day(date) RETURNS integer AS
			\'SELECT EXTRACT(DAY FROM DATE($1))::integer AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION DAYOFMONTH (timestamp) RETURNS integer AS
			\'SELECT CAST (EXTRACT(DAY FROM $1) AS integer) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION HOUR (timestamp) RETURNS integer AS
			\'SELECT CAST (EXTRACT(HOUR FROM $1) AS integer) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION DATE_FORMAT (timestamp, text) RETURNS text AS \'
			SELECT
				REPLACE(
						REPLACE($2, \'\'%m\'\', to_char($1, \'\'MM\'\')),
				\'\'%d\'\', to_char($1, \'\'DD\'\')) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION TO_DAYS (timestamp) RETURNS integer AS
			\'SELECT DATE_PART(\'\'DAY\'\', $1 - \'\'0001-01-01bc\'\')::integer AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION CONCAT (text, text) RETURNS text AS
			\'SELECT $1 || $2 AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION INSTR (text, text) RETURNS integer AS
			\'SELECT POSITION($2 in $1) AS result\'
		LANGUAGE \'sql\';');

		$this->db->query('', '
		CREATE OR REPLACE FUNCTION bool_not_eq_int (boolean, integer) RETURNS boolean AS
			\'SELECT CAST($1 AS integer) != $2 AS result\'
		LANGUAGE \'sql\';');
	}

	public function create_operators()
	{
		$this->db->query('', '
		CREATE OPERATOR + (PROCEDURE = add_num_text, LEFTARG = text, RIGHTARG = integer);');

		$this->db->query('', '
		CREATE OPERATOR != (PROCEDURE = bool_not_eq_int, LEFTARG = boolean, RIGHTARG = integer);');
	}
}
