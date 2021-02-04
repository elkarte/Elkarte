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
	/**
	 * Add a group
	 *
	 * Depends on the fact that we are logged in to the ACP
	 *
	 */
	public function testAcpAddGroup()
	{
		// Login the admin in to the ACP
		$this->adminQuickLogin();
		$this->enterACP();

		// Start at the add member group page
		$this->url('index.php?action=admin;area=membergroups;sa=add');
		$this->assertEquals("Add Member Group", $this->title());

		// Fill in the new group form with initial values
		$this->ById('group_name_input')->value('Test Group');
		$this->clickit('#checkall_check');
		$this->clickit('input[value="Add group"]');

		// Group Details, give it a description, icons, etc
		$this->assertEquals("Edit Membergroup", $this->title());
		$this->ById('group_desc_input')->value('The Test Group');
		$this->ById('icon_count_input')->value('2');
		$this->clickit('input[name="save"]');

		// We should be back at the group listing, the new group should be there
		$this->assertEquals("Manage Membergroups", $this->title());
		$this->assertStringContainsString('Test Group', $this->byCssSelector('#list_regular_membergroups_list_9')->text());
	}

	/**
	 * Try to logout with action=logout
	 *
	 * @todo This does not seem to work yet. Rename to test when working
	 */
	public function Logout()
	{
		// In order to interact with hidden elements, we need to expose them.
		// Here we hover (move) over the profile button
		// so the logout button is visible, such that we can click it.
		$this->timeouts()->implicitWait(10000);
		$this->moveto(array(
			'element' => $this->byId('button_profile'),
			'xoffset' => 10,
			'yoffset' => 10,
			)
		);
		$this->clickit('#button_logout > a');
		$this->assertStringContainsString('Log In', $this->byCssSelector('#button_login > a')->text());
	}
}
