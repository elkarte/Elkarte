<?php

/**
 * Payment Gateway: PayPal
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Subscriptions\PaymentGateway\PayPal;

use ElkArte\Subscriptions\PaymentGateway\DisplayInterface;

/**
 * Class for returning available form data for this gateway
 *
 * @package Subscriptions
 */
class Display implements DisplayInterface
{
	/**
	 * Name of this payment gateway
	 * @var string
	 */
	public $title = 'PayPal';

	/**
	 * Return the admin settings for this gateway
	 *
	 * @return array
	 */
	public function getGatewaySettings()
	{
		global $txt;

		$setting_data = array(
			array(
				'text', 'paypal_email',
				'subtext' => $txt['paypal_email_desc']
			),
		);

		return $setting_data;
	}

	/**
	 * Is this enabled for new payments?
	 *
	 * @return boolean
	 */
	public function gatewayEnabled()
	{
		global $modSettings;

		return !empty($modSettings['paypal_email']);
	}

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
	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url)
	{
		global $modSettings, $txt, $boardurl;

		$return_data = array(
			'form' => 'https://www.' . (!empty($modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com/cgi-bin/webscr',
			'id' => 'paypal',
			'hidden' => array(),
			'title' => $txt['paypal'],
			'desc' => $txt['paid_confirm_paypal'],
			'submit' => $txt['paid_paypal_order'],
			'javascript' => '',
		);

		// All the standard bits.
		$return_data['hidden']['business'] = $modSettings['paypal_email'];
		$return_data['hidden']['item_name'] = $sub_data['name'] . ' ' . $txt['subscription'];
		$return_data['hidden']['item_number'] = $unique_id;
		$return_data['hidden']['currency_code'] = strtoupper($modSettings['paid_currency_code']);
		$return_data['hidden']['no_shipping'] = 1;
		$return_data['hidden']['no_note'] = 1;
		$return_data['hidden']['amount'] = $value;
		$return_data['hidden']['cmd'] = !$sub_data['repeatable'] ? '_xclick' : '_xclick-subscriptions';
		$return_data['hidden']['return'] = $return_url;
		$return_data['hidden']['a3'] = $value;
		$return_data['hidden']['src'] = 1;
		$return_data['hidden']['notify_url'] = $boardurl . '/subscriptions.php';

		// Now stuff dependant on what we're doing.
		if ($sub_data['flexible'])
		{
			$return_data['hidden']['p3'] = 1;
			$return_data['hidden']['t3'] = strtoupper(substr($period, 0, 1));
		}
		else
		{
			preg_match('~(\d*)(\w)~', $sub_data['real_length'], $match);
			$unit = $match[1];
			$period = $match[2];

			$return_data['hidden']['p3'] = $unit;
			$return_data['hidden']['t3'] = $period;
		}

		// If it's repeatable do some javascript to respect this idea.
		if (!empty($sub_data['repeatable']))
		{
			$return_data['javascript'] = '
				var container = document.getElementById("' . $return_data['id'] . '");

				container.innerHTML += \'<label for="do_paypal_recur"><input type="checkbox" name="do_paypal_recur" id="do_paypal_recur" checked="checked" onclick="switchPaypalRecur();" class="input_check" />' . $txt['paid_make_recurring'] . '</label><br />\';

				function switchPaypalRecur()
				{
					document.getElementById("paypal_cmd").value = document.getElementById("do_paypal_recur").checked ? "_xclick-subscriptions" : "_xclick";
				}';
		}

		return $return_data;
	}
}
