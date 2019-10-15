<?php

/**
 * TestCase class for managing members
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class TestManageMembersController extends ElkArteWebTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	public function registerMembers()
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$require = array('activation', 'approval');
		$_SESSION['just_registered'] = 0;
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

			// Will show sh: 1: sendmail: not found in the travis console
			registerMember($regOptions);
			$_SESSION['just_registered'] = 0;
		}
	}

	public function activateMember($mname, $act, $el)
	{
		// First, navigate to member management.
		$this->url('index.php?action=admin;area=viewmembers;sa=browse;type=' . $act);
		$this->assertEquals('Manage Members', $this->title());

		// Let's do it: approve/delete...
		$this->assertContains('new accounts', $this->byCssSelector('.generic_menu li a:last-child')->text());
		$this->clickit('.generic_menu li a:last-child');
		$this->assertContains($mname, $this->byCssSelector('#list_approve_list_0')->text());
		$this->clickit('#list_approve_list_0 input');
		$this->clickit('select[name=todo] option[value=' . $el . ']');

		// htmlunit does not seem to work with the alert code
		if (in_array($this->browser, array('firefox', 'chrome')))
		{
			$this->assertContains('all selected members?', $this->alertText());
			$this->acceptAlert();
		}
		else
		{
			$this->keys($this->keysHolder->specialKey('ENTER'));
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testApproveMember()
	{
		// First, we register some members...
		$this->registerMembers();

		// Then, navigate to member management.
		$this->activateMember('user1', 'approve', 'ok');

		// Login the admin in to the ACP
		$this->adminLogin();
		$this->enterACP();

		// Finally, ensure they have been approved.
		$this->url('index.php?action=admin;area=viewmembers;sa=search');
		$this->clickit('#activated-1');
		$this->clickit('#activated-2');
		$this->clickit('input[value=Search]');
		$this->assertContains('user1', $this->byId('member_list')->text());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testActivateMember()
	{
		$this->activateMember('user0', 'activate', 'delete');

		// Login the admin in to the ACP
		$this->adminLogin();
		$this->enterACP();

		// Should be gone.
		$this->url('index.php?action=admin;area=viewmembers;sa=all');
		$this->assertNotContains('user0', $this->byId('member_list')->text());
	}
}
