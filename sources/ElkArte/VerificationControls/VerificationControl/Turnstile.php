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

use ElkArte\User;

/**
 * Class Turnstile
 */
class Turnstile implements ControlInterface
{
	/** @var array Holds the $verificationOptions passed to the constructor */
	private $_options;

	/** @var null|string turnstile site key */
	private $_site_key;

	/** @var null|string turnstile secret key */
	private $_secret_key;

	/** @var mixed Users IP address */
	private $_userIP;

	/** @var string */
	private $_siteVerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Turnstile constructor.
	 *
	 * @param null|array $verificationOptions
	 */
	public function __construct($verificationOptions = null)
	{
		global $modSettings;

		$this->_site_key = empty($modSettings['turnstile_site_key']) ? '' : $modSettings['turnstile_site_key'];
		$this->_secret_key = empty($modSettings['turnstile_secret_key']) ? '' : $modSettings['turnstile_secret_key'];
		$this->_userIP = User::$info->ip;

		if (!empty($verificationOptions))
		{
			$this->_options = $verificationOptions;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function showVerification($sessionVal, $isNew, $force_refresh = true)
	{
		global $modSettings, $context;

		$show_captcha = !empty($modSettings['turnstile_enable']) && !empty($this->_site_key) && !empty($this->_secret_key);

		if ($show_captcha)
		{
			// Language parameter
			$lang = !empty($modSettings['turnstile_language']) ? $modSettings['turnstile_language'] : 'auto';

			theme()->getTemplates()->load('VerificationControls');
			loadJavascriptFile('https://challenges.cloudflare.com/turnstile/v0/api.js?onload=_turnstileCb', ['defer' => true, 'async' => 'true']);

			theme()->addInlineJavascript('
			function _turnstileCb() {
				turnstile.render("#TurnstileControl", {
					sitekey: "' . $this->_site_key . '",
					theme: "light",
					language: "' . $lang . '",
					action: "register"
				});
			};');
		}

		return $show_captcha;
	}

	/**
	 * {@inheritDoc}
	 */
	public function createTest($sessionVal, $refresh = true)
	{
		// Done by the JS which will $POST the results
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareContext($sessionVal)
	{
		return [
			'template' => 'Turnstile',
			'values' => [
				'site_key' => $this->_site_key,
			]
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function doTest($sessionVal)
	{
		if (!isset($_POST['cf-turnstile-response']) || empty(trim($_POST['cf-turnstile-response'])))
		{
			return 'wrong_captcha_verification';
		}

		$resp = $this->verifyResponse($_POST['cf-turnstile-response']);
		if ($resp['success'] === true)
		{
			return true;
		}

		return $resp['errorCodes'][0] ?? 'wrong_captcha_verification';
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasVisibleTemplate()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings()
	{
		global $txt;

		// Visual verification.
		return [
			['title', 'turnstile_verification'],
			['desc', 'turnstile_desc'],
			['check', 'turnstile_enable'],
			['text', 'turnstile_site_key', 40],
			['text', 'turnstile_secret_key', 40],
			['text', 'turnstile_language', 6, 'postinput' => $txt['turnstile_language_desc']],
		];
	}

	/**
	 * Calls the Turnstile API to verify whether the user passed the test.
	 *
	 * @param string $response response string from captcha verification.
	 */
	public function verifyResponse($response)
	{
		$turnstileResponse = [];
		$turnstileResponse['success'] = false;

		// Discard empty solution submissions
		if (empty($response))
		{
			$turnstileResponse['errorCodes'] = 'missing-input';

			return $turnstileResponse;
		}

		$getResponse = $this->_submitHTTPPost(
			[
				'secret' => $this->_secret_key,
				'remoteip' => $this->_userIP,
				'response' => $response
			]
		);

		if ($getResponse === false)
		{
			$turnstileResponse['errorCodes'] = 'failed-verification';

			return $turnstileResponse;
		}

		$answers = json_decode($getResponse, true);

		if (isset($answers) && $answers['success'] === true)
		{
			$turnstileResponse['success'] = true;
		}
		else
		{
			$turnstileResponse['errorCodes'] = $answers['error-codes'];
		}

		return $turnstileResponse;
	}

	/**
	 * Submits an HTTP POST to a Turnstile server.
	 *
	 * @param array $data array of parameters to be sent.
	 */
	private function _submitHTTPPost($data)
	{
		require_once(SUBSDIR . '/Package.subs.php');

		$req = [];
		foreach ($data as $key => $value)
		{
			$req[] = $key . '=' . $value;
		}

		$req = implode('&', $req);

		return fetch_web_data($this->_siteVerifyUrl, $req);
	}
}
