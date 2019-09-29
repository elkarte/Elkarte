<?php

/**
 * Payment Gateway: TwoCheckOut
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Subscriptions\PaymentGateway;

/**
 * The available form data for the gateway
 *
 * @package Subscriptions
 */
interface DisplayInterface
{
	/**
	 * Return the admin settings for this gateway
	 *
	 * @return array
	 */
	public function getGatewaySettings();

	/**
	 * Can we accept payments with this gateway?
	 */
	public function gatewayEnabled();

	/**
	 * Called from Profile-Actions.php to return a unique set of fields for the given gateway
	 * plus all the standard ones for the subscription form
	 *
	 * @param int $unique_id for the transaction
	 * @param mixed[] $sub_data subscription data array, name, reoccurring, etc
	 * @param int $value amount of the transaction
	 * @param string $period length of the transaction
	 * @param string $return_url
	 *
	 * @return array
	 */
	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url);
}
