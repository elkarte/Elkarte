<?php

/**
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 *
 */

namespace BBC;

class HtmlParser
{
    protected $empty_tags = array('br', 'hr');
    protected $closable_tags = array('b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote');

    public function __construct()
    {
        // Commented only for testing purposes
        //require_once(SUBSDIR . '/Attachments.subs.php');

        $empty_tags = $this->empty_tags;
        $closable_tags = $this->closable_tags;
        call_integration_hook('integrate_html_parser_load', array(&$empty_tags, &$closable_tags));
        $this->empty_tags = $empty_tags;
        $this->closable_tags = $closable_tags;
    }

    public function parse(&$data)
    {
        $this->anchorTags($data);

        $this->emptyTags($data);

        $this->closableTags($data);

        $this->imageTags($data);
    }

    public function setImageWidthHeight($width, $height)
    {
        $this->image_width = (int) $width;
        $this->image_height = (int) $height;

        return $this;
    }

    protected function anchorTags(&$data)
    {
        // Changes <a href=... to [url=
        $data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|mailto:)\S+?)\\1&gt;~i', '[url=$2]', $data);
        $data = preg_replace('~&lt;/a&gt;~i', '[/url]', $data);
    }

    protected function emptyTags(&$data)
    {
        // <br /> should be empty.
        foreach ($this->empty_tags as $tag)
        {
            $data = str_replace(array('&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'), '[' . $tag . ' /]', $data);
        }
    }

    protected function closableTags(&$data)
    {
        foreach ($this->closable_tags as $tag)
        {
            $diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
            $data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

            if ($diff > 0)
            {
                $data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
            }
        }
    }

    protected function imageTags(&$data)
    {
        global $modSettings;

        // Do <img ... /> - with security... action= -> action-.
        preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);
        if (!empty($matches[0]))
        {
            $replaces = array();
            foreach ($matches[2] as $match => $imgtag)
            {
                $alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

                // Remove action= from the URL - no funny business, now.
                if (preg_match('~action(=|%3d)(?!dlattach)~i', $imgtag) !== 0)
                {
                    $imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);
                }

                // Check if the image is larger than allowed.
                // @todo - We should seriously look at deprecating some of this in favour of CSS resizing.
                if (!empty($modSettings['max_image_width']) && !empty($modSettings['max_image_height']))
                {
                    // For images, we'll want this
                    list ($width, $height) = url_image_size($imgtag);

                    if (!empty($modSettings['max_image_width']) && $width > $modSettings['max_image_width'])
                    {
                        $height = (int) (($modSettings['max_image_width'] * $height) / $width);
                        $width = $modSettings['max_image_width'];
                    }

                    if (!empty($modSettings['max_image_height']) && $height > $modSettings['max_image_height'])
                    {
                        $width = (int) (($modSettings['max_image_height'] * $width) / $height);
                        $height = $modSettings['max_image_height'];
                    }

                    // Set the new image tag.
                    $replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
                }
                else
                    $replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
            }

            $data = strtr($data, $replaces);
        }
    }
}