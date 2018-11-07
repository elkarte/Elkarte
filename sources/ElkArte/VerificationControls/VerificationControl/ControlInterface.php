<?php

/**
 * This file contains those functions specific to the various verification controls
 * used to challenge users, and hopefully robots as well.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
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
	 * @param boolean $isNew
	 * @param boolean $force_refresh
	 *
	 * @return boolean
	 */
	public function showVerification($sessionVal, $isNew, $force_refresh = true);

	/**
	 * Create the actual test that will be used
	 *
	 * @param boolean $refresh
	 *
	 * @return void
	 */
	public function createTest($sessionVal, $refresh = true);

	/**
	 * Prepare the context for use in the template
	 *
	 * @return void
	 */
	public function prepareContext($sessionVal);

	/**
	 * Run the test, return if it passed or not
	 *
	 * @return string|boolean
	 */
	public function doTest($sessionVal);

	/**
	 * If the control has a visible location on the template or if its hidden
	 *
	 * @return boolean
	 */
	public function hasVisibleTemplate();

	/**
	 * Handles the ACP for the control
	 *
	 * @return void
	 */
	public function settings();
}
