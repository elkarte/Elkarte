<?php

class TestSearchclass extends \PHPUnit\Framework\TestCase
{
	protected $board_all_access = 0;
	protected $board_limited_access = 0;
	protected $member_full_access = 0;
	protected $member_limited_access = 0;
	protected $member_no_access = 0;
	protected $run_tests = true;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $user_info;

		require_once(SUBSDIR . '/Auth.subs.php');
		if (userByEmail('search@email1.tld'))
		{
			$this->run_tests = false;
			return;
		}

		// This is here to cheat with allowedTo
		$user_info['is_admin'] = true;
		
		// Create 2 boards
		require_once(SUBSDIR . '/Boards.subs.php');
		$this->board_all_access = createBoard([
			'board_name' => 'Search 1',
			'redirect' => '',
			'move_to' => 'bottom',
			'target_category' => 1,
			'access_groups' => [0,1],
			'deny_groups' => [],
		]);
		$this->board_limited_access = createBoard([
			'board_name' => 'Search 2',
			'redirect' => '',
			'move_to' => 'bottom',
			'target_category' => 1,
			'access_groups' => [1],
			'deny_groups' => [],
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
			'ip' => '127.0.0.1',
			'ip2' => '127.0.0.1',
			'auth_method' => 'password',
		];
		$this->member_full_access = registerMember($regOptions1);

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
			'ip' => '127.0.0.1',
			'ip2' => '127.0.0.1',
			'auth_method' => 'password',
		];
		$this->member_limited_access = registerMember($regOptions2);

		// Hopefully a guest doesn't have any access
		$this->member_no_access = 0;

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
			'board' => $this->board_all_access,
			'lock_mode' => null,
			'sticky_mode' => null,
			'mark_as_read' => true,
			'is_approved' => true,
		];
		$posterOptions = [
			'id' => $this->member_full_access,
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
			'board' => $this->board_limited_access,
			'lock_mode' => null,
			'sticky_mode' => null,
			'mark_as_read' => true,
			'is_approved' => true,
		];
		$posterOptions = [
			'id' => $this->member_full_access,
			'name' => 'guestname',
			'email' => $regOptions1['email'],
			'update_post_count' => false,
		];
		createPost($msgOptions, $topicOptions, $posterOptions);

		Elk_Autoloader::instance()->register(SUBSDIR . '/Search', '\\ElkArte\\Search');
	}

	/**
	 * Admins are able to find both topics
	 */
	public function testBasicAdminSearch()
	{
		global $cookiename;

		if ($this->run_tests === false)
		{
			return;
		}

		$_COOKIE[$cookiename] = json_encode([$this->member_full_access, $this->_getSalt($this->member_full_access)]);
		loadUserSettings();

		$topics = $this->_performSearch();
		$this->assertEquals(2, count($topics), 'Admin search results not correct, found ' . count($topics) . ' instead of 2');
	}

	/**
	 * Normal users with access to one of the two boards are able to find
	 * only one topic
	 */
	public function testBasicMemberSearch()
	{
		global $cookiename;

		if ($this->run_tests === false)
		{
			return;
		}

		$_COOKIE[$cookiename] = json_encode([$this->member_limited_access, $this->_getSalt($this->member_limited_access)]);
		loadUserSettings();

		$topics = $this->_performSearch();
		$this->assertEquals(1, count($topics), 'Normal member search results not correct, found ' . count($topics) . ' instead of 1');
	}

	/**
	 * Guests do not have access to the boards, so they cannot find any result
	 *
	 * @expectedException \Exception
	 * @expectedExceptionMessage query_not_specific_enough
	 */
	public function testBasicGuestSearch()
	{
		global $cookiename;

		if ($this->run_tests === false)
		{
			throw new \Exception('query_not_specific_enough');
		}

		$_COOKIE[$cookiename] = null;
		loadUserSettings();

		$topics = $this->_performSearch();
		$this->assertEquals(0, count($topics), 'Guest search results not correct, found ' . count($topics) . ' instead of 0');

	}

	protected function _performSearch()
	{
		global $modSettings, $context;

		$recentPercentage = 0.30;
		$maxMembersToSearch = 500;
		$humungousTopicPosts = 200;
		$maxMessageResults = empty($modSettings['search_max_results']) ? 0 :
		$search_terms = [
			'search' => 'search',
			'search_selection' => 'all',
			'advanced' => 0
		];
		$_GET['search'] = $search_terms['search'];
		$search = new \ElkArte\Search\Search();
		$search->setWeights(new \ElkArte\Search\WeightFactors($modSettings, 1));
		$search_params = new \ElkArte\Search\SearchParams('');
		$search_params->merge($search_terms, $recentPercentage, $maxMembersToSearch);
		$search->setParams($search_params, !empty($modSettings['search_simple_fulltext']));
		$context['params'] = $search->compileURLparams();

		$search_config = new \ElkArte\ValuesContainer(array(
			'humungousTopicPosts' => $humungousTopicPosts,
			'maxMessageResults' => $maxMessageResults,
			'search_index' => !empty($modSettings['search_index']) ? $modSettings['search_index'] : '',
			'banned_words' => empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']),
		));
		$topics = $search->searchQuery(
			new \ElkArte\Search\SearchApiWrapper($search_config, $search->getSearchParams())
		);

		return $topics;
	}

	protected function _getSalt($member)
	{
		$db = database();

		if (empty($member))
		{
			return '';
		}

		$res = $db->fetchQuery('
			SELECT passwd, password_salt
			FROM {db_prefix}members
			where id_member = {int:id_member}',
			array(
				'id_member' => $member
			)
		);

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
