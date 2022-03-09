<?php

/**
 * Used to validate and transform user supplied data from forms etc
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Languages\Txt;

/**
 * Class used to validate and transform data
 *
 * Initiate
 *    $validation = new \ElkArte\DataValidator();
 *
 * Set validation rules
 *    $validation->validation_rules(array(
 *      'username' => 'required|alpha_numeric|max_length[10]|min_length[6]',
 *      'email'    => 'required|valid_email'
 *    ));
 *
 * Set optional sanitation rules
 *    $validation->sanitation_rules(array(
 *      'username' => 'trim|strtoupper',
 *      'email'    => 'trim|gmail_normalize'
 *    ));
 *
 * Set optional variable name substitutions
 *    $validation->text_replacements(array(
 *      'username' => $txt['someThing'],
 *      'email'    => $txt['someEmail']
 *    ));
 *
 * Set optional special processing tags
 *    $validation->input_processing(array(
 *      'somefield'    => 'csv',
 *      'anotherfield' => 'array'
 *    ));
 *
 * Run the validation
 *    $validation->validate($data);
 * $data must be an array with keys matching the validation rule e.g. $data['username'], $data['email']
 *
 * Get the results
 *    $validation->validation_errors(optional array of fields to return errors on)
 *    $validation->validation_data()
 *    $validation->username
 *
 * Use it inline with the static method
 * $_POST['username'] = ' username '
 * if (\ElkArte\DataValidator::is_valid($_POST, array('username' => 'required|alpha_numeric'), array('username' => 'trim|strtoupper')))
 *    $username = $_POST['username'] // now = 'USERNAME'
 *
 * Current validation can be one or a combination of:
 *    max_length[x], min_length[x], length[x],
 *    alpha, alpha_numeric, alpha_dash
 *    numeric, integer, boolean, float, notequal[x,y,z], isarray, limits[min,max]
 *    valid_url, valid_ip, valid_ipv6, valid_email, valid_color
 *    php_syntax, contains[x,y,x], required, without[x,y,z]
 */
class DataValidator
{
	/** @var array Validation rules */
	protected $_validation_rules = [];

	/** @var array Sanitation rules */
	protected $_sanitation_rules = [];

	/** @var array Text substitutions for field names in the error messages */
	protected $_replacements = [];

	/** @var array Holds validation errors */
	protected $_validation_errors = [];

	/** @var array Holds our data */
	protected $_data = [];

	/** @var bool Strict data processing, if true drops data for which no sanitation rule was set */
	protected $_strict = false;

	/** @var string[] Holds any special processing that is required for certain fields csv or array */
	protected $_datatype = [];

	/**
	 * Shorthand static method for simple inline validation
	 *
	 * @param array|object $data generally $_POST data for this method
	 * @param array $validation_rules associative array of field => rules
	 * @param array $sanitation_rules associative array of field => rules
	 *
	 * @return bool
	 */
	public static function is_valid(&$data = array(), $validation_rules = array(), $sanitation_rules = array())
	{
		$validator = new DataValidator();

		// Set the rules
		$validator->sanitation_rules($sanitation_rules);
		$validator->validation_rules($validation_rules);

		// Run the test
		$result = $validator->validate($data);

		// Replace the data
		if (!empty($sanitation_rules))
		{
			// Handle cases where we have an object
			if (is_object($data))
			{
				$data = array_replace((array) $data, $validator->validation_data());
				$data = (object) $data;
			}
			else
			{
				$data = array_replace($data, $validator->validation_data());
			}
		}

		// Return true or false on valid data
		return $result;
	}

	/**
	 * Sets the sanitation rules used to clean data
	 *
	 * @param array $rules associative array of field => rule|rule|rule
	 * @param bool $strict
	 */
	public function sanitation_rules($rules = array(), $strict = false)
	{
		// If not an array, make it one
		if (!is_array($rules))
		{
			$rules = array($rules);
		}

		// Set the sanitation rules
		$this->_strict = $strict;

		$this->_sanitation_rules = !empty($rules) ? $rules : $this->_sanitation_rules;
	}

	/**
	 * Set the validation rules that will be run against the data
	 *
	 * @param array $rules associative array of field => rule|rule|rule
	 */
	public function validation_rules($rules = array())
	{
		// If not an array, make it one
		if (!is_array($rules))
		{
			$rules = array($rules);
		}

		// Set the validation rules
		$this->_validation_rules = !empty($rules) ? $rules : $this->_validation_rules;
	}

	/**
	 * Run the sanitation and validation on the data
	 *
	 * @param array|object $input associative array or object of data to process name => value
	 *
	 * @return bool
	 */
	public function validate($input)
	{
		// If an object, convert it to an array
		if (is_object($input))
		{
			$input = (array) $input;
		}

		if (!is_array($input))
		{
			$key = (string) $input;
			$input[$key] = $input;
		}

		// Clean em
		$this->_data = $this->_sanitize($input, $this->_sanitation_rules);

		// Check em
		return $this->_validate($this->_data, $this->_validation_rules);
	}

	/**
	 * Data sanitation is a good thing
	 *
	 * @param array $input
	 * @param array $ruleset
	 * @return mixed
	 */
	private function _sanitize($input, $ruleset)
	{
		// For each field, run our set of rules against the data
		foreach ($ruleset as $field => $rules)
		{
			// Data for which we don't have rules
			if (!array_key_exists($field, $input))
			{
				if ($this->_strict)
				{
					unset($input[$field]);
				}

				continue;
			}

			// Is this a special processing field like csv or array?
			if (isset($this->_datatype[$field]) && in_array($this->_datatype[$field], array('csv', 'array')))
			{
				$input[$field] = $this->_sanitize_recursive($input, $field, $rules);
			}
			else
			{
				// Rules for which we do have data
				$rules = explode('|', $rules);
				foreach ($rules as $rule)
				{
					$sanitation = $this->_getRuleValues($rule, $type = '_sanitation_');

					// Defined method to use?
					if (is_callable(array($this, $sanitation['method'])))
					{
						$input[$field] = $this->{$sanitation['method']}($input[$field], $sanitation['parameters']);
					}
					// One of our static methods or even a built in php function like strtoupper, intval, etc?
					elseif (is_callable($sanitation['function']))
					{
						$input[$field] = call_user_func_array($sanitation['function'], array_merge((array) $input[$field], $sanitation['parameters_function']));
					}
					// Or even a language construct?
					elseif (in_array($sanitation['function'], array('empty', 'array', 'isset')))
					{
						// could be done as methods instead ...
						switch ($sanitation['function'])
						{
							case 'empty':
								$input[$field] = empty($input[$field]);
								break;
							case 'array':
								$input[$field] = is_array($input[$field]) ? $input[$field] : array($input[$field]);
								break;
							case 'isset':
								$input[$field] = isset($input[$field]);
								break;
						}
					}
					else
					{
						// @todo fatal_error or other ? being asked to do something we don't know?
						// results in returning $input[$field] = $input[$field];
					}
				}
			}
		}

		return $input;
	}

	/**
	 * When the input field is an array or csv, this will build a new validator
	 * as if the fields were individual ones, each checked against the base rule
	 *
	 * @param array $input
	 * @param string $field
	 * @param string $rules
	 *
	 * @return mixed
	 */
	private function _sanitize_recursive($input, $field, $rules)
	{
		// create a new instance to run against this sub data
		$validator = new DataValidator();

		$fields = array();
		$sanitation_rules = array();

		if ($this->_datatype[$field] === 'array')
		{
			// Convert the array to individual values, they all use the same rules
			foreach ($input[$field] as $key => $value)
			{
				$sanitation_rules[$key] = $rules;
				$fields[$key] = $value;
			}

			// Sanitize each "new" field
			$validator->sanitation_rules($sanitation_rules);
			$validator->validate($fields);

			// Take the individual results and replace them in the original array
			$input[$field] = array_replace($input[$field], $validator->validation_data());
		}
		elseif ($this->_datatype[$field] === 'csv')
		{
			// Break up the CSV data so we have an array
			$temp = explode(',', $input[$field]);
			foreach ($temp as $key => $value)
			{
				$sanitation_rules[$key] = $rules;
				$fields[$key] = $value;
			}

			// Sanitize each "new" field
			$validator->sanitation_rules($sanitation_rules);
			$validator->validate($fields);

			// Put it back together with clean data
			$input[$field] = implode(',', $validator->validation_data());
		}

		return $input[$field];
	}

	/**
	 * Return the validation data, all or a specific key
	 *
	 * @param int|string|null $key int or string
	 *
	 * @return mixed|array
	 */
	public function validation_data($key = null)
	{
		if ($key === null)
		{
			return $this->_data;
		}

		return $this->_data[$key] ?? null;
	}

	/**
	 * Performs data validation against the provided rule
	 *
	 * @param array $input
	 * @param array $ruleset
	 *
	 * @return bool
	 */
	private function _validate($input, $ruleset)
	{
		// No errors ... yet ;)
		$this->_validation_errors = array();

		// For each field, run our rules against the data
		foreach ($ruleset as $field => $rules)
		{
			// Special processing required on this field like csv or array?
			if (isset($this->_datatype[$field]) && in_array($this->_datatype[$field], array('csv', 'array')))
			{
				$this->_validate_recursive($input, $field, $rules);
			}
			else
			{
				// Get rules for this field
				$rules = explode('|', $rules);
				foreach ($rules as $rule)
				{
					$validation = $this->_getRuleValues($rule, '_validate_');
					$result = $this->_runValidationRule($field, $input, $validation);

					if (is_array($result))
					{
						$this->_validation_errors[] = $result;
					}
				}
			}
		}

		return count($this->_validation_errors) === 0;
	}

	/**
	 * Used when a field contains csv or array of data
	 *
	 * -Will convert field to individual elements and run a separate validation on that group
	 * using the rules defined to the parent node
	 *
	 * @param array $input
	 * @param string $field
	 * @param string $rules
	 *
	 * @return bool|void
	 */
	private function _validate_recursive($input, $field, $rules)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		// Start a new instance of the validator to work on this sub data (csv/array)
		$sub_validator = new DataValidator();

		$fields = array();
		$validation_rules = array();

		if ($this->_datatype[$field] === 'array')
		{
			// Convert the array to individual values, they all use the same rules
			foreach ($input[$field] as $key => $value)
			{
				$validation_rules[$key] = $rules;
				$fields[$key] = $value;
			}
		}
		// CSV is much the same process as array
		elseif ($this->_datatype[$field] === 'csv')
		{
			// Blow it up!
			$temp = explode(',', $input[$field]);
			foreach ($temp as $key => $value)
			{
				$validation_rules[$key] = $rules;
				$fields[$key] = $value;
			}
		}

		// Validate each "new" field
		$sub_validator->validation_rules($validation_rules);
		$result = $sub_validator->validate($fields);

		// If not valid, then just take the first error and use it for the original field
		if (!$result)
		{
			$errors = $sub_validator->validation_errors(true);
			foreach ($errors as $error)
			{
				$this->_validation_errors[] = array(
					'field' => $field,
					'input' => $error['input'],
					'function' => $error['function'],
					'param' => $error['param'],
				);
			}
		}

		return $result;
	}

	/**
	 * Return any errors found, either in the raw or nicely formatted
	 *
	 * @param array|string|bool $raw
	 *    - true returns the raw error array,
	 *    - array returns just error messages of those fields
	 *    - string returns just that error message
	 *    - default is all error message(s)
	 *
	 * @return array|bool
	 */
	public function validation_errors($raw = false)
	{
		// Return the array
		if ($raw === true)
		{
			return $this->_validation_errors;
		}

		// Otherwise, return the formatted text string(s)
		return $this->_get_error_messages($raw);
	}

	/**
	 * Process any errors and return the error strings
	 *
	 * @param array|string $keys
	 *
	 * @return array|bool
	 */
	private function _get_error_messages($keys)
	{
		global $txt;

		if (empty($this->_validation_errors))
		{
			return false;
		}

		Txt::load('Validation');
		$result = array();

		// Just want specific errors then it must be an array
		if (!empty($keys) && !is_array($keys))
		{
			$keys = array($keys);
		}

		foreach ($this->_validation_errors as $error)
		{
			// Field name substitution supplied?
			$field = $this->_replacements[$error['field']] ?? $error['field'];

			// Just want specific field errors returned?
			if (!empty($keys) && is_array($keys) && !in_array($error['field'], $keys))
			{
				continue;
			}

			// Set the error message for this validation failure
			if (isset($error['error']))
			{
				$result[] = sprintf($txt[$error['error']], $field, $error['error_msg']);
			}
			// Use our error text based on the function name itself
			elseif (isset($txt[$error['function']]))
			{
				if (!empty($error['param']))
				{
					$result[] = sprintf($txt[$error['function']], $field, $error['param']);
				}
				else
				{
					$result[] = sprintf($txt[$error['function']], $field, $error['input']);
				}
			}
			// can't find the function text, so set a generic one
			else
			{
				$result[] = sprintf($txt['_validate_generic'], $field);
			}
		}

		return empty($result) ? false : $result;
	}

	/**
	 * Parses the supplied rule into its components
	 *
	 * @param string $rule
	 * @param string $type
	 * @return array
	 */
	private function _getRuleValues($rule, $type)
	{
		$details = [];
		$details['parameters'] = null;
		$details['parameters_function'] = [];

		// Were any parameters provided for the rule, e.g. min_length[6]
		if (preg_match('~(.*)\[(.*)\]~', $rule, $match))
		{
			$details['method'] = $type . $match[1];
			$details['parameters'] = $match[2];
			$details['function'] = $match[1];
			$details['parameters_function'] = explode(',', defined($match[2]) ? constant($match[2]) : $match[2]);
		}
		// Or just a predefined rule e.g. valid_email
		else
		{
			$details['method'] = $type . $rule;
			$details['function'] = $rule;
		}

		return $details;
	}

	/**
	 * Runs a validation rule on a set of data
	 *
	 * @param string $field
	 * @param array $input
	 * @param array $validation
	 * @return array|false|mixed
	 */
	private function _runValidationRule($field, $input, $validation)
	{
		// Defined method to use?
		if (is_callable(array($this, $validation['method'])))
		{
			$result = $this->{$validation['method']}($field, $input, $validation['parameters']);
		}
		// Maybe even a custom function set up like a defined one, addons can do this.
		elseif (is_callable($validation['function'])
			&& isset($input[$field])
			&& strpos($validation['function'], 'validate_') === 0)
		{
			$result = call_user_func_array($validation['function'], array_merge((array) $field, (array) $input[$field], $validation['parameters_function']));
		}
		else
		{
			$result = array(
				'field' => $validation['method'],
				'input' => $input[$field] ?? null,
				'function' => '_validate_invalid_function',
				'param' => $validation['parameters']
			);
		}

		return $result;
	}

	/**
	 * Allow reading otherwise inaccessible data values
	 *
	 * @param string $property key name of array value to return
	 *
	 * @return mixed|null
	 */
	public function __get($property)
	{
		return $this->_data[$property] ?? null;
	}

	/**
	 * Allow testing data values for empty/isset
	 *
	 * @param string $property key name of array value to return
	 *
	 * @return bool
	 */
	public function __isset($property)
	{
		return isset($this->_data[$property]);
	}

	/**
	 * Field Name Replacements
	 *
	 * @param array $replacements associative array of field => txt string key
	 */
	public function text_replacements($replacements = array())
	{
		$this->_replacements = !empty($replacements) ? $replacements : $this->_replacements;
	}

	/**
	 * Set special processing conditions for fields, such as (and only)
	 * csv or array
	 *
	 * @param string[] $datatype csv or array processing for the field
	 */
	public function input_processing($datatype = array())
	{
		$this->_datatype = !empty($datatype) ? $datatype : $this->_datatype;
	}

	/**
	 * Common method used to set what failed during a test
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|string $validation_parameters
	 * @return array
	 */
	protected function setFailureArray($field, $input, $validation_parameters)
	{
		// Get the calling function that failed
		$dbt=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
		$caller = $dbt[1]['function'] ?? null;

		return array(
			'field' => $field,
			'input' => $input[$field] ?? '',
			'function' => $caller,
			'param' => is_array($validation_parameters)
				? implode(',', $validation_parameters)
				: $validation_parameters
		);
	}

	/**
	 * Contains ... Verify that a value is one of those provided (case insensitive)
	 *
	 * Usage: '[key]' => 'contains[value, value, value]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param string|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_contains($field, $input, $validation_parameters = null)
	{
		$validation_parameters = array_map('trim', explode(',', strtolower($validation_parameters)));
		$input[$field] = $input[$field] ?? '';
		$value = strtolower(trim($input[$field]));

		if (!in_array($value, $validation_parameters, true))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * NotEqual ... Verify that a value does equal any values in list (case insensitive)
	 *
	 * Usage: '[key]' => 'notequal[value, value, value]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param string|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_notequal($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', strtolower(trim($validation_parameters)));
		$input[$field] = $input[$field] ?? '';
		$value = strtolower(trim($input[$field]));

		if (in_array($value, $validation_parameters))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * Limits ... Verify that a value is within the defined limits
	 *
	 * Usage: '[key]' => 'limits[min, max]'
	 * >= min and <= max
	 * Limits may be specified one sided
	 *  - limits[,10] means <=10 with no lower bound check
	 *  - limits[10,] means >= 10 with no upper bound
	 *
	 * @param string $field
	 * @param array $input
	 * @param string|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_limits($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', $validation_parameters);
		$validation_parameters = array_filter($validation_parameters, 'strlen');
		$input[$field] = $input[$field] ?? '';
		$value = $input[$field];

		// Lower bound ?
		$passmin = true;
		if (isset($validation_parameters[0]))
		{
			$passmin = $value >= $validation_parameters[0];
		}

		// Upper bound ?
		$passmax = true;
		if (isset($validation_parameters[1]))
		{
			$passmax = $value <= $validation_parameters[1];
		}

		if (!$passmax || !$passmin)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * Without ... Verify that a value does contain any characters/values in list
	 *
	 * Usage: '[key]' => 'without[value, value, value]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param string $validation_parameters
	 *
	 * @return array|void
	 */
	protected function _validate_without($field, $input, $validation_parameters = '')
	{
		$parameters = explode(',', $validation_parameters);
		$input[$field] = $input[$field] ?? '';
		$value = $input[$field];

		foreach ($parameters as $dummy => $check)
		{
			if (strpos($value, $check) !== false)
			{
				return $this->setFailureArray($field, $input, $parameters);
			}
		}
	}

	/**
	 * required ... Check if the specified key is present and not empty
	 *
	 * Usage: '[key]' => 'required'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_required($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]) || trim($input[$field]) === '')
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * valid_email .... Determine if the provided email is valid
	 *
	 * Usage: '[key]' => 'valid_email'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_valid_email($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		$valid = !(strrpos($input[$field], '@') === false) && filter_var($input[$field], FILTER_VALIDATE_EMAIL) !== false;
		if (!$valid)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * max_length ... Determine if the provided value length is less or equal to a specific value
	 *
	 * Usage: '[key]' => 'max_length[x]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_max_length($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (Util::strlen($input[$field]) > (int) $validation_parameters)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * min_length Determine if the provided value length is greater than or equal to a specific value
	 *
	 * Usage: '[key]' => 'min_length[x]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_min_length($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (Util::strlen($input[$field]) < (int) $validation_parameters)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * length ... Determine if the provided value length matches a specific value
	 *
	 * Usage: '[key]' => 'exact_length[x]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_length($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (Util::strlen($input[$field]) !== (int) $validation_parameters)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * alpha ... Determine if the provided value contains only alpha characters
	 *
	 * Usage: '[key]' => 'alpha'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_alpha($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		// A character with the Unicode property of letter (any kind of letter from any language)
		if (!preg_match('~^(\p{L})+$~iu', $input[$field]))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * alpha_numeric ... Determine if the provided value contains only alpha-numeric characters
	 *
	 * Usage: '[key]' => 'alpha_numeric'
	 * Allows letters, numbers dash and underscore characters
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_alpha_numeric($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		// A character with the Unicode property of letter or number (any kind of letter or numeric 0-9 from any language)
		if (!preg_match('~^([-_\p{L}\p{Nd}])+$~iu', $input[$field]))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * alpha_dash ... Determine if the provided value contains only alpha characters plus dashed and underscores
	 *
	 * Usage: '[key]' => 'alpha_dash'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_alpha_dash($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (!preg_match('~^([-_\p{L}])+$~iu', $input[$field]))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * isarray ... Determine if the provided value exists and is an array
	 *
	 * Usage: '[key]' => 'isarray'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_isarray($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (!is_array($input[$field]))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * numeric ... Determine if the provided value is a valid number or numeric string
	 *
	 * Usage: '[key]' => 'numeric'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_numeric($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (!is_numeric($input[$field]))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * integer ... Determine if the provided value is a valid integer
	 *
	 * Usage: '[key]' => 'integer'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_integer($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (filter_var($input[$field], FILTER_VALIDATE_INT) === false)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * boolean ... Determine if the provided value is a boolean
	 *
	 * Usage: '[key]' => 'boolean'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_boolean($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		$filter = filter_var($input[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($filter === null)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * float ... Determine if the provided value is a valid float
	 *
	 * Usage: '[key]' => 'float'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_float($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (filter_var($input[$field], FILTER_VALIDATE_FLOAT) === false)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * valid_url ... Determine if the provided value is a valid-ish URL
	 *
	 * Usage: '[key]' => 'valid_url'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_valid_url($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (!preg_match('`^(https?:(//([a-z0-9\-._~%]+)(:\d+)?(/[a-z0-9\-._~%!$&\'()*+,;=:@]+)*/?))(\?[a-z0-9\-._~%!$&\'()*+,;=:@/?]*)?(\#[a-z0-9\-._~%!$&\'()*+,;=:@/?]*)?$`', $input[$field], $matches))
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * valid_ipv6 ... Determine if the provided value is a valid IPv6 address
	 *
	 * Usage: '[key]' => 'valid_ipv6'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_valid_ipv6($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (filter_var($input[$field], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * valid_ip ... Determine if the provided value is a valid IP4 address
	 *
	 * Usage: '[key]' => 'valid_ip'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 */
	protected function _validate_valid_ip($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		if (filter_var($input[$field], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
		{
			return $this->setFailureArray($field, $input, $validation_parameters);
		}
	}

	/**
	 * Validate PHP syntax of an input.
	 *
	 * This approach to validation has been inspired by Compuart.
	 *
	 * Usage: '[key]' => 'php_syntax'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|void
	 * @uses ParseError
	 *
	 */
	protected function _validate_php_syntax($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		// Check the depth.
		$level = 0;
		$tokens = @token_get_all($input[$field]);
		foreach ($tokens as $token)
		{
			if ($token === '{' || (isset($token[1]) && $token[1] === '${'))
			{
				$level++;
			}
			elseif ($token === '}')
			{
				$level--;
			}
		}

		if (!empty($level))
		{
			$result = false;
		}
		else
		{
			// Check the validity of the syntax.
			ob_start();
			$errorReporting = error_reporting(0);
			try
			{
				$result = @eval('
					if (false)
					{
						' . preg_replace('~^(?:\s*<\\?(?:php)?|\\?>\s*$)~u', '', $input[$field]) . '
					}
				');
			}
			catch (\ParseError $e)
			{
				$result = false;
			}
			error_reporting($errorReporting);
			@ob_end_clean();
		}

		if ($result === false)
		{
			$errorMsg = error_get_last();

			return array(
				'field' => $field,
				'input' => $input[$field],
				'error' => '_validate_php_syntax',
				'error_msg' => $errorMsg['message'] ?? 'NaN',
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * Checks if the input is a valid css-like color
	 *
	 * Usage: '[key]' => 'valid_color'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array|null $validation_parameters array or null
	 *
	 * @return array|bool|void
	 */
	protected function _validate_valid_color($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
		{
			return;
		}

		// A color can be a name: there are 140 valid, but a similar list is too long, so let's just use the basic 17
		if (in_array(strtolower($input[$field]), array('aqua', 'black', 'blue', 'fuchsia', 'gray', 'green', 'lime', 'maroon', 'navy', 'olive', 'orange', 'purple', 'red', 'silver', 'teal', 'white', 'yellow')))
		{
			return true;
		}

		// An hex code
		if (preg_match('~^#([a-f0-9]{3}|[a-f0-9]{6})$~i', $input[$field]) === 1)
		{
			return true;
		}

		// RGB
		if (preg_match('~^rgb\(\d{1,3},\d{1,3},\d{1,3}\)$~i', str_replace(' ', '', $input[$field])) === 1)
		{
			return true;
		}

		// RGBA
		if (preg_match('~^rgba\(\d{1,3},\d{1,3},\d{1,3},(0|0\.\d+|1(\.0*)|\.\d+)\)$~i', str_replace(' ', '', $input[$field])) === 1)
		{
			return true;
		}

		// HSL
		if (preg_match('~^hsl\(\d{1,3},\d{1,3}%,\d{1,3}%\)$~i', str_replace(' ', '', $input[$field])) === 1)
		{
			return true;
		}

		// HSLA
		if (preg_match('~^hsla\(\d{1,3},\d{1,3}%,\d{1,3}%,(0|0\.\d+|1(\.0*)|\.\d+)\)$~i', str_replace(' ', '', $input[$field])) === 1)
		{
			return true;
		}

		return $this->setFailureArray($field, $input, $validation_parameters);
	}

	/**
	 * gmail_normalize ... Used to normalize a gmail address as many resolve to the same address
	 *
	 * - Gmail user can use @googlemail.com instead of @gmail.com
	 * - Gmail ignores all characters after a + (plus sign) in the username
	 * - Gmail ignores all . (dots) in username
	 * - auser@gmail.com, a.user@gmail.com, auser+big@gmail.com and a.user+gigantic@googlemail.com are same email
	 * address.
	 *
	 * @param string $input
	 *
	 * @return string|void
	 */
	protected function _sanitation_gmail_normalize($input)
	{
		if (!isset($input))
		{
			return;
		}

		$at_index = strrpos($input, '@');

		// Time to do some checking on the local@domain parts
		$local_name = substr($input, 0, $at_index);
		$domain_name = strtolower(substr($input, $at_index + 1));

		// Gmail address?
		if (in_array($domain_name, array('gmail.com', 'googlemail.com')))
		{
			// Gmail ignores all . (dot) in username
			$local_name = str_replace('.', '', $local_name);

			// Gmail ignores all characters after a + (plus sign) in username
			$temp = explode('+', $local_name);
			$local_name = $temp[0];

			// @todo should we force gmail.com or use $domain_name, force is safest but perhaps most confusing
		}

		return $local_name . '@' . $domain_name;
	}

	/**
	 * Uses \ElkArte\Util::htmlspecialchars to sanitize any html in the input
	 *
	 * @param string $input
	 *
	 * @return null|string|string[]
	 */
	protected function _sanitation_cleanhtml($input)
	{
		if (!isset($input))
		{
			return;
		}

		return Util::htmlspecialchars($input);
	}
}
