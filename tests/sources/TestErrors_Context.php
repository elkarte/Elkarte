<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
// require_once(SOURCEDIR . '/ErrorContext.class.php');

/**
 * TestCase class for Error_Context class.
 * Tests adding and removing errors and few other options
 */
class TestError_Context extends UnitTestCase
{
	function testSimpleError()
	{
		$error_context = Error_Context::context();

		// Let's add an error and see
		$error_context->addError('test');
		$this->assertTrue($error_context->hasErrors());
		$this->assertTrue($error_context->hasError('test'));
		$this->assertFalse($error_context->hasError('test2'));
		$this->assertEqual($error_context->getErrorType(), Error_Context::MINOR);

		// Now the error can be removed
		$error_context->removeError('test');
		$this->assertFalse($error_context->hasErrors());
		$this->assertFalse($error_context->hasError('test'));
		$this->assertFalse($error_context->hasError('test2'));
		$this->assertFalse($error_context->getErrors());
	}
}