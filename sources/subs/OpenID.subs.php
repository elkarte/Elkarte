<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * Handle all of the OpenID interfacing and communications.
 *
 */

if (!defined('ELK'))
	die('No access...');

class OpenID
{
	/**
	 * Openid_uri is the URI given by the user
	 * Validates the URI and changes it to a fully canonical URL
	 * Determines the IDP server and delegation
	 * Optional array of fields to restore when validation complete.
	 * Redirects the user to the IDP for validation
	 *
	 * @param string $openid_uri
	 * @param bool $return = false
	 * @param array $save_fields = array()
	 * @param string $return_action = null
	 * @return string
	 */
	function validate($openid_uri, $return = false, $save_fields = array(), $return_action = null)
	{
		global $scripturl, $modSettings;

		$openid_url = $this->canonize($openid_uri);

		$response_data = $this->getServerInfo($openid_url);
		if ($response_data === false || empty($response_data['provider']))
			return 'no_data';

		if (($assoc = $this->getAssociation($response_data['provider'])) == null)
			$assoc = $this->makeAssociation($response_data['provider']);

		// include file for member existence
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

		$id_select = 'http://specs.openid.net/auth/2.0/identifier_select';
		$openid_identity = $id_select;
		$openid_claimedid = $id_select;
		if ($openid_url != $response_data['server'])
		{
			$openid_identity = urlencode(empty($response_data['delegate']) ? $openid_url : $response_data['delegate']);
			if (strpos($openid_identity, 'https') === 0)
				$openid_claimedid = str_replace("http://", "https://", $openid_url);			
			else
				$openid_claimedid = $openid_url;
		}

		$parameters = array(
			'openid.mode=checkid_setup',
			'openid.realm=' . $scripturl,
			'openid.ns=http://specs.openid.net/auth/2.0',
			'openid.identity=' . $openid_identity,
			'openid.claimed_id=' . $openid_claimedid,
			'openid.assoc_handle=' . urlencode($assoc['handle']),
			'openid.return_to=' . urlencode($scripturl . '?action=openidreturn&sa=' . (!empty($return_action) ? $return_action : $_REQUEST['action']) . '&t=' . $request_time . (!empty($save_fields) ? '&sf=' . base64_encode(serialize($save_fields)) : '')),
			'openid.sreg.required=email',
		);

		// If they are logging in but don't yet have an account or they are registering, let's request some additional information
		if (($_REQUEST['action'] == 'login2' && !memberExists($openid_url)) || ($_REQUEST['action'] == 'register' || $_REQUEST['action'] == 'register2'))
			$parameters[] = 'openid.sreg.optional=nickname,dob,gender';

		$redir_url = $response_data['server'] . '?' . implode('&', $parameters);

		if ($return)
			return $redir_url;
		else
			redirectexit($redir_url);
	}

	/**
	 * Revalidate a user using OpenID.
	 * Note that this function will not return when authentication is required.
	 *
	 * @return boolean
	 */
	function revalidate()
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
	 * @param string $handle = null
	 * @param bool $no_delete = false
	 * @return array
	 */
	function getAssociation($server, $handle = null, $no_delete = false)
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
			WHERE server_url = {string:server_url}' . ($handle === null ? '' : '
				AND handle = {string:handle}') . '
			ORDER BY expires DESC',
			array(
				'server_url' => $server,
				'handle' => $handle,
			)
		);

		if ($db->num_rows($request) == 0)
			return null;

		$return = $db->fetch_assoc($request);
		$db->free_result($request);

		return $return;
	}

	/**
	 * Create and store an association to the given server.
	 *
	 * @param string $server
	 * @return array
	 */
	function makeAssociation($server)
	{
		global $p;

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
			fatal_lang_error('openid_server_bad_response');

		// Clean things up a bit.
		$handle = isset($assoc_data['assoc_handle']) ? $assoc_data['assoc_handle'] : '';
		$issued = time();
		$expires = $issued + min((int)$assoc_data['expires_in'], 60);
		$assoc_type = isset($assoc_data['assoc_type']) ? $assoc_data['assoc_type'] : '';

		// @todo Is this really needed?
		foreach (array('dh_server_public', 'enc_mac_key') as $key)
			if (isset($assoc_data[$key]))
				$assoc_data[$key] = str_replace(' ', '+', $assoc_data[$key]);

		// Figure out the Diffie-Hellman secret.
		if (!empty($assoc_data['enc_mac_key']))
		{
			$dh_secret = bcpowmod(binary_to_long(base64_decode($assoc_data['dh_server_public'])), $dh_keys['private'], $p);
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
	function removeAssociation($handle)
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
	function canonize($uri)
	{
		// @todo Add in discovery.

		if (strpos($uri, 'http://') !== 0 && strpos($uri, 'https://') !== 0)
			$uri = 'http://' . $uri;

		if (strpos(substr($uri, strpos($uri, '://') + 3), '/') === false)
			$uri .= '/';

		return $uri;
	}

	/**
	 * Prepare for a Diffie-Hellman key exchange.
	 * @param bool $regenerate = false
	 * @return array|false return false on failure or an array() on success
	 */
	function setup_DH($regenerate = false)
	{
		global $p, $g;

		// First off, do we have BC Math available?
		if (!function_exists('bcpow'))
			return false;

		// Defined in OpenID spec.
		$p = '155172898181473697471232257763715539915724801966915404479707795314057629378541917580651227423698188993727816152646631438561595825688188889951272158842675419950341258706556549803580104870537681476726513255747040765857479291291572334510643245094715007229621094194349783925984760375594985848253359305585439638443';
		$g = '2';

		// Make sure the scale is set.
		bcscale(0);

		return $this->get_keys($regenerate);
	}

	/**
	 * Retrieve DH keys from the store.
	 * It generates them if they're not stored or $regerate parameter is true.
	 *
	 * @param bool $regenerate
	 */
	function get_keys($regenerate)
	{
		global $modSettings, $p, $g;

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

		// Dang it, now I have to do math.  And it's not just ordinary math, its the evil big interger math.  This will take a few seconds.
		$private = $this->generate_private_key();
		$public = bcpowmod($g, $private, $p);

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
	 * @return float
	 */
	function generate_private_key()
	{
		global $p;
		static $cache = array();

		$byte_string = long_to_binary($p);

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

		return bcadd(bcmod($num, $p), 1);
	}

	/**
	 *
	 * Retrieve server information.
	 *
	 * @param string $openid_url
	 * @return boolean|array
	 */
	function getServerInfo($openid_url)
	{
		require_once(SUBSDIR . '/Package.subs.php');

		// Get the html and parse it for the openid variable which will tell us where to go.
		$webdata = fetch_web_data($openid_url);

		if (empty($webdata))
			return false;

		$response_data = array();
		// dirty, but .. Yadis response? Let's get the <URI>
		preg_match('~<URI.*?>(.*)</URI>~', $webdata, $uri);
		if ($uri)
		{
			$response_data['provider'] = $uri[1];
			$response_data['server'] = $uri[1];
			return $response_data;
		}
		// Some OpenID servers have strange but still valid HTML which makes our job hard.
		if (preg_match_all('~<link([\s\S]*?)/?>~i', $webdata, $link_matches) == 0)
			fatal_lang_error('openid_server_bad_response');

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
 * @param string $data
 * @param string $key
 * @return string
 */
function sha1_hmac($data, $key)
{

	if (strlen($key) > 64)
		$key = sha1($key, true);

	// Pad the key if need be.
	$key = str_pad($key, 64, chr(0x00));
	$ipad = str_repeat(chr(0x36), 64);
	$opad = str_repeat(chr(0x5c), 64);
	$hash1 = sha1(($key ^ $ipad) . $data, true);
	$hmac = sha1(($key ^ $opad) . $hash1, true);
	return $hmac;
}

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

function long_to_binary($value)
{
	$cmp = bccomp($value, 0);
	if ($cmp < 0)
		fatal_error('Only non-negative integers allowed.');

	if ($cmp == 0)
		return "\x00";

	$bytes = array();

	while (bccomp($value, 0) > 0)
	{
		array_unshift($bytes, bcmod($value, 256));
		$value = bcdiv($value, 256);
	}

	if ($bytes && ($bytes[0] > 127))
		array_unshift($bytes, 0);

	$return = '';
	foreach ($bytes as $byte)
		$return .= pack('C', $byte);

	return $return;
}

/**
 * @param int $num1
 * @param int $num2
 */
function binary_xor($num1, $num2)
{
	$return = '';

	for ($i = 0; $i < strlen($num2); $i++)
		$return .= $num1[$i] ^ $num2[$i];

	return $return;
}

/**
 * Retrieve a member settings based on the claimed id
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