<?php

/**
 * Payment Gateway: TwoCheckOut
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

namespace ElkArte\Subscriptions\PaymentGateway\TwoCheckOut;

use ElkArte\Subscriptions\PaymentGateway\DisplayInterface;

/**
 * The available form data for the gateway
 *
 * @package Subscriptions
 */
class Display implements DisplayInterface
{
	/**
	 * Name of this payment gateway
	 *
	 * @var string
	 */
	public $title = 'twoCheckOut | 2co';

	/**
	 * Return the admin settings for this gateway
	 *
	 * @return array
	 */
	public function getGatewaySettings()
	{
		global $txt;

		return array(
			array('text', '2co_id', 'subtext' => $txt['2co_id_desc']),
			array('text', '2co_password', 'subtext' => $txt['2co_password_desc']),
		);
	}

	/**
	 * Can we accept payments with 2co?
	 */
	public function gatewayEnabled()
	{
		global $modSettings;

		return !empty($modSettings['2co_id']) && !empty($modSettings['2co_password']);
	}

	/**
	 * Called from Profile-Actions.php to return a unique set of fields for the given gateway
	 * plus all the standard ones for the subscription form
	 *
	 * @param int $unique_id for the transaction
	 * @param array $sub_data subscription data array, name, reoccurring, etc
	 * @param int $value amount of the transaction
	 * @param string $period length of the transaction
	 * @param string $return_url
	 *
	 * @return array
	 */
	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url)
	{
		global $modSettings, $txt, $context;

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
