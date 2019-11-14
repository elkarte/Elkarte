<?php

use ElkArte\Errors\ErrorContext;

/**
 * TestCase class for members subs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestMembers extends \PHPUnit\Framework\TestCase
{
	private $memberID = null;
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $txt;

		$txt['guest_title'] = 'Guest';
		$txt['ban_register_prohibited'] = 'Sorry, you are not allowed to register on this forum.';

		require_once(SUBSDIR . '/Members.subs.php');

		$regOptions = array(
			'interface' => 'tests',
			'username' => 'in-test user',
			'email' => 'an.email@address.tld',
			'password' => '12345678',
			'password_check' => '12345678',
			'check_reserved_name' => true,
			'check_password_strength' => true,
			'check_email_ban' => true,
			'send_welcome_email' => false,
			'require' => 'nothing',
			'memberGroup' => 0,
			'ip' => '127.0.0.2',
			'ip2' => '127.0.0.3',
			'auth_method' => 'password',
		);

		$reg_errors = ErrorContext::context('register', 0);
		$this->memberID = registerMember($regOptions, 'register');

		// First test is here: there should be no errors during the registration
		$this->assertFalse($reg_errors->hasErrors());
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	 * Find a member by IP
	 */
	public function testMemberByIP()
	{
		// Default is exact and no IP2
		$result = membersByIP('127.0.0.2');
		$this->assertEquals($this->memberID, $result[0]['id_member']);

		// A test with relaxed query
		$result = membersByIP('127.0.0.*', 'relaxed');
		$mem_id = array();
		foreach ($result as $mem)
			$mem_id[] = $mem['id_member'];
		$this->assertTrue(in_array($this->memberID, $mem_id));

		// An hopefully non existing IP
		$result = membersByIP('127.0.0.3');
		$this->assertTrue(empty($result));

		// Again
		$result = membersByIP('127.0.*.5', 'relaxed');
		$this->assertTrue(empty($result));

		// Now let's check IP2
		$result = membersByIP('127.0.0.3', 'exact', true);
		$this->assertEquals($this->memberID, $result[0]['id_member']);
	}
}
