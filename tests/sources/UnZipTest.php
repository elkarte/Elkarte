<?php

use ElkArte\Helper\UnZip;
use PHPUnit\Framework\TestCase;

class UnZipTest extends TestCase
{
	/** @var string this is a hex value for a simple zip file */
	public $data = "504b0304140000000000c96d385800000000000000000000000005000000746573742f504b03041400000000000cad37580000000000000000000000000a000000746573742f746573742f504b03040a00000000000cad3758c635b93b050000000500000012000000746573742f746573742f746573742e747874746573740a504b01023f00140000000000c96d3858000000000000000000000000050024000000000000001000000000000000746573742f0a0020000000000001001800f734fcfefd4eda0100000000000000000000000000000000504b01023f001400000000000cad37580000000000000000000000000a0024000000000000001000000023000000746573742f746573742f0a002000000000000100180080ad8f0f774eda0100000000000000000000000000000000504b01023f000a00000000000cad3758c635b93b050000000500000012002400000000000000200000004b000000746573742f746573742f746573742e7478740a002000000000000100180080ad8f0f774eda0100000000000000000000000000000000504b0506000000000300030017010000800000000000";

	/**
	 * Test check_valid_zip method
	 */
	public function testCheckValidZip()
	{
		$unZip = new UnZip(hex2bin($this->data), "/packages", false, false, null);

		$this->assertTrue($unZip->check_valid_zip());
	}

	/**
	 * Test the method _load_file_headers from the UnZip class
	 */
	public function testLoadFileHeaders()
	{
		// Instantiate the UnZip object with our test data
		$unZip = new UnZip(hex2bin($this->data), "/packages", false, false, null);

		// Start reflection on the class to access the method _load_file_headers
		$reflectionClass = new \ReflectionClass($unZip);

		// Obtain the protected methods _load_file_headers &  _read_endof_cdr from the class
		$method = $reflectionClass->getMethod('_load_file_headers');
		$method->setAccessible(true);
		$method2 = $reflectionClass->getMethod('_read_endof_cdr');
		$method2->setAccessible(true);

		// Call _read_endof_cdr method, here just to avoid an undefined error
		$method2->invoke($unZip);

		// Call the _load_file_headers method from our UnZip object
		$returnValue = $method->invoke($unZip);

		// Assert that it does false as we have not properly setup the call
		$this->assertFalse($returnValue);
	}

	/**
	 * Test for 'read_zip_data' method
	 *
	 * This method is responsible for reading the data from a .zip file
	 */
	public function testReadTgzData()
	{
		$unZip = new UnZip(hex2bin($this->data), '/packages', false, false, NULL);

		$unZip->check_valid_zip();

		$result = $unZip->read_zip_data();

		$md5 = $result[0]['md5'];

		$this->assertEquals('d8e8fca2dc0f896fd7cb4cb0031ba249', $md5, 'The md5 is valid');
	}
}