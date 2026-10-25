<?php

namespace ElkArte\Http;

use PHPUnit\Framework\TestCase;

class FtpConnectionTest extends TestCase
{
	/**
	 * Test IPv6 literals survive sanitising and are bracketed for sockets.
	 */
	public function testGetServerPreservesIpv6(): void
	{
		$ftp = new FtpConnection(null);

		$this->assertSame('[2a00:1450:4001::1]', $ftp->getServer('2a00:1450:4001::1'));
		$this->assertSame('[2a00:1450:4001::1]', $ftp->getServer('[2a00:1450:4001::1]'));
		$this->assertSame('[2a00:1450:4001::1]', $ftp->getServer('[2a00:1450:4001::1]:21'));
		$this->assertSame('[2a00:1450:4001::1]', $ftp->getServer('ftp://[2a00:1450:4001::1]/file.txt'));
		$this->assertSame('ssl://[2a00:1450:4001::1]', $ftp->getServer('ssl://[2a00:1450:4001::1]'));
		$this->assertSame('ssl://[2a00:1450:4001::1]', $ftp->getServer('ftps://2a00:1450:4001::1'));
	}

	/**
	 * Test hostnames and IPv4 are sanitised as before.
	 */
	public function testGetServerHostnames(): void
	{
		$ftp = new FtpConnection(null);

		$this->assertSame('example.com', $ftp->getServer('example.com'));
		$this->assertSame('example.com', $ftp->getServer('ftp://example.com'));
		$this->assertSame('example.com', $ftp->getServer('example.com:21'));
		$this->assertSame('example.com', $ftp->getServer('example.com/path'));
		$this->assertSame('example.com', $ftp->getServer('user:pass@example.com'));
		$this->assertSame('ssl://example.com', $ftp->getServer('ftps://example.com'));
		$this->assertSame('ssl://example.com', $ftp->getServer('https://example.com'));
		$this->assertSame('1.2.3.4', $ftp->getServer('1.2.3.4'));
		$this->assertSame('1.2.3.4', $ftp->getServer('1.2.3.4:21'));
	}
}
