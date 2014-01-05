<?php

$tests = glob(dirname(__FILE__) . '/run_*.php');
$final_return = 0;
foreach ($tests as $test)
{
	echo "\n-------" . $test . "\n\n";
	echo exec('php ' . $test, $output, $return);
	$final_return = max($final_return, $return);
}

exit($final_return);