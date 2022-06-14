<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

/**
 * Renders a collapsible list of groups
 *
 * @param string $group defaults to default_groups_list
 */
function template_list_groups_collapsible($group = 'default_groups_list')
{
	global $context, $txt;

	$current_group_list = $context[$group];
	$all_selected = true;

	if (!isset($current_group_list['id']))
	{
		$current_group_list['id'] = $group;
	}

	echo '
		<fieldset id="', $current_group_list['id'], '">
			<legend>', $current_group_list['select_group'], '</legend>';

	echo '
			<ul class="permission_groups">';

	foreach ($current_group_list['member_groups'] as $group)
	{
		$all_selected &= $group['status'] === 'on';
		echo '
				<li>
					<input type="checkbox" id="', $current_group_list['id'], '_', $group['id'], '" name="', $current_group_list['id'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' />
					<label for="', $current_group_list['id'], '_', $group['id'], '"', $group['is_postgroup'] ? ' class="em"' : '', '>', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
				</li>';
	}

	echo '
				<li class="check_all">
					<input type="checkbox" id="check_all" ', $all_selected ? 'checked="checked" ' : '', 'onclick="invertAll(this, this.form, \'', $current_group_list['id'], '\');" class="input_check" />
					<label for="check_all">', $txt['check_all'], '</label>
				</li>
			</ul>
		</fieldset>';
}

/**
 * Dropdown usable to select a board
 *
 * @param string $name
 * @param string $label
 * @param string $extra
 * @param bool $all
 *
 * @return string as echoed output
 */
function template_select_boards($name, $label = '', $extra = '', $all = false)
{
	global $context, $txt;

	if (!empty($label))
	{
		echo '
	<label for="', $name, '">', $label, ' </label>';
	}

	echo '
	<select name="', $name, '" id="', $name, '" ', $extra, ' >';

	if ($all)
	{
		echo '
		<option value="">', $txt['icons_edit_icons_all_boards'], '</option>';
	}

	foreach ($context['categories'] as $category)
	{
		echo '
		<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
		{
			echo '
			<option value="', $board['id'], '"', !empty($board['selected']) ? ' selected="selected"' : '', !empty($context['current_board']) && $board['id'] == $context['current_board'] && $context['boards_current_disabled'] ? ' disabled="disabled"' : '', '>', $board['child_level'] > 0 ? str_repeat('&#8195;', $board['child_level'] - 1) . '&#8195;&#10148;' : '', $board['name'], '</option>';
		}
		echo '
		</optgroup>';
	}

	echo '
	</select>';
}

/**
 * Generate a strip of buttons (like those present at the top of the message display)
 *
 * What it does:
 *
 * - Create a button list area, passed an array of the button name with parameter values to use on each <li>
 * ['buttonName' => [
 * 		'url' => link to call when button is pressed
 * 		'text' => txt key to use as $txt[key] to display in the button
 *  	'icon' => (optional) svg icon name to be applied as <i class="icon i-{icon}"></i> in front of the text
 * 		'custom' => (optional) action to perform, generally used to add 'onclick' events
 * 		'test' => (optional) permission to check for in $context[key] before showing the button
 * 		'submenu' => (optional) if the button should be placed in a "more" dropdown button
 * 		'class' => (optional) *additional* className to apply to the <li> element
 * 		'linkclass' => (optional) *additional* className to use on <a>, if not supplied defaults to button_strip_{buttonName}
 * 		'active' => (optional) adds active to the the list of classes on the <a> link
 * 		'id' => (optional) id to apply to the <a> link as button_strip_{id}
 * 		'checkbox' => (optional) 'always' will wrap an input element, otherwise an empty <li> placeholder, suitable for JS
 * 		'counter' => (optional) if set, will add a count indicator span in front of the link text
 * ]]
 *
 * @param mixed[] $button_strip the above definition array
 * @param string $class overall class to append to "buttonlist no_js" on the list UL
 * @param string[] $strip_options = [] of options applied to the outer <UL>
 * 		'id' => id to use on the UL
 * 		'no-class' => do not apply the default "buttonlist no_js" to the ul (will still use passed $class)
 * @return void string as echoed content as buttons | submenu | checkbox
 */
function template_button_strip($button_strip, $class = '', $strip_options = [])
{
	global $context, $txt, $options;

	// Not sure if this can happen, but people can misuse functions very efficiently
	if (empty($button_strip))
	{
		return;
	}

	if (!is_array($strip_options))
	{
		$strip_options = [];
	}

	// Create the buttons... now with cleaner markup (yay!).
	$buttons = [];
	$subMenu = [];
	$checkbox = [];

	foreach ($button_strip as $buttonName => $buttonParameters)
	{
		// Don't have the right permission, or it has been disabled, no button for you!

		if ((isset($buttonParameters['enabled']) && $buttonParameters['enabled'] === false)
			|| (isset($buttonParameters['test']) && empty($context[$buttonParameters['test']])))
		{
			continue;
		}

		// Start with this button markup details
		$id = (isset($buttonParameters['id']) ? 'id="button_strip_' . $buttonParameters['id'] . '"' : '');
		$liClass = 'class="listlevel1 ' . ($buttonParameters['class'] ?? $buttonName) . '"';
		$linkClass = 'class="linklevel1 ' . (!empty($buttonParameters['active']) ? 'active ' : '') . (!empty($buttonParameters['linkclass']) ? $buttonParameters['linkclass'] : 'button_strip_' . $buttonName) . '"';
		$icon = !empty($buttonParameters['icon']) ? '<i class="icon icon-small i-' . $buttonParameters['icon'] . '"></i>' : '';
		$counter = !empty($buttonParameters['counter']) ? '<span class="button_indicator">' . $buttonParameters['counter'] . '</span>' : '';

		// Special case, the button checkbox.
		if (!empty($buttonParameters['checkbox']) && (!empty($options['display_quick_mod']) || $buttonParameters['checkbox'] === 'always'))
		{
			// if not always, just reserve a checkbox location (e.g. for quick moderation)
			$checkbox[] = '
						<li ' . (isset($buttonParameters['id']) ? 'id="' . $buttonParameters['id'] . '"' : '') . ' ' . $liClass . ' role="none">' .
							($buttonParameters['checkbox'] === 'always' ? '<input role="menuitemcheckbox" class="input_check ' . $buttonName . '_check" type="checkbox" name="' . $buttonParameters['name'] . '[]" value="' . $buttonParameters['value'] . '" />' : '') . '
						</li>';

			continue;
		}

		// This little button goes in a dropdown
		if (!empty($buttonParameters['submenu']))
		{
			$subMenu[] = '
						<li ' . $liClass . ' role="none">
							<a href="' . $buttonParameters['url'] . '" class="linklevel2 button_strip_' . $buttonName . '" ' . ($buttonParameters['custom'] ?? '') . '>
							' . $icon . $txt[$buttonParameters['text']] . '
							</a>
						</li>';
			continue;
		}

		// This little button goes in a row
		$buttons[] = '
						<li ' . $liClass . ' role="none">
							<a ' . $id . ' ' . $linkClass . ' role="menuitem" href="' . $buttonParameters['url'] . '" ' . ($buttonParameters['custom'] ?? '') . '>
							' . $counter . $icon . $txt[$buttonParameters['text']] . '
							</a>
						</li>';
	}

	// If a "more" button was needed, we place it as the last button (but before a checkbox)
	if (!empty($subMenu))
	{
		$buttons[] = '
						<li class="listlevel1 subsections" role="none">
							<a aria-haspopup="true" role="menuitem" href="#" ' . (!empty($options['use_click_menu']) ? '' : 'onclick="event.stopPropagation();return false;"') . ' class="linklevel1 post_options">' .
			$txt['post_options'] . '
							</a>
							<ul role="menu" class="menulevel2">' . implode('', $subMenu) . '</ul>
						</li>';
	}

	// No buttons? No button strip either.
	if (!empty($buttons))
	{
		// The markup details for the entire strip
		$id = !empty($strip_options['id']) ? 'id="' . $strip_options['id'] . '" ' : '';
		$defaultClass = !empty($strip_options['no-class']) ? '' : 'buttonlist no_js';
		$class .= $context['right_to_left'] ? ' rtl' : '';

		echo '
					<ul ', $id, 'role="menubar" class="', $defaultClass, !empty($class) ? ' ' . $class : '', '">
						', implode('', $buttons), implode('', $checkbox), '
					</ul>';
	}
}

/**
 * Another used and abused piece of template that can be found everywhere
 *
 * @param string|bool $button_strip index of $context to create the button strip
 * @param string $strip_direction direction of the button strip (see template_button_strip for details)
 * @param array $options array of optional values, possible values:
 *     - 'page_index' (string) index of $context where is located the pages index generated by constructPageIndex
 *     - 'page_index_markup' (string) markup for the page index, overrides 'page_index' and can be used if
 *        the page index code is not in the first level of $context
 *     - 'extra' (string) used to add html markup at the end of the template
 *
 * @return string as echoed content
 */
function template_pagesection($button_strip = false, $strip_direction = '', $options = [])
{
	global $context;

	if (!empty($options['page_index_markup']))
	{
		$pages = '<ul ' . (isset($options['page_index_id']) ? 'id="' . $options['page_index_id'] . '" ' : '') . 'class="pagelinks">' . $options['page_index_markup'] . '</ul>';
	}
	else
	{
		if (!isset($options['page_index']))
		{
			$options['page_index'] = 'page_index';
		}

		$pages = empty($context[$options['page_index']]) ? '' : '<ul ' . (isset($options['page_index_id']) ? 'id="' . $options['page_index_id'] . '" ' : '') . 'class="pagelinks">' . $context[$options['page_index']] . '</ul>';
	}

	if (!isset($options['extra']))
	{
		$options['extra'] = '';
	}

	if (!empty($strip_direction) && $strip_direction === 'left')
	{
		$strip_direction = 'rtl';
	}

	echo '
			<nav class="pagesection">
				', $pages, '
				', !empty($button_strip) && !empty($context[$button_strip]) ? template_button_strip($context[$button_strip], $strip_direction) : '',
				$options['extra'], '
			</nav>';
}

/**
 * DEPRECIATED since 2.0
 *
 * Generate a strip of "quick" buttons (those present next to each message)
 *
 * What it does:
 *
 * - Create a quick button, pass an array of the button name with key values
 * - array('somename' => array(href => '' text => '' custom => '' test => ''))
 *      - href => link to call when button is pressed
 *      - text => text to display in the button
 *      - custom => custom action to perform, generally used to add 'onclick' events (optional)
 *      - test => key to check in the $tests array before showing the button (optional)
 *      - override => full and complete <li></li> to use for the button
 * - checkboxes can be shown as well as buttons,
 *      - use array('check' => array(checkbox => (true | always), name => value =>)
 *      - if true follows show moderation as checkbox setting, always will always show
 *      - name => name of the checkbox array, like delete, will have [] added for the form
 *      - value => value for the checkbox to return in the post
 *
 * @param array $strip - the $context index where the strip is stored
 * @param bool[] $tests - an array of tests to determine if the button should be displayed or not
 * @depreciated since 2.0, use improved template_button_strip
 * @return void echos a string of buttons
 */
function template_quickbutton_strip($strip, $tests = array())
{
	global $options;

	// Annoy devs so they stop using this function
	Errors::instance()->log_error('Depreciated: template_quickbutton_strip usage', 'depreciated');

	$buttons = [];

	foreach ($strip as $key => $value)
	{
		if (!empty($value['checkbox']) && (!empty($options['display_quick_mod']) || $value['checkbox'] === 'always'))
		{
			$buttons[] = '
					<li class="listlevel1 ' . $key . '">
						<input class="input_check ' . $key . '_check" type="checkbox" name="' . $value['name'] . '[]" value="' . $value['value'] . '" />
					</li>';

			continue;
		}

		// No special permission needed, or you have valid permission, then get a button!
		if (!isset($value['test']) || !empty($tests[$value['test']]))
		{
			if (!empty($value['override']))
			{
				$buttons[] = $value['override'];
				continue;
			}

			$buttons[] = '
					<li class="listlevel1">
						<a href="' . $value['href'] . '" class="linklevel1 ' . $key . '_button"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $value['text'] . '</a>
					</li>';
		}
	}

	// No buttons? No button strip either.
	if (!empty($buttons))
	{
		echo '
					<ul class="quickbuttons">', implode('
						', $buttons), '
					</ul>';
	}
}
