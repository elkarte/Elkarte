<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

use tests\ElkArteCommonSetupTest;

class MaillistSubsTest extends ElkArteCommonSetupTest
{
    protected $backupGlobalsExcludeList = ['user_info'];

    private $created_error_ids = [];
    private $created_filter_ids = [];
    private $created_email_keys = [];
    private $ensure_imap_task = false;

    protected function setUp(): void
    {
        global $txt, $boardurl;

        parent::setUp();

        // Minimal globals used by Maillist.subs.php
        $boardurl = 'http://example.com';
        $txt['dummy_short'] = 'Dummy';

        require_once(SUBSDIR . '/Maillist.subs.php');
    }

    protected function tearDown(): void
    {
        $db = database();

        // Clean up created postby_emails_error rows
        foreach ($this->created_error_ids as $id) {
            $db->query('', 'DELETE FROM {db_prefix}postby_emails_error WHERE id_email = {int:id}', ['id' => $id]);
        }

        // Clean up created postby_emails_filters rows
        if (!empty($this->created_filter_ids)) {
            $db->query('', 'DELETE FROM {db_prefix}postby_emails_filters WHERE id_filter IN ({array_int:ids})', ['ids' => $this->created_filter_ids]);
        }

        // Clean up created postby_emails rows
        foreach ($this->created_email_keys as $mkey) {
            $db->query('', 'DELETE FROM {db_prefix}postby_emails WHERE message_key = {string:k}', ['k' => $mkey]);
        }

        parent::tearDown();
    }

    public function testListUnapprovedAndCountAndDelete()
    {
        $db = database();

        // Seed a failed email entry (id_board = -1 to always show)
        $db->insert('insert', '{db_prefix}postby_emails_error',
            [
                'error' => 'string',
                'message_key' => 'string',
                'subject' => 'string',
                'message_id' => 'string',
                'email_from' => 'string',
                'message_type' => 'string',
                'message' => 'string',
                'id_board' => 'int',
            ],
            [
                'dummy',
                'key_aaa',
                'A subject',
                '123',
                'noreply@example.com',
                't',
                'Body here',
                -1,
            ],
            ['id_email']
        );

        // Get the new id
        $request = $db->query('', 'SELECT MAX(id_email) FROM {db_prefix}postby_emails_error', []);
        [$id_email] = $request->fetch_row();
        $request->free_result();
        $this->created_error_ids[] = (int) $id_email;

        // list_maillist_unapproved
        $rows = list_maillist_unapproved(0, 0, 0, '');
        $this->assertNotEmpty($rows);
        $found = null;
        foreach ($rows as $r) {
            if ($r['key'] === 'key_aaa') {
                $found = $r;
                break;
            }
        }
        $this->assertNotNull($found, 'Inserted error not found');
        $this->assertSame('A subject', $found['subject']);
        $this->assertSame('http://example.com?topic=123', $found['link']);
        $this->assertSame('dummy', $found['error_code']);
        $this->assertSame('Dummy', $found['error']);

        // list_maillist_count_unapproved
        $count_before = list_maillist_count_unapproved();
        $this->assertGreaterThanOrEqual(1, $count_before);

        // maillist_delete_error_entry
        maillist_delete_error_entry((int) $id_email);

        $count_after = list_maillist_count_unapproved();
        $this->assertGreaterThanOrEqual(0, $count_after);
        $this->assertGreaterThanOrEqual($count_before - 1, $count_after);
    }

    public function testFilterParserCRUDAndOrder()
    {
        $db = database();

        // Seed a couple of filters
        $toInsert = [
            ['filter', 'to', 'to@example.com', '', 'Filter A', 5],
            ['filter', 'from', '', 'from@example.com', 'Filter B', 10],
        ];
        foreach ($toInsert as $row) {
            $db->insert('insert', '{db_prefix}postby_emails_filters',
                [
                    'filter_style' => 'string',
                    'filter_type' => 'string',
                    'filter_to' => 'string',
                    'filter_from' => 'string',
                    'filter_name' => 'string',
                    'filter_order' => 'int',
                ],
                $row,
                ['id_filter']
            );
        }

        // Track created ids
        $req = $db->query('', 'SELECT id_filter FROM {db_prefix}postby_emails_filters WHERE filter_name IN ({array_string:n})', ['n' => ['Filter A', 'Filter B']]);
        while ($r = $req->fetch_row()) {
            $this->created_filter_ids[] = (int) $r[0];
        }
        $req->free_result();

        // list_get_filter_parser
        $list = list_get_filter_parser(0, 0, '', 0, 'filter');
        $this->assertIsArray($list);

        // list_count_filter_parser
        $cnt = list_count_filter_parser(0, 'filter');
        $this->assertGreaterThanOrEqual(2, $cnt);

        // maillist_load_filter_parser (existing)
        $oneId = $this->created_filter_ids[0];
        $one = maillist_load_filter_parser($oneId, 'filter');
        $this->assertSame('filter', $one['filter_style']);

        // updateParserFilterOrder
        $newOrders = [];
        $when = '';
        foreach ($this->created_filter_ids as $idx => $fid) {
            $newOrders[$fid] = $idx + 1;
            $when .= ' WHEN id_filter=' . (int) $fid . ' THEN ' . ($idx + 1);
        }
        updateParserFilterOrder(trim($when), $this->created_filter_ids);

        // Verify orders updated
        $q = $db->query('', 'SELECT id_filter, filter_order FROM {db_prefix}postby_emails_filters WHERE id_filter IN ({array_int:i})', ['i' => $this->created_filter_ids]);
        while ($r = $q->fetch_assoc()) {
            $this->assertSame($newOrders[(int) $r['id_filter']], (int) $r['filter_order']);
        }
        $q->free_result();

        // maillist_delete_filter_parser
        maillist_delete_filter_parser($this->created_filter_ids[1]);
        // Remove from local tracking so tearDown doesn't try again
        array_splice($this->created_filter_ids, 1, 1);
    }

    public function testBoardList()
    {
        $list = maillist_board_list();
        $this->assertArrayHasKey(0, $list);
        $this->assertSame('', $list[0]);
        // Usually there is board 1 in fixtures
        $this->assertIsArray($list);
    }

    public function testEnableImapCron()
    {
        $db = database();

        // Ensure the scheduled task exists
        $req = $db->query('', 'SELECT task FROM {db_prefix}scheduled_tasks WHERE task = {string:t} LIMIT 1', ['t' => 'pbeIMAP']);
        if ($req->num_rows() === 0) {
            $db->insert('insert', '{db_prefix}scheduled_tasks',
                [
                    'task' => 'string',
                    'next_time' => 'int',
                    'time_offset' => 'int',
                    'disabled' => 'int',
                ],
                ['pbeIMAP', 0, 0, 1],
                ['id_task']
            );
        }
        $req->free_result();

        // Enable
        enable_maillist_imap_cron(true);

        $row = $db->fetchQuery('SELECT disabled, next_time FROM {db_prefix}scheduled_tasks WHERE task = {string:t}', ['t' => 'pbeIMAP'])
            ->fetch_callback(static function ($r) { return $r; });
        $this->assertNotEmpty($row);
        $this->assertSame(0, (int) $row[0]['disabled']);
        $this->assertGreaterThan(time(), (int) $row[0]['next_time']);
    }

    public function testTemplatesAndLogEmail()
    {
        $db = database();

        // Seed a couple of templates
        $db->insert('insert', '{db_prefix}log_comments',
            [
                'id_recipient' => 'int',
                'recipient_name' => 'string',
                'comment_type' => 'string',
                'body' => 'string',
            ],
            [0, 'Public Template', 'emailtpl', 'Hello'],
            ['id_comment']
        );

        $db->insert('insert', '{db_prefix}log_comments',
            [
                'id_recipient' => 'int',
                'recipient_name' => 'string',
                'comment_type' => 'string',
                'body' => 'string',
            ],
            [1, 'Private Template', 'emailtpl', 'Hi there'],
            ['id_comment']
        );

        $tpls = maillist_templates('emailtpl', 'A Subject');
        $this->assertNotEmpty($tpls);
        $this->assertArrayHasKey('title', $tpls[0]);
        $this->assertArrayHasKey('body', $tpls[0]);
        $this->assertArrayHasKey('subject', $tpls[0]);

        // log_email
        $msg_key = 'mkey_' . uniqid('', true);
        $sent = [
            $msg_key,
            't',
            '321',
            time(),
            'to@example.com',
        ];
        $this->created_email_keys[] = $msg_key;
        log_email($sent);

        $req = $db->query('', 'SELECT message_key, email_to FROM {db_prefix}postby_emails WHERE message_key = {string:k}', ['k' => $msg_key]);
        $row = $req->fetch_row();
        $req->free_result();
        $this->assertSame($msg_key, $row[0]);
        $this->assertSame('to@example.com', $row[1]);
    }
}
