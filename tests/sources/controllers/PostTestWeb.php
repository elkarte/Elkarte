<?php

/**
 * TestCase class for profile summary info actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class PostTestWebController extends ElkArteWebSupport
{
	/**
	 * Called just before a test run, but after setUp() use to
	 * auto login or set a default page for initial browser view
	 */
	public function setUpPage()
	{
		$this->url = 'index.php';
		$this->login = true;
		parent::setUpPage();
	}

	/**
	 * Lets make a post!
	 */
	public function testMakePost()
	{
		// First go to the first board index
		$this->byId("b1")->click();
		sleep(2);

		// Start a new topic
		$this->byXpath("//*[@id='main_content_section']/nav[1]/ul[2]/li[1]/a")->click();
		//$this->byCssSelector(''".pagesection:nth-child(3) .button_strip_new_topic")->click();

		// Subject, Body, Post
		$this->byId("post_subject")->click();
		$this->keys("Some Subject");

		$this->byCssSelector(".forumposts")->click();
		$this->byCssSelector(".sceditor-container > textarea")->click();
		$this->keys("Some post");

		$this->byName("post")->click();

		// Wait for it to post
		$this->waitUntil(function ($testCase)
		{
			try
			{
				return $testCase->byCssSelector('section.messageContent');
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 5000);

		// Should be back at the post page
		$this->assertStringContainsString('Some post', $this->byCssSelector("section.messageContent")->text(), $this->source());
	}
}
