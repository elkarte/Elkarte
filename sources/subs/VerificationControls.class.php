<?php

/**
 * This file contains those functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Very simple function that loads and returns the verification controls known to Elk
 */
function loadVerificationControls()
{
	$known_verifications = array(
		'captcha',
		'questions',
		'emptyfield'
	);
	call_integration_hook('integrate_control_verification', array(&$known_verifications));

	return $known_verifications;
}

/**
 * Create a anti-bot verification control?
 *
 * @param array $verificationOptions
 * @param bool $do_test = false
 */
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $context;

	// We need to remember this because when failing the page is realoaded and the
	// code must remain the same (unless it has to change)
	static $all_instances = array();

	// Always have an ID.
	assert(isset($verificationOptions['id']));
	$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);

	if ($isNew)
		$context['controls']['verification'][$verificationOptions['id']] = array(
			'id' => $verificationOptions['id'],
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
			'render' => false,
		);
	$thisVerification = &$context['controls']['verification'][$verificationOptions['id']];

	if (!isset($_SESSION[$verificationOptions['id'] . '_vv']))
		$_SESSION[$verificationOptions['id'] . '_vv'] = array();

	$force_refresh = ((!empty($_SESSION[$verificationOptions['id'] . '_vv']['did_pass']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) || $_SESSION[$verificationOptions['id'] . '_vv']['count'] > 3) && empty($verificationOptions['dont_refresh']));
	if (!isset($all_instances[$verificationOptions['id']]))
	{
		$known_verifications = loadVerificationControls();
		$all_instances[$verificationOptions['id']] = array();

		$current_instance = null;
		foreach ($known_verifications as $verification)
		{
			$class_name = 'Control_Verification_' . ucfirst($verification);
			$current_instance = new $class_name($verificationOptions);

			// If there is anything to show, otherwise forget it
			if ($current_instance->showVerification($isNew, $force_refresh))
				$all_instances[$verificationOptions['id']][$verification] = $current_instance;
		}
	}

	$instances = &$all_instances[$verificationOptions['id']];

	// Is there actually going to be anything?
	if (empty($instances))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

	$verification_errors = Error_Context::context($verificationOptions['id']);
	$increase_error_count = false;

	// Start with any testing.
	if ($do_test)
	{
		// This cannot happen!
		if (!isset($_SESSION[$verificationOptions['id'] . '_vv']['count']))
			fatal_lang_error('no_access', false);

		foreach ($instances as $instance)
		{
			$outcome = $instance->doTest();
			if ($outcome !== true)
			{
				$increase_error_count = true;
				$verification_errors->addError($outcome);
			}
		}
	}

	// Any errors means we refresh potentially.
	if ($increase_error_count)
	{
		if (empty($_SESSION[$verificationOptions['id'] . '_vv']['errors']))
			$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		// Too many errors?
		elseif ($_SESSION[$verificationOptions['id'] . '_vv']['errors'] > $thisVerification['max_errors'])
			$force_refresh = true;

		// Keep a track of these.
		$_SESSION[$verificationOptions['id'] . '_vv']['errors']++;
	}

	// Are we refreshing then?
	if ($force_refresh)
	{
		// Assume nothing went before.
		$_SESSION[$verificationOptions['id'] . '_vv']['count'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = false;
	}

	foreach ($instances as $test => $instance)
	{
		$instance->createTest($force_refresh);
		$thisVerification['test'][$test] = $instance->prepareContext();
		if ($instance->hasVisibleTemplate())
			$thisVerification['render'] = true;
	}

	$_SESSION[$verificationOptions['id'] . '_vv']['count'] = empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) ? 1 : $_SESSION[$verificationOptions['id'] . '_vv']['count'] + 1;

	// Return errors if we have them.
	if ($verification_errors->hasErrors())
	{
		// @todo temporary until the error class is implemented in register
		$error_codes = array();
		foreach ($verification_errors->getErrors() as $errors)
			foreach ($errors as $error)
				$error_codes[] = $error;

		return $error_codes;
	}
	// If we had a test that one, make a note.
	elseif ($do_test)
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = true;

	// Say that everything went well chaps.
	return true;
}

/**
 * A simple interface that defines all the methods a "Control_Verification"
 * class must have because used in the process of creating the verification
 */
interface Control_Verifications
{
	function showVerification($isNew, $force_refresh = true);
	function createTest($refresh = true);
	function prepareContext();
	function doTest();
	function hasVisibleTemplate();
	function settings();
}

class Control_Verification_Captcha implements Control_Verifications
{
	private $_options = null;
	private $_show_captcha = false;
	private $_text_value = null;
	private $_image_href = null;
	private $_tested = false;
	private $_use_graphic_library = false;
	private $_standard_captcha_range = array();

	public function __construct($verificationOptions = null)
	{
		$this->_use_graphic_library = in_array('gd', get_loaded_extensions());

		// Skip I, J, L, O, Q, S and Z.
		$this->_standard_captcha_range = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));

		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	public function showVerification($isNew, $force_refresh = true)
	{
		global $context, $modSettings, $scripturl;

		// Some javascript ma'am? (But load it only once)
		if (!empty($this->_options['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($this->_options['override_visual'])) && empty($context['captcha_js_loaded']))
		{
			loadTemplate('VerificationControls');
			loadJavascriptFile('captcha.js');
			$context['captcha_js_loaded'] = true;
		}

		$this->_tested = false;

		if ($isNew)
		{
			$this->_show_captcha = !empty($this->_options['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($this->_option['override_visual']));

			if ($this->_show_captcha)
			{
				$this->_text_value = '';
				$this->_image_href = $scripturl . '?action=verificationcode;vid=' . $this->_options['id'] . ';rand=' . md5(mt_rand());

				addInlineJavascript('
					var verification' . $this->_options['id'] . 'Handle = new elkCaptcha("' . $this->_image_href . '", "' . $this->_options['id'] . '", ' . ($this->_use_graphic_library ? 1 : 0) . ');', true);
			}
		}

		if ($isNew || $force_refresh)
			$this->createTest($force_refresh);

		return $this->_show_captcha;
	}

	public function createTest($refresh = true)
	{
		global $modSettings;

		if (!$this->_show_captcha)
			return;

		if ($refresh)
		{
			$_SESSION[$this->_options['id'] . '_vv']['code'] = '';

			// Are we overriding the range?
			$character_range = !empty($this->_options['override_range']) ? $this->_options['override_range'] : $this->_standard_captcha_range;

			for ($i = 0; $i < $modSettings['visual_verification_num_chars']; $i++)
				$_SESSION[$this->_options['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}
		else
			$this->_text_value = !empty($_REQUEST[$this->_options['id'] . '_vv']['code']) ? Util::htmlspecialchars($_REQUEST[$this->_options['id'] . '_vv']['code']) : '';
	}

	public function prepareContext()
	{
		return array(
			'template' => 'captcha',
			'values' => array(
				'image_href' => $this->_image_href,
				'text_value' => $this->_text_value,
				'use_graphic_library' => $this->_use_graphic_library,
				'is_error' => $this->_tested && !$this->_verifyCode(),
			)
		);
	}

	public function doTest()
	{
		$this->_tested = true;

		if (!$this->_verifyCode())
			return 'wrong_verification_code';

		return true;
	}

	public function hasVisibleTemplate()
	{
		return true;
	}

	public function settings()
	{
		global $txt, $scripturl, $modSettings;

		// Generate a sample registration image.
		$verification_image = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

		// Visual verification.
		$config_vars = array(
			array('title', 'configure_verification_means'),
			array('desc', 'configure_verification_means_desc'),
			array('int', 'visual_verification_num_chars'),
			'vv' => array('select', 'visual_verification_type',
				array($txt['setting_image_verification_off'], $txt['setting_image_verification_vsimple'], $txt['setting_image_verification_simple'], $txt['setting_image_verification_medium'], $txt['setting_image_verification_high'], $txt['setting_image_verification_extreme']),
				'subtext'=> $txt['setting_visual_verification_type_desc'], 'onchange' => $this->_use_graphic_library ? 'refreshImages();' : ''),
		);

		if (isset($_GET['save']))
		{
			if (isset($_POST['visual_verification_num_chars']) && $_POST['visual_verification_num_chars'] < 6)
				$_POST['visual_verification_num_chars'] = 5;
		}

		$_SESSION['visual_verification_code'] = '';
		for ($i = 0; $i < $modSettings['visual_verification_num_chars']; $i++)
			$_SESSION['visual_verification_code'] .= $this->_standard_captcha_range[array_rand($this->_standard_captcha_range)];

		// Some javascript for CAPTCHA.
		if ($this->_use_graphic_library)
			addInlineJavascript('
			function refreshImages()
			{
				var imageType = document.getElementById(\'visual_verification_type\').value;
				document.getElementById(\'verification_image\').src = \'' . $verification_image . ';type=\' + imageType;
			}', true);

		// Show the image itself, or text saying we can't.
		if ($this->_use_graphic_library)
			$config_vars['vv']['postinput'] = '<br /><img src="' . $verification_image . ';type=' . (empty($modSettings['visual_verification_type']) ? 0 : $modSettings['visual_verification_type']) . '" alt="' . $txt['setting_image_verification_sample'] . '" id="verification_image" /><br />';
		else
			$config_vars['vv']['postinput'] = '<br /><span class="smalltext">' . $txt['setting_image_verification_nogd'] . '</span>';

		return $config_vars;
	}

	private function _verifyCode()
	{
		return !$this->_show_captcha || (!empty($_REQUEST[$this->_options['id'] . '_vv']['code']) && !empty($_SESSION[$this->_options['id'] . '_vv']['code']) && strtoupper($_REQUEST[$this->_options['id'] . '_vv']['code']) === $_SESSION[$this->_options['id'] . '_vv']['code']);
	}
}

class Control_Verification_Questions implements Control_Verifications
{
	private $_options = null;
	private $_questionIDs = null;
	private $_number_questions = null;
	private $_questions_language = null;
	private $_possible_questions = null;

	public function __construct($verificationOptions = null)
	{
		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	public function showVerification($isNew, $force_refresh = true)
	{
		global $modSettings, $user_info, $language;

		if ($isNew)
		{
			$this->_number_questions = isset($this->_options['override_qs']) ? $this->_options['override_qs'] : (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0);

			// If we want questions do we have a cache of all the IDs?
			if (!empty($this->_number_questions) && empty($modSettings['question_id_cache']))
				$this->_refreshQuestionsCache();

			// Let's deal with languages
			// First thing we need to know what language the user wants and if there is at least one question
			$this->_questions_language = !empty($_SESSION[$this->_options['id'] . '_vv']['language']) ? $_SESSION[$this->_options['id'] . '_vv']['language'] : (!empty($user_info['language']) ? $user_info['language'] : $language);

			// No questions in the selected language?
			if (empty($modSettings['question_id_cache'][$this->_questions_language]))
			{
				// Not even in the forum default? What the heck are you doing?!
				if (empty($modSettings['question_id_cache'][$language]))
				{
					$this->_number_questions = 0;
				}
				// Fall back to the default
				else
					$this->_questions_language = $language;
			}

			// Do we have enough questions?
			if (!empty($this->_number_questions) && $this->_number_questions <= count($modSettings['question_id_cache'][$this->_questions_language]))
			{
				$this->_possible_questions = $modSettings['question_id_cache'][$this->_questions_language];
				$this->_number_questions = min($this->_number_questions, count($this->_possible_questions));
				$this->_questionIDs = array();

				if ($isNew || $force_refresh)
					$this->createTest($force_refresh);
			}
		}

		return !empty($this->_number_questions);
	}

	public function createTest($refresh = true)
	{
		if (empty($this->_number_questions))
			return;

		// Getting some new questions?
		if ($refresh)
		{
			$this->_questionIDs = array();

			// Pick some random IDs
			if ($this->_number_questions == 1)
				$this->_questionIDs[] = $this->_possible_questions[array_rand($this->_possible_questions, $this->_number_questions)];
			else
				foreach (array_rand($this->_possible_questions, $this->_number_questions) as $index)
					$this->_questionIDs[] = $this->_possible_questions[$index];
		}
		// Same questions as before.
		else
			$this->_questionIDs = !empty($_SESSION[$this->_options['id'] . '_vv']['q']) ? $_SESSION[$this->_options['id'] . '_vv']['q'] : array();
	}

	public function prepareContext()
	{
		loadTemplate('VerificationControls');

		$_SESSION[$this->_options['id'] . '_vv']['q'] = array();

		$questions = $this->_loadAntispamQuestions(array('type' => 'id_question', 'value' => $this->_questionIDs));
		$asked_questions = array();

		foreach ($questions as $row)
		{
			$asked_questions[] = array(
				'id' => $row['id_question'],
				'q' => parse_bbc($row['question']),
				'is_error' => !empty($this->_incorrectQuestions) && in_array($row['id_question'], $this->_incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$this->_options['id'] . '_vv'], $_REQUEST[$this->_options['id'] . '_vv']['q'], $_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) ? Util::htmlspecialchars($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) : '',
			);
			$_SESSION[$this->_options['id'] . '_vv']['q'][] = $row['id_question'];
		}

		return array(
			'template' => 'questions',
			'values' => $asked_questions,
		);
	}

	public function doTest()
	{
		if ($this->_number_questions && (!isset($_SESSION[$this->_options['id'] . '_vv']['q']) || !isset($_REQUEST[$this->_options['id'] . '_vv']['q'])))
			fatal_lang_error('no_access', false);

		if (!$this->_verifyAnswers())
			return 'wrong_verification_answer';

		return true;
	}

	public function hasVisibleTemplate()
	{
		return true;
	}

	public function settings()
	{
		global $txt, $context, $language;

		// Load any question and answers!
		$filter = null;
		if (isset($_GET['language']))
			$filter = array(
				'type' => 'language',
				'value' => $_GET['language'],
			);
		$context['question_answers'] = $this->_loadAntispamQuestions($filter);
		$languages = getLanguages();
		// Languages dropdown only if we have more than a lang installed, otherwise is plain useless
		if (count($languages) > 1)
		{
			$context['languages'] = $languages;
			foreach ($context['languages'] as &$lang)
				if ($lang['filename'] === $language)
					$lang['selected'] = true;
		}

		if (isset($_GET['save']))
		{
			// Handle verification questions.
			$questionInserts = array();
			$count_questions = 0;

			foreach ($_POST['question'] as $id => $question)
			{
				$question = trim(Util::htmlspecialchars($question, ENT_COMPAT));
				$answers = array();
				$question_lang = isset($_POST['language'][$id]) && isset($languages[$_POST['language'][$id]]) ? $_POST['language'][$id] : $language;
				if (!empty($_POST['answer'][$id]))
					foreach ($_POST['answer'][$id] as $answer)
					{
						$answer = trim(Util::strtolower(Util::htmlspecialchars($answer, ENT_COMPAT)));
						if ($answer != '')
							$answers[] = $answer;
					}

				// Already existed?
				if (isset($context['question_answers'][$id]))
				{
					$count_questions++;
					// Changed?
					if ($question == '' || empty($answers))
					{
						$this->_delete($id);
						$count_questions--;
					}
					else
						$this->_update($id, $question, $answers, $question_lang);
				}
				// It's so shiney and new!
				elseif ($question != '' && !empty($answers))
				{
					$questionInserts[] = array(
						'question' => $question,
						// @todo: remotely possible that the serialized value is longer than 65535 chars breaking the update/insertion
						'answer' => serialize($answers),
						'language' => $question_lang,
					);
					$count_questions++;
				}
			}

			// Any questions to insert?
			if (!empty($questionInserts))
				$this->_insert($questionInserts);

			if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
				$_POST['qa_verification_number'] = $count_questions;

		}

		return array(
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			array('title', 'setup_verification_questions'),
				array('desc', 'setup_verification_questions_desc'),
				array('int', 'qa_verification_number', 'postinput' => $txt['setting_qa_verification_number_desc']),
				array('callback', 'question_answer_list'),
		);
	}

	/**
	* Checks if an the answers to anti-spam questions are correct
	* @param string $verificationId the ID of the verification element
	* @return mixed true if the answers are correct, an array of id of wrong questions otherwise
	*/
	private function _verifyAnswers()
	{
		// Get the answers and see if they are all right!
		$questions = $this->_loadAntispamQuestions(array('type' => 'id_question', 'value' => $_SESSION[$this->_options['id'] . '_vv']['q']));
		$this->_incorrectQuestions = array();
		foreach ($questions as $row)
		{
			// Everything lowercase
			$answers = array();
			foreach ($row['answer'] as $answer)
				$answers[] = Util::strtolower($answer);

			if (!isset($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) || trim($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) == '' || !in_array(trim(Util::htmlspecialchars(Util::strtolower($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]))), $answers))
				$this->_incorrectQuestions[] = $row['id_question'];
		}

		return empty($this->_incorrectQuestions);
	}

	/**
	* Updates the cache of questions IDs
	*/
	private function _refreshQuestionsCache()
	{
		global $modSettings;

		$db = database();

		if (($modSettings['question_id_cache'] = cache_get_data('verificationQuestionIds', 300)) == null)
		{
			$request = $db->query('', '
				SELECT id_question, language
				FROM {db_prefix}antispam_questions',
				array()
			);
			$modSettings['question_id_cache'] = array();
			while ($row = $db->fetch_assoc($request))
				$modSettings['question_id_cache'][$row['language']][] = $row['id_question'];
			$db->free_result($request);

			if (!empty($modSettings['cache_enable']))
				cache_put_data('verificationQuestionIds', $modSettings['question_id_cache'], 300);
		}
	}

	/**
	 * Loads all the available antispam questions, or a subset based on a filter
	 * @param array $filter, if specified it myst be an array with two indexes:
	 *              - 'type' => a valid filter, it can be 'language' or 'id_question'
	 *              - 'value' => the value of the filter (i.e. the language)
	 */
	private function _loadAntispamQuestions($filter = null)
	{
		$db = database();

		$available_filters = array(
			'language' => 'language = {string:current_filter}',
			'id_question' => 'id_question IN ({array_int:current_filter})',
		);

		// Load any question and answers!
		$question_answers = array();
		$request = $db->query('', '
			SELECT id_question, question, answer, language
			FROM {db_prefix}antispam_questions' . ($filter === null || !isset($available_filters[$filter['type']]) ? '' : '
			WHERE ' . $available_filters[$filter['type']]),
			array(
				'current_filter' => $filter['value'],
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$question_answers[$row['id_question']] = array(
				'id_question' => $row['id_question'],
				'question' => $row['question'],
				'answer' => unserialize($row['answer']),
				'language' => $row['language'],
			);
		}
		$db->free_result($request);

		return $question_answers;
	}

	private function _delete($id)
	{
		$db = database();

		$db->query('', '
			DELETE FROM {db_prefix}antispam_questions
			WHERE id_question = {int:id}',
			array(
				'id' => $id,
			)
		);
	}

	private function _update($id, $question, $answers, $language)
	{
		$db = database();

		$db->query('', '
			UPDATE {db_prefix}antispam_questions
			SET
				question = {string:question},
				answer = {string:answer},
				language = {string:language}
			WHERE id_question = {int:id}',
			array(
				'id' => $id,
				'question' => $question,
				// @todo: remotely possible that the serialized value is longer than 65535 chars breaking the update/insertion
				'answer' => serialize($answers),
				'language' => $language,
			)
		);
	}

	private function _insert($questions)
	{
		$db = database();

		$db->insert('',
			'{db_prefix}antispam_questions',
			array('question' => 'string-65535', 'answer' => 'string-65535', 'language' => 'string-50'),
			$questions,
			array('id_question')
		);
	}
}

/**
 * This class shows an anti spam bot box in the form
 * The proper response is to leave the field empty, bots however will see this
 * much like a session field and populate it with a value.
 *
 * Adding additional catch terms is recommended to keep bots from learning
 */
class Control_Verification_EmptyField implements Control_Verifications
{
	private $_options = null;
	private $_empty_field = null;
	private $_field_name = null;
	private $_tested = false;
	private $_user_value = null;
	private $_hash = null;
	private $_terms = array('gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier', 'bb2');
	private $_second_terms = array('hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value', 'screener');

	public function __construct($verificationOptions = null)
	{
		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	/**
	 * Returns if we are showing this verification control or not
	 *
	 * @param type $isNew
	 * @param type $force_refresh
	 */
	public function showVerification($isNew, $force_refresh = true)
	{
		global $modSettings;

		$this->_tested = false;

		if ($isNew)
		{
			$this->_empty_field = !empty($this->_options['no_empty_field']) || (!empty($modSettings['enable_emptyfield']) && !isset($this->_option['no_empty_field']));
			$this->_user_value = '';
		}

		if ($isNew || $force_refresh)
			$this->createTest($force_refresh);

		return $this->_empty_field;
	}

	/**
	 * Create the name data for the empty field that will be added to the template
	 *
	 * @param boolean $refresh
	 */
	public function createTest($refresh = true)
	{
		if (!$this->_empty_field)
			return;

		// Building a field with a believable name that will be inserted lives in the template.
		if ($refresh || !isset($_SESSION[$this->_options['id'] . '_vv']['empty_field']))
		{
			$start = mt_rand(0, 27);
			$this->_hash = substr(md5(time()), $start, 6);
			$this->_field_name = $this->_terms[array_rand($this->_terms)] . '-' . $this->_second_terms[array_rand($this->_second_terms)] . '-' . $this->_hash;
			$_SESSION[$this->_options['id'] . '_vv']['empty_field'] = '';
			$_SESSION[$this->_options['id'] . '_vv']['empty_field'] = $this->_field_name;
		}
		else
		{
			$this->_field_name = $_SESSION[$this->_options['id'] . '_vv']['empty_field'];
			$this->_user_value = !empty($_REQUEST[$_SESSION[$this->_options['id'] . '_vv']['empty_field']]) ? $_REQUEST[$_SESSION[$this->_options['id'] . '_vv']['empty_field']] : '';
		}
	}

	/**
	 * Values passed to the template inside of GenericControls
	 * Use the values to adjust how the control does or does not appear
	 */
	public function prepareContext()
	{
		loadTemplate('VerificationControls');

		return array(
			'template' => 'emptyfield',
			'values' => array(
				'is_error' => $this->_tested && !$this->_verifyField(),
				// Can be used in the template to show the normally hidden field to add some spice to things
				'show' => !empty($_SESSION[$this->_options['id'] . '_vv']['empty_field']) && (mt_rand(1, 100) > 60),
				'user_value' => $this->_user_value,
				'field_name' => $this->_field_name,
				// Can be used in the template to randomly add a value to the empty field that needs to be removed when show is on
				'clear' => (mt_rand(1, 100) > 60),
			)
		);
	}

	/**
	 * Run the test on the returned value and return pass or fail
	 */
	public function doTest()
	{
		$this->_tested = true;

		if (!$this->_verifyField())
			return 'wrong_verification_answer';

		return true;
	}

	public function hasVisibleTemplate()
	{
		return false;
	}

	/**
	 * Test the field, easy, its on, its is set and it is empty
	 */
	private function _verifyField()
	{
		return $this->_empty_field && !empty($_SESSION[$this->_options['id'] . '_vv']['empty_field']) && empty($_REQUEST[$_SESSION[$this->_options['id'] . '_vv']['empty_field']]);
	}

	/**
	 * Callback for this verification control options, which is on or off
	 */
	public function settings()
	{
		// Empty field verification.
		$config_vars = array(
			array('title', 'configure_emptyfield'),
			array('desc', 'configure_emptyfield_desc'),
			array('check', 'enable_emptyfield'),
		);

		return $config_vars;
	}
}