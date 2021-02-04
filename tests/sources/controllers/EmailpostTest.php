<?php

use ElkArte\Controller\Emailpost;
use ElkArte\EventManager;

/**
 * TestCase class for the EmailPost Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestEmailPostController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	protected $data = '';

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $modSettings;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();

		$modSettings['maillist_enabled'] = true;
		$modSettings['pbe_post_enabled'] = true;
		$_POST['item'] = '';

		$this->data = 'Return-Path: <noreply@elkarte.net>
Delivered-To: <email@testadmin.tld>
Received: from galileo.tardis.com
	by galileo.tardis.com (Dovecot) with LMTP id znQ3AvalOVi/SgAAhPm7pg
	for <email@testadmin.tld>; Sat, 26 Nov 2016 09:10:46 -0600
Received: by galileo.tardis.com (Postfix, from userid 1005)
	id 0671C1C8; Sat, 26 Nov 2016 09:10:46 -0600 (CST)
X-Spam-Checker-Version: SpamAssassin 3.4.0 (2014-02-07) on
	galileo.tardis.com
X-Spam-Flag: No
X-Spam-Level: ******
X-Spam-Status: No, score=4.0 required=5.0 tests=HTML_IMAGE_ONLY_16,
	HTML_MESSAGE,HTML_SHORT_LINK_IMG_2,MIME_HTML_ONLY,MIME_HTML_ONLY_MULTI,
	MPART_ALT_DIFF,RCVD_IN_BRBL_LASTEXT,T_DKIM_INVALID,URIBL_BLACK autolearn=no
	autolearn_force=no version=3.4.0
Received: from mail.elkarte.net (s2.eurich.de [85.214.104.5])
	by galileo.tardis.com (Postfix) with ESMTP id 1872579
	for <email@testadmin.tld>; Sat, 26 Nov 2016 09:10:40 -0600 (CST)
Received: from localhost (localhost [127.0.0.1])
	by mail.elkarte.net (Postfix) with ESMTP id 9DE3C4CE1535
	for <email@testadmin.tld>; Sat, 26 Nov 2016 16:10:39 +0100 (CET)
X-Virus-Scanned: Debian amavisd-new at s2.eurich.de
Received: from mail.elkarte.net ([127.0.0.1])
	by localhost (h2294877.stratoserver.net [127.0.0.1]) (amavisd-new, port 10024)
	with ESMTP id zQep5x32jrqA for <email@testadmin.tld>;
	Sat, 26 Nov 2016 16:10:03 +0100 (CET)
Received: from mail.elkarte.net (h2294877.stratoserver.net [85.214.104.5])
	by mail.elkarte.net (Postfix) with ESMTPA id 990694CE0CFA
	for <email@testadmin.tld>; Sat, 26 Nov 2016 16:10:03 +0100 (CET)
Subject: [ElkArte Community] Test Message
To: <email@testadmin.tld>
From: "Administrator" <email@testadmin.tld>
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
Message-ID: <cd8c399768891330804a1d2fc613ccf3-t1@elkarte.net>


Testing


Regards, The ElkArte Community

[cd8c399768891330804a1d2fc613ccf3-t1]

--ELK-66593aefa4beed000470cbd4cc3238d9
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit


Testing


Regards, The ElkArte Community

[cd8c399768891330804a1d2fc613ccf3-t1]

--ELK-66593aefa4beed000470cbd4cc3238d9--

--ELK-66593aefa4beed000470cbd4cc3238d9
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 7bit


<strong>Testing</strong>


Regards, The ElkArte Community

[cd8c399768891330804a1d2fc613ccf3-t1]

--ELK-66593aefa4beed000470cbd4cc3238d9--;';
	}

	/**
	 * Test trying looking at a pbe message
	 */
	public function testActionPbePreview()
	{
		// Get the controller, call preview
		$controller = new Emailpost(new EventManager());
		$result = $controller->action_pbe_preview($this->data);

		// Check that the preview was set
		$this->assertEquals('email@testadmin.tld', $result['to'], $result['to']);
		$this->assertStringContainsString('[b]Testing[/b]', $result['body'], $result['body']);
	}

	/**
	 * Test trying to post at a pbe message
	 */
	public function testActionPbePost()
	{
		// Get the controller, call preview
		$controller = new Emailpost(new EventManager());
		$controller->action_pbe_post($this->data, false);

		// We will fail since the key does not exist
		$this->assertEquals('It appears that you already replied to this email.  If you need to modify your post please use the web interface, if you are making another reply to this topic please reply to the latest notification', $_SESSION['email_error'], $_SESSION['email_error']);
	}

	/**
	 * Test trying to start a pbe topic, its not setup so this will fail as well ;)
	 */
	public function testActionPbeTopic()
	{
		// Get the controller, call preview
		$controller = new Emailpost(new EventManager());
		$controller->action_pbe_topic($this->data);

		// We will fail since the key does not exist
		$this->assertEquals('Attempted to start a new topic to a non existing board, potential hacking attempt', $_SESSION['email_error'], $_SESSION['email_error']);
	}
}