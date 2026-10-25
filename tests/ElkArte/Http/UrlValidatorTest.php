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

	/**
	 * Test RFC 3986 relative reference resolution.
	 */
	public function testBuildAbsoluteUrlRfc3986(): void
	{
		$base = 'http://example.com/foo/bar.php?id=1';

		$this->assertSame('http://example.com/foo/bar.php?x=1', UrlValidator::buildAbsoluteUrl($base, '?x=1'));
		$this->assertSame('http://example.com/foo/bar.php?id=1#frag', UrlValidator::buildAbsoluteUrl($base, '#frag'));
		$this->assertSame('http://example.com/foo/bar.php?id=1', UrlValidator::buildAbsoluteUrl($base, ''));
		$this->assertSame('http://example.com/c', UrlValidator::buildAbsoluteUrl($base, '../c'));
		$this->assertSame('http://example.com/foo/c', UrlValidator::buildAbsoluteUrl($base, './c'));
		$this->assertSame('http://example.com/foo/c/', UrlValidator::buildAbsoluteUrl($base, 'c/'));
		$this->assertSame('http://example.com/a/', UrlValidator::buildAbsoluteUrl($base, '/a/b/..'));
		$this->assertSame('http://other.com/x', UrlValidator::buildAbsoluteUrl($base, '//other.com/x'));
		$this->assertSame('https://example.com/', UrlValidator::buildAbsoluteUrl('https://example.com', ''));
		$this->assertSame(
			'http://user:pass@example.com:8080/a/c',
			UrlValidator::buildAbsoluteUrl('http://user:pass@example.com:8080/a/b', 'c')
		);
	}

	/**
	 * Test unsafe references are rejected.
	 */
	public function testBuildAbsoluteUrlRejectsUnsafe(): void
	{
		$base = 'http://example.com/foo/bar.php';

		$this->assertSame('', UrlValidator::buildAbsoluteUrl($base, 'javascript:alert(1)'));
		$this->assertSame('', UrlValidator::buildAbsoluteUrl($base, 'file:///etc/passwd'));
		$this->assertSame('', UrlValidator::buildAbsoluteUrl($base, "http://example.com/a\r\nX-Injected: 1"));
		$this->assertSame('', UrlValidator::buildAbsoluteUrl('file:///etc/passwd', 'x'));
	}

	/**
	 * Test IPv6 addresses with an embedded IPv4 address.
	 */
	public function testEmbeddedIpv4InIpv6(): void
	{
		$blocked = [
			'::7f00:1',
			'::a9fe:a9fe',
			'::127.0.0.1',
			'::169.254.169.254',
			'::ffff:0:127.0.0.1',
			'::ffff:0:169.254.169.254',
			'::ffff:0:10.0.0.1',
			'64:ff9b::10.0.0.1',
			'2002:0a00:0001::',
		];

		foreach ($blocked as $ip)
		{
			$this->assertFalse(UrlValidator::isIpAllowed($ip), "Expected embedded IPv4 in {$ip} to be rejected");
		}

		$allowed = [
			'::8.8.8.8',
			'::ffff:0:8.8.8.8',
			'::ffff:8.8.8.8',
			'64:ff9b::8.8.8.8',
			'2002:0808:0808::1',
		];

		foreach ($allowed as $ip)
		{
			$this->assertTrue(UrlValidator::isIpAllowed($ip), "Expected embedded public IPv4 in {$ip} to be allowed");
		}

		$this->assertFalse(UrlValidator::isValid('http://[::a9fe:a9fe]/latest/meta-data/'));
	}

	/**
	 * Test CIDR boundaries for IPv4 and IPv6 ranges.
	 */
	public function testCidrBoundaries(): void
	{
		$blocked = [
			'100.64.0.0',
			'100.127.255.255',
			'172.16.0.0',
			'172.31.255.255',
			'198.18.0.0',
			'198.19.255.255',
			'224.0.0.0',
			'239.255.255.255',
			'240.0.0.0',
			'255.255.255.255',
			'fe80::1',
			'febf::1',
			'fc00::1',
			'fdff::1',
			'2001:1f::1',
			'2001:2f::1',
			'2001:db8::1',
		];

		foreach ($blocked as $ip)
		{
			$this->assertFalse(UrlValidator::isIpAllowed($ip), "Expected {$ip} to be rejected");
		}

		$allowed = [
			'100.63.255.255',
			'100.128.0.0',
			'172.15.255.255',
			'172.32.0.0',
			'198.17.255.255',
			'198.20.0.0',
			'223.255.255.255',
			'fe00::1',
			'2001:30::1',
			'2001:db9::1',
		];

		foreach ($allowed as $ip)
		{
			$this->assertTrue(UrlValidator::isIpAllowed($ip), "Expected {$ip} to be allowed");
		}
	}

	/**
	 * Test option values are normalised instead of causing type errors.
	 */
	public function testOptionTypes(): void
	{
		$this->assertTrue(UrlValidator::isValid('http://93.184.216.34/', ['allowed_schemes' => 'http']));
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34/', ['allowed_schemes' => 'https']));
		$this->assertTrue(UrlValidator::isValid('http://93.184.216.34/', ['allowed_ports' => ['80', '443']]));
		$this->assertTrue(UrlValidator::isValid('http://93.184.216.34/', ['allowed_ports' => '80']));
		$this->assertFalse(UrlValidator::isValid('http://93.184.216.34/', ['allowed_ports' => ['443']]));
		$this->assertTrue(UrlValidator::isValid('http://93.184.216.34:6379/', ['allowed_ports' => null]));
		$this->assertFalse(UrlValidator::isValid('http://127.0.0.1/', ['allow_private' => 'false']));
		$this->assertTrue(UrlValidator::isValid('http://127.0.0.1/', ['allow_private' => 'true']));
	}

	/**
	 * Test host_bare is available for TLS and resolver use.
	 */
	public function testHostBare(): void
	{
		$validated = UrlValidator::validate('http://[2606:4700:4700::1111]:8080/path');

		$this->assertIsArray($validated);
		$this->assertSame('[2606:4700:4700::1111]', $validated['host']);
		$this->assertSame('2606:4700:4700::1111', $validated['host_bare']);
	}

	/**
	 * Test IPv6 bracketing helper.
	 */
	public function testBracketIp(): void
	{
		$this->assertSame('[2606:4700:4700::1111]', UrlValidator::bracketIp('2606:4700:4700::1111'));
		$this->assertSame('93.184.216.34', UrlValidator::bracketIp('93.184.216.34'));
	}

	/**
	 * Test CURLOPT_RESOLVE entry construction.
	 */
	public function testResolveEntry(): void
	{
		$this->assertSame(
			'example.com:443:[2606:4700:4700::1111]',
			UrlValidator::resolveEntry([
				'host_bare' => 'example.com',
				'port' => 443,
				'valid_ip' => '2606:4700:4700::1111',
			])
		);

		$this->assertSame(
			'example.com:80:93.184.216.34',
			UrlValidator::resolveEntry([
				'host_bare' => 'example.com',
				'port' => 80,
				'valid_ip' => '93.184.216.34',
			])
		);

		$this->assertSame('', UrlValidator::resolveEntry([
			'host_bare' => '2606:4700:4700::1111',
			'port' => 443,
			'valid_ip' => '2606:4700:4700::1111',
		]));

		$this->assertSame('', UrlValidator::resolveEntry([
			'host_bare' => '93.184.216.34',
			'port' => 80,
			'valid_ip' => '93.184.216.34',
		]));

		$this->assertSame('', UrlValidator::resolveEntry([
			'host_bare' => 'example.com',
			'port' => 443,
			'valid_ip' => null,
		]));
	}
}
