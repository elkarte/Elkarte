<?php

/**
 * This file contains those functions specific to Google reCaptcha V2
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
 * Class ReCaptcha
 */
class reCaptcha implements ControlInterface
{
	/** @var array Holds the $verificationOptions passed to the constructor */
	private $_options;

	/** @var null|string reCAPTCHA site key */
	private $_site_key;

	/** @var null|string reCAPTCHA secret key */
	private $_secret_key;

	/** @var mixed Users IP address */
	private $_userIP;

	/** @var string  */
	private $_siteVerifyUrl = 'https://www.google.com/recaptcha/api/siteverify?';

	/**
	 * ReCaptcha constructor.
	 *
	 * @param null|array $verificationOptions
	 */
	public function __construct($verificationOptions = null)
	{
		global $modSettings;

		// for development testing ONLY, compliments of a Google search for captcha keys
		// site key of 6Ld-KCcTAAAAAJTLqpKC3yba2tZZlytk0gtSxy0_
		// secret key of 6Ld-KCcTAAAAAOeMYwZdoI8QW4Pr_h0ZhW5WFHno
		// @todo remove at release
		$this->_site_key = !empty($modSettings['recaptcha_site_key']) ? $modSettings['recaptcha_site_key'] : '6Ld-KCcTAAAAAJTLqpKC3yba2tZZlytk0gtSxy0_';
		$this->_secret_key = !empty($modSettings['recaptcha_secret_key']) ? $modSettings['recaptcha_secret_key'] : '6Ld-KCcTAAAAAOeMYwZdoI8QW4Pr_h0ZhW5WFHno';
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

		$show_captcha = empty($this->_options['override_visual']) && !empty($modSettings['recaptcha_enable'])
			&& !empty($this->_site_key) && !empty($this->_secret_key);

		// On and valid, well at least non-empty keys.
		if ($show_captcha)
		{
			theme()->getTemplates()->load('VerificationControls');
			theme()->addInlineJavascript('
	var onloadreCaptcha = function() {grecaptcha.render("g-recaptcha", {sitekey: "' . $this->_site_key . '", theme: "light"});};'
			);

			loadJavascriptFile('https://www.google.com/recaptcha/api.js?onload=onloadreCaptcha&render=explicit', ['defer' => true, 'async' => 'true']);
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
			'template' => 'recaptcha',
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
		if (empty(trim($_POST['g-recaptcha-response'])))
		{
			return 'wrong_captcha_verification';
		}

		$resp = $this->verifyResponse($this->_userIP, $_POST['g-recaptcha-response']);
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
			['title', 'recaptcha_verification'],
			['desc', 'recaptcha_desc'],
			['check', 'recaptcha_enable'],
			['text', 'recaptcha_site_key'],
			['text', 'recaptcha_secret_key'],
		];
	}

	/**
	 * Calls the reCAPTCHA verify API to verify whether the user passed the CAPTCHA test.
	 *
	 * @param string $remoteIp IP address of end user.
	 * @param string $response response string from recaptcha verification.
	 *
	 */
	public function verifyResponse($remoteIp, $response)
	{
		$recaptchaResponse = [];
		$recaptchaResponse['success'] = false;

		// Discard empty solution submissions
		if (empty($response))
		{
			$recaptchaResponse['errorCodes'] = 'missing-input';

			return $recaptchaResponse;
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
			$recaptchaResponse['success'] = true;
		}
		else
		{
			$recaptchaResponse['errorCodes'] = $answers['error-codes'];
		}

		return $recaptchaResponse;
	}

	/**
	 * Submits an HTTP POST to a reCAPTCHA server.
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
