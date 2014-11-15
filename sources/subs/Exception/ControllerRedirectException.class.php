<?php

class Controller_Redirect_Exception extends Exception
{
	protected $_controller;
	protected $_method;

	public function __construct($controller, $method)
	{
		$this->_controller = $controller;
		$this->_method = $method;
	}

	public function doRedirect()
	{
		$controller = new $this->_controller();
		return $controller->$this->_method();
	}
}