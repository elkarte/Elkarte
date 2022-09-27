<?php

/**
 * This file contains those functions specific to hCaptcha
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\VerificationControls\VerificationControl;

use ElkArte\User;

/**
 * Class hCaptcha
 */
class hCaptcha implements ControlInterface
{
	/** @var array Holds the $verificationOptions passed to the constructor */
	private $_options;

	/** @var null|string hCAPTCHA site key */
	private $_site_key;

	/** @var null|string hCAPTCHA secret key */
	private $_secret_key;

	/** @var mixed Users IP address */
	private $_userIP;

	/** @var string */
	private $_siteVerifyUrl = 'https://hcaptcha.com/siteverify';

	/**
	 * hCaptcha constructor.
	 *
	 * @param null|array $verificationOptions
	 */
	public function __construct($verificationOptions = null)
	{
		global $modSettings;

		$this->_site_key = !empty($modSettings['hcaptcha_site_key']) ? $modSettings['hcaptcha_site_key'] : '';
		$this->_secret_key = !empty($modSettings['hcaptcha_secret_key']) ? $modSettings['hcaptcha_secret_key'] : '';
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

		$show_captcha = empty($this->_options['override_visual']) && !empty($modSettings['hcaptcha_enable'])
			&& !empty($this->_site_key) && !empty($this->_secret_key);

		if ($show_captcha)
		{
			theme()->getTemplates()->load('VerificationControls');
			theme()->addInlineJavascript('
	var onloadhCaptcha = function() {hcaptcha.render("h-captcha", {sitekey: "' . $this->_site_key . '", theme: "light"});}'
			);

			loadJavascriptFile('https://js.hcaptcha.com/1/api.js?onload=onloadhCaptcha&render=explicit', ['defer' => true, 'async' => 'true']);
		}

		return $show_captcha;
	}

	/**
	 * {@inheritdoc}
	 */
	public function createTest($sessionVal, $refresh = true)
	{
		// Done by the JS which will $POST the results
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepareContext($sessionVal)
	{
		return [
			'template' => 'hcaptcha',
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
		if (empty(trim($_POST['h-captcha-response'])))
		{
			return 'wrong_captcha_verification';
		}

		$resp = $this->verifyResponse($this->_userIP, $_POST['h-captcha-response']);
		if ($resp['success'] === true)
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
		// Visual verification.
		return [
			['title', 'hcaptcha_verification'],
			['desc', 'hcaptcha_desc'],
			['check', 'hcaptcha_enable'],
			['text', 'hcaptcha_site_key'],
			['text', 'hcaptcha_secret_key'],
		];
	}

	/**
	 * Calls the hCAPTCHA API to verify whether the user passed the test.
	 *
	 * @param string $remoteIp IP address of end user.
	 * @param string $response response string from captcha verification.
	 *
	 */
	public function verifyResponse($remoteIp, $response)
	{
		$hcaptchaResponse = [];
		$hcaptchaResponse['success'] = false;

		// Discard empty solution submissions
		if (empty($response))
		{
			$hcaptchaResponse['errorCodes'] = 'missing-input';

			return $hcaptchaResponse;
		}

		$getResponse = $this->_submitHTTPPost(
			[
				'secret' => $this->_secret_key,
				'remoteip' => $remoteIp,
				'response' => $response
			]
		);

		$answers = json_decode($getResponse, true);
		if ($answers['success'] === true)
		{
			$hcaptchaResponse['success'] = true;
		}
		else
		{
			$hcaptchaResponse['errorCodes'] = $answers['error-codes'];
		}

		return $hcaptchaResponse;
	}

	/**
	 * Submits an HTTP POST to a hCAPTCHA server.
	 *
	 * @param array $data array of parameters to be sent.
	 */
	private function _submitHTTPPost($data)
	{
		require_once(SUBSDIR . '/Package.subs.php');

		$req = [];
		foreach ($data as $key => $value)
		{
			$req[] = $key . '=' . urlencode(stripslashes($value));
		}

		$req = implode('&', $req);

		return fetch_web_data($this->_siteVerifyUrl, $req);
	}
}
