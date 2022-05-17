<?php

/**
 * This class deals with the generation of Open Graph and Schema.org metadata / microdata
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class \ElkArte\MetadataIntegrate
 *
 * OG and Schema functions for creation of microdata
 */
class MetadataIntegrate
{
	/** @var array data from the post renderer */
	public $data;

	/** @var array attachment data from AttachmentsDisplay Controller */
	public $attachments;

	/** @var array ila data from AttachmentsDisplay Controller */
	public $ila;

	/**
	 * Register Metadata hooks to the system.
	 *
	 * @return array
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['metadata_enabled']))
		{
			return [];
		}

		// Simply load context with our data which will be consumed by the theme's index.template (if supported)
		return array(
			// Display
			array('integrate_action_display_after', '\\ElkArte\\MetadataIntegrate::prepare_topic_metadata'),
			// Board
			array('integrate_action_boardindex_after', '\\ElkArte\\MetadataIntegrate::prepare_basic_metadata'),
			// MessageIndex
			array('integrate_action_messageindex_after', '\\ElkArte\\MetadataIntegrate::prepare_basic_metadata'),
		);
	}

	/**
	 * Prepares Open Graph and Schema data for use in templates when viewing a specific topic
	 *
	 * - It will only generate full schema data when the pageindex of the topic is on page 1
	 *
	 * @param int $start
	 */
	public static function prepare_topic_metadata($start = -1)
	{
		global $context;

		$meta = new self();
		$start = $context['start'] ?? $start;

		// Load in the post data if available
		$meta->data = $meta->initPostData($start);
		$meta->attachments = $meta->data['attachments'] ?? [];
		$meta->ila = $meta->data['ila'] ?? [];

		// Set the data into context for template use
		$meta->setContext();
	}

	/**
	 * Prepares Open Graph and Schema data when viewing a message listing or the board index.
	 * Currently, this consists of a simple organizational card and OG with description
	 */
	public static function prepare_basic_metadata()
	{
		$meta = new self();

		// Set the data into context for template use
		$meta->setContext();
	}

	/*
	 * Set what we have created into context for template consumption.
	 *
	 * The schema data should be output in a template as
	 * <script type="application/ld+json">
	 * json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
	 * </script>
	 * OG data is an array of <meta> tags for implosion.
	 */
	private function setContext()
	{
		global $context;

		// Set the data into context for template use
		$context['smd_site'] = $this->getSiteSchema();
		$context['smd_article'] = $this->getPostSchema();
		$context['open_graph'] = $this->getOgData();
	}

	/**
	 * When viewing the first page of a topic, will return data from the first post
	 * to be used in creating microdata.
	 *
	 * Requires that display renderer, $context['get_message'], has been set via the Display Controller
	 *
	 * @param int $start
	 * @return array
	 */
	private function initPostData($start)
	{
		global $context, $topic;

		$smd = [];

		// If this is a topic, and we are on the first page (so we can get first post data)
		if (!empty($topic)
			&& $start === 0
			&& (!empty($context['get_message'][0]) && is_object($context['get_message'][0])))
		{

			// Grab the first post of the thread
			$controller = $context['get_message'][0];
			$smd = $controller->{$context['get_message'][1]}();

			// Tell the template to reset, or it will miss the first post!
			$context['reset_renderer'] = true;

			// Create a short body, leaving some very basic html
			$smd['raw_body'] = trim(strip_tags($smd['body']));
			$smd['html_body'] = trim(strip_tags($smd['body'], '<br><strong><em><blockquote>'));

			// Strip attributes from any remaining tags
			$smd['html_body'] = preg_replace('~<([bse][a-z0-9]*)[^>]*?(/?)>~i', '<$1$2>', $smd['html_body']);
			$smd['html_body'] = Util::shorten_html($smd['html_body'], 375);

			// Create a short plain text description
			$description = empty($context['description'])
				? preg_replace('~\s\s+|&nbsp;|&quot;|&#039;~', ' ', $smd['raw_body'])
				: $context['description'];
			$smd['description'] = Util::shorten_text($description, 110, true);
		}

		return $smd;
	}

	/**
	 * Build and return the schema business card
	 *
	 * @return array
	 */
	public function getSiteSchema()
	{
		global $context, $boardurl, $mbname, $settings;

		// Snag us a site logo
		$logo = $this->getLogo();

		$slogan = $settings['site_slogan'] ??  un_htmlspecialchars($mbname);

		// The sites organizational card
		return array(
			'@context' => 'https://schema.org',
			'@type' => 'Organization',
			'url' => !empty($context['canonical_url']) ? $context['canonical_url'] : $boardurl,
			'logo' => array(
				'@type' => 'ImageObject',
				'url' => $logo[2],
				'width' => $logo[0],
				'height' => $logo[1],
			),
			'name' => un_htmlspecialchars($context['forum_name']),
			'slogan' => $slogan,
		);
	}

	/**
	 * Function to return the sites logo url
	 *
	 * @return array width, height and html safe logo url
	 */
	private function getLogo()
	{
		global $context, $boardurl;

		// Set in ThemeLoader
		if (!empty($context['header_logo_url_html_safe']))
		{
			$logo = $context['header_logo_url_html_safe'];
		}
		else
		{
			$logo = $boardurl . '/mobile.png';
		}

		// This will also cache these values for us
		require_once(SUBSDIR . '/Attachments.subs.php');
		list($width, $height) = url_image_size(un_htmlspecialchars($logo));

		return [$width, $height, $logo];
	}

	/**
	 * Build and return the article schema.  This is intended for use when displaying
	 * a topic.
	 *
	 * @return array
	 */
	public function getPostSchema()
	{
		global $context, $boardurl, $mbname, $board_info;

		$smd = [];

		if (empty($this->data))
		{
			return $smd;
		}

		$logo = $this->getLogo();
		$smd = [
			'@context' => 'https://schema.org',
			'@type' => 'DiscussionForumPosting',
			'@id' => $this->data['href'],
			'headline' => $this->getPageTitle(),
			'author' => [
				'@type' => 'Person',
				'name' => $this->data['member']['name'],
			],
			'url' => $this->data['href'],
			'articleBody' => $this->data['html_body'],
			'articleSection' => $board_info['name'] ?? '',
			'datePublished' => $this->data['time'],
			'dateModified' => !empty($this->data['modified']['name']) ? $this->data['modified']['time'] : $this->data['time'],
			'interactionStatistic' => [
				'@type' => 'InteractionCounter',
				'interactionType' => 'https://schema.org/ReplyAction',
				'userInteractionCount' => !empty($context['real_num_replies']) ? $context['real_num_replies'] : 0,
			],
			'wordCount' => str_word_count($this->data['raw_body']),
			'publisher' => [
				'@type' => 'Organization',
				'name' => un_htmlspecialchars($mbname),
				'logo' => [
					'@type' => 'ImageObject',
					'url' => $logo[2],
					'width' => $logo[0],
					'height' => $logo[1],
				],
			],
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id' => !empty($context['canonical_url']) ? $context['canonical_url'] : $boardurl,
			],
		];

		// If the post has any attachments, set an ImageObject
		$image = $this->getAttachment();
		if (!empty($image))
		{
			$smd['image'] = $image;
		}

		return $smd;
	}

	/**
	 * Checks the post for any attachments to use as an image.  Will use the
	 * first below post attachment, failing that the first ILA, failing that nothing
	 *
	 * @return array
	 */
	private function getAttachment()
	{
		global $boardurl;

		if (empty($this->data))
		{
			return [];
		}

		// If there are below post attachments, use the first one that is an image
		if (!empty($this->data['attachment']))
		{
			foreach ($this->data['attachment'] as $attachment)
			{
				if (isset($attachment['is_image']))
				{
					return [
						'@type' => 'ImageObject',
						'url' => $attachment['href'],
						'width' => $attachment['real_width'] ?? 0,
						'height' => $attachment['real_height'] ?? 0
					];
				}
			}
		}

		// Maybe it has an inline image?
		if (!empty($this->data['ila']))
		{
			foreach ($this->data['ila'] as $ila)
			{
				if (isset($ila['is_image']))
				{
					return [
						'@type' => 'ImageObject',
						'url' => $boardurl . '/index.php?action=dlattach;attach=' . $ila['id'] . ';image',
						'width' => $ila['width'],
						'height' => $ila['height']
					];
				}
			}
		}

		return [];
	}

	/**
	 * Function to provide backup page name if none is defined
	 *
	 * @param string $description
	 * @return string html safe title
	 */
	private function getPageTitle($description = '')
	{
		global $context;

		// As long as you are calling this class from the right area, this will be set
		if (!empty($context['page_title']))
		{
			return Util::shorten_text(Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])), 110, true);
		}

		// Otherwise, do the best we can
		$description = empty($description) ? $this->getDescription() : $description;

		return Util::shorten_text(Util::htmlspecialchars(un_htmlspecialchars($description)), 110, true);
	}

	/**
	 * Prepares the description for use in Metadata
	 *
	 * This is typically already generated and is one of
	 * - The board description, set in MessageIndex Controller
	 * - The topic description, set in Display Controller
	 *
	 * Failing that will use one of
	 * - The page title
	 * - The site slogan
	 * - The site name
	 *
	 * @return string html safe description
	 */
	private function getDescription()
	{
		global $context, $settings, $mbname;

		// Supplied one, simply use it.
		if (!empty($context['description']))
		{
			return $context['description'];
		}

		// Build out a default that makes some sense
		if (!empty($this->data['description']))
		{
			$description = $this->data['description'];
		}
		else
		{
			$sitename = un_htmlspecialchars($mbname);

			// Avoid if possible a description like sitename - Index
			if (isset($context['page_title']) && strpos($context['page_title'], $sitename) === 0)
			{
				$description = $settings['site_slogan'] ?? $context['page_title'];
			}
			else
			{
				$description = $context['page_title'] ?? $settings['site_slogan'] ?? $sitename;
			}
		}

		return Util::htmlspecialchars($description);
	}

	/**
	 * Basic OG Metadata to insert in to the <head></head> element.  See https://ogp.me
	 *
	 * This will generate *basic* og metadata, suitable for FB/Meta website/post sharing.
	 *
	 * og:title - The title of your article without any branding (site name)
	 * og:type - The type of your object, e.g., "website".
	 * og:image - The URL of the image that appears when someone shares the content
	 * og:url - The canonical URL of your page.
	 * og:site_name - The name which should be displayed for the overall site.
	 * og:description - A brief description of the content, usually between 2 and 4 sentences.
	 *
	 * @return array
	 */
	public function getOgData()
	{
		global $context, $boardurl, $mbname, $topic;

		$description = strip_tags($this->getDescription());
		$page_title = $this->getPageTitle();
		$logo = $this->getLogo();
		$attach = $this->getAttachment();

		// If on a post page, with attachments, use it vs a site logo
		if (isset($attach['url']))
		{
			$logo[2] = $attach['url'];
			$logo[1] = $attach['height'];
			$logo[0] = $attach['width'];
		}

		$metaOg = [];
		$metaOg['title'] = '<meta property="og:title" content="' . $page_title . '" />';
		$metaOg['type'] = '<meta property="og:type" content="' . (!empty($topic) ? 'article' : 'website') . '" />';
		$metaOg['url'] = '<meta property="og:url" content="' . (!empty($context['canonical_url']) ? $context['canonical_url'] : $boardurl) . '" />';
		$metaOg['image'] = '<meta property="og:image" content="' . $logo[2] . '" />';
		$metaOg['image_width'] = '<meta property="og:image:width" content="' . $logo[0] . '" />';
		$metaOg['image_height'] = '<meta property="og:image:height" content="' . $logo[1] . '" />';
		$metaOg['sitename'] = '<meta property="og:site_name" content="' . Util::htmlspecialchars($mbname) . '" />';
		$metaOg['description'] = '<meta property="og:description" content="' . $description . '" />';

		return $metaOg;
	}
}
