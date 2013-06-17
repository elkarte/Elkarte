<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */
if (!defined('ELK'))
	die('No access...');

/**
 * Class used to validate and transform data
 *
 * Initiate
 *		$validation = new Data_Validator();
 *
 * Set validation rules
 * validation_rules()
 * 		$validation->validation_rules(array(
 * 			'username' => 'required|alpha_numeric|max_length[10]|min_length[6]',
 * 			'email'    => 'required|valid_email'
 * 		));
 *
 * Set optional sanitation rules
 * sanitation_rules()
 * 		$validation->sanitation_rules(array(
 * 			'username'    => 'trim|uppercase',
 * 			'email'   	  => 'trim|gmail_normalize'
 * 		));
 *
 * Set Optional variable name substitutions
 * text_replacements()
 *		$validation->text_replacements(array(
 *			'username' => $txt['someThing'],
 *			'email' => $txt['someEmail']
 *		));
 *
 * Run the validation
 * 		$validation->validation_run($data);
 * $data must be an array with keys matching the validation rule e.g. $data['username'], $data['email']
 *
 * Get the results
 * 		$validation->validation_errors(optional array of fields to return errors)
 * 		$validation->validation_data()
 *		$validation->username
 *
 * Current validation can be one or a combination of:
 * 		max_length[x], min_length[x], length[x],
 * 		alpha, alpha_numeric, alpha_dash
 * 		numeric, integer, boolean, float, notequal[x,y,z]
 * 		valid_url, valid_ip, valid_ipv6, valid_email,
 * 		php_syntax, contains[x,y,x], required, without[x,y,z]
 */
class Data_Validator
{
	/**
	 * Validation rules
	 */
	protected $_validation_rules = array();

	/**
	 * Sanitation rules
	 */
	protected $_sanitation_rules = array();

	/**
	 * Text substitutions for field names in the error messages
	 */
	protected $_replacements = array();

	/**
	 * Holds validation errors
	 */
	protected $_validation_errors = array();

	/**
	 * Holds our data
	 */
	protected $_data = array();

	/**
	 * Strict data processing,
	 * if true drops data for which no sanitation rule was set
	 */
	protected $strict = false;

	/**
	 * Allow reading otherwise inaccessible data values
	 *
	 * @param type $property key name of array value to return
	 */
	public function __get($property)
	{
		return array_key_exists($property, $this->_data) ? $this->_data[$property] : null;
	}

	/**
	 * Set the validation rules that will be run against the data
	 *
	 * @param array $rules
	 * @return array
	 */
	public function validation_rules($rules = array())
	{
		// If its not an array, make it one
		if (!is_array($rules))
			$rules = array($rules);

		// Set the validation rules
		if (!empty($rules))
			$this->_validation_rules = $rules;
		else
			return $this->_validation_rules;
	}

	/**
	 * Sets the sanitation rules used to clean data
	 *
	 * @param array $rules
	 * @param boolean $strict
	 * @return array
	 */
	public function sanitation_rules($rules = array(), $strict = false)
	{
		// If its not an array, make it one
		if (!is_array($rules))
			$rules = array($rules);

		// Set the sanitation rules
		$this->strict = $strict;

		if (!empty($rules))
			$this->_sanitation_rules = $rules;
		else
			return $this->_sanitation_rules;
	}

	/**
	 * Field Name Replacements
	 *
	 * @return array
	 */
	public function text_replacements($replacements = array())
	{
		if (!empty($replacements))
			$this->_replacements = $replacements;
		else
			return $this->_replacements;
	}

	/**
	 * Run the sanitation and validation on the data
	 *
	 * @param array $input
	 * @return boolean
	 */
	public function validate($input)
	{
		// @todo this won't work, $input[$field] will be undefined
		if (!is_array($input))
			$input = array($input);

		// Clean em
		$this->_data = $this->_sanitize($input, $this->_sanitation_rules);

		// Check em
		return $this->_validate($this->_data, $this->_validation_rules);
	}

	/**
	 * Return errors
	 *
	 * @param mixed $raw - true returns the faw error array,
	 *					 - array returns just error messages of those fields
	 *					 - otherwise all error message(s)
	 * @return array
	 */
	public function validation_errors($raw = false)
	{
		if ($raw === true)
			return $this->_validation_errors;
		else
			return $this->_get_error_messages($raw);
	}

	/**
	 * Return data
	 *
	 * @return array
	 */
	public function validation_data($key = null)
	{
		if ($key === null)
			return $this->_data;

		return isset($this->_data[$key]) ? $this->_data[$key] : null;
	}

	/**
	 * Performs data validation against the provided rule
	 *
	 * @param mixed $input
	 * @param array $ruleset
	 * @return mixed
	 */
	private function _validate($input, $ruleset)
	{
		// No errors ... yet ;)
		$this->_validation_errors = array();

		// For each field, run our rules against the data
		foreach ($ruleset as $field => $rules)
		{
			// Get rules for this field
			$rules = explode('|', $rules);
			foreach ($rules as $rule)
			{
				$validation_method = null;
				$validation_parameters = null;

				// Were any parameters provided for the rule, e.g. min_length[6]
				if (preg_match('~(.*)\[(.*)\]~', $rule, $match))
				{
					$validation_method = '_validate_' . $match[1];
					$validation_parameters = $match[2];
				}
				// Or just a predefined rule e.g. valid_email
				else
					$validation_method = '_validate_' . $rule;

				// Time to validate
				$result = array();
				if (is_callable(array($this, $validation_method)))
					$result = $this->$validation_method($field, $input, $validation_parameters);
				else
					$result = array(
						'field' => $validation_method,
						'input' => $input[$field],
						'function' => '_validate_invalid_function',
						'param' => $validation_parameters
					);

				if (is_array($result))
					$this->_validation_errors[] = $result;
			}
		}

		return count($this->_validation_errors) === 0 ? true : false;
	}

	/**
	 * Data sanitation is a good thing
	 *
	 * @param mixed $input
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
				if ($this->strict)
					unset($input[$field]);

				continue;
			}

			// Rules for which we do have data
			$rules = explode('|', $rules);
			foreach ($rules as $rule)
			{
				// Set the method
				$sanitation_method = '_sanitation_' . $rule;

				// Defined method to use?
				if (is_callable(array($this, $sanitation_method)))
					$input[$field] = $this->$sanitation_method($input[$field]);
				// One of our static methods
				elseif (strpos($rule, '::') !== false && is_callable($rule))
					$input[$field] = call_user_func($rule, $input[$field]);
				// Maybe even a built in php function?
				elseif (function_exists($rule))
					$input[$field] = $rule($input[$field]);
				else
				{
					$input[$field] = $input[$field];
					// @todo fatal_error or other ? being asked to do something we don't know?
				}
			}
		}

		return $input;
	}

	/**
	 * Process any errors and return the error strings
	 *
	 * @return array
	 * @return string
	 */
	private function _get_error_messages($keys)
	{
		global $txt;

		if (empty($this->_validation_errors))
			return;

		loadLanguage('Validation');
		$result = array();

		// Just want specific errors then it must be an array
		if (!empty($keys) && !is_array($keys))
			$keys = array($keys);

		foreach ($this->_validation_errors as $error)
		{
			// Field name substitution supplied?
			$field = isset($this->_replacements[$error['field']]) ? $this->_replacements[$error['field']] : $error['field'];

			// Just want specific field errors returned?
			if (!empty($keys) && !in_array($error['field'], $keys))
				continue;

			// Set the error message for this validation failure
			if (isset($error['error']))
				$result[] = sprintf($txt[$error['error']], $field, $error['error_msg']);
			elseif (isset($txt[$error['function']]))
			{
				if (!empty($error['param']))
					$result[] = sprintf($txt[$error['function']], $field, $error['param']);
				else
					$result[] = sprintf($txt[$error['function']], $field, $error['input']);
			}
		}

		return $result;
	}

	//
	// Start of validation functions
	//

	/**
	 * Contains ... Verify that a value is one of those provided (case insensitive)
	 *
	 * Usage: '[key]' => 'contains[value, value, value]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_contains($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', trim(strtolower($validation_parameters)));
		$value = trim(strtolower($input[$field]));

		if (in_array($value, $validation_parameters))
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => implode(',', $validation_parameters)
		);
	}

	/**
	 * NotEqual ... Verify that a value does equal any values in list (case insensitive)
	 *
	 * Usage: '[key]' => 'notequal[value, value, value]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_notequal($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', trim(strtolower($validation_parameters)));
		$value = trim(strtolower($input[$field]));

		if (!in_array($value, $validation_parameters))
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => implode(',', $validation_parameters)
		);
	}

	/**
	 * Without ... Verify that a value does contain any characters/values in list
	 *
	 * Usage: '[key]' => 'without[value, value, value]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_without($field, $input, $validation_parameters = null)
	{
		$validation_parameters = explode(',', $validation_parameters);
		$value = $input[$field];

		foreach ($validation_parameters as $dummy => $check)
		{
			if (strpos($value, $check) !== false)
				return array(
					'field' => $field,
					'input' => $input[$field],
					'function' => __FUNCTION__,
					'param' => implode($check)
				);
		}

		return;
	}


	/**
	 * required ... Check if the specified key is present and not empty
	 *
	 * Usage: '[key]' => 'required'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_required($field, $input, $validation_parameters = null)
	{
		if (isset($input[$field]) && trim($input[$field]) !== '')
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * valid_email .... Determine if the provided email is valid
	 *
	 * Usage: '[key]' => 'valid_email'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_valid_email($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		$valid = true;
		$at_index = strrpos($input[$field], '@');

		// No @ in the email
		if ($at_index === false)
			$valid = false;
		else
		{
			// Time to do some checking on the local@domain parts
			// http://www.linuxjournal.com/article/9585
			$local = substr($input[$field], 0, $at_index);
			$local_len = strlen($local);

			$domain = substr($input[$field], $at_index + 1);
			$domain_len = strlen($domain);

			/* Some RFC 2822 email "rules" ...
			 * - Uppercase and lowercase English letters (a–z, A–Z)
			 * - Digits 0 to 9
			 * - Characters !#$%&'*+-/=?^_`{|}~
			 * - Character . provided that it is not the first/last and that it does not appear two or more times consecutively
			 * - Special characters Space "(),:;<>@[\] provided they are contained between quotation marks, and that 2 of them, the
			 *   backslash \ and quotation mark ", must also be preceded by a backslash \ (e.g. "\\\"").
			 */

			// local part length problems (RFC 2821)
			if ($local_len === 0 || $local_len > 64)
				$valid = false;
			// local part starts or ends with a '.' (RFC 2822)
			elseif ($local[0] === '.' || $local[$local_len - 1] === '.')
				$valid = false;
			// local part has two consecutive dots (RFC 2822)
			elseif (preg_match('~\\.\\.~', $local))
				$valid = false;
			// domain part length problems (RFC 2821)
			elseif ($domain_len === 0 || $domain_len > 255)
				$valid = false;
			// domain does not have a least two parts
			elseif (strpos($domain, '.') === false)
				$valid = false;
			// character not valid in domain part (RFC 1035)
			elseif (!preg_match('~^[A-Za-z0-9\\-\\.]+$~', $domain))
				$valid = false;
			// domain part has two consecutive dots (RFC 1035)
			elseif (preg_match('~\\.\\.~', $domain))
				$valid = false;
			// character not valid in local part unless local part is quoted (RFC 2822)
			elseif (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local)))
			{
				if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local)))
					$valid = false;
			}
		}

		if ($valid)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * max_length ... Determine if the provided value length is less or equal to a specific value
	 *
	 * Usage: '[key]' => 'max_length[x]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_max_length($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (Util::strlen($input[$field]) <= (int) $validation_parameters)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * min_length Determine if the provided value length is greater than or equal to a specific value
	 *
	 * Usage: '[key]' => 'min_length[x]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_min_length($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (Util::strlen($input[$field]) >= (int) $validation_parameters)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * length ... Determine if the provided value length matches a specific value
	 *
	 * Usage: '[key]' => 'exact_length[x]'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_length($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (Util::strlen($input[$field]) == (int) $validation_parameters)
			return;

		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}

	/**
	 * alpha ... Determine if the provided value contains only alpha characters
	 *
	 * Usage: '[key]' => 'alpha'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_alpha($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// A character with the Unicode property of letter (any kind of letter from any language)
		if (!preg_match('~^(\p{L})+$~iu', $input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * alpha_numeric ... Determine if the provided value contains only alpha-numeric characters
	 *
	 * Usage: '[key]' => 'alpha_numeric'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_alpha_numeric($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// A character with the Unicode property of letter or number (any kind of letter or numeric 0-9 from any language)
		if (!preg_match('~^([\p{L}\p{Nd}])+$~iu', $input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * alpha_dash ... Determine if the provided value contains only alpha characters plus dashed and underscores
	 *
	 * Usage: '[key]' => 'alpha_dash'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_alpha_dash($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!preg_match('~^([-_\p{L}])+$~iu', $input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * numeric ... Determine if the provided value is a valid number or numeric string
	 *
	 * Usage: '[key]' => 'numeric'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_numeric($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!is_numeric($input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * integer ... Determine if the provided value is a valid integer
	 *
	 * Usage: '[key]' => 'integer'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_integer($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!is_int($input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * boolean ... Determine if the provided value is a boolean
	 *
	 * Usage: '[key]' => 'boolean'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_boolean($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!is_bool($input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * float ... Determine if the provided value is a valid float
	 *
	 * Usage: '[key]' => 'float'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_float($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!is_float($input[$field]))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * valid_url ... Determine if the provided value is a valid-ish URL
	 *
	 * Usage: '[key]' => 'valid_url'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_valid_url($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (!preg_match('`^(https{0,1}?:(//([a-z0-9\-._~%]+)(:[0-9]+)?(/[a-z0-9\-._~%!$&\'()*+,;=:@]+)*/?))(\?[a-z0-9\-._~%!$&\'()*+,;=:@/?]*)?(\#[a-z0-9\-._~%!$&\'()*+,;=:@/?]*)?$`', $input[$field], $matches))
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * valid_ipv6 ... Determine if the provided value is a valid IPv6 address
	 *
	 * Usage: '[key]' => 'valid_ipv6'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_valid_ipv6($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (preg_match('~^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$~', $input[$field]) === 0)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
		}
	}

	/**
	 * valid_ip ... Determine if the provided value is a valid IP4 address
	 *
	 * Usage: '[key]' => 'valid_ip'
	 *
	 * @param string $field
	 * @param array $input
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_valid_ip($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $input[$field]) === 0)
		{
			return array(
				'field' => $field,
				'input' => $input[$field],
				'function' => __FUNCTION__,
				'param' => $validation_parameters
			);
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
	 * @param array or null $validation_parameters
	 * @return mixed
	 */
	protected function _validate_php_syntax($field, $input, $validation_parameters = null)
	{
		if (!isset($input[$field]))
			return;

		// Check the depth.
		$level = 0;
		$tokens = @token_get_all($input[$field]);
		foreach ($tokens as $token)
		{
			if ($token === '{')
				$level++;
			elseif ($token === '}')
				$level--;
		}

		if (!empty($level))
			$result = false;
		else
		{
			// Check the validity of the syntax.
			ob_start();
			$errorReporting = error_reporting(0);
			$result = @eval('
				if (false) {
					' . preg_replace('~^(?:\s*<\\?(?:php)?|\\?>\s*$)~', '', $input[$field]) . '
				}
			');
			error_reporting($errorReporting);
			ob_end_clean();
		}

		if ($result === false)
		{
			$errorMsg = error_get_last();

			return array(
				'field' => $field,
				'input' => $input[$field],
				'error' => 'php_syntax_error',
				'error_msg' => $errorMsg['message'],
				'param' => $validation_parameters
			);
		}
	}

	//
	// Start of sanitation functions
	//

	/**
	 * gmail_normalize ... Used to normalize a gmail address as many resolve to the same thing address
	 *
	 * - Gmail user can use @googlemail.com instead of @gmail.com
	 * - Gmail ignores all characters after a + (plus sign) in the username
	 * - Gmail ignores all . (dots) in username
	 * - auser@gmail.com, a.user@gmail.com, auser+big@gmail.com and a.user+gigantic@googlemail.com are same email address.
	 *
	 * @param string $input
	 */
	protected function _sanitation_gmail_normalize($input)
	{
		if (!isset($input))
			return;

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
}