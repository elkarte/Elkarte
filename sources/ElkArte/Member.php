<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class Member
 *
 * This class collects all the data related to a certain member extracted
 * from the db
 */
class Member extends \ElkArte\ValuesContainer
{
	protected $set = '';

	public function __construct($data, $set)
	{
		$this->data = $data;
		$this->set = $set;
	}

	public function append($type, $data)
	{
		$this->data[$type] = $data;
	}

	public function get($item)
	{
		if (isset($this->data[$item]))
		{
			return $this->data[$item];
		}
		else
		{
			return null;
		}
	}

	public function getContext($id)
	{
	}

	public function toArray()
	{
		return [
			'set' => $this->set,
			'data' => $this->data,
		];
	}
}
