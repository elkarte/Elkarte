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
 * @version 1.1 beta 4
 *
 */

class InstallInstructions_install_1_1
{
	protected $db = null;
	protected $table = null;

	public function __construct($db, $table)
	{
		$this->db = $db;
		return $this->table = $table;
	}

	public function table_antispam_questions()
	{
		return $this->table->db_create_table('{db_prefix}antispam_questions',
			array(
				array('name' => 'id_question', 'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'auto' => true),
				array('name' => 'question',    'type' => 'text'),
				array('name' => 'answer',      'type' => 'text'),
				array('name' => 'language',    'type' => 'varchar', 'default' => '', 'size' => 50),
			),
			array(
				array('name' => 'id_question', 'columns' => array('id_question'), 'type' => 'primary'),
				array('name' => 'language',    'columns' => array('language(30)'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_approval_queue()
	{
		return $this->table->db_create_table('{db_prefix}approval_queue',
			array(
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_attach', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_event',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			),
			array(),
			array(),
			'ignore'
		);
	}

	public function table_attachments()
	{
		return $this->table->db_create_table('{db_prefix}attachments',
			array(
				array('name' => 'id_attach',       'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_thumb',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',          'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',       'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_folder',       'type' => 'tinyint', 'size' => 3, 'default' => 1),
				array('name' => 'attachment_type', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'filename',        'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'file_hash',       'type' => 'varchar', 'default' => '', 'size' => 40),
				array('name' => 'fileext',         'type' => 'varchar', 'default' => '', 'size' => 8),
				array('name' => 'size',            'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'downloads',       'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'width',           'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'height',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'mime_type',       'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'approved',        'type' => 'tinyint', 'size' => 3, 'default' => 1),
			),
			array(
				array('name' => 'id_attach',       'columns' => array('id_attach'), 'type' => 'primary'),
				array('name' => 'id_member',       'columns' => array('id_member', 'id_attach'), 'type' => 'unique'),
				array('name' => 'id_msg',          'columns' => array('id_msg'), 'type' => 'key'),
				array('name' => 'attachment_type', 'columns' => array('attachment_type'), 'type' => 'key'),
				array('name' => 'id_thumb',        'columns' => array('id_thumb'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_ban_groups()
	{
		return $this->table->db_create_table('{db_prefix}ban_groups',
			array(
				array('name' => 'id_ban_group',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'name',            'type' => 'varchar', 'default' => '', 'size' => 20),
				array('name' => 'ban_time',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'expire_time',     'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => true),
				array('name' => 'cannot_access',   'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'cannot_register', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'cannot_post',     'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'cannot_login',    'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'reason',          'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'notes',           'type' => 'text'),
			),
			array(
				array('name' => 'id_ban_group', 'columns' => array('id_ban_group'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_ban_items()
	{
		return $this->table->db_create_table('{db_prefix}ban_items',
			array(
				array('name' => 'id_ban',        'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_ban_group',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low1',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high1',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low2',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high2',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low3',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high3',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low4',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high4',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low5',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high5',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low6',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high6',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low7',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high7',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_low8',       'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip_high8',      'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'hostname',      'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'email_address', 'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'id_member',     'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'hits',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_ban',       'columns' => array('id_ban'), 'type' => 'primary'),
				array('name' => 'id_ban_group', 'columns' => array('id_ban_group'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_board_permissions()
	{
		return $this->table->db_create_table('{db_prefix}board_permissions',
			array(
				array('name' => 'id_group',   'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'id_profile', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'permission', 'type' => 'varchar', 'default' => '', 'size' => 30),
				array('name' => 'add_deny',   'type' => 'tinyint', 'size' => 4, 'default' => 1),
			),
			array(
				array('name' => 'id_group', 'columns' => array('id_group', 'id_profile', 'permission'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_board_permissions()
	{
		return $this->db->insert('ignore',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string-30'),
			array(
				array(-1, 1, 'poll_view'),
				array(0, 1, 'remove_own'),
				array(0, 1, 'like_posts'),
				array(0, 1, 'lock_own'),
				array(0, 1, 'mark_any_notify'),
				array(0, 1, 'mark_notify'),
				array(0, 1, 'modify_own'),
				array(0, 1, 'poll_add_own'),
				array(0, 1, 'poll_edit_own'),
				array(0, 1, 'poll_lock_own'),
				array(0, 1, 'poll_post'),
				array(0, 1, 'poll_view'),
				array(0, 1, 'poll_vote'),
				array(0, 1, 'post_attachment'),
				array(0, 1, 'post_new'),
				array(0, 1, 'post_draft'),
				array(0, 1, 'postby_email'),
				array(0, 1, 'post_autosave_draft'),
				array(0, 1, 'post_reply_any'),
				array(0, 1, 'post_reply_own'),
				array(0, 1, 'post_unapproved_topics'),
				array(0, 1, 'post_unapproved_replies_any'),
				array(0, 1, 'post_unapproved_replies_own'),
				array(0, 1, 'post_unapproved_attachments'),
				array(0, 1, 'delete_own'),
				array(0, 1, 'report_any'),
				array(0, 1, 'send_topic'),
				array(0, 1, 'view_attachments'),
				array(2, 1, 'like_posts'),
				array(2, 1, 'moderate_board'),
				array(2, 1, 'post_new'),
				array(2, 1, 'post_draft'),
				array(2, 1, 'post_autosave_draft'),
				array(2, 1, 'post_reply_own'),
				array(2, 1, 'post_reply_any'),
				array(2, 1, 'post_unapproved_topics'),
				array(2, 1, 'post_unapproved_replies_any'),
				array(2, 1, 'post_unapproved_replies_own'),
				array(2, 1, 'post_unapproved_attachments'),
				array(2, 1, 'poll_post'),
				array(2, 1, 'poll_add_any'),
				array(2, 1, 'poll_remove_any'),
				array(2, 1, 'poll_view'),
				array(2, 1, 'poll_vote'),
				array(2, 1, 'poll_lock_any'),
				array(2, 1, 'poll_edit_any'),
				array(2, 1, 'report_any'),
				array(2, 1, 'lock_own'),
				array(2, 1, 'send_topic'),
				array(2, 1, 'mark_any_notify'),
				array(2, 1, 'mark_notify'),
				array(2, 1, 'delete_own'),
				array(2, 1, 'modify_own'),
				array(2, 1, 'make_sticky'),
				array(2, 1, 'lock_any'),
				array(2, 1, 'remove_any'),
				array(2, 1, 'move_any'),
				array(2, 1, 'merge_any'),
				array(2, 1, 'split_any'),
				array(2, 1, 'delete_any'),
				array(2, 1, 'modify_any'),
				array(2, 1, 'approve_posts'),
				array(2, 1, 'post_attachment'),
				array(2, 1, 'view_attachments'),
				array(3, 1, 'like_posts'),
				array(3, 1, 'moderate_board'),
				array(3, 1, 'post_new'),
				array(3, 1, 'post_draft'),
				array(3, 1, 'post_autosave_draft'),
				array(3, 1, 'post_reply_own'),
				array(3, 1, 'post_reply_any'),
				array(3, 1, 'post_unapproved_topics'),
				array(3, 1, 'post_unapproved_replies_any'),
				array(3, 1, 'post_unapproved_replies_own'),
				array(3, 1, 'post_unapproved_attachments'),
				array(3, 1, 'poll_post'),
				array(3, 1, 'poll_add_any'),
				array(3, 1, 'poll_remove_any'),
				array(3, 1, 'poll_view'),
				array(3, 1, 'poll_vote'),
				array(3, 1, 'poll_lock_any'),
				array(3, 1, 'poll_edit_any'),
				array(3, 1, 'report_any'),
				array(3, 1, 'lock_own'),
				array(3, 1, 'send_topic'),
				array(3, 1, 'mark_any_notify'),
				array(3, 1, 'mark_notify'),
				array(3, 1, 'delete_own'),
				array(3, 1, 'modify_own'),
				array(3, 1, 'make_sticky'),
				array(3, 1, 'lock_any'),
				array(3, 1, 'remove_any'),
				array(3, 1, 'move_any'),
				array(3, 1, 'merge_any'),
				array(3, 1, 'split_any'),
				array(3, 1, 'delete_any'),
				array(3, 1, 'modify_any'),
				array(3, 1, 'approve_posts'),
				array(3, 1, 'post_attachment'),
				array(3, 1, 'view_attachments'),
				array(-1, 2, 'poll_view'),
				array(0, 2, 'remove_own'),
				array(0, 2, 'like_posts'),
				array(0, 2, 'lock_own'),
				array(0, 2, 'mark_any_notify'),
				array(0, 2, 'mark_notify'),
				array(0, 2, 'modify_own'),
				array(0, 2, 'poll_view'),
				array(0, 2, 'poll_vote'),
				array(0, 2, 'post_attachment'),
				array(0, 2, 'post_new'),
				array(0, 2, 'postby_email'),
				array(0, 2, 'post_draft'),
				array(0, 2, 'post_autosave_draft'),
				array(0, 2, 'post_reply_any'),
				array(0, 2, 'post_reply_own'),
				array(0, 2, 'post_unapproved_topics'),
				array(0, 2, 'post_unapproved_replies_any'),
				array(0, 2, 'post_unapproved_replies_own'),
				array(0, 2, 'post_unapproved_attachments'),
				array(0, 2, 'delete_own'),
				array(0, 2, 'report_any'),
				array(0, 2, 'send_topic'),
				array(0, 2, 'view_attachments'),
				array(2, 2, 'like_posts'),
				array(2, 2, 'moderate_board'),
				array(2, 2, 'post_new'),
				array(2, 2, 'post_draft'),
				array(2, 2, 'post_autosave_draft'),
				array(2, 2, 'post_reply_own'),
				array(2, 2, 'post_reply_any'),
				array(2, 2, 'post_unapproved_topics'),
				array(2, 2, 'post_unapproved_replies_any'),
				array(2, 2, 'post_unapproved_replies_own'),
				array(2, 2, 'post_unapproved_attachments'),
				array(2, 2, 'poll_post'),
				array(2, 2, 'poll_add_any'),
				array(2, 2, 'poll_remove_any'),
				array(2, 2, 'poll_view'),
				array(2, 2, 'poll_vote'),
				array(2, 2, 'poll_lock_any'),
				array(2, 2, 'poll_edit_any'),
				array(2, 2, 'report_any'),
				array(2, 2, 'lock_own'),
				array(2, 2, 'send_topic'),
				array(2, 2, 'mark_any_notify'),
				array(2, 2, 'mark_notify'),
				array(2, 2, 'delete_own'),
				array(2, 2, 'modify_own'),
				array(2, 2, 'make_sticky'),
				array(2, 2, 'lock_any'),
				array(2, 2, 'remove_any'),
				array(2, 2, 'move_any'),
				array(2, 2, 'merge_any'),
				array(2, 2, 'split_any'),
				array(2, 2, 'delete_any'),
				array(2, 2, 'modify_any'),
				array(2, 2, 'approve_posts'),
				array(2, 2, 'post_attachment'),
				array(2, 2, 'view_attachments'),
				array(3, 2, 'like_posts'),
				array(3, 2, 'moderate_board'),
				array(3, 2, 'post_new'),
				array(3, 2, 'post_draft'),
				array(3, 2, 'post_autosave_draft'),
				array(3, 2, 'post_reply_own'),
				array(3, 2, 'post_reply_any'),
				array(3, 2, 'post_unapproved_topics'),
				array(3, 2, 'post_unapproved_replies_any'),
				array(3, 2, 'post_unapproved_replies_own'),
				array(3, 2, 'post_unapproved_attachments'),
				array(3, 2, 'poll_post'),
				array(3, 2, 'poll_add_any'),
				array(3, 2, 'poll_remove_any'),
				array(3, 2, 'poll_view'),
				array(3, 2, 'poll_vote'),
				array(3, 2, 'poll_lock_any'),
				array(3, 2, 'poll_edit_any'),
				array(3, 2, 'report_any'),
				array(3, 2, 'lock_own'),
				array(3, 2, 'send_topic'),
				array(3, 2, 'mark_any_notify'),
				array(3, 2, 'mark_notify'),
				array(3, 2, 'delete_own'),
				array(3, 2, 'modify_own'),
				array(3, 2, 'make_sticky'),
				array(3, 2, 'lock_any'),
				array(3, 2, 'remove_any'),
				array(3, 2, 'move_any'),
				array(3, 2, 'merge_any'),
				array(3, 2, 'split_any'),
				array(3, 2, 'delete_any'),
				array(3, 2, 'modify_any'),
				array(3, 2, 'approve_posts'),
				array(3, 2, 'post_attachment'),
				array(3, 2, 'view_attachments'),
				array(-1, 3, 'poll_view'),
				array(0, 3, 'remove_own'),
				array(0, 3, 'lock_own'),
				array(0, 3, 'like_posts'),
				array(0, 3, 'mark_any_notify'),
				array(0, 3, 'mark_notify'),
				array(0, 3, 'modify_own'),
				array(0, 3, 'poll_view'),
				array(0, 3, 'poll_vote'),
				array(0, 3, 'post_attachment'),
				array(0, 3, 'post_reply_any'),
				array(0, 3, 'post_reply_own'),
				array(0, 3, 'post_unapproved_replies_any'),
				array(0, 3, 'post_unapproved_replies_own'),
				array(0, 3, 'post_unapproved_attachments'),
				array(0, 3, 'delete_own'),
				array(0, 3, 'report_any'),
				array(0, 3, 'send_topic'),
				array(0, 3, 'view_attachments'),
				array(2, 3, 'like_posts'),
				array(2, 3, 'moderate_board'),
				array(2, 3, 'post_new'),
				array(2, 3, 'post_draft'),
				array(2, 3, 'post_autosave_draft'),
				array(2, 3, 'post_reply_own'),
				array(2, 3, 'post_reply_any'),
				array(2, 3, 'post_unapproved_topics'),
				array(2, 3, 'post_unapproved_replies_any'),
				array(2, 3, 'post_unapproved_replies_own'),
				array(2, 3, 'post_unapproved_attachments'),
				array(2, 3, 'poll_post'),
				array(2, 3, 'poll_add_any'),
				array(2, 3, 'poll_remove_any'),
				array(2, 3, 'poll_view'),
				array(2, 3, 'poll_vote'),
				array(2, 3, 'poll_lock_any'),
				array(2, 3, 'poll_edit_any'),
				array(2, 3, 'report_any'),
				array(2, 3, 'lock_own'),
				array(2, 3, 'send_topic'),
				array(2, 3, 'mark_any_notify'),
				array(2, 3, 'mark_notify'),
				array(2, 3, 'delete_own'),
				array(2, 3, 'modify_own'),
				array(2, 3, 'make_sticky'),
				array(2, 3, 'lock_any'),
				array(2, 3, 'remove_any'),
				array(2, 3, 'move_any'),
				array(2, 3, 'merge_any'),
				array(2, 3, 'split_any'),
				array(2, 3, 'delete_any'),
				array(2, 3, 'modify_any'),
				array(2, 3, 'approve_posts'),
				array(2, 3, 'post_attachment'),
				array(2, 3, 'view_attachments'),
				array(3, 3, 'like_posts'),
				array(3, 3, 'moderate_board'),
				array(3, 3, 'post_new'),
				array(3, 3, 'post_draft'),
				array(3, 3, 'post_autosave_draft'),
				array(3, 3, 'post_reply_own'),
				array(3, 3, 'post_reply_any'),
				array(3, 3, 'post_unapproved_topics'),
				array(3, 3, 'post_unapproved_replies_any'),
				array(3, 3, 'post_unapproved_replies_own'),
				array(3, 3, 'post_unapproved_attachments'),
				array(3, 3, 'poll_post'),
				array(3, 3, 'poll_add_any'),
				array(3, 3, 'poll_remove_any'),
				array(3, 3, 'poll_view'),
				array(3, 3, 'poll_vote'),
				array(3, 3, 'poll_lock_any'),
				array(3, 3, 'poll_edit_any'),
				array(3, 3, 'report_any'),
				array(3, 3, 'lock_own'),
				array(3, 3, 'send_topic'),
				array(3, 3, 'mark_any_notify'),
				array(3, 3, 'mark_notify'),
				array(3, 3, 'delete_own'),
				array(3, 3, 'modify_own'),
				array(3, 3, 'make_sticky'),
				array(3, 3, 'lock_any'),
				array(3, 3, 'remove_any'),
				array(3, 3, 'move_any'),
				array(3, 3, 'merge_any'),
				array(3, 3, 'split_any'),
				array(3, 3, 'delete_any'),
				array(3, 3, 'modify_any'),
				array(3, 3, 'approve_posts'),
				array(3, 3, 'post_attachment'),
				array(3, 3, 'view_attachments'),
				array(-1, 4, 'poll_view'),
				array(0, 4, 'mark_any_notify'),
				array(0, 4, 'mark_notify'),
				array(0, 4, 'poll_view'),
				array(0, 4, 'poll_vote'),
				array(0, 4, 'report_any'),
				array(0, 4, 'send_topic'),
				array(0, 4, 'view_attachments'),
				array(2, 4, 'like_posts'),
				array(2, 4, 'moderate_board'),
				array(2, 4, 'post_new'),
				array(2, 4, 'post_draft'),
				array(2, 4, 'post_autosave_draft'),
				array(2, 4, 'post_reply_own'),
				array(2, 4, 'post_reply_any'),
				array(2, 4, 'post_unapproved_topics'),
				array(2, 4, 'post_unapproved_replies_any'),
				array(2, 4, 'post_unapproved_replies_own'),
				array(2, 4, 'post_unapproved_attachments'),
				array(2, 4, 'poll_post'),
				array(2, 4, 'poll_add_any'),
				array(2, 4, 'poll_remove_any'),
				array(2, 4, 'poll_view'),
				array(2, 4, 'poll_vote'),
				array(2, 4, 'poll_lock_any'),
				array(2, 4, 'poll_edit_any'),
				array(2, 4, 'report_any'),
				array(2, 4, 'lock_own'),
				array(2, 4, 'send_topic'),
				array(2, 4, 'mark_any_notify'),
				array(2, 4, 'mark_notify'),
				array(2, 4, 'delete_own'),
				array(2, 4, 'modify_own'),
				array(2, 4, 'make_sticky'),
				array(2, 4, 'lock_any'),
				array(2, 4, 'remove_any'),
				array(2, 4, 'move_any'),
				array(2, 4, 'merge_any'),
				array(2, 4, 'split_any'),
				array(2, 4, 'delete_any'),
				array(2, 4, 'modify_any'),
				array(2, 4, 'approve_posts'),
				array(2, 4, 'post_attachment'),
				array(2, 4, 'view_attachments'),
				array(3, 4, 'like_posts'),
				array(3, 4, 'moderate_board'),
				array(3, 4, 'post_new'),
				array(3, 4, 'post_draft'),
				array(3, 4, 'post_autosave_draft'),
				array(3, 4, 'post_reply_own'),
				array(3, 4, 'post_reply_any'),
				array(3, 4, 'post_unapproved_topics'),
				array(3, 4, 'post_unapproved_replies_any'),
				array(3, 4, 'post_unapproved_replies_own'),
				array(3, 4, 'post_unapproved_attachments'),
				array(3, 4, 'poll_post'),
				array(3, 4, 'poll_add_any'),
				array(3, 4, 'poll_remove_any'),
				array(3, 4, 'poll_view'),
				array(3, 4, 'poll_vote'),
				array(3, 4, 'poll_lock_any'),
				array(3, 4, 'poll_edit_any'),
				array(3, 4, 'report_any'),
				array(3, 4, 'lock_own'),
				array(3, 4, 'send_topic'),
				array(3, 4, 'mark_any_notify'),
				array(3, 4, 'mark_notify'),
				array(3, 4, 'delete_own'),
				array(3, 4, 'modify_own'),
				array(3, 4, 'make_sticky'),
				array(3, 4, 'lock_any'),
				array(3, 4, 'remove_any'),
				array(3, 4, 'move_any'),
				array(3, 4, 'merge_any'),
				array(3, 4, 'split_any'),
				array(3, 4, 'delete_any'),
				array(3, 4, 'modify_any'),
				array(3, 4, 'approve_posts'),
				array(3, 4, 'post_attachment'),
				array(3, 4, 'view_attachments'),
			),
			array('id_group', 'id_profile', 'permission')
		);
	}

	public function table_boards()
	{
		return $this->table->db_create_table('{db_prefix}boards',
			array(
				array('name' => 'id_board',           'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_cat',             'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'child_level',        'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_parent',          'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'board_order',        'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'id_last_msg',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg_updated',     'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'member_groups',      'type' => 'varchar', 'default' => '-1,0', 'size' => 255),
				array('name' => 'id_profile',         'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 1),
				array('name' => 'name',               'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'description',        'type' => 'text'),
				array('name' => 'num_topics',         'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'num_posts',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'count_posts',        'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'id_theme',           'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'override_theme',     'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'unapproved_posts',   'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'unapproved_topics',  'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'redirect',           'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'deny_member_groups', 'type' => 'varchar', 'default' => '', 'size' => 255),
			),
			array(
				array('name' => 'id_board',       'columns' => array('id_board'), 'type' => 'primary'),
				array('name' => 'categories',     'columns' => array('id_cat', 'id_board'), 'type' => 'unique'),
				array('name' => 'id_parent',      'columns' => array('id_parent'), 'type' => 'key'),
				array('name' => 'id_msg_updated', 'columns' => array('id_msg_updated'), 'type' => 'key'),
				array('name' => 'member_groups',  'columns' => array('member_groups(48)'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_boards()
	{
		return $this->db->insert('ignore',
			'{db_prefix}boards',
			array('id_cat' => 'int', 'board_order' => 'int', 'id_last_msg' => 'int', 'id_msg_updated' => 'int', 'name' => 'string', 'description' => 'string', 'num_topics' => 'int', 'num_posts' => 'int', 'member_groups' => 'string'),
			array(
				array(1, 1, 1, 1, '{$default_board_name}', '{$default_board_description}', 1, 1, '-1,0,2'),
			),
			array('id_board')
		);
	}

	public function table_calendar()
	{
		return $this->table->db_create_table('{db_prefix}calendar',
			array(
				array('name' => 'id_event',   'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'start_date', 'type' => 'date', 'default' => '0001-01-01'),
				array('name' => 'end_date',   'type' => 'date', 'default' => '0001-01-01'),
				array('name' => 'id_board',   'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_topic',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'title',      'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'id_member',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_event',   'columns' => array('id_event'), 'type' => 'primary'),
				array('name' => 'start_date', 'columns' => array('start_date'), 'type' => 'key'),
				array('name' => 'end_date',   'columns' => array('end_date'), 'type' => 'key'),
				array('name' => 'topic',      'columns' => array('id_topic', 'id_member'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_calendar_holidays()
	{
		return $this->table->db_create_table('{db_prefix}calendar_holidays',
			array(
				array('name' => 'id_holiday', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'event_date', 'type' => 'date', 'default' => '0001-01-01'),
				array('name' => 'title',      'type' => 'varchar', 'default' => '', 'size' => 255),
			),
			array(
				array('name' => 'id_holiday', 'columns' => array('id_holiday'), 'type' => 'primary'),
				array('name' => 'event_date', 'columns' => array('event_date'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_calendar_holidays()
	{
		return $this->db->insert('ignore',
			'{db_prefix}calendar_holidays',
			array('title' => 'string', 'event_date' => 'date'),
			array(
				array('New Year\'s', '0004-01-01'),
				array('Christmas', '0004-12-25'),
				array('Valentine\'s Day', '0004-02-14'),
				array('St. Patrick\'s Day', '0004-03-17'),
				array('St. Andrew\'s Day', '0004-11-30'),
				array('April Fools', '0004-04-01'),
				array('Earth Day', '0004-04-22'),
				array('United Nations Day', '0004-10-24'),
				array('Halloween', '0004-10-31'),
				array('Mother\'s Day', '2017-05-14'),
				array('Mother\'s Day', '2018-05-13'),
				array('Mother\'s Day', '2019-05-12'),
				array('Mother\'s Day', '2020-05-10'),
				array('Father\'s Day', '2017-06-18'),
				array('Father\'s Day', '2018-06-17'),
				array('Father\'s Day', '2019-06-16'),
				array('Father\'s Day', '2020-06-21'),
				array('Summer Solstice', '2017-06-20'),
				array('Summer Solstice', '2018-06-21'),
				array('Summer Solstice', '2019-06-21'),
				array('Summer Solstice', '2020-06-20'),
				array('Vernal Equinox', '2017-03-20'),
				array('Vernal Equinox', '2018-03-20'),
				array('Vernal Equinox', '2019-03-20'),
				array('Vernal Equinox', '2020-03-19'),
				array('Winter Solstice', '2017-12-21'),
				array('Winter Solstice', '2018-12-21'),
				array('Winter Solstice', '2019-12-21'),
				array('Winter Solstice', '2020-12-21'),
				array('Autumnal Equinox', '2017-09-22'),
				array('Autumnal Equinox', '2018-09-22'),
				array('Autumnal Equinox', '2019-09-23'),
				array('Autumnal Equinox', '2020-09-22'),
				array('American Independence Day', '0004-07-04'),
				array('Cinco de Mayo', '0004-05-05'),
				array('Flag Day', '0004-06-14'),
				array('Veterans Day', '0004-11-11'),
				array('Groundhog Day', '0004-02-02'),
				array('Thanksgiving', '2017-11-23'),
				array('Thanksgiving', '2018-11-22'),
				array('Thanksgiving', '2019-11-28'),
				array('Thanksgiving', '2020-11-26'),
				array('Memorial Day', '2017-05-29'),
				array('Memorial Day', '2018-05-28'),
				array('Memorial Day', '2019-05-27'),
				array('Memorial Day', '2020-05-25'),
				array('Labor Day', '2017-09-04'),
				array('Labor Day', '2018-09-03'),
				array('Labor Day', '2019-09-02'),
				array('Labor Day', '2020-09-07'),
				array('D-Day', '0004-06-06'),
			),
			array('id_holiday')
		);
	}

	public function table_categories()
	{
		return $this->table->db_create_table('{db_prefix}categories',
			array(
				array('name' => 'id_cat',       'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'auto' => true),
				array('name' => 'cat_order',    'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'name',         'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'can_collapse', 'type' => 'tinyint', 'size' => 1, 'default' => 1),
			),
			array(
				array('name' => 'id_cat', 'columns' => array('id_cat'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_categories()
	{
		return $this->db->insert('ignore',
			'{db_prefix}categories',
			array('cat_order' => 'int', 'name' => 'string', 'can_collapse' => 'int'),
			array(
				array(0, '{$default_category_name}', 1),
			),
			array('id_cat')
		);
	}

	public function table_collapsed_categories()
	{
		return $this->table->db_create_table('{db_prefix}collapsed_categories',
			array(
				array('name' => 'id_cat',   'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_cat', 'columns' => array('id_cat', 'id_member'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_custom_fields()
	{
		return $this->table->db_create_table('{db_prefix}custom_fields',
			array(
				array('name' => 'id_field',        'type' => 'smallint', 'size' => 5, 'auto' => true),
				array('name' => 'col_name',        'type' => 'varchar', 'default' => '', 'size' => 12),
				array('name' => 'field_name',      'type' => 'varchar', 'default' => '', 'size' => 40),
				array('name' => 'field_desc',      'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'field_type',      'type' => 'varchar', 'default' => 'text', 'size' => 8),
				array('name' => 'field_length',    'type' => 'smallint', 'size' => 5, 'default' => 255),
				array('name' => 'field_options',   'type' => 'text'),
				array('name' => 'mask',            'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'rows',            'type' => 'smallint', 'size' => 5, 'default' => 3),
				array('name' => 'cols',            'type' => 'smallint', 'size' => 5, 'default' => 30),
				array('name' => 'show_reg',        'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'show_display',    'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'show_memberlist', 'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'show_profile',    'type' => 'varchar', 'default' => 'forumprofile', 'size' => 20),
				array('name' => 'private',         'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'active',          'type' => 'tinyint', 'size' => 3, 'default' => 1),
				array('name' => 'bbc',             'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'can_search',      'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'default_value',   'type' => 'varchar', 'default' => '', 'size' => 255),
				array('name' => 'enclose',         'type' => 'text'),
				array('name' => 'placement',       'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'vieworder',       'type' => 'smallint', 'size' => 5, 'default' => 0),
			),
			array(
				array('name' => 'id_field', 'columns' => array('id_field'), 'type' => 'primary'),
				array('name' => 'col_name', 'columns' => array('col_name'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_custom_fields()
	{
		return $this->db->insert('ignore',
			'{db_prefix}custom_fields',
			array(
				'col_name' => 'string',
				'field_name' => 'string',
				'field_desc' => 'string',
				'field_type' => 'string',
				'field_length' => 'int',
				'field_options' => 'string',
				'mask' => 'string',
				'show_reg' => 'int',
				'show_display' => 'int',
				'show_profile' => 'string',
				'private' => 'int',
				'active' => 'int',
				'bbc' => 'int',
				'can_search' => 'int',
				'default_value' => 'string',
				'enclose' => 'string',
				'placement' => 'int',
				'rows' => 'int',
				'cols' => 'int'
			),
			array(
				array(
					'col_name' => 'cust_gender',
					'field_name' => 'Gender',
					'field_desc' => 'Your gender',
					'field_type' => 'radio',
					'field_length' => 15,
					'field_options' => 'undisclosed,male,female,genderless,nonbinary,transgendered',
					'mask' => '',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => 'undisclosed',
					'enclose' => '<i class="icon i-{INPUT}" title="{INPUT}"><s>{INPUT}</s></i>',
					'placement' => 0,
					'rows' => 0,
					'cols' => 0
				),
				array(
					'col_name' => 'cust_blurb',
					'field_name' => 'Personal Text',
					'field_desc' => 'A custom bit of text for your postbit.',
					'field_type' =>'text',
					'field_length' => 120,
					'field_options' => '',
					'mask' => '',
					'show_reg' => 0,
					'show_display' => 0,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => 'Default Personal Text',
					'enclose' => '',
					'placement' => 3,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_locate',
					'field_name' => 'Location',
					'field_desc' => 'Where you are',
					'field_type' => 'text',
					'field_length' => 32,
					'field_options' => '',
					'mask' => '',
					'show_reg' => 0,
					'show_display' => 0,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '',
					'placement' => 0,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_aim',
					'field_name' => 'AOL Instant Messenger',
					'field_desc' => 'This is your AOL Instant Messenger nickname.',
					'field_type' => 'text',
					'field_length' => 50,
					'field_options' => '',
					'mask' => 'regex~[a-z][0-9a-z.-]{1,31}~i',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/profile/aim.png" alt="AIM - {INPUT}"></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_icq',
					'field_name' => 'ICQ',
					'field_desc' => 'This is your ICQ number.',
					'field_type' => 'text',
					'field_length' => 12,
					'field_options' => '',
					'mask' => 'regex~[1-9][0-9]{4,9}~i',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_skye',
					'field_name' => 'Skype',
					'field_desc' => 'This is your Skype account name',
					'field_type' => 'text',
					'field_length' => 32,
					'field_options' => '',
					'mask' => 'regex~[a-z][0-9a-z.-]{1,31}~i',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a href="skype:{INPUT}?call" class="icon i-skype icon-big" title="Skype call {INPUT}"><s>Skype call {INPUT}</s></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_fbook',
					'field_name' => 'Facebook Profile',
					'field_desc' => 'Enter your Facebook username.',
					'field_type' => 'text',
					'field_length' => 50,
					'field_options' => '',
					'mask' => 'regex~[a-z][0-9a-z.-]{1,31}~i',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a target="_blank" href="https://www.facebook.com/{INPUT}" class="icon i-facebook icon-big" title="Facebook"><s>Facebook</s></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_twitt',
					'field_name' => 'Twitter Profile',
					'field_desc' => 'Enter your Twitter username.',
					'field_type' => 'text',
					'field_length' => 50,
					'field_options' => '',
					'mask' => 'regex~[a-z][0-9a-z.-]{1,31}~i',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a target="_blank" href="https://www.twitter.com/{INPUT}" class="icon i-twitter icon-big" title="Twitter Profile"><s>Twitter Profile</s></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_linked',
					'field_name' => 'LinkedIn Profile',
					'field_desc' => 'Set your LinkedIn Public profile link. You must set a Custom public url for this to work.',
					'field_type' => 'text',
					'field_length' => 255,
					'field_options' => '',
					'mask' => 'nohtml',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a href="{INPUT}" class="icon i-linkedin icon-big" title="Linkedin Profile"><s>Linkedin Profile</s></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_gplus'
					'field_name' =>, 'Google+ Profile',
					'field_desc' => 'This is your Google+ profile url.',
					'field_type' => 'text',
					'field_length' => 255,
					'field_options' => '',
					'mask' => 'nohtml',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a target="_blank" href="{INPUT}" class="icon i-google-plus icon-big" title="G+ Profile"><s>G+ Profile</s></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_yim',
					'field_name' => 'Yahoo! Messenger',
					'field_desc' => 'This is your Yahoo! Instant Messenger e-mail address.',
					'field_type' => 'text',
					'field_length' => 50,
					'field_options' => '',
					'mask' => 'email',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="http://opi.yahoo.com/online?m=g&t=0&u={INPUT}" alt="Yahoo! Messenger - {INPUT}"></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
				array(
					'col_name' => 'cust_insta',
					'field_name' => 'Instagram Profile',
					'field_desc' => 'Enter your Instagram username.',
					'field_type' => 'text',
					'field_length' => 50,
					'field_options' => '',
					'mask' => 'regex~[a-z][0-9a-z.-_]{1,30}~i',
					'show_reg' => 0,
					'show_display' => 1,
					'show_profile' => 'forumprofile',
					'private' => 0,
					'active' => 1,
					'bbc' => 0,
					'can_search' => 0,
					'default_value' => '',
					'enclose' => '<a class="i-instagram icon-big" href="https://www.instagram.com/{INPUT}/" target="_blank" title="Instagram"><s>Instagram</s></a>',
					'placement' => 1,
					'rows' => 0,
					'cols' => 0),
			),
			array('id_field')
		);
	}

	public function table_custom_fields_data()
	{
		return $this->table->db_create_table('{db_prefix}custom_fields_data',
			array(
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'variable',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'value',     'type' => 'text'),
			),
			array(
				array('name' => 'id_member', 'columns' => array('id_member', 'variable(30)'), 'type' => 'primary'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_group_moderators()
	{
		return $this->table->db_create_table('{db_prefix}group_moderators',
			array(
				array('name' => 'id_group',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_group', 'columns' => array('id_group', 'id_member'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_follow_ups()
	{
		return $this->table->db_create_table('{db_prefix}follow_ups',
			array(
				array('name' => 'follow_up',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'derived_from', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_group', 'columns' => array('follow_up', 'derived_from'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_actions()
	{
		return $this->table->db_create_table('{db_prefix}log_actions',
			array(
				array('name' => 'id_action', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_log',    'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
				array('name' => 'log_time',  'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip',        'type' => 'char', 'size' => 16, 'default' => '                '),
				array('name' => 'action',    'type' => 'varchar', 'size' => 30, 'default' => ''),
				array('name' => 'id_board',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_topic',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'extra',     'type' => 'text'),
			),
			array(
				array('name' => 'id_action', 'columns' => array('id_action'), 'type' => 'primary'),
				array('name' => 'id_log',    'columns' => array('id_log'), 'type' => 'key'),
				array('name' => 'log_time',  'columns' => array('log_time'), 'type' => 'key'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'id_board',  'columns' => array('id_board'), 'type' => 'key'),
				array('name' => 'id_msg',    'columns' => array('id_msg'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_activity()
	{
		return $this->table->db_create_table('{db_prefix}log_activity',
			array(
				array('name' => 'date',      'type' => 'date', 'default' => '0001-01-01'),
				array('name' => 'hits',      'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'topics',    'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'posts',     'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'registers', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'most_on',   'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'pm',        'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'email',     'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'date',    'columns' => array('date'), 'type' => 'primary'),
				array('name' => 'most_on', 'columns' => array('most_on'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_badbehavior()
	{
		return $this->table->db_create_table('{db_prefix}log_badbehavior',
			array(
				array('name' => 'id',              'type' => 'int', 'size' => 10, 'auto' => true),
				array('name' => 'ip',              'type' => 'char', 'size' => 19),
				array('name' => 'date',            'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'request_method',  'type' => 'varchar', 'size' => 255),
				array('name' => 'request_uri',     'type' => 'varchar', 'size' => 255),
				array('name' => 'server_protocol', 'type' => 'varchar', 'size' => 255),
				array('name' => 'http_headers',    'type' => 'text'),
				array('name' => 'user_agent',      'type' => 'varchar', 'size' => 255),
				array('name' => 'request_entity',  'type' => 'varchar', 'size' => 255),
				array('name' => 'valid',           'type' => 'varchar', 'size' => 255),
				array('name' => 'id_member',       'type' => 'mediumint', 'size' => 8, 'unsigned' => true),
				array('name' => 'session',         'type' => 'char', 'size' => 64, 'default' => ''),
			),
			array(
				array('name' => 'id',         'columns' => array('id'), 'type' => 'primary'),
				array('name' => 'ip',         'columns' => array('ip'), 'type' => 'index'),
				array('name' => 'user_agent', 'columns' => array('user_agent'), 'type' => 'index'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_banned()
	{
		return $this->table->db_create_table('{db_prefix}log_banned',
			array(
				array('name' => 'id_ban_log', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_member',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip',         'type' => 'char', 'size' => 16, 'default' => '                '),
				array('name' => 'email',      'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'log_time',   'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_ban_log', 'columns' => array('id_ban_log'), 'type' => 'primary'),
				array('name' => 'log_time',   'columns' => array('log_time'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_boards()
	{
		return $this->table->db_create_table('{db_prefix}log_boards',
			array(
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_board',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_member', 'columns' => array('id_member', 'id_board'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_comments()
	{
		return $this->table->db_create_table('{db_prefix}log_comments',
			array(
				array('name' => 'id_comment',     'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_member',      'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'member_name',    'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'comment_type',   'type' => 'varchar', 'size' => 8, 'default' => 'warning'),
				array('name' => 'id_recipient',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'recipient_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'log_time',       'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'id_notice',      'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'counter',        'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'body',           'type' => 'text'),
			),
			array(
				array('name' => 'id_comment',   'columns' => array('id_comment'), 'type' => 'primary'),
				array('name' => 'id_recipient', 'columns' => array('id_recipient'), 'type' => 'key'),
				array('name' => 'log_time',     'columns' => array('log_time'), 'type' => 'key'),
				array('name' => 'comment_type', 'columns' => array('comment_type(8)'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_digest()
	{
		return $this->table->db_create_table('{db_prefix}log_digest',
			array(
				array('name' => 'id_topic',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'note_type', 'type' => 'varchar', 'size' => 10, 'default' => 'post'),
				array('name' => 'daily',     'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'exclude',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
			),
			array(),
			'ignore'
		);
	}

	public function table_log_errors()
	{
		return $this->table->db_create_table('{db_prefix}log_errors',
			array(
				array('name' => 'id_error',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'log_time',   'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip',         'type' => 'char', 'size' => 16, 'default' => '                '),
				array('name' => 'url',        'type' => 'text'),
				array('name' => 'message',    'type' => 'text'),
				array('name' => 'session',    'type' => 'char', 'size' => 64, 'default' => '                                                                '),
				array('name' => 'error_type', 'type' => 'char', 'size' => 15, 'default' => 'general'),
				array('name' => 'file',       'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'line',       'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_error',  'columns' => array('id_error'), 'type' => 'primary'),
				array('name' => 'log_time',  'columns' => array('log_time'), 'type' => 'key'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'ip',        'columns' => array('ip(16)'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_floodcontrol()
	{
		return $this->table->db_create_table('{db_prefix}log_floodcontrol',
			array(
				array('name' => 'ip',       'type' => 'char', 'size' => 16, 'default' => '                '),
				array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'log_type', 'type' => 'varchar', 'size' => 10, 'default' => 'post'),
			),
			array(
				array('name' => 'ip_log_time', 'columns' => array('ip(16)', 'log_type(10)'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_group_requests()
	{
		return $this->table->db_create_table('{db_prefix}log_group_requests',
			array(
				array('name' => 'id_request',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_member',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_group',     'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'time_applied', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'reason',       'type' => 'text'),
			),
			array(
				array('name' => 'id_request', 'columns' => array('id_request'), 'type' => 'primary'),
				array('name' => 'id_member',  'columns' => array('id_member', 'id_group'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_karma()
	{
		return $this->table->db_create_table('{db_prefix}log_karma',
			array(
				array('name' => 'id_target',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_executor', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'log_time',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'action',      'type' => 'tinyint', 'size' => 4, 'default' => 0),
			),
			array(
				array('name' => 'target_executor', 'columns' => array('id_target', 'id_executor'), 'type' => 'primary'),
				array('name' => 'log_time',        'columns' => array('log_time'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_likes()
	{
		return $this->table->db_create_table('{db_prefix}log_likes',
			array(
				array('name' => 'action',    'type' => 'char', 'size' => 1, 'default' => '0'),
				array('name' => 'id_target', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'log_time',  'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'target_member', 'columns' => array('id_target', 'id_member'), 'type' => 'primary'),
				array('name' => 'log_time',      'columns' => array('log_time'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_mark_read()
	{
		return $this->table->db_create_table('{db_prefix}log_mark_read',
			array(
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_board',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'member_id_board', 'columns' => array('id_member', 'id_board'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_member_notices()
	{
		return $this->table->db_create_table('{db_prefix}log_member_notices',
			array(
				array('name' => 'id_notice', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'subject',   'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'body',      'type' => 'text'),
			),
			array(
				array('name' => 'id_notice', 'columns' => array('id_notice'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_mentions()
	{
		return $this->table->db_create_table('{db_prefix}log_mentions',
			array(
				array('name' => 'id_mention',     'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_member',      'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_target',      'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'status',         'type' => 'tinyint', 'size' => 1, 'default' => 0),
				array('name' => 'is_accessible',  'type' => 'tinyint', 'size' => 1, 'default' => 0),
				array('name' => 'id_member_from', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'log_time',       'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'mention_type',   'type' => 'varchar', 'size' => 12, 'default' => ''),
			),
			array(
				array('name' => 'id_mention',       'columns' => array('id_mention'), 'type' => 'primary'),
				array('name' => 'id_member_status', 'columns' => array('id_member', 'status'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_notify()
	{
		return $this->table->db_create_table('{db_prefix}log_notify',
			array(
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_topic',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_board',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'sent',      'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'member_topic_board', 'columns' => array('id_member', 'id_topic', 'id_board'), 'type' => 'primary'),
				array('name' => 'id_topic',           'columns' => array('id_topic', 'id_member'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_online()
	{
		return $this->table->db_create_table('{db_prefix}log_online',
			array(
				array('name' => 'session',   'type' => 'varchar', 'size' => 64, 'default' => ''),
				array('name' => 'log_time',  'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_spider', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'ip',        'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'url',       'type' => 'text'),
			),
			array(
				array('name' => 'session',   'columns' => array('session'), 'type' => 'primary'),
				array('name' => 'log_time',  'columns' => array('log_time'), 'type' => 'key'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_packages()
	{
		return $this->table->db_create_table('{db_prefix}log_packages',
			array(
				array('name' => 'id_install',          'type' => 'int', 'size' => 10, 'auto' => true),
				array('name' => 'filename',            'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'package_id',          'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'name',                'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'version',             'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'id_member_installed', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'member_installed',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'time_installed',      'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'id_member_removed',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'member_removed',      'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'time_removed',        'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'install_state',       'type' => 'tinyint', 'size' => 3, 'default' => 1),
				array('name' => 'failed_steps',        'type' => 'text'),
				array('name' => 'themes_installed',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'db_changes',          'type' => 'text'),
				array('name' => 'credits',             'type' => 'varchar', 'size' => 255, 'default' => ''),
			),
			array(
				array('name' => 'id_install', 'columns' => array('id_install'), 'type' => 'primary'),
				array('name' => 'filename',   'columns' => array('filename(15)'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_polls()
	{
		return $this->table->db_create_table('{db_prefix}log_polls',
			array(
				array('name' => 'id_poll',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_choice', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_poll',  'columns' => array('id_poll', 'id_member', 'id_choice'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_reported()
	{
		return $this->table->db_create_table('{db_prefix}log_reported',
			array(
				array('name' => 'id_report',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_msg',       'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'type',         'type' => 'varchar', 'size' => 5, 'default' => ''),
				array('name' => 'id_topic',     'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_board',     'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'membername',   'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'subject',      'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'body',         'type' => 'mediumtext'),
				array('name' => 'time_message', 'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'time_started', 'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'time_updated', 'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'num_reports',  'type' => 'mediumint', 'size' => 6, 'default' => 0),
				array('name' => 'closed',       'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'ignore_all',   'type' => 'tinyint', 'size' => 3, 'default' => 0),
			),
			array(
				array('name' => 'id_report',    'columns' => array('id_report'), 'type' => 'primary'),
				array('name' => 'id_member',    'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'id_topic',     'columns' => array('id_topic'), 'type' => 'key'),
				array('name' => 'closed',       'columns' => array('closed'), 'type' => 'key'),
				array('name' => 'time_started', 'columns' => array('time_started'), 'type' => 'key'),
				array('name' => 'msg_type',     'columns' => array('type', 'id_msg'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_reported_comments()
	{
		return $this->table->db_create_table('{db_prefix}log_reported_comments',
			array(
				array('name' => 'id_comment',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_report',     'type' => 'mediumint', 'size' => 8, 'default' => 0),
				array('name' => 'id_member',     'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'membername',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'email_address', 'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'member_ip',     'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'comment',       'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'time_sent',     'type' => 'int', 'size' => 10, 'default' => 0),
			),
			array(
				array('name' => 'id_comment', 'columns' => array('id_comment'), 'type' => 'primary'),
				array('name' => 'id_report',  'columns' => array('id_report'), 'type' => 'key'),
				array('name' => 'id_member',  'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'time_sent',  'columns' => array('time_sent'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_scheduled_tasks()
	{
		return $this->table->db_create_table('{db_prefix}log_scheduled_tasks',
			array(
				array('name' => 'id_log',     'type' => 'mediumint', 'size' => 8, 'auto' => true),
				array('name' => 'id_task',    'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'time_run',   'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'time_taken', 'type' => 'float', 'default' => 0),
			),
			array(
				array('name' => 'id_log', 'columns' => array('id_log'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_search_messages()
	{
		return $this->table->db_create_table('{db_prefix}log_search_messages',
			array(
				array('name' => 'id_search', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'search_msg', 'columns' => array('id_search', 'id_msg'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_search_results()
	{
		return $this->table->db_create_table('{db_prefix}log_search_results',
			array(
				array('name' => 'id_search',   'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_topic',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',      'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'relevance',   'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'num_matches', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_search_topic', 'columns' => array('id_search', 'id_topic'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_search_subjects()
	{
		return $this->table->db_create_table('{db_prefix}log_search_subjects',
			array(
				array('name' => 'word',     'type' => 'varchar', 'size' => 20, 'default' => ''),
				array('name' => 'id_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'word_id_topic', 'columns' => array('word', 'id_topic'), 'type' => 'primary'),
				array('name' => 'id_topic',      'columns' => array('id_topic'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_search_topics()
	{
		return $this->table->db_create_table('{db_prefix}log_search_topics',
			array(
				array('name' => 'id_search', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_topic',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_search_topic', 'columns' => array('id_search', 'id_topic'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_spider_hits()
	{
		return $this->table->db_create_table('{db_prefix}log_spider_hits',
			array(
				array('name' => 'id_hit',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_spider', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'log_time',  'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'url',       'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'processed', 'type' => 'tinyint', 'size' => 3, 'default' => 0),
			),
			array(
				array('name' => 'id_hit',    'columns' => array('id_hit'), 'type' => 'primary'),
				array('name' => 'id_spider', 'columns' => array('id_spider'), 'type' => 'key'),
				array('name' => 'log_time',  'columns' => array('log_time'), 'type' => 'key'),
				array('name' => 'processed', 'columns' => array('processed'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_spider_stats()
	{
		return $this->table->db_create_table('{db_prefix}log_spider_stats',
			array(
				array('name' => 'id_spider', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'page_hits', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'last_seen', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'stat_date', 'type' => 'date', 'default' => '0001-01-01'),
			),
			array(
				array('name' => 'stat_date_id_spider', 'columns' => array('stat_date', 'id_spider'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_subscribed()
	{
		return $this->table->db_create_table('{db_prefix}log_subscribed',
			array(
				array('name' => 'id_sublog',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_subscribe',     'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',        'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'old_id_group',     'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'start_time',       'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'end_time',         'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'status',           'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'payments_pending', 'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'pending_details',  'type' => 'text'),
				array('name' => 'reminder_sent',    'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'vendor_ref',       'type' => 'varchar', 'size' => 255, 'default' => ''),
			),
			array(
				array('name' => 'id_sublog',        'columns' => array('id_sublog'), 'type' => 'primary'),
				array('name' => 'id_subscribe',     'columns' => array('id_subscribe', 'id_member'), 'type' => 'unique'),
				array('name' => 'end_time',         'columns' => array('end_time'), 'type' => 'key'),
				array('name' => 'reminder_sent',    'columns' => array('reminder_sent'), 'type' => 'key'),
				array('name' => 'payments_pending', 'columns' => array('payments_pending'), 'type' => 'key'),
				array('name' => 'status',           'columns' => array('status'), 'type' => 'key'),
				array('name' => 'id_member',        'columns' => array('id_member'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_log_topics()
	{
		return $this->table->db_create_table('{db_prefix}log_topics',
			array(
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_topic',  'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'unwatched', 'type' => 'tinyint', 'size' => 3, 'default' => 0),
			),
			array(
				array('name' => 'id_member_topic', 'columns' => array('id_member', 'id_topic'), 'type' => 'primary'),
				array('name' => 'id_topic',        'columns' => array('id_topic'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_mail_queue()
	{
		return $this->table->db_create_table('{db_prefix}mail_queue',
			array(
				array('name' => 'id_mail',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'time_sent',  'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'recipient',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'body',       'type' => 'mediumtext'),
				array('name' => 'subject',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'headers',    'type' => 'text'),
				array('name' => 'send_html',  'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'priority',   'type' => 'tinyint', 'size' => 3, 'default' => 1),
				array('name' => 'private',    'type' => 'tinyint', 'size' => 1, 'default' => 0),
				array('name' => 'message_id', 'type' => 'varchar', 'size' => 12, 'default' => ''),
			),
			array(
				array('name' => 'id_mail',       'columns' => array('id_mail'), 'type' => 'primary'),
				array('name' => 'time_sent',     'columns' => array('time_sent'), 'type' => 'key'),
				array('name' => 'mail_priority', 'columns' => array('priority', 'id_mail'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_membergroups()
	{
		return $this->table->db_create_table('{db_prefix}membergroups',
			array(
				array('name' => 'id_group',     'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'group_name',   'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'description',  'type' => 'text'),
				array('name' => 'online_color', 'type' => 'varchar', 'size' => 20, 'default' => ''),
				array('name' => 'min_posts',    'type' => 'mediumint', 'size' => 9, 'default' => -1),
				array('name' => 'max_messages', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'icons',        'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'group_type',   'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'hidden',       'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'id_parent',    'type' => 'smallint', 'size' => 5, 'default' => -2),
			),
			array(
				array('name' => 'id_group',  'columns' => array('id_group'), 'type' => 'primary'),
				array('name' => 'min_posts', 'columns' => array('min_posts'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_membergroups()
	{
		return $this->db->insert('ignore',
			'{db_prefix}membergroups',
			array('group_name' => 'string', 'description' => 'string', 'online_color' => 'string', 'min_posts' => 'int', 'icons' => 'string', 'group_type' => 'int'),
			array(
				array('{$default_administrator_group}', '', '#CD0000', -1, '5#iconadmin.png', 1),
				array('{$default_global_moderator_group}', '', '#0066FF', -1, '5#icongmod.png', 0),
				array('{$default_moderator_group}', '', '', -1, '5#iconmod.png', 0),
				array('{$default_newbie_group}', '', '', 0, '1#icon.png', 0),
				array('{$default_junior_group}', '', '', 50, '2#icon.png', 0),
				array('{$default_full_group}', '', '', 100, '3#icon.png', 0),
				array('{$default_senior_group}', '', '', 250, '4#icon.png', 0),
				array('{$default_hero_group}', '', '', 500, '5#icon.png', 0),
			),
			array('id_group')
		);
	}

	public function table_members()
	{
		return $this->table->db_create_table('{db_prefix}members',
			array(
				array('name' => 'id_member',            'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'member_name',          'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'date_registered',      'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'posts',                'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_group',             'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'lngfile',              'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'last_login',           'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'real_name',            'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'personal_messages',    'type' => 'mediumint', 'size' => 8, 'default' => 0),
				array('name' => 'mentions',             'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'unread_messages',      'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'new_pm',               'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'buddy_list',           'type' => 'text'),
				array('name' => 'pm_ignore_list',       'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'pm_prefs',             'type' => 'mediumint', 'size' => 8, 'default' => 2),
				array('name' => 'mod_prefs',            'type' => 'varchar', 'size' => 20, 'default' => ''),
				array('name' => 'message_labels',       'type' => 'text'),
				array('name' => 'passwd',               'type' => 'varchar', 'size' => 64, 'default' => ''),
				array('name' => 'openid_uri',           'type' => 'text'),
				array('name' => 'email_address',        'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'birthdate',            'type' => 'date', 'default' => '0001-01-01'),
				array('name' => 'website_title',        'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'website_url',          'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'hide_email',           'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'show_online',          'type' => 'tinyint', 'size' => 4, 'default' => 1),
				array('name' => 'time_format',          'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'signature',            'type' => 'text'),
				array('name' => 'time_offset',          'type' => 'float', 'default' => 0),
				array('name' => 'avatar',               'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'pm_email_notify',      'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'karma_bad',            'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'karma_good',           'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'likes_given',          'type' => 'mediumint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'likes_received',       'type' => 'mediumint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'usertitle',            'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'notify_announcements', 'type' => 'tinyint', 'size' => 4, 'default' => 1),
				array('name' => 'notify_regularity',    'type' => 'tinyint', 'size' => 4, 'default' => 1),
				array('name' => 'notify_send_body',     'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'notify_types',         'type' => 'tinyint', 'size' => 4, 'default' => 2),
				array('name' => 'member_ip',            'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'member_ip2',           'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'secret_question',      'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'secret_answer',        'type' => 'varchar', 'size' => 64, 'default' => ''),
				array('name' => 'id_theme',             'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'is_activated',         'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
				array('name' => 'validation_code',      'type' => 'varchar', 'size' => 10, 'default' => ''),
				array('name' => 'id_msg_last_visit',    'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'additional_groups',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'smiley_set',           'type' => 'varchar', 'size' => 48, 'default' => ''),
				array('name' => 'id_post_group',        'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'total_time_logged_in', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'password_salt',        'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'ignore_boards',        'type' => 'text'),
				array('name' => 'warning',              'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'passwd_flood',         'type' => 'varchar', 'size' => 12, 'default' => ''),
				array('name' => 'receive_from',         'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 1),
				array('name' => 'otp_secret',           'type' => 'varchar', 'size' => 16, 'default' => ''),
				array('name' => 'enable_otp',           'type' => 'tinyint', 'size' => 1, 'default' => 0),
				array('name' => 'otp_used',             'type' => 'int', 'size' => 6, 'default' => 0),
			),
			array(
				array('name' => 'id_member',            'columns' => array('id_member'), 'type' => 'primary'),
				array('name' => 'member_name',          'columns' => array('member_name'), 'type' => 'key'),
				array('name' => 'real_name',            'columns' => array('real_name'), 'type' => 'key'),
				array('name' => 'date_registered',      'columns' => array('date_registered'), 'type' => 'key'),
				array('name' => 'id_group',             'columns' => array('id_group'), 'type' => 'key'),
				array('name' => 'birthdate',            'columns' => array('birthdate'), 'type' => 'key'),
				array('name' => 'posts',                'columns' => array('posts'), 'type' => 'key'),
				array('name' => 'last_login',           'columns' => array('last_login'), 'type' => 'key'),
				array('name' => 'lngfile',              'columns' => array('lngfile(30)'), 'type' => 'key'),
				array('name' => 'id_post_group',        'columns' => array('id_post_group'), 'type' => 'key'),
				array('name' => 'warning',              'columns' => array('warning'), 'type' => 'key'),
				array('name' => 'total_time_logged_in', 'columns' => array('total_time_logged_in'), 'type' => 'key'),
				array('name' => 'id_theme',             'columns' => array('id_theme'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_member_logins()
	{
		return $this->table->db_create_table('{db_prefix}member_logins',
			array(
				array('name' => 'id_login',  'type' => 'int', 'size' => 10, 'auto' => true),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'time',      'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'ip',        'type' => 'varchar', 'size' => 255, 'default' => 0),
				array('name' => 'ip2',       'type' => 'varchar', 'size' => 255, 'default' => 0),
			),
			array(
				array('name' => 'id_login',  'columns' => array('id_login'), 'type' => 'primary'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'time',      'columns' => array('time'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_message_icons()
	{
		return $this->table->db_create_table('{db_prefix}message_icons',
			array(
				array('name' => 'id_icon',    'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'title',      'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'filename',   'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'id_board',   'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'icon_order', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_icon',  'columns' => array('id_icon'), 'type' => 'primary'),
				array('name' => 'id_board', 'columns' => array('id_board'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_message_icons()
	{
		// @todo i18n
		return $this->db->insert('ignore',
			'{db_prefix}message_icons',
			array('filename' => 'string', 'title' => 'string', 'icon_order' => 'int'),
			array(
				array('xx', 'Standard', '0'),
				array('thumbup', 'Thumb Up', '1'),
				array('thumbdown', 'Thumb Down', '2'),
				array('exclamation', 'Exclamation point', '3'),
				array('question', 'Question mark', '4'),
				array('lamp', 'Lamp', '5'),
				array('smiley', 'Smiley', '6'),
				array('angry', 'Angry', '7'),
				array('cheesy', 'Cheesy', '8'),
				array('grin', 'Grin', '9'),
				array('sad', 'Sad', '10'),
				array('wink', 'Wink', '11'),
				array('poll', 'Poll', '12'),
			),
			array('id_icon')
		);
	}

	public function table_message_likes()
	{
		return $this->table->db_create_table('{db_prefix}message_likes',
			array(
				array('name' => 'id_member',      'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg',         'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_poster',      'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'like_timestamp', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_msg',    'columns' => array('id_msg', 'id_member'), 'type' => 'primary'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'id_poster', 'columns' => array('id_poster'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_messages()
	{
		return $this->table->db_create_table('{db_prefix}messages',
			array(
				array('name' => 'id_msg',          'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_topic',        'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_board',        'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'poster_time',     'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',       'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_msg_modified', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'subject',         'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'poster_name',     'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'poster_email',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'poster_ip',       'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'smileys_enabled', 'type' => 'tinyint', 'size' => 4, 'default' => 1),
				array('name' => 'modified_time',   'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'modified_name',   'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'body',            'type' => 'text'),
				array('name' => 'icon',            'type' => 'varchar', 'size' => 16, 'default' => 'xx'),
				array('name' => 'approved',        'type' => 'tinyint', 'size' => 3, 'default' => 1),
			),
			array(
				array('name' => 'id_msg',        'columns' => array('id_msg'), 'type' => 'primary'),
				array('name' => 'topic',         'columns' => array('id_topic', 'id_msg'), 'type' => 'unique'),
				array('name' => 'id_board',      'columns' => array('id_board', 'id_msg'), 'type' => 'unique'),
				array('name' => 'id_member',     'columns' => array('id_member', 'id_msg'), 'type' => 'unique'),
				array('name' => 'approved',      'columns' => array('approved'), 'type' => 'key'),
				array('name' => 'ip_index',      'columns' => array('poster_ip(15)', 'id_topic'), 'type' => 'key'),
				array('name' => 'participation', 'columns' => array('id_member', 'id_topic'), 'type' => 'key'),
				array('name' => 'show_posts',    'columns' => array('id_member', 'id_board'), 'type' => 'key'),
				array('name' => 'id_topic',      'columns' => array('id_topic'), 'type' => 'key'),
				array('name' => 'id_member_msg', 'columns' => array('id_member', 'approved', 'id_msg'), 'type' => 'key'),
				array('name' => 'current_topic', 'columns' => array('id_topic', 'id_msg', 'id_member', 'approved'), 'type' => 'key'),
				array('name' => 'related_ip',    'columns' => array('id_member', 'poster_ip', 'id_msg'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_messages()
	{
		return $this->db->insert('ignore',
			'{db_prefix}messages',
			array('id_msg_modified' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'poster_time' => 'int', 'subject' => 'string', 'poster_name' => 'string', 'poster_email' => 'string', 'poster_ip' => 'string', 'modified_name' => 'string', 'body' => 'string', 'icon' => 'string'),
			array(
				array(1, 1, 1, time(), '{$default_topic_subject}', 'Elkarte', 'info@elkarte.net', '127.0.0.1', '', '{$default_topic_message}', 'xx'),
			),
			array('id_msg')
		);
	}

	public function table_moderators()
	{
		return $this->table->db_create_table('{db_prefix}moderators',
			array(
				array('name' => 'id_board',  'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_board', 'columns' => array('id_board', 'id_member'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_notifications_pref()
	{
		return $this->table->db_create_table('{db_prefix}notifications_pref',
			array(
				array('name' => 'id_member',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'notification_level', 'type' => 'tinyint', 'size' => 1, 'default' => 1),
				array('name' => 'mention_type',       'type' => 'varchar', 'size' => 12, 'default' => ''),
			),
			array(
				array('name' => 'mention_member', 'columns' => array('id_member', 'mention_type'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_notifications_pref()
	{
		return $this->db->insert('ignore',
			'{db_prefix}notifications_pref',
			array('id_member' => 'int', 'notification_level' => 'int', 'mention_type' => 'string-12'),
			array(
				array(
					0,
					1,
					'buddy'
				),
				array(
					0,
					1,
					'likemsg'
				),
				array(
					0,
					1,
					'quotedmem'
				),
				array(
					0,
					1,
					'rlikemsg'
				),
				array(
					0,
					1,
					'mentionmem'
				),
			),
			array('id_server')
		);
	}

	public function table_openid_assoc()
	{
		return $this->table->db_create_table('{db_prefix}openid_assoc',
			array(
				array('name' => 'server_url', 'type' => 'text'),
				array('name' => 'handle',     'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'secret',     'type' => 'text'),
				array('name' => 'issued',     'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'expires',    'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'assoc_type', 'type' => 'varchar', 'size' => 64, 'default' => ''),
			),
			array(
				array('name' => 'server_handle', 'columns' => array('server_url(125)', 'handle(125)'), 'type' => 'primary'),
				array('name' => 'expires',       'columns' => array('expires'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_package_servers()
	{
		return $this->table->db_create_table('{db_prefix}package_servers',
			array(
				array('name' => 'id_server', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'name',      'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'url',       'type' => 'varchar', 'size' => 255, 'default' => ''),
			),
			array(
				array('name' => 'id_server', 'columns' => array('id_server'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_package_servers()
	{
		return $this->db->insert('ignore',
			'{db_prefix}package_servers',
			array('name' => 'string', 'url' => 'string'),
			array(
				array('ElkArte Third-party Add-ons Site', 'http://addons.elkarte.net/package.json'),
			),
			array('id_server')
		);
	}

	public function table_pending_notifications()
	{
		return $this->table->db_create_table('{db_prefix}pending_notifications',
			array(
				array('name' => 'notification_type', 'type' => 'varchar', 'size' => 10),
				array('name' => 'id_member',         'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'log_time',          'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'frequency',         'type' => 'varchar', 'size' => 1, 'default' => ''),
				array('name' => 'snippet',           'type' => 'text'),
			),
			array(
				array('name' => 'types_member', 'columns' => array('notification_type', 'id_member'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}

	public function table_permission_profiles()
	{
		return $this->table->db_create_table('{db_prefix}permission_profiles',
			array(
				array('name' => 'id_profile',   'type' => 'smallint', 'size' => 5, 'auto' => true),
				array('name' => 'profile_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			),
			array(
				array('name' => 'id_profile', 'columns' => array('id_profile'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_permission_profiles()
	{
		return $this->db->insert('ignore',
			'{db_prefix}permission_profiles',
			array('profile_name' => 'string'),
			array(
				array('default'),
				array('no_polls'),
				array('reply_only'),
				array('read_only'),
			),
			array('id_group')
		);
	}

	public function table_permissions()
	{
		return $this->table->db_create_table('{db_prefix}permissions',
			array(
				array('name' => 'id_group',   'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'permission', 'type' => 'varchar', 'size' => 30, 'default' => ''),
				array('name' => 'add_deny',   'type' => 'tinyint', 'size' => 4, 'default' => 1),
			),
			array(
				array('name' => 'group_permission', 'columns' => array('id_group', 'permission'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_permissions()
	{
		return $this->db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string'),
			array(
				array(-1, 'search_posts'),
				array(-1, 'calendar_view'),
				array(-1, 'view_stats'),
				array(-1, 'profile_view_any'),
				array(0, 'view_mlist'),
				array(0, 'search_posts'),
				array(0, 'profile_view_own'),
				array(0, 'profile_view_any'),
				array(0, 'pm_read'),
				array(0, 'pm_send'),
				array(0, 'calendar_view'),
				array(0, 'view_stats'),
				array(0, 'who_view'),
				array(0, 'profile_identity_own'),
				array(0, 'profile_extra_own'),
				array(0, 'profile_remove_own'),
				array(0, 'profile_set_avatar'),
				array(0, 'send_email_to_members'),
				array(0, 'karma_edit'),
				array(2, 'view_mlist'),
				array(2, 'search_posts'),
				array(2, 'profile_view_own'),
				array(2, 'profile_view_any'),
				array(2, 'pm_read'),
				array(2, 'pm_send'),
				array(2, 'pm_draft'),
				array(2, 'pm_autosave_draft'),
				array(2, 'calendar_view'),
				array(2, 'view_stats'),
				array(2, 'who_view'),
				array(2, 'profile_identity_own'),
				array(2, 'profile_extra_own'),
				array(2, 'profile_remove_own'),
				array(0, 'profile_set_avatar'),
				array(2, 'send_email_to_members'),
				array(2, 'profile_title_own'),
				array(2, 'calendar_post'),
				array(2, 'calendar_edit_any'),
				array(2, 'karma_edit'),
				array(2, 'access_mod_center'),
			),
			array('id_group', 'permission')
		);
	}

	public function table_personal_messages()
	{
		return $this->table->db_create_table('{db_prefix}personal_messages',
			array(
				array('name' => 'id_pm',             'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_pm_head',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member_from',    'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'deleted_by_sender', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'from_name',         'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'msgtime',           'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'subject',           'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'body',              'type' => 'text'),
			),
			array(
				array('name' => 'id_pm',      'columns' => array('id_pm'), 'type' => 'primary'),
				array('name' => 'id_member',  'columns' => array('id_member_from', 'deleted_by_sender'), 'type' => 'key'),
				array('name' => 'msgtime',    'columns' => array('msgtime'), 'type' => 'key'),
				array('name' => 'id_pm_head', 'columns' => array('id_pm_head'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_pm_recipients()
	{
		return $this->table->db_create_table('{db_prefix}pm_recipients',
			array(
				array('name' => 'id_pm',     'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'labels',    'type' => 'varchar', 'size' => 60, 'default' => -1),
				array('name' => 'bcc',       'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'is_read',   'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'is_new',    'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'deleted',   'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_pm_member', 'columns' => array('id_pm', 'id_member'), 'type' => 'primary'),
				array('name' => 'id_member',    'columns' => array('id_member', 'deleted', 'id_pm'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}

	public function table_pm_rules()
	{
		return $this->table->db_create_table('{db_prefix}pm_rules',
			array(
				array('name' => 'id_rule',   'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_member', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'rule_name', 'type' => 'varchar', 'size' => 60, 'default' => ''),
				array('name' => 'criteria',  'type' => 'text'),
				array('name' => 'actions',   'type' => 'text'),
				array('name' => 'delete_pm', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'is_or',     'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_rule',   'columns' => array('id_rule'), 'type' => 'primary'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
				array('name' => 'delete_pm', 'columns' => array('delete_pm'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_polls()
	{
		return $this->table->db_create_table('{db_prefix}polls',
			array(
				array('name' => 'id_poll',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'question',         'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'voting_locked',    'type' => 'tinyint', 'size' => 1, 'default' => 0),
				array('name' => 'max_votes',        'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
				array('name' => 'expire_time',      'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'hide_results',     'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'change_vote',      'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'guest_vote',       'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'num_guest_voters', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'reset_poll',       'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',        'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'poster_name',      'type' => 'varchar', 'size' => 255, 'default' => ''),
			),
			array(
				array('name' => 'id_poll', 'columns' => array('id_poll'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_poll_choices()
	{
		return $this->table->db_create_table('{db_prefix}poll_choices',
			array(
				array('name' => 'id_poll',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_choice', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
				array('name' => 'label',     'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'votes',     'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_poll', 'columns' => array('id_poll', 'id_choice'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_postby_emails()
	{
		return $this->table->db_create_table('{db_prefix}postby_emails',
			array(
				array('name' => 'message_key',  'type' => 'varchar', 'size' => 32, 'default' => ''),
				array('name' => 'message_type', 'type' => 'varchar', 'size' => 10, 'default' => ''),
				array('name' => 'message_id',   'type' => 'mediumint', 'size' => 8, 'default' => 0),
				array('name' => 'time_sent',    'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'email_to',     'type' => 'varchar', 'size' => 50, 'default' => ''),
			),
			array(
				array('name' => 'id_email', 'columns' => array('message_key', 'message_type', 'message_id'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_postby_emails_error()
	{
		return $this->table->db_create_table('{db_prefix}postby_emails_error',
			array(
				array('name' => 'id_email',     'type' => 'int', 'size' => 10, 'auto' => true),
				array('name' => 'error',        'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'message_key',  'type' => 'varchar', 'size' => 32, 'default' => ''),
				array('name' => 'subject',      'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'message_id',   'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'id_board',     'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'email_from',   'type' => 'varchar', 'size' => 50, 'default' => ''),
				array('name' => 'message_type', 'type' => 'char', 'size' => 10, 'default' => ''),
				array('name' => 'message',      'type' => 'mediumtext'),
			),
			array(
				array('name' => 'id_email', 'columns' => array('id_email'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_postby_emails_filters()
	{
		return $this->table->db_create_table('{db_prefix}postby_emails_filters',
			array(
				array('name' => 'id_filter',    'type' => 'int', 'size' => 10, 'auto' => true),
				array('name' => 'filter_style', 'type' => 'char', 'size' => 6, 'default' => ''),
				array('name' => 'filter_type',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'filter_to',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'filter_from',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'filter_name',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'filter_order', 'type' => 'int', 'size' => 10, 'default' => 0),
			),
			array(
				array('name' => 'id_filter', 'columns' => array('id_filter'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_scheduled_tasks()
	{
		return $this->table->db_create_table('{db_prefix}scheduled_tasks',
			array(
				array('name' => 'id_task',         'type' => 'smallint', 'size' => 5, 'auto' => true),
				array('name' => 'next_time',       'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'time_offset',     'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'time_regularity', 'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'time_unit',       'type' => 'varchar', 'size' => 1, 'default' => 'h'),
				array('name' => 'disabled',        'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'task',            'type' => 'varchar', 'size' => 24, 'default' => ''),
			),
			array(
				array('name' => 'id_task',   'columns' => array('id_task'), 'type' => 'primary'),
				array('name' => 'next_time', 'columns' => array('next_time'), 'type' => 'key'),
				array('name' => 'disabled',  'columns' => array('disabled'), 'type' => 'key'),
				array('name' => 'task',      'columns' => array('task'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_scheduled_tasks()
	{
		return $this->db->insert('ignore',
			'{db_prefix}scheduled_tasks',
			array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string'),
			array(
				array(0, 0, 2, 'h', 0, 'approval_notification'),
				array(0, 0, 7, 'd', 0, 'auto_optimize'),
				array(0, 60, 1, 'd', 0, 'daily_maintenance'),
				array(0, 0, 1, 'd', 0, 'daily_digest'),
				array(0, 0, 1, 'w', 0, 'weekly_digest'),
				array(0, 0, 1, 'd', 1, 'birthdayemails'),
				array(0, 0, 1, 'w', 0, 'weekly_maintenance'),
				array(0, 120, 1, 'd', 1, 'paid_subscriptions'),
				array(0, 120, 1, 'd', 0, 'remove_temp_attachments'),
				array(0, 180, 1, 'd', 0, 'remove_topic_redirect'),
				array(0, 240, 1, 'd', 0, 'remove_old_drafts'),
				array(0, 0, 6, 'h', 0, 'remove_old_followups'),
				array(0, 360, 10, 'm', 1, 'maillist_fetch_IMAP'),
				array(0, 30, 1, 'h', 0, 'user_access_mentions'),
			),
			array('id_task')
		);
	}

	public function table_settings()
	{
		return $this->table->db_create_table('{db_prefix}settings',
			array(
				array('name' => 'variable', 'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'value',    'type' => 'text'),
			),
			array(
				array('name' => 'variable', 'columns' => array('variable(30)'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_settings()
	{
		return $this->db->insert('ignore',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array(
				array('elkVersion', '{$current_version}'),
				array('news', '{$default_news}'),
				array('detailed-version.js', 'https://elkarte.github.io/Elkarte/site/detailed-version.js'),
				array('compactTopicPagesContiguous', '5'),
				array('compactTopicPagesEnable', '1'),
				array('todayMod', '1'),
				array('likes_enabled', '1'),
				array('likeDisplayLimit', '5'),
				array('likeMinPosts', '5'),
				array('karmaMode', '0'),
				array('karmaTimeRestrictAdmins', '1'),
				array('enablePreviousNext', '1'),
				array('pollMode', '1'),
				array('modules_display', 'mentions,poll,verification,random'),
				array('modules_post', 'mentions,poll,attachments,verification,random'),
				array('modules_personalmessage', 'labels,verification'),
				array('modules_register', 'verification'),
				array('enableVBStyleLogin', '1'),
				array('enableCompressedOutput', '{$enableCompressedOutput}'),
				array('karmaWaitTime', '1'),
				array('karmaMinPosts', '0'),
				array('karmaLabel', '{$default_karmaLabel}'),
				array('karmaSmiteLabel', '{$default_karmaSmiteLabel}'),
				array('karmaApplaudLabel', '{$default_karmaApplaudLabel}'),
				array('attachmentSizeLimit', '128'),
				array('attachmentPostLimit', '192'),
				array('attachmentNumPerPostLimit', '4'),
				array('attachmentDirSizeLimit', '10240'),
				array('attachmentDirFileLimit', '1000'),
				array('attachmentUploadDir', '{BOARDDIR}/attachments'),
				array('attachmentExtensions', 'doc,gif,jpg,mpg,pdf,png,txt,zip'),
				array('attachmentCheckExtensions', '0'),
				array('attachmentShowImages', '1'),
				array('attachmentEnable', '1'),
				array('attachmentEncryptFilenames', '1'),
				array('attachmentThumbnails', '1'),
				array('attachmentThumbWidth', '150'),
				array('attachmentThumbHeight', '150'),
				array('use_subdirectories_for_attachments', '1'),
				array('censorIgnoreCase', '1'),
				array('mostOnline', '1'),
				array('mostOnlineToday', '1'),
				array('mostDate', time()),
				array('allow_disableAnnounce', '1'),
				array('trackStats', '1'),
				array('userLanguage', '1'),
				array('titlesEnable', '1'),
				array('topicSummaryPosts', '15'),
				array('enableErrorLogging', '1'),
				array('max_image_width', '0'),
				array('max_image_height', '0'),
				array('onlineEnable', '0'),
				array('cal_enabled', '0'),
				// cal_maxyear kept for compatibility purposes
				array('cal_maxyear', '2030'),
				array('cal_limityear', '10'),
				array('cal_minyear', date('Y') - 4),
				array('cal_daysaslink', '0'),
				array('cal_defaultboard', ''),
				array('cal_showholidays', '1'),
				array('cal_showbdays', '1'),
				array('cal_showevents', '1'),
				array('cal_showweeknum', '0'),
				array('cal_maxspan', '7'),
				array('smtp_host', ''),
				array('smtp_port', '25'),
				array('smtp_username', ''),
				array('smtp_password', ''),
				array('mail_type', '0'),
				array('timeLoadPageEnable', '0'),
				array('totalMembers', '0'),
				array('totalTopics', '1'),
				array('totalMessages', '1'),
				array('censor_vulgar', ''),
				array('censor_proper', ''),
				array('enablePostHTML', '0'),
				array('theme_allow', '1'),
				array('theme_default', '1'),
				array('theme_guests', '1'),
				array('xmlnews_enable', '1'),
				array('xmlnews_limit', '5'),
				array('xmlnews_maxlen', '255'),
				array('hotTopicPosts', '15'),
				array('hotTopicVeryPosts', '25'),
				array('registration_method', '0'),
				array('send_validation_onChange', '0'),
				array('send_welcomeEmail', '1'),
				array('allow_editDisplayName', '1'),
				array('admin_session_lifetime', '10'),
				array('allow_hideOnline', '1'),
				array('spamWaitTime', '5'),
				array('pm_spam_settings', '10,5,20'),
				array('reserveWord', '0'),
				array('reserveCase', '1'),
				array('reserveUser', '1'),
				array('reserveName', '1'),
				array('reserveNames', '{$default_reserved_names}'),
				array('autoLinkUrls', '1'),
				array('banLastUpdated', '0'),
				array('smileys_dir', '{BOARDDIR}/smileys'),
				array('smileys_url', '{$boardurl}/smileys'),
				array('avatar_default', '0'),
				array('avatar_stored_enabled', '1'),
				array('avatar_external_enabled', '1'),
				array('avatar_gravatar_enabled', '1'),
				array('avatar_upload_enabled', '1'),
				array('avatar_directory', '{BOARDDIR}/avatars'),
				array('avatar_url', '{$boardurl}/avatars'),
				array('avatar_max_height', '65'),
				array('avatar_max_width', '65'),
				array('avatar_action_too_large', 'option_resize'),
				array('avatar_download_png', '1'),
				array('gravatar_rating', 'g'),
				array('failed_login_threshold', '3'),
				array('oldTopicDays', '120'),
				array('edit_wait_time', '90'),
				array('edit_disable_time', '0'),
				array('autoFixDatabase', '1'),
				array('allow_guestAccess', '1'),
				array('time_format', '{$default_time_format}'),
				array('number_format', '1234.00'),
				array('enableBBC', '1'),
				array('max_messageLength', '20000'),
				array('signature_settings', '1,300,0,0,0,0,0,0:'),
				array('autoOptMaxOnline', '0'),
				array('defaultMaxMessages', '15'),
				array('defaultMaxTopics', '20'),
				array('defaultMaxMembers', '30'),
				array('enableParticipation', '1'),
				array('enableFollowup', '1'),
				array('recycle_enable', '0'),
				array('recycle_board', '0'),
				array('maxMsgID', '1'),
				array('enableAllMessages', '0'),
				array('fixLongWords', '0'),
				array('knownThemes', '1,2,3'),
				array('who_enabled', '1'),
				array('time_offset', '0'),
				array('cookieTime', '60'),
				array('jquery_source', 'local'),
				array('lastActive', '15'),
				array('smiley_sets_known', 'default'),
				array('smiley_sets_names', '{$default_smileyset_name}'),
				array('smiley_sets_default', 'default'),
				array('cal_days_for_index', '7'),
				array('requireAgreement', '1'),
				array('unapprovedMembers', '0'),
				array('default_personal_text', ''),
				array('package_make_backups', '1'),
				array('databaseSession_enable', '{$databaseSession_enable}'),
				array('databaseSession_loose', '1'),
				array('databaseSession_lifetime', '2880'),
				array('search_cache_size', '50'),
				array('search_results_per_page', '30'),
				array('search_weight_frequency', '30'),
				array('search_weight_age', '25'),
				array('search_weight_length', '20'),
				array('search_weight_subject', '15'),
				array('search_weight_first_message', '10'),
				array('search_max_results', '1200'),
				array('search_floodcontrol_time', '5'),
				array('permission_enable_deny', '0'),
				array('permission_enable_postgroups', '0'),
				array('mail_next_send', '0'),
				array('mail_recent', '0000000000|0'),
				array('settings_updated', '0'),
				array('next_task_time', '1'),
				array('warning_settings', '1,20,0'),
				array('warning_watch', '10'),
				array('warning_moderate', '35'),
				array('warning_mute', '60'),
				array('admin_features', ''),
				array('last_mod_report_action', '0'),
				array('pruningOptions', '30,180,180,180,30,7,0'),
				array('cache_enable', '1'),
				array('reg_verification', '1'),
				array('visual_verification_type', '3'),
				array('visual_verification_num_chars', '6'),
				array('enable_buddylist', '1'),
				array('birthday_email', 'happy_birthday'),
				array('attachment_image_reencode', '1'),
				array('attachment_image_paranoid', '0'),
				array('attachment_thumb_png', '1'),
				array('avatar_reencode', '1'),
				array('avatar_paranoid', '0'),
				array('enable_unwatch', '0'),
				array('mentions_enabled', '1'),
				array('mentions_buddy', '0'),
				array('mentions_dont_notify_rlike', '0'),
				array('enabled_mentions', 'buddy,likemsg,mentionmem,quotedmem'),
				array('badbehavior_enabled', '0'),
				array('badbehavior_logging', '1'),
				array('badbehavior_ip_wl', 'a:3:{i:2;s:10:"10.0.0.0/8";i:5;s:13:"172.16.0.0/12";i:6;s:14:"192.168.0.0/16";}'),
				array('badbehavior_ip_wl_desc', 'a:3:{i:2;s:18:"RFC 1918 addresses";i:5;s:18:"RFC 1918 addresses";i:6;s:18:"RFC 1918 addresses";}'),
				array('badbehavior_url_wl', 'a:1:{i:0;s:18:"/subscriptions.php";}'),
				array('badbehavior_url_wl_desc', 'a:1:{i:0;s:15:"Payment Gateway";}'),
				array('notification_methods', 'a:4:{s:5:"buddy";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}s:7:"likemsg";a:1:{s:12:"notification";s:1:"1";}s:10:"mentionmem";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}s:9:"quotedmem";a:4:{s:12:"notification";s:1:"1";s:5:"email";s:1:"1";s:11:"email_daily";s:1:"1";s:12:"email_weekly";s:1:"1";}}'),
				array('autoload_integrate', 'User_Notification_Integrate,Ila_Integrate'),
				array('usernotif_favicon_bgColor', '#ff0000'),
				array('usernotif_favicon_position', 'up'),
				array('usernotif_favicon_textColor', '#ffff00'),
				array('usernotif_favicon_type', 'circle'),
				array('secureCookies', '1'),
				array('httponlyCookies', '1'),
			),
			array('variable')
		);
	}

	public function table_sessions()
	{
		return $this->table->db_create_table('{db_prefix}sessions',
			array(
				array('name' => 'session_id',  'type' => 'char', 'size' => 64),
				array('name' => 'last_update', 'type' => 'int', 'size' => 10, 'unsigned' => true),
				array('name' => 'data',        'type' => 'text'),
			),
			array(
				array('name' => 'session_id', 'columns' => array('session_id'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function table_smileys()
	{
		return $this->table->db_create_table('{db_prefix}smileys',
			array(
				array('name' => 'id_smiley',    'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'code',         'type' => 'varchar', 'size' => 30, 'default' => ''),
				array('name' => 'filename',     'type' => 'varchar', 'size' => 48, 'default' => ''),
				array('name' => 'description',  'type' => 'varchar', 'size' => 80, 'default' => ''),
				array('name' => 'smiley_row',   'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
				array('name' => 'smiley_order', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'hidden',       'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 0),
			),
			array(
				array('name' => 'id_smiley', 'columns' => array('id_smiley'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_smileys()
	{
		return $this->db->insert('ignore',
			'{db_prefix}smileys',
			array('code' => 'string', 'filename' => 'string', 'description' => 'string', 'smiley_order' => 'int', 'hidden' => 'int'),
			array(
				array(':)', 'smiley.gif', '{$default_smiley_smiley}', 0, 0),
				array(';)', 'wink.gif', '{$default_wink_smiley}', 1, 0),
				array(':D', 'cheesy.gif', '{$default_cheesy_smiley}', 2, 0),
				array(';D', 'grin.gif', '{$default_grin_smiley}', 3, 0),
				array('>:(', 'angry.gif', '{$default_angry_smiley}', 4, 0),
				array(':(', 'sad.gif', '{$default_sad_smiley}', 5, 0),
				array(':o', 'shocked.gif', '{$default_shocked_smiley}', 6, 0),
				array('8)', 'cool.gif', '{$default_cool_smiley}', 7, 0),
				array('???', 'huh.gif', '{$default_huh_smiley}', 8, 0),
				array('::)', 'rolleyes.gif', '{$default_roll_eyes_smiley}', 9, 0),
				array(':P', 'tongue.gif', '{$default_tongue_smiley}', 10, 0),
				array(':-[', 'embarrassed.gif', '{$default_embarrassed_smiley}', 11, 0),
				array(':-X', 'lipsrsealed.gif', '{$default_lips_sealed_smiley}', 12, 0),
				array(':-\\', 'undecided.gif', '{$default_undecided_smiley}', 13, 0),
				array(':-*', 'kiss.gif', '{$default_kiss_smiley}', 14, 0),
				array(':\'(', 'cry.gif', '{$default_cry_smiley}', 15, 0),
				array('>:D', 'evil.gif', '{$default_evil_smiley}', 16, 1),
				array('^-^', 'azn.gif', '{$default_azn_smiley}', 17, 1),
				array('O0', 'afro.gif', '{$default_afro_smiley}', 18, 1),
				array(':))', 'laugh.gif', '{$default_laugh_smiley}', 19, 1),
				array('C:-)', 'police.gif', '{$default_police_smiley}', 20, 1),
				array('O:)', 'angel.gif', '{$default_angel_smiley}', 21, 1),
			),
			array('id_smiley')
		);
	}

	public function table_spiders()
	{
		return $this->table->db_create_table('{db_prefix}spiders',
			array(
				array('name' => 'id_spider',   'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'auto' => true),
				array('name' => 'spider_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'user_agent',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'ip_info',     'type' => 'varchar', 'size' => 255, 'default' => ''),
			),
			array(
				array('name' => 'id_spider', 'columns' => array('id_spider'), 'type' => 'primary'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_spiders()
	{
		return $this->db->insert('ignore',
			'{db_prefix}spiders',
			array('spider_name' => 'string', 'user_agent' => 'string', 'ip_info' => 'string'),
			array(
				array('Google', 'googlebot', ''),
				array('Yahoo!', 'Yahoo! Slurp', ''),
				array('MSN', 'msnbot', ''),
				array('Bing', 'bingbot', ''),
				array('Google (Mobile)', 'Googlebot-Mobile', ''),
				array('Google (Image)', 'Googlebot-Image', ''),
				array('Google (AdSense)', 'Mediapartners-Google', ''),
				array('Google (Adwords)', 'AdsBot-Google', ''),
				array('Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', ''),
				array('Yahoo! (Image)', 'Yahoo-MMCrawler', ''),
				array('Yahoo! (Blogs)', 'Yahoo-Blogs', ''),
				array('Yahoo! (Feeds)', 'YahooFeedSeeker', ''),
				array('MSN (Mobile)', 'MSNBOT_Mobile', ''),
				array('MSN (Media)', 'msnbot-media', ''),
				array('Cuil', 'twiceler', ''),
				array('Ask', 'Teoma', ''),
				array('Baidu', 'Baiduspider', ''),
				array('Gigablast', 'Gigabot', ''),
				array('InternetArchive', 'ia_archiver-web.archive.org', ''),
				array('Alexa', 'ia_archiver', ''),
				array('Omgili', 'omgilibot', ''),
				array('EntireWeb', 'Speedy Spider', ''),
				array('Yandex', 'YandexBot', ''),
				array('Yandex (Images)', 'YandexImages', ''),
				array('Yandex (Video)', 'YandexVideo', ''),
				array('Yandex (Blogs)', 'YandexBlogs', ''),
				array('Yandex (Media)', 'YandexMedia', ''),
			),
			array('id_spider')
		);
	}

	public function table_subscriptions()
	{
		return $this->table->db_create_table('{db_prefix}subscriptions',
			array(
				array('name' => 'id_subscribe',   'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'name',           'type' => 'varchar', 'size' => 60, 'default' => ''),
				array('name' => 'description',    'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'cost',           'type' => 'text'),
				array('name' => 'length',         'type' => 'varchar', 'size' => 6, 'default' => ''),
				array('name' => 'id_group',       'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'add_groups',     'type' => 'varchar', 'size' => 40, 'default' => ''),
				array('name' => 'active',         'type' => 'tinyint', 'size' => 3, 'default' => 1),
				array('name' => 'repeatable',     'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'allow_partial',  'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'reminder',       'type' => 'tinyint', 'size' => 3, 'default' => 0),
				array('name' => 'email_complete', 'type' => 'text'),
			),
			array(
				array('name' => 'id_subscribe', 'columns' => array('id_subscribe'), 'type' => 'primary'),
				array('name' => 'active',       'columns' => array('active'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function table_themes()
	{
		// this may look inconsistent, but id_member is *not* unsigned
		return $this->table->db_create_table('{db_prefix}themes',
			array(
				array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
				array('name' => 'id_theme',  'type' => 'tinyint', 'size' => 4, 'unsigned' => true, 'default' => 1),
				array('name' => 'variable',  'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'value',     'type' => 'text'),
			),
			array(
				array('name' => 'id_theme',  'columns' => array('id_theme', 'id_member', 'variable(30)'), 'type' => 'primary'),
				array('name' => 'id_member', 'columns' => array('id_member'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_themes()
	{
		return $this->db->insert('ignore',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			array(
				array(1, 'name', '{$default_theme_name}'),
				array(1, 'theme_url', '{$boardurl}/themes/default'),
				array(1, 'images_url', '{$boardurl}/themes/default/images'),
				array(1, 'theme_dir', '{BOARDDIR}/themes/default'),
				array(1, 'show_bbc', '1'),
				array(1, 'show_latest_member', '1'),
				array(1, 'show_modify', '1'),
				array(1, 'show_user_images', '1'),
				array(1, 'number_recent_posts', '0'),
				array(1, 'linktree_link', '1'),
				array(1, 'show_profile_buttons', '1'),
				array(1, 'show_mark_read', '1'),
				array(1, 'show_stats_index', '1'),
				array(1, 'newsfader_time', '5000'),
				array(1, 'allow_no_censored', '0'),
				array(1, 'additional_options_collapsable', '1'),
				array(1, 'use_image_buttons', '1'),
				array(1, 'enable_news', '1'),
				array(1, 'forum_width', '90%'),
			),
			array('id_theme', 'id_member', 'variable')
		);
	}

	public function insert_themes2()
	{
		return $this->db->insert('ignore',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			array(
				array(-1, 1, 'display_quick_reply', '2'),
				array(-1, 1, 'view_newest_pm_first', '1'),
				array(-1, 1, 'return_to_post', '1'),
				array(-1, 1, 'drafts_autosave_enabled', '1'),
			),
			array('id_theme', 'id_member', 'variable')
		);
	}

	public function table_topics()
	{
		return $this->table->db_create_table('{db_prefix}topics',
			array(
				array('name' => 'id_topic',          'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'auto' => true),
				array('name' => 'is_sticky',         'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'id_board',          'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_first_msg',      'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_last_msg',       'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member_started', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member_updated', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_poll',           'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_previous_board', 'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'id_previous_topic', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
				array('name' => 'num_replies',       'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'num_views',         'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'num_likes',         'type' => 'int', 'size' => 10, 'default' => 0),
				array('name' => 'locked',            'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'redirect_expires',  'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_redirect_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'unapproved_posts',  'type' => 'smallint', 'size' => 5, 'default' => 0),
				array('name' => 'approved',          'type' => 'tinyint', 'size' => 3, 'default' => 1),
			),
			array(
				array('name' => 'id_topic',            'columns' => array('id_topic'), 'type' => 'primary'),
				array('name' => 'last_message',        'columns' => array('id_last_msg', 'id_board'), 'type' => 'unique'),
				array('name' => 'first_message',       'columns' => array('id_first_msg', 'id_board'), 'type' => 'unique'),
				array('name' => 'poll',                'columns' => array('id_poll', 'id_topic'), 'type' => 'unique'),
				array('name' => 'is_sticky',           'columns' => array('is_sticky'), 'type' => 'key'),
				array('name' => 'approved',            'columns' => array('approved'), 'type' => 'key'),
				array('name' => 'id_board',            'columns' => array('id_board'), 'type' => 'key'),
				array('name' => 'member_started',      'columns' => array('id_member_started', 'id_board'), 'type' => 'key'),
				array('name' => 'last_message_sticky', 'columns' => array('id_board', 'is_sticky', 'id_last_msg'), 'type' => 'key'),
				array('name' => 'board_news',          'columns' => array('id_board', 'id_first_msg'), 'type' => 'key'),
			),
			array(),
			'ignore'
		);
	}

	public function insert_topics()
	{
		return $this->db->insert('ignore',
			'{db_prefix}topics',
			array('id_board' => 'int', 'id_first_msg' => 'int', 'id_last_msg' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int'),
			array(
				array(1, 1, 1, 0, 0),
			),
			array('id_topic')
		);
	}

	public function table_user_drafts()
	{
		return $this->table->db_create_table('{db_prefix}user_drafts',
			array(
				array('name' => 'id_draft',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
				array('name' => 'id_topic',        'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_board',        'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_reply',        'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'type',            'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'poster_time',     'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
				array('name' => 'id_member',       'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
				array('name' => 'subject',         'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'smileys_enabled', 'type' => 'tinyint', 'size' => 4, 'default' => 1),
				array('name' => 'body',            'type' => 'mediumtext'),
				array('name' => 'icon',            'type' => 'varchar', 'size' => 16, 'default' => 'xx'),
				array('name' => 'locked',          'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'is_sticky',       'type' => 'tinyint', 'size' => 4, 'default' => 0),
				array('name' => 'to_list',         'type' => 'varchar', 'size' => 255, 'default' => ''),
				array('name' => 'is_usersaved',    'type' => 'tinyint', 'size' => 4, 'default' => 0),
			),
			array(
				array('name' => 'id_draft',  'columns' => array('id_draft'), 'type' => 'primary'),
				array('name' => 'id_member', 'columns' => array('id_member', 'id_draft', 'type'), 'type' => 'unique'),
			),
			array(),
			'ignore'
		);
	}
}
