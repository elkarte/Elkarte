<?php

/**
 * This file contains those functions specific to the question and answer verification controls
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\VerificationControls\VerificationControl;

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\HttpReq;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\ValuesContainer;

/**
 * Class to manage, prepare, show, and validate question -> answer verifications
 */
class Questions implements ControlInterface
{
	/** @var array Holds any options passed to the class */
	private $_options;

	/** @var int[] array holding all available question id */
	private $_questionIDs;

	/** @var int Number of challenge questions to use */
	private $_number_questions;

	/** @var int[] Questions that can be used given what available (try's to account for languages) */
	private $_possible_questions;

	/** @var int[] Array of question id's that they provided a wrong answer to */
	private $_incorrectQuestions;

	/** @var null|\ElkArte\ValuesContainer Filters to use to load the questions */
	private $_filter;

	/** @var \ElkArte\HttpReq Form variables */
	private $_req;

	/**
	 * On your mark
	 *
	 * @param array|null $verificationOptions override_qs,
	 */
	public function __construct($verificationOptions = null)
	{
		if (!empty($verificationOptions))
		{
			$this->_options = $verificationOptions;
		}

		$this->_filter = new ValuesContainer();
		$this->_req = HttpReq::instance();
	}

	/**
	 * {@inheritdoc}
	 */
	public function showVerification($sessionVal, $isNew, $force_refresh = true)
	{
		global $modSettings, $language;

		if ($isNew)
		{
			$this->_number_questions = $this->_options['override_qs'] ?? (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0);

			// If we want questions do we have a cache of all the IDs?
			if (!empty($this->_number_questions) && empty($modSettings['question_id_cache']))
			{
				$this->_refreshQuestionsCache();
			}

			// Let's deal with languages
			// First thing we need to know what language the user wants and if there is at least one question
			$questions_language = !empty($sessionVal['language']) ? $sessionVal['language'] : (!empty(User::$info->language) ? User::$info->language : $language);

			// No questions in the selected language?
			if (empty($modSettings['question_id_cache'][$questions_language]))
			{
				// Not even in the forum default? What the heck are you doing?!
				if (empty($modSettings['question_id_cache'][$language]))
				{
					$this->_number_questions = 0;
				}
				// Fall back to the default
				else
				{
					$questions_language = $language;
				}
			}

			// Do we have enough questions?
			if (!empty($this->_number_questions) && $this->_number_questions <= count($modSettings['question_id_cache'][$questions_language]))
			{
				$this->_possible_questions = $modSettings['question_id_cache'][$questions_language];
				$this->_number_questions = min($this->_number_questions, count($this->_possible_questions));
				$this->_questionIDs = [];

				if ($force_refresh)
				{
					$this->createTest($sessionVal, $force_refresh);
				}
			}
		}

		return !empty($this->_number_questions);
	}

	/**
	 * Updates the cache of questions IDs
	 *
	 * @throws \Exception
	 */
	private function _refreshQuestionsCache()
	{
		global $modSettings;

		$db = database();
		$cache = Cache::instance();

		if (!$cache->getVar($modSettings['question_id_cache'], 'verificationQuestionIds', 300) || !$modSettings['question_id_cache'])
		{
			$modSettings['question_id_cache'] = [];
			$db->fetchQuery('
				SELECT 
					id_question, language
				FROM {db_prefix}antispam_questions',
				[]
			)->fetch_callback(
				function ($row) use (&$modSettings) {
					$modSettings['question_id_cache'][$row['language']][] = $row['id_question'];
				}
			);

			$cache->put('verificationQuestionIds', $modSettings['question_id_cache'], 300);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function createTest($sessionVal, $refresh = true)
	{
		if (empty($this->_number_questions))
		{
			return;
		}

		// Getting some new questions?
		if ($refresh)
		{
			$this->_questionIDs = [];

			// Pick some random IDs
			if ($this->_number_questions == 1)
			{
				$this->_questionIDs[] = $this->_possible_questions[array_rand($this->_possible_questions, $this->_number_questions)];
			}
			else
			{
				foreach (array_rand($this->_possible_questions, $this->_number_questions) as $index)
				{
					$this->_questionIDs[] = $this->_possible_questions[$index];
				}
			}
		}
		// Same questions as before.
		else
		{
			$this->_questionIDs = !empty($sessionVal['q']) ? $sessionVal['q'] : [];
		}

		if (empty($this->_questionIDs) && !$refresh)
		{
			$this->createTest($sessionVal, true);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepareContext($sessionVal)
	{
		theme()->getTemplates()->load('VerificationControls');

		$value = [];
		$this->_filter = new ValuesContainer(['type' => 'id_question', 'value' => $this->_questionIDs]);

		$questions = $this->_loadAntispamQuestions();
		$asked_questions = [];

		$parser = ParserWrapper::instance();

		foreach ($questions as $row)
		{
			$asked_questions[] = [
				'id' => $row['id_question'],
				'q' => $parser->parseVerificationControls($row['question']),
				'is_error' => !empty($this->_incorrectQuestions) && in_array($row['id_question'], $this->_incorrectQuestions, true),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) ? Util::htmlspecialchars($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) : '',
			];

			$value[] = $row['id_question'];
		}

		// Need to load the valueContainer this way
		//$sessionVal['q'] = [];
		$sessionVal['q'] = $value;

		return [
			'template' => 'questions',
			'values' => $asked_questions,
		];
	}

	/**
	 * Loads all the available antispam questions, or a subset based on a filter
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function _loadAntispamQuestions()
	{
		$db = database();

		$available_filters = new ValuesContainer([
			'language' => ' WHERE language = {string:current_filter}',
			'id_question' => ' WHERE id_question IN ({array_int:current_filter})',
		]);
		$condition = (string) $available_filters[$this->_filter['type']];

		// Load any question and answers!
		$question_answers = [];
		$db->fetchQuery('
			SELECT 
				id_question, question, answer, language
			FROM {db_prefix}antispam_questions' .
				$condition,
			[
				'current_filter' => $this->_filter === null ? '' : $this->_filter['value'],
			]
		)->fetch_callback(
			function ($row) use (&$question_answers) {
				$question_answers[$row['id_question']] = [
					'id_question' => $row['id_question'],
					'question' => $row['question'],
					'answer' => Util::unserialize($row['answer']),
					'language' => $row['language'],
				];
			}
		);

		return $question_answers;
	}

	/**
	 * {@inheritdoc}
	 */
	public function doTest($sessionVal)
	{
		if ($this->_number_questions && (!isset($sessionVal['q'], $_REQUEST[$this->_options['id'] . '_vv']['q'])))
		{
			throw new Exception('no_access', false);
		}

		if (!$this->_verifyAnswers($sessionVal))
		{
			return 'wrong_verification_answer';
		}

		return true;
	}

	/**
	 * Checks if the answers to anti-spam questions are correct
	 *
	 * @param array $sessionVal
	 * @return bool
	 * @throws \Exception
	 */
	private function _verifyAnswers($sessionVal)
	{
		$this->_filter = new ValuesContainer(['type' => 'id_question', 'value' => $sessionVal['q']]);

		// Get the answers and see if they are all right!
		$questions = $this->_loadAntispamQuestions();
		$this->_incorrectQuestions = [];
		foreach ($questions as $row)
		{
			// Everything lowercase
			$answers = [];
			foreach ($row['answer'] as $answer)
			{
				$answers[] = Util::strtolower($answer);
			}

			if (!isset($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) || trim($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) == '' || !in_array(trim(Util::htmlspecialchars(Util::strtolower($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]))), $answers, true))
			{
				$this->_incorrectQuestions[] = $row['id_question'];
			}
		}

		return empty($this->_incorrectQuestions);
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasVisibleTemplate()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function settings()
	{
		global $txt, $context, $language;

		// Load any question and answers!
		if (isset($_GET['language']))
		{
			$this->_filter = new ValuesContainer([
				'type' => 'language',
				'value' => $_GET['language'],
			]);
		}
		$languages = getLanguages();

		// Languages dropdown only if we have more than a lang installed, otherwise is plain useless
		if (count($languages) > 1)
		{
			$context['languages'] = $languages;
			foreach ($context['languages'] as &$lang)
			{
				if ($lang['filename'] === $language)
				{
					$lang['selected'] = true;
				}
			}
		}

		// Saving them?
		if (isset($this->_req->save))
		{
			$question = $this->_req->getPost('question', '', []);
			$answer = $this->_req->getPost('answer', '', []);
			$sel_language = $this->_req->getPost('language', '', []);

			$count_questions = $this->_saveSettings($question, $answer, $sel_language);

			if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
			{
				$_POST['qa_verification_number'] = $count_questions;
			}
		}

		$context['question_answers'] = $this->_loadAntispamQuestions();

		return [
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			['title', 'setup_verification_questions'],
			['desc', 'setup_verification_questions_desc'],
			['int', 'qa_verification_number', 'postinput' => $txt['setting_qa_verification_number_desc']],
			['callback', 'question_answer_list'],
		];
	}

	/**
	 * Prepares the questions coming from the UI to be saved into the database.
	 *
	 * @param array $save_question
	 * @param array $save_answer
	 * @param array $save_language
	 * @return int
	 */
	protected function _saveSettings($save_question, $save_answer, $save_language)
	{
		global $language;

		$existing_question_answers = $this->_loadAntispamQuestions();

		// Handle verification questions.
		$questionInserts = [];
		$count_questions = 0;

		foreach ($save_question as $id => $question)
		{
			$question = trim(Util::htmlspecialchars($question, ENT_COMPAT));
			$answers = [];
			$question_lang = isset($save_language[$id], $languages[$save_language[$id]]) ? $save_language[$id] : $language;
			if (!empty($save_answer[$id]))
			{
				foreach ($save_answer[$id] as $answer)
				{
					$answer = trim(Util::strtolower(Util::htmlspecialchars($answer, ENT_COMPAT)));
					if ($answer !== '')
					{
						$answers[] = $answer;
					}
				}
			}

			// Already existed?
			if (isset($existing_question_answers[$id]))
			{
				$count_questions++;

				// Changed?
				if ($question === '' || empty($answers))
				{
					$this->_delete($id);
					$count_questions--;
				}
				else
				{
					$this->_update($id, $question, $answers, $question_lang);
				}
			}
			// It's so shiney and new!
			elseif ($question !== '' && !empty($answers))
			{
				$questionInserts[] = [
					'question' => $question,
					// @todo: remotely possible that the serialized value is longer than 65535 chars breaking the update/insertion
					'answer' => serialize($answers),
					'language' => $question_lang,
				];
				$count_questions++;
			}
		}

		// Any questions to insert?
		if (!empty($questionInserts))
		{
			$this->_insert($questionInserts);
		}

		return $count_questions;
	}

	/**
	 * Remove a question by id
	 *
	 * @param int $id
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _delete($id)
	{
		$db = database();

		$db->query('', '
			DELETE FROM {db_prefix}antispam_questions
			WHERE id_question = {int:id}',
			[
				'id' => $id,
			]
		);
	}

	/**
	 * Update an existing question
	 *
	 * @param int $id
	 * @param string $question
	 * @param string[] $answers
	 * @param string $language
	 * @throws \ElkArte\Exceptions\Exception
	 */
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
			[
				'id' => $id,
				'question' => $question,
				// @todo: remotely possible that the serialized value is longer than 65535 chars breaking the update/insertion
				'answer' => serialize($answers),
				'language' => $language,
			]
		);
	}

	/**
	 * Adds the questions to the db
	 *
	 * @param array $questions
	 * @throws \Exception
	 */
	private function _insert($questions)
	{
		$db = database();

		$db->insert('',
			'{db_prefix}antispam_questions',
			['question' => 'string-65535', 'answer' => 'string-65535', 'language' => 'string-50'],
			$questions,
			['id_question']
		);
	}
}
