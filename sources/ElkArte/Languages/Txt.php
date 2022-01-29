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
 * This class allows the tracking of the languages loaded and push the values to
 * global variables to maintain a balance between the "old" and the "new".
 */
class Txt
{
	/** @var Loader[] */
	protected static $loader = null;

	/**
	 * Loads the language lexicon file(s) in the proper language
	 *
	 * @param string $lexicon File(s) to load
	 * @param boolean $fatal
	 * @param boolean $fix_calendar_arrays
	 */
	public static function load($lexicon, $fatal = true, $fix_calendar_arrays = false)
	{
		global $txt, $language, $modSettings;

		if (self::$loader === null)
		{
			$txt = [];
			$lang = User::$info->language ?? $language;
			self::$loader = new Loader($lang, $txt, database());
			self::$loader->setFallback(empty($modSettings['disable_language_fallback']));
		}

		if (is_array($lexicon))
		{
			$lexicon = implode('+', $lexicon);
		}

		self::$loader->load($lexicon, $fatal, $fix_calendar_arrays);
	}
}