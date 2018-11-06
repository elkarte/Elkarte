<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\MessagesCallback;

use \ElkArte\MessagesCallback\BodyParser\BodyParserInterface;
use \ElkArte\ValuesContainer;

/**
 * Renderer
 *
 * The common skeleton to display content via callback.
 * Classes extending this abstract should declare two constants:
 *   - BEFORE_PREPARE_HOOK
 *   - CONTEXT_HOOK
 * that will be strings used as hook names.
 */
abstract class Renderer
{
	/**
	 * The request object
	 * @var Object
	 */
	protected $_dbRequest = null;

	/**
	 * The parser that will convert the body
	 * @var BodyParserInterface
	 */
	protected $_bodyParser = null;

	/**
	 * The database object
	 * @var Object
	 */
	protected $_db = null;

	/**
	 * Some options
	 * @var ValuesContainer
	 */
	protected $_options = null;

	/**
	 * Position tracker, to know where we are into the request
	 * @var int
	 */
	protected $_counter = 0;

	/**
	 * Should we show the signature of this message?
	 * @var bool
	 */
	protected $_signature_shown = null;

	/**
	 * The current message being prepared
	 * @var mixed[]
	 */
	protected $_this_message = null;

	/**
	 * Index mapping, to normalize certain indexes across requests
	 * @var ValuesContainer
	 */
	protected $_idx_mapper = array();

	/**
	 * Starts everything.
	 *
	 * @param Object $request
	 * @param BodyParserInterface $bodyParser
	 * @param ValuesContainer $opt
	 */
	public function __construct($request, BodyParserInterface $bodyParser, ValuesContainer $opt = null)
	{
		$this->_dbRequest = $request;
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
		if ($opt === null)
		{
			$this->_options = new ValuesContainer();
		}
		else
		{
			$this->_options = $opt;
		}
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
		global $settings, $txt, $modSettings, $user_info;
		global $memberContext, $context, $topic;
		static $counter = null;

		// If the query returned false, bail.
		if ($this->_dbRequest === false)
		{
			return false;
		}

		// Remember which message this is.  (ie. reply #83)
		if ($this->_counter === null || $reset === true)
		{
			$this->_counter = $context['start'];
		}

		// Start from the beginning...
		if ($reset === true)
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
		$this->_this_message['subject'] = $this->_this_message['subject'] != '' ? $this->_this_message['subject'] : $txt['no_subject'];

		$this->_setupPermissions();

		$id_member = $this->_this_message[$this->_idx_mapper->id_member];

		// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
		if (!loadMemberContext($id_member, true))
		{
			$this->_adjustGuestContext();
		}
		else
		{
			$this->_adjustMemberContext();
		}
		$this->_adjustAllMembers();

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
	 * @return boolean
	 */
	protected function _currentContext($reset = false)
	{
		// Start from the beginning...
		if ($reset)
		{
			$this->_db->data_seek($this->_dbRequest, 0);
		}

		// If the query has already returned false, get out of here
		if (empty($this->_dbRequest))
		{
			return false;
		}

		// Attempt to get the next message.
		$this->_this_message = $this->_db->fetch_assoc($this->_dbRequest);
		if (!$this->_this_message)
		{
			$this->_db->free_result($this->_dbRequest);

			return false;
		}

		return true;
	}

	/**
	 * Utility function, it shall be implemented by the extending class.
	 * Run just before loadMemberContext is executed.
	 */
	abstract protected function _setupPermissions();

	/**
	 * Utility function, it can be overridden to alter something just after the
	 * members' data have been loaded from the database.
	 * Run only if loadMemberContext succeeded.
	 */
	protected function _adjustGuestContext()
	{
		global $memberContext, $txt;

		$member_id = $this->_this_message[$this->_idx_mapper->id_member];

		// Notice this information isn't used anywhere else....
		$memberContext[$member_id]['name'] = $this->_this_message[$this->_idx_mapper->name];
		$memberContext[$member_id]['id'] = 0;
		$memberContext[$member_id]['group'] = $txt['guest_title'];
		$memberContext[$member_id]['link'] = $this->_this_message[$this->_idx_mapper->name];
		$memberContext[$member_id]['email'] = $this->_this_message['poster_email'] ?? '';
		$memberContext[$member_id]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$member_id]['is_guest'] = true;
	}

	/**
	 * Utility function, it can be overridden to alter something just after the
	 * members data have been set.
	 * Run only if loadMemberContext failed.
	 */
	protected function _adjustMemberContext()
	{
		global $memberContext, $txt, $user_info, $context, $modSettings;

		$member_id = $this->_this_message[$this->_idx_mapper->id_member];

		$memberContext[$member_id]['can_view_profile'] = allowedTo('profile_view_any') || ($member_id == $user_info['id'] && allowedTo('profile_view_own'));
		$memberContext[$member_id]['is_topic_starter'] = $member_id == $context['topic_starter_id'];
		$memberContext[$member_id]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$member_id]['warning_status'] && (!empty($context['user']['can_mod']) || (!$user_info['is_guest'] && !empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $member_id == $user_info['id'])));

		if ($this->_options->show_signatures === 1)
		{
			if (empty($this->_signature_shown[$member_id]))
			{
				$this->_signature_shown[$member_id] = true;
			}
			else
			{
				$memberContext[$member_id]['signature'] = '';
			}
		}
		elseif ($this->_options->show_signatures === 2)
		{
			$memberContext[$member_id]['signature'] = '';
		}
	}

	/**
	 * Utility function, it can be overridden to alter something just after either
	 * the members or the guests data have been loaded from the database.
	 * Run both if loadMemberContext succeeded or failed.
	 */
	protected function _adjustAllMembers()
	{
	}

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
		global $user_info, $memberContext;

		return array(
			'alternate' => $this->_counter % 2,
			'id' => $this->_this_message[$this->_idx_mapper->id_msg],
			'member' => &$memberContext[$this->_this_message[$this->_idx_mapper->id_member]],
			'subject' => $this->_this_message['subject'],
			'html_time' => htmlTime($this->_this_message[$this->_idx_mapper->time]),
			'time' => standardTime($this->_this_message[$this->_idx_mapper->time]),
			'timestamp' => forum_time(true, $this->_this_message[$this->_idx_mapper->time]),
			'counter' => $this->_counter,
			'body' => $this->_this_message['body'],
			'can_see_ip' => allowedTo('moderate_forum') || ($this->_this_message[$this->_idx_mapper->id_member] == $user_info['id'] && !empty($user_info['id'])),
			'classes' => array()
		);

	}
}
