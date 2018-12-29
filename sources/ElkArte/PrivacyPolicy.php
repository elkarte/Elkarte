<?php

/**
 * This class takes care of the registration agreement
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
 * Class PrivacyPolicy
 *
 * A simple class to take care of the privacy policy
 */
class PrivacyPolicy extends Agreement
{
	/**
	 * Everything starts here.
	 *
	 * @param string $language the wanted language of the agreement.
	 * @param string $backup_dir where to store the backup of the privacy policy.
	 */
	public function __construct($language, $backup_dir = null)
	{
		$this->_log_table_name = '{db_prefix}log_privacy_policy_accept';
		$this->_backupdir_name = 'privacypolicies';
		$this->_file_name = 'privacypolicy';

		parent::__construct($language, $backup_dir);
	}
}
