<?php

/**
 * This file loads javascript localizations (i.e. language strings)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Jslocale_Controller class.
 * This file is called via ?action=jslocale;sa=sceditor to load in a list of
 * language strings for the editor
 */
class Jslocale_Controller extends Action_Controller
{
	/**
	 * The content of the file to be returned
	 * @var string
	 */
	private $_file_data = null;

	/**
	 * {@inheritdoc }
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * The default action for the class
	 */
	public function action_index()
	{
		// If we don't know what to do, better not do anything
		obExit(false);
	}

	/**
	 * Creates the javascript code for localization of the editor (SCEditor)
	 */
	public function action_sceditor()
	{
		global $txt, $editortxt;

		$this->_prepareLocale('Editor');

		// If we don't have any locale better avoid broken js
		if (empty($txt['lang_locale']) || empty($editortxt))
			die();

		$this->_file_data = '(function ($) {
		\'use strict\';

		$.sceditor.locale[' . JavaScriptEscape($txt['lang_locale']) . '] = {';

		foreach ($editortxt as $key => $val)
			$this->_file_data .= '
			' . JavaScriptEscape($key) . ': ' . JavaScriptEscape($val) . ',';

		$this->_file_data .= '
			dateFormat: "day.month.year"
		}
	})(jQuery);';

		$this->_sendFile();
	}

	public function action_agreement_api()
	{
		global $context, $modSettings;

		$langs = getLanguages();
		$lang = $this->_req->post->lang;

		Template_Layers::instance()->removeAll();
		loadTemplate('Json');
		$context['sub_template'] = 'send_json';
		$context['require_agreement'] = !empty($modSettings['requireAgreement']);

		if (isset($langs[$lang]))
		{
			// If you have to agree to the agreement, it needs to be fetched from the file.
			$agreement = new \Agreement($lang);
			try
			{
				$context['json_data'] = $agreement->getParsedText();
			}
			catch (\Elk_Exception $e)
			{
				$context['json_data'] = $e->getMessage();
			}
		}
		else
		{
			$context['json_data'] = '';
		}
	}

	/**
	 * Handy shortcut to prepare the "system"
	 *
	 * @param string $language_file
	 */
	private function _prepareLocale($language_file)
	{
		global $modSettings;

		if (!empty($language_file))
			loadLanguage($language_file);

		Template_Layers::instance()->removeAll();

		// Lets make sure we aren't going to output anything nasty.
		obStart(!empty($modSettings['enableCompressedOutput']));
	}

	/**
	 * Takes care of echo'ing the javascript file stored in $this->_file_data
	 */
	private function _sendFile()
	{
		// Make sure they know what type of file we are.
		header('Content-Type: text/javascript');

		echo $this->_file_data;

		// And terminate
		obExit(false);
	}
}