<?php

/**
 * Dummy
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

namespace ElkArte\UrlGenerator;

abstract class Abstract_Url_Generator // implements Generic_UrlGenerator
{
	protected $_separator = ';';
	protected $_types = array();

	public function setSeparator($separator)
	{
		$this->_separator = $separator;
	}

	public function getTypes()
	{
		return $this->_types;
	}

	abstract public function generate($params);
}
