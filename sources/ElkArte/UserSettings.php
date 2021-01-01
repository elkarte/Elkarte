<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * This class holds all the data belonging to a certain member.
 */
class UserSettings extends ValuesContainerReadOnly
{
	/**
	 * Sets last_login to the current time
	 */
	public function updateLastLogin()
	{
		$this->data['last_login'] = time();
	}

	/**
	 * Changes the password to the provided one in $this->settings
	 * Doesn't actually change the database.
	 *
	 * @param string $password The hashed password
	 */
	public function updatePassword($password)
	{
		$this->data['passwd'] = $password;

		$tokenizer = new TokenHash();
		$this->data['password_salt'] = $tokenizer->generate_hash(UserSettingsLoader::HASH_LENGTH);
	}

	/**
	 * Updates total_time_logged_in
	 *
	 * @param int $increment_offset
	 */
	public function updateTotalTimeLoggedIn($increment_offset)
	{
		$this->data['total_time_logged_in'] += time() - $increment_offset;
	}

	/**
	 * Fixes the password salt if not present or if it needs to be changed
	 *
	 * @param bool $force - If true the salt is changed no matter what
	 */
	public function fixSalt($force = false)
	{
		// Correct password, but they've got no salt; fix it!
		if ($this->data['password_salt'] === '' || $force || strlen($this->data['password_salt']) < 10)
		{
			$tokenizer = new TokenHash();

			$this->data['password_salt'] = $tokenizer->generate_hash(UserSettingsLoader::HASH_LENGTH);

			return true;
		}

		return false;
	}

	/**
	 * Returns the true activation status of an account
	 *
	 * @param bool $strip_ban
	 * @return int
	 */
	public function getActivationStatus($strip_ban = true)
	{
		return (int) $this->is_activated > UserSettingsLoader::BAN_OFFSET ? $this->is_activated - UserSettingsLoader::BAN_OFFSET : $this->is_activated;
	}

	/**
	 * Repeat the hashing of the password
	 *
	 * @param string $password The plain text (or sha256 hashed) password
	 * @return bool|null Returns false if something fails
	 */
	public function rehashPassword($password)
	{
		// If the password is not already 64 characters, lets make it a (SHA-256)
		if (strlen($password) !== 64)
		{
			$password = hash('sha256', Util::strtolower($this->member_name) . un_htmlspecialchars($password));
		}

		$passhash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

		// Something is not right
		if ($passhash === false)
		{
			// @todo here we should throw an exception
			return false;
		}
		else
		{
			$this->updatePassword($passhash);
		}
	}

	/**
	 * Checks whether a password meets the current forum rules
	 *
	 * What it does:
	 *
	 * - called when registering/choosing a password.
	 * - checks the password obeys the current forum settings for password strength.
	 * - if password checking is enabled, will check that none of the words in restrict_in appear in the password.
	 * - returns an error identifier if the password is invalid.
	 *
	 * @param string $password
	 * @return bool
	 */
	public function validatePassword($password)
	{
		// If the password is not 64 characters, lets make it a (SHA-256)
		if (strlen($password) !== 64)
		{
			$password = hash('sha256', Util::strtolower($this->member_name) . un_htmlspecialchars($password));
		}

		return (bool) password_verify($password, $this->passwd);
	}
}
