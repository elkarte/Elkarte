<?php

/**
 * Feature integration trait for themes
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes\Traits;

use ElkArte\Controller\ScheduledTasks;
use ElkArte\EventManager;
use ElkArte\User;

/**
 * Trait FeatureIntegration
 *
 * Handles specialized theme features like video embedding, code prettify, relative times
 */
trait FeatureIntegration
{
	/**
	 * If video embedding is enabled, this loads the necessary JS and vars
	 */
	public function autoEmbedVideo(): void
	{
		global $txt, $modSettings;

		if (!empty($modSettings['enableVideoEmbeding']))
		{
			loadJavascriptFile('elk_jquery_embed.js', ['defer' => true]);

			$this->addInlineJavascript('
				if (typeof oEmbedtext === "undefined") {
					var oEmbedtext = ({
						embed_limit : ' . (empty($modSettings['video_embed_limit']) ? 25 : $modSettings['video_embed_limit']) . ',
						preview_image : ' . JavaScriptEscape($txt['preview_image']) . ',
						ctp_video : ' . JavaScriptEscape($txt['ctp_video']) . ',
						hide_video : ' . JavaScriptEscape($txt['hide_video']) . ',
						youtube : ' . JavaScriptEscape($txt['youtube']) . ',
						vimeo : ' . JavaScriptEscape($txt['vimeo']) . ',
						dailymotion : ' . JavaScriptEscape($txt['dailymotion']) . ',
						tiktok : ' . JavaScriptEscape($txt['tiktok']) . ',
						twitter : ' . JavaScriptEscape($txt['twitter']) . ',
						facebook : ' . JavaScriptEscape($txt['facebook']) . ',
						instagram : ' . JavaScriptEscape($txt['instagram']) . ',
					});

					document.addEventListener("DOMContentLoaded", () => {
						if ($.isFunction($.fn.linkifyvideo))
						{
							$().linkifyvideo(oEmbedtext);
						}
					});
				}
			', true);
		}
	}

	/**
	 * If the option to pretty output code is on, this loads the JS and CSS
	 */
	public function addCodePrettify(): void
	{
		global $modSettings;

		if (!empty($modSettings['enableCodePrettify']))
		{
			$this->loadVariant('prettify');
			loadJavascriptFile('ext/prettify.min.js', ['defer' => true]);

			$this->addInlineJavascript('
				document.addEventListener("DOMContentLoaded", () => {
				if (typeof prettyPrint === "function")
				{
					prettyPrint();
				}
			});', true);
		}
	}

	/**
	 * Relative times require a few variables be set in the JS
	 */
	public function relativeTimes(): void
	{
		global $modSettings, $context, $txt;

		// Relative times?
		if (!empty($modSettings['todayMod']) && $modSettings['todayMod'] > 2)
		{
			loadJavascriptFile('elk_relativeTime.js', ['defer' => true]);
			$this->addInlineJavascript('
				if (typeof oRttime === "undefined") {
					var oRttime = ({
						referenceTime : ' . forum_time() * 1000 . ',
						now : ' . JavaScriptEscape($txt['rt_now']) . ',
						minute : ' . JavaScriptEscape($txt['rt_minute']) . ',
						minutes : ' . JavaScriptEscape($txt['rt_minutes']) . ',
						hour : ' . JavaScriptEscape($txt['rt_hour']) . ',
						hours : ' . JavaScriptEscape($txt['rt_hours']) . ',
						day : ' . JavaScriptEscape($txt['rt_day']) . ',
						days : ' . JavaScriptEscape($txt['rt_days']) . ',
						week : ' . JavaScriptEscape($txt['rt_week']) . ',
						weeks : ' . JavaScriptEscape($txt['rt_weeks']) . ',
						month : ' . JavaScriptEscape($txt['rt_month']) . ',
						months : ' . JavaScriptEscape($txt['rt_months']) . ',
						year : ' . JavaScriptEscape($txt['rt_year']) . ',
						years : ' . JavaScriptEscape($txt['rt_years']) . ',
					});
				}
				document.addEventListener("DOMContentLoaded", () => {updateRelativeTime();});', true);

			$context['using_relative_time'] = true;
		}
	}

	/**
	 * Ensures we kick the mail queue from time to time so that it gets
	 * checked as often as possible.
	 */
	public function doScheduledSendMail(): void
	{
		global $modSettings;

		if (!empty(User::$info->possibly_robot))
		{
			// @todo Maybe move this somewhere better?!
			$controller = new ScheduledTasks(new EventManager());

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
			{
				$controller->action_autotask();
			}
			else
			{
				$controller->action_reducemailqueue();
			}
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type === 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			$this->addInlineJavascript('
		function elkAutoTask()
		{
			let tempImage = new Image();
			tempImage.src = elk_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("elkAutoTask();", 1);', true);
		}
	}
}
