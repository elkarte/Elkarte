<?php if (!defined('BB2_CORE')) die('I said no cheating!');

// Functions called when a request has been denied
// This part can be gawd-awful slow, doesn't matter :)

require_once(BB2_CORE . "/responses.inc.php");

function bb2_housekeeping($settings, $package)
{
	if (!$settings['logging']) return;

	// FIXME Yes, the interval's hard coded (again) for now.
	$query = "DELETE FROM `" . $settings['log_table'] . "` WHERE `date` < DATE_SUB('" . bb2_db_date() . "', INTERVAL 7 DAY)";
	bb2_db_query($query);

	// Waste a bunch more of the spammer's time, sometimes.
	if (rand(1,1000) == 1) {
		$query = "OPTIMIZE TABLE `" . $settings['log_table'] . "`";
		bb2_db_query($query);
	}
}

function bb2_display_denial($settings, $package, $key, $previous_key = false)
{
	define('DONOTCACHEPAGE', true);	// WP Super Cache
	if (!$previous_key) $previous_key = $key;
	if ($key == "e87553e1") {
		// FIXME: lookup the real key
	}
	// Create support key
	$ip = explode(".", $package['ip']);
	$ip_hex = "";
	foreach ($ip as $octet) {
		$ip_hex .= str_pad(dechex($octet), 2, 0, STR_PAD_LEFT);
	}
	$support_key = implode("-", str_split("$ip_hex$key", 4));

	// Get response data
	$response = bb2_get_response($previous_key);
	header("HTTP/1.1 " . $response['response'] . " Bad Behavior");
	header("Status: " . $response['response'] . " Bad Behavior");
	$request_uri = $_SERVER["REQUEST_URI"];
	if (!$request_uri) $request_uri = $_SERVER['SCRIPT_NAME'];	# IIS
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!--< html xmlns="http://www.w3.org/1999/xhtml">-->
<head>
<title>HTTP Error <?php echo $response['response']; ?></title>
</head>
<body>
<h1>Error <?php echo $response['response']; ?></h1>
<p>We're sorry, but we could not fulfill your request for
<?php echo htmlspecialchars($request_uri) ?> on this server.</p>
<p><?php echo $response['explanation']; ?></p>
<p>Your technical support key is: <strong><?php echo $support_key; ?></strong></p>
<p>You can use this key to <a href="http://www.ioerror.us/bb2-support-key?key=<?php echo $support_key; ?>">fix this problem yourself</a>.</p>
<p>If you are unable to fix the problem yourself, please contact <a href="mailto:<?php echo htmlspecialchars(str_replace("@", "+nospam@nospam.", bb2_email())); ?>"><?php echo htmlspecialchars(str_replace("@", " at ", bb2_email())); ?></a> and be sure to provide the technical support key shown above.</p>
<?php
}

function bb2_log_denial($settings, $package, $key, $previous_key=false)
{
	if (!$settings['logging']) return;
	bb2_db_query(bb2_insert($settings, $package, $key));
}
