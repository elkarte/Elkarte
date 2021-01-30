<?php

/**
 * Class TestSearchclass
 */
class TestSearchclass extends \PHPUnit\Framework\TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	protected $member_full_access;
	protected $member_limited_access;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUpBeforeClass() is run automatically by the testing framework just once before any test method.
	 */
	public static function setUpBeforeClass()
	{
		// This is here to cheat with allowedTo
		\ElkArte\User::$info = new \ElkArte\ValuesContainer([
			'is_admin' => true,
			'is_guest' => false,
			'possibly_robot' => false,
			'groups' => array(0 => 1),
			'permissions' => array('admin_forum'),
		]);

		// Create 2 boards
		require_once(SUBSDIR . '/Boards.subs.php');
		$board_all_access = createBoard([
			'board_name' => 'Search 1',
			'redirect' => '',
			'move_to' => 'bottom',
			'target_category' => 1,
			'access_groups' => [0,1],
			'deny_groups' => [],
			'profile' => '1',
			'inherit_permissions' => false,
		]);

		$board_limited_access = createBoard([
			'board_name' => 'Search 2',
			'redirect' => '',
			'move_to' => 'bottom',
			'target_category' => 1,
			'access_groups' => [1],
			'deny_groups' => [],
			'profile' => '1',
			'inherit_permissions' => false,
		]);

		// Create 3 users (actually 2)
		// One user must have access to all the boards
		// One user must have access to one board
		// One user must have access to no boards (guests)
		require_once(SUBSDIR . '/Members.subs.php');

		$regOptions1 = [
			'interface' => 'admin',
			'username' => 'Search User 1',
			'email' => 'search@email1.tld',
			'password' => 'password',
			'password_check' => 'password',
			'check_reserved_name' => false,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'send_welcome_email' => false,
			'require' => 'nothing',
			'memberGroup' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'ip2' => long2ip(rand(0, 2147483647)),
			'auth_method' => 'password',
		];
		$member_full_access = registerMember($regOptions1);

		$regOptions2 = [
			'interface' => 'admin',
			'username' => 'Search User 2',
			'email' => 'search@email2.tld',
			'password' => 'password',
			'password_check' => 'password',
			'check_reserved_name' => false,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'send_welcome_email' => false,
			'require' => 'nothing',
			'memberGroup' => 0,
			'ip' => long2ip(rand(0, 2147483647)),
			'ip2' => long2ip(rand(0, 2147483647)),
			'auth_method' => 'password',
		];
		registerMember($regOptions2);

		// Create 2 topics, one for each board
		require_once(SUBSDIR . '/Post.subs.php');
		$msgOptions = [
			'id' => 0,
			'subject' => 'Visible search topic',
			'body' => 'Visible search topic',
			'icon' => '',
			'smileys_enabled' => true,
			'approved' => true,
		];
		$topicOptions = [
			'id' => 0,
			'board' => $board_all_access,
			'lock_mode' => null,
			'sticky_mode' => null,
			'mark_as_read' => true,
			'is_approved' => true,
		];
		$posterOptions = [
			'id' => $member_full_access,
			'ip' => long2ip(rand(0, 2147483647)),
			'name' => 'guestname',
			'email' => $regOptions1['email'],
			'update_post_count' => false,
		];
		createPost($msgOptions, $topicOptions, $posterOptions);

		$msgOptions = [
			'id' => 0,
			'subject' => 'Hidden search topic',
			'body' => 'Hidden search topic',
			'icon' => '',
			'smileys_enabled' => true,
			'approved' => true,
		];
		$topicOptions = [
			'id' => 0,
			'board' => $board_limited_access,
			'lock_mode' => null,
			'sticky_mode' => null,
			'mark_as_read' => true,
			'is_approved' => true,
		];
		$posterOptions = [
			'id' => $member_full_access,
			'ip' => long2ip(rand(0, 2147483647)),
			'name' => 'guestname',
			'email' => $regOptions1['email'],
			'update_post_count' => false,
		];
		createPost($msgOptions, $topicOptions, $posterOptions);
	}

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		require_once(SUBSDIR . '/Auth.subs.php');

		$this->member_full_access = userByEmail('search@email1.tld');
		$this->member_limited_access = userByEmail('search@email2.tld');
	}

	/**
	 * Admins are able to find both topics
	 */
	public function testBasicAdminSearch()
	{
		$this->assertIsInt($this->member_full_access, 'Admin member not found ' . $this->member_full_access);
		$this->loadUser($this->member_full_access);

		$topics = $this->_performSearch();
		$this->assertEquals(2, count($topics), 'Admin search results not correct, found ' . count($topics) . ' instead of 2');
	}

	/**
	 * Normal users with access to one of the two boards are able to find
	 * only one topic
	 */
	public function testBasicMemberSearch()
	{
		$this->assertIsInt($this->member_limited_access, 'Limited member not found ' . $this->member_limited_access);
		$this->loadUser($this->member_limited_access);

		$topics = $this->_performSearch();
		$this->assertEquals(1, count($topics), 'Normal member search results not correct, found ' . count($topics) . ' instead of 1');
	}

	/**
	 * Guests do not have access to the boards, so they cannot find any result
	 */
	public function testBasicGuestSearch()
	{
		$this->loadUser(0);

		$this->expectException('\Exception');
		$this->expectExceptionMessage('query_not_specific_enough');
		$topics = $this->_performSearch();

		$this->assertEquals(0, count($topics), 'Guest search results not correct, found ' . count($topics) . ' instead of 0');
	}

	protected function loadUser($id)
	{
		$db = database();
		$cache = \ElkArte\Cache\Cache::instance();
		$req = request();

		$user = new \ElkArte\UserSettingsLoader($db, $cache, $req);
		$user->loadUserById($id, true, '');
		\ElkArte\User::reloadByUser($user);
	}

	/**
	 * Sets up the search parameters, runs search, and returns the results
	 *
	 * @return mixed[]
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _performSearch()
	{
		global $modSettings, $context;

		$req = \ElkArte\HttpReq::instance();

		$recentPercentage = 0.30;
		$maxMembersToSearch = 500;
		$humungousTopicPosts = 200;
		$maxMessageResults = empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'];
		$search_terms = [
			'search' => 'search',
			'search_selection' => 'all',
			'advanced' => 0
		];
		$req->query->search = $search_terms['search'];

		$search = new \ElkArte\Search\Search();
		$search->setWeights(new \ElkArte\Search\WeightFactors($modSettings, true));
		$search_params = new \ElkArte\Search\SearchParams('');
		$search_params->merge($search_terms, $recentPercentage, $maxMembersToSearch);
		$search->setParams($search_params, !empty($modSettings['search_simple_fulltext']));
		$search->getSearchArray();
		$context['params'] = $search->compileURLparams();

		$search_config = new \ElkArte\ValuesContainer(array(
			'humungousTopicPosts' => $humungousTopicPosts,
			'maxMessageResults' => $maxMessageResults,
			'search_index' => !empty($modSettings['search_index']) ? $modSettings['search_index'] : '',
			'banned_words' => empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']),
		));

		return $search->searchQuery(
			new \ElkArte\Search\SearchApiWrapper($search_config, $search->getSearchParams())
		);
	}

	protected function _getSalt($member)
	{
		$db = database();

		if (empty($member))
		{
			return '';
		}

		$res = $db->fetchQuery('
			SELECT 
				passwd, password_salt
			FROM {db_prefix}members
			where id_member = {int:id_member}',
			array(
				'id_member' => $member
			)
		)->fetch_all();

		if (empty($res))
		{
			return '';
		}

		return hash('sha256', ($res[0]['passwd'] . $res[0]['password_salt']));
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}
}
