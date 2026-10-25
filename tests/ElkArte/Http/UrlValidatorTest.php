<?php

namespace ElkArte\Http;

use PHPUnit\Framework\TestCase;

class UrlValidatorTest extends TestCase
{
	/**
	 * Test private and loopback IPv4 addresses are rejected.
	 */
	public function testPrivateAndLoopbackIpv4(): void
	{
		$privateIps = [
			'127.0.0.1',
			'127.1.2.3',
			'10.0.0.1',
			'10.255.255.255',
			'172.16.0.1',
			'172.31.255.254',
			'192.168.1.1',
			'192.168.100.50',
			'169.254.169.254', // AWS/GCP/Azure metadata
			'169.254.1.1',
			'0.0.0.0',
			'100.64.0.1',
			'192.0.2.1',
			'198.51.100.1',
			'203.0.113.1',
			'224.0.0.1',
			'240.0.0.1',
			'255.255.255.255',
		];

		foreach ($privateIps as $ip)
		{
			$this->assertFalse(
				UrlValidator::isIpAllowed($ip),
				"Expected IP {$ip} to be rejected"
			);
			$this->assertFalse(
				UrlValidator::isValid("http://{$ip}/"),
				"Expected URL with IP {$ip} to be rejected"
			);
		}
	}

	/**
	 * Test IPv6 special and private ranges are rejected.
	 */
	public function testPrivateAndSpecialIpv6(): void
	{
		$privateIps = [
			'::1',
			'::',
			'fe80::1',
			'fc00::1',
			'fd00::1',
			'ff02::1',
			'2001:db8::1',
			'::ffff:127.0.0.1',
			'::ffff:169.254.169.254',
			'::ffff:10.0.0.1',
			'::ffff:192.168.1.1',
		];

		foreach ($privateIps as $ip)
		{
			$this->assertFalse(
				UrlValidator::isIpAllowed($ip),
				"Expected IPv6 {$ip} to be rejected"
			);
		}
	}

	/**
	 * Test public IPs are accepted.
	 */
	public function testPublicIps(): void
	{
		$publicIps = [
			'8.8.8.8',
			'1.1.1.1',
			'93.184.216.34',
			'2606:4700:4700::1111',
		];

		foreach ($publicIps as $ip)
		{
			$this->assertTrue(
				UrlValidator::isIpAllowed($ip),
				"Expected public IP {$ip} to be allowed"
			);
		}
	}

	/**
	 * Test schemes are enforced.
	 */
	public function testSchemeValidation(): void
	{
		$this->assertFalse(UrlValidator::isValid('file:///etc/passwd'));
		$this->assertFalse(UrlValidator::isValid('gopher://127.0.0.1:6379/_'));
		$this->assertFalse(UrlValidator::isValid('dict://127.0.0.1:11211/'));
		$this->assertFalse(UrlValidator::isValid('ftp://example.com/test'));
		$this->assertFalse(UrlValidator::isValid('php://filter/read=convert.base64-encode/resource=index.php'));
		$this->assertFalse(UrlValidator::isValid('data:text/plain;base64,SGVsbG8='));
		$this->assertFalse(UrlValidator::isValid('javascript:alert(1)'));
	}

	/**
	 * Test dangerous ports are rejected by default.
	 */
	public function testPortValidation(): void
	{
		// Disallowed ports (e.g. redis, memcached, mysql, smtp, ssh)
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34:6379/'));
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34:11211/'));
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34:3306/'));
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34:22/'));
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34:25/'));

		// Allowed ports
		$this->assertTrue(UrlValidator::isValid('http://93.184.216.34:80/'));
		$this->assertTrue(UrlValidator::isValid('https://93.184.216.34:443/'));
		$this->assertTrue(UrlValidator::isValid('http://93.184.216.34:8080/'));
		$this->assertTrue(UrlValidator::isValid('https://93.184.216.34:8443/'));
	}

	/**
	 * Test localhost and internal domains are rejected.
	 */
	public function testLocalhostDomains(): void
	{
		$this->assertFalse(UrlValidator::isValid('http://localhost/'));
		$this->assertFalse(UrlValidator::isValid('http://localhost:8080/secret'));
		$this->assertFalse(UrlValidator::isValid('http://test.localhost/'));
		$this->assertFalse(UrlValidator::isValid('http://server.local/'));
		$this->assertFalse(UrlValidator::isValid('http://service.internal/'));
		$this->assertFalse(UrlValidator::isValid('http://router.lan/'));
	}

	/**
	 * Test buildAbsoluteUrl resolves relative and absolute redirects correctly.
	 */
	public function testBuildAbsoluteUrl(): void
	{
		$base = 'http://example.com/foo/bar.php?id=1';

		$this->assertSame(
			'http://example.com/foo/baz.php',
			UrlValidator::buildAbsoluteUrl($base, 'baz.php')
		);

		$this->assertSame(
			'http://example.com/other/path',
			UrlValidator::buildAbsoluteUrl($base, '/other/path')
		);

		$this->assertSame(
			'https://otherdomain.com/test',
			UrlValidator::buildAbsoluteUrl($base, 'https://otherdomain.com/test')
		);
	}
}
