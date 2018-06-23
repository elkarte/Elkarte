<?php if (!defined('BB2_CORE')) die("I said no cheating!");

# Round trip DNS verification

# Returns TRUE if DNS matches; FALSE on mismatch
# Returns $ip if an error occurs
# TODO: Not IPv6 safe
# FIXME: Returns false on DNS server failure; PHP provides no distinction
# between no records and error condition
function bb2_roundtripdns($ip,$domain)
{
	if (@is_ipv6($ip)) return $ip;

	$host = gethostbyaddr($ip);
	$host_result = strpos(strrev($host), strrev($domain));
	if ($host_result === false || $host_result > 0) return false;
	$addrs = gethostbynamel($host);
	if (in_array($ip, $addrs)) return true;
	return false;
}
