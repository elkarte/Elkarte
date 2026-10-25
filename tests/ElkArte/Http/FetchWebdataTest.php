<?php

namespace ElkArte\Http;

use PHPUnit\Framework\TestCase;

class FetchWebdataTest extends TestCase
{
	/**
	 * Test that FsockFetchWebdata rejects SSRF target URLs.
	 */
	public function testFsockFetchWebdataBlocksSsrf(): void
	{
		$targets = [
			'http://127.0.0.1/admin',
			'http://127.0.0.1:6379/',
			'http://10.0.0.1/',
			'http://192.168.1.1/',
			'http://172.16.0.1/',
			'http://169.254.169.254/latest/meta-data/',
			'http://localhost/test',
			'gopher://127.0.0.1:6379/_',
			'file:///etc/passwd',
		];

		foreach ($targets as $url)
		{
			$fetcher = new FsockFetchWebdata();
			$fetcher->get_url_data($url);

			$this->assertSame(
				400,
				(int) $fetcher->result('code'),
				"Expected FsockFetchWebdata to block {$url}"
			);
			$this->assertNotEmpty(
				$fetcher->result('error'),
				"Expected FsockFetchWebdata to return an error for {$url}"
			);
		}
	}

	/**
	 * Test that CurlFetchWebdata rejects SSRF target URLs.
	 */
	public function testCurlFetchWebdataBlocksSsrf(): void
	{
		$targets = [
			'http://127.0.0.1/admin',
			'http://127.0.0.1:6379/',
			'http://10.0.0.1/',
			'http://192.168.1.1/',
			'http://172.16.0.1/',
			'http://169.254.169.254/latest/meta-data/',
			'http://localhost/test',
			'gopher://127.0.0.1:6379/_',
			'file:///etc/passwd',
		];

		foreach ($targets as $url)
		{
			$fetcher = new CurlFetchWebdata();
			$fetcher->get_url_data($url);

			$this->assertSame(
				400,
				(int) $fetcher->result('code'),
				"Expected CurlFetchWebdata to block {$url}"
			);
			$this->assertNotEmpty(
				$fetcher->result('error'),
				"Expected CurlFetchWebdata to return an error for {$url}"
			);
		}
	}

	/**
	 * Test that StreamFetchWebdata rejects SSRF target URLs.
	 */
	public function testStreamFetchWebdataBlocksSsrf(): void
	{
		$targets = [
			'http://127.0.0.1/admin',
			'http://127.0.0.1:6379/',
			'http://10.0.0.1/',
			'http://192.168.1.1/',
			'http://172.16.0.1/',
			'http://169.254.169.254/latest/meta-data/',
			'http://localhost/test',
			'gopher://127.0.0.1:6379/_',
			'file:///etc/passwd',
		];

		foreach ($targets as $url)
		{
			$fetcher = new StreamFetchWebdata();
			$fetcher->get_url_data($url);

			$this->assertSame(
				400,
				(int) $fetcher->result('code'),
				"Expected StreamFetchWebdata to block {$url}"
			);
			$this->assertNotEmpty(
				$fetcher->result('error'),
				"Expected StreamFetchWebdata to return an error for {$url}"
			);
		}
	}
}
