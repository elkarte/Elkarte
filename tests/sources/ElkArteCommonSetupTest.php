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

abstract class ElkArteCommonSetupTest extends \PHPUnit\Framework\TestCase
{
	private $session = false;
	public $userData = [];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		global $modSettings, $settings;

		// Lets add in just enough info for the system to think we are logged
		$modSettings['smiley_sets_known'] = 'none';
		$modSettings['smileys_url'] = 'http://127.0.0.1/smileys';
		$modSettings['default_forum_action'] = [];
		$settings['default_theme_dir'] = '/var/www/themes/default';

		$userData = [
			'id' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'testing',
			'query_wanna_see_board' => '1=1',
			'query_see_board' => '1=1',
			'is_moderator' => false,
			'email' => 'a@a.com',
			'ignoreusers' => array(),
			'name' => 'itsme',
			'smiley_set' => 'none',
			'time_offset' => 0,
			'time_format' => '',
			'possibly_robot' => false,
			'posts' => '15',
			'buddies' => array(),
			'groups' => array(0 => 1),
			'ignoreboards' => array(),
			'avatar' => array('url' => '', 'name' => ''),
			'permissions' => array('admin_forum'),
			'date_registered' => time() - 86400,
		];

		\ElkArte\User::$info = new \ElkArte\UserInfo($userData);
		\ElkArte\User::load();

		$settings['page_index_template'] = array(
			'base_link' => '<li></li>',
			'previous_page' => '<span></span>',
			'current_page' => '<li></li>',
			'next_page' => '<span></span>',
			'expand_pages' => '<li></li>',
			'all' => '<span></span>',
		);
	}

	public function setSession()
	{
		global $modSettings, $context;

		// Trick the session and other checks
		$_POST['elk_test_session'] = 'elk_test_session';
		$_GET['elk_test_session'] = 'elk_test_session';
		$_SESSION['session_value'] = 'elk_test_session';
		$_SESSION['session_var'] = 'elk_test_session';
		$_SESSION['USER_AGENT'] = 'elkarte';
		$_SESSION['admin_time'] = time() + 600;
		$modSettings['disableCheckUA'] = 1;
		$context['session_var'] = 'elk_test_session';
		$context['session_value'] = 'elk_test_session';

		$this->session = true;
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		global $modSettings, $settings;

		// remove temporary test data
		unset($settings, $modSettings);
		\ElkArte\User::$info = null;

		if ($this->session)
		{
			unset($_SESSION['session_value'], $_SESSION['session_var'], $_SESSION['USER_AGENT'], $_SESSION['admin_time']);
		}
	}
}