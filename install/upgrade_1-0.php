<?php

/**
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

/**
 * This class is the core of the upgrade system.
 * Methods starting with "__" (double underscore) are not executed.
 * Each method that contains one or more actions is paired with a method
 * with the same name plus "_title", for example:
 *   - my_new_action
 *   - my_new_action_title
 * Methods whose name ends with "_title" are supposed to return a single
 * string representing the title of the step.
 * Methods containing the actions are supposed to return a multidimentional
 * array with the following structure:
 * array(
 *     array(
 *         'debug_title' => 'A string representing a title shown when debugging',
 *         'function' => function($db, $db_table) { // Code },
 *     ),
 *     [...],
 * );
 */
class UpgradeInstructions_upgrade_1_0
{
	protected $db = null;
	protected $table = null;

	public function __construct($db, $table)
	{
		$this->db = $db;
		return $this->table = $table;
	}

	public function changes_101_title()
	{
		return 'Fixes from 1.0.1...';
	}

	public function changes_101()
	{
		return array(
			array(
				'debug_title' => 'Adding new column to message_likes...',
				'function' => function($db, $db_table)
				{
					$db_table->add_column('{db_prefix}message_likes',
						array(
							'name' => 'like_timestamp',
							'type' => 'int',
							'size' => 10,
							'unsigned' => true,
							'default' => 0,
						),
						array(),
						'ignore'
					);
				}
			),
			array(
				'debug_title' => 'More space for email filters...',
				'function' => function($db, $db_table)
				{
					$db_table->add_column('{db_prefix}postby_emails_filters',
						array(
							'name' => 'filter_style',
							'type' => 'char',
							'size' => 10,
							'default' => '',
						),
						array(),
						'ignore'
					);
				}
			),
			array(
				'debug_title' => 'Possible wrong type for mail_queue...',
				'function' => function($db, $db_table)
				{
					$db_table->add_column('{db_prefix}mail_queue',
						array(
							'name' => 'message_id',
							'type' => 'varchar',
							'size' => 12,
							'default' => '',
						),
						array(),
						'ignore'
					);
				}
			)
		);
	}

	public function changes_102_title()
	{
		return 'Changes for 1.0.2...';
	}

	public function changes_102()
	{
		return array(
			array(
				'debug_title' => 'Remove unused avatar permissions...',
				'function' => function($db, $db_table)
				{
					global $modSettings;

					$db->query('', '
						DELETE FROM {db_prefix}permissions
						WHERE permission = \'profile_upload_avatar\'',
						array()
					);
					$db->query('', '
						DELETE FROM {db_prefix}permissions
						WHERE permission = \'profile_remote_avatar\'',
						array()
					);
					$db->query('', '
						DELETE FROM {db_prefix}permissions
						WHERE permission = \'profile_gravatar\'',
						array()
					);

					$db->query('', '
						UPDATE {db_prefix}permissions
						SET permission = \'profile_set_avatar\'
						WHERE permission = \'profile_server_avatar\'',
						array()
					);

					$db->query('', '
						UPDATE {db_prefix}settings
						SET value = {string:value}
						WHERE variable = {string:variable}',
						array(
							'value' => $modSettings['avatar_max_height_external'],
							'variable' => 'avatar_max_height'
						)
					);

					$db->query('', '
						UPDATE {db_prefix}settings
						SET value = {string:value}
						WHERE variable = {string:variable}',
						array(
							'value' => $modSettings['avatar_max_width_external'],
							'variable' => 'avatar_max_width'
						)
					);

					updateSettings(array(
						'avatar_stored_enabled' => '1',
						'avatar_external_enabled' => '1',
						'avatar_gravatar_enabled' => '1',
						'avatar_upload_enabled' => '1'
					));
				}
			),
		);
	}

	public function changes_104_title()
	{
		return 'Changes for 1.0.4...';
	}

	public function changes_104()
	{
		return array(
			array(
				'debug_title' => 'Update to new package server...',
				'function' => function($db, $db_table)
				{
					$db->query('', '
						UPDATE {db_prefix}package_servers
						SET url = {string:value}
						WHERE name = {string:name}',
						array(
							'value' => 'http://addons.elkarte.net/package.json',
							'name' => 'ElkArte Third-party Add-ons Site'
						)
					);
				}
			)
		);
	}
}
