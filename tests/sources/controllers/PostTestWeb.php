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
	public function setUpPage($url = '', $login = false)
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

    	// Should be back at the post page
		sleep(3);
		$this->assertStringContainsString('Some post', $this->byCssSelector(".messageContent")->text(), $this->source());
	}
}
