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
	public const DEFAULT_ALLOWED_PORTS = [80, 443];

	/** @var string[] Blocked IPv4 ranges (CIDR) */
	protected const IPV4_BLOCKED_CIDRS = [
		'0.0.0.0/8',
		'10.0.0.0/8',
		'100.64.0.0/10',
		'127.0.0.0/8',
		'169.254.0.0/16',
		'172.16.0.0/12',
		'192.0.0.0/24',
		'192.0.2.0/24',
		'192.168.0.0/16',
		'198.18.0.0/15',
		'198.51.100.0/24',
		'203.0.113.0/24',
		'224.0.0.0/4',
		'240.0.0.0/4',
	];

	/** @var string[] Blocked IPv6 ranges (CIDR) */
	protected const IPV6_BLOCKED_CIDRS = [
		'fc00::/7',
		'fe80::/10',
		'ff00::/8',
		'2001:db8::/32',
		'2001:10::/28',
		'2001:20::/28',
	];

	/** @var array IPv6 prefixes with an embedded IPv4 and its byte offset */
	protected const IPV6_EMBEDDED_CIDRS = [
		'::/96' => 12,
		'::ffff:0:0/96' => 12,
		'::ffff:0:0:0/96' => 12,
		'64:ff9b::/96' => 12,
		'2002::/16' => 2,
	];

	/**
	 * Check if a URL is valid and safe from SSRF.
	 *
	 * @param string $url The URL to check
	 * @param array $options Optional rules:
	 *   - allowed_schemes: array of schemes (default ['http', 'https'])
	 *   - allowed_ports: array of ports or null for any port (default [80, 443])
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
		$allowedSchemes = (array) ($options['allowed_schemes'] ?? self::DEFAULT_ALLOWED_SCHEMES);
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
		if ($allowedPorts !== null)
		{
			$allowedPorts = array_map('intval', (array) $allowedPorts);
			if (!in_array($port, $allowedPorts, true))
			{
				return false;
			}
		}

		$host = $parsed['host'];
		$hostBare = trim($host, '[]');
		$allowPrivate = filter_var($options['allow_private'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
		$dnsResolve = filter_var($options['dns_resolve'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

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
			'host_bare' => $hostBare,
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
		static $resolved = [];

		$cleanHost = trim($host, '[]');
		if ($cleanHost === '')
		{
			return false;
		}

		// Direct IP check xxx.xxx.xxx.xxx or IPv6 literal
		if (filter_var($cleanHost, FILTER_VALIDATE_IP))
		{
			if (!$allowPrivate && !self::isIpAllowed($cleanHost))
			{
				return false;
			}

			return [$cleanHost];
		}

		// Not a direct IP, first check for localhost and special internal TLDs
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

		// Begin to resolve the hostname and check the IPs
		$cacheKey = ($allowPrivate ? '1|' : '0|') . $lowerHost;
		if (isset($resolved[$cacheKey]))
		{
			return $resolved[$cacheKey];
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

		// Fallback to gethostbynamel for IPv4 when no records were returned
		if (empty($ips) && function_exists('gethostbynamel'))
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

		if (count($resolved) < 32)
		{
			$resolved[$cacheKey] = $ips;
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
		foreach (self::IPV4_BLOCKED_CIDRS as $cidr)
		{
			if (self::matchesCidr($packed, $cidr))
			{
				return false;
			}
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

		// Addresses with an embedded IPv4 address
		foreach (self::IPV6_EMBEDDED_CIDRS as $cidr => $offset)
		{
			if (self::matchesCidr($packed, $cidr))
			{
				return self::isIpv4Allowed(substr($packed, $offset, 4));
			}
		}

		foreach (self::IPV6_BLOCKED_CIDRS as $cidr)
		{
			if (self::matchesCidr($packed, $cidr))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Check a packed IP address against a CIDR range.
	 *
	 * @param string $packed
	 * @param string $cidr
	 * @return bool
	 */
	protected static function matchesCidr(string $packed, string $cidr): bool
	{
		static $networks = [];

		[$network, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
		$bits = $bits === null ? strlen($packed) * 8 : (int) $bits;

		if (!isset($networks[$network]))
		{
			$networks[$network] = inet_pton($network);
		}

		$networkPacked = $networks[$network];
		if ($networkPacked === false)
		{
			return false;
		}

		$wholeBytes = intdiv($bits, 8);
		if ($wholeBytes > 0 && substr($packed, 0, $wholeBytes) !== substr($networkPacked, 0, $wholeBytes))
		{
			return false;
		}

		$remainingBits = $bits % 8;
		if ($remainingBits === 0)
		{
			return true;
		}

		$mask = (0xff << (8 - $remainingBits)) & 0xff;

		return (ord($packed[$wholeBytes]) & $mask) === (ord($networkPacked[$wholeBytes]) & $mask);
	}

	/**
	 * Wrap an IPv6 literal in brackets for URLs and socket addresses.
	 *
	 * @param string $ip
	 * @return string
	 */
	public static function bracketIp(string $ip): string
	{
		return str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
	}

	/**
	 * Build a CURLOPT_RESOLVE entry for a validated URL.
	 *
	 * IP-literal URLs need no pinning because no name resolution takes place.
	 *
	 * @param array $validated Result of validate()
	 * @return string Resolve entry, or an empty string when not applicable
	 */
	public static function resolveEntry(array $validated): string
	{
		$host = $validated['host_bare'] ?? '';
		$ip = $validated['valid_ip'] ?? '';
		$port = (int) ($validated['port'] ?? 0);

		if ($host === '' || $ip === '' || $port < 1)
		{
			return '';
		}

		// A literal IP is connected to directly, so there is no name to pin
		if (filter_var($host, FILTER_VALIDATE_IP))
		{
			return '';
		}

		return $host . ':' . $port . ':' . self::bracketIp($ip);
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

		// Reject header-injection / control characters
		if (preg_match('~[\x00-\x1F\x7F]~', $relativeUrl))
		{
			return '';
		}

		$base = parse_url($baseUrl);
		$ref = parse_url($relativeUrl);

		if ($base === false || $ref === false || empty($base['scheme']) || empty($base['host']))
		{
			return '';
		}

		$baseScheme = strtolower($base['scheme']);
		if (!in_array($baseScheme, self::DEFAULT_ALLOWED_SCHEMES, true))
		{
			return '';
		}

		// Absolute URI
		if (isset($ref['scheme']))
		{
			return in_array(strtolower($ref['scheme']), self::DEFAULT_ALLOWED_SCHEMES, true) && !empty($ref['host'])
				? $relativeUrl
				: '';
		}

		// Network-path reference: //host/path
		if (isset($ref['host']))
		{
			return self::formatUrl($baseScheme, $ref, $ref['path'] ?? '', $ref['query'] ?? null, $ref['fragment'] ?? null);
		}

		$refPath = $ref['path'] ?? '';
		if ($refPath === '')
		{
			// Query-only and fragment-only references keep the base path
			$path = $base['path'] ?? '/';
			$query = $ref['query'] ?? ($base['query'] ?? null);
		}
		else
		{
			if ($refPath[0] === '/')
			{
				$path = self::removeDotSegments($refPath);
			}
			else
			{
				$basePath = $base['path'] ?? '/';
				$lastSlash = strrpos($basePath, '/');
				$baseDir = $lastSlash === false ? '/' : substr($basePath, 0, $lastSlash + 1);
				$path = self::removeDotSegments($baseDir . $refPath);
			}

			$query = $ref['query'] ?? null;
		}

		if ($path === '')
		{
			$path = '/';
		}

		return self::formatUrl($baseScheme, $base, $path, $query, $ref['fragment'] ?? null);
	}

	/**
	 * Assemble a URL from its components.
	 *
	 * @param string $scheme
	 * @param array $authority Parsed URL providing host, optional port and user info
	 * @param string $path
	 * @param string|null $query
	 * @param string|null $fragment
	 * @return string
	 */
	protected static function formatUrl(string $scheme, array $authority, string $path, ?string $query, ?string $fragment = null): string
	{
		$userInfo = '';
		if (isset($authority['user']))
		{
			$userInfo = $authority['user'] . (isset($authority['pass']) ? ':' . $authority['pass'] : '') . '@';
		}

		$url = $scheme . '://' . $userInfo . $authority['host'];
		if (isset($authority['port']))
		{
			$url .= ':' . $authority['port'];
		}

		$url .= $path;
		if ($query !== null)
		{
			$url .= '?' . $query;
		}

		if ($fragment !== null)
		{
			$url .= '#' . $fragment;
		}

		return $url;
	}

	/**
	 * Remove "." and ".." segments from a URL path (RFC 3986 section 5.2.4).
	 *
	 * @param string $path
	 * @return string
	 */
	protected static function removeDotSegments(string $path): string
	{
		$output = '';

		while ($path !== '')
		{
			if (str_starts_with($path, '../'))
			{
				$path = substr($path, 3);
			}
			elseif (str_starts_with($path, './'))
			{
				$path = substr($path, 2);
			}
			elseif (str_starts_with($path, '/./'))
			{
				$path = '/' . substr($path, 3);
			}
			elseif ($path === '/.')
			{
				$path = '/';
			}
			elseif (str_starts_with($path, '/../'))
			{
				$path = '/' . substr($path, 4);
				$output = self::popLastSegment($output);
			}
			elseif ($path === '/..')
			{
				$path = '/';
				$output = self::popLastSegment($output);
			}
			elseif ($path === '.' || $path === '..')
			{
				$path = '';
			}
			else
			{
				$nextSlash = $path[0] === '/' ? strpos($path, '/', 1) : strpos($path, '/');
				$segment = $nextSlash === false ? $path : substr($path, 0, $nextSlash);
				$output .= $segment;
				$path = substr($path, strlen($segment));
			}
		}

		return $output;
	}

	/**
	 * Remove the last segment from a URL path.
	 *
	 * @param string $output
	 * @return string
	 */
	protected static function popLastSegment(string $output): string
	{
		$lastSlash = strrpos($output, '/');

		return $lastSlash === false ? '' : substr($output, 0, $lastSlash);
	}
}
