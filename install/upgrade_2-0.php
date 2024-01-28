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

	public function migrate_theme_settings_title()
	{
		return 'Moving theme settings that are now site settings...';
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

	public function migrate_session_settings_title()
	{
		return 'Updating session data to provide more room...';
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

	public function migrate_badbehavior_settings_title()
	{
		return 'Removing bad behavior log...';
	}

	public function migrate_badbehavior_settings()
	{
		return array(
			array(
				'debug_title' => 'Drop Table log_badbehavior ...',
				'function' => function () {
					$this->table->drop_table('{db_prefix}log_badbehavior');
				}
			),
			array(
				'debug_title' => 'Remove of badbehavior settings ...',
				'function' => function () {
					removeSettings(
						array('badbehavior_enabled', 'badbehavior_logging', 'badbehavior_ip_wl',
							  'badbehavior_ip_wl_desc', 'badbehavior_url_wl', 'badbehavior_url_wl_desc')
					);
				}
			)
		);
	}

	public function preparing_board_oldposts_title()
	{
		return 'Adding old post warning by board functionality...';
	}

	public function preparing_board_oldposts()
	{
		return array(
			array(
				'debug_title' => 'Adding new old_column to board table...',
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

	public function preparing_smiley_title()
	{
		return 'Update smiley support to include emoji...';
	}

	public function preparing_smiley()
	{
		return array(
			array(
				'debug_title' => 'Remove extension from existing smiley filenames ...',
				'function' => function () {
					$request = $this->db->query('', '
						SELECT 
							filename
						FROM {db_prefix}smileys',
						array()
					);
					$inserts = array();
					while ($row = $this->db->fetch_assoc($request))
					{
						$inserts[] = array(
							'newname' => pathinfo($row['filename'], PATHINFO_FILENAME),
							'oldname' => $row['filename']
						);
					}
					$this->db->free_result($request);
					foreach ($inserts as $insert)
					{
						$this->db->query('', '
							UPDATE {db_prefix}smileys
							SET filename = {string:newname}
							WHERE filename = {string:oldname}',
							array(
								'newname' => $insert['newname'],
								'oldname' => $insert['oldname'],
							)
						);
					}
				}
			),
			array(
				'debug_title' => 'Add new smiley extension values...',
				'function' => function () {
					global $modSettings;

					// Not defined is easy, but unlikely
					if (empty($modSettings['smiley_sets_known']))
					{
						$smiley_sets_known = 'default';
						$smiley_sets_names = 'Default';
						$smiley_sets_extensions = 'svg';
					}
					else
					{
						// Set up the extensions modSettings based on existing sets
						require_once(SUBSDIR . '/Smileys.subs.php');
						$smiley_sets_extensions = setSmileyExtensionArray();

						$sets = explode(',', $modSettings['smiley_sets_known']);
						$key = array_search('default', $sets, true);

						// default does not exist, easy, add it as the first
						if (empty($key))
						{
							$smiley_sets_known = 'default,' . $modSettings['smiley_sets_known'];
							$smiley_sets_names = 'Default' . "\n" . $modSettings['smiley_sets_names'];
							$smiley_sets_extensions = 'svg,' . $smiley_sets_extensions;
						}
						// Otherwise, force the extension to svg for the default set
						else
						{
							$smiley_sets_known = $modSettings['smiley_sets_known'];
							$smiley_sets_names = $modSettings['smiley_sets_names'];
							$smiley_sets_extensions = explode(',', $smiley_sets_extensions);
							$smiley_sets_extensions[$key] = 'svg';
							$smiley_sets_extensions = implode(',', $smiley_sets_extensions);
						}
					}

					updateSettings(array(
						'smiley_sets_known' => $smiley_sets_known,
						'smiley_sets_names' => $smiley_sets_names,
						'smiley_sets_extensions' => $smiley_sets_extensions,
					));
				}
			),
			array(
				'debug_title' => 'Add new/missing smiley codes ...',
				'function' => function () {
					global $txt;

					// Definitions of all emoji smiles we use in the editor
					$emojiSmile = array(
						array(':smiley:', 'smiley', $txt['default_smiley_smiley'], 0, 2),
						array(':wink:', 'wink', $txt['default_wink_smiley'], 0, 2),
						array(':cheesy:', 'cheesy', $txt['default_cheesy_smiley'], 0, 2),
						array(':grin:', 'grin', $txt['default_grin_smiley'], 0, 2),
						array(':angry:', 'angry', $txt['default_angry_smiley'], 0, 2),
						array(':sad:', 'sad', $txt['default_sad_smiley'], 0, 2),
						array(':shocked:', 'shocked', $txt['default_shocked_smiley'], 0, 2),
						array(':cool:', 'cool', $txt['default_cool_smiley'], 0, 2),
						array(':huh:', 'huh', $txt['default_huh_smiley'], 0, 2),
						array(':rolleyes:', 'rolleyes', $txt['default_roll_eyes_smiley'], 0, 2),
						array(':tongue:', 'tongue', $txt['default_tongue_smiley'], 0, 2),
						array(':embarrassed:', 'embarrassed', $txt['default_embarrassed_smiley'], 0, 2),
						array(':zipper_mouth:', 'lipsrsealed', $txt['default_lips_sealed_smiley'], 0, 2),
						array(':undecided:', 'undecided', $txt['default_undecided_smiley'], 0, 2),
						array(':kiss:', 'kiss', $txt['default_kiss_smiley'], 0, 2),
						array(':cry:', 'cry', $txt['default_cry_smiley'], 0, 2),
						array(':evil:', 'evil', $txt['default_evil_smiley'], 0, 2),
						array(':laugh:', 'laugh', $txt['default_laugh_smiley'], 0, 2),
						array(':rotating_light:', 'police', $txt['default_police_smiley'], 0, 2),
						array(':innocent:', 'angel', $txt['default_angel_smiley'], 0, 2),
						array(':thumbsup:', 'thumbsup', $txt['default_thumbup_smiley'], 0, 2),
						array(':thumbsdown:', 'thumbsdown', $txt['default_thumbdown_smiley'], 0, 2),
						array(':skull:', 'skull', $txt['default_skull_smiley'], 0, 2),
						array(':poop:', 'poop', $txt['default_poop_smiley'], 0, 2),
						array(':sweat:', 'sweat', $txt['default_sweat_smiley'], 0, 2),
						array(':heart_eyes:', 'heart', $txt['default_heart_smiley'], 0, 2),
						array(':grimacing:', 'grimacing', $txt['default_grimace_smiley'], 0, 2),
						array(':nerd:', 'nerd', $txt['default_nerd_smiley'], 0, 2),
						array(':head_bandage:', 'clumsy', $txt['default_clumsy_smiley'], 0, 2),
						array(':clown:', 'clown', $txt['default_clown_smiley'], 0, 2),
						array(':partying_face', 'party', $txt['default_party_smiley'], 0, 2),
						array(':zany_face:', 'zany', $txt['default_wild_smiley'], 0, 2),
						array(':shushing_face:', 'shh', $txt['default_shh_smiley'], 0, 2),
						array(':face_vomiting:', 'vomit', $txt['default_vomit_smiley'], 0, 2)
					);
					$codes = array();
					$inserts = array();
					// Grab all existing codes
					$request = $this->db->query('', '
						SELECT 
							code
						FROM {db_prefix}smileys',
						array()
					);
					while ($row = $this->db->fetch_assoc($request))
					{
						$codes[$row['code']] = $row['code'];
					}
					// Add any that are missing, to minimize admin trauma, they are added to the popup, unordered
					foreach ($emojiSmile as $entry)
					{
						if (isset($codes[$entry[0]]))
						{
							continue;
						}

						$inserts[] = $entry;
					}
					$this->db->insert('ignore',
						'{db_prefix}smileys',
						array('code' => 'string', 'filename' => 'string', 'description' => 'string', 'smiley_order' => 'int', 'hidden' => 'int'),
						$inserts,
						array('id_smiley')
					);
				}
			),
			array(
				'debug_title' => 'Drop user smiley set column ...',
				'function' => function () {
					$this->table->remove_column('{db_prefix}members', 'smiley_set');
				}
			)
		);
	}

	public function migrate_misc_settings_title()
	{
		return 'Updating misc data ...';
	}

	public function migrate_misc_session_settings()
	{
		return array(
			array(
				'debug_title' => 'Removing / Changing misc modSetting data ...',
				'function' => function () {
					removeSettings(
						array('visual_verification_type', 'visual_verification_num_chars')
					);
				}
			)
		);
	}


	public function migrate_packageserver_settings_title()
	{
		return 'Removing package servers table...';
	}

	public function migrate_packageserver_settings()
	{
		return array(
			array(
				'debug_title' => 'Drop Table package_servers ...',
				'function' => function () {
					$this->table->drop_table('{db_prefix}package_servers');
				}
			),
			array(
				'debug_title' => 'Add of package servers settings ...',
				'function' => function () {
					updateSettings(array(
						'elkarte_addon_server' => 'https://elkarte.github.io/addons/package.json',
					));
				}
			)
		);
	}
}
