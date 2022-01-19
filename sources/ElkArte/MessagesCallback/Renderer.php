<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\MessagesCallback;

use ElkArte\MembersList;
use ElkArte\MessagesCallback\BodyParser\BodyParserInterface;
use ElkArte\ValuesContainer;

/**
 * Class Renderer
 *
 * The common skeleton to display content via callback.
 * Classes extending this abstract should declare two constants:
 *   - BEFORE_PREPARE_HOOK
 *   - CONTEXT_HOOK
 * that will be strings used as hook names.
 *
 * @package ElkArte\MessagesCallback
 */
abstract class Renderer
{
	/**
	 * The request object
	 *
	 * @var Object
	 */
	protected $_dbRequest = null;

	/**
	 * The current user data
	 *
	 * @var \ElkArte\UserInfo
	 */
	protected $user = null;

	/**
	 * The parser that will convert the body
	 *
	 * @var BodyParserInterface
	 */
	protected $_bodyParser = null;

	/**
	 * The database object
	 *
	 * @var Object
	 */
	protected $_db = null;

	/**
	 * Some options
	 *
	 * @var ValuesContainer
	 */
	protected $_options = null;

	/**
	 * Position tracker, to know where we are into the request
	 *
	 * @var int
	 */
	protected $_counter = 0;

	/**
	 * Should we show the signature of this message?
	 *
	 * @var bool
	 */
	protected $_signature_shown = null;

	/**
	 * The current message being prepared
	 *
	 * @var mixed[]
	 */
	protected $_this_message = null;

	/**
	 * Index mapping, to normalize certain indexes across requests
	 *
	 * @var ValuesContainer
	 */
	protected $_idx_mapper = array();

	/**
	 * Renderer constructor, starts everything.
	 *
	 * @param Object $request
	 * @param Object $user
	 * @param BodyParserInterface $bodyParser
	 * @param ValuesContainer $opt
	 * @throws \Exception
	 */
	public function __construct($request, $user, BodyParserInterface $bodyParser, ValuesContainer $opt = null)
	{
		$this->_dbRequest = $request;
		$this->user = $user;
		$this->_bodyParser = $bodyParser;
		$this->_db = database();
		$this->_idx_mapper = new ValuesContainer([
			'id_msg' => 'id_msg',
			'id_member' => 'id_member',
			'name' => 'poster_name',
			'time' => 'poster_time',
		]);

		// opt:
		// icon_sources
		// show_signatures
		$this->_options = $opt === null ? new ValuesContainer() : $opt;
	}

	/**
	 * The main part: reads the DB resource and returns the complex array
	 * for a certain message.
	 *
	 * @param bool $reset
	 *
	 * @return bool|mixed[]
	 */
	public function getContext($reset = false)
	{
		global $txt, $context;

		// If the query returned false, bail.
		if (!is_object($this->_dbRequest) || $this->_dbRequest->hasResults() === false)
		{
			return false;
		}

		// Remember which message this is.  (ie. reply #83)
		if ($this->_counter === 0 || $reset)
		{
			$this->_counter = empty($context['start']) ? 0 : $context['start'];
		}

		// Start from the beginning...
		if ($reset)
		{
			$this->_currentContext($reset);
		}
		// Attempt to get the next message.
		else
		{
			$this->_currentContext();
		}

		if (empty($this->_this_message))
		{
			return false;
		}

		// If you're a lazy bum, you probably didn't give a subject...
		$this->_this_message['subject'] = $this->_this_message['subject'] !== '' ? $this->_this_message['subject'] : $txt['no_subject'];

		$this->_setupPermissions();

		$id_member = $this->_this_message[$this->_idx_mapper->id_member];
		$member_context = MembersList::get($id_member);
		$member_context->loadContext();

		// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
		if ($member_context->isEmpty())
		{
			$this->_adjustGuestContext($member_context);
		}
		else
		{
			$this->_adjustMemberContext($member_context);
		}
		$this->_adjustAllMembers($member_context);

		// Do the censor thang.
		$this->_this_message['subject'] = censor($this->_this_message['subject']);

		// Run BBC interpreter on the message.
		$this->_this_message['body'] = $this->_bodyParser->prepare($this->_this_message['body'], $this->_this_message['smileys_enabled']);

		call_integration_hook(static::BEFORE_PREPARE_HOOK, array(&$this->_this_message));

		// Compose the memory eat- I mean message array.
		$output = $this->_buildOutputArray();

		call_integration_hook(static::CONTEXT_HOOK, array(&$output, &$this->_this_message, $this->_counter));

		$output['classes'] = implode(' ', $output['classes']);

		$this->_counter++;

		return $output;
	}

	/**
	 * This function receives a request handle and attempts to retrieve the next result.
	 *
	 * What it does:
	 *
	 * - It is used by the controller callbacks from the template, such as
	 * posts in topic display page, posts search results page, or personal messages.
	 *
	 * @param bool $reset
	 *
	 * @return bool
	 */
	protected function _currentContext($reset = false)
	{
		// Start from the beginning...
		if ($reset)
		{
			$this->_dbRequest->data_seek(0);
		}

		// If the query has already returned false, get out of here
		if ($this->_dbRequest->hasResults() === false)
		{
			return false;
		}

		// Attempt to get the next message.
		$this->_this_message = $this->_dbRequest->fetch_assoc();
		if (!$this->_this_message)
		{
			$this->_dbRequest->free_result();

			return false;
		}

		return true;
	}

	/**
	 * Utility function, it shall be implemented by the extending class.
	 * Run just before \ElkArte\MembersList::get()->loadContext is executed.
	 */
	abstract protected function _setupPermissions();

	/**
	 * Utility function, it can be overridden to alter something just after the
	 * members' data have been loaded from the database.
	 * Run only if member exists failed.
	 *
	 * @param \ElkArte\ValuesContainer $member_context
	 */
	protected function _adjustGuestContext($member_context)
	{
		global $txt;

		// Notice this information isn't used anywhere else....
		$member_context['name'] = $this->_this_message[$this->_idx_mapper->name];
		$member_context['id'] = 0;
		$member_context['group'] = $txt['guest_title'];
		$member_context['link'] = $this->_this_message[$this->_idx_mapper->name];
		$member_context['email'] = $this->_this_message['poster_email'] ?? '';
		$member_context['show_email'] = showEmailAddress(true, 0);
		$member_context['is_guest'] = true;
	}

	/**
	 * Utility function, it can be overridden to alter something just after the
	 * members data have been set.
	 * Run only if member doesn't exist succeeded.
	 *
	 * @param \ElkArte\ValuesContainer $member_context
	 */
	protected function _adjustMemberContext($member_context)
	{
		global $context, $modSettings;

		$member_id = $this->_this_message[$this->_idx_mapper->id_member];

		$member_context['can_view_profile'] = allowedTo('profile_view_any') || ($member_id == $this->user->id && allowedTo('profile_view_own'));
		$member_context['is_topic_starter'] = $member_id == $context['topic_starter_id'];
		$member_context['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $member_context['warning_status'] && (!empty($context['user']['can_mod']) || ($this->user->is_guest === false && !empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $member_id == $this->user->id)));

		if ($this->_options->show_signatures === 1)
		{
			if (empty($this->_signature_shown[$member_id]))
			{
				$this->_signature_shown[$member_id] = true;
			}
			else
			{
				$member_context['signature'] = '';
			}
		}
		elseif ($this->_options->show_signatures === 2)
		{
			$member_context['signature'] = '';
		}
	}

	/**
	 * Utility function, it can be overridden to alter something just after either
	 * the members or the guests data have been loaded from the database.
	 * Run both if he member exists or not.
	 *
	 * @param \ElkArte\ValuesContainer $member_context
	 * @return mixed
	 */
	abstract protected function _adjustAllMembers($member_context);

	/**
	 * The most important bit that differentiate the various implementations.
	 * It is supposed to prepare the $output array with all the information
	 * needed by the template to properly render the message.
	 *
	 * The method of the class extending this abstract may run
	 * parent::_buildOutputArray()
	 * as first statement in order to have a starting point and
	 * some commonly used content for the array.
	 *
	 * @return mixed[]
	 */
	protected function _buildOutputArray()
	{
		return array(
			'alternate' => $this->_counter % 2,
			'id' => $this->_this_message[$this->_idx_mapper->id_msg],
			'member' => MembersList::get($this->_this_message[$this->_idx_mapper->id_member]),
			'subject' => $this->_this_message['subject'],
			'html_time' => htmlTime($this->_this_message[$this->_idx_mapper->time]),
			'time' => standardTime($this->_this_message[$this->_idx_mapper->time]),
			'timestamp' => forum_time(true, $this->_this_message[$this->_idx_mapper->time]),
			'counter' => $this->_counter,
			'body' => $this->_this_message['body'],
			'can_see_ip' => allowedTo('moderate_forum') || ($this->_this_message[$this->_idx_mapper->id_member] == $this->user->id && !empty($this->user->id)),
			'classes' => array()
		);

	}
}
