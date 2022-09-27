<?php

/**
 * This file contains those functions specific to keyCaptcha
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\VerificationControls\VerificationControl;

use ElkArte\Http\FsockFetchWebdata;
use ElkArte\User;

/**
 * Class keyCaptcha
 */
class keyCaptcha implements ControlInterface
{
	/** @var array Holds the $verificationOptions passed to the constructor */
	private $_options;

	/** @var null|string keyCAPTCHA site key */
	private $_site_key = '';

	/** @var null|string keyCAPTCHA secret key */
	private $_secret_key;

	/** @var mixed Users IP address */
	private $_userIP;

	/**
	 * keyCaptcha constructor.
	 *
	 * @param null|array $verificationOptions
	 */
	public function __construct($verificationOptions = null)
	{
		global $modSettings;

		$this->_secret_key = !empty($modSettings['keycaptcha_secret_key']) ? $modSettings['keycaptcha_secret_key'] : '';
		$this->getSiteAndUserKeys();
		$this->_userIP = User::$info->ip;

		if (!empty($verificationOptions))
		{
			$this->_options = $verificationOptions;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function showVerification($sessionVal, $isNew, $force_refresh = true)
	{
		global $modSettings;

		$show_captcha = empty($this->_options['override_visual']) && !empty($modSettings['keycaptcha_enable'])
			&& !empty($this->_site_key) && !empty($this->_secret_key);

		if ($show_captcha)
		{
			$sessionId = md5(uniqid('elkkeycaptcha', true));
			$sign = md5($sessionId . $this->_userIP  . $this->_secret_key);
			$sign2 = md5($sessionId . $this->_secret_key);

			theme()->getTemplates()->load('VerificationControls');
			theme()->addInlineJavascript('
	var s_s_c_user_id = "' . $this->_site_key . '",
		s_s_c_session_id = "' . $sessionId . '",
		s_s_c_captcha_field_id = "key-capcode",
		s_s_c_submit_button_id = "submit,regSubmit",
		s_s_c_web_server_sign = "' . $sign . '",
		s_s_c_web_server_sign2 = "' . $sign2 . '";
	document.s_s_c_without_submit_search=1;'
		);

			loadJavascriptFile('https://backs.keycaptcha.com/swfs/cap.js', ['defer' => true, 'async' => 'true']);
		}

		return $show_captcha;
	}

	/**
	 * {@inheritdoc}
	 */
	public function createTest($sessionVal, $refresh = true)
	{
		// keyCaptcha will take care of this
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepareContext($sessionVal)
	{
		return [
			'template' => 'keycaptcha',
			'values' => [
				'site_key' => $this->_site_key,
			]
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function doTest($sessionVal)
	{
		if (empty(trim($_POST['key-capcode'])))
		{
			return 'wrong_captcha_verification';
		}

		$resp = $this->verifyResponse($_POST['key-capcode']);
		if ($resp === '1')
		{
			return true;
		}

		return 'wrong_captcha_verification';
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
		return [
			['title', 'keycaptcha_verification'],
			['desc', 'keycaptcha_desc'],
			['check', 'keycaptcha_enable'],
			['text', 'keycaptcha_secret_key'],
		];
	}

	/**
	 * The site and user keys are joined by a 0
	 */
	private function getSiteAndUserKeys()
	{
		$set = explode('0', trim($this->_secret_key), 2);
		if (count($set) === 2)
		{
			$this->_secret_key = trim($set[0]);
			$this->_site_key = (int) $set[1];
		}
	}

	/**
	 * Calls the keyCAPTCHA API to verify whether the user passed the test.
	 *
	 * @param string $response response string from captcha verification.
	 */
	public function verifyResponse($response)
	{
		if (!$response || !is_string($response))
		{
			return false;
		}

		$kc_vars = explode("|", $response);
		if (count($kc_vars) < 4)
		{
			return false;
		}

		// Make sure the CAPTCHA answer is from the KeyCAPTCHA server
		// A == md5('accept'+B+PRIVATE_KEY+C)
		if ($kc_vars[0] !== hash('md5', 'accept' . $kc_vars[1] . $this->_secret_key . $kc_vars[2]))
		{
			return false;
		}

		if (strpos($kc_vars[2], 'http://') !== 0)
		{
			return false;
		}

		return $this->_submitHTTPGet($kc_vars[2]);
	}

	/**
	 * Submits an HTTP Get to a keyCAPTCHA server.
	 *
	 * @param string $data host/page to send the request.
	 */
	private function _submitHTTPGet($data)
	{
		require_once(SUBSDIR . '/Package.subs.php');

		$fetch_data = new FsockFetchWebdata([], 1, false);
		$fetch_data->get_url_data($data);
		if ((int) $fetch_data->result('code') === 200 && !$fetch_data->result('error'))
		{
			return $fetch_data->result('body');
		}

		// Failed to reach the server?  Let them pass instead of failing with no options
		return true;
	}
}