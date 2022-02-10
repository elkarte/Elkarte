<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * The sidebar menu option. Used for all admin, moderation, profile and PM pages.
 */
function template_generic_menu_sidebar_above()
{
	global $context;

	// This is the main table - we need it so we can keep the content to the right of it.
	echo '
	<div id="main_container">
		<div id="menu_sidebar">';

	// What one are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 1;
	$menu_context = &$context['menu_data_' . $context['cur_menu_id']];

	// For every section that appears on the sidebar...
	foreach ($menu_context['sections'] as $section)
	{
		// Show the section header - and pump up the line spacing for readability.
		echo '
			<h2 class="category_header">', $section['label'], '</h2>
			<ul class="sidebar_menu no_js">';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		foreach ($section['areas'] as $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
			{
				continue;
			}

			echo '
				<li class="listlevel1', !empty($area['subsections']) ? ' subsections"  aria-haspopup="true"' : '"', '>
					<a class="linklevel1', !empty($area['selected']) ? ' chosen' : '', '" href="', $area['url'], '">', $area['label'], '</a>';

			// Are there any subsections?
			if (!empty($area['subsections']))
			{
				echo '
					<ul class="menulevel2">';

				foreach ($area['subsections'] as $sub)
				{
					if (!empty($sub['disabled']))
					{
						continue;
					}

					echo '
						<li class="listlevel2">
							<a class="linklevel2', !empty($sub['selected']) ? ' chosen' : '', '" href="', $sub['url'], '">', $sub['label'], '</a>
						</li>';
				}

				echo '
					</ul>';
			}

			echo '
				</li>';
		}

		echo '
			</ul>';
	}

	// This is where the actual "main content" area for the admin section starts.
	echo '
		</div>
		<div id="main_admsection">';

	// If there are any "tabs" setup, this is the place to shown them.
	if (empty($context['force_disable_tabs']) && !empty($menu_context['tab_data']))
	{
		template_generic_menu_tabs($menu_context['tab_data']);
	}
}

/**
 * Part of the sidebar layer - closes off the main bit.
 */
function template_generic_menu_sidebar_below()
{
	echo '
		</div>
	</div>';
}

/**
 * The drop menu option. Used for all admin, moderation, profile and PM pages.
 */
function template_generic_menu_dropdown_above()
{
	global $context;

	// Which menu are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 1;
	$menu_context = &$context['menu_data_' . $context['cur_menu_id']];

	echo '
				<nav id="generic_nav_', $context['cur_menu_id'], '">
					<ul class="admin_menu no_js" id="dropdown_menu_', $context['cur_menu_id'], '" role="menubar">';

	// Main areas first.
	foreach ($menu_context['sections'] as $section)
	{
		echo '
						<li class="listlevel1', !empty($section['areas']) ? ' subsections' : '', '" role="none">
							<a class="linklevel1', !empty($section['selected']) ? ' active' : '', '" href="', $section['url'], '" role="menuitem"', !empty($section['areas']) ? ' aria-haspopup="true"' : '', '>',
								$section['label'], '
							</a>
							<ul class="menulevel2" role="menu">';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		$section['areas'] = $section['areas'] ?? [];
		foreach ($section['areas'] as $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
			{
				continue;
			}

			echo '
								<li class="listlevel2', !empty($area['subsections']) ? ' subsections' : '', '" role="none">
									<a class="linklevel2', !empty($area['selected']) ? ' chosen' : '', '" href="', $area['url'], '"', !empty($area['subsections']) ? ' aria-haspopup="true"' : '', ' role="menuitem">',
										$area['icon'], $area['label'], '
									</a>';

			// Are there any subsections?
			if (!empty($area['subsections']))
			{
				echo '
									<ul class="menulevel3" role="menu">';

				foreach ($area['subsections'] as $sub)
				{
					if (!empty($sub['disabled']))
					{
						continue;
					}

					echo '
										<li class="listlevel3" role="none">
											<a class="linklevel3', !empty($sub['selected']) ? ' chosen ' : '', '" href="', $sub['url'], '" role="menuitem">',
												$sub['label'], '
											</a>
										</li>';
				}

				echo '
									</ul>';
			}

			echo '
								</li>';
		}

		echo '
							</ul>
						</li>';
	}

	echo '
					</ul>
				</nav>';

	// This is the main table - we need it so we can keep the content to the right of it.
	echo '
				<div id="admin_content">';

	// It's possible that some pages have their own tabs they wanna force...
	if (isset($menu_context['tab_data']))
	{
		template_generic_menu_tabs($menu_context['tab_data']);
	}
}

/**
 * Part of the admin layer - used with admin_above to close the table started in it.
 */
function template_generic_menu_dropdown_below()
{
	echo '
				</div>';
}

/**
 * Some code for showing a tabbed view.
 *
 * @param array $tab_context
 */
function template_generic_menu_tabs($tab_context)
{
	global $settings, $scripturl, $txt;

	if (!empty($tab_context['title']))
	{
		echo '
					<div class="category_header">
						<h3 class="floatleft">';

		// Show an icon and/or a help item?
		if (!empty($tab_context['class']))
		{
			echo '
						<span class="hdicon cat_img_', $tab_context['class'], '"></span>';
		}

		if (!empty($tab_context['help']))
		{
			echo '
						<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=', $tab_context['help'], '" onclick="return reqOverlayDiv(this.href);" label="', $txt['help'], '"></a>';
		}

		echo '
						', $tab_context['title'], '
						</h3>';

		// The function is in Admin.template.php, but since this template is used elsewhere,
		// we need to check if the function is available.
		if (function_exists('template_admin_quick_search'))
		{
			template_admin_quick_search();
		}

		echo '
					</div>';
	}

	if (!empty($tab_context['description']))
	{
		echo '
					<p class="description">
						', $tab_context['description'], '
					</p>';
	}

	// Print out all the items in this tab (if any).
	if (!empty($tab_context['tabs']))
	{
		// The admin tabs.
		echo '
					<ul id="adm_submenus">';

		foreach ($tab_context['tabs'] as $tab)
		{
			if (!empty($tab['disabled']) || empty($tab['url']))
			{
				continue;
			}

			echo '
						<li class="listlevel1">
							<a class="linklevel1', !empty($tab['selected']) ? ' active' : '', '" href="', $tab['url'], $tab['add_params'] ?? '', '">', $tab['label'], '</a>
						</li>';
		}

		// the end of tabs
		echo '
					</ul>';
	}
}
