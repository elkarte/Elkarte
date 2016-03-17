<?php

/**
 * @name      Inline Attachments (ILA)
 * @license   Mozilla Public License version 1.1 http://www.mozilla.org/MPL/1.1/.
 * @author    Spuds
 * @copyright (c) 2014 Spuds
 *
 * @version 1.1 beta 1
 *
 * Based on original code by mouser http://www.donationcoder.com
 * Updated/Modified/etc with permission
 *
 */

// Thats just no to you
if (!defined('ELK'))
	die('No access...');

/**
 * Searches a post for all ila tags and trys to replace them with the destinations
 * image, link, etc
 */
class In_Line_Attachment
{
	/**
	 * Holds attach id's that have been inlined so they can be excluded from display below
	 * @var int[]
	 */
	protected $_dont_show_attach_below = array();

	/**
	 * This loads an attachment's contextual data from loadAttachmentContext()
	 * @var mixed[]
	 */
	protected $_attachments_context = array();

	/**
	 * The singular attachment context we are working on for a given ila tag
	 * @var mixed[]
	 */
	protected $_attachment = array();

	/**
	 * Holds if we feel this is a preview so blocks are rendered
	 * @var boolean
	 */
	protected $_new_msg_preview = array();

	/**
	 * a simple array of elements use it to keep track of attachment number usage in the message
	 * @var int[]
	 */
	protected $_attachments = array();

	/**
	 * The board that the message is on, used for permission check
	 * @var int
	 */
	protected $_board = null;

	/**
	 * Pointer to the attachment ID to use as we loop through a message using sequential [attach] method
	 * @var int
	 */
	protected $_start_num = 0;

	/**
	 * The message body that we are looking for tags in
	 * @var string
	 */
	protected $_message = '';

	/**
	 * Topic ID that the message is associated with
	 * @var int
	 */
	protected $_topic = '';

	/**
	 * The message id as supplied from parsebbc, blank indicates a new or preview
	 * @var int|null
	 */
	protected $_id_msg = null;

	/**
	 * The details of the current ILA tag being worked on
	 * @var mixed[]
	 */
	protected $_curr_tag = array();

	/**
	 * Type of message we are looking at, that means type of attachment to load.
	 * @var int
	 */
	protected $_attach_source = 0;

	/**
	 * Constructor, loads the message an id in to the class
	 *
	 * @param string $message
	 * @param int|null $id_msg
	 */
	public function __construct($message, $id_msg, $attach_source)
	{
		$this->_message = $message;
		$this->_id_msg = $id_msg;
		$this->_attach_source = $attach_source;
	}

	/**
	 * parse_bbc()
	 *
	 * - Traffic cop
	 * - Checks availability
	 * - Finds all [attach tags, determines msg number, inits values
	 * - Calls needed functions to render ila tags
	 */
	public function parse_bbc()
	{
		global $modSettings, $context, $txt, $attachments, $topic;

		// Addon or BBC disabled, or coming in from areas we don't want to work on
		if (empty($modSettings['enableBBC']) || (isset($context['site_action']) && in_array($context['site_action'], array('boardindex', 'messageindex'))))
			return $this->_message;

		// No message id and not previewing a new message ($_REQUEST['ila'] will be set)
		if ($this->_id_msg === -1 && !isset($_REQUEST['ila']))
		{
			// Make sure block quotes are cleaned up, then return
			$this->_find_nested();
			return $this->_message;
		}

		// Can't trust the $topic global due to portals and other integration
		list($this->_topic, $this->_board) = $this->_get_topic($this->_id_msg);
		$save_topic = !empty($topic) ? $topic : '';
		$topic = $this->_topic;

		// Lets make sure we have the attachments
		require_once(SUBSDIR . '/Attachments.subs.php');
		if (!isset($attachments[$this->_id_msg]))
		{
			if (is_array($attachments))
				$attachments += $this->load_attachments();
			else
				$attachments = $this->load_attachments();
		}

		// Get the rest of the details for the message attachments, this uses the global topic
		$this->_attachments_context = loadAttachmentContext($this->_id_msg);

		// Put back the topic, whatever it was
		$topic = $save_topic;

		// Do we have new, not yet uploaded, attachments in either a new or a modified message (preview)?
		if (isset($_REQUEST['ila']))
		{
			$this->_start_num = isset($attachments[$this->_id_msg]) ? count($attachments[$this->_id_msg]) : 0;
			$ila_temp = explode(',', $_REQUEST['ila']);

			// Add them at the end of the currently uploaded attachment count index
			foreach ($ila_temp as $new_attach)
			{
				$this->_start_num++;
				$this->_new_msg_preview[$this->_start_num] = $new_attach;
			}
		}

		// Take care of any attach links that reside in quote blocks, we must render these first
		$this->_find_nested();

		// Find all of the inline attach tags in this message
		// [attachimg=xx] [attach=xx] [attachurl=xx] [attachmini=xx] [attach] or
		// some malformed ones like [attachIMG = "xx"]
		// ila_tags[0] will hold the entire tag [1] will hold the attach type (before the ]) eg img=1
		$ila_tags = array();
		if (preg_match_all('~\[attach\s*?(.*?(?:".+?")?.*?|.*?)\][\r\n]?~i', $this->_message, $ila_tags))
		{
			// Load a simple array of elements.  We use it to keep track of attachment number usage in the message body
			$this->_attachments = !empty($this->_start_num) ? range(1, $this->_start_num) : range(1, isset($attachments[$this->_id_msg]) ? count($attachments[$this->_id_msg]) : 0);
			$ila_num = 0;

			// If they have no permissions to view attachments then we sub out the tag with the appropriate message
			if (!allowedTo('view_attachments', $this->_board))
			{
				$this->_message = preg_replace_callback('~\[attach\s*?(.*?(?:".+?")?.*?|.*?)\][\r\n]?~i',
				function() use($context, $txt) {
					if ($context['user']['is_guest'])
						return $txt['ila_forbidden_for_guest'];
					else
						return $txt['ila_nopermission'];
				},
				$this->_message);
			}
			else
			{
				// If we have attachments, and ILA tags then go through each ILA tag,
				// one by one, and resolve it back to the correct ELK attachment
				if (!empty($ila_tags) && ((count($this->_attachments_context) > 0) || (isset($_REQUEST['ila']))))
				{
					foreach ($ila_tags[1] as $id => $ila_replace)
					{
						$this->_message = $this->str_replace_once($ila_tags[0][$id], $this->parse_bbc_tag($ila_replace, $ila_num), $this->_message);
						$ila_num++;
					}
				}
				// We have tags in the message and no attachments, replace them with an failed message
				elseif (!empty($ila_tags))
				{
					// There are a few reasons why this can, and does, occur
					//
					// - The tags in the message but there is no attachments, perhaps the attachment did not upload correctly
					// - The user put the tag in wrong because they are rock dumb and did not read our fantastic help,
					// just kidding, really the help is not that good.
					// - They don't have permission to view attachments in that board or the admin has disable attachments
					foreach ($ila_tags[1] as $id => $ila_replace)
						$this->_message = $this->str_replace_once($ila_tags[0][$id], $txt['ila_invalid'], $this->_message);
				}
			}
		}

		// Keep track of what we have used inline so its not shown below
		$context['ila_dont_show_attach_below'][$this->_id_msg] = $this->_dont_show_attach_below;

		return $this->_message;
	}

	/**
	 * parse_bbc_tag()
	 *
	 * - Breaks up the components of the [attach tag getting id, width, align
	 * - Fixes some common usage errors
	 *
	 * @param string $data
	 * @param int $ila_num
	 * @return string
	 */
	private function parse_bbc_tag($data, $ila_num)
	{
		$this->_curr_tag = array('id' => '', 'type' => '', 'align' => '', 'width' => '');
		$data = trim($data);

		// Find the align tag, save its value and remove it from the data string
		$matches = array();
		if (preg_match('~align\s{0,1}=(?:&quot;)?(right|left|center)(?:&quot;)?~i', $data, $matches))
		{
			$this->_curr_tag['align'] = strtolower($matches[1]);
			$data = str_replace($matches[0], '', $data);
		}

		// Find the width tag, save its value and remove it from the data string
		if (preg_match('~width\s{0,1}=(?:&quot;)?(\d+)(?:&quot;)?~i', $data, $matches))
		{
			$this->_curr_tag['width'] = strtolower($matches[1]);
			$data = str_replace($matches[0], '', $data);
		}

		// All that should be left is the id and tag, split on = to see what we have
		$temp = array();
		$result = preg_match('~(.*?)=(\d+).*~', $data, $temp);
		if ($result && $temp[1] != '')
		{
			// One of img=1 thumb=1 mini=1 url=1, we hope ;)
			$this->_curr_tag['id'] = isset($temp[2]) ? trim($temp[2]) : '';
			$this->_curr_tag['type'] = $temp[1];
		}
		else
		{
			// Nothing but a =x, or =x and wrong tags, or even perhaps nothing at all since we support that to!
			$this->_curr_tag['id'] = isset($temp[2]) ? trim($temp[2]) : '';
			$this->_curr_tag['type'] = 'none';
		}

		// Lets help the kids out by fixing some common errors in usage, I mean did they read the super great help?
		// like attach=#1 -> attach=1
		$this->_curr_tag['id'] = str_replace('#', '', $this->_curr_tag['id']);

		// like [attach] -> attach=1 by assuming attachments are sequentially placed in the
		// topic and sub in the attachment index increment
		if (!is_numeric($this->_curr_tag['id']))
		{
			// Take the first un-used attach number and use it
			$this->_curr_tag['id'] = array_shift($this->_attachments);

			// Stick it back on the end in case we need to loop around
			array_push($this->_attachments, $this->_curr_tag['id']);
		}
		// Standard =x, Remove this from the [attach] choice since we have used it
		else
			$this->_attachments = array_diff($this->_attachments, array($this->_curr_tag['id']));

		// Replace this tag with the inlined attachment
		$result = $this->showInline($ila_num);

		return !empty($result) ? $result : '[attach' . $data . ']';
	}

	/**
	 * ila_find_nested()
	 *
	 * - Does [attach replacements in quotes and nested quotes
	 * - Look for quote blocks with ila attach tags and builds the link.
	 * - Replaces ila attach tags in quotes with a link back to the post with the attachment
	 * - Prevents ILA from firing on those attach tags should we have a quote block with an
	 * attach placed in a message with an attach
	 *
	 * - Is painfully complicated as is, should consider other approaches me thinks
	 */
	private function _find_nested()
	{
		global $context, $txt, $scripturl;

		// Regexs to search the message for quotes, nested quotes and quoted text, and tags
		$regex = array();
		$regex['quotelinks'] = '~<div\b[^>]*class="quoteheader">(?:.*?)</div>~si';
		$regex['ila'] = '~\[attach\s*?(.*?(?:".+?")?.*?|.*?)\][\r\n]?~i';

		// Break up the quotes on the endtags, this way we will get *all* the needed text
		$quotes = preg_split('~(.*?</blockquote>)~si', $this->_message, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		// The last one is junk, strip it off ...
		array_pop($quotes);

		// Init
		$quote_count = count($quotes);
		$loop = $quote_count;
		$start = 0;

		// Loop through the quote array
		while ($quote_count > 0 && $loop > 0)
		{
			//  Get all the quoteheaders, they contain the links (or not) of the message that was quoted,
			//  each link represents a quoteblock
			$blockquote_count = preg_match_all($regex['quotelinks'], $quotes[$start], $links, PREG_SET_ORDER);
			$quote_count = $quote_count - $blockquote_count;

			// $quote_count will control the while, but belt and suspenders here we keep a
			// loop count to stop any potential run away, don't trust the data !
			$loop -= 1;

			// If this has blockquotes, we have work to do, we will have a nesting level of blockquote_count
			if (!empty($blockquote_count))
			{
				// Flip the array, quotes are outside to inside and links are inside to outside,
				// its a nesting thing to mess with your mind.
				$links = array_reverse($links);

				// Scrape off anything ahead of a leading quoteheader ... its regular message text,
				// likely between quoted zones
				$temp = array();
				if ((strpos($quotes[$start], '<div class="quoteheader">') != 0) && (preg_match('~.*(<div class="quoteheader">.*)~si', $quotes[$start], $temp)))
					$quotes[$start] = $temp[1];

				// Set the end of the link/quote array look ahead
				$end = $start + $blockquote_count - 1;
				$which_link = 0;

				// This quote block runs from array elements $start to $end
				for ($i = $start; $i <= $end; $i++)
				{
					// Search the link to get the msg_id
					$href_temp = array();
					if (preg_match('~<a href="(?:.*)#(.*?)">~i', $links[$which_link][0], $href_temp) == 1)
						$quoted_msg_id = $href_temp[1];
					// We either found the quoted msg id above or we did not, yes profound I know ....
					// if none set the link to the first message of the thread.
					else
						$quoted_msg_id = isset($context['topic_first_message']) ? $context['topic_first_message'] : '';

					// Build the link, we will replace any quoted ILA tags with this bad boy
					if (!empty($quoted_msg_id))
					{
						if (!isset($context['current_topic']))
							list($quote_topic, ) = $this->_get_topic(str_replace('msg', '', $quoted_msg_id));
						else
							$quote_topic = $context['current_topic'];

						$linktoquotedmsg = '
							<a href="' . $scripturl . '/topic,' . $quote_topic . '.' . $quoted_msg_id . '.html#' . $quoted_msg_id . '">
								' . $txt['ila_quote_link'] . '
							</a>';
					}
					else
						$linktoquotedmsg = $txt['ila_quote_nolink'];

					// The link back is the same for all the ila tags in an individual quoteblock
					// (they all point back to the same message)
					$ila_tags = array();
					if (preg_match_all($regex['ila'], $quotes[$i], $ila_tags))
					{
						// We have found an ila tag, in this quoted message section
						$ila_string = $quotes[$i];

						// Replace the ila attach tag with the link back to the message that was quoted
						foreach ($ila_tags[0] as $id => $ila_replace)
							$ila_string = $this->str_replace_once($ila_replace, $linktoquotedmsg, $ila_string);

						// At last the final step, sub in the attachment link
						$this->_message = str_replace($quotes[$i], $ila_string, $this->_message);
					}

					$which_link++;
				}

				$start += $blockquote_count;
			}
		}

		return;
	}

	/**
	 * showInline()
	 *
	 * - Does the actual replacement of the [attach tag with the img tag
	 *
	 * @param int $ila_num
	 */
	private function showInline($ila_num)
	{
		global $txt, $modSettings;

		$images = array('none', 'img', 'thumb');

		// Find the text of the attachment being referred to
		$this->_attachment = isset($this->_attachments_context[$this->_curr_tag['id'] - 1]) ? $this->_attachments_context[$this->_curr_tag['id'] - 1] : '';

		// We found an attachment that matches our attach id in the message
		if ($this->_attachment != '')
		{
			// We need a unique css id for javascript to find the correct image, cant just use the
			// attach id since we allow the users to use the same attachment many times in the same post.
			$uniqueID = $this->_attachment['id'] . '-' . $ila_num;

			if ($this->_attachment['is_image'])
			{
				// Make sure we have the javascript call set
				if (!isset($this->_attachment['thumbnail']['javascript']))
				{
					if (((!empty($modSettings['max_image_width']) && $this->_attachment['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $this->_attachment['real_height'] > $modSettings['max_image_height'])))
					{
						if (isset($this->_attachment['width']) && isset($this->_attachment['height']))
							$this->_attachment['thumbnail']['javascript'] = 'return reqWin(\'' . $this->_attachment['href'] . ';image\', ' . ($this->_attachment['width'] + 20) . ', ' . ($this->_attachment['height'] + 20) . ', true);';
						else
							$this->_attachment['thumbnail']['javascript'] = 'return expandThumb(\'' . $this->_attachment['href'] . '\');';
					}
					else
						$this->_attachment['thumbnail']['javascript'] = 'return expandThumb(\'' . $uniqueID . '\');';
				}

				// Set up our private js call if needed
				if (!empty($this->_attachment['thumbnail']['has_thumb']))
				{
					// If the image is too large to show inline, make it a popup window.
					if (((!empty($modSettings['max_image_width']) && $this->_attachment['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $this->_attachment['real_height'] > $modSettings['max_image_height'])))
						$this->_attachment['thumbnail']['javascript'] .= '';
					else
						$this->_attachment['thumbnail']['javascript'] = 'return expandThumb(\'' . $uniqueID . '\');';
				}
			}

			// Can't show an image for a non image attachment
			if ((!$this->_attachment['is_image']) && (in_array($this->_curr_tag['type'], $images)))
				$this->_curr_tag['type'] = 'url';

			// Create the image tag based off the type given
			$inlinedtext = $this->build_img_tag($uniqueID);

			// Handle the align tag if it was supplied.
			if ($this->_curr_tag['align'] == 'left' || $this->_curr_tag['align'] == 'right' || $this->_curr_tag['align'] == 'center')
				$inlinedtext = '<div class="ila_align_' . $this->_curr_tag['align'] . '">' . $inlinedtext . '</div>';

			// Keep track of the attachments we have in-lined so we can exclude them from being displayed in the post footers
			$this->_dont_show_attach_below[$this->_attachment['id']] = 1;
		}
		else
		{
			// Couldn't find the attachment specified
			// - they may have specified it wrong
			// - or they don't have permissions for attachments
			// - or they are replying to a message and this is in a quote, code or other type of tag
			// - or it has not been uploaded yet because they are previewing a new message,
			// - or they are modifying a message and added new attachments and hit preview
			// .... simple huh?
			if (allowedTo('view_attachments'))
			{
				// Check to see if the preview flag, via attach number, is set, if so try to render a preview ILA
				if (isset($this->_new_msg_preview[$this->_curr_tag['id']]))
					$inlinedtext = $this->preview_inline($this->_new_msg_preview[$this->_curr_tag['id']], $this->_curr_tag['type'], $this->_curr_tag['id'], $this->_curr_tag['align'], $this->_curr_tag['width']);
				else
					$inlinedtext = $txt['ila_attachment_missing'];
			}
			else
				$inlinedtext = $txt['ila_forbidden_for_guest'];
		}

		return $inlinedtext;
	}

	/**
	 * Builds the actual tag that will be inserted in place of the ILA tag
	 *
	 * @param string $uniqueID
	 * @return string
	 */
	private function build_img_tag($uniqueID)
	{
		global $txt, $context, $modSettings, $settings;

		$inlinedtext = '';
		$fb_link = 'rel="gallery_msg_' . $this->_id_msg . '_footer"';

		switch ($this->_curr_tag['type'])
		{
			// [attachimg=xx -- full sized image type=img
			case 'img':
				// Make sure the width its not bigger than the actual image or bigger than allowed by the admin
				if ($this->_curr_tag['width'] != '')
					$this->_curr_tag['width'] = !empty($modSettings['max_image_width']) ? min($this->_curr_tag['width'], $this->_attachment['real_width'], $modSettings['max_image_width']) : min($this->_curr_tag['width'], $this->_attachment['real_width']);
				else
					$this->_curr_tag['width'] = !empty($modSettings['max_image_width']) ? min($this->_attachment['real_width'], $modSettings['max_image_width']) : $this->_attachment['real_width'];

				$ila_title = isset($context['subject']) ? $context['subject'] : (isset($this->_attachment['name']) ? $this->_attachment['name'] : '');

				// Insert the correct image tag, clickable or just a full image
				if ($this->_curr_tag['width'] < $this->_attachment['real_width'])
					$inlinedtext = '
						<a href="' . $this->_attachment['href'] . ';image" id="link_' . $uniqueID . '" ' . $fb_link . ' onclick="' . $this->_attachment['thumbnail']['javascript'] . '">
							<img src="' . $this->_attachment['href'] . ';image" alt="' . $uniqueID . '" title="' . $ila_title . '" id="thumb_' . $uniqueID . '" style="width:' . $this->_curr_tag['width'] . 'px;" />
						</a>';
				else
					$inlinedtext = '<img src="' . $this->_attachment['href'] . ';image" alt="" title="' . $ila_title . '" id="thumb_' . $uniqueID . '" style="width:' . $this->_curr_tag['width'] . 'px;" />';
				break;
			// [attach=xx] or [attach]
			case 'none':
				// If a thumbnail is available use it, if not create an html one and use it
				if ($this->_curr_tag['width'] != '' && $this->_attachment['thumbnail']['has_thumb'])
					$this->_curr_tag['width'] = min($this->_curr_tag['width'], isset($this->_attachment['real_width']) ? $this->_attachment['real_width'] : (isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160));
				elseif ($this->_attachment['thumbnail']['has_thumb'])
					$this->_curr_tag['width'] = isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160;
				elseif ($this->_curr_tag['width'] != '')
					$this->_curr_tag['width'] = min($this->_curr_tag['width'], isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160, $this->_attachment['real_width']);
				else
					$this->_curr_tag['width'] = min(isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160, $this->_attachment['real_width']);

				$ila_title = isset($context['subject']) ? $context['subject'] : (isset($this->_attachment['name']) ? $this->_attachment['name'] : '');

				// Now with the width defined insert the thumbnail if available or create an html resized one
				if ($this->_attachment['thumbnail']['has_thumb'])
					$inlinedtext = '
						<a href="' . $this->_attachment['href'] . ';image" id="link_' . $uniqueID . '" ' . $fb_link . ' onclick="' . $this->_attachment['thumbnail']['javascript'] . '">
							<img src="' . $this->_attachment['thumbnail']['href'] . '" alt="' . $uniqueID . '" title="' . $ila_title . '" id="thumb_' . $uniqueID . '"  style="width:' . $this->_curr_tag['width'] . 'px;" />
						</a>';
				else
					$inlinedtext = $this->create_html_thumb($uniqueID);
				break;
			// [attachurl=xx] -- no image, just a link with size/view details type = url
			case 'url':
				$inlinedtext = '
					<a href="' . $this->_attachment['href'] . '">
						<i class="icon i-paperclip"></i>' . $this->_attachment['name'] . '
					</a> (' . $this->_attachment['size'] . ($this->_attachment['is_image'] ? ', ' . $this->_attachment['real_width'] . 'x' . $this->_attachment['real_height'] . ' - ' . sprintf($txt['attach_viewed'], $this->_attachment['downloads']) : ' ' . sprintf($txt['attach_downloaded'], $this->_attachment['downloads'])) . ')';
				break;
			// [attachmini=xx] -- just a plain link type = mini
			case 'mini':
				$inlinedtext = '
					<a href="' . $this->_attachment['href'] . '">
						<i class="icon i-paperclip"></i>' . $this->_attachment['name'] . '
					</a>';
				break;
		}

		return $inlinedtext;
	}

	/**
	 * ila_createfakethumb()
	 *
	 * - Creates the html sized thumbnail if none exists
	 *
	 * @param int $uniqueID
	 */
	private function create_html_thumb($uniqueID)
	{
		global $modSettings, $context;

		// Get the attachment size
		$src_width = $this->_attachment['real_width'];
		$src_height = $this->_attachment['real_height'];

		// Set thumbnail limits
		$max_width = $this->_curr_tag['width'];
		$max_height = min(isset($modSettings['attachmentThumbHeight']) ? $modSettings['attachmentThumbHeight'] : 120, $this->_curr_tag['width']);

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if ($src_height * $max_width / $src_width <= $max_height)
		{
			$dst_height = floor($src_height * $max_width / $src_width);
			$dst_width = $max_width;
		}
		else
		{
			$dst_width = floor($src_width * $max_height / $src_height);
			$dst_height = $max_height;
		}

		// Don't show a link if we can't resize or if we were asked not to
		$ila_title = isset($context['subject']) ? $context['subject'] : (isset($this->_attachment['name']) ? $this->_attachment['name'] : '');

		// Build the replacement string
		if ($dst_width < $src_width || $dst_height < $src_height)
			$inlinedtext = '
				<a href="' . $this->_attachment['href'] . ';image" id="link_' . $uniqueID . '" onclick="return expandThumb(\'' . $uniqueID . '\');">
					<img src="' . $this->_attachment['href'] . '" alt="' . $uniqueID . '" title="' . $ila_title . '" style="width:' . $dst_width . 'px; height:' . $dst_height . ';" id="thumb_' . $uniqueID . '" />
				</a>';
		else
			$inlinedtext = '
				<img src="' . $this->_attachment['href'] . ';image" alt="" title="' . $ila_title . '" border="0" />';

		return $inlinedtext;
	}

	/**
	 * preview_inline()
	 *
	 * Renders a preview box for attachments that have not been uploaded, used in preview message
	 *
	 * @param string $attachname
	 * @param string $type
	 * @param int $id
	 * @param string $align
	 * @param int $width
	 */
	private function preview_inline($attachname, $type, $id, $align, $width)
	{
		global $txt, $modSettings;

		// We are trying to preview a message but the attachments have not been uploaded,
		// lets sub in a fake image box with our ILA text so the user can check things are
		// positioned correctly even if they cant yet see the image
		$inlinedtext = '';
		$txt_name = 'ila_' . $type;

		// Decide how to do our fake preview based on the type
		switch ($type)
		{
			// [attachimg=xx -- full sized image type=img
			case 'img':
				if ($width != '')
					$width = !empty($modSettings['max_image_width']) ? min($width, $modSettings['max_image_width']) : $width;
				else
					$width = !empty($modSettings['max_image_width']) ? min($modSettings['max_image_width'], 400) : 160;
				$inlinedtext = '<div class="ila_preview" style="width:' . $width . 'px; height:' . floor($width / 1.333) . 'px;">[Attachment:' . $id . ': <strong>' . $attachname . '</strong> ' . $txt[$txt_name] . ']</div>';
				break;
			// [attach=xx] or depreciated [attachthumb=xx]-- thumbnail
			case 'none':
				if ($width != '')
					$width = min($width, isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160);
				else
					$width = isset($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 160;
				$inlinedtext = '<div class="ila_preview" style="width:' . $width . 'px; height:' . floor($width / 1.333) . 'px;">[Attachment:' . $id . ': <strong>' . $attachname . '</strong> ' . $txt[$txt_name] . ']</div>';
				break;
			// [attachurl=xx] -- no image, just a link with size/view details type = url
			case 'url':
				$inlinedtext = '[Attachment:' . $id . ': ' . $attachname . ' ' . $txt[$txt_name] . ']';
				break;
			// [attachmini=xx] -- just a plain link type = mini
			case 'mini':
				$inlinedtext = '[Attachment:' . $id . ': ' . $attachname . ' ' . $txt[$txt_name] . ']';
				break;
		}

		// Handle the align tag if it was supplied
		if ($align === 'left' || $align === 'right' || $align === 'center')
			$inlinedtext = '<div class="' . $align . '">' . $inlinedtext . '</div>';

		return $inlinedtext;
	}

	/**
	 * load_attachments()
	 *
	 * - Loads attachments for a given msg if they have not yet been loaded
	 * - Attachments must be enabled and user allowed to see attachments
	 */
	private function load_attachments()
	{
		global $modSettings;

		$msg_id = array($this->_id_msg);
		$attachments = array();

		// With a message id and the topic we can fetch the attachments
		if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments', $this->_board) && $this->_topic != -1)
			$attachments = getAttachments($msg_id, false, null, array(), $this->_attach_source);

		return $attachments;
	}

	/**
	 * ila_get_topic()
	 *
	 * - Get the topic and board for a given message number, needed to check permissions
	 * - Used to also get link details for quoted messages with attach tags in them
	 *
	 * @param int $msg_id
	 */
	private function _get_topic($msg_id)
	{
		$db = database();

		// Init
		$topic = -1;
		$board = null;

		// No message is complete without a topic and board, its like bread, peanut butter and jelly
		if (!empty($msg_id))
		{
			$request = $db->query('', '
				SELECT
					id_topic, id_board
				FROM {db_prefix}messages
				WHERE id_msg = {int:msg}
				LIMIT 1',
				array(
					'msg' => $msg_id,
				)
			);
			if ($db->num_rows($request) == 1)
				list($topic, $board) = $db->fetch_row($request);
			$db->free_result($request);
		}

		return array($topic, $board);
	}

	/**
	 * str_replace_once()
	 *
	 * - Looks for the first occurrence of $needle in $haystack and replaces it with $replace,
	 * this is a single replace
	 *
	 * @param string $needle
	 * @param string $replace
	 * @param string $haystack
	 */
	private function str_replace_once($needle, $replace, $haystack)
	{
		$pos = strpos($haystack, $needle);
		if ($pos === false)
		{
			// Nothing found
			return $haystack;
		}

		return substr_replace($haystack, $replace, $pos, strlen($needle));
	}

	/**
	 * ila_hide_bbc()
	 *
	 * Makes [attach tags invisible inside for certain bbc blocks like code, nobbc, etc
	 *
	 * @param string[] $hide_tags
	 */
	public function hide_bbc($hide_tags = array())
	{
		global $modSettings;

		// Not using BBC no need to do anything
		if (empty($modSettings['enableBBC']))
			return $this->_message;

		// If our ila attach tags are nested inside of these tags we need to hide them so they don't execute
		if (empty($hide_tags))
			$hide_tags = array('code', 'html', 'php', 'noembed', 'nobbc');

		// Look for each tag, if attach is found inside then replace its '[' with a hex
		// so parse bbc does not try to render them
		foreach ($hide_tags as $tag)
		{
			if (stripos($this->_message, '[' . $tag . ']') !== false)
			{
				$this->_message = preg_replace_callback('~\[' . $tag . ']((?>[^[]|\[(?!/?' . $tag . ']))+?)\[/' . $tag . ']~i',
				function($matches) use($tag) {return "[" . $tag . "]" . str_ireplace("[attach", "&#91;attach", $matches[1]) . "[/" . $tag . "]";},
				$this->_message);
			}
		}

		return $this->_message;
	}
}