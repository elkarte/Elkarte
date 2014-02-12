<?php

/**
 * Payment Gateway: authorize
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code also covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Class for returning available form data for this gateway
 */
class authorize_display
{
	/**
	 * Title of this payment gateway
	 * @var string
	 */
	public $title = 'Authorize.net | Credit Card';

	/**
	 * Admin settings for the gateway.
	 */
	public function getGatewaySettings()
	{
		global $txt;

		$setting_data = array(
			array('text', 'authorize_id', 'subtext' => $txt['authorize_id_desc']),
			array('text', 'authorize_transid'),
		);

		return $setting_data;
	}

	/**
	 * Whether this gateway is enabled.
	 *
	 * @return bool
	 */
	public function gatewayEnabled()
	{
		global $modSettings;

		return !empty($modSettings['authorize_id']) && !empty($modSettings['authorize_transid']);
	}

	/**
	 * Returns the fields needed for the transaction.
	 * Called from Profile-Actions.php to return a unique set of fields for the given gateway
	 *
	 * @param int $unique_id
	 * @param mixed[] $sub_data
	 * @param int $value
	 * @param string $period
	 * @param string $return_url
	 *
	 * @return array
	 */
	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url)
	{
		global $modSettings, $txt, $boardurl, $context;

		$return_data = array(
			'form' => 'https://' . (empty($modSettings['paidsubs_test']) ? 'secure' : 'test') . '.authorize.net/gateway/transact.dll',
			'id' => 'authorize',
			'hidden' => array(),
			'title' => $txt['authorize'],
			'desc' => $txt['paid_confirm_authorize'],
			'submit' => $txt['paid_authorize_order'],
			'javascript' => '',
		);

		$timestamp = time();
		$sequence = substr(time(), -5);
		$hash = $this->_md5_hmac($modSettings['authorize_transid'], $modSettings['authorize_id'] . '^' . $sequence . '^' . $timestamp . '^' . $value . '^' . strtoupper($modSettings['paid_currency_code']));

		$return_data['hidden']['x_login'] = $modSettings['authorize_id'];
		$return_data['hidden']['x_amount'] = $value;
		$return_data['hidden']['x_currency_code'] = strtoupper($modSettings['paid_currency_code']);
		$return_data['hidden']['x_show_form'] = 'PAYMENT_FORM';
		$return_data['hidden']['x_test_request'] = empty($modSettings['paidsubs_test']) ? 'FALSE' : 'TRUE';
		$return_data['hidden']['x_fp_sequence'] = $sequence;
		$return_data['hidden']['x_fp_timestamp'] = $timestamp;
		$return_data['hidden']['x_fp_hash'] = $hash;
		$return_data['hidden']['x_invoice_num'] = $unique_id;
		$return_data['hidden']['x_email'] = $context['user']['email'];
		$return_data['hidden']['x_type'] = 'AUTH_CAPTURE';
		$return_data['hidden']['x_cust_id'] = $context['user']['name'];
		$return_data['hidden']['x_relay_url'] = $boardurl . '/subscriptions.php';
		$return_data['hidden']['x_receipt_link_url'] = $return_url;

		return $return_data;
	}

	/**
	 * Generates the hash needed
	 *
	 * @param int $key
	 * @param string $data
	 */
	private function _md5_hmac($key, $data)
	{
		$key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
		return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)) . $data)));
	}
}

/**
 * Class of functions to validate a authorize_payment response and provide details of the payment
 */
class authorize_payment
{
	/**
	 * Holds our return results
	 * @var array
	 */
	private $return_data;

	/**
	 * Validates that we have valid data to work with
	 * Returns true/false for whether this gateway thinks the data is intended for it.
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
		if (empty($_POST['x_response_code']))
			return false;

		return true;
	}

	/**
	 * Validate this is valid for this transaction type.
	 * If valid returns the subscription and member IDs we are going to process.
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
	 * Returns if this is a normal payment.
	 */
	public function isPayment()
	{
		return $_POST['x_response_code'] == 1;
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