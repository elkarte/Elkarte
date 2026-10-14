<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Maillist\MaillistImap;
use tests\ElkArteCommonSetupTest;

/**
 * Exposes the protected helpers for testing
 */
class TestableMaillistImap extends MaillistImap
{
	public function setType(string $type): void
	{
		$this->_type = $type;
	}

	public function setNoValidate(bool $value): void
	{
		$this->_novalidate_cert = $value;
	}

	public function setMax(int $max): void
	{
		$this->_max_per_run = $max;
	}

	public function portType(): array
	{
		return $this->_port_type();
	}

	public function batch(array $emails): array
	{
		return $this->_select_batch($emails);
	}
}

class MaillistImapTest extends ElkArteCommonSetupTest
{
	public function testCertificatesAreValidatedByDefault()
	{
		$imap = new TestableMaillistImap();

		foreach (['pop3', 'pop3tls', 'pop3ssl', 'imap', 'imaptls', 'imapssl', 'bogus'] as $type)
		{
			$imap->setType($type);
			$this->assertStringNotContainsString('novalidate-cert', $imap->portType()['flags'], $type);
		}
	}

	public function testNoValidateIsAnExplicitOptOut()
	{
		$imap = new TestableMaillistImap();
		$imap->setNoValidate(true);

		$imap->setType('imapssl');
		$this->assertSame('/ssl/novalidate-cert', $imap->portType()['flags']);

		$imap->setType('imaptls');
		$this->assertSame('/tls/novalidate-cert', $imap->portType()['flags']);

		$imap->setNoValidate(false);
		$imap->setType('imapssl');
		$this->assertSame('/ssl', $imap->portType()['flags']);
		$this->assertSame(993, $imap->portType()['port']);
	}

	public function testBatchIsSortedAndCapped()
	{
		$imap = new TestableMaillistImap();
		$imap->setMax(3);

		$this->assertSame([2, 5, 9], $imap->batch([9, 40, 2, 5, 30]));
		$this->assertSame([1, 2], $imap->batch([2, 1]));

		// Never less than one message per run
		$imap->setMax(0);
		$this->assertSame([1], $imap->batch([3, 1, 2]));
	}
}
