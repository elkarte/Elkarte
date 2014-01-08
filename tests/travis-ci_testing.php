<?php
global $testName;

$tests = glob(dirname(__FILE__) . '/run_*.php');
$final_return = 0;
$global_results = array(
	'tests_run' => array(0, 0),
	'passes' => 0,
	'failures' => 0,
	'exceptions' => 0,
);

foreach ($tests as $test)
{
	$text = file($test);
	$output = array();

	// Grab the test name from a line in the test file.
	eval('global $testName;' . $text[2]);

	// Print out what test is actually running
	echo "\n\n" . 'Running the test: "' . $testName . '"...' . "\n\n";

	// @todo parse the output to create a final summary of the passed and failed tests
	$result = exec('php ' . $test, $output, $return);
	$results = parse_exec_result($result);

	$global_results = array(
		'tests_run' => array(
			0 => $global_results['tests_run'][0] + $results['tests_run'][0],
			1 => $global_results['tests_run'][1] + $results['tests_run'][1],
		),
		'passes' => $global_results['passes'] + $results['passes'],
		'failures' => $global_results['failures'] + $results['failures'],
		'exceptions' => $global_results['exceptions'] + $results['exceptions'],
	);

	echo implode("\n", $output) . "\n\n";

	$final_return = max($final_return, $return, $results['failures'] + $results['exceptions']);
}

echo "\n" . 'Test cases run: ' . $global_results['tests_run'][0] . '/' . $global_results['tests_run'][1] . ', Passes: ' . $global_results['passes'] . ', Failures: ' . $global_results['failures'] . ', Exceptions: ' . $global_results['exceptions'] . "\n";

exit($final_return);

// Schema: Test cases run: 1/1, Passes: 0, Failures: 0, Exceptions: 0
function parse_exec_result($result)
{
	$elem = explode(',', $result);
	$error_array = array(
		'tests_run' => array(
			0 => 1,
			1 => 1,
		),
		'passes' => 0,
		'failures' => 1,
		'exceptions' => 1,
	);

	// Something wrong, so: 1 failure and 1 exception just in case
	if (count($elem) !== 4)
		return $error_array;

	// Getting Test cases run:
	$cases = array(0, 0);
	$string = 'Test cases run: ';
	if (substr($elem[0], 0, strlen($string)) == $string)
	{
		$ratio = substr($elem[0], -(strlen($elem[0]) - (strrpos($elem[0], ' ') + 1)));
		$cases = explode('/', $ratio);
	}
	else
		return $error_array;

	$res = array();
	foreach (array('Passes: ', 'Failures: ', 'Exceptions: ') as $key => $string)
	{
		$i = $key + 1;
		if (substr(trim($elem[$i]), 0, strlen($string)) == $string)
			$res[$i] = substr($elem[$i], -(strlen($elem[$i]) - (strrpos($elem[$i], ' ') + 1)));
		else
			return $error_array;
	}

	return array(
		'tests_run' => $cases,
		'passes' => $res[1],
		'failures' => $res[2],
		'exceptions' => $res[3],
	);
}