<?php

/**
 * Payment Gateway: Authorize
 *
 * @package   ElkArte Forum
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

namespace ElkArte\Subscriptions\PaymentGateway\Authorize;

/**
 * Class of functions to validate a authorize_payment response and provide details of the payment
 *
 * @package Subscriptions
 */
class Payment
{
	/**
	 * Validates that we have valid data to work with
	 *
	 * - Returns true/false for whether this gateway thinks the data is intended for it.
	 *
	 * @return boolean
	 */
	public function isValid()
	{
		global $modSettings;

		// Is it even on?
		if (empty($modSettings['authorize_id']) || empty($modSettings['authorize_transid']))
			return false;

		// We got a hash?
		if (empty($_POST['x_MD5_Hash']))
			return false;

		// Do we have an invoice number?
		if (empty($_POST['x_invoice_num']))
			return false;

		// And a response?
		return !empty($_POST['x_response_code']);
	}

	/**
	 * Validate this is valid for this transaction type.
	 *
	 * - If valid returns the subscription and member IDs we are going to process.
	 */
	public function precheck()
	{
		global $modSettings;

		// Is this the right hash?
		if ($_POST['x_MD5_Hash'] != strtoupper(md5($modSettings['authorize_id'] . $_POST['x_trans_id'] . $_POST['x_amount'])))
			exit;

		// Can't exist if it doesn't contain anything.
		if (empty($_POST['x_invoice_num']))
			exit;

		// Verify the currency
		$currency = $_POST['x_currency_code'];

		// Verify the currency!
		if (strtolower($currency) != $modSettings['currency_code'])
			exit;

		// Return the ID_SUB/ID_MEMBER
		return explode('+', $_POST['x_invoice_num']);
	}

	/**
	 * Returns if this is a refund.
	 */
	public function isRefund()
	{
		return false;
	}

	/**
	 * Returns if this is a subscription.
	 */
	public function isSubscription()
	{
		return false;
	}

	/**
	 * Returns if this is a normal valid approved payment.
	 *
	 * If a transaction is approved x_response_code will contain a value of 1.
	 * If the card is declined x_response_code will contain a value of 2.
	 * If there was an error the card is expired x_response_code will contain a value of 3.
	 * If the transaction is held for review x_response_code will contain a value of 4.
	 */
	public function isPayment()
	{
		return $_POST['x_response_code'] == 1;
	}

	/**
	 * Returns if this is this is a cancellation transaction
	 *
	 * @return boolean
	 */
	public function isCancellation()
	{
		return false;
	}

	/**
	 * Retrieve the cost.
	 */
	public function getCost()
	{
		return $_POST['x_amount'];
	}

	/**
	 * Redirect the user away.
	 */
	public function close()
	{
		exit();
	}
}
