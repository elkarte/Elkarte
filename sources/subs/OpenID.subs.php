<?php

/**
 * Handle all of the OpenID interfacing and communications.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * OpenID class, controls interfacing and communications for openid auth
 */
class OpenID
{
	/**
	 * Defined in OpenID spec.
	 */
	protected $p = '155172898181473697471232257763715539915724801966915404479707795314057629378541917580651227423698188993727816152646631438561595825688188889951272158842675419950341258706556549803580104870537681476726513255747040765857479291291572334510643245094715007229621094194349783925984760375594985848253359305585439638443';
	protected $g = '2';

	/**
	 * Validate the supplied OpenID, redirects to the IDP server
	 *
	 * What it does:
	 * - Openid_uri is the URI given by the user
	 * - Validates the URI and changes it to a fully canonical URL
	 * - Determines the IDP server and delegation
	 * - Optional array of fields to restore when validation complete.
	 * - Redirects the user to the IDP for validation
	 *
	 * @param string $openid_uri
	 * @param bool $return = false
	 * @param mixed[]|null $save_fields = array()
	 * @param string|null $return_action = null
	 * @return string
	 */
	public function validate($openid_uri, $return = false, $save_fields = array(), $return_action = null)
	{
		global $scripturl, $modSettings;

		$openid_url = $this->canonize($openid_uri);
		$response_data = $this->getServerInfo($openid_url);

		// We can't do anything without the proper response data.
		if ($response_data === false || empty($response_data['provider']))
			return 'no_data';

		// Is there an existing association?
		if (($assoc = $this->getAssociation($response_data['provider'])) === null)
			$assoc = $this->makeAssociation($response_data['provider']);

		// Include file for member existence
		require_once(SUBSDIR . '/Members.subs.php');

		// Before we go wherever it is we are going, store the GET and POST data, because it might be useful when we get back.
		$request_time = time();

		// Just in case they are doing something else at this time.
		while (isset($_SESSION['openid']['saved_data'][$request_time]))
			$request_time = md5($request_time);

		$_SESSION['openid']['saved_data'][$request_time] = array(
			'get' => $_GET,
			'post' => $_POST,
			'openid_uri' => $openid_url,
			'cookieTime' => $modSettings['cookieTime'],
		);

		// Set identity and claimed id to match the specs.
		$openid_identity = 'http://specs.openid.net/auth/2.0/identifier_select';
		$openid_claimedid = $openid_identity;

		// OpenID url an server response equal?
		if ($openid_url != $response_data['server'])
		{
			$openid_identity = urlencode(empty($response_data['delegate']) ? $openid_url : $response_data['delegate']);
			if (strpos($openid_identity, 'https') === 0)
				$openid_claimedid = str_replace('http://', 'https://', $openid_url);
			else
				$openid_claimedid = $openid_url;
		}

		// Prepare parameters for the OpenID setup.
		$parameters = array(
			'openid.mode=checkid_setup',
			'openid.realm=' . $scripturl,
			'openid.ns=http://specs.openid.net/auth/2.0',
			'openid.identity=' . $openid_identity,
			'openid.claimed_id=' . $openid_claimedid,
			'openid.assoc_handle=' . urlencode($assoc['handle']),
			'openid.return_to=' . urlencode($scripturl . '?action=openidreturn&sa=' . (!empty($return_action) ? $return_action : $_REQUEST['action']) . '&t=' . $request_time . (!empty($save_fields) ? '&sf=' . base64_encode(json_encode($save_fields)) : '')),
			'openid.sreg.required=email',
		);

		// If they are logging in but don't yet have an account or they are registering, let's request some additional information
		if (($_REQUEST['action'] == 'login2' && !memberExists($openid_url)) || $_REQUEST['action'] == 'register')
			$parameters[] = 'openid.sreg.optional=nickname,dob';

		$redir_url = $response_data['server'] . '?' . implode('&', $parameters);

		if ($return)
			return $redir_url;
		else
			redirectexit($redir_url);
	}

	/**
	 * Revalidate a user using OpenID.
	 *
	 * - Note that this function will not return when authentication is required.
	 *
	 * @return boolean|null
	 */
	public function revalidate()
	{
		global $user_settings;

		if (isset($_SESSION['openid_revalidate_time']) && $_SESSION['openid_revalidate_time'] > time() - 60)
		{
			unset($_SESSION['openid_revalidate_time']);
			return true;
		}
		else
			$this->validate($user_settings['openid_uri'], false, null, 'revalidate');

		// We shouldn't get here.
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}

	/**
	 * Retrieve an existing, not expired, association if there is any.
	 *
	 * @param string $server
	 * @param string|null $handle = null
	 * @param bool $no_delete = false
	 * @return array
	 */
	public function getAssociation($server, $handle = null, $no_delete = false)
	{
		$db = database();

		if (!$no_delete)
		{
			// Delete the already expired associations.
			$db->query('openid_delete_assoc_old', '
				DELETE FROM {db_prefix}openid_assoc
				WHERE expires <= {int:current_time}',
				array(
					'current_time' => time(),
				)
			);
		}

		// Get the association that has the longest lifetime from now.
		$request = $db->query('openid_select_assoc', '
			SELECT server_url, handle, secret, issued, expires, assoc_type
			FROM {db_prefix}openid_assoc
			WHERE (server_url = {string:https_server_url} OR server_url = {string:http_server_url})
			' . ($handle === null ? '' : '
				AND handle = {string:handle}') . '
			ORDER BY expires DESC',
			array(
				'http_server_url' => strtr($server, array('https://' => 'http://')),
				'https_server_url' => strtr($server, array('http://' => 'https://')),
				'handle' => $handle,
			)
		);

		if ($db->num_rows($request) == 0)
			return null;

		$return = $db->fetch_assoc($request);
		$return['server_url'] = $server;
		$db->free_result($request);

		return $return;
	}

	/**
	 * Create and store an association to the given server.
	 *
	 * @param string $server
	 * @return array
	 */
	public function makeAssociation($server)
	{
		$db = database();

		$parameters = array(
			'openid.mode=associate',
			'openid.ns=http://specs.openid.net/auth/2.0',
		);

		// We'll need to get our keys for the Diffie-Hellman key exchange.
		$dh_keys = $this->setup_DH();

		// If we don't support DH we'll have to see if the provider will accept no encryption.
		if ($dh_keys === false)
			$parameters[] = 'openid.session_type=';
		else
		{
			$parameters[] = 'openid.session_type=DH-SHA1';
			$parameters[] = 'openid.dh_consumer_public=' . urlencode(base64_encode(long_to_binary($dh_keys['public'])));
			$parameters[] = 'openid.assoc_type=HMAC-SHA1';
		}

		// The data to post to the server.
		$post_data = implode('&', $parameters);
		$data = fetch_web_data($server, $post_data);

		// Parse the data given.
		preg_match_all('~^([^:]+):(.+)$~m', $data, $matches);
		$assoc_data = array();

		foreach ($matches[1] as $key => $match)
			$assoc_data[$match] = $matches[2][$key];

		if (!isset($assoc_data['assoc_type']) || (empty($assoc_data['mac_key']) && empty($assoc_data['enc_mac_key'])))
			Errors::instance()->fatal_lang_error('openid_server_bad_response');

		// Clean things up a bit.
		$handle = isset($assoc_data['assoc_handle']) ? $assoc_data['assoc_handle'] : '';
		$issued = time();
		$expires = $issued + min((int) $assoc_data['expires_in'], 60);
		$assoc_type = isset($assoc_data['assoc_type']) ? $assoc_data['assoc_type'] : '';

		// @todo Is this really needed?
		foreach (array('dh_server_public', 'enc_mac_key') as $key)
			if (isset($assoc_data[$key]))
				$assoc_data[$key] = str_replace(' ', '+', $assoc_data[$key]);

		// Figure out the Diffie-Hellman secret.
		if (!empty($assoc_data['enc_mac_key']))
		{
			$dh_secret = bcpowmod(binary_to_long(base64_decode($assoc_data['dh_server_public'])), $dh_keys['private'], $this->p);
			$secret = base64_encode(binary_xor(sha1(long_to_binary($dh_secret), true), base64_decode($assoc_data['enc_mac_key'])));
		}
		else
			$secret = $assoc_data['mac_key'];

		// Store the data
		$db->insert('replace',
			'{db_prefix}openid_assoc',
			array('server_url' => 'string', 'handle' => 'string', 'secret' => 'string', 'issued' => 'int', 'expires' => 'int', 'assoc_type' => 'string'),
			array($server, $handle, $secret, $issued, $expires, $assoc_type),
			array('server_url', 'handle')
		);

		return array(
			'server' => $server,
			'handle' => $assoc_data['assoc_handle'],
			'secret' => $secret,
			'issued' => $issued,
			'expires' => $expires,
			'assoc_type' => $assoc_data['assoc_type'],
		);
	}

	/**
	 * Delete an existing association from the database.
	 *
	 * @param string $handle
	 */
	public function removeAssociation($handle)
	{
		$db = database();

		$db->query('openid_remove_association', '
			DELETE FROM {db_prefix}openid_assoc
			WHERE handle = {string:handle}',
			array(
				'handle' => $handle,
			)
		);
	}

	/**
	 * Fix the URI to a canonical form
	 *
	 * @param string $uri
	 */
	public function canonize($uri)
	{
		// @todo Add in discovery.

		$uri = addProtocol($uri, array('http://', 'https://'));

		// Strip http:// and https:// and if there is no / in what is left, add one
		if (strpos(strtr($uri, array('http://' => '', 'https://' => '')), '/') === false)
			$uri .= '/';

		return $uri;
	}

	/**
	 * Prepare for a Diffie-Hellman key exchange.
	 *
	 * @param bool $regenerate = false
	 * @return string return false on failure or an array() on success
	 */
	public function setup_DH($regenerate = false)
	{
		// First off, do we have BC Math available?
		if (!function_exists('bcpow'))
			return false;

		// Make sure the scale is set.
		bcscale(0);

		return $this->get_keys($regenerate);
	}

	/**
	 * Retrieve DH keys from the store.
	 *
	 * - It generates them if they're not stored or $regenerate parameter is true.
	 *
	 * @param bool $regenerate
	 */
	public function get_keys($regenerate)
	{
		global $modSettings;

		// Ok lets take the easy way out, are their any keys already defined for us? They are changed in the daily maintenance scheduled task.
		if (!empty($modSettings['dh_keys']) && !$regenerate)
		{
			// Sweeeet!
			list ($public, $private) = explode("\n", $modSettings['dh_keys']);
			return array(
				'public' => base64_decode($public),
				'private' => base64_decode($private),
			);
		}

		// Dang it, now I have to do math.  And it's not just ordinary math, its the evil big integer math.
		// This will take a few seconds.
		$private = $this->generate_private_key();
		$public = bcpowmod($this->g, $private, $this->p);

		// Now that we did all that work, lets save it so we don't have to keep doing it.
		$keys = array('dh_keys' => base64_encode($public) . "\n" . base64_encode($private));
		updateSettings($keys);

		return array(
			'public' => $public,
			'private' => $private,
		);
	}

	/**
	 * Generate private key
	 *
	 * @return string
	 */
	public function generate_private_key()
	{
		static $cache = array();

		$byte_string = long_to_binary($this->p);

		if (isset($cache[$byte_string]))
			list ($dup, $num_bytes) = $cache[$byte_string];
		else
		{
			$num_bytes = strlen($byte_string) - ($byte_string[0] == "\x00" ? 1 : 0);

			$max_rand = bcpow(256, $num_bytes);

			$dup = bcmod($max_rand, $num_bytes);

			$cache[$byte_string] = array($dup, $num_bytes);
		}

		do
		{
			$str = '';
			for ($i = 0; $i < $num_bytes; $i += 4)
				$str .= pack('L', mt_rand());

			$bytes = "\x00" . $str;

			$num = binary_to_long($bytes);
		} while (bccomp($num, $dup) < 0);

		return bcadd(bcmod($num, $this->p), 1);
	}

	/**
	 * Retrieve server information.
	 *
	 * @param string $openid_url
	 * @return boolean|array
	 */
	public function getServerInfo($openid_url)
	{
		require_once(SUBSDIR . '/Package.subs.php');

		// Get the html and parse it for the openid variable which will tell us where to go.
		$webdata = fetch_web_data($openid_url);

		if (empty($webdata))
			return false;

		$response_data = array();

		// dirty, but .. Yadis response? Let's get the <URI>
		preg_match('~<URI.*?>(.*)</URI>~', $webdata, $uri);
		if (!empty($uri))
		{
			$response_data['provider'] = $uri[1];
			$response_data['server'] = $uri[1];
			return $response_data;
		}

		// Some OpenID servers have strange but still valid HTML which makes our job hard.
		if (preg_match_all('~<link([\s\S]*?)/?>~i', $webdata, $link_matches) == 0)
			Errors::instance()->fatal_lang_error('openid_server_bad_response');

		foreach ($link_matches[1] as $link_match)
		{
			if (preg_match('~rel="([\s\S]*?)"~i', $link_match, $rel_match) == 0 || preg_match('~href="([\s\S]*?)"~i', $link_match, $href_match) == 0)
				continue;

			$rels = preg_split('~\s+~', $rel_match[1]);
			foreach ($rels as $rel)
				if (preg_match('~openid2?\.(server|delegate|provider)~i', $rel, $match) != 0)
					$response_data[$match[1]] = $href_match[1];
		}

		if (empty($response_data['provider']))
			$response_data['server'] = $openid_url;
		else
			$response_data['server'] = $response_data['provider'];

		return $response_data;
	}
}

/**
 * Given a binary string, returns the binary string converted to a long number.
 *
 * @param string $str
 * @return string
 */
function binary_to_long($str)
{
	$bytes = array_merge(unpack('C*', $str));

	$n = 0;

	foreach ($bytes as $byte)
	{
		$n = bcmul($n, 256);
		$n = bcadd($n, $byte);
	}

	return $n;
}

/**
 * Given a long integer, returns the number converted to a binary
 * string.
 *
 * This function accepts long integer values of arbitrary
 * magnitude.
 *
 * @param string $value
 * @return string
 */
function long_to_binary($value)
{
	$cmp = bccomp($value, 0);
	if ($cmp < 0)
		Errors::instance()->fatal_error('Only non-negative integers allowed.');

	if ($cmp == 0)
		return "\x00";

	$bytes = array();

	while (bccomp($value, 0) > 0)
	{
		array_unshift($bytes, bcmod($value, 256));
		$value = bcdiv($value, 256);
	}

	if (!empty($bytes) && ($bytes[0] > 127))
		array_unshift($bytes, 0);

	$return = '';
	foreach ($bytes as $byte)
		$return .= pack('C', $byte);

	return $return;
}

/**
 * Performs an exclusive or (^ bitwise operator) character for character on two stings.
 *
 * - The result of the biwise operator is 1 if and only if both bits differ.
 *
 * Returns a binary string representing the per character position comparison results.
 *
 * @param int $num1
 * @param int $num2
 */
function binary_xor($num1, $num2)
{
	$return = '';

	$str_len = strlen($num2);
	for ($i = 0; $i < $str_len; $i++)
		$return .= $num1[$i] ^ $num2[$i];

	return $return;
}

/**
 * Retrieve a member settings based on the claimed id
 *
 * @param string $claimed_id the claimed id
 *
 * @return array the member settings
 */
function memberByOpenID($claimed_id)
{
	$db = database();

	$result = $db->query('', '
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			openid_uri
		FROM {db_prefix}members
		WHERE openid_uri = {string:openid_uri}',
		array(
			'openid_uri' => $claimed_id,
		)
	);

	$member_found = $db->fetch_assoc($result);
	$db->free_result($result);

	return $member_found;
}
