<?php
// Version: 1.0; ManageMembers

global $context;

$txt['groups'] = 'Groups';
$txt['viewing_groups'] = 'Viewing Member Groups';

$txt['membergroups_title'] = 'Manage Member Groups';
$txt['membergroups_description'] = 'Member Groups are groups of members that have similar permission settings, appearance, or access rights. Some member groups are based on the amount of posts a user has made. You can assign someone to a member group by selecting their profile and changing their account settings.';
$txt['membergroups_modify'] = 'Modify';

$txt['membergroups_add_group'] = 'Add group';
$txt['membergroups_regular'] = 'Regular groups';
$txt['membergroups_post'] = 'Post count based groups';
$txt['membergroups_guests_na'] = 'n/a';

$txt['membergroups_group_name'] = 'Member Group name';
$txt['membergroups_new_board'] = 'Visible Boards';
$txt['membergroups_new_board_desc'] = 'Boards the member group can see';
$txt['membergroups_new_board_post_groups'] = '<em>Note: normally, post groups don\'t need access because the group the member is in will give them access.</em>';
$txt['membergroups_new_as_inherit'] = 'inherit from';
$txt['membergroups_new_as_type'] = 'by type';
$txt['membergroups_new_as_copy'] = 'based off of';
$txt['membergroups_new_copy_none'] = '(none)';
$txt['membergroups_can_edit_later'] = 'You can edit them later.';

$txt['membergroups_edit_group'] = 'Edit Member Group';
$txt['membergroups_edit_name'] = 'Group name';
$txt['membergroups_edit_inherit_permissions'] = 'Inherit Permissions';
$txt['membergroups_edit_inherit_permissions_desc'] = 'Select &quot;No&quot; to enable group to have own permission set.';
$txt['membergroups_edit_inherit_permissions_no'] = 'No - Use Unique Permissions';
$txt['membergroups_edit_inherit_permissions_from'] = 'Inherit From';
$txt['membergroups_edit_hidden'] = 'Visibility';
$txt['membergroups_edit_hidden_no'] = 'Visible';
$txt['membergroups_edit_hidden_boardindex'] = 'Visible - Apart from in group key'; // ehm, what?
$txt['membergroups_edit_hidden_all'] = 'Invisible';
// Do not use numeric entities in the below string.
$txt['membergroups_edit_hidden_warning'] = 'Are you sure you want to disallow assignment of this group as a users primary group?\\n\\nDoing so will restrict assignment to additional groups only, and will update all current &quot;primary&quot; members to have it as an additional group only.';
$txt['membergroups_edit_desc'] = 'Group description';
$txt['membergroups_edit_group_type'] = 'Group type';
$txt['membergroups_edit_select_group_type'] = 'Select Group type';
$txt['membergroups_group_type_private'] = 'Private <span class="smalltext">(Membership must be assigned)</span>';
$txt['membergroups_group_type_protected'] = 'Protected <span class="smalltext">(Only administrators can manage and assign)</span>';
$txt['membergroups_group_type_request'] = 'Requestable <span class="smalltext">(Users may request membership)</span>';
$txt['membergroups_group_type_free'] = 'Free <span class="smalltext">(Users may leave and join group at will)</span>';
$txt['membergroups_group_type_post'] = 'Post Based <span class="smalltext">(Membership based on post count)</span>';
$txt['membergroups_min_posts'] = 'Required posts';
$txt['membergroups_online_color'] = 'Color in online list';
$txt['membergroups_icon_count'] = 'Number of icon images';
$txt['membergroups_icon_image'] = 'Icon image filename';
$txt['membergroups_icon_image_note'] = 'Upload icon images in to the default theme directory to enable selection.<br />Select the icon to change it.';
$txt['membergroups_max_messages'] = 'Max personal messages';
$txt['membergroups_max_messages_note'] = '0 = unlimited';
$txt['membergroups_max_messages_desc'] = 'Here you can set the limit of personal messages a user can keep on the server.<br />
To allow store an unlimited number of personal messages, you can set the value to 0';
$txt['membergroups_edit_save'] = 'Save';
$txt['membergroups_delete'] = 'Delete';
$txt['membergroups_confirm_delete'] = 'Are you sure you want to delete this group?';

$txt['membergroups_members_title'] = 'Viewing Group';
$txt['membergroups_members_group_members'] = 'Group Members';
$txt['membergroups_members_no_members'] = 'This group is currently empty';
$txt['membergroups_members_add_title'] = 'Add a member to this group';
$txt['membergroups_members_add_desc'] = 'List of Members to Add';
$txt['membergroups_members_add'] = 'Add Members';
$txt['membergroups_members_remove'] = 'Remove from Group';
$txt['membergroups_members_last_active'] = 'Last Active';
$txt['membergroups_members_additional_only'] = 'Add as additional group only.';
$txt['membergroups_members_group_moderators'] = 'Group Moderators';
$txt['membergroups_members_description'] = 'Description';
// Use javascript escaping in the below.
$txt['membergroups_members_deadmin_confirm'] = 'Are you sure you wish to remove yourself from the Administration group?';

$txt['membergroups_postgroups'] = 'Post groups';
$txt['membergroups_settings'] = 'Member Group Settings';
$txt['groups_manage_membergroups'] = 'Groups allowed to change member groups';
$txt['membergroups_select_permission_type'] = 'Select permission profile';
$txt['membergroups_images_url'] = '{theme URL}/images/group_icons/';
$txt['membergroups_select_visible_boards'] = 'Show boards';
$txt['membergroups_members_top'] = 'Members';
$txt['membergroups_name'] = 'Name';
$txt['membergroups_icons'] = 'Icons';

$txt['admin_browse_approve'] = 'Members whose accounts are awaiting approval';
$txt['admin_browse_approve_desc'] = 'From here you can manage all members who are waiting to have their accounts approved.';
$txt['admin_browse_activate'] = 'Members whose accounts are awaiting activation';
$txt['admin_browse_activate_desc'] = 'This screen lists all the members who have still not activated their accounts at your forum.';
$txt['admin_browse_awaiting_approval'] = 'Awaiting Approval [%1$d]';
$txt['admin_browse_awaiting_activate'] = 'Awaiting Activation [%1$d]';

$txt['admin_browse_username'] = 'User name';
$txt['admin_browse_email'] = 'E-mail Address';
$txt['admin_browse_ip'] = 'IP Address';
$txt['admin_browse_registered'] = 'Registered';
$txt['admin_browse_id'] = 'ID';
$txt['admin_browse_with_selected'] = 'With Selected';
$txt['admin_browse_no_members_approval'] = 'No members currently await approval.';
$txt['admin_browse_no_members_activate'] = 'No members currently have not activated their accounts.'; // now that's a funny (but correct) grammar :)

// Don't use entities in the below strings, except the main ones. (lt, gt, quot.)
$txt['admin_browse_warn'] = 'all selected members?';
$txt['admin_browse_outstanding_warn'] = 'all affected members?';
$txt['admin_browse_w_approve'] = 'Approve';
$txt['admin_browse_w_activate'] = 'Activate';
$txt['admin_browse_w_delete'] = 'Delete';
$txt['admin_browse_w_reject'] = 'Reject (Delete)';
$txt['admin_browse_w_remind'] = 'Remind';
$txt['admin_browse_w_approve_deletion'] = 'Approve (Delete Accounts)';
$txt['admin_browse_w_email'] = 'and send e-mail';
$txt['admin_browse_w_approve_require_activate'] = 'Approve and require activation';

$txt['admin_browse_filter_by'] = 'Filter By';
$txt['admin_browse_filter_show'] = 'Displaying';
$txt['admin_browse_filter_type_0'] = 'Unactivated new accounts';
$txt['admin_browse_filter_type_2'] = 'Unactivated e-mail changes';
$txt['admin_browse_filter_type_3'] = 'Unapproved new accounts';
$txt['admin_browse_filter_type_4'] = 'Unapproved account deletions';
$txt['admin_browse_filter_type_5'] = 'Unapproved "Under Age" Accounts';

$txt['admin_browse_outstanding'] = 'Outstanding Members';
$txt['admin_browse_outstanding_days_1'] = 'With all members who registered longer than';
$txt['admin_browse_outstanding_days_2'] = 'days ago';
$txt['admin_browse_outstanding_perform'] = 'Perform the following action';
$txt['admin_browse_outstanding_go'] = 'Perform Action';

$txt['check_for_duplicate'] = 'Check for duplicates';
$txt['dont_check_for_duplicate'] = 'Don\'t check for duplicates';
$txt['duplicates'] = 'Duplicates';

$txt['not_activated'] = 'Not activated';
