<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Helper\Util;
use ElkArte\MembersList;
use PHPUnit\Framework\TestCase;

class AuthSubsTest extends TestCase
{
	protected $passwd = 'test_admin_pwd';
	protected $user = 'test_admin';
	protected $useremail = 'email@testadmin.tld';
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Auth.subs.php');
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * We run this test in a separate process to prevent headers already sent errors
	 * when the cookie is generated.
	 *
	 * @runInSeparateProcess
	 */
	public function blocked_test_login_cookie()
	{
		global $cookiename, $context;

		// Lets test load data, this should be id #1 for the testcase
		$user_data = MembersList::load($this->user, true, 'profile');
		$member = MembersList::get($user_data[0]);

		$this->assertEquals(1, $user_data[0]);
		$salt = $member->password_salt;
		setLoginCookie(60 * 60, $member->id_member, hash('sha256', $this->passwd . $salt));

		// Cookie should be set, with our values
		$array = json_decode($_COOKIE[$cookiename]);
		$this->assertEquals($array[1], hash('sha256', $this->passwd . $salt));
	}

	/**
	 * Validate a user password
	 */
	public function test_password()
	{
		// Load them with loadExistingMember
		$user_data = loadExistingMember($this->user, false);
		$this->assertNotEmpty($user_data);

		// What a form could send in
		$password = hash('sha256', Util::strtolower($this->user) . un_htmlspecialchars($this->passwd));

		// Check secure and insecure forms
		$this->assertTrue(validateLoginPassword($password, $user_data['passwd']));
		$this->assertTrue(validateLoginPassword($this->passwd, $user_data['passwd'], $this->user));
	}

	/**
	 * Load a user by email etc.  Then send in a bogus password
	 */
	public function test_false_password()
	{
		// Get the id with the userByEmail function
		$user_id = userByEmail($this->useremail);

		// Then load them with loadExistingMember by id
		$user_data = loadExistingMember($user_id, true);
		$this->assertEquals($user_data['member_name'], $this->user);

		// Send in a hashed password, like it was stolen
		$this->assertFalse(validateLoginPassword($user_data['passwd'], $user_data['passwd']));

		// Or just a plain text one
		$this->assertFalse(validateLoginPassword($this->passwd, $user_data['passwd']));
	}
}
