<?php

/**
 * Just show the spellchecker.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:        BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.7
 *
 */

/**
 * Spellcheck_Controller Class
 *
 * Handles the initialization pspell and spellchecker processing
 */
class Spellcheck_Controller extends Action_Controller
{
	/**
	 * Known words that pspell will not know
	 *
	 * @var array
	 */
	public $known_words = array('elkarte', 'php', 'mysql', 'www', 'gif', 'jpeg', 'png', 'http');

	/**
	 * The object that holds our initialized pspell
	 *
	 * @var object
	 */
	public $pspell_link;

	/**
	 * The template layers object
	 *
	 * @var null|object
	 */
	protected $_template_layers = null;

	/**
	 * List of words that will be spell checked, word|offset_begin|offset_end
	 * @var string[]
	 */
	private $_alphas;

	/**
	 * Spell checks the post for typos ;).
	 *
	 * What it does:
	 *
	 * - It uses the pspell library, which MUST be installed.
	 * - It has problems with internationalization.
	 * - It is accessed via ?action=spellcheck.
	 * - Triggers the prepare_spellcheck event
	 */
	public function action_index()
	{
		global $txt, $context;

		// A list of "words" we know about but pspell doesn't.
		$this->_events->trigger('prepare_spellcheck', array('$this->known_words' => &$this->known_words));

		loadLanguage('Post');
		loadTemplate('Post');

		// Okay, this looks funny, but it actually fixes a weird bug.
		ob_start();
		$old = error_reporting(0);

		// Next, the dictionary in question may not exist. So, we try it... but...
		$this->pspell_link = pspell_new($txt['lang_locale'], $txt['lang_spelling'], null, 'utf-8', PSPELL_FAST);
		if (!$this->pspell_link)
		{
			$this->pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], null, 'utf-8', PSPELL_FAST);
		}

		// Most people don't have anything but English installed... So we use English as a last resort.
		if (!$this->pspell_link)
		{
			$this->pspell_link = pspell_new('en');
		}

		// Reset error reporting to what it was
		error_reporting($old);
		@ob_end_clean();

		// Nothing to check or nothing to check with
		if (!isset($this->_req->post->spellstring) || !$this->pspell_link)
		{
			die ('pspell initializing failure');
		}

		// Get all the words (Javascript already separated them).
		$this->_alphas = explode("\n", strtr($this->_req->post->spellstring, array("\r" => '')));

		// Construct a bit of Javascript code.
		$context['spell_js'] = '
			var txt = {"done": "' . $txt['spellcheck_done'] . '"},
				mispstr = ' . ($this->_req->post->fulleditor === 'true' ? 'window.opener.spellCheckGetText(spell_fieldname)' : 'window.opener.document.forms[spell_formname][spell_fieldname].value') . ',
				misps = ' . $this->_build_misps_array() . '
			);';

		// And instruct the template system to just show the spellcheck sub template.
		$this->_template_layers = Template_Layers::instance();
		$this->_template_layers->removeAll();
		$context['sub_template'] = 'spellcheck';
	}

	/**
	 * Builds the mis spelled words array for use in JS
	 *
	 * What it does:
	 *
	 * - Examines all words passed to it, checking spelling of each
	 * - Incorrect ones are supplied an array of possible substitutions
	 *
	 * @return string
	 */
	protected function _build_misps_array()
	{
		$array = 'Array(';

		$found_words = false;
		foreach ($this->_alphas as $alpha)
		{
			// Words are sent like 'word|offset_begin|offset_end'.
			$check_word = explode('|', $alpha);

			// If the word is a known word, or spelled right...
			if (in_array(Util::strtolower($check_word[0]), $this->known_words) || pspell_check($this->pspell_link, $check_word[0]) || !isset($check_word[2]))
			{
				continue;
			}

			// Find the word, and move up the "last occurrence" to here.
			$found_words = true;

			// Add on the javascript for this misspelling.
			$array .= '
				new misp("' . strtr($check_word[0], array('\\' => '\\\\', '"' => '\\"', '<' => '', '&gt;' => '')) . '", ' . (int) $check_word[1] . ', ' . (int) $check_word[2] . ', [';

			// If there are suggestions, add them in...
			$suggestions = pspell_suggest($this->pspell_link, $check_word[0]);
			if (!empty($suggestions))
			{
				// But first check they aren't going to be censored - no naughty words!
				foreach ($suggestions as $k => $word)
				{
					if ($suggestions[$k] != censor($word))
					{
						unset($suggestions[$k]);
					}
				}

				if (!empty($suggestions))
				{
					$array .= '"' . implode('", "', $suggestions) . '"';
				}
			}

			$array .= ']),';
		}

		// If words were found, take off the last comma.
		if ($found_words)
		{
			$array = substr($array, 0, -1);
		}

		return $array;
	}
}
