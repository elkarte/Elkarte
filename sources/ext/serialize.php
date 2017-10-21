<?php
/*
 * @author anthon (dpt) pang (at) gmail (dot) com
 * @license Public Domain
 */

namespace ElkArte\ext\upgradephp;

/*
 * Used to impose practical limits for safe_unserialize()
 */
if (!defined('MAX_SERIALIZED_INPUT_LENGTH'))
{
	define('MAX_SERIALIZED_INPUT_LENGTH', 4194304);
}
if (!defined('MAX_SERIALIZED_ARRAY_LENGTH'))
{
	define('MAX_SERIALIZED_ARRAY_LENGTH', 2048);
}
if (!defined('MAX_SERIALIZED_ARRAY_DEPTH'))
{
	define('MAX_SERIALIZED_ARRAY_DEPTH', 24);
}


/**
 * safe_serialize implementation
 *
 * @param mixed $value
 * @return string
 * @throw Exception if $value is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_serialize( $value )
{
	if(is_null($value))
	{
		return 'N;';
	}
	if(is_bool($value))
	{
		return 'b:'.(int)$value.';';
	}
	if(is_int($value))
	{
		return 'i:'.$value.';';
	}
	if(is_float($value))
	{
		return 'd:'.$value.';';
	}
	if(is_string($value))
	{
		return 's:'.strlen($value).':"'.$value.'";';
	}
	if(is_array($value))
	{
		$out = '';
		foreach($value as $k => $v)
		{
			$out .= _safe_serialize($k) . _safe_serialize($v);
		}

		return 'a:'.count($value).':{'.$out.'}';
	}
	if(is_resource($value))
	{
		// built-in returns 'i:0;'
		throw new \Exception('safe_serialize: resources not supported');
	}
	if(is_object($value) || gettype($value) == 'object')
	{
		throw new \Exception('safe_serialize: objects not supported');
	}
	throw new \Exception('safe_serialize cannot serialize: '.gettype($value));
}

/**
 * Safe serialize() replacement
 * - output a strict subset of PHP's native serialized representation
 *
 * @param mixed $value
 * @return string
 */
function safe_serialize( $value )
{
	// ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	try {
		$out = _safe_serialize($value);
	} catch(Exception $e) {
		$out = false;
	}

	if (isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}
	return $out;
}

/**
 * safe_unserialize implementation
 *
 * @param string $str
 * @return mixed
 * @throw Exception if $str is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_unserialize($str)
{
	if(strlen($str) > MAX_SERIALIZED_INPUT_LENGTH)
	{
		throw new \Exception('safe_unserialize: input exceeds ' . MAX_SERIALIZED_INPUT_LENGTH);
	}

	if(empty($str) || !is_string($str))
	{
		return false;
	}

	$stack = array();
	$expected = array();
	$state = 0;

	while($state != 1)
	{
		$type = isset($str[0]) ? $str[0] : '';

		if($type == '}')
		{
			$str = substr($str, 1);
		}
		else if($type == 'N' && $str[1] == ';')
		{
			$value = null;
			$str = substr($str, 2);
		}
		else if($type == 'b' && preg_match('/^b:([01]);/', $str, $matches))
		{
			$value = $matches[1] == '1' ? true : false;
			$str = substr($str, 4);
		}
		else if($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches))
		{
			$value = (int)$matches[1];
			$str = $matches[2];
		}
		else if($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches))
		{
			$value = (float)$matches[1];
			$str = $matches[3];
		}
		else if($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int)$matches[1], 2) == '";')
		{
			$value = substr($matches[2], 0, (int)$matches[1]);
			$str = substr($matches[2], (int)$matches[1] + 2);
		}
		else if($type == 'a' && preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches) && $matches[1] < MAX_SERIALIZED_ARRAY_LENGTH)
		{
			$expectedLength = (int)$matches[1];
			$str = $matches[2];
		}
		else if($type == 'O')
		{
			throw new \Exception('safe_unserialize: objects not supported');
		}
		else
		{
			throw new \Exception('safe_unserialize: unknown/malformed type: '.$type);
		}

		switch($state)
		{
			case 3: // in array, expecting value or another array
				if($type == 'a')
				{
					if(count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH)
					{
						throw new \Exception('safe_unserialize: array nesting exceeds ' . MAX_SERIALIZED_ARRAY_DEPTH);
					}

					$stack[] = &$list;
					$list[$key] = array();
					$list = &$list[$key];
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if($type != '}')
				{
					$list[$key] = $value;
					$state = 2;
					break;
				}

				throw new \Exception('safe_unserialize: missing array value');

			case 2: // in array, expecting end of array or a key
				if($type == '}')
				{
					if(count($list) < end($expected))
					{
						throw new \Exception('safe_unserialize: array size less than expected ' . $expected[0]);
					}

					unset($list);
					$list = &$stack[count($stack)-1];
					array_pop($stack);

					// go to terminal state if we're at the end of the root array
					array_pop($expected);
					if(count($expected) == 0) {
						$state = 1;
					}
					break;
				}
				if($type == 'i' || $type == 's')
				{
					if(count($list) >= MAX_SERIALIZED_ARRAY_LENGTH)
					{
						throw new \Exception('safe_unserialize: array size exceeds ' . MAX_SERIALIZED_ARRAY_LENGTH);
					}
					if(count($list) >= end($expected))
					{
						throw new \Exception('safe_unserialize: array size exceeds expected length');
					}

					$key = $value;
					$state = 3;
					break;
				}

				throw new \Exception('safe_unserialize: illegal array index type');

			case 0: // expecting array or value
				if($type == 'a')
				{
					if(count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH)
					{
						throw new \Exception('safe_unserialize: array nesting exceeds ' . MAX_SERIALIZED_ARRAY_DEPTH);
					}

					$data = array();
					$list = &$data;
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if($type != '}')
				{
					$data = $value;
					$state = 1;
					break;
				}

				throw new \Exception('safe_unserialize: not in array');
		}
	}

	if(!empty($str))
	{
		throw new \Exception('safe_unserialize: trailing data in input');
	}
	return $data;
}

/**
 * Safe unserialize() replacement
 * - accepts a strict subset of PHP's native serialized representation
 *
 * @param string $str
 * @return mixed
 */
function safe_unserialize( $str )
{
	// ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	try {
		$out = _safe_unserialize($str);
	} catch(Exception $e) {
		$out = '';
	}

	if (isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}
	return $out;
}

?>