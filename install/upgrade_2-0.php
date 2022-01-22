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
 *         'function' => function() { // Code },
 *     ),
 *     [...],
 * );
 */
class UpgradeInstructions_upgrade_2_0
{
	protected $db = null;
	protected $table = null;

	public function __construct($db, $table)
	{
		$this->db = $db;
		$this->table = $table;
	}

	public function migrate_notifications_to_types_title()
	{
		return 'Adapt notifications to 2.0...';
	}

	public function migrate_notifications_to_types()
	{
		return array(
			array(
				'debug_title' => 'Changing notifications levels to types...',
				'function' => function()
				{
					$this->table->add_column('{db_prefix}notifications_pref',
						array('name' => 'notification_type', 'type' => 'text')
					);
					foreach ([
						'none' => 0,
						'notification' => 1,
						'email' => 2,
						'emaildaily' => 3,
						'emailweekly' => 4
					] as $type => $level)
					{
						$this->db->fetchQuery('
							UPDATE {dbPrefix}notifications_pref
							SET notification_type = {string:type}
							WHERE notification_level = {int:level}',
							[
								'type' => json_encode([$type]),
								'level' => $level,
							]
						);
					}
					$this->table->remove_column('{db_prefix}notifications_pref', 'notification_level');

					updateSettings(array(
						'notification_methods' => serialize([
							'buddy' => [
								'notification' => "1",
								'email' => "1",
								'emaildaily' => "1",
								'emailweekly' => "1"
							],
							'likemsg' => [
								'notification' => "1"
							],
							"mentionmem" => [
								"notification" => "1",
								"email" => "1",
								"emaildaily" => "1",
								"emailweekly" => "1",
							],
							"quotedmem" => [
								"notification" => "1",
								"email" => "1",
								"emaildaily" => "1",
								"emailweekly" => "1"
							]
						])));
				}
			)
		);
	}

	public function tweak_modules_support_title()
	{
		return 'Tweak modules...';
	}

	public function tweak_modules_support()
	{
		return array(
			array(
				'debug_title' => 'Converts settings to modules...',
				'function' => function()
				{
					global $modSettings;

					if (!empty($modSettings['drafts_enabled']))
					{
						enableModules('drafts', array('post', 'display', 'profile', 'personalmessage'));
						\ElkArte\Hooks::instance()->enableIntegration('\\ElkArte\\DraftsIntegrate');
					}

					\ElkArte\Hooks::instance()->enableIntegration('\\ElkArte\\UserNotificationIntegrate');
					\ElkArte\Hooks::instance()->enableIntegration('\\ElkArte\\IlaIntegrate');
					\ElkArte\Hooks::instance()->enableIntegration('\\ElkArte\\EmojiIntegrate');
				}
			)
		);
	}

	public function preparing_postbyemail_title()
	{
		return 'Performing updates to post-by-email handling...';
	}

	public function preparing_postbyemail()
	{
		return array(
			array(
				'debug_title' => 'Cleanup postby_emails...',
				'function' => function()
				{
					// Remove any improper data
					$this->db->query('',
						'DELETE FROM {db_prefix}postby_emails
						WHERE length(id_email) < 35'
					);
				}
			),
		);
	}

	public function preparing_languages_title()
	{
		return 'Add support for language editing in the db...';
	}

	public function preparing_languages()
	{
		return array(
			array(
				'debug_title' => 'Cleanup postby_emails...',
				'function' => function()
				{
					$this->table->create_table('{db_prefix}languages',
						array(
							array('name' => 'language',     'type' => 'string', 'size' => 40,  'default' => ''),
							array('name' => 'file',         'type' => 'string', 'size' => 40,  'default' => ''),
							array('name' => 'language_key', 'type' => 'string', 'size' => 255, 'default' => ''),
							array('name' => 'value',        'type' => 'text'),
						),
						array(
							array('name' => 'id_lang', 'columns' => array('language', 'file'), 'type' => 'primary'),
						),
						array(),
						'ignore'
					);
				}
			),
		);
	}
}
