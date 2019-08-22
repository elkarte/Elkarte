<?php

/**
 * Handles openID verification
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2012 Simple Machines Forum (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Controls openID verification process
 */
class OpenID extends \ElkArte\AbstractController
{
	/**
	 * Can't say, you see, its a secret
	 * @var string
	 */
	private $_secret = '';

	/**
	 * Forward to the right action.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// We only know one thing and know it well :P
		$this->action_openidreturn();
	}

	/**
	 * Callback action handler for OpenID
	 */
	public function action_openidreturn()
	{
		global $modSettings, $context;

		// Is OpenID even enabled?
		if (empty($modSettings['enableOpenID']))
			throw new \ElkArte\Exceptions\Exception('no_access', false);

		// The OpenID provider did not respond with the OpenID mode? Throw an error..
		if (!isset($this->_req->query->openid_mode))
			throw new \ElkArte\Exceptions\Exception('openid_return_no_mode', false);

		// @todo Check for error status!
		if ($this->_req->query->openid_mode !== 'id_res')
			throw new \ElkArte\Exceptions\Exception('openid_not_resolved');

		// This has annoying habit of removing the + from the base64 encoding.  So lets put them back.
		foreach (array('openid_assoc_handle', 'openid_invalidate_handle', 'openid_sig', 'sf') as $key)
		{
			if (isset($this->_req->query->{$key}))
			{
				$this->_req->query->{$key} = str_replace(' ', '+', $this->_req->query->{$key});
			}
		}

		$openID = new \ElkArte\OpenID();

		// Did they tell us to remove any associations?
		if (!empty($this->_req->query->openid_invalidate_handle))
			$openID->removeAssociation($this->_req->query->openid_invalidate_handle);

		// Get the OpenID server info.
		$server_info = $openID->getServerInfo($this->_req->query->openid_identity);

		// Get the association data.
		$assoc = $openID->getAssociation($server_info['server'], $this->_req->query->openid_assoc_handle, true);
		if ($assoc === null)
		{
			throw new \ElkArte\Exceptions\Exception('openid_no_assoc');
		}

		// Verify the OpenID signature.
		if (!$this->_verify_string($assoc['secret']))
		{
			throw new \ElkArte\Exceptions\Exception('openid_sig_invalid', 'critical');
		}

		if (!isset($_SESSION['openid']['saved_data'][$this->_req->query->t]))
		{
			throw new \ElkArte\Exceptions\Exception('openid_load_data');
		}

		$openid_uri = $_SESSION['openid']['saved_data'][$this->_req->query->t]['openid_uri'];
		$modSettings['cookieTime'] = $_SESSION['openid']['saved_data'][$this->_req->query->t]['cookieTime'];

		if (empty($openid_uri))
		{
			throw new \ElkArte\Exceptions\Exception('openid_load_data');
		}

		// Any save fields to restore?
		$openid_save_fields = isset($this->_req->query->sf) ? json_decode(base64_decode($this->_req->query->sf), true) : array();
		$context['openid_claimed_id'] = $this->_req->query->openid_claimed_id;

		// Is there a user with this OpenID_uri?
		$member_found = $openID->memberByOpenID($context['openid_claimed_id']);

		if (empty($member_found) && $this->_req->getQuery('sa') === 'change_uri' && !empty($_SESSION['new_openid_uri']) && $_SESSION['new_openid_uri'] == $context['openid_claimed_id'])
		{
			// Update the member.
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData(\ElkArte\User::$settings['id_member'], array('openid_uri' => $context['openid_claimed_id']));

			unset($_SESSION['new_openid_uri']);
			$_SESSION['openid'] = array(
				'verified' => true,
				'openid_uri' => $context['openid_claimed_id'],
			);

			// Send them back to profile.
			redirectexit('action=profile;area=authentication;updated');
		}
		elseif (empty($member_found))
		{
			// Store the received openid info for the user when returned to the registration page.
			$_SESSION['openid'] = array(
				'verified' => true,
				'openid_uri' => $context['openid_claimed_id'],
			);

			if (isset($this->_req->query->openid_sreg_nickname))
			{
				$_SESSION['openid']['nickname'] = $this->_req->query->openid_sreg_nickname;
			}
			if (isset($this->_req->query->openid_sreg_email))
			{
				$_SESSION['openid']['email'] = $this->_req->query->openid_sreg_email;
			}
			if (isset($this->_req->query->openid_sreg_dob))
			{
				$_SESSION['openid']['dob'] = $this->_req->query->openid_sreg_dob;
			}

			// Were we just verifying the registration state?
			if (isset($this->_req->query->sa) && $this->_req->query->sa === 'register2')
			{
				// Did we save some open ID fields?
				if (!empty($openid_save_fields))
				{
					foreach ($openid_save_fields as $id => $value)
					{
						$this->_req->post->{$id} = $value;
					}
				}

				$controller = new Register(new \ElkArte\EventManager());
				$controller->setUser(\ElkArte\User::$info);
				$controller->pre_dispatch();
				$controller->do_register(true);
				return null;
			}
			else
			{
				redirectexit('action=register');
			}
		}
		elseif (isset($this->_req->query->sa) && $this->_req->query->sa === 'revalidate' && \ElkArte\User::$settings['openid_uri'] == $openid_uri)
		{
			$_SESSION['openid_revalidate_time'] = time();

			// Restore the get data.
			require_once(SUBSDIR . '/Auth.subs.php');
			$_SESSION['openid']['saved_data'][$this->_req->query->t]['get']['openid_restore_post'] = $this->_req->query->t;
			$query_string = construct_query_string($_SESSION['openid']['saved_data'][$this->_req->query->t]['get']);

			redirectexit($query_string);
		}
		else
		{
			$db = database();
			$cache = \ElkArte\Cache\Cache::instance();
			$req = request();

			$user = new \ElkArte\UserSettings($db, $cache, $req);
			$user->loadUserById($member_found['id_member'], true, '');
			$user_setting = $user->getSettings();

			// Generate an ElkArte hash for the db to protect this account
			$user->$user_setting($this->_secret);

			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_setting['id_member'], array('passwd' => $user_setting['passwd'], 'password_salt' => $user_setting['password_salt']));

			// Cleanup on Aisle 5.
			$_SESSION['openid'] = array(
				'verified' => true,
				'openid_uri' => $context['openid_claimed_id'],
			);

			// Activation required?
			// The isset check should not be necessary
			if ($user->checkActivation(isset($_REQUEST['undelete'])))
			{
				// Finally do the login.
				doLogin($user);
			}

			return false;
		}
	}

	/**
	 * Compares the association with the signatures received from the server
	 *
	 * @param string $raw_secret - The association stored in the database
	 *
	 * @return bool
	 */
	private function _verify_string($raw_secret)
	{
		$this->_secret = base64_decode($raw_secret);
		$signed = explode(',', $this->_req->query->openid_signed);
		$verify_str = '';

		foreach ($signed as $sign)
		{
			$sign_key = 'openid_' . str_replace('.', '_', $sign);
			$verify_str .= $sign . ':' . strtr($this->_req->query->{$sign_key}, array('&amp;' => '&')) . "\n";
		}

		$verify_str = base64_encode(hash_hmac('sha1', $verify_str, $this->_secret, true));

		// Verify the OpenID signature.
		return $verify_str == $this->_req->query->openid_sig;
	}

	/**
	 * Generate the XRDS data for an OpenID 2.0, YADIS discovery
	 */
	public function action_xrds()
	{
		global $modSettings;

		obStart(!empty($modSettings['enableCompressedOutput']));

		header('Content-Type: application/xrds+xml');
		echo '<?xml version="1.0" encoding="UTF-8"?' . '>';
		// Generate the XRDS data for OpenID 2.0, YADIS discovery..
		echo '
<xrds:XRDS xmlns:xrds="xri://$xrds" xmlns="xri://$xrd*($v*2.0)">
	<XRD>
		<Service>
			<Type>http://specs.openid.net/auth/2.0/return_to</Type>
			<URI>', getUrl('action', ['action' => 'openidreturn']) . '</URI>
	</Service>
	</XRD>
</xrds:XRDS>';

	obExit(false);
	}
}
