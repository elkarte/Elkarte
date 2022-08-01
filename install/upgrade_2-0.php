<?php

/**
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

/**
 * This class is the core of the upgrade system.
 * Methods starting with "__" (double underscore) are not executed.
 * Each method that contains one or more actions is paired with a method
 * with the same name plus "_title", for example:
 *   - my_new_action
 *   - my_new_action_title
 * Methods whose name ends with "_title" are supposed to return a single
 * string representing the title of the step.
 * Methods containing the actions are supposed to return a multidimensional
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
					// Can only do this once
					if ($this->table->column_exists('{db_prefix}notifications_pref', 'notification_level') === true)
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
							UPDATE {db_prefix}notifications_pref
							SET notification_type = {string:type}
							WHERE notification_level = {int:level}',
								[
									'type' => json_encode([$type]),
									'level' => $level,
								]
							);
						}
						$this->table->remove_column('{db_prefix}notifications_pref', 'notification_level');
					}

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
						require_once(SUBSDIR . '/Admin.subs.php');
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

	public function preparing_languages_title()
	{
		return 'Add support for language editing in the db...';
	}

	public function preparing_languages()
	{
		return array(
			array(
				'debug_title' => 'Adding Language edit table...',
				'function' => function()
				{
					$this->table->create_table('{db_prefix}languages',
						array(
							array('name' => 'language',     'type' => 'varchar', 'size' => 40,  'default' => ''),
							array('name' => 'file',         'type' => 'varchar', 'size' => 40,  'default' => ''),
							array('name' => 'language_key', 'type' => 'varchar', 'size' => 255, 'default' => ''),
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
			array(
				'debug_title' => 'Move privacy policy and agreement...',
				'function' => function()
				{
					if (file_exists(BOARDDIR . '/agreement.txt'))
					{
						// Don't want to bother for the time being with errors, especially during upgrade.
						@rename(BOARDDIR . '/agreement.txt', SOURCEDIR . '/ElkArte/Languages/Agreement/English.txt');
					}
					$agreements = glob(BOARDDIR . '/agreement.*.txt');
					foreach ($agreements as $agreement)
					{
						$matches = [];
						$file = basename($agreement);
						preg_match('~agreement\.(.+)\.txt~', $file, $matches);
						if (isset($matches[1]))
						{
							@rename(BOARDDIR . '/' . $file, SOURCEDIR . '/ElkArte/Languages/Agreement/' . ucfirst($matches[1]) . '.txt');
						}
					}

					if (file_exists(BOARDDIR . '/privacypolicy.txt'))
					{
						// Don't want to bother for the time being with errors, especially during upgrade.
						@rename(BOARDDIR . '/privacypolicy.txt', SOURCEDIR . '/ElkArte/Languages/PrivacyPolicy/English.txt');
					}
					$policies = glob(BOARDDIR . '/privacypolicy.*.txt');
					foreach ($policies as $policy)
					{
						$matches = [];
						$file = basename($policy);
						preg_match('~privacypolicy\.(.+)\.txt~', $file, $matches);
						if (isset($matches[1]))
						{
							@rename(BOARDDIR . '/' . $file, SOURCEDIR . '/ElkArte/Languages/PrivacyPolicy/' . ucfirst($matches[1]) . '.txt');
						}
					}
				}
			),
		);
	}

	public function preparing_openid_title()
	{
		return 'Removing support for openid in the db...';
	}

	public function preparing_openid()
	{
		return array(
			array(
				'debug_title' => 'Dropping column openid...',
				'function' => function()
				{
					if ($this->table->column_exists('{db_prefix}members', 'openid_uri') !== false)
					{
						$this->table->remove_column('{db_prefix}members', 'openid_uri');
					}

					// Drop openid assoc table
					$this->table->drop_table('{db_prefix}openid_assoc');

					// Remove Settings
					$this->db->query('',
						'DELETE FROM {db_prefix}settings
						WHERE variable="enableOpenID" 
						    OR variable="dh_keys"'
					);
				}
			)
		);
	}

	public function preparing_custom_search_title()
	{
		return 'Dropping the custom search Index...';
	}

	public function preparing_custom_search()
	{
		return array(
			array(
				'debug_title' => 'Removing old hash custom search index...',
				'function' => function()
				{
					global $modSettings;

					// Drop the custom index if it exists.  The way the id_word value in text2words is
					// calculated changed in 2.0, there is no conversion, the index must be rebuilt and
					// doing that as part of the upgrade could take a long time.
					$this->table->drop_table('{db_prefix}log_search_words');

					updateSettings(array(
						'search_custom_index_config' => '',
						'search_custom_index_resume' => '',
					));

					// Go back to the default search method if they were using custom
					if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'custom')
					{
						updateSettings(array(
							'search_index' => '',
						));
					}
				}
			)
		);
	}

	public function preparing_avatars_title()
	{
		return 'Moving attachment style avatars to custom avatars...';
	}

	public function preparing_avatars()
	{
		return array(
			array(
				'debug_title' => 'Moving attachment avatars to custom avatars location...',
				'function' => function()
				{
					global $modSettings;

					// Get/Set the custom avatar location, the upgrade script checks for existence and access
					$custom_avatar_dir = !empty($modSettings['custom_avatar_dir']) ? $modSettings['custom_avatar_dir'] : BOARDDIR . '/avatars_user';

					// Perhaps we have a smart admin, and they were using a custom dir
					if ($custom_avatar_dir !== BOARDDIR . '/avatars_user')
					{
						if (!file_exists($custom_avatar_dir . '/index.php'))
						{
							@rename(BOARDDIR . '/avatars_user/index.php', $custom_avatar_dir . '/index.php');
						}
						else
						{
							@unlink(BOARDDIR . '/avatars_user/index.php');
						}

						// Attempt to delete the default directory to avoid confusion
						@rmdir(BOARDDIR . '/avatars_user');
					}

					// Find and move
					// @todo should this be done in a loop? Of course, but how?
					$request = $this->db->query('', '
						SELECT 
							id_attach, id_folder, id_member, filename, file_hash
						FROM {db_prefix}attachments
						WHERE attachment_type = {int:attachment_type}
							AND id_member > {int:guest_id_member}',
						array(
							'attachment_type' => 0,
							'guest_id_member' => 0,
						)
					);
					require_once(SUBSDIR . '/Attachments.subs.php');
					$updatedAvatars = [];
					while ($row = $this->db->fetch_assoc($request))
					{
						$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

						if (@rename($filename, $custom_avatar_dir . '/' . $row['filename']))
						{
							$updatedAvatars[] = $row['id_attach'];
						}
					}
					$this->db->free_result($request);
					if (!empty($updatedAvatars))
					{
						$this->db->query('', '
							UPDATE {db_prefix}attachments
							SET 
								attachment_type = {int:attachment_type},
								file_hash = ""
							WHERE id_attach IN ({array_int:updated_avatars})',
							array(
								'updated_avatars' => $updatedAvatars,
								'attachment_type' => 1,
							)
						);
					}
				}
			)
		);
	}

	public function migrate_theme_settings()
	{
		return array(
			array(
				'debug_title' => 'Moving settings that are now site vs theme dependant...',
				'function' => function()
				{
					$moved = array('show_modify', 'show_user_images', 'hide_post_group');

					$request = $this->db->query('', '
						SELECT 
							variable, value
						FROM {db_prefix}themes
						WHERE variable IN({array_string:moved})
							AND id_member = 0
							AND id_theme = 1',
						array(
							'moved' => $moved,
						)
					);
					$inserts = array();
					while ($row = $this->db->fetch_assoc($request))
					{
						$inserts[] = array($row['variable'], $row['value']);
					}
					$this->db->free_result($request);
					$this->db->insert('replace',
						'{db_prefix}settings',
						array('variable' => 'string', 'value' => 'string'),
						$inserts,
						array('variable')
					);
				}
			)
		);
	}

	public function migrate_session_settings()
	{
		return array(
			array(
				'debug_title' => 'Increase DB space for session data ...',
				'function' => function () {
					$this->table->change_column('{db_prefix}log_online',
						'session',
						array(
							'type' => 'varchar',
							'size' => 128,
							'default' => ''
						)
					);
					$this->table->change_column('{db_prefix}log_errors',
						'session',
						array(
							'type' => 'varchar',
							'size' => 128,
							'default' => '                                                                ',
						)
					);
					$this->table->change_column('{db_prefix}sessions',
						'session_id',
						array(
							'type' => 'varchar',
							'size' => 128,
							'default' => ''
						)
					);
				}
			)
		);
	}

	public function migrate_badbehavior_settings()
	{
		return array(
			array(
				'debug_title' => 'Drop Table log_badbehavior ...',
				'function' => function () {
					$this->table->drop_table('{db_prefix}log_badbehavior');
				}
			)
		);
	}

	public function preparing_board_oldposts()
	{
		return array(
			array(
				'debug_title' => 'Add board based old post warning ...',
				'function' => function () {
					$this->table->add_column('{db_prefix}boards',
						array(
							'name' => 'old_posts',
							'type' => 'tinyint',
							'size' => 4,
							'default' => 0
						),
						array(),
						'ignore'
					);
				}
			)
		);
	}
}
