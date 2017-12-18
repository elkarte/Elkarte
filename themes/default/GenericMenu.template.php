<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0-dev
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
			<h2 class="category_header">', $section['title'], '</h2>
			<ul class="sidebar_menu">';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		foreach ($section['areas'] as $i => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
				continue;

			echo '
				<li class="listlevel1', !empty($area['subsections']) ? ' subsections"  aria-haspopup="true"' : '"', ' ', ($i == $menu_context['current_area']) ? 'id="menu_current_area"' : '', '>';

			// Is this the current area, or just some area?
			if ($i == $menu_context['current_area'])
			{
				echo '
					<strong><a class="linklevel1" href="', isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i, $menu_context['extra_parameters'], '">', $area['label'], '</a></strong>';

				if (empty($context['tabs']))
					$context['tabs'] = isset($area['subsections']) ? $area['subsections'] : array();
			}
			else
				echo '
					<a class="linklevel1" href="', isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i, $menu_context['extra_parameters'], '">', $area['label'], '</a>';

			// Are there any subsections?
			if (!empty($area['subsections']))
			{
				echo '
					<ul class="menulevel2">';

				foreach ($area['subsections'] as $sa => $sub)
				{
					if (!empty($sub['disabled']))
						continue;

					$url = isset($sub['url']) ? $sub['url'] : (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i) . ';sa=' . $sa;

					echo '
						<li class="listlevel2">
							<a class="linklevel2', !empty($sub['selected']) ? ' chosen' : '', '" href="', $url, $menu_context['extra_parameters'], '">', $sub['label'], '</a>
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
	if (empty($context['force_disable_tabs']))
		template_generic_menu_tabs($menu_context);
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
				<ul class="admin_menu" id="dropdown_menu_', $context['cur_menu_id'], '">';

	// Main areas first.
	foreach ($menu_context['sections'] as $section)
	{
		echo '
					<li class="listlevel1', !empty($section['areas']) ? ' subsections" aria-haspopup="true"' : '"', '>
						<a class="linklevel1', !empty($section['selected']) ? ' active' : '', '" href="', $section['url'], $menu_context['extra_parameters'], '">', $section['title'], '</a>
						<ul class="menulevel2">';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		// @todo Code for additional_items class was deprecated and has been removed. Suggest following up in Sources if required.
		foreach ($section['areas'] as $i => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
				continue;

			echo '
							<li class="listlevel2', !empty($area['subsections']) ? ' subsections" aria-haspopup="true"' : '"', '>';

			echo '
								<a class="linklevel2', !empty($area['selected']) ? ' chosen' : '', '" href="', (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i), $menu_context['extra_parameters'], '">', $area['icon'], $area['label'], '</a>';

			// Is this the current area, or just some area?
			if (!empty($area['selected']) && empty($context['tabs']))
				$context['tabs'] = isset($area['subsections']) ? $area['subsections'] : array();

			// Are there any subsections?
			if (!empty($area['subsections']))
			{
				echo '
								<ul class="menulevel3">';

				foreach ($area['subsections'] as $sa => $sub)
				{
					if (!empty($sub['disabled']))
						continue;

					$url = isset($sub['url']) ? $sub['url'] : (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i) . ';sa=' . $sa;

					echo '
									<li class="listlevel3">
										<a class="linklevel3', !empty($sub['selected']) ? ' chosen ' : '', '" href="', $url, $menu_context['extra_parameters'], '">', $sub['label'], '</a>
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
				</ul>';

	// This is the main table - we need it so we can keep the content to the right of it.
	echo '
				<div id="admin_content">';

	// It's possible that some pages have their own tabs they wanna force...
	template_generic_menu_tabs($menu_context);
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
 * @param integer $menu_context
 */
function template_generic_menu_tabs(&$menu_context)
{
	global $context, $settings, $scripturl, $txt;

	// Handy shortcut.
	$tab_context = &$menu_context['tab_data'];

	if (!empty($tab_context['title']))
	{
		echo '
					<div class="category_header">';

		// Exactly how many tabs do we have?
		if (!empty($context['tabs']))
		{
			foreach ($context['tabs'] as $id => $tab)
			{
				// Can this not be accessed?
				if (!empty($tab['disabled']))
				{
					$tab_context['tabs'][$id]['disabled'] = true;
					continue;
				}

				// Did this not even exist - or do we not have a label?
				if (!isset($tab_context['tabs'][$id]))
					$tab_context['tabs'][$id] = array('label' => $tab['label']);
				elseif (!isset($tab_context['tabs'][$id]['label']))
					$tab_context['tabs'][$id]['label'] = $tab['label'];

				// Has a custom URL defined in the main admin structure?
				if (isset($tab['url']) && !isset($tab_context['tabs'][$id]['url']))
					$tab_context['tabs'][$id]['url'] = $tab['url'];

				// Any additional parameters for the url?
				if (isset($tab['add_params']) && !isset($tab_context['tabs'][$id]['add_params']))
					$tab_context['tabs'][$id]['add_params'] = $tab['add_params'];

				// Has it been deemed selected?
				if (!empty($tab['is_selected']))
					$tab_context['tabs'][$id]['is_selected'] = true;

				// Does it have its own help?
				if (!empty($tab['help']))
					$tab_context['tabs'][$id]['help'] = $tab['help'];

				// Is this the last one?
				if (!empty($tab['is_last']) && !isset($tab_context['override_last']))
					$tab_context['tabs'][$id]['is_last'] = true;
			}

			// Find the selected tab
			foreach ($tab_context['tabs'] as $sa => $tab)
			{
				if (!empty($tab['is_selected']) || (isset($menu_context['current_subsection']) && $menu_context['current_subsection'] == $sa))
				{
					$selected_tab = $tab;
					$tab_context['tabs'][$sa]['is_selected'] = true;
				}
			}
		}

		echo '
						<h3 class="floatleft">';

		// Show an icon and/or a help item?
		if (!empty($selected_tab['icon']) || !empty($tab_context['icon']) || !empty($selected_tab['help']) || !empty($tab_context['help']) || !empty($selected_tab['class']) || !empty($tab_context['class']))
		{
			if (!empty($selected_tab['icon']) || !empty($tab_context['icon']))
				echo '
						<img src="', $settings['images_url'], '/icons/', !empty($selected_tab['icon']) ? $selected_tab['icon'] : $tab_context['icon'], '" alt="" class="icon" />';
			elseif (!empty($selected_tab['class']) || !empty($tab_context['class']))
				echo '
						<span class="hdicon cat_img_', !empty($selected_tab['class']) ? $selected_tab['class'] : $tab_context['class'], '"></span>';

			if (!empty($selected_tab['help']) || !empty($tab_context['help']))
				echo '
						<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=', !empty($selected_tab['help']) ? $selected_tab['help'] : $tab_context['help'], '" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a>';

			echo '
						', $tab_context['title'];
		}
		else
			echo '
						', $tab_context['title'];

		echo '
						</h3>';

		// The function is in Admin.template.php, but since this template is used elsewhere,
		// we need to check if the function is available.
		if (function_exists('template_admin_quick_search'))
			template_admin_quick_search();
		echo '
					</div>';
	}

	// Shall we use the tabs? Yes, it's the only known way!
	if (!empty($selected_tab['description']) || !empty($tab_context['description']))
		echo '
					<p class="description">
						', !empty($selected_tab['description']) ? $selected_tab['description'] : $tab_context['description'], '
					</p>';

	// Print out all the items in this tab (if any).
	if (!empty($tab_context['tabs']))
	{
		// The admin tabs.
		echo '
					<ul id="adm_submenus">';

		foreach ($tab_context['tabs'] as $sa => $tab)
		{
			if (!empty($tab['disabled']))
				continue;

			if (!empty($tab['is_selected']))
				echo '
						<li class="listlevel1">
							<a class="linklevel1 active" href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], isset($tab['add_params']) ? $tab['add_params'] : '', '">', $tab['label'], '</a>
						</li>';
			else
				echo '
						<li class="listlevel1">
							<a class="linklevel1" href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], isset($tab['add_params']) ? $tab['add_params'] : '', '">', $tab['label'], '</a>
						</li>';
		}

		// the end of tabs
		echo '
					</ul>';
	}
}