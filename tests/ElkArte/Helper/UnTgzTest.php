<?php

namespace ElkArte\Helper;

use PHPUnit\Framework\TestCase;

class UnTgzTest extends TestCase
{
	/** @var string this is a hex value for a simple tar.gz file */
	public $data = "1f8b0800000000000003edd14d0ac2301086e1597b8a5c40cddf98f354e842902ecc081e5f53bab108052188f03e8b0c4c02f9e0b3b1da51faf22fa5943643c9cbd430ef1712b26a8e4953f2e2438af1244e3be79addab0d37e76498eae57c1df7d3503fbedbbaff53d6fa6fc7c11ed6e98fedfe75d57f2e398bf39df2bca17fdbfd3a0400000000000000000000000080af3c0178c8bb2c00280000";

	/**
	 * Tests _read_header_tgz method in the UnTgz class.
	 *
	 * For now a NULL test is provided as the function does not return a value
	 */
	public function testReadHeaderTgz()
	{
		$unTgz = new UnTgz(hex2bin($this->data), '', false, false, NULL);

		$this->assertTrue($unTgz->check_valid_tgz());

		$method = new \ReflectionMethod(UnTgz::class, '_read_header_tgz');
		$method->setAccessible(true);

		$this->assertNull($method->invoke($unTgz));
	}

	/**
	 * Test the check_valid_tgz method
	 *
	 * This method tests the check_valid_tgz method of the UnTgz class.
	 *
	 * @return void
	 */
	public function testCheckValidTgz()
	{
		$destination = './';  // Target destination
		$single_file = true;  // single file
		$overwrite = true;    // overwrite existing files

		// Act
		$unTgz = new UnTgz(hex2bin($this->data), $destination, $single_file, $overwrite);
		$result = $unTgz->check_valid_tgz();

		// Assert
		$this->assertTrue($result, 'String data is a valid .tgz file');
	}

	/**
	 * Test for '_ungzip_data' method
	 *
	 * This method is responsible for ungziping the data from a .tgz file
	 */
	public function testUngzipData(): void
	{
		$unTgz = new UnTgz(hex2bin($this->data), 'destination', false, false, null);

		// Create a reflection of '_ungzip_data' method
		$reflection = new \ReflectionClass($unTgz);
		$method = $reflection->getMethod('_read_header_tgz');
		$method->setAccessible(true);
		$method2 = $reflection->getMethod('_ungzip_data');
		$method2->setAccessible(true);

		$unTgz->check_valid_tgz();
		$method->invoke($unTgz);

		// Call '_ungzip_data' method and test the expected behavior
		$this->assertNotFalse($method2->invoke($unTgz));
	}

	/**
	 * Test for 'read_tgz_data' method
	 *
	 * This method is responsible for reading the data from a .tgz file
	 */
	public function testReadTgzData()
	{
		$unTgz = new UnTgz(hex2bin($this->data), '/packages', false, false, NULL);

		$unTgz->check_valid_tgz();

		$result = $unTgz->read_tgz_data();

		$md5 = $result[0]['md5'];

		$this->assertEquals('d8e8fca2dc0f896fd7cb4cb0031ba249', $md5, 'The md5 is valid');
	}
}
