<?php

/**
 * Used to generate random hash codes for use in forms

 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

/**
 * Class Random_Hash
 *
 * Used to generate a high entropy random hash for use in one time use forms
 * Can return up to a 32 character alphanumeric hash A-Z a-z 0-9
 */
class Token_Hash
{
	/**
	 * Available characters
	 * @var string
	 */
	var $itoa64;

	/**
	 * Random digits for seeding, consider password_hash() when 5.5
	 * @var string
	 */
	var $random_state;

	/**
	 * Basic constructor, sets characters and random state
	 */
	public function __construct()
	{
		$this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		// Set a random state / salt
		$this->random_state = '';

		$this->_state_seed();
	}

	/**
	 * Set a random value for our initial state
	 */
	private function _state_seed()
	{
		// If openssl is installed, let it create a 16 character code
		if (function_exists('openssl_random_pseudo_bytes'))
		{
			$this->random_state = bin2hex(openssl_random_pseudo_bytes(8));
		}
		else
		{
			// Just use uniqid as a seed
			$this->random_state = uniqid('', true);

			// Use the trailing 16 characters
			$this->random_state = substr(str_replace(array(' ', '.'), '', $this->random_state), -16);
		}
	}

	/**
	 * Generates a random hash
	 *
	 * What it does:
	 *  - Uses crypt to generate a hash value
	 *  - Returns * on failure
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
		// A salt to tell cyrpt to use sha512 algos (since 5.3.2)
		if (empty($salt) || strlen($salt) < 16)
		{
			$salt = '$6$' . bin2hex($this->get_random_bytes(8)) . '$';
		}
		else
		{
			$salt = '$6$' . preg_replace('~\W~', 'x', $salt) . '$';
		}

		// A random character password to hash
		$password = bin2hex($this->get_random_bytes($length));

		// Hash away
		$hash = crypt($password, $salt);

		// The hash (86) + salt (20) should be 106
		if (strlen($hash) == 106)
		{
			// For our purposes lets stay with alphanumeric values only
			$hash = str_replace(array('.', '/', '+'), '', $hash);

			// Return up to 32 characters
			return substr($hash, 20 + mt_rand(0, 50), $length);
		}
		// Something happened and its not good, so at least return the hash which
		// is guaranteed to differ from the salt on failure.
		else
			return substr($hash, 0, $length);
	}

	/**
	 * Generates a random string of binary characters
	 *
	 * - Can use as is or pass though bintohex()
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