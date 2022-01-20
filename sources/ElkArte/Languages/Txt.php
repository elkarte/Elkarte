<?php

/**
 * Class related to load multiple languages and keep relation with global variables
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Languages;

use ElkArte\User;

/**
 * This class allows to keep track of the languages loaded and push the values to
 * global variables to maintain a balance between the "old" and the "new".
 */
class Txt
{
	/** @var Loader[] */
	protected static $loader = null;

	public static function load($template, $fatal = true, $fix_calendar_arrays = false)
	{
		global $txt, $language, $modSettings;

		if (self::$loader === null)
		{
			$txt = [];
			$lang = User::$info->language ?? $language;
			self::$loader = new Loader($lang, $txt);
			self::$loader->setFallback(empty($modSettings['disable_language_fallback']));
		}
		if (is_array($template))
		{
			$template = implode('+', $template);
		}
		self::$loader->load($template, $fatal, $fix_calendar_arrays);
	}
}