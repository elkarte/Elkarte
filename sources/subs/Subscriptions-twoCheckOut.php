<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code also covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * Payment Gateway: twocheckout
 *
 */

/**
 * The available form data for the gateway
 */
class twocheckout_display
{
	public $title = 'twoCheckOut | 2co';

	public function getGatewaySettings()
	{
		global $txt;

		$setting_data = array(
			array('text', '2co_id', 'subtext' => $txt['2co_id_desc']),
			array('text', '2co_password', 'subtext' => $txt['2co_password_desc']),
		);

		return $setting_data;
	}

	/**
	 * Can we accept payments with 2co?
	 */
	public function gatewayEnabled()
	{
		global $modSettings;

		return !empty($modSettings['2co_id']) && !empty($modSettings['2co_password']);
	}

	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url)
	{
		global $modSettings, $txt, $boardurl, $context;

		$return_data = array(
			'form' => 'https://www.2checkout.com/2co/buyer/purchase',
			'id' => 'twocheckout',
			'hidden' => array(),
			'title' => $txt['2co'],
			'desc' => $txt['paid_confirm_2co'],
			'submit' => $txt['paid_2co_order'],
			'javascript' => '',
		);

		// Now some hidden fields.
		$return_data['hidden']['x_login'] = $modSettings['2co_id'];
		$return_data['hidden']['x_invoice_num'] = $unique_id;
		$return_data['hidden']['x_amount'] = $value;
		$return_data['hidden']['x_Email'] = $context['user']['email'];
		$return_data['hidden']['fixed'] = 'Y';
		$return_data['hidden']['demo'] = empty($modSettings['paidsubs_test']) ? 'N' : 'Y';
		$return_data['hidden']['return_url'] = $return_url;

		return $return_data;
	}
}

class twocheckout_payment
{
	private $return_data;

	public function isValid()
	{
		global $modSettings;

		// Is it even on?
		if (empty($modSettings['2co_id']) || empty($modSettings['2co_password']))
			return false;
		// Is it a 2co hash?
		if (empty($_POST['x_MD5_Hash']))
			return false;
		// Do we have an invoice number?
		if (empty($_POST['x_invoice_num']))
			return false;

		return true;
	}

	public function precheck()
	{
		global $modSettings, $txt;

		// Is this the right hash?
		if (empty($modSettings['2co_password']) || $_POST['x_MD5_Hash'] != strtoupper(md5($modSettings['2co_password'] . $modSettings['2co_id'] . $_POST['x_trans_id'] . $_POST['x_amount'])))
			generateSubscriptionError($txt['2co_password_wrong']);

		// Verify the currency
		list (, $currency) = explode(':', $_POST['x_invoice_num']);

		// Verify the currency!
		if (strtolower($currency) != $modSettings['currency_code'])
			exit;

		// Return the ID_SUB/ID_MEMBER
		return explode('+', $_POST['x_invoice_num']);
	}

	/**
	 * Returns whether this is this a refund
	 */
	public function isRefund()
	{
		return false;
	}

	/**
	 * Returns whether this is a subscription
	 */
	public function isSubscription()
	{
		return false;
	}

	/**
	 * Returns whether this is a normal payment
	 */
	function isPayment()
	{
		// We have to assume so?
		return true;
	}

	/**
	 * Returns the cost
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