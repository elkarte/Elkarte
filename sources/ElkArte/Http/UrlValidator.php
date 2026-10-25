<?php

/**
 * URL and IP validation helper for preventing SSRF and securing outbound HTTP requests.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\Http;

/**
 * Validates URLs and hostnames against SSRF (Server-Side Request Forgery) vulnerabilities.
 */
class UrlValidator
{
	/** @var array Default allowed schemes */
	public const DEFAULT_ALLOWED_SCHEMES = ['http', 'https'];

	/** @var array Default allowed ports */
	public const DEFAULT_ALLOWED_PORTS = [80, 443, 8080, 8443];

	/**
	 * Check if a URL is valid and safe from SSRF.
	 *
	 * @param string $url The URL to check
	 * @param array $options Optional rules:
	 *   - allowed_schemes: array of schemes (default ['http', 'https'])
	 *   - allowed_ports: array of ports or null for any port (default [80, 443, 8080, 8443])
	 *   - allow_private: bool (default false)
	 *   - dns_resolve: bool (default true)
	 * @return bool
	 */
	public static function isValid(string $url, array $options = []): bool
	{
		return self::validate($url, $options) !== false;
	}

	/**
	 * Validate and parse a URL for safe HTTP fetching.
	 *
	 * @param string $url
	 * @param array $options
	 * @return array|false Parsed & validated URL details, or false if invalid/blocked.
	 */
	public static function validate(string $url, array $options = []): array|false
	{
		$url = trim($url);
		if ($url === '')
		{
			return false;
		}

		// Basic filter check
		if (!filter_var($url, FILTER_VALIDATE_URL))
		{
			return false;
		}

		$parsed = parse_url($url);
		if ($parsed === false || empty($parsed['host']))
		{
			return false;
		}

		$scheme = strtolower($parsed['scheme'] ?? '');
		$allowedSchemes = $options['allowed_schemes'] ?? self::DEFAULT_ALLOWED_SCHEMES;
		if (!in_array($scheme, $allowedSchemes, true))
		{
			return false;
		}

		$port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
		if ($port < 1 || $port > 65535)
		{
			return false;
		}

		$allowedPorts = array_key_exists('allowed_ports', $options) ? $options['allowed_ports'] : self::DEFAULT_ALLOWED_PORTS;
		if ($allowedPorts !== null && !in_array($port, $allowedPorts, true))
		{
			return false;
		}

		$host = $parsed['host'];
		$allowPrivate = (bool) ($options['allow_private'] ?? false);
		$dnsResolve = (bool) ($options['dns_resolve'] ?? true);

		$ips = [];
		if ($dnsResolve)
		{
			$ips = self::resolveAndValidateHost($host, $allowPrivate);
			if (empty($ips))
			{
				return false;
			}
		}

		$path = $parsed['path'] ?? '/';
		if ($path === '' || $path[0] !== '/')
		{
			$path = '/' . $path;
		}

		$query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
		$defaultPort = ($scheme === 'https') ? 443 : 80;
		$portSuffix = ($port !== $defaultPort) ? ':' . $port : '';
		$normalizedUrl = $scheme . '://' . $host . $portSuffix . $path . $query;

		return [
			'scheme' => $scheme,
			'host' => $host,
			'port' => $port,
			'path' => $path . $query,
			'path_only' => $path,
			'query' => $parsed['query'] ?? '',
			'ips' => $ips,
			'valid_ip' => $ips[0] ?? null,
			'url' => $normalizedUrl,
		];
	}

	/**
	 * Resolves a hostname and checks that all resolved IP addresses are public and safe.
	 *
	 * @param string $host Hostname or IP literal
	 * @param bool $allowPrivate
	 * @return array|false List of safe IP strings, or false on resolution failure or forbidden IP.
	 */
	public static function resolveAndValidateHost(string $host, bool $allowPrivate = false): array|false
	{
		$cleanHost = trim($host, '[]');
		if ($cleanHost === '')
		{
			return false;
		}

		// Direct IP check
		if (filter_var($cleanHost, FILTER_VALIDATE_IP))
		{
			if (!$allowPrivate && !self::isIpAllowed($cleanHost))
			{
				return false;
			}

			return [$cleanHost];
		}

		// Disallow localhost and special internal TLDs
		$lowerHost = strtolower($cleanHost);
		if ($lowerHost === 'localhost'
			|| str_ends_with($lowerHost, '.localhost')
			|| str_ends_with($lowerHost, '.local')
			|| str_ends_with($lowerHost, '.internal')
			|| str_ends_with($lowerHost, '.lan')
			|| str_ends_with($lowerHost, '.home')
			|| str_ends_with($lowerHost, '.corp')
			|| str_ends_with($lowerHost, '.arpa'))
		{
			if (!$allowPrivate)
			{
				return false;
			}
		}

		$ips = [];

		// Try dns_get_record for IPv4 and IPv6
		if (function_exists('dns_get_record'))
		{
			$records = @dns_get_record($cleanHost, DNS_A + DNS_AAAA);
			if (is_array($records))
			{
				foreach ($records as $record)
				{
					if (isset($record['type']))
					{
						if ($record['type'] === 'A' && !empty($record['ip']))
						{
							$ips[] = $record['ip'];
						}
						elseif ($record['type'] === 'AAAA' && !empty($record['ipv6']))
						{
							$ips[] = $record['ipv6'];
						}
					}
				}
			}
		}

		// Fallback / supplement with gethostbynamel for IPv4
		if (function_exists('gethostbynamel'))
		{
			$v4 = @gethostbynamel($cleanHost);
			if (is_array($v4))
			{
				$ips = array_merge($ips, $v4);
			}
		}

		$ips = array_values(array_unique($ips));
		if (empty($ips))
		{
			return false;
		}

		// If not allowing private, verify EVERY resolved IP address
		if (!$allowPrivate)
		{
			foreach ($ips as $ip)
			{
				if (!self::isIpAllowed($ip))
				{
					return false;
				}
			}
		}

		return $ips;
	}

	/**
	 * Determines if an IP address is a safe, routable public IP (not private, reserved, loopback, link-local, etc.).
	 *
	 * @param string $ip
	 * @return bool
	 */
	public static function isIpAllowed(string $ip): bool
	{
		if (!filter_var($ip, FILTER_VALIDATE_IP))
		{
			return false;
		}

		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
		{
			return false;
		}

		$packed = @inet_pton($ip);
		if ($packed === false)
		{
			return false;
		}

		// IPv4 (4 bytes)
		if (strlen($packed) === 4)
		{
			return self::isIpv4Allowed($packed);
		}

		// IPv6 (16 bytes)
		if (strlen($packed) === 16)
		{
			return self::isIpv6Allowed($packed);
		}

		return false;
	}

	/**
	 * Check packed 4-byte IPv4 address against reserved / private ranges.
	 *
	 * @param string $packed
	 * @return bool
	 */
	protected static function isIpv4Allowed(string $packed): bool
	{
		$bytes = array_values(unpack('C4', $packed));

		// 0.0.0.0/8 (Current network)
		if ($bytes[0] === 0)
		{
			return false;
		}

		// 10.0.0.0/8 (Private RFC 1918)
		if ($bytes[0] === 10)
		{
			return false;
		}

		// 100.64.0.0/10 (Carrier-grade NAT RFC 6598)
		if ($bytes[0] === 100 && ($bytes[1] >= 64 && $bytes[1] <= 127))
		{
			return false;
		}

		// 127.0.0.0/8 (Loopback)
		if ($bytes[0] === 127)
		{
			return false;
		}

		// 169.254.0.0/16 (Link-local / Cloud metadata RFC 3927)
		if ($bytes[0] === 169 && $bytes[1] === 254)
		{
			return false;
		}

		// 172.16.0.0/12 (Private RFC 1918)
		if ($bytes[0] === 172 && ($bytes[1] >= 16 && $bytes[1] <= 31))
		{
			return false;
		}

		// 192.0.0.0/24 (IETF Protocol Assignments RFC 6890)
		if ($bytes[0] === 192 && $bytes[1] === 0 && $bytes[2] === 0)
		{
			return false;
		}

		// 192.0.2.0/24 (TEST-NET-1 RFC 5737)
		if ($bytes[0] === 192 && $bytes[1] === 0 && $bytes[2] === 2)
		{
			return false;
		}

		// 192.168.0.0/16 (Private RFC 1918)
		if ($bytes[0] === 192 && $bytes[1] === 168)
		{
			return false;
		}

		// 198.18.0.0/15 (Benchmarking RFC 2544)
		if ($bytes[0] === 198 && ($bytes[1] === 18 || $bytes[1] === 19))
		{
			return false;
		}

		// 198.51.100.0/24 (TEST-NET-2 RFC 5737)
		if ($bytes[0] === 198 && $bytes[1] === 51 && $bytes[2] === 100)
		{
			return false;
		}

		// 203.0.113.0/24 (TEST-NET-3 RFC 5737)
		if ($bytes[0] === 203 && $bytes[1] === 0 && $bytes[2] === 113)
		{
			return false;
		}

		// 224.0.0.0/4 (Multicast) and 240.0.0.0/4 (Reserved) and 255.255.255.255 (Broadcast)
		if ($bytes[0] >= 224)
		{
			return false;
		}

		return true;
	}

	/**
	 * Check packed 16-byte IPv6 address against reserved / private ranges.
	 *
	 * @param string $packed
	 * @return bool
	 */
	protected static function isIpv6Allowed(string $packed): bool
	{
		$bytes = array_values(unpack('C16', $packed));

		// ::/128 (Unspecified)
		if ($packed === str_repeat("\0", 16))
		{
			return false;
		}

		// ::1/128 (Loopback)
		if ($packed === str_repeat("\0", 15) . "\1")
		{
			return false;
		}

		// ::ffff:0:0/96 (IPv4-mapped IPv6)
		if (substr($packed, 0, 10) === str_repeat("\0", 10) && substr($packed, 10, 2) === "\xff\xff")
		{
			return self::isIpv4Allowed(substr($packed, 12, 4));
		}

		// 64:ff9b::/96 (IPv4/IPv6 translation RFC 6052)
		if (substr($packed, 0, 4) === "\x00\x64\xff\x9b" && substr($packed, 4, 8) === str_repeat("\0", 8))
		{
			return self::isIpv4Allowed(substr($packed, 12, 4));
		}

		// 2002::/16 (6to4 RFC 3056) - contains embedded IPv4 in bytes 2..5
		if ($bytes[0] === 0x20 && $bytes[1] === 0x02)
		{
			if (!self::isIpv4Allowed(substr($packed, 2, 4)))
			{
				return false;
			}
		}

		// fc00::/7 (Unique Local Address - ULA RFC 4193)
		if (($bytes[0] & 0xfe) === 0xfc)
		{
			return false;
		}

		// fe80::/10 (Link-Local RFC 4291)
		if ($bytes[0] === 0xfe && ($bytes[1] & 0xc0) === 0x80)
		{
			return false;
		}

		// ff00::/8 (Multicast RFC 4291)
		if ($bytes[0] === 0xff)
		{
			return false;
		}

		// 2001:db8::/32 (Documentation RFC 3849)
		if ($bytes[0] === 0x20 && $bytes[1] === 0x01 && $bytes[2] === 0x0d && $bytes[3] === 0xb8)
		{
			return false;
		}

		// 2001:10::/28 and 2001:20::/28 (ORCHID RFC 4843 / RFC 7343)
		if ($bytes[0] === 0x20 && $bytes[1] === 0x01 && ($bytes[2] === 0x00 && ($bytes[3] >= 0x10 && $bytes[3] <= 0x2f)))
		{
			return false;
		}

		return true;
	}

	/**
	 * Build fully qualified absolute URL from a base URL and a potentially relative redirect location.
	 *
	 * @param string $baseUrl
	 * @param string $relativeUrl
	 * @return string
	 */
	public static function buildAbsoluteUrl(string $baseUrl, string $relativeUrl): string
	{
		$relativeUrl = trim($relativeUrl);
		$lastUrlParse = parse_url($baseUrl);
		$newUrlParse = parse_url($relativeUrl);

		if ($newUrlParse === false)
		{
			return $baseUrl;
		}

		if (!empty($newUrlParse['scheme']) && !empty($newUrlParse['host']))
		{
			return $relativeUrl;
		}

		$scheme = $newUrlParse['scheme'] ?? ($lastUrlParse['scheme'] ?? 'http');
		$host = $newUrlParse['host'] ?? ($lastUrlParse['host'] ?? '');
		$port = isset($newUrlParse['port']) ? ':' . $newUrlParse['port'] : (isset($lastUrlParse['port']) ? ':' . $lastUrlParse['port'] : '');

		$path = $newUrlParse['path'] ?? '';
		if ($path === '' || $path[0] !== '/')
		{
			$lastPath = $lastUrlParse['path'] ?? '/';
			$lastDir = substr($lastPath, 0, (int) strrpos($lastPath, '/') + 1);
			$path = $lastDir . $path;
		}

		$query = !empty($newUrlParse['query']) ? '?' . $newUrlParse['query'] : '';

		return $scheme . '://' . $host . $port . $path . $query;
	}
}
