<?php

/**
 * TestCase class for basic PBE functions
 */
class TestPBE extends PHPUnit_Framework_TestCase
{
	protected $_email;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		require_once(CONTROLLERDIR . '/Emailpost.controller.php');
		require_once(SUBSDIR . '/EmailParse.class.php');
		require_once(SUBSDIR . '/Emailpost.subs.php');

		theme()->getTemplates()->loadLanguageFile('Maillist');

		$this->_email = 'Return-Path: <noreply@elkarte.net>
Delivered-To: <drwho@tardis.com>
Received: from galileo.tardis.com
	by galileo.tardis.com (Dovecot) with LMTP id znQ3AvalOVi/SgAAhPm7pg
	for <drwho@tardis.com>; Sat, 26 Nov 2016 09:10:46 -0600
Received: by galileo.tardis.com (Postfix, from userid 1005)
	id 0671C1C8; Sat, 26 Nov 2016 09:10:46 -0600 (CST)
X-Spam-Checker-Version: SpamAssassin 3.4.0 (2014-02-07) on
	galileo.tardis.com
X-Spam-Flag: YES
X-Spam-Level: ******
X-Spam-Status: Yes, score=5.0 required=5.0 tests=HTML_IMAGE_ONLY_16,
	HTML_MESSAGE,HTML_SHORT_LINK_IMG_2,MIME_HTML_ONLY,MIME_HTML_ONLY_MULTI,
	MPART_ALT_DIFF,RCVD_IN_BRBL_LASTEXT,T_DKIM_INVALID,URIBL_BLACK autolearn=no
	autolearn_force=no version=3.4.0
Received: from mail.elkarte.net (s2.eurich.de [85.214.104.5])
	by galileo.tardis.com (Postfix) with ESMTP id 1872579
	for <drwho@tardis.com>; Sat, 26 Nov 2016 09:10:40 -0600 (CST)
Received: from localhost (localhost [127.0.0.1])
	by mail.elkarte.net (Postfix) with ESMTP id 9DE3C4CE1535
	for <drwho@tardis.com>; Sat, 26 Nov 2016 16:10:39 +0100 (CET)
X-Virus-Scanned: Debian amavisd-new at s2.eurich.de
Received: from mail.elkarte.net ([127.0.0.1])
	by localhost (h2294877.stratoserver.net [127.0.0.1]) (amavisd-new, port 10024)
	with ESMTP id zQep5x32jrqA for <drwho@tardis.com>;
	Sat, 26 Nov 2016 16:10:03 +0100 (CET)
Received: from mail.elkarte.net (h2294877.stratoserver.net [85.214.104.5])
	by mail.elkarte.net (Postfix) with ESMTPA id 990694CE0CFA
	for <drwho@tardis.com>; Sat, 26 Nov 2016 16:10:03 +0100 (CET)
Subject: [ElkArte Community] Test Message
To: <drwho@tardis.com>
From: "Administrator via ElkArte Community" <noreply@elkarte.net>
Reply-To: "ElkArte Community" <elkarteforum@gmail.com>
References: <4124@elkarte.net>
Date: Sat, 26 Nov 2016 15:09:15 -0000
X-Mailer: ELK
X-Auto-Response-Suppress: All
Auto-Submitted: auto-generated
List-Id: <elkarteforum@gmail.com>
List-Unsubscribe: <http://www.elkarte.net/community/index.php?action=profile;area=notification>
List-Owner: <mailto:help@elkarte.net> (ElkArte Community)
Mime-Version: 1.0
Content-Type: multipart/alternative; boundary="ELK-66593aefa4beed000470cbd4cc3238d9"
Content-Transfer-Encoding: 7bit
Message-ID: <cd8c399768891330804a1d2fc613ccf3-t4124@elkarte.net>


Testing


Regards, The ElkArte Community

[cd8c399768891330804a1d2fc613ccf3-t4124]

--ELK-66593aefa4beed000470cbd4cc3238d9
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit


Testing


Regards, The ElkArte Community

[cd8c399768891330804a1d2fc613ccf3-t4124]

--ELK-66593aefa4beed000470cbd4cc3238d9--

--ELK-66593aefa4beed000470cbd4cc3238d9
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 7bit


<strong>Testing</strong>


Regards, The ElkArte Community

[cd8c399768891330804a1d2fc613ccf3-t4124]

--ELK-66593aefa4beed000470cbd4cc3238d9--';
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	 * Test that we can read and parse the email
	 */
	public function testMailParse()
	{
		// Parse a simple email
		$email_message = new Email_Parse();
		$email_message->read_email(true, $this->_email);

		// Basics
		$this->assertEquals('"ElkArte Community" <elkarteforum@gmail.com>', $email_message->headers['reply-to']);
		$this->assertEquals('[ElkArte Community] Test Message', $email_message->subject);

		// Its marked as spam
		$email_message->load_spam();
		$this->assertTrue($email_message->spam_found);

		// A few more details
		$email_message->load_address();
		$this->assertEquals('noreply@elkarte.net', $email_message->email['from']);
		$this->assertEquals('drwho@tardis.com', $email_message->email['to'][0]);

		// The key
		$email_message->load_key();
		$this->assertEquals('cd8c399768891330804a1d2fc613ccf3-t4124', $email_message->message_key_id);
		$this->assertEquals('cd8c399768891330804a1d2fc613ccf3', $email_message->message_key);

		// The plain and HTML messages
		$this->assertContains('<strong>Testing</strong>', $email_message->body);
		$this->assertRegExp('/Testing\n/', $email_message->plain_body);

		// The IP
		$this->assertEquals('85.214.104.5', $email_message->load_ip());

		// And some MD as well
		$markdown = pbe_load_text($email_message->html_found, $email_message, array());
		$this->assertContains('[b]Testing[/b]', $markdown);
	}
}