<?php

use ElkArte\Server;
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
}