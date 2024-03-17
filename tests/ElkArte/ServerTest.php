<?php

namespace ElkArte;

use PHPUnit\Framework\TestCase;

class ServerTest extends TestCase
{
	/**
	 * Test that the `getProtocol()` method of the `Server` class
	 * correctly returns web server's protocol.
	 */
	public function testGetProtocol()
	{
		$serverData = [
			'SERVER_PROTOCOL' => 'HTTP/1.1',
		];

		$server = new Server($serverData);

		$this->assertEquals('HTTP/1.1', $server->getProtocol());
	}

	/**
	 * Testing `getProtocol()` method when 'SERVER_PROTOCOL' value is not set.
	 * In this case, the method should return 'HTTP/1.0'
	 */
	public function testGetProtocolDefault()
	{
		$serverData = [];

		$server = new Server($serverData);

		$this->assertEquals('HTTP/1.0', $server->getProtocol());
	}

	/**
	 * Test the `getProtocol()` method to handle invalid value of 'SERVER_PROTOCOL'.
	 * The method should return the default 'HTTP/1.0'
	 */
	public function testGetProtocolInvalid()
	{
		$serverData = [
			'SERVER_PROTOCOL' => 'INVALID_PROTOCOL',
		];

		$server = new Server($serverData);

		$this->assertEquals('HTTP/1.0', $server->getProtocol());
	}

	/**
	 * Test the supportsSSL method
	 * in the Server class.
	 */
	public function testSupportsSSL()
	{
		$server = new Server();

		// Test case when HTTPS is 'on'
		$server->HTTPS = 'on';
		$this->assertTrue($server->supportsSSL());

		// Test case when HTTPS is 1
		$server->HTTPS = 1;
		$this->assertTrue($server->supportsSSL());

		// Test case when HTTPS is 'off'
		$server->HTTPS = 'off';
		$this->assertFalse($server->supportsSSL());

		// Test when REQUEST_SCHEME is 'https'
		$server->REQUEST_SCHEME = 'https';
		$this->assertTrue($server->supportsSSL());

		// Test when REQUEST_SCHEME is not 'https'
		$server->REQUEST_SCHEME = 'http';
		$this->assertFalse($server->supportsSSL());

		// Test case when SERVER_PORT is 443
		$server->SERVER_PORT = 443;
		$this->assertTrue($server->supportsSSL());

		// Test case when SERVER_PORT is not 443
		$server->SERVER_PORT = 80;
		$this->assertFalse($server->supportsSSL());

		// Test when HTTP_X_FORWARDED_PROTO is 'https'
		$server->HTTP_X_FORWARDED_PROTO = 'https';
		$this->assertTrue($server->supportsSSL());

		// Test when HTTP_X_FORWARDED_PROTO is not 'https'
		$server->HTTP_X_FORWARDED_PROTO = 'http';
		$this->assertFalse($server->supportsSSL());
	}
}
