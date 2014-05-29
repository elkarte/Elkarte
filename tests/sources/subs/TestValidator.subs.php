<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
// require_once(SUBSDIR . '/DataValidator.class.php');

class TestDataValidator extends UnitTestCase
{
	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		$this->rules = array(
			'required'      => 'required',
			'max_length'    => 'max_length[1]',
			'min_length'    => 'min_length[4]',
			'length'        => 'length[10]',
			'alpha'         => 'alpha',
			'alpha_numeric' => 'alpha_numeric',
			'alpha_dash'    => 'alpha_dash',
			'numeric'       => 'numeric',
			'integer'       => 'integer',
			'boolean'       => 'boolean',
			'float'         => 'float',
			'notequal'      => 'notequal[abc]',
			'valid_url'     => 'valid_url',
			'valid_ip'      => 'valid_ip',
			'valid_ipv6'    => 'valid_ipv6',
			'valid_email'   => 'valid_email',
			'contains'      => 'contains[elk,art]',
			'without'       => 'without[1,2,3]',
			'min_len_csv'   => 'min_length[4]',
			'min_len_array' => 'min_length[4]',
			'limits'        => 'limits[0,10]',
		);

		$this->invalid_data = array(
			'required'      => '',
			'max_length'    => "1234567890",
			'min_length'    => "123",
			'length'        => "123456",
			'alpha'         => "abc*def%",
			'alpha_numeric' => "abcde12345+",
			'alpha_dash'    => "abcdefg12345-_+",
			'numeric'       => "one, two",
			'integer'       => "1,003",
			'boolean'       => "not a boolean",
			'float'         => "not a float",
			'notequal'      => 'abc',
			'valid_url'     => "\r\n\r\nhttp://add",
			'valid_ip'      => "google.com",
			'valid_ipv6'    => "google.com",
			'valid_email'   => '*&((*S))(*09890uiadaiusyd)',
			'contains'      => 'premium',
			'without'       => '1 way 2 do this',
			'min_len_csv'   => '1234,12345, 123',
			'min_len_array' => array('1234', '12345', '123'),
			'limits'        => 11,
		);

		$this->valid_data = array(
			'required'      => ':D',
			'max_length'    => '1',
			'min_length'    => '12345',
			'length'        => '1234567890',
			'alpha'         => 'ÈÉÊËÌÍÎÏÒÓÔasdasdasd',
			'alpha_numeric' => 'abcdefg12345-',
			'alpha_dash'    => 'abcdefg-_',
			'numeric'       => 2.00,
			'integer'       => 3,
			'boolean'       => false,
			'float'         => 10.10,
			'notequal'      => 'xyz',
			'valid_url'     => 'http://www.elkarte.net',
			'valid_ip'      => '69.163.138.62',
			'valid_ipv6'    => "2001:0db8:85a3:08d3:1319:8a2e:0370:7334",
			'valid_email'   => 'timelord@gallifrey.com',
			'contains'      => 'elk',
			'without'       => 'this does not have one or two',
			'min_len_csv'   => '1234,12345,123456',
			'min_len_array' => array('1234', '12345', '123456'),
			'limits'        => 9,
		);
	}

	/**
	 * Run some validation tests, rules vs valid and invalid data
	 */
	public function testValidation()
	{
		// These should all fail
		$validation = new Data_Validator();
		$validation->validation_rules($this->rules);
		$validation->sanitation_rules(array('min_len_csv' => 'trim'));
		$validation->input_processing(array('min_len_csv' => 'csv', 'min_len_array' => 'array'));
		$validation->validate($this->invalid_data);

		foreach ($this->invalid_data as $key => $value)
		{
			$test = $validation->validation_errors($key);
			$value = is_array($value) ? implode(' | ', $value) : $value;
			$this->assertNotNull($validation->validation_errors($key), 'Test: ' . $test[0] . ' passed data: ' . $value . ' but it should have failed');
		}

		// These should all pass
		$validation = new Data_Validator();
		$validation->validation_rules($this->rules);
		$validation->input_processing(array('min_len_csv' => 'csv', 'min_len_array' => 'array'));
		$validation->validate($this->valid_data);

		foreach ($this->valid_data as $key => $value)
		{
			$test = $validation->validation_errors($key);
			$value = is_array($value) ? implode(' | ', $value) : $value;
			$this->assertNull($validation->validation_errors($key), 'Test: ' . $test[0] . ' failed data: ' . $value . ' but it should have passed');
		}
	}
}