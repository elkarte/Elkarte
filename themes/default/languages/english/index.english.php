<?php
// Version: 1.1; index

global $forum_copyright;

// Locale (strftime, pspell_new) and spelling. (pspell_new, can be left as '' normally.)
// For more information see:
//   - http://www.php.net/function.pspell-new
//   - http://www.php.net/function.setlocale
// Again, SPELLING SHOULD BE '' 99% OF THE TIME!!  Please read this!
$txt['lang_locale'] = 'en_US';
$txt['lang_dictionary'] = 'en';
$txt['lang_spelling'] = 'american';

// Ensure you remember to use uppercase for character set strings.
$txt['lang_character_set'] = 'UTF-8';
// Character set and right to left?
$txt['lang_rtl'] = false;
// Capitalize day and month names?
$txt['lang_capitalize_dates'] = true;
// Number format.
$txt['number_format'] = '1,234.00';

$txt['sunday'] = 'Sunday';
$txt['monday'] = 'Monday';
$txt['tuesday'] = 'Tuesday';
$txt['wednesday'] = 'Wednesday';
$txt['thursday'] = 'Thursday';
$txt['friday'] = 'Friday';
$txt['saturday'] = 'Saturday';

$txt['sunday_short'] = 'Sun';
$txt['monday_short'] = 'Mon';
$txt['tuesday_short'] = 'Tue';
$txt['wednesday_short'] = 'Wed';
$txt['thursday_short'] = 'Thu';
$txt['friday_short'] = 'Fri';
$txt['saturday_short'] = 'Sat';

$txt['january'] = 'January';
$txt['february'] = 'February';
$txt['march'] = 'March';
$txt['april'] = 'April';
$txt['may'] = 'May';
$txt['june'] = 'June';
$txt['july'] = 'July';
$txt['august'] = 'August';
$txt['september'] = 'September';
$txt['october'] = 'October';
$txt['november'] = 'November';
$txt['december'] = 'December';

$txt['january_titles'] = 'January';
$txt['february_titles'] = 'February';
$txt['march_titles'] = 'March';
$txt['april_titles'] = 'April';
$txt['may_titles'] = 'May';
$txt['june_titles'] = 'June';
$txt['july_titles'] = 'July';
$txt['august_titles'] = 'August';
$txt['september_titles'] = 'September';
$txt['october_titles'] = 'October';
$txt['november_titles'] = 'November';
$txt['december_titles'] = 'December';

$txt['january_short'] = 'Jan';
$txt['february_short'] = 'Feb';
$txt['march_short'] = 'Mar';
$txt['april_short'] = 'Apr';
$txt['may_short'] = 'May';
$txt['june_short'] = 'Jun';
$txt['july_short'] = 'Jul';
$txt['august_short'] = 'Aug';
$txt['september_short'] = 'Sep';
$txt['october_short'] = 'Oct';
$txt['november_short'] = 'Nov';
$txt['december_short'] = 'Dec';

$txt['time_am'] = 'am';
$txt['time_pm'] = 'pm';

// Let's get all the main menu strings in one place.
$txt['home'] = 'Home';
$txt['community'] = 'Community';
// Sub menu labels
$txt['help'] = 'Help';
$txt['search'] = 'Search';
$txt['calendar'] = 'Calendar';
$txt['members'] = 'Members';
$txt['recent_posts'] = 'Recent Posts';

$txt['admin'] = 'Admin';
// Sub menu labels
$txt['errlog'] = 'Error Log';
$txt['package'] = 'Package Manager';
$txt['edit_permissions'] = 'Permissions';
$txt['modSettings_title'] = 'Features and Options';

$txt['moderate'] = 'Moderate';
// Sub menu labels
$txt['modlog_view'] = 'Moderation Log';
$txt['mc_emailerror'] = 'Unapproved Emails';
$txt['mc_reported_posts'] = 'Reported Posts';
$txt['mc_reported_pms'] = 'Reported Personal Messages';
$txt['mc_unapproved_attachments'] = 'Unapproved Attachments';
$txt['mc_unapproved_poststopics'] = 'Unapproved Posts and Topics';

$txt['pm_short'] = 'My Messages';
// Sub menu labels
$txt['pm_menu_read'] = 'Read your messages';
$txt['pm_menu_send'] = 'Send a message';

$txt['account_short'] = 'My Account';
// Sub menu labels
$txt['profile'] = 'Profile';
$txt['mydrafts'] = 'My Drafts';
$txt['summary'] = 'Summary';
$txt['theme'] = 'Look and Layout';
$txt['account'] = 'Account Settings';
$txt['forumprofile'] = 'Forum Profile';

$txt['view_unread_category'] = 'New Posts';
$txt['view_replies_category'] = 'New Replies';

$txt['login'] = 'Log in';
$txt['register'] = 'Register';
$txt['logout'] = 'Log out';
// End main menu strings.

$txt['save'] = 'Save';

$txt['modify'] = 'Modify';
$txt['forum_index'] = '%1$s - Index';
$txt['board_name'] = 'Board name';
$txt['posts'] = 'Posts';

$txt['member_postcount'] = 'Posts';
$txt['no_subject'] = '(No subject)';
$txt['view_profile'] = 'View Profile';
$txt['guest_title'] = 'Guest';
$txt['author'] = 'Author';
$txt['on'] = 'on';
$txt['remove'] = 'Remove';
$txt['start_new_topic'] = 'Start new topic';

// Use numeric entities in the below string.
$txt['username'] = 'Username';
$txt['password'] = 'Password';

$txt['username_no_exist'] = 'That username does not exist.';
$txt['no_user_with_email'] = 'There are no usernames associated with that email.';

$txt['board_moderator'] = 'Board Moderator';
$txt['remove_topic'] = 'Remove';
$txt['topics'] = 'Topics';
$txt['modify_msg'] = 'Modify message';
$txt['name'] = 'Name';
$txt['email'] = 'Email';
$txt['user_email_address'] = 'Email Address';
$txt['subject'] = 'Subject';
$txt['message'] = 'Message';
$txt['redirects'] = 'Redirects';

$txt['choose_pass'] = 'Choose password';
$txt['verify_pass'] = 'Verify password';
$txt['position'] = 'Position';
$txt['notify_announcements'] = 'Sign up to receive important site news by email';

$txt['profile_of'] = 'View the profile of';
$txt['total'] = 'Total';
$txt['posts_made'] = 'Posts';
$txt['topics_made'] = 'Topics';
$txt['website'] = 'Website';
$txt['contact'] = 'Contact Us';
$txt['warning_status'] = 'Warning Status';
$txt['user_warn_watch'] = 'User is on moderator watch list';
$txt['user_warn_moderate'] = 'User posts join approval queue';
$txt['user_warn_mute'] = 'User is banned from posting';
$txt['warn_watch'] = 'Watched';
$txt['warn_moderate'] = 'Moderated';
$txt['warn_mute'] = 'Muted';
$txt['warning_issue'] = 'Warn';

$txt['message_index'] = 'Message Index';
$txt['news'] = 'News';
$txt['page'] = 'Page';
$txt['prev'] = 'previous';
$txt['next'] = 'next';

$txt['post'] = 'Post';
$txt['error_occurred'] = 'An Error Has Occurred';
$txt['send_error_occurred'] = 'An error has occurred, <a href="{href}">please click here to try again</a>.';
$txt['require_field'] = 'This is a required field.';
$txt['started_by'] = 'Started by author';
$txt['topic_started_by'] = 'Started by %1$s';
$txt['topic_started_by_in'] = 'Started by %1$s in %2$s';
$txt['replies'] = 'Replies';
$txt['last_post'] = 'Last post';
$txt['first_post'] = 'First post';
$txt['last_poster'] = 'Last post author';

// @todo - Clean this up a bit. See notes in template.
// Just moved a space, so the output looks better when things break to an extra line.
$txt['last_post_message'] = '<span class="lastpost_link">%2$s </span><span class="board_lastposter">by %1$s</span><span class="board_lasttime"><strong>Last post: </strong>%3$s</span>';
$txt['boardindex_total_posts'] = '%1$s Posts in %2$s Topics by %3$s Members';
$txt['show'] = 'Show';
$txt['hide'] = 'Hide';
$txt['sort_by'] = 'Sort By';
$txt['sort_asc'] = 'Sort ascending';
$txt['sort_desc'] = 'Sort descending';

$txt['admin_login'] = 'Administration Log in';
// Use numeric entities in the below string.
$txt['topic'] = 'Topic';
$txt['help'] = 'Help';
$txt['notify'] = 'Notify';
$txt['unnotify'] = 'Unnotify';
$txt['notify_request'] = 'Do you want a notification email if someone replies to this topic?';
// Use numeric entities in the below string.
$txt['regards_team'] = "Regards,\nThe {forum_name_html_unsafe} Team.";
$txt['notify_replies'] = 'Notify of replies';
$txt['move_topic'] = 'Move';
$txt['move_to'] = 'Move to';
$txt['pages'] = 'Pages';
$txt['users_active'] = 'Active in past %1$d minutes';
$txt['personal_messages'] = 'Personal Messages';
$txt['reply_quote'] = 'Reply with quote';
$txt['reply'] = 'Reply';
$txt['reply_number'] = 'Reply #%1$s';
$txt['approve'] = 'Approve';
$txt['unapprove'] = 'Unapprove';
$txt['approve_all'] = 'approve all';
$txt['awaiting_approval'] = 'Awaiting Approval';
$txt['attach_awaiting_approve'] = 'Attachments awaiting approval';
$txt['post_awaiting_approval'] = 'Note: This message is awaiting approval by a moderator.';
$txt['there_are_unapproved_topics'] = 'There are %1$s topics and %2$s posts awaiting approval in this board. <a href="%3$s">Click here to view them</a>.';
$txt['send_message'] = 'Send message';

$txt['msg_alert_no_messages'] = 'you don\'t have any message';
$txt['msg_alert_one_message'] = 'you have <a href="%1$s">1 message</a>';
$txt['msg_alert_many_message'] = 'you have <a href="%1$s">%2$d messages</a>';
$txt['msg_alert_one_new'] = '1 is new';
$txt['msg_alert_many_new'] = '%1$d are new';
$txt['remove_message'] = 'Remove this message';

$txt['topic_alert_none'] = 'No messages...';
$txt['pm_alert_none'] = 'No messages...';

$txt['online_users'] = 'Users Online'; //Deprecated
$txt['online_now'] = 'Online Now';
$txt['personal_message'] = 'Personal Message';
$txt['jump_to'] = 'Jump to';
$txt['go'] = 'Go';
$txt['are_sure_remove_topic'] = 'Are you sure you want to remove this topic?';
$txt['yes'] = 'Yes';
$txt['no'] = 'No';

// @todo this string seems a good candidate for deprecation
$txt['search_on'] = 'on';

$txt['search'] = 'Search';
$txt['all'] = 'All';
$txt['search_entireforum'] = 'Entire Forum';
$txt['search_thisbrd'] = 'This board';
$txt['search_thistopic'] = 'This topic';
$txt['search_members'] = 'Members';

$txt['back'] = 'Back';
$txt['continue'] = 'Continue';
$txt['password_reminder'] = 'Password reminder';
$txt['topic_started'] = 'Topic started by';
$txt['title'] = 'Title';
$txt['post_by'] = 'Post by';
$txt['welcome_newest_member'] = 'Please welcome %1$s, our newest member.';
$txt['admin_center'] = 'Administration Center';
$txt['admin_session_active'] = 'You have an active admin session in place. We recommend to <strong><a class="strong" href="%1$s">end this session</a></strong> once you have finished your administrative tasks.';
$txt['admin_maintenance_active'] = 'Your forum is currently in maintenance mode, only admins can log in.  Remember to <strong><a class="strong" href="%1$s">exit maintenance</a></strong> once you have finished your administrative tasks.';
$txt['query_command_denied'] = 'The following MySQL errors are occurring, please verify your setup:';
$txt['query_command_denied_guests'] = 'It seems something has gone sour on the forum with the database. This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please report the following message to the administrator:';
$txt['query_command_denied_guests_msg'] = 'the command %1$s is denied on the database';
$txt['last_edit_by'] = '<span class="lastedit">Last Edit</span>: %1$s by %2$s';
$txt['notify_deactivate'] = 'Would you like to deactivate notification on this topic?';

$txt['date_registered'] = 'Date Registered';
$txt['date_joined'] = 'Joined';
$txt['date_joined_format'] = '%b %d, %Y';

$txt['recent_view'] = 'View all recent posts.';
$txt['is_recent_updated'] = '%1$s is the most recently updated topic';

$txt['male'] = 'Male';
$txt['female'] = 'Female';

$txt['error_invalid_characters_username'] = 'Invalid character used in user name.';

$txt['welcome_guest'] = 'Welcome, <strong>Guest</strong>. Please <a href="{login_url}" rel="nofollow">login</a>.';
$txt['welcome_guest_register'] = 'Welcome to <strong>{forum_name}</strong>. Please <a href="{login_url}" rel="nofollow">login</a> or <a href="{register_url}" rel="nofollow">register</a>.';
$txt['welcome_guest_activate'] = '<br />Did you miss your <a href="{activate_url}" rel="nofollow">activation email</a>?';

// @todo the following to sprintf
$txt['hello_member'] = 'Hey,';
// Use numeric entities in the below string.
$txt['hello_guest'] = 'Welcome,';
$txt['select_destination'] = 'Please select a destination';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['posted_by'] = 'Posted by';

$txt['icon_smiley'] = 'Smiley';
$txt['icon_angry'] = 'Angry';
$txt['icon_cheesy'] = 'Cheesy';
$txt['icon_laugh'] = 'Laugh';
$txt['icon_sad'] = 'Sad';
$txt['icon_wink'] = 'Wink';
$txt['icon_grin'] = 'Grin';
$txt['icon_shocked'] = 'Shocked';
$txt['icon_cool'] = 'Cool';
$txt['icon_huh'] = 'Huh';
$txt['icon_rolleyes'] = 'Roll Eyes';
$txt['icon_tongue'] = 'Tongue';
$txt['icon_embarrassed'] = 'Embarrassed';
$txt['icon_lips'] = 'Lips sealed';
$txt['icon_undecided'] = 'Undecided';
$txt['icon_kiss'] = 'Kiss';
$txt['icon_cry'] = 'Cry';
$txt['icon_angel'] = 'Innocent';

$txt['moderator'] = 'Moderator';
$txt['moderators'] = 'Moderators';

$txt['views'] = 'Views';
$txt['new'] = 'New';
$txt['no_redir'] = 'Redirected from %1$s';

$txt['view_all_members'] = 'View All Members';
$txt['view'] = 'View';

$txt['viewing_members'] = 'Viewing Members %1$s to %2$s';
$txt['of_total_members'] = 'of %1$s total members';

$txt['forgot_your_password'] = 'Forgot your password?';

$txt['date'] = 'Date';
// Use numeric entities in the below string.
$txt['from'] = 'From';
$txt['to'] = 'To';

$txt['board_topics'] = 'Topics';
$txt['members_title'] = 'Members';
$txt['members_list'] = 'Members List';
$txt['new_posts'] = 'New Posts';
$txt['old_posts'] = 'No New Posts';
$txt['redirect_board'] = 'Redirect Board';
$txt['redirect_board_to'] = 'Redirecting to %1$s';

$txt['sendtopic_send'] = 'Send';
$txt['report_sent'] = 'Your report has been sent successfully.';
$txt['topic_sent'] = 'Your email has been sent successfully.';

$txt['time_offset'] = 'Time Offset';
$txt['or'] = 'or';

$txt['mention'] = 'Notifications';
$txt['notifications'] = 'Notifications';
$txt['unread_notifications'] = 'You have %1$s unread notifications since your last visit.';
$txt['new_from_last_notifications'] = 'You have %1$s new notifications.';
$txt['forum_notification'] = 'Notifications from %1$s.';

$txt['your_ban'] = 'Sorry %1$s, you are banned from using this forum!';
$txt['your_ban_expires'] = 'This ban is set to expire %1$s.';
$txt['your_ban_expires_never'] = 'This ban is not set to expire.';
$txt['ban_continue_browse'] = 'You may continue to browse the forum as a guest.';

$txt['mark_as_read'] = 'Mark ALL messages as read';
$txt['mark_as_read_confirm'] = 'Are you sure you want to mark ALL messages as read?';
$txt['mark_these_as_read'] = 'Mark THESE messages as read';
$txt['mark_these_as_read_confirm'] = 'Are you sure you want to mark THESE messages as read?';

$txt['locked_topic'] = 'Locked Topic';
$txt['normal_topic'] = 'Normal Topic';
$txt['participation_caption'] = 'Topic you have posted in';

$txt['print'] = 'Print';
$txt['topic_summary'] = 'Topic Summary';
$txt['not_applicable'] = 'N/A';
$txt['name_in_use'] = 'The name %1$s is already in use by another member.';

$txt['total_members'] = 'Total Members';
$txt['total_posts'] = 'Total Posts';
$txt['total_topics'] = 'Total Topics';

$txt['mins_logged_in'] = 'Minutes to stay logged in';

$txt['preview'] = 'Preview';
$txt['always_logged_in'] = 'Always stay logged in';

$txt['logged'] = 'Logged';
// Use numeric entities in the below string.
$txt['ip'] = 'IP';

$txt['www'] = 'WWW';
$txt['link'] = 'Link';

$txt['by'] = 'by'; //Deprecated

$txt['hours'] = 'hours';
$txt['minutes'] = 'minutes';
$txt['seconds'] = 'seconds';

// Used upper case in Paid subscriptions management
$txt['hour'] = 'Hour';
$txt['days_word'] = 'days';

$txt['newest_member'] = ', our newest member.'; //Deprecated

$txt['search_for'] = 'Search for';
$txt['search_match'] = 'Match';

$txt['maintain_mode_on'] = 'Remember, this forum is in \'Maintenance Mode\'.';

$txt['read'] = 'Read'; //Deprecated
$txt['times'] = 'times'; //Deprecated
$txt['read_one_time'] = 'Read 1 time';
$txt['read_many_times'] = 'Read %1$d times';

$txt['forum_stats'] = 'Forum Stats';
$txt['latest_member'] = 'Latest Member';
$txt['total_cats'] = 'Total Categories';
$txt['latest_post'] = 'Latest Post';

$txt['here'] = 'here';
$txt['you_have_no_msg'] = 'You don\'t have any message...';
$txt['you_have_one_msg'] = 'You\'ve 1 message...<a href="%1$s">Click here to view it</a>';
$txt['you_have_many_msgs'] = 'You\'ve %2$d messages...<a href="%1$s">Click here to view them</a>';

$txt['total_boards'] = 'Total Boards';

$txt['print_page'] = 'Print Page';
$txt['print_page_text'] = 'Text only';
$txt['print_page_images'] = 'Text with Images';

$txt['valid_email'] = 'This must be a valid email address.';

$txt['info_center_title'] = '%1$s - Info Center';

$txt['send_topic'] = 'Share';
$txt['unwatch'] = 'Unwatch';
$txt['watch'] = 'Watch';

$txt['sendtopic_title'] = 'Send the topic &quot;%1$s&quot; to a friend.';
$txt['sendtopic_sender_name'] = 'Your name';
$txt['sendtopic_sender_email'] = 'Your email address';
$txt['sendtopic_receiver_name'] = 'Recipient\'s name';
$txt['sendtopic_receiver_email'] = 'Recipient\'s email address';
$txt['sendtopic_comment'] = 'Add a comment';

$txt['allow_user_email'] = 'Allow users to email me';

$txt['check_all'] = 'Check all';

// Use numeric entities in the below string.
$txt['database_error'] = 'Database Error';
$txt['try_again'] = 'Please try again.  If you come back to this error screen, report the error to an administrator.';
$txt['file'] = 'File';
$txt['line'] = 'Line';

// Use numeric entities in the below string.
$txt['tried_to_repair'] = 'ElkArte has detected and automatically tried to repair an error in your database.  If you continue to have problems, or continue to receive these emails, please contact your host.';
$txt['database_error_versions'] = '<strong>Note:</strong> Your database version is %1$s.';
$txt['template_parse_error'] = 'Template Parse Error!';
$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
$txt['template_parse_error_details'] = 'There was a problem loading the <span class="tt"><strong>%1$s</strong></span> template or language file.  Please check the syntax and try again - remember, single quotes (<span class="tt">\'</span>) often have to be escaped with a backslash (<span class="tt">\\</span>).  To see more specific error information from PHP, try <a href="%2$s%1$s">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="%3$s">use the default theme</a>.';
$txt['template_parse_undefined'] = 'An undefined error occurred during the parsing of this template';

$txt['today'] = 'Today at %1$s';
$txt['yesterday'] = 'Yesterday at %1$s';

// Relative times
$txt['rt_now'] = 'just now';
$txt['rt_minute'] = 'A minute ago';
$txt['rt_minutes'] = '%s minutes ago';
$txt['rt_hour'] = 'An hour ago';
$txt['rt_hours'] = '%s hours ago';
$txt['rt_day'] = 'A day ago';
$txt['rt_days'] = '%s days ago';
$txt['rt_week'] = 'A week ago';
$txt['rt_weeks'] = '%s weeks ago';
$txt['rt_month'] = 'A month ago';
$txt['rt_months'] = '%s months ago';
$txt['rt_year'] = 'A year ago';
$txt['rt_years'] = '%s years ago';

$txt['new_poll'] = 'New poll';
$txt['poll_question'] = 'Question';
$txt['poll_question_options'] = 'Question and Options';
$txt['poll_vote'] = 'Submit Vote';
$txt['poll_total_voters'] = 'Total Members Voted';
$txt['draft_saved_on'] = 'Draft last saved';
$txt['poll_results'] = 'View results';
$txt['poll_lock'] = 'Lock Voting';
$txt['poll_unlock'] = 'Unlock Voting';
$txt['poll_edit'] = 'Edit Poll';
$txt['poll'] = 'Poll';
$txt['one_day'] = '1 Day';
$txt['one_week'] = '1 Week';
$txt['two_weeks'] = '2 Weeks';
$txt['one_month'] = '1 Month';
$txt['two_months'] = '2 Months';
$txt['forever'] = 'Forever';
$txt['quick_login_dec'] = 'Login with username, password and session length';
$txt['one_hour'] = '1 Hour';
$txt['moved'] = 'MOVED';
$txt['moved_why'] = 'Please enter a brief description as to<br />why this topic is being moved.';
$txt['board'] = 'Board';
$txt['in'] = 'in';
$txt['sticky_topic'] = 'Pinned Topic';
$txt['split'] = 'SPLIT';

$txt['delete'] = 'Delete';

$txt['byte'] = 'B';
$txt['kilobyte'] = 'KB';
$txt['megabyte'] = 'MB';
$txt['gigabyte'] = 'MB';

$txt['more_stats'] = '[More Stats]';

// Use numeric entities in the below three strings.
$txt['code'] = 'Code';
$txt['code_select'] = '[Select]';
$txt['quote_from'] = 'Quote from';
$txt['quote'] = 'Quote';
$txt['quote_new'] = 'New topic';
$txt['follow_ups'] = 'Follow-ups';
$txt['topic_derived_from'] = 'Topic derived from %1$s';
$txt['edit'] = 'Edit';
$txt['quick_edit'] = 'Quick Edit';
$txt['post_options'] = 'More...';

$txt['set_sticky'] = 'Pin';
$txt['set_nonsticky'] = 'Unpin';
$txt['set_lock'] = 'Lock';
$txt['set_unlock'] = 'Unlock';

$txt['search_advanced'] = 'Show advanced options';
$txt['search_simple'] = 'Hide advanced options';

$txt['security_risk'] = 'MAJOR SECURITY RISK:';
$txt['not_removed'] = 'You have not removed %1$s';
$txt['not_removed_extra'] = '%1$s is a backup of %2$s that was not generated by ElkArte. It can be accessed directly and used to gain unauthorised access to your forum. You should delete it immediately.';
$txt['generic_warning'] = 'Warning';
$txt['agreement_missing'] = 'You are requiring new users to accept a registration agreement, however the file (agreement.txt) doesn\'t exist.';
$txt['agreement_accepted'] = 'You have just accepted the agreement.';
$txt['privacypolicy_accepted'] = 'You have just accepted the forum privacy policy.';

$txt['new_version_updates'] = 'You have just updated!';
$txt['new_version_updates_text'] = '<a href="{admin_url};area=credits#latest_updates">Click here to see what\'s new in this version of ElkArte!</a>!';

$txt['cache_writable'] = 'The cache directory is not writable - this will adversely affect the performance of your forum.';

$txt['page_created_full'] = 'Page created in %1$.3f seconds with %2$d queries.';

$txt['report_to_mod_func'] = 'Use this function to inform the moderators and administrators of an abusive or wrongly posted message.<br /><em>Please note that your email address will be revealed to the moderators if you use this.</em>';

$txt['online'] = 'Online';
$txt['member_is_online'] = '%1$s is online';
$txt['offline'] = 'Offline';
$txt['member_is_offline'] = '%1$s is offline';
$txt['pm_online'] = 'Personal Message (Online)';
$txt['pm_offline'] = 'Personal Message (Offline)';
$txt['status'] = 'Status';

$txt['skip_nav'] = 'Skip to main content';
$txt['go_up'] = 'Go Up';
$txt['go_down'] = 'Go Down';

$forum_copyright = '<a href="https://www.elkarte.net" title="ElkArte Forum" target="_blank" class="new_win">Powered by %1$s</a> | <a href="{credits_url}" title="Credits" target="_blank" class="new_win" rel="nofollow">Credits</a>';

$txt['birthdays'] = 'Birthdays:';
$txt['events'] = 'Events:';
$txt['birthdays_upcoming'] = 'Upcoming Birthdays:';
$txt['events_upcoming'] = 'Upcoming Events:';
// Prompt for holidays in the calendar, leave blank to just display the holiday's name.
$txt['calendar_prompt'] = 'Holidays:';
$txt['calendar_month'] = 'Month:';
$txt['calendar_year'] = 'Year:';
$txt['calendar_day'] = 'Day:';
$txt['calendar_event_title'] = 'Event Title';
$txt['calendar_event_options'] = 'Event Options';
$txt['calendar_post_in'] = 'Post In:';
$txt['calendar_edit'] = 'Edit Event';
$txt['event_delete_confirm'] = 'Delete this event?';
$txt['event_delete'] = 'Delete Event';
$txt['calendar_post_event'] = 'Post Event';
$txt['calendar'] = 'Calendar';
$txt['calendar_link'] = 'Link to Calendar';
$txt['calendar_upcoming'] = 'Upcoming Calendar';
$txt['calendar_today'] = 'Today\'s Calendar';
$txt['calendar_week'] = 'Week';
$txt['calendar_week_title'] = 'Week %1$d of %2$d';
$txt['calendar_numb_days'] = 'Number of Days:';
$txt['calendar_how_edit'] = 'how do you edit these events?';
$txt['calendar_link_event'] = 'Link Event To Post:';
$txt['calendar_confirm_delete'] = 'Are you sure you want to delete this event?';
$txt['calendar_linked_events'] = 'Linked Events';
$txt['calendar_click_all'] = 'click to see all %1$s';

$txt['moveTopic1'] = 'Post a redirection topic';
$txt['moveTopic2'] = 'Change the topic\'s subject';
$txt['moveTopic3'] = 'New subject';
$txt['moveTopic4'] = 'Change every message\'s subject';
$txt['move_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the move.';
$txt['movetopic_auto_board'] = '[BOARD]';
$txt['movetopic_auto_topic'] = '[TOPIC LINK]';
$txt['movetopic_default'] = 'This topic has been moved to [BOARD] - [TOPIC LINK]';
$txt['movetopic_redirect'] = 'Redirect to the moved topic';
$txt['movetopic_expires'] = 'Automatically remove the redirection topic';

$txt['merge_to_topic_id'] = 'ID of target topic';
$txt['split_topic'] = 'Split';
$txt['merge'] = 'Merge';
$txt['subject_new_topic'] = 'Subject For New Topic';
$txt['split_this_post'] = 'Only split this post.';
$txt['split_after_and_this_post'] = 'Split topic after and including this post.';
$txt['select_split_posts'] = 'Select posts to split.';

$txt['splittopic_notification'] = 'Post a message when the topic is split';
$txt['splittopic_default'] = 'One or more of the messages of this topic have been moved to [BOARD] - [TOPIC LINK]';
$txt['splittopic_move'] = 'Move the new topic to another board';

$txt['new_topic'] = 'New Topic';
$txt['split_successful'] = 'Topic successfully split into two topics.';
$txt['origin_topic'] = 'Origin Topic';
$txt['please_select_split'] = 'Please select which posts you wish to split.';
$txt['merge_successful'] = 'Topics successfully merged.';
$txt['new_merged_topic'] = 'Newly Merged Topic';
$txt['topic_to_merge'] = 'Topic to be merged';
$txt['target_board'] = 'Target board';
$txt['target_topic'] = 'Target topic';
$txt['merge_confirm'] = 'Are you sure you want to merge';
$txt['with'] = 'with';
$txt['merge_desc'] = 'This function will merge the messages of two topics into one topic. The messages will be sorted according to the time of posting. Therefore the earliest posted message will be the first message of the merged topic.';

$txt['theme_template_error'] = 'Unable to load the \'%1$s\' template.';
$txt['theme_language_error'] = 'Unable to load the \'%1$s\' language file.';

$txt['parent_boards'] = 'Sub-boards';

$txt['smtp_no_connect'] = 'Could not connect to SMTP host';
$txt['smtp_port_ssl'] = 'SMTP port setting incorrect; it should be 465 for SSL servers.';
$txt['smtp_bad_response'] = 'Couldn\'t get mail server response codes';
$txt['smtp_error'] = 'Ran into problems sending Mail. Error: ';
$txt['mail_send_unable'] = 'Unable to send mail to the email address \'%1$s\'';

$txt['mlist_search'] = 'Search For Members';
$txt['mlist_search_email'] = 'Search by email address';
$txt['mlist_search_group'] = 'Search by position';
$txt['mlist_search_name'] = 'Search by name';
$txt['mlist_search_website'] = 'Search by website';
$txt['mlist_search_results'] = 'Search results for';
$txt['mlist_search_by'] = 'Search by %1$s';

$txt['attach_downloaded'] = 'downloaded %1$d times';
$txt['attach_viewed'] = 'viewed %1$d times';

$txt['settings'] = 'Settings';
$txt['never'] = 'Never';
$txt['more'] = 'more';

$txt['hostname'] = 'Hostname';
$txt['you_are_post_banned'] = 'Sorry %1$s, you are banned from posting and sending personal messages on this forum.';
$txt['ban_reason'] = 'Reason';

$txt['add_poll'] = 'Add poll';
$txt['poll_options6'] = 'You may only select up to %1$s options.';
$txt['poll_remove'] = 'Remove Poll';
$txt['poll_remove_warn'] = 'Are you sure you want to remove this poll from the topic?';
$txt['poll_results_expire'] = 'Results will be shown when voting has closed';
$txt['poll_expires_on'] = 'Voting closes';
$txt['poll_expired_on'] = 'Voting closed';
$txt['poll_change_vote'] = 'Remove Vote';
$txt['poll_return_vote'] = 'Voting options';
$txt['poll_cannot_see'] = 'You cannot see the results of this poll at the moment.';

$txt['quick_mod_approve'] = 'Approve selected';
$txt['quick_mod_remove'] = 'Remove selected';
$txt['quick_mod_lock'] = 'Lock/Unlock selected';
$txt['quick_mod_sticky'] = 'Pin/Unpin selected';
$txt['quick_mod_move'] = 'Move selected to';
$txt['quick_mod_merge'] = 'Merge selected';
$txt['quick_mod_markread'] = 'Mark selected read';
$txt['quick_mod_go'] = 'Go';
$txt['quickmod_confirm'] = 'Are you sure you want to do this?';

$txt['spell_check'] = 'Spell Check';

$txt['quick_reply'] = 'Quick Reply';
$txt['quick_reply_warning'] = 'Warning! This topic is currently locked, only admins and moderators can reply.';
$txt['quick_reply_verification'] = 'After submitting your post you will be directed to the regular post page to verify your post %1$s.';
$txt['quick_reply_verification_guests'] = '(required for all guests)';
$txt['quick_reply_verification_posts'] = '(required for all users with less than %1$d posts)';
$txt['wait_for_approval'] = 'Note: this post will not display until it\'s been approved by a moderator.';

$txt['notification_enable_board'] = 'Are you sure you wish to enable notification of new topics for this board?';
$txt['notification_disable_board'] = 'Are you sure you wish to disable notification of new topics for this board?';
$txt['notification_enable_topic'] = 'Are you sure you wish to enable notification of new replies for this topic?';
$txt['notification_disable_topic'] = 'Are you sure you wish to disable notification of new replies for this topic?';

$txt['report_to_mod'] = 'Report Post';
$txt['issue_warning_post'] = 'Issue a warning because of this message';

$txt['like_post'] = 'Like';
$txt['unlike_post'] = 'Unlike';
$txt['likes'] = 'Likes';
$txt['liked_by'] = 'Liked by:';
$txt['liked_you'] = 'You';
$txt['liked_more'] = 'more';
$txt['likemsg_are_you_sure'] = 'You already liked this message, are you sure you want to remove your like?';

$txt['unread_topics_visit'] = 'Recent Unread Topics';
$txt['unread_topics_visit_none'] = 'No unread topics found since your last visit. <a href="{unread_all_url}" class="linkbutton">Click here to try all unread topics</a>';
$txt['unread_topics_all'] = 'All Unread Topics';
$txt['unread_replies'] = 'Updated Topics';

$txt['who_title'] = 'Who\'s Online';
$txt['who_and'] = ' and ';
$txt['who_viewing_topic'] = ' are viewing this topic.';
$txt['who_viewing_board'] = ' are viewing this board.';
$txt['who_member'] = 'Member';

// Current footer strings
$txt['valid_html'] = 'Valid HTML 5';
$txt['rss'] = 'RSS';
$txt['atom'] = 'Atom';
$txt['html'] = 'HTML';

$txt['guest'] = 'Guest';
$txt['guests'] = 'Guests';
$txt['user'] = 'User';
$txt['users'] = 'Users';
$txt['hidden'] = 'Hidden';
// Plural form of hidden for languages other than English
$txt['hidden_s'] = 'Hidden';
$txt['buddy'] = 'Buddy';
$txt['buddies'] = 'Buddies';
$txt['most_online_ever'] = 'Most Online Ever';
$txt['most_online_today'] = 'Most Online Today';

$txt['merge_select_target_board'] = 'Select the target board of the merged topic';
$txt['merge_select_poll'] = 'Select which poll the merged topic should have';
$txt['merge_topic_list'] = 'Select topics to be merged';
$txt['merge_select_subject'] = 'Select subject of merged topic';
$txt['merge_custom_subject'] = 'Custom subject';
$txt['merge_enforce_subject'] = 'Change the subject of all the messages';
$txt['merge_include_notifications'] = 'Include notifications?';
$txt['merge_check'] = 'Merge?';
$txt['merge_no_poll'] = 'No poll';

$txt['response_prefix'] = 'Re: ';
$txt['current_icon'] = 'Current icon';
$txt['message_icon'] = 'Message icon';

$txt['smileys_current'] = 'Current Smiley Set';
$txt['smileys_none'] = 'No Smileys';
$txt['smileys_forum_board_default'] = 'Forum/Board Default';

$txt['search_results'] = 'Search Results';
$txt['search_no_results'] = 'Sorry, no matches were found';

$txt['totalTimeLogged2'] = ' days, ';
$txt['totalTimeLogged3'] = ' hours and ';
$txt['totalTimeLogged4'] = ' minutes.';
$txt['totalTimeLogged5'] = 'd ';
$txt['totalTimeLogged6'] = 'h ';
$txt['totalTimeLogged7'] = 'm';

$txt['approve_thereis'] = 'There is'; //Deprecated
$txt['approve_thereare'] = 'There are'; //Deprecated
$txt['approve_member'] = 'one member'; //Deprecated
$txt['approve_members'] = 'members'; //Deprecated
$txt['approve_members_waiting'] = 'awaiting approval.'; //Deprecated
$txt['approve_one_member_waiting'] = 'There is <a href="%1$s">one member</a> awaiting approval.';
$txt['approve_many_members_waiting'] = 'There are <a href="%1$s">%2$d members</a> awaiting approval.';

$txt['notifyboard_turnon'] = 'Do you want a notification email when someone posts a new topic in this board?';
$txt['notifyboard_turnoff'] = 'Are you sure you do not want to receive new topic notifications for this board?';

$txt['find_members'] = 'Find Members';
$txt['find_username'] = 'Name, username, or email address';
$txt['find_buddies'] = 'Show Buddies Only?';
$txt['find_wildcards'] = 'Allowed Wildcards: *, ?';
$txt['find_no_results'] = 'No results found';
$txt['find_results'] = 'Results';
$txt['find_close'] = 'Close';

$txt['quickmod_delete_selected'] = 'Remove Selected';
$txt['quickmod_split_selected'] = 'Split Selected';

$txt['show_personal_messages_heading'] = 'New messages';
$txt['show_personal_messages'] = 'You have <strong>%1$s</strong> unread personal messages in your inbox.<br /><br /><a href="%2$s">Go to your inbox</a>';

$txt['help_popup'] = 'A little lost? Let me explain:';

$txt['previous_next_back'] = 'previous topic';
$txt['previous_next_forward'] = 'next topic';

$txt['upshrink_description'] = 'Shrink or expand the header.';

$txt['mark_unread'] = 'Mark unread';

$txt['ssi_not_direct'] = 'Please don\'t access SSI.php by URL directly; you may want to use the path (%1$s) or add ?ssi_function=something.';
$txt['ssi_session_broken'] = 'SSI.php was unable to load a session!  This may cause problems with logout and other functions - please make sure SSI.php is included before *anything* else in all your scripts!';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['preview_title'] = 'Preview post';
$txt['preview_fetch'] = 'Fetching preview...';
$txt['pm_error_while_submitting'] = 'The following error or errors occurred while sending this personal message:';
$txt['warning_while_submitting'] = 'Something happened, review it here:';
$txt['error_while_submitting'] = 'The message has the following error or errors that must be corrected before continuing:';
$txt['error_old_topic'] = 'Warning: this topic has not been posted in for at least %1$d days.<br />Unless you\'re sure you want to reply, please consider starting a new topic.';

$txt['split_selected_posts'] = 'Selected posts';
$txt['split_selected_posts_desc'] = 'The posts below will form a new topic after splitting.';
$txt['split_reset_selection'] = 'reset selection';

$txt['modify_cancel'] = 'Cancel';
$txt['mark_read_short'] = 'Mark Read';

$txt['hello_member_ndt'] = 'Hello';

$txt['unapproved_posts'] = 'Unapproved Posts (Topics: %1$d, Posts: %2$d)';

$txt['ajax_in_progress'] = 'Loading...';
$txt['ajax_bad_response'] = 'Invalid response.';

$txt['mod_reports_waiting'] = 'There are currently %1$d moderator reports open.';
$txt['pm_reports_waiting'] = 'There are currently %1$d personal message reports open.';

$txt['new_posts_in_category'] = 'Click to see the new posts in %1$s';
$txt['verification'] = 'Verification';
$txt['visual_verification_hidden'] = 'Please leave this box empty';
$txt['visual_verification_description'] = 'Type the letters shown in the picture';
$txt['visual_verification_sound'] = 'Listen to the letters';
$txt['visual_verification_request_new'] = 'Request another image';

// @todo Send email strings - should move?
$txt['send_email'] = 'Send email';
$txt['send_email_disclosed'] = 'Note this will be visible to the recipient.';
$txt['send_email_subject'] = 'Email Subject';

$txt['ignoring_user'] = 'You are ignoring this user.';
$txt['show_ignore_user_post'] = '<em>[Show me the post.]</em>';

$txt['spider'] = 'Spider';
$txt['spiders'] = 'Spiders';
$txt['openid'] = 'OpenID';

$txt['downloads'] = 'Downloads';
$txt['filesize'] = 'File size';

// Restore topic
$txt['restore_topic'] = 'Restore Topic';
$txt['restore_message'] = 'Restore';
$txt['quick_mod_restore'] = 'Restore Selected';

// Editor prompt.
$txt['prompt_text_email'] = 'Please enter the email address.';
$txt['prompt_text_ftp'] = 'Please enter the FTP address.';
$txt['prompt_text_url'] = 'Please enter the URL you wish to link to.';
$txt['prompt_text_img'] = 'Enter image location';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['autosuggest_delete_item'] = 'Delete Item';

// Bad Behavior
$txt['badbehavior_blocked'] = '<a href="http://www.bad-behavior.ioerror.us/">Bad Behavior</a> has blocked %1$s access attempts in the last 7 days.';

// Debug related - when $db_show_debug is true.
$txt['debug_templates'] = 'Templates: ';
$txt['debug_sub_templates'] = 'Sub templates: ';
$txt['debug_language_files'] = 'Language files: ';
$txt['debug_sheets'] = 'Style sheets: ';
$txt['debug_javascript'] = 'Scripts: ';
$txt['debug_files_included'] = 'Files included: ';
$txt['debug_kb'] = 'KB.';
$txt['debug_show'] = 'show';
$txt['debug_cache_hits'] = 'Cache hits: ';
$txt['debug_cache_seconds_bytes'] = '%1$ss - %2$s bytes';
$txt['debug_cache_seconds_bytes_total'] = '%1$ss for %2$s bytes';
$txt['debug_queries_used'] = 'Queries used: %1$d.';
$txt['debug_queries_used_and_warnings'] = 'Queries used: %1$d, %2$d warnings.';
$txt['debug_query_in_line'] = 'in <em>%1$s</em> line <em>%2$s</em>, ';
$txt['debug_query_which_took'] = 'which took %1$s seconds.';
$txt['debug_query_which_took_at'] = 'which took %1$s seconds at %2$s into request.';
$txt['debug_show_queries'] = '[Show Queries]';
$txt['debug_hide_queries'] = '[Hide Queries]';
$txt['debug_tokens'] = 'Tokens: ';
$txt['debug_browser'] = 'Browser ID: ';
$txt['debug_hooks'] = 'Hooks called: ';
$txt['debug_system_type'] = 'System: ';
$txt['debug_server_load'] = 'Server Load: ';
$txt['debug_script_mem_load'] = 'Script Memory Usage: ';
$txt['debug_script_cpu_load'] = 'Script CPU Time (user/system): ';

// Video embedding
$txt['preview_image'] = 'Video Preview Image';
$txt['ctp_video'] = 'Click to play video, double click to load video';
$txt['hide_video'] = 'Show/Hide video';
$txt['youtube'] = 'YouTube video:';
$txt['vimeo'] = 'Vimeo video:';
$txt['dailymotion'] = 'Dailymotion video:';

// Spoiler BBC
$txt['spoiler'] = 'Spoiler (click to show/hide)';

$txt['ok_uppercase'] = 'OK';

// Title of box for warnings that admins should see
$txt['admin_warning_title'] = 'Warning';

$txt['via'] = 'via';

$txt['like_post_stats'] = 'Like stats';

$txt['otp_token'] = 'Time-based One-time Password';
$txt['otp_enabled'] = 'Enable two factor authentication';
$txt['invalid_otptoken'] = 'Time-based One-time Password is invalid';
$txt['otp_used'] = 'Time-based One-time Password already used.<br /> Please wait a moment and use the next code.';
$txt['otp_generate'] = 'Generate';
$txt['otp_show_qr'] = 'Show QR-Code';
