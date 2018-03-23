<?php

namespace ElkArte\sources\subs\MessagesCallback;

use \ElkArte\sources\subs\MessagesCallback\BodyParser\BodyParserInterface;
use \ElkArte\ValuesContainer;

abstract class Renderer
{
	protected $_dbRequest = null;
	protected $_bodyParser = null;
	protected $_db = null;
	protected $_options = null;
	protected $_counter = 0;
	protected $_signature_shown = null;
	protected $_this_message = null;
	protected $_idx_mapper = array();

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

	public function getContext($reset = false)
	{
		global $settings, $txt, $modSettings, $scripturl, $user_info;
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

		if (!$this->_this_message)
		{
			return false;
		}

		// If you're a lazy bum, you probably didn't give a subject...
		$this->_this_message['subject'] = $this->_this_message['subject'] != '' ? $this->_this_message['subject'] : $txt['no_subject'];

		$this->_setupPermissions($this->_this_message);

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

	protected function _adjustAllMembers()
	{
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

	protected abstract function _setupPermissions();

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