<?php

use ElkArte\Mail\BuildMail;
use ElkArte\Mail\PreparseMail;
use ElkArte\Languages\Loader;

/**
 * TestCase class for the PreparseMail and BuildMail Controllers
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestMail extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
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

		// Sniff for Plain section
		$this->assertStringContainsString('We need **some** cruft&#128516;here so we can `test \[b\]not bold\[/b\]` test', $email['body']);

		// Sniff for base64 section
		$this->assertStringContainsString('V2UgbmVlZCAqKnNvbWUqKiBjcnVmdPCfmIRoZXJlIHNvIHdlIGNhbiBgdGVzdCBcW2JcXW5vdCBi', $email['body']);

		// Sniff HTML Quoted Printable Section
		$this->assertStringContainsString('s=3D"bbc_strong">some</strong> cruft&#x1f604;here so we can <span class=3D"=', $email['body']);
	}
}