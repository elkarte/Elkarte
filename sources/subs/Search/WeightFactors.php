<?php

/**
 * Standard non full index, non custom index search
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

namespace ElkArte\Search;

class WeightFactors
{
	protected $_input_weights = [];
	protected $_is_admin = false;
	protected $_weight = array();
	protected $_weight_total = 0;
	protected $_weight_factors = [];

	public function __construct($weights, $is_admin = false)
	{
		$this->_input_weights = $weights;
		$this->_is_admin = (bool) $is_admin;

		$this->_setup_weight_factors();
	}

	public function getFactors()
	{
		return $this->_weight_factors;
	}

	public function getWeight()
	{
		return $this->_weight;
	}

	public function getTotal()
	{
		return $this->_weight_total;
	}

	/**
	 * Prepares the weighting factors
	 */
	private function _setup_weight_factors()
	{
		global $modSettings;

		$default_factors = $this->_weight_factors = array(
			'frequency' => array(
				'search' => 'COUNT(*) / (MAX(t.num_replies) + 1)',
				'results' => '(t.num_replies + 1)',
			),
			'age' => array(
				'search' => 'CASE WHEN MAX(m.id_msg) < {int:min_msg} THEN 0 ELSE (MAX(m.id_msg) - {int:min_msg}) / {int:recent_message} END',
				'results' => 'CASE WHEN t.id_first_msg < {int:min_msg} THEN 0 ELSE (t.id_first_msg - {int:min_msg}) / {int:recent_message} END',
			),
			'length' => array(
				'search' => 'CASE WHEN MAX(t.num_replies) < {int:huge_topic_posts} THEN MAX(t.num_replies) / {int:huge_topic_posts} ELSE 1 END',
				'results' => 'CASE WHEN t.num_replies < {int:huge_topic_posts} THEN t.num_replies / {int:huge_topic_posts} ELSE 1 END',
			),
			'subject' => array(
				'search' => 0,
				'results' => 0,
			),
			'first_message' => array(
				'search' => 'CASE WHEN MIN(m.id_msg) = MAX(t.id_first_msg) THEN 1 ELSE 0 END',
			),
			'sticky' => array(
				'search' => 'MAX(t.is_sticky)',
				'results' => 't.is_sticky',
			),
			'likes' => array(
				'search' => 'MAX(t.num_likes)',
				'results' => 't.num_likes',
			),
		);

		// These are fallback weights in case of errors somewhere.
		// Not intended to be passed to the hook
		$default_weights = array(
			'search_weight_frequency' => 30,
			'search_weight_age' => 25,
			'search_weight_length' => 20,
			'search_weight_subject' => 15,
			'search_weight_first_message' => 10,
		);

		call_integration_hook('integrate_search_weights', array(&$this->_weight_factors));

		// Set the weight factors for each area (frequency, age, etc) as defined in the ACP
		$this->_calculate_weights($this->_weight_factors, $modSettings);

		// Zero weight.  Weightless :P.
		if (empty($this->_weight_total))
		{
			// Admins can be bothered with a failure
			if ($this->_is_admin)
			{
				throw new \ElkArte\Exceptions\Exception('search_invalid_weights');
			}

			// Even if users will get an answer, the admin should know something is broken
			Errors::instance()->log_lang_error('search_invalid_weights');

			// Instead is better to give normal users and guests some kind of result
			// using our defaults.
			// Using a different variable here because it may be the hook is screwing
			// things up
			$this->_calculate_weights($default_factors, $default_weights);
		}
	}

	/**
	 * Fill the $_weight variable and calculate the total weight
	 *
	 * @param mixed[] $factors
	 * @param int[] $weights
	 */
	private function _calculate_weights($factors, $weights)
	{
		foreach ($factors as $weight_factor => $value)
		{
			$this->_weight[$weight_factor] = (int) ($weights['search_weight_' . $weight_factor] ?? 0);
			$this->_weight_total += $this->_weight[$weight_factor];
		}
	}
}
