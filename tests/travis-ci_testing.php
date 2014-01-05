<?php
global $testName;

$tests = glob(dirname(__FILE__) . '/run_*.php');
$final_return = 0;
foreach ($tests as $test)
{
	$text = file($test);

	eval('global $testName;' . $text[2]);
	echo "\n\n" . 'Running the test: "' . $testName . '"...' . "\n\n";

	echo exec('php ' . $test, $output, $return) . "\n";
	$final_return = max($final_return, $return);
}

exit($final_return);