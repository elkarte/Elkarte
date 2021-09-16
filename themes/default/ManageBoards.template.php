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
 * @version 1.1.8
 *
 */

/**
 * Template for listing all the current categories and boards.
 */
function template_manage_boards()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Table header.
	echo '
	<div id="manage_boards">
		<h2 class="category_header">', $txt['boardsEdit'], '</h2>';

	if (!empty($context['move_board']))
		echo '
		<div class="information">
			<p>', $context['move_title'], ' [<a href="', $scripturl, '?action=admin;area=manageboards">', $txt['mboards_cancel_moving'], '</a>]', '</p>
		</div>';

	// No categories so show a label.
	if (empty($context['categories']))
		echo '
		<div class="content centertext">
			', $txt['mboards_no_cats'], '
		</div>';

	// Loop through every category, listing the boards in each as we go.
	$sortables = array();
	foreach ($context['categories'] as $category)
	{
		$sortables[] = '#category_' . $category['id'];

		// Link to modify the category.
		echo '
			<h2 class="category_header">
				<a href="' . $scripturl . '?action=admin;area=manageboards;sa=cat;cat=' . $category['id'] . '">', $category['name'], '</a>
				<a href="' . $scripturl . '?action=admin;area=manageboards;sa=cat;cat=' . $category['id'] . '"><i class="icon icon-small i-pencil"></i>', $txt['catModify'], '</a>
			</h2>';

		// Boards table header.
		echo '
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=newboard;cat=', $category['id'], '" method="post" accept-charset="UTF-8">
			<div id="category_', $category['id'], '" class="content">
				<ul class="nolist">';

		if (!empty($category['move_link']))
			echo '
					<li><a href="', $category['move_link']['href'], '" title="', $category['move_link']['label'], '"><img src="', $settings['images_url'], '/smiley_select_spot.png" alt="', $category['move_link']['label'], '" /></a></li>';

		$first = true;
		$depth = 0;

		// If there is nothing in a category, add a drop zone
		if (empty($category['boards']))
			echo '
					<li id="cbp_' . $category['id'] . ',-1,"></li>';

		// List through every board in the category, printing its name and link to modify the board.
		foreach ($category['boards'] as $board)
		{
			// Going in a level deeper (sub-board)
			if ($board['child_level'] > $depth)
				echo '
						<ul class="nolist">';
			// Backing up a level to a childs parent
			elseif ($board['child_level'] < $depth)
			{
				for ($i = $board['child_level']; $i < $depth; $i++)
					echo
					'
							</li>
						</ul>';
			}
			// Base node parent but not the first one
			elseif ($board['child_level'] == 0 && !$first)
				echo '
					</li>';

			echo '
					<li id="cbp_' . $category['id'] . ',' . $board['id'] . '"', (!empty($modSettings['recycle_board']) && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board['id'] ? 'class="recycle_board"' : ''), ' style="', $board['move'] ? ';color: red;' : '', '">
						<span class="floatleft"><a href="', $scripturl, '?board=', $board['id'], '">', $board['name'], '</a>', !empty($modSettings['recycle_board']) && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board['id'] ? '&nbsp;<a href="' . $scripturl . '?action=admin;area=manageboards;sa=settings"><img src="' . $settings['images_url'] . '/post/recycled.png" alt="' . $txt['recycle_board'] . '" /></a></span>' : '</span>', '
						<span class="floatright">', $context['can_manage_permissions'] ? '<span class="modify_boards"><a href="' . $scripturl . '?action=admin;area=permissions;sa=index;pid=' . $board['permission_profile'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['mboards_permissions'] . '</a></span>' : '', '
						<span class="modify_boards"><a href="', $scripturl, '?action=admin;area=manageboards;move=', $board['id'], '">', $txt['mboards_move'], '</a></span>
						<span class="modify_boards"><a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], '">', $txt['mboards_modify'], '</a></span></span><br style="clear: right;" />';

			if (!empty($board['move_links']))
			{
				echo '
					<li style="padding-', $context['right_to_left'] ? 'right' : 'left', ': ', 5 + 30 * $board['move_links'][0]['child_level'], 'px;">';

				foreach ($board['move_links'] as $link)
					echo '
						<a href="', $link['href'], '" class="move_links" title="', $link['label'], '"><img src="', $settings['images_url'], '/board_select_spot', $link['child_level'] > 0 ? '_child' : '', '.png" alt="', $link['label'], '" style="padding: 0px; margin: 0px;" /></a>';

				echo '
					</li>';
			}

			$depth = $board['child_level'];
			$first = false;
		}

		// All done, backing up to a base node
		if (!$first)
		{
			if ($depth > 0)
			{
				for ($i = $depth; $i > 0; $i--)
					echo
					'
							</li>
						</ul>';
			}

			echo '
					</li>';
		}

		// Button to add a new board.
		echo '
				</ul>
				<br class="clear" />
				<div class="submitbutton">
					<input type="submit" value="', $txt['mboards_new_board'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>';
	}

	echo '
	</div>
	<script>
		// Start by creating proper ids and ul childs for use
		setBoardIds();

		// Set up our sortable call
		$().elkSortable({
			sa: "boardorder",
			error: "' . $txt['admin_order_error'] . '",
			title: "' . $txt['admin_order_title'] . '",
			token: {token_var: "' . $context['admin-sort_token_var'] . '", token_id: "' . $context['admin-sort_token'] . '"},
			tag: "' . implode(' ul,', $sortables) . ' ul",
			connect: ".nolist",
			tolerance: "pointer",
			containment: "document",
			href: "?action=admin;area=manageboards",
			placeholder: "ui-state-highlight",
			preprocess: "setBoardIds",
			axis: "",
			setorder: "inorder"
		});
	</script>';
}

/**
 * Template for editing/adding a category on the forum.
 */
function template_modify_category()
{
	global $context, $scripturl, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="', $context['category']['id'], '" />
				<h2 class="category_header">
					', isset($context['category']['is_new']) ? $txt['mboards_new_cat_name'] : $txt['catEdit'], '
				</h2>
				<div class="content">
					<dl class="settings">';

	// If this isn't the only category, let the user choose where this category should be positioned down the boardindex.
	if (count($context['category_order']) > 1)
	{
		echo '
					<dt><label for="cat_order">', $txt['order'], ':</label></dt>
					<dd>
						<select id="cat_order" name="cat_order">';

		// Print every existing category into a select box.
		foreach ($context['category_order'] as $order)
			echo '
							<option', $order['selected'] ? ' selected="selected"' : '', ' value="', $order['id'], '">', $order['name'], '</option>';

		echo '
						</select>
					</dd>';
	}

	// Allow the user to edit the category name and/or choose whether you can collapse the category.
	echo '
					<dt>
						<label for="cat_name">', $txt['full_name'], ':</label><br />
						<span class="smalltext">', $txt['name_on_display'], '</span>
					</dt>
					<dd>
						<input type="text" id="cat_name" name="cat_name" value="', $context['category']['editable_name'], '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" />
					</dd>
					<dt>
						<strong>' . $txt['collapse_enable'] . '</strong><br />
						<span class="smalltext">' . $txt['collapse_desc'] . '</span>
					</dt>
					<dd>
						<input type="checkbox" name="collapse"', $context['category']['can_collapse'] ? ' checked="checked"' : '', ' tabindex="', $context['tabindex']++, '" />
					</dd>';

	// Table footer.
	echo '
				</dl>
				<div class="submitbutton">';

	if (isset($context['category']['is_new']))
		echo '
						<input type="submit" name="add" value="', $txt['mboards_add_cat_button'], '" onclick="return !isEmptyText(this.form.cat_name);" tabindex="', $context['tabindex']++, '" />';
	else
		echo '
						<input type="submit" name="edit" value="', $txt['modify'], '" onclick="return !isEmptyText(this.form.cat_name);" tabindex="', $context['tabindex']++, '" />
						<input type="submit" name="delete" value="', $txt['mboards_delete_cat'], '" />';

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	if (!empty($context['token_check']))
		echo '
						<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	// If this category is empty we don't bother with the next confirmation screen.
	if ($context['category']['is_empty'])
		echo '
						<input type="hidden" name="empty" value="1" />';

	echo '
					</div>
				</div>
			</form>
		</div>';
}

/**
 * A template to confirm if a user wishes to delete a category - and whether they want to save the boards.
 */
function template_confirm_category_delete()
{
	global $context, $scripturl, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=cat2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="cat" value="', $context['category']['id'], '" />
			<h2 class="category_header">', $txt['mboards_delete_cat'], '</h2>
			<div class="content">
				<p>', $txt['mboards_delete_cat_contains'], ':</p>
				<ul>';

	foreach ($context['category']['children'] as $child)
		echo '
					<li>', $child, '</li>';

	echo '
					</ul>
			</div>
			<h2 class="category_header">', $txt['mboards_delete_what_do'], '</h2>
			<div class="content">
				<p>
					<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" checked="checked" />', $txt['mboards_delete_option1'], '</label><br />
					<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1" class="input_radio"', count($context['category_order']) == 1 ? ' disabled="disabled"' : '', ' />', $txt['mboards_delete_option2'], '</label>:
					<select name="cat_to" ', count($context['category_order']) == 1 ? 'disabled="disabled"' : '', '>';

	foreach ($context['category_order'] as $cat)
		if ($cat['id'] != 0)
			echo '
							<option value="', $cat['id'], '">', $cat['true_name'], '</option>';

	echo '
					</select>
				</p>
				<div class="submitbutton">
					<input type="submit" name="delete" value="', $txt['mboards_delete_confirm'], '" onclick="return confirm(\'', $txt['catConfirm'], '\');" />
					<input type="submit" name="cancel" value="', $txt['mboards_delete_cancel'], '" />
					<input type="hidden" name="confirmation" value="1" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
			</div>
		</form>
	</div>';
}

/**
 * Below is the template for adding/editing an board on the forum.
 */
function template_modify_board()
{
	global $context, $scripturl, $txt, $modSettings;

	// The main table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=board2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="boardid" value="', $context['board']['id'], '" />
			<h2 class="category_header">
				', isset($context['board']['is_new']) ? $txt['mboards_new_board_name'] : $txt['boardsEdit'], '
			</h2>
			<div class="content">
				<dl class="settings">';

	// Option for choosing the category the board lives in.
	echo '

					<dt>
						<label for="new_cat">', $txt['mboards_category'], ':</label>
					</dt>
					<dd>
						<select id="new_cat" name="new_cat" onchange="if (this.form.order) {this.form.order.disabled = this.options[this.selectedIndex].value != 0; this.form.board_order.disabled = this.options[this.selectedIndex].value != 0 || this.form.order.options[this.form.order.selectedIndex].value == \'\';}">';

	foreach ($context['categories'] as $category)
		echo '
							<option', $category['selected'] ? ' selected="selected"' : '', ' value="', $category['id'], '">', $category['name'], '</option>';

	echo '
						</select>
					</dd>';

	// If this isn't the only board in this category let the user choose where the board is to live.
	if ((isset($context['board']['is_new']) && count($context['board_order']) > 0) || count($context['board_order']) > 1)
	{
		echo '
					<dt>
						<label for="order">', $txt['order'], ':</label>
					</dt>
					<dd>';

		// The first select box gives the user the option to position it before, after or as a child of another board.
		echo '
						<select id="order" name="placement" onchange="this.form.board_order.disabled = this.options[this.selectedIndex].value == \'\';">
							', !isset($context['board']['is_new']) ? '<option value="">(' . $txt['mboards_unchanged'] . ')</option>' : '', '
							<option value="after">' . $txt['mboards_order_after'] . '...</option>
							<option value="child">' . $txt['mboards_order_child_of'] . '...</option>
							<option value="before">' . $txt['mboards_order_before'] . '...</option>
						</select>';

		// The second select box lists all the boards in the category.
		echo '
						<select id="board_order" name="board_order" ', isset($context['board']['is_new']) ? '' : 'disabled="disabled"', '>
								', !isset($context['board']['is_new']) ? '<option value="">(' . $txt['mboards_unchanged'] . ')</option>' : '';

		foreach ($context['board_order'] as $order)
			echo '
							<option', $order['selected'] ? ' selected="selected"' : '', ' value="', $order['id'], '">', $order['name'], '</option>';

		echo '
						</select>
					</dd>';
	}

	// Options for board name and description.
	echo '
					<dt>
						<label for="board_name">', $txt['full_name'], ':</label><br />
						<span class="smalltext">', $txt['name_on_display'], '</span>
					</dt>
					<dd>
						<input type="text" id="board_name" name="board_name" value="', $context['board']['name'], '" size="30" class="input_text" />
					</dd>
					<dt>
						<label for="desc">', $txt['mboards_description'], ':</label><br />
						<span class="smalltext">', $txt['mboards_description_desc'], '</span>
					</dt>
					<dd>
						<textarea id="desc" name="desc" rows="3" cols="35">', $context['board']['description'], '</textarea>
					</dd>
					<dt>
						<label for="profile">', $txt['permission_profile'], ':</label><br />
						<span class="smalltext">', $context['can_manage_permissions'] ? sprintf($txt['permission_profile_desc'], $scripturl . '?action=admin;area=permissions;sa=profiles;' . $context['session_var'] . '=' . $context['session_id']) : strip_tags($txt['permission_profile_desc']), '</span>
					</dt>
					<dd>
						<select id="profile" name="profile">';

	if (isset($context['board']['is_new']))
		echo '
							<option value="-1">[', $txt['permission_profile_inherit'], ']</option>';

	foreach ($context['profiles'] as $id => $profile)
		echo '
								<option value="', $id, '" ', $id == $context['board']['profile'] ? 'selected="selected"' : '', '>', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<label>', $txt['mboards_groups'], ':</label><br />
						<span class="smalltext">', empty($modSettings['deny_boards_access']) ? $txt['mboards_groups_desc'] : $txt['boardsaccess_option_desc'], '</span>';

	echo '
					</dt>
					<dd>';

	if (!empty($modSettings['deny_boards_access']))
	{
		echo '
						<table>
							<tr>
								<td></td>
								<th>', $txt['permissions_option_on'], '</th>
								<th>', $txt['permissions_option_off'], '</th>
								<th>', $txt['permissions_option_deny'], '</th>
								<th></th>
							</tr>';
	}

	// List all the membergroups so the user can choose who may access this board.
	foreach ($context['groups'] as $group)
	{
		if (empty($modSettings['deny_boards_access']))
		{
			echo '
						<label for="groups_', $group['id'], '">
							<input type="checkbox" name="groups[', $group['id'], ']" value="allow" id="groups_', $group['id'], '"', $group['allow'] ? ' checked="checked"' : '', ' />
							<span', $group['is_post_group'] ? ' class="post_group" title="' . $txt['mboards_groups_post_group'] . '"' : '', $group['id'] == 0 ? ' class="regular_members" title="' . $txt['mboards_groups_regular_members'] . '"' : '', '>
								', $group['name'], '
							</span>
						</label>
						<br />';
		}
		else
		{
			echo '
							<tr>
								<td>
									<span', $group['is_post_group'] ? ' class="post_group" title="' . $txt['mboards_groups_post_group'] . '"' : '', $group['id'] == 0 ? ' class="regular_members" title="' . $txt['mboards_groups_regular_members'] . '"' : '', '>
										', $group['name'], '
									</span>
								</td>
								<td>
									<input type="radio" name="groups[', $group['id'], ']" value="allow" id="groups_', $group['id'], '_a"', $group['allow'] ? ' checked="checked"' : '', ' />
								</td>
								<td>
									<input type="radio" name="groups[', $group['id'], ']" value="ignore" id="groups_', $group['id'], '_x"', !$group['allow'] && !$group['deny'] ? ' checked="checked"' : '', ' />
								</td>
								<td>
									<input type="radio" name="groups[', $group['id'], ']" value="deny" id="groups_', $group['id'], '_d"', $group['deny'] ? ' checked="checked"' : '', ' />
								</td>
								<td></td>
							</tr>';
		}
	}

	if (empty($modSettings['deny_boards_access']))
	{
		echo '
						<span class="select_all_box">
							<em><label for="check_all">', $txt['check_all'], '</label></em> <input type="checkbox" id="check_all" onclick="invertAll(this, this.form, \'groups[\');" />
						</span>
						<br />
						<br />
					</dd>';
	}
	else
	{
		echo '
							<tr class="select_all_box">
								<td>
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, \'groups\', \'allow\');" />
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, \'groups\', \'ignore\');" />
								</td>
								<td>
									<input type="radio" name="select_all" onclick="selectAllRadio(this, this.form, \'groups\', \'deny\');" />
								</td>
								<td>
									<em>', $txt['check_all'], '</em>
								</td>
							</tr>
						</table>
					</dd>';
	}

	// Options to choose moderators, specify as announcement board and choose whether to count posts here.
	echo '
					<dt>
						<label for="moderators">', $txt['mboards_moderators'], ':</label><br />
						<span class="smalltext">', $txt['mboards_moderators_desc'], '</span><br />
					</dt>
					<dd>
						<input type="text" name="moderators" id="moderators" value="', $context['board']['moderator_list'], '" size="30" class="input_text" />
						<div id="moderator_container"></div>
					</dd>
				</dl>
				<hr />';

	// Add a select all box for the allowed groups section
	theme()->addInlineJavascript('
		$(function() {
			$(".select_all_box").each(function () {
				$(this).removeClass(\'select_all_box\');
			});
		});', true);

	if (empty($context['board']['is_recycle']) && empty($context['board']['topics']))
		echo '
				<dl class="settings">
					<dt>
						<label for="redirect_enable">', $txt['mboards_redirect'], ':</label><br />
						<span class="smalltext">', $txt['mboards_redirect_desc'], '</span><br />
					</dt>
					<dd>
						<input type="checkbox" id="redirect_enable" name="redirect_enable"', $context['board']['redirect'] != '' ? ' checked="checked"' : '', ' onclick="refreshOptions();" />
					</dd>
				</dl>';

	if (!empty($context['board']['is_recycle']))
		echo '
				<div class="infobox">', $txt['mboards_redirect_disabled_recycle'], '<br />', $txt['mboards_recycle_disabled_delete'], '</div>';

	if (empty($context['board']['is_recycle']) && !empty($context['board']['topics']))
		echo '
				<div class="infobox">
					<strong>', $txt['mboards_redirect'], '</strong><br />
					', $txt['mboards_redirect_disabled'], '
				</div>';

	if (!$context['board']['topics'] && empty($context['board']['is_recycle']))
	{
		echo '
				<div id="redirect_address_div">
					<dl class="settings">
						<dt>
							<label for="redirect_address">', $txt['mboards_redirect_url'], ':</label><br />
							<span class="smalltext">', $txt['mboards_redirect_url_desc'], '</span><br />
						</dt>
						<dd>
							<input type="text" id="redirect_address" name="redirect_address" value="', $context['board']['redirect'], '" size="40" class="input_text" />
						</dd>
					</dl>
				</div>';

		if ($context['board']['redirect'])
			echo '
				<div id="reset_redirect_div">
					<dl class="settings">
						<dt>
							<label for="reset_redirect">', $txt['mboards_redirect_reset'], ':</label><br />
							<span class="smalltext">', $txt['mboards_redirect_reset_desc'], '</span><br />
						</dt>
						<dd>
							<input type="checkbox" id="reset_redirect" name="reset_redirect" />
							<em>(', sprintf($txt['mboards_current_redirects'], $context['board']['posts']), ')</em>
						</dd>
					</dl>
				</div>';
	}

	echo '
				<div id="count_posts_div">
					<dl class="settings">
						<dt>
							<label for="count">', $txt['mboards_count_posts'], ':</label><br />
							<span class="smalltext">', $txt['mboards_count_posts_desc'], '</span><br />
						</dt>
						<dd>
							<input type="checkbox" id="count" name="count" ', $context['board']['count_posts'] ? ' checked="checked"' : '', ' />
						</dd>
					</dl>
				</div>';

	// Here the user can choose to force this board to use a theme other than the default theme for the forum.
	echo '
				<div id="board_theme_div">
					<dl class="settings">
						<dt>
							<label for="boardtheme">', $txt['mboards_theme'], ':</label><br />
							<span class="smalltext">', $txt['mboards_theme_desc'], '</span><br />
						</dt>
						<dd>
							<select name="boardtheme" id="boardtheme" onchange="refreshOptions();">
								<option value="0"', $context['board']['theme'] == 0 ? ' selected="selected"' : '', '>', $txt['mboards_theme_default'], '</option>';

	foreach ($context['themes'] as $theme)
		echo '
								<option value="', $theme['id'], '"', $context['board']['theme'] == $theme['id'] ? ' selected="selected"' : '', '>', $theme['name'], '</option>';

	echo '
							</select>
						</dd>
					</dl>
				</div>
				<div id="override_theme_div">
					<dl class="settings">
						<dt>
							<label for="override_theme">', $txt['mboards_override_theme'], ':</label><br />
							<span class="smalltext">', $txt['mboards_override_theme_desc'], '</span><br />
						</dt>
						<dd>
							<input type="checkbox" id="override_theme" name="override_theme"', $context['board']['override_theme'] ? ' checked="checked"' : '', ' />
						</dd>
					</dl>
				</div>';

	echo '
				<div class="submitbutton">
					<input type="hidden" name="rid" value="', $context['redirect_location'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-be-' . $context['board']['id'] . '_token_var'], '" value="', $context['admin-be-' . $context['board']['id'] . '_token'], '" />';

	// If this board has no children don't bother with the next confirmation screen.
	if ($context['board']['no_children'])
		echo '
					<input type="hidden" name="no_children" value="1" />';

	if (isset($context['board']['is_new']))
		echo '
					<input type="hidden" name="cur_cat" value="', $context['board']['category'], '" />
					<input type="submit" name="add" value="', $txt['mboards_new_board'], '" onclick="return !isEmptyText(this.form.board_name);" />';
	else
		echo '
					<input type="submit" name="edit" value="', $txt['modify'], '" onclick="return !isEmptyText(this.form.board_name);" />';

	if (!isset($context['board']['is_new']) && empty($context['board']['is_recycle']))
		echo '
					<input type="submit" name="delete" value="', $txt['mboards_delete_board'], '" onclick="return confirm(\'', $txt['boardConfirm'], '\');" />';

	echo '
				</div>
			</div>
		</form>
	</div>';

	$js = '
		var oModeratorSuggest = new smc_AutoSuggest({
			sSelf: \'oModeratorSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'moderators\',
			sControlId: \'moderators\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'moderator_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			sItemListContainerId: \'moderator_container\',
			aListItems: [';

	foreach ($context['board']['moderators'] as $id_member => $member_name)
		$js .= '
					{
						sItemId: ' . JavaScriptEscape($id_member) . ',
						sItemName: ' . JavaScriptEscape($member_name) . '
					}' . ($id_member == $context['board']['last_moderator_id'] ? '' : ',');

	$js .= '
			]
		});';

	addInlineJavascript($js, true);

	// Javascript for deciding what to show.
	echo '
	<script>
		function refreshOptions()
		{
			var redirect = document.getElementById("redirect_enable"),
				redirectEnabled = redirect ? redirect.checked : false,
				nonDefaultTheme = document.getElementById("boardtheme").value!=0;

			// What to show?
			document.getElementById("override_theme_div").style.display = redirectEnabled || !nonDefaultTheme ? "none" : "";
			document.getElementById("board_theme_div").style.display = redirectEnabled ? "none" : "";
			document.getElementById("count_posts_div").style.display = redirectEnabled ? "none" : "";';

	if (!$context['board']['topics'] && empty($context['board']['is_recycle']))
	{
		echo '
			document.getElementById("redirect_address_div").style.display = redirectEnabled ? "" : "none";';

		if ($context['board']['redirect'])
			echo '
			document.getElementById("reset_redirect_div").style.display = redirectEnabled ? "" : "none";';
	}

	echo '
		}

		refreshOptions();
	</script>';
}

/**
 * A template used when a user is deleting a board with sub-boards in it - to see what they want to do with them.
 */
function template_confirm_board_delete()
{
	global $context, $scripturl, $txt;

	// Print table header.
	echo '
	<div id="manage_boards">
		<form action="', $scripturl, '?action=admin;area=manageboards;sa=board2" method="post" accept-charset="UTF-8">
			<input type="hidden" name="boardid" value="', $context['board']['id'], '" />
			<h2 class="category_header">', $txt['mboards_delete_board'], '</h2>
			<div class="content">
				<p>', $txt['mboards_delete_board_contains'], '</p>
					<ul>';

	foreach ($context['children'] as $child)
		echo '
						<li>', $child['node']['name'], '</li>';

	echo '
					</ul>
			</div>
			<h2 class="category_header">', $txt['mboards_delete_what_do'], '</h2>
			<div class="content">
				<p>
					<label for="delete_action0"><input type="radio" id="delete_action0" name="delete_action" value="0" checked="checked" />', $txt['mboards_delete_board_option1'], '</label><br />
					<label for="delete_action1"><input type="radio" id="delete_action1" name="delete_action" value="1" class="input_radio"', empty($context['can_move_children']) ? ' disabled="disabled"' : '', ' />', $txt['mboards_delete_board_option2'], '</label>:
					<select name="board_to" ', empty($context['can_move_children']) ? 'disabled="disabled"' : '', '>';

	foreach ($context['board_order'] as $board)
		if ($board['id'] != $context['board']['id'] && empty($board['is_child']))
			echo '
						<option value="', $board['id'], '">', $board['name'], '</option>';

	echo '
					</select>
				</p>
				<div class="submitbutton">
					<input type="submit" name="delete" value="', $txt['mboards_delete_confirm'], '" />
					<input type="submit" name="cancel" value="', $txt['mboards_delete_cancel'], '" />
					<input type="hidden" name="confirmation" value="1" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-be-' . $context['board']['id'] . '_token_var'], '" value="', $context['admin-be-' . $context['board']['id'] . '_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}
