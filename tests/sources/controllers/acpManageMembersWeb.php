<?php

/**
 * TestCase class for managing members
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class SupportManageMembersController extends ElkArteWebSupport
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
	 * Register some members, user0, user1, user2
	 */
	public function registerMembers()
	{
		new ElkArte\Themes\ThemeLoader();

		require_once(SUBSDIR . '/Members.subs.php');
		$require = array('activation', 'approval');
		$_SESSION['just_registered'] = 0;

		// Register a couple of members, like user0, user1
		for ($i = 0; $i < 2; $i++)
		{
			$regOptions = array(
				'interface' => 'admin',
				'username' => 'user' . $i,
				'email' => 'user' . $i . '@mydomain.com',
				'password' => 'user' . $i,
				'password_check' => 'user' . $i,
				'require' => $require[$i],
				'memberGroup' => 2,
			);

			// Will show sh: 1: sendmail: not found in the CI console
			$id = registerMember($regOptions);

			// Depends a bit on when it runs, but normally will be 4, 5
			$this->assertContains($id, [2, 3, 4, 5], 'Unexpected MemberID: ' . $id);
			$_SESSION['just_registered'] = 0;
		}
	}

	/**
	 * Activate a member that was registered, utility function for other
	 * test functions in this group.
	 *
	 * @param string $mname
	 * @param string $act
	 * @param string $el
	 */
	public function activateMember($mname, $act, $el)
	{
		// First, navigate to member management.
		$this->url('index.php?action=admin;area=viewmembers;sa=browse;type=' . $act);
		$this->assertEquals('Manage Members', $this->title(), $this->source());

		$this->assertStringContainsString($mname, $this->byCssSelector('#list_approve_list_0')->text());
		$this->clickit('#list_approve_list_0 input');

		// Submit the form, catch the exception thrown (at least by chrome)
		try
		{
			$this->select($this->byName('todo'))->selectOptionByValue($el);
			$this->select($this->byName('todo'))->submit();
		}
		catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
		{
			// At least chromedriver will throw an exception
		}
		finally
		{
			// htmlunit does not seem to work with the alert code
			if (in_array($this->browser, array('firefox', 'chrome')))
			{
				$this->assertStringContainsString('all selected members?', $this->alertText());
				$this->acceptAlert();
			}
			else
			{
				$this->keys($this->keysHolder->specialKey('ENTER'));
			}
		}
	}

	/**
	 * Register and activate some meatheads, uh ... members
	 *
	 * This used to run in runInSeparateProcess, but after 5 years some update just made
	 * that process fail, so additonal ACP detection logic has been added.
	 */
	public function testApproveMember()
	{
		// First, we register some members...
		$this->registerMembers();

		// Login the admin in to the ACP
		$this->enterACP();

		// Then, navigate to member management.
		$this->activateMember('user1', 'approve', 'ok');

		// Finally, ensure they have been approved.
		$this->url('index.php?action=admin;area=viewmembers;sa=search');
		$this->waitUntil(function ($testCase) {
			try
			{
				return $testCase->byId('activated-1');
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 15000);

		// Unselect Not Activated and Banned so we only see activated members
		$this->byId('activated-1')->click();
		$this->byId('activated-2')->click();
		$this->clickit('input[value=Search]');
		$this->assertStringContainsString('user1', $this->byId('member_list')->text());
	}

	/**
	 * Activate a member, as above used to runInSeparateProcess
	 */
	public function testRejectActivateMember()
	{
		// Login the admin in to the ACP
		$this->enterACP();

		// Lets delete this request
		$this->activateMember('user0', 'activate', 'delete');

		// Should be gone.
		$this->url('index.php?action=admin;area=viewmembers;sa=all');
		$this->waitUntil(function ($testCase) {
			try
			{
				return $testCase->byId('member_list');
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 10000);

		$this->assertStringNotContainsString('user0', $this->byId('member_list')->text());
	}
}
