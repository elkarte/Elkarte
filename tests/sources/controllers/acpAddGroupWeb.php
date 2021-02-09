<?php

/**
 * TestCase class for ACP add group controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class SupportManageMembergroupsController extends ElkArteWebSupport
{
	public function setUpPage($url = '', $login = false)
	{
		$this->url = 'index.php';
		$this->login = true;
		parent::setUpPage();
	}

	/**
	 * Add a group
	 *
	 * Depends on the fact that we are logged in to the ACP
	 *
	 */
	public function testAcpAddGroup()
	{
		// Login the admin in to the ACP
		$this->timeouts()->implicitWait(10000);
		$this->enterACP();

		// Start at the add member group page
		$this->url('index.php?action=admin;area=membergroups;sa=add');
		$this->assertEquals("Add Member Group", $this->title(), $this->source());

		// Fill in the new group form with initial values
		$this->ById('group_name_input')->click();
		$this->keys('Test Group');
		$this->byId('checkall_check')->click();
		$this->clickit('input[value="Add group"]');

		// Group Details, give it a description, icons, etc
		$this->assertEquals("Edit Membergroup", $this->title(), $this->source());
		$this->ById('group_desc_input')->click();
		$this->keys('The Test Group');
		$this->ById('icon_count_input')->click();
		$this->keys('2');
		$this->clickit('input[name="save"]');

		// We should be back at the group listing, the new group should be there
		$this->assertEquals("Manage Membergroups", $this->title(), $this->source());
		$this->assertStringContainsString('Test Group', $this->byCssSelector('#list_regular_membergroups_list_9')->text(), $this->source());
	}
}
