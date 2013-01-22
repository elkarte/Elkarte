<?php if (!defined('BB2_CORE')) die('I said no cheating!');

// Defines the responses which Bad Behavior might return.

function bb2_get_response($key) {
	$bb2_responses = array(
		'00000000' => array('response' => 200, 'explanation' => '', 'log' => 'Permitted'),
		'136673cd' => array('response' => 403, 'explanation' => 'Your Internet Protocol address is listed on a blacklist of addresses involved in malicious or illegal activity. See the listing below for more details on specific blacklists and removal procedures.', 'log' => 'IP address found on external blacklist'),
		'17566707' => array('response' => 403, 'explanation' => 'An invalid request was received from your browser. This may be caused by a malfunctioning proxy server or browser privacy software.', 'log' => 'Required header \'Accept\' missing'),
		'17f4e8c8' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'User-Agent was found on blacklist'),
		'21f11d3f' => array('response' => 403, 'explanation' => 'An invalid request was received. You claimed to be a mobile Web device, but you do not actually appear to be a mobile Web device.', 'log' => 'User-Agent claimed to be AvantGo, claim appears false'),
		'2b021b1f' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, run anti-virus and anti-spyware software and remove any viruses and spyware from your computer.', 'log' => 'IP address found on http:BL blacklist'),
		'2b90f772' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. If you are using the Opera browser, then Opera must appear in your user agent.', 'log' => 'Connection: TE present, not supported by MSIE'),
		'35ea7ffa' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Check your browser\'s language and locale settings.', 'log' => 'Invalid language specified'),
		'408d7e72' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, run anti-virus and anti-spyware software and remove any viruses and spyware from your computer.', 'log' => 'POST comes too quickly after GET'),
		'41feed15' => array('response' => 400, 'explanation' => 'An invalid request was received. This may be caused by a malfunctioning proxy server. Bypass the proxy server and connect directly, or contact your proxy server administrator.', 'log' => 'Header \'Pragma\' without \'Cache-Control\' prohibited for HTTP/1.1 requests'),
		'45b35e30' => array('response' => 400, 'explanation' => 'An invalid request was received from your browser. This may be caused by a malfunctioning proxy server or browser privacy software.', 'log' => 'Header \'Referer\' is corrupt'),
		'57796684' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, run anti-virus and anti-spyware software and remove any viruses and spyware from your computer.', 'log' => 'Prohibited header \'X-Aaaaaaaaaa\' or \'X-Aaaaaaaaaaaa\' present'),
		'582ec5e4' => array('response' => 400, 'explanation' => 'An invalid request was received. If you are using a proxy server, bypass the proxy server or contact your proxy server administrator. This may also be caused by a bug in the Opera web browser.', 'log' => '"Header \'TE\' present but TE not specified in \'Connection\' header'),
		'69920ee5' => array('response' => 400, 'explanation' => 'An invalid request was received from your browser. This may be caused by a malfunctioning proxy server or browser privacy software.', 'log' => 'Header \'Referer\' present but blank'),
		'6c502ff1' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'Bot not fully compliant with RFC 2965'),
		'70e45496' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'User agent claimed to be CloudFlare, claim appears false'),
		'71436a15' => array('response' => 403, 'explanation' => 'An invalid request was received. You claimed to be a major search engine, but you do not appear to actually be a major search engine.', 'log' => 'User-Agent claimed to be Yahoo, claim appears to be false'),
		'799165c2' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'Rotating user-agents detected'),
		'7a06532b' => array('response' => 400, 'explanation' => 'An invalid request was received from your browser. This may be caused by a malfunctioning proxy server or browser privacy software.', 'log' => 'Required header \'Accept-Encoding\' missing'),
		'7ad04a8a' => array('response' => 400, 'explanation' => 'The automated program you are using is not permitted to access this server. Please use a different program or a standard Web browser.', 'log' => 'Prohibited header \'Range\' present'),
		'7d12528e' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'Prohibited header \'Range\' or \'Content-Range\' in POST request'),
		'939a6fbb' => array('response' => 403, 'explanation' => 'The proxy server you are using is not permitted to access this server. Please bypass the proxy server, or contact your proxy server administrator.', 'log' => 'Banned proxy server in use'),
		'96c0bd29' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'URL pattern found on blacklist'),
		'9c9e4979' => array('response' => 403, 'explanation' => 'The proxy server you are using is not permitted to access this server. Please bypass the proxy server, or contact your proxy server administrator.', 'log' => 'Prohibited header \'via\' present'),
		'a0105122' => array('response' => 417, 'explanation' => 'Expectation failed. Please retry your request.', 'log' => 'Header \'Expect\' prohibited; resend without Expect'),
		'a1084bad' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'User-Agent claimed to be MSIE, with invalid Windows version'),
		'a52f0448' => array('response' => 400, 'explanation' => 'An invalid request was received.  This may be caused by a malfunctioning proxy server or browser privacy software. If you are using a proxy server, bypass the proxy server or contact your proxy server administrator.', 'log' => 'Header \'Connection\' contains invalid values'),
		'b0924802' => array('response' => 400, 'explanation' => 'An invalid request was received. This may be caused by malicious software on your computer.', 'log' => 'Incorrect form of HTTP/1.0 Keep-Alive'),
		'b40c8ddc' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, close your browser, run anti-virus and anti-spyware software and remove any viruses and spyware from your computer.', 'log' => 'POST more than two days after GET'),
		'b7830251' => array('response' => 400, 'explanation' => 'Your proxy server sent an invalid request. Please contact the proxy server administrator to have this problem fixed.', 'log' => 'Prohibited header \'Proxy-Connection\' present'),
		'b9cc1d86' => array('response' => 403, 'explanation' => 'The proxy server you are using is not permitted to access this server. Please bypass the proxy server, or contact your proxy server administrator.', 'log' => 'Prohibited header \'X-Aaaaaaaaaa\' or \'X-Aaaaaaaaaaaa\' present'),
		'c1fa729b' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, run anti-virus and anti-spyware software and remove any viruses and spyware from your computer.', 'log' => 'Use of rotating proxy servers detected'),
		'cd361abb' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Data may not be posted from offsite forms.', 'log' => 'Referer did not point to a form on this site'),
		'd60b87c7' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, please remove any viruses or spyware from your computer.', 'log' => 'Trackback received via proxy server'),
		'dfd9b1ad' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'Request contained a malicious JavaScript or SQL injection attack'),
		'e3990b47' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, please remove any viruses or spyware from your computer.', 'log' => 'Obviously fake trackback received'),
		'e4de0453' => array('response' => 403, 'explanation' => 'An invalid request was received. You claimed to be a major search engine, but you do not appear to actually be a major search engine.', 'log' => 'User-Agent claimed to be msnbot, claim appears to be false'),
		'e87553e1' => array('response' => 403, 'explanation' => 'You do not have permission to access this server.', 'log' => 'I know you and I don\'t like you, dirty spammer.'),
		'f0dcb3fd' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. Before trying again, run anti-virus and anti-spyware software and remove any viruses and spyware from your computer.', 'log' => 'Web browser attempted to send a trackback'),
		'f1182195' => array('response' => 403, 'explanation' => 'An invalid request was received. You claimed to be a major search engine, but you do not appear to actually be a major search engine.', 'log' => 'User-Agent claimed to be Googlebot, claim appears to be false.'),
		'f9f2b8b9' => array('response' => 403, 'explanation' => 'You do not have permission to access this server. This may be caused by a malfunctioning proxy server or browser privacy software.', 'log' => 'A User-Agent is required but none was provided.'),
	);

	if (array_key_exists($key, $bb2_responses)) return $bb2_responses[$key];
	return array('00000000');
}
