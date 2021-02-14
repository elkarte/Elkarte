<?php

/**
 * TestCase class for profile forum actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class ProfileForumController extends ElkArteWebSupport
{
	/**
	 * Called just before a test run, but after setUp() use to
	 * auto login or set a default page for initial browser view
	 */
	public function setUpPage()
	{
		$this->url = 'index.php?action=profile;area=forumprofile';
		$this->login = true;
		parent::setUpPage();
	}

	/**
	 * Lets set an avatar
	 *
	 * It should set an avatar from one of the available ones
	 *
	 */
	public function testAvatar()
	{
		// Lets choose an avatar
		$this->byId('avatar_choice_server_stored')->click();
		$this->waitUntil(function ($testCase)
		{
			try
			{
				return $testCase->byId('cat');
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 8000);
		$this->byXPath("//option[. = '[Oxygen]']")->click();

		$this->waitUntil(function ($testCase)
		{
			try
			{
				return $testCase->byId('file');
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 8000);
		$this->byXpath("//option[. = 'wine']")->click();

		$this->byName('save')->click();

		// We return to the forum profile page
		$this->url('index.php?action=profile;area=forumprofile');
		$this->assertStringContainsString('wine', $this->byId('avatar')->attribute('alt'));
	}

	/**
	 * Lets set our signature
	 */
	public function testSignature()
	{
		// Lets set a signature, something profound
		$this->byId('signature')->click();
		$this->keys('A Signature');
		$this->byName('save')->click();

		// We return to the forum profile page
		$this->url('index.php?action=profile;area=forumprofile');
		$this->assertStringContainsString('A Signature', $this->byId('signature')->text());
	}
}
