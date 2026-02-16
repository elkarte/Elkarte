<?php

/**
 * This file provides utility functions and db functions for signature management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

use BBC\ParserWrapper;
use ElkArte\Emoji;
use ElkArte\Helper\Util;

/**
 * Loads the signature from 50 members per request
 * Used in ManageSignatures to apply signature settings to all members
 *
 * @param int $start_member
 * @return array
 */
function getSignatureFromMembers($start_member)
{
	$db = database();

	$members = [];

	$db->fetchQuery('
		SELECT 
			id_member, signature
		FROM {db_prefix}members
		WHERE id_member BETWEEN ' . $start_member . ' AND ' . $start_member . ' + 49
			AND id_group != {int:admin_group}
			AND FIND_IN_SET({int:admin_group}, additional_groups) = 0',
		[
			'admin_group' => 11,
		]
	)->fetch_callback(
		function ($row) use (&$members) {
			$members[$row['id_member']]['id_member'] = $row['id_member'];
			$members[$row['id_member']]['signature'] = $row['signature'];
		}
	);

	return $members;
}

/**
 * Updates the signature from a given member
 *
 * @param int $id_member
 * @param string $signature
 */
function updateSignature($id_member, $signature)
{
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($id_member, ['signature' => $signature]);
}

/**
 * Enforce maximum character limit on signature
 *
 * @param string $sig The signature text
 * @param int $max_length Maximum character length
 * @return string The truncated signature
 */
function enforceSignatureMaxCharacters(&$sig, $max_length)
{
	if (!empty($max_length))
	{
		$sig = Util::substr($sig, 0, (int) $max_length);
	}
}

/**
 * Enforce maximum line limit on signature
 *
 * @param string $sig The signature text
 * @param int $max_lines Maximum number of lines
 * @return string The signature with excess lines converted to spaces
 */
function enforceSignatureMaxLines(&$sig, $max_lines)
{
	if (!empty($max_lines))
	{
		// Start with 1 line (first line before any newline)
		$count = 1;
		$str_len = strlen($sig);
		for ($i = 0; $i < $str_len; $i++)
		{
			if ($sig[$i] === "\n")
			{
				$count++;
				if ($count > $max_lines)
				{
					// Truncate at this position and replace remaining newlines with spaces
					$sig = substr($sig, 0, $i) . strtr(substr($sig, $i), ["\n" => ' ']);
					break;
				}
			}
		}
	}
}

/**
 * Enforce maximum font size in signature
 *
 * @param string $sig The signature text
 * @param int $max_size Maximum font size in pixels
 * @return string The signature with font sizes enforced
 */
function enforceSignatureMaxTextSize(&$sig, $max_size)
{
	if (empty($max_size))
	{
		return;
	}

	$replacements = getFontSizeViolations($sig, $max_size);

	foreach ($replacements as $original => $replacement)
	{
		$sig = str_replace($original, $replacement, $sig);
	}
}

/**
 * Determine if a signature exceeds the maximum number of lines.
 *
 * @param string $sig The signature text
 * @param int $max_lines Maximum number of lines
 * @return bool True if the signature exceeds the limit
 */
function signatureExceedsMaxLines($sig, $max_lines)
{
	if (empty($max_lines) || $sig === '')
	{
		return false;
	}

	$line_count = substr_count($sig, "\n") + 1;

	return $line_count > $max_lines;
}

/**
 * Determine if a signature exceeds the maximum number of images.
 *
 * @param string $sig The signature text
 * @param int $max_images Maximum number of images
 * @return bool True if the signature exceeds the limit
 */
function signatureExceedsMaxImages($sig, $max_images)
{
	if (empty($max_images))
	{
		return false;
	}

	$matches = getSignatureImageMatches($sig);

	return count($matches[0]) > $max_images;
}

/**
 * Determine if a signature uses a font size that exceeds the maximum.
 *
 * @param string $sig The signature text
 * @param int $max_size Maximum font size in pixels
 * @param string $limit_broke The limit string that was exceeded
 * @return bool True if the signature exceeds the limit
 */
function signatureHasTooLargeFontSize($sig, $max_size, &$limit_broke)
{
	$limit_broke = '';

	if (empty($max_size))
	{
		return false;
	}

	$violations = getFontSizeViolations($sig, $max_size);

	if (!empty($violations))
	{
		// Get the first violation's limit value for the error message
		$limit_broke = reset($violations);
		$limit_broke = str_replace('[size=', '', $limit_broke);

		return true;
	}

	return false;
}

/**
 * Parse font size tags and find violations of max size limit
 *
 * @param string $sig The signature text
 * @param int $max_size Maximum font size in pixels
 * @return array Associative array of original tags to replacement tags for violations
 */
function getFontSizeViolations($sig, $max_size)
{
	$violations = [];

	if (!preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)?~i', $sig, $matches) || !isset($matches[2]))
	{
		return $violations;
	}

	// Same as parse_bbc
	$sizes = [1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95];

	foreach ($matches[1] as $ind => $size)
	{
		$limit_broke = '';

		// Just specifying as [size=x]?
		if (empty($matches[2][$ind]))
		{
			$matches[2][$ind] = 'em';
			$size = $sizes[(int) $size] ?? 0;
		}

		// Attempt to allow all sizes of abuse, so to speak.
		if ($matches[2][$ind] === 'px' && $size > $max_size)
		{
			$limit_broke = $max_size . 'px';
		}
		elseif ($matches[2][$ind] === 'pt' && $size > ($max_size * 0.75))
		{
			$limit_broke = ((int) $max_size * 0.75) . 'pt';
		}
		elseif ($matches[2][$ind] === 'em' && $size > ((float) $max_size / 16))
		{
			$limit_broke = ((float) $max_size / 16) . 'em';
		}
		elseif ($matches[2][$ind] !== 'px' && $matches[2][$ind] !== 'pt' && $matches[2][$ind] !== 'em' && $max_size < 18)
		{
			$limit_broke = 'large';
		}

		if ($limit_broke !== '')
		{
			$violations[$matches[0][$ind]] = '[size=' . $max_size . 'px';
		}
	}

	return $violations;
}

/**
 * Enforce maximum smiley limit on signature
 *
 * Finds smiley codes and emoji in the unparsed signature and replaces excess ones with ''
 * Works by getting smiley codes from the database and using the Emoji class regex.
 *
 * @param string $sig The unparsed signature text (modified by reference)
 * @param int $max_smileys Maximum number of smileys allowed (0 = unlimited, -1 = none allowed)
 */
function enforceSignatureMaxSmileys(&$sig, $max_smileys)
{
	if (empty($max_smileys))
	{
		return;
	}

	// Determine the limit (if -1, no smileys allowed so limit is 0)
	$limit = ($max_smileys == -1) ? 0 : $max_smileys;

	// Count current smileys
	$smiley_count = countSignatureSmileys($sig);

	// Check if we need to enforce anything
	if ($smiley_count <= $limit)
	{
		return;
	}

	$db = database();
	$emoji = Emoji::instance();

	// First, convert HTML-encoded emoji (&#128512;, &#x1f600;) to Unicode characters
	$sig = $emoji->emojiFromHTML($sig);

	// Get smiley codes from the database, ordered by length (longest first to avoid partial replacements)
	$smileyCodes = [];
	$db->fetchQuery('
		SELECT code
		FROM {db_prefix}smileys
		ORDER BY LENGTH(code) DESC',
		[]
	)->fetch_callback(
		static function ($row) use (&$smileyCodes) {
			$smileyCodes[] = $row['code'];
		}
	);

	// Build a pattern to match any smiley code
	$smileyPattern = '';
	if (!empty($smileyCodes))
	{
		$escapedCodes = array_map(static fn($code) => preg_quote($code, '~'), $smileyCodes);
		$smileyPattern = '(?:' . implode('|', $escapedCodes) . ')';
	}

	// Get the emoji regex from the Emoji class
	$emoji->setSearchReplaceRegex();
	$emojiRegex = $emoji->emoji_regex;

	// Remove the delimiters (~...~u) from the emoji regex to combine it
	// The emoji regex format is ~pattern~u, so strip first char and last 2 chars
	$emojiPatternInner = '(?:' . substr($emojiRegex, 1, -2) . ')';

	// Build combined pattern - emoji first (longest matches), then smileys
	$patterns = array_filter([$emojiPatternInner, $smileyPattern]);
	$combinedPattern = '~(' . implode('|', $patterns) . ')~u';

	// Replace smileys/emoji beyond the limit
	$count = 0;
	$sig = preg_replace_callback(
		$combinedPattern,
		static function ($matches) use (&$count, $limit) {
			$count++;
			if ($count > $limit)
			{
				return '';
			}

			return $matches[0];
		},
		$sig
	);
}

/**
 * Collect image tag matches from a signature string.
 *
 * @param string $sig The signature text
 * @return array The preg_match_all matches array
 */
function getSignatureImageMatches($sig)
{
	$matches = [];
	preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br />)*([^<">]+?)(?:<br />)*\[/img\]~i', $sig, $matches);

	$matches2 = [];
	preg_match_all('~(?:&lt;|<)img\s+src=(?:&quot;|")?((?:http://|ftp://|https://|ftps://).+?)(?:&quot;|")?(?:\s+alt=(?:&quot;|")?(.*?)(?:&quot;|")?)?(?:\s?/)?(?:&gt;|>)~i', $sig, $matches2, PREG_PATTERN_ORDER);

	for ($i = 0; $i <= 7; $i++)
	{
		if (!isset($matches[$i]))
		{
			$matches[$i] = [];
		}
	}

	if (!empty($matches2[0]))
	{
		foreach ($matches2[0] as $ind => $dummy)
		{
			$matches[0][] = $matches2[0][$ind];
			$matches[1][] = '';
			$matches[2][] = '';
			$matches[3][] = '';
			$matches[4][] = '';
			$matches[5][] = '';
			$matches[6][] = '';
			$matches[7][] = $matches2[1][$ind];
		}
	}

	return $matches;
}

/**
 * Count the number of smileys in a signature by parsing smiley codes.
 *
 * @param string $sig The unparsed signature text
 * @return int The number of smileys found
 */
function countSignatureSmileys($sig)
{
	$wrapper = ParserWrapper::instance();
	$parser = $wrapper->getSmileyParser();
	$parser->setEnabled($GLOBALS['user_info']['smiley_set'] !== 'none' && trim($sig) !== '');
	$smiley_parsed = $parser->parse($sig);

	// Count smileys by finding new <img tags added by the smiley parser
	return substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($sig), '<img');
}

/**
 * Determine if a signature exceeds the maximum number of smileys.
 *
 * @param string $sig The unparsed signature text
 * @param int $max_smileys Maximum number of smileys (-1 = no smileys allowed, 0 = unlimited)
 * @return bool|string True if the signature exceeds the limit, 'disallowed' if smileys are not allowed
 */
function signatureExceedsMaxSmileys($sig, $max_smileys)
{
	if (empty($max_smileys))
	{
		return false;
	}

	$smiley_count = countSignatureSmileys($sig);

	// -1 means smileys are completely disabled
	if ($max_smileys == -1 && $smiley_count > 0)
	{
		return 'disallowed';
	}

	if ($max_smileys > 0 && $smiley_count > $max_smileys)
	{
		return true;
	}

	return false;
}

/**
 * Determine if a signature uses disabled BBC tags.
 *
 * @param string $sig The signature text
 * @param array $disabledTags List of disabled BBC tags
 * @return bool True if a disabled tag is found
 */
function signatureUsesDisabledBbc($sig, $disabledTags)
{
	if (empty($disabledTags))
	{
		return false;
	}

	$disabledSigBBC = implode('|', $disabledTags);

	return !empty($disabledSigBBC)
		&& preg_match('~\[(' . $disabledSigBBC . '[ =\]/])~i', $sig) === 1;
}

/**
 * Process and enforce image constraints in signature
 *
 * @param string $sig The signature text
 * @param int $max_images Maximum number of images
 * @param int $max_width Maximum image width
 * @param int $max_height Maximum image height
 * @return string The signature with images constrained
 */
function enforceSignatureImageConstraints(&$sig, $max_images, $max_width, $max_height)
{
	// No image constraints to enforce
	if (empty($max_images) && empty($max_width) && empty($max_height))
	{
		return;
	}

	$replaces = [];
	$img_count = 0;

	$matches = getSignatureImageMatches($sig);

	// Try to find all the images!
	if (!empty($matches[0]))
	{
		$image_count_holder = [];
		foreach ($matches[0] as $key => $image)
		{
			$width = -1;
			$height = -1;
			$img_count++;

			// Too many images?
			if (!empty($max_images) && $img_count > $max_images)
			{
				// If we've already had this before, we only want to remove the excess.
				if (isset($image_count_holder[$image]))
				{
					$img_offset = -1;
					$rep_img_count = 0;
					while ($img_offset !== false)
					{
						$img_offset = strpos($sig, $image, $img_offset + 1);
						$rep_img_count++;
						if ($rep_img_count > $image_count_holder[$image])
						{
							// Only replace the excess.
							$sig = substr($sig, 0, $img_offset) . str_replace($image, '', substr($sig, $img_offset));
							$img_offset = false;
						}
					}
				}
				else
				{
					$replaces[$image] = '';
				}

				continue;
			}

			// Does it have predefined restraints? Width first.
			if ($matches[6][$key])
			{
				$matches[2][$key] = $matches[6][$key];
			}

			if ($matches[2][$key] && $max_width && $matches[2][$key] > $max_width)
			{
				$width = $max_width;
				$matches[4][$key] *= $width / $matches[2][$key];
			}
			elseif ($matches[2][$key])
			{
				$width = $matches[2][$key];
			}

			// ... and height.
			if ($matches[4][$key] && $max_height && $matches[4][$key] > $max_height)
			{
				$height = $max_height;
				if ($width != -1)
				{
					$width *= $height / $matches[4][$key];
				}
			}
			elseif ($matches[4][$key])
			{
				$height = $matches[4][$key];
			}

			// If the dimensions are still not fixed - we need to check the actual image.
			if (($width == -1 && $max_width) || ($height == -1 && $max_height))
			{
				// We'll mess up with images, who knows.
				require_once(SUBSDIR . '/Attachments.subs.php');

				$sizes = url_image_size($matches[7][$key]);
				if (is_array($sizes))
				{
					// Too wide?
					if ($sizes[0] > $max_width && $max_width)
					{
						$width = $max_width;
						$sizes[1] *= $width / $sizes[0];
					}

					// Too high?
					if ($sizes[1] > $max_height && $max_height)
					{
						$height = $max_height;
						if ($width == -1)
						{
							$width = $sizes[0];
						}
						$width *= $height / $sizes[1];
					}
					elseif ($width != -1)
					{
						$height = $sizes[1];
					}
				}
			}

			// Did we come up with some changes? If so, remake the string.
			if ($width != -1 || $height != -1)
			{
				$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
			}

			// Record that we got one.
			$image_count_holder[$image] = isset($image_count_holder[$image]) ? $image_count_holder[$image] + 1 : 1;
		}

		if (!empty($replaces))
		{
			$sig = str_replace(array_keys($replaces), array_values($replaces), $sig);
		}
	}
}

/**
 * Remove disabled BBC tags from signature
 *
 * @param string $sig The signature text
 * @param array $disabledTags Array of disabled BBC tag names
 * @return string The signature with disabled tags removed
 */
function removeDisabledBbcTags(&$sig, $disabledTags)
{
	if (!empty($disabledTags))
	{
		$sig = preg_replace('~\[(?:' . implode('|', $disabledTags) . ').+?\]~i', '', $sig);
		$sig = preg_replace('~\[/(?:' . implode('|', $disabledTags) . ')\]~i', '', $sig);
	}
}

/**
 * Apply all signature constraints to a single signature
 *
 * @param string $sig The signature text
 * @param array $sig_limits Array of signature limits (indices: 1=max_chars, 2=max_lines, 3=max_images, 5=max_width, 6=max_height, 7=max_font_size)
 * @param array $disabledTags Array of disabled BBC tags
 * @return string The constrained signature
 */
function applySignatureConstraints($sig, $sig_limits, $disabledTags)
{
	// Apply all the rules we can realistically do.
	$sig = strtr($sig, ['<br />' => "\n"]);

	// Max characters...
	enforceSignatureMaxCharacters($sig, $sig_limits[1] ?? 0);

	// Max lines...
	enforceSignatureMaxLines($sig, $sig_limits[2] ?? 0);

	// Max text size
	enforceSignatureMaxTextSize($sig, $sig_limits[7] ?? 0);

	// Image constraints
	enforceSignatureImageConstraints($sig, $sig_limits[3] ?? 0, $sig_limits[5] ?? 0, $sig_limits[6] ?? 0);

	// Max smileys (assuming index 4 in sig_limits)
	enforceSignatureMaxSmileys($sig, $sig_limits[4] ?? 0);

	// Try to fix disabled tags.
	removeDisabledBbcTags($sig, $disabledTags);

	return strtr($sig, ["\n" => '<br />']);
}

/**
 * Update all signatures given a new set of constraints
 *
 * @param int $applied_sigs
 */
function updateAllSignatures($applied_sigs)
{
	global $context, $modSettings;

	require_once(SUBSDIR . '/Members.subs.php');

	// This is horrid - but I suppose some people will want the option to do it.
	$done = false;
	$context['max_member'] = maxMemberID();

	// Load all the signature settings.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);
	$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : [];

	// It does not work in signatures, and seriously, why would you do this?
	$disabledTags[] = 'footnote';

	while (!$done)
	{
		// No changed signatures yet
		$changes = [];

		// Get a group of member signatures, 50 at a clip
		$update_sigs = getSignatureFromMembers($applied_sigs);

		if (empty($update_sigs))
		{
			$done = true;
		}

		foreach ($update_sigs as $row)
		{
			$sig = applySignatureConstraints($row['signature'], $sig_limits, $disabledTags);

			call_integration_hook('integrate_apply_signature_settings', [&$sig, $sig_limits, $disabledTags]);

			if ($sig != $row['signature'])
			{
				$changes[$row['id_member']] = $sig;
			}
		}

		// Do we need to delete what we have?
		if (!empty($changes))
		{
			foreach ($changes as $id => $sig)
			{
				updateSignature($id, $sig);
			}
		}

		$applied_sigs += 50;
		if (!$done)
		{
			pauseSignatureApplySettings($applied_sigs);
		}
	}
}

/**
 * Pause the signature applying thing.
 *
 * @param int $applied_sigs
 */
function pauseSignatureApplySettings($applied_sigs)
{
	global $context, $txt, $time_start;

	// Try to get more time...
	detectServer()->setTimeLimit(600);

	// Have we exhausted all the time we allowed?
	if ((microtime(true) - $time_start) > 3)
	{
		return;
	}

	$context['continue_get_data'] = '?action=admin;area=postsettings;sa=sig;apply;step=' . $applied_sigs . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'sig';

	// Get the right percent.
	$context['continue_percent'] = round(($applied_sigs / $context['max_member']) * 100);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}
