<?php

/**
 * This file contains those functions specific to the various verification controls
 * used to challenge users, and hopefully robots as well.
 *
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

namespace ElkArte\VerificationControls\VerificationControl;

/**
 * A simple interface that defines all the methods any "Control_Verification"
 * class MUST have because they are used in the process of creating the verification
 */
interface ControlInterface
{
	/**
	 * Used to build the control and return if it should be shown or not
	 *
	 * @param \ElkArte\Sessions\SessionIndex $sessionVal
	 * @param bool $isNew
	 * @param bool $force_refresh
	 *
	 * @return bool
	 */
	public function showVerification($sessionVal, $isNew, $force_refresh = true);

	/**
	 * Create the actual test that will be used
	 *
	 * @param \ElkArte\Sessions\SessionIndex $sessionVal
	 * @param bool $refresh
	 *
	 * @return void
	 */
	public function createTest($sessionVal, $refresh = true);

	/**
	 * Prepare the context for use in the template.
	 *
	 * Required keys template => string, values => []
	 * Template function must exist in VerificationControls,template
	 * Will called as template_verification_control_' . 'template('id', 'values')
	 *
	 * @param \ElkArte\Sessions\SessionIndex $sessionVal
	 *
	 * @return void
	 */
	public function prepareContext($sessionVal);

	/**
	 * Run the test, return if it passed or not
	 *
	 * @param \ElkArte\Sessions\SessionIndex $sessionVal
	 *
	 * @return string|bool
	 */
	public function doTest($sessionVal);

	/**
	 * If the control has a visible location on the template or if its hidden
	 *
	 * @return bool
	 */
	public function hasVisibleTemplate();

	/**
	 * Handles the ACP for the control
	 *
	 * @return array
	 */
	public function settings();
}
