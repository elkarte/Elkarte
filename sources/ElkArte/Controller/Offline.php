<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;

class Offline extends AbstractController
{
	/**
	 * Default action handler: just Offline.
	 */
	public function action_index()
	{
		// I need the internet!
		$this->action_offline();
	}

	public function action_offline()
	{
		// Load the template
		theme()->getTemplates()->load('Offline');
		theme()->getTemplates()->loadSubTemplate('offline');

		obExit(false, false);
	}
}