<?php

/**
 * TestCase class for the PreparseMail and BuildMail Controllers
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

namespace ElkArte\Mail;

use ElkArte;
use ElkArte\Languages\Loader;
use tests\ElkArteCommonSetupTest;

class MailTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];
	protected $data = '';
	protected $replacements;

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $modSettings, $txt;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		$this->replacements = [
			'TOPICSUBJECT' => 'Psycho Rant',
			'POSTERNAME' => 'Internet Warrior',
			'SIGNATURE' => 'The internet has made me <strong>mental</strong> :)',
			'BOARDNAME' => 'Some Board',
			'SUBSCRIPTION' => '1234',
			'BODY' => 'We need [b]some[/b] cruft :smile: here so we can [icode]test [b]not bold[/b][/icode] test',
		];

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('EmailTemplates+MaillistTemplates');

		$modSettings['maillist_enabled'] = true;
	}

	public function testPreParse()
	{
		$mailPreparse = new PreparseMail();

		$subject = $mailPreparse->preparseSubject($this->replacements['TOPICSUBJECT']);
		$body = $mailPreparse->preparseHtml($this->replacements['BODY']);
		$signature = $mailPreparse->preparseSignature($this->replacements['SIGNATURE']);

		$this->assertEquals('Psycho Rant', $subject);

		// render emoji to entities, protect code block, icode
		$this->assertEquals('We need <strong class="bbc_strong">some</strong> cruft&#x1f604;here so we can <span class="bbc_code_inline">test [b]not bold[/b]</span> test', $body);

		// Don't render html in sig, not bold, no smiles
		$this->assertEquals('<hr />The internet has made me mental :)', $signature);
	}

	/**
	 * Test trying looking at a pbe message
	 */
	public function testBuildMail()
	{
		global $modSettings;

		// Use the queue so we can see what is created
		$modSettings['mail_queue'] = 1;

		$mailPreparse = new PreparseMail();

		$this->replacements['TOPICSUBJECT'] = $mailPreparse->preparseSubject($this->replacements['TOPICSUBJECT']);
		$this->replacements['BODY'] = $mailPreparse->preparseHtml($this->replacements['BODY']);
		$this->replacements['SIGNATURE'] = $mailPreparse->preparseSignature($this->replacements['SIGNATURE']);

		// This should build and add it to the queue
		$sendMail = new BuildMail();
		$sendMail->setEmailReplacements($this->replacements);
		$sendMail->buildEmail(
			'a@a.com',
			$this->replacements['TOPICSUBJECT'],
			$this->replacements['BODY'],
			null,
			'm123',
			true);

		// Flush it to the db
		AddMailQueue(true);

		// And now grab it to see what we have
		list($id, $email) = emailsInfo(1);

		$email = $email[0];

		// Sniff the Headers
		$this->assertStringContainsString('X-Mailer: ELK', $email['headers']);
		$this->assertStringContainsString('Mime-Version: 1.0', $email['headers']);
		$this->assertStringContainsString('Content-Type: multipart/alternative;', $email['headers']);
		$this->assertStringContainsString('List-Unsubscribe:', $email['headers']);
		$this->assertStringNotContainsString('List-Unsubscribe-Post', $email['headers']);

		// Sniff for Plain section (quoted printable UTF-8)
		$this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $email['body']);
		$this->assertStringContainsString('Content-Transfer-Encoding: Quoted-Printable', $email['body']);

		// Sniff HTML Quoted Printable Section
		$this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $email['body']);
		$this->assertStringContainsString('s=3D"bbc_strong">some</strong> cruft&#x1f604;here so we can <span class=3D"=', $email['body']);
	}

	/**
	 * Test building plain text only email (no multipart container)
	 */
	public function testBuildMailPlainText()
	{
		global $modSettings;

		$modSettings['mail_queue'] = 1;

		$sendMail = new BuildMail();
		$sendMail->buildEmail(
			'a@a.com',
			'Plain Subject',
			'This is a plain text message.',
			null,
			'm124',
			false);

		AddMailQueue(true);

		list($id, $email) = emailsInfo(1);
		$email = $email[0];

		// Headers for single part plain text
		$this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $email['headers']);
		$this->assertStringContainsString('Content-Transfer-Encoding: Quoted-Printable', $email['headers']);
		$this->assertStringNotContainsString('multipart/alternative', $email['headers']);

		// Body contains only plain text quoted-printable
		$this->assertStringContainsString('This is a plain text message.', $email['body']);
		$this->assertStringNotContainsString('Content-Type: text/html', $email['body']);
	}

	/**
	 * Test CSS caching in BuildMail
	 */
	public function testEmailCssCaching()
	{
		BuildMail::resetEmailCss();
		$sendMail = new BuildMail();
		$css1 = $sendMail->getEmailCss();
		$css2 = $sendMail->getEmailCss();

		$this->assertSame($css1, $css2);
	}
}