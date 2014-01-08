<?php
global $testName;

$tests = glob(dirname(__FILE__) . '/run_*.php');
$final_return = 0;
foreach ($tests as $test)
{
	$text = file($test);

	// Grab the test name from a line in the test file.
	eval('global $testName;' . $text[2]);

	// Print out what test is actually running
	echo "\n\n" . 'Running the test: "' . $testName . '"...' . "\n\n";

	// @todo parse the output to create a final summary of the passed and failed tests
	echo exec('php ' . $test, $output, $return) . "\n";
	$final_return = max($final_return, $return);
}

exit($final_return);