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
 * Class of functions to validate a twocheckout payment response and provide details of the payment
 *
 * @package Subscriptions
 */
interface PaymentInterface
{
	/**
	 * Validates that we have valid data to work with
	 *
	 * - Returns true/false for whether this gateway thinks the data is intended for it.
	 *
	 * @return bool
	 */
	public function isValid();

	/**
	 * Validate this is valid for this transaction type.
	 *
	 * - If valid returns the subscription and member IDs we are going to process.
	 */
	public function precheck();

	/**
	 * Returns whether this is this a refund
	 */
	public function isRefund();

	/**
	 * Returns whether this is a subscription
	 */
	public function isSubscription();

	/**
	 * Returns whether this is a normal payment
	 */
	public function isPayment();

	/**
	 * Returns if is this is a cancellation transaction
	 *
	 * @return bool
	 */
	public function isCancellation();

	/**
	 * Returns the cost
	 */
	public function getCost();

	/**
	 * Redirect the user away.
	 *
	 * @param int $subscription_id
	 */
	public function close($subscription_id);
}
