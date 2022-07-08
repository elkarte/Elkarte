<?php

use ElkArte\DataValidator;
use ElkArte\Languages\Loader;

class TestDataValidator extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		global $txt;
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Validation');

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
			'valid_color'   => 'valid_color',
			'php_syntax'    => 'php_syntax'
		);

		$this->invalid_data = array(
			'required'      => '',
			'max_length'    => '1234567890',
			'min_length'    => '123',
			'length'        => '123456',
			'alpha'         => 'abc*def%',
			'alpha_numeric' => 'abcde12345+',
			'alpha_dash'    => 'abcdefg12345-_+',
			'numeric'       => 'one, two',
			'integer'       => '1,003',
			'boolean'       => 'not a boolean',
			'float'         => 'not a float',
			'notequal'      => 'abc',
			'valid_url'     => "\r\n\r\nhttp://add",
			'valid_ip'      => 'google.com',
			'valid_ipv6'    => 'google.com',
			'valid_email'   => '*&((*S))(*09890uiadaiusyd)',
			'contains'      => 'premium',
			'without'       => '1 way 2 do this',
			'min_len_csv'   => '1234,12345, 123',
			'min_len_array' => array('1234', '12345', '123'),
			'limits'        => 11,
			'valid_color'   => '#fffgff',
			'php_syntax'    => 'if ($a == 1) {$b = true'
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
			'boolean'       => true,
			'float'         => 10.10,
			'notequal'      => 'xyz',
			'valid_url'     => 'http://www.elkarte.net',
			'valid_ip'      => '69.163.138.62',
			'valid_ipv6'    => '2001:0db8:85a3:08d3:1319:8a2e:0370:7334',
			'valid_email'   => 'timelord@gallifrey.com',
			'contains'      => 'elk',
			'without'       => 'this does not have one or two',
			'min_len_csv'   => '1234,12345,123456',
			'min_len_array' => array('1234', '12345', '123456'),
			'limits'        => 9,
			'valid_color'   => '#ffffff',
			'php_syntax'    => 'if ($a == 1) {$b = true;}'
		);
	}

	/**
	 * Run some validation tests, rules vs valid and invalid data
	 */
	public function testValidation()
	{
		// These should all fail
		$validation = new DataValidator();
		$validation->validation_rules($this->rules);
		$validation->sanitation_rules(array('min_len_csv' => 'trim'));
		$validation->input_processing(array('min_len_csv' => 'csv', 'min_len_array' => 'array'));
		$validation->validate($this->invalid_data);

		foreach ($this->invalid_data as $key => $value)
		{
			$test = $validation->validation_errors($key);
			$test[0] = isset($test[0]) ? $test[0] : $key;
			$value = is_array($value) ? implode(' | ', $value) : $value;
			$this->assertNotFalse($validation->validation_errors($key), 'Test: ' . $test[0] . ' passed data: ' . $value . ' but it should have failed');
		}

		// These should all pass
		$validation = new DataValidator();
		$validation->validation_rules($this->rules);
		$validation->input_processing(array('min_len_csv' => 'csv', 'min_len_array' => 'array'));
		$validation->validate($this->valid_data);

		foreach ($this->valid_data as $key => $value)
		{
			$test = $validation->validation_errors($key);
			$test = $test === false ? $key : $test[0];
			$value = is_array($value) ? implode(' | ', $value) : $value;
			$this->assertFalse($validation->validation_errors($key), 'Test: ' . $test . ' failed data: ' . $value . ' but it should have passed');
		}
	}

	public function testCsv()
	{
		$validation = new DataValidator();
		$validation->validation_rules(array('csv' => 'limits[0,10]|without[1,2,3]'));
		$validation->input_processing(array('csv' => 'csv'));
		$validation->validate(array('csv' => '10,12,36,49,5'));
		$result = $validation->validation_errors(true);

		$this->assertCount(6, $result);
		$this->assertSame('10', $result[0]['input']);
		$this->assertSame('_validate_without', $result[0]['function']);
		$this->assertSame('12', $result[1]['input']);
		$this->assertSame('_validate_limits', $result[1]['function']);
		$this->assertSame('12', $result[2]['input']);
		$this->assertSame('_validate_without', $result[2]['function']);
		$this->assertSame('36', $result[3]['input']);
		$this->assertSame('_validate_limits', $result[3]['function']);
		$this->assertSame('36', $result[4]['input']);
		$this->assertSame('_validate_without', $result[4]['function']);
		$this->assertSame('49', $result[5]['input']);
		$this->assertSame('_validate_limits', $result[5]['function']);
	}

	/**
	 * @dataProvider isValidBooleanProvider
	 */
	public function testIsValidBoolean($value, $expected)
	{
		$data = array('value' => $value);
		$result = DataValidator::is_valid($data, array('value' => 'boolean'));
		$this->assertSame($expected !== null, $result);
	}

	public function isValidBooleanProvider()
	{
		/*
		 * Try to mimic the output of filter_var() where it returns null
		 * when casting to boolean fails.
		 */
		return array(
			array('foo', null),
			array(false, false),
			array('baz', null),
			array(array(1,2), null),
			array(array(1), null),
			array(array(0), null),
			array(42, null),
			array(-42, null),
			array(true, true),
			array('true', true),
			array('on', true),
			array('off', false),
			array('yes', true),
			array('1', true),
			array('no', false),
			array('ja', null),
			array('nein', null),
			array(null, false),
			array(0, false),
			array('false', false),
			array('string', null),
			array('0.0', null),
			array('4.2', null),
			array('0', false),
			array('', false),
			array(array(), null),

			/*
			 * Objects (even empty ones) should not be able to evaluate to
			 * a boolean without __tostring() defined. This was fixed in PHP 7.
			 */
			array(new stdClass, null),
		);
	}

	/**
	 * @dataProvider isValidFloatProvider
	 */
	public function testIsValidFloat($value, $expected)
	{
		$data = array('value' => $value);
		$result = DataValidator::is_valid($data, array('value' => 'float'));
		$this->assertSame($expected, $result);
	}

	public function isValidFloatProvider()
	{
		/*
		 * Several of these test cases seem silly, but we should document
		 * all of these anyway so people know what to expect.
		 */
		return array(
			array('foo', false),
			array(false, false),
			array('baz', false),
			array(array(1,2), false),
			array(array(1), false),
			array(array(0), false),
			array(42, true),
			array(-42, true),
			array(true, true),
			array('1', true),
			array(null, true),
			array(0, true),
			array('0.0', true),
			array(4.2, true),
			array('4.2', true),
			array('0', true),
			array('+0', true),
			array('-0', true),
			array('', false),
			array(array(), false),
		);
	}

	/**
	 * @dataProvider isValidIntegerProvider
	 */
	public function testIsValidInteger($value, $expected)
	{
		$data = array('value' => $value);
		$result = DataValidator::is_valid($data, array('value' => 'integer'));
		$this->assertSame($expected, $result);
	}

	public function isValidIntegerProvider()
	{
		/*
		 * Some real head scratchers here.
		 */
		return array(
			array('foo', false),
			array(false, false),
			array('baz', false),
			array(array(1,2), false),
			array(array(1), false),
			array(array(0), false),
			array(42, true),
			array(-42, true),
			array(true, true),
			array('1', true),
			array(null, true),
			array(0, true),
			array('0.0', false),
			array(4.2, false),
			array('4.2', false),
			array('0', true),
			array('+0', true),
			array('-0', true),
			array('', false),
			array(array(), false),
		);
	}
}
