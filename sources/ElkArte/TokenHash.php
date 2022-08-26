<?php

/**
 * Used to generate random hash codes for use in forms or anywhere else
 * that a secure random hash value is needed
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class TokenHash
 *
 * Used to generate a high entropy random hash for use in one time use forms
 *
 * - Can return up to a 64 character alphanumeric hash A-Z a-z 0-9
 */
class TokenHash
{
	/**
	 * Available characters for private salt
	 *
	 * @var string
	 */
	private $itoa64;

	/**
	 * Random salt to feed crypt
	 *
	 * @var string
	 */
	private $_salt = '';

	/**
	 * Basic constructor, sets characters and random state
	 */
	public function __construct()
	{
		// Valid salt characters for crypt
		$this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	}

	/**
	 * Generates a random hash
	 *
	 * What it does:
	 *  - Uses crypt to generate a hash value
	 *  - Returns other than salt on failure
	 *  - Returns a A-Z a-z 0-9 string of length characters on success
	 *  - If supplying a salt it must be min 16 characters.  It will be wrapped to indicate
	 * its a sha512 salt and cleaned to only contain word characters
	 *
	 * @param int $length the number of characters to return
	 * @param string $salt use a custom salt, leave empty to let the system generate a secure one
	 *
	 * @return string the random token
	 */
	public function generate_hash($length = 10, $salt = '')
	{
		if ($length > 64)
		{
			Errors::instance()->fatal_lang_error('error_token_length');
		}

		// Generate a random salt
		$this->_salt = $salt;
		$this->_gen_salt();

		// A random length character password to hash
		$password = bin2hex($this->get_random_bytes(mt_rand(10, 25)));

		// Hash away, crypt allows us a full a-Z 0-9 set
		$hash = crypt($password, $this->_salt);

		// Clean and return this one
		return substr($this->_prepareToken($hash, $length), mt_rand(1, 10), $length);
	}

	/**
	 * Tidys up our token for return
	 *
	 * What it does:
	 *  - Strips off the salt
	 *  - removes non text characters, leaving just a-Z 0-9
	 *  - May pad the result if for some reason we don't have enough characters
	 *  to fulfill the request.
	 *
	 * @param string $hash
	 * @param int $length
	 * @return string
	 */
	private function _prepareToken($hash, $length)
	{
		// Strip off the salt and just use the crypt value
		$hash = explode('$', $hash);
		$token = array_pop($hash);

		// For our purposes lets stay with alphanumeric values only
		$token = preg_replace('~\W~', '', $token);

		// This should never happen, but its better than vaping
		$short = $length + 10 - strlen($token);
		if ($short > 0)
		{
			$token = str_pad($token, $short, $this->_private_salt($this->get_random_bytes($short)), STR_PAD_RIGHT);
		}

		return $token;
	}

	/**
	 * Generates a salt that tells crypt to use sha512
	 *
	 * - Wraps a random or supplied salt with $6$ ... $
	 * - If supplied a salt, validates it is good to use
	 */
	private function _gen_salt()
	{
		// We are just using this as a random generator, so opt for speed
		$saltPrefix = '$6$rounds=1000$';

		// Not supplied one, then generate a random one, this is preferred
		if (empty($this->_salt) || strlen($this->_salt) < 16)
		{
			$this->_salt = $saltPrefix . $this->_private_salt($this->get_random_bytes(16)) . '$';
		}
		// Supplied a salt, make sure its valid
		else
		{
			// Prep it for crypt / etc
			$this->_salt = substr(preg_replace('~\W~', '', $this->_salt), 0, 16);
			$this->_salt = str_pad($this->_salt, 16, $this->_private_salt($this->get_random_bytes(16)), STR_PAD_RIGHT);
			$this->_salt = $saltPrefix . $this->_salt . '$';
		}
	}

	/**
	 * Generates a salt with a character set that is suitable for crypt
	 *
	 * @param string $input a binary string as supplied from get_random_bytes
	 *
	 * @return string
	 */
	private function _private_salt($input)
	{
		$i = 0;
		$output = '';

		do
		{
			$c1 = ord($input[$i++]);
			$output .= $this->itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;

			// Finished?
			if ($i >= 16)
			{
				$output .= $this->itoa64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $this->itoa64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $this->itoa64[$c1];
			$output .= $this->itoa64[$c2 & 0x3f];
		} while (1);

		return $output;
	}

	/**
	 * Generates a random string of binary characters
	 *
	 * - Can use as is or pass though bin2hex or via ord to get characters.
	 *
	 * @param int $count The number of bytes to produce
	 *
	 * @return string
	 */
	public function get_random_bytes($count)
	{
		return random_bytes($count);
	}
}
