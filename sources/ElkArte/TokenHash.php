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
 * - Can return up to a 32 character alphanumeric hash A-Z a-z 0-9
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
	 * Random digits for initial seeding
	 *
	 * @var string
	 */
	private $random_state = '';

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

		// Set a random state to initialize
		$this->_state_seed();
	}

	/**
	 * Set a random value for our initial state
	 */
	private function _state_seed()
	{
		$this->random_state = bin2hex(random_bytes(8));
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
	 * @return string the random hash
	 */
	public function generate_hash($length = 10, $salt = '')
	{
		// Generate a random salt
		$this->_salt = $salt;
		$this->_gen_salt();

		// A random character password to hash
		$this->_state_seed();
		$password = bin2hex($this->get_random_bytes($length));

		// Hash away
		$hash = crypt($password, $this->_salt);

		// For our purposes lets stay with alphanumeric values only
		$hash = preg_replace('~\W~', '', $hash);

		return substr($hash, mt_rand(0, 45), $length);
	}

	/**
	 * Generates a salt that tells crypt to use sha512
	 *
	 * - Wraps a random or supplied salt with $6$ ... $
	 * - If supplied a salt, validates it is good to use
	 */
	private function _gen_salt()
	{
		// Not supplied one, then generate a random one, this is preferred
		if (empty($this->_salt) || strlen($this->_salt) < 16)
		{
			$this->_salt = '$6$' . $this->_private_salt($this->get_random_bytes(16)) . '$';
		}
		// Supplied a salt, make sure its valid
		else
		{
			// Prep it for crypt / etc
			$this->_salt = substr(preg_replace('~\W~', '', $this->_salt), 0, 16);
			$this->_salt = str_pad($this->_salt, 16, $this->_private_salt($this->get_random_bytes(16)), STR_PAD_RIGHT);
			$this->_salt = '$6$' . $this->_salt . '$';
		}
	}

	/**
	 * Generates a random salt with a character set that is suitable for crypt
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
	 * - Can use as is or pass though bintohex or via ord to get characters.
	 *
	 * @param int $count The number of bytes to produce
	 *
	 * @return string
	 */
	public function get_random_bytes($count)
	{
		$output = '';

		// Loop every 16 characters
		for ($i = 0; $i < $count; $i += 16)
		{
			$this->random_state = hash('sha1', microtime() . $this->random_state);
			$output .= hash('sha1', $this->random_state, true);
		}

		return substr($output, 0, $count);
	}
}
