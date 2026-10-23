<?php

/**
 * TestCase class for legacy password conversion in the Auth controller.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 */

namespace ElkArte\Controller;

use ElkArte\EventManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AuthPasswordConversionTest extends TestCase
{
	public function testLegacyPasswordConversionWithSaltUsesDocumentedArgumentOrder()
	{
		$posted = 'legacy-password';
		$member = 'LegacyUser';
		$salt = 'a1b2c3d4';
		$stored = md5(md5($posted) . $salt);

		$this->assertContains($stored, $this->legacyPasswordCandidates($posted, $member, $stored, $salt));
	}

	public function testLegacyPasswordConversionWithoutSaltUsesDocumentedArgumentOrder()
	{
		$posted = 'legacy-password';
		$member = 'LegacyUser';
		$stored = md5($posted);

		$this->assertContains($stored, $this->legacyPasswordCandidates($posted, $member, $stored, ''));
	}

	private function legacyPasswordCandidates(string $posted, string $member, string $stored, string $salt): array
	{
		global $modSettings;

		$previous = $modSettings['enable_password_conversion'] ?? null;
		$modSettings['enable_password_conversion'] = 1;

		try
		{
			$controller = new Auth(new EventManager());
			$method = new ReflectionMethod($controller, '_other_passwords');
			$method->setAccessible(true);

			return $method->invoke($controller, $posted, $member, $stored, $salt);
		}
		finally
		{
			if ($previous === null)
			{
				unset($modSettings['enable_password_conversion']);
			}
			else
			{
				$modSettings['enable_password_conversion'] = $previous;
			}
		}
	}
}
