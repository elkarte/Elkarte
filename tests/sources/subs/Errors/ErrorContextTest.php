<?php

/**
 * TestCase class for ErrorContext class.
 *
 * Tests adding and removing errors and few other options
 */

use ElkArte\Errors\ErrorContext;
use PHPUnit\Framework\TestCase;

class ErrorContextTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];
	public function testSimpleError()
	{
		$errorContext = ErrorContext::context();

		// Let's add an error and see
		$errorContext->addError('test');
		$this->assertTrue($errorContext->hasErrors());
		$this->assertTrue($errorContext->hasError('test'));
		$this->assertFalse($errorContext->hasError('test2'));
		$this->assertEquals(ErrorContext::MINOR, $errorContext->getErrorType());

		// Now the error can be removed
		$errorContext->removeError('test');
		$this->assertFalse($errorContext->hasErrors());
		$this->assertFalse($errorContext->hasError('test'));
		$this->assertFalse($errorContext->hasError('test2'));
		$this->assertFalse($errorContext->getErrors());
	}
}
