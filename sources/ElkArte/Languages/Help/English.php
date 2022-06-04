<?php
// Version: 2.0; Help

global $helptxt;

$txt['close_window'] = 'Close window';

$helptxt['manage_boards'] = '
	<strong>Edit Boards</strong><br />
	In this menu you can create/reorder/remove boards, and the categories
	above them. For example, if you had a wide-ranging
	site that offered information on &quot;Sports&quot; and &quot;Cars&quot; and &quot;Music&quot;, these
	would be the top-level Categories you\'d create. Under each of these
	categories you\'d likely want to create hierarchical &quot;sub-categories&quot;,
	or &quot;Boards&quot; for topics within each. It\'s a simple hierarchy, with this structure: <br />
	<ul class="normallist">
		<li>
			<strong>Sports</strong>
			&nbsp;- A &quot;category&quot;
			<ul class="normallist">
				<li>
					<strong>Baseball</strong>
					&nbsp;- A board under the category of &quot;Sports&quot;
					<ul class="normallist">
						<li>
							<strong>Stats</strong>
							&nbsp;- A sub-board under the board of &quot;Baseball&quot;
						</li>
					</ul>
				</li>
				<li><strong>Football</strong>
				&nbsp;- A board under the category of &quot;Sports&quot;</li>
			</ul>
		</li>
	</ul>
	Categories allow you to break down the board into broad topics (&quot;Cars,
	&quot;Sports&quot;), and the &quot;Boards&quot; under them are the actual topics under which
	members can post. A user interested in Pintos
	would post a message under &quot;Cars&rarr;Pinto&quot;. Categories allow people to
	quickly find what their interests are: Instead of a &quot;Store&quot; you have
	&quot;Hardware&quot; and &quot;Clothing&quot; stores you can go to. This simplifies your
	search for &quot;pipe joint compound&quot; because you can go to the Hardware
	Store &quot;category&quot; instead of the Clothing Store (where you\'re unlikely
	to find pipe joint compound).<br />
	As noted above, a Board is a key topic underneath a broad category.
	If you want to discuss &quot;Pintos&quot; you\'d go to the &quot;Auto&quot; category and
	jump into the &quot;Pinto&quot; board to post your thoughts in that board.<br />
	Administrative functions for this menu item are to create new boards
	under each category, to reorder them (put &quot;Pinto&quot; behind &quot;Chevy&quot;), or
	to delete the board entirely.';

$helptxt['edit_news'] = '
	<ul class="normallist">
		<li>
			<strong>News</strong><br />
			This section allows you to set the text for news items displayed on the Board Index page.
			Add any item you want (e.g., &quot;Don\'t miss the conference this Tuesday&quot;). Each news item is displayed randomly and should be placed in a separate box.
		</li>
		<li>
			<strong>Newsletters</strong><br />
			This section allows you to send out newsletters to the members of the forum via personal message or email. First select the groups that you want to receive the newsletter, and those you don\'t want to receive the newsletter. If you wish, you can add additional members and email addresses that will receive the newsletter. Finally, input the message you want to send and select whether you want it to be sent to members as a personal message or as an email.
		</li>
		<li>
			<strong>Settings</strong><br />
				This section contains a few settings that relate to news and newsletters, including selecting what groups can edit forum news or send newsletters. There is also an setting to configure whether you want news feeds enabled on the forum, as well as a setting to configure the length (how many characters are displayed) for each news post from a news feed.
		</li>
	</ul>';

$helptxt['view_members'] = '
	<ul class="normallist">
		<li>
			<strong>View all Members</strong><br />
			View all members in the board. You are presented with a hyperlinked list of member names. You may click
			on any of the names to find details of the members (homepage, age, etc.), and as Administrator
			you are able to modify these parameters. You have complete control over members, including the
			ability to delete them from the forum.<br /><br />
		</li>
		<li>
			<strong>Awaiting Approval</strong><br />
			This section is only shown if you have enabled admin approval of all new registrations. Anyone who registers to join your
			forum will only become a full member once they have been approved by an admin. The section lists all those members who
			are still awaiting approval, along with their email and IP address. You can choose to either accept or reject (delete)
			any member on the list by checking the box next to the member and choosing the action from the drop-down box at the bottom
			of the screen. When rejecting a member you can choose to delete the member either with or without notifying them of your decision.<br /><br />
		</li>
		<li>
			<strong>Awaiting Activation</strong><br />
			This section will only be visible if you have activation of member accounts enabled on the forum. This section will list all
			members who have still not activated their new accounts. From this screen you can choose to either accept, reject or remind
			members with outstanding activations. As above you can also choose to email the member to inform them of the
			action you have taken.<br /><br />
		</li>
	</ul>';

$helptxt['ban_members'] = '<strong>Ban Members</strong><br />
	This provides the ability to &quot;ban&quot; users, to prevent people who have violated the trust of the board
	by spamming, trolling, etc. from continuing. This allows you to ban those users who are detrimental to your forum. As an admin,
	when you view messages, you can see each user\'s IP address used to post at that time. In the ban list,
	you simply type that IP address in, save, and they can no longer post from that location.<br />
	You can also ban people through their email address.';

$helptxt['featuresettings'] = '<strong>Features and Options</strong><br />
	There are several features in this section that can be changed to your preference.';

$helptxt['securitysettings'] = '<strong>Security and Moderation</strong><br />
	This section contains settings relating to the security and moderation of your forum.';

$helptxt['addonsettings'] = '<strong>Add-On Settings</strong><br />
	This section should contain any settings added by add-ons installed on your forum.';

$helptxt['time_format'] = '<strong>Time Format</strong><br />
	You have the power to adjust how the time and date look for yourself. There are a lot of little letters, but it\'s quite simple.
	The conventions follow PHP\'s strftime function and are described as below (more details can be found at <a href="http://www.php.net/manual/function.strftime.php" target="_blank" class="new_win">php.net</a>).<br />
	<br />
	The following characters are recognized in the format string:<br />
	<span class="smalltext">
	&nbsp;&nbsp;%a - abbreviated weekday name<br />
	&nbsp;&nbsp;%A - full weekday name<br />
	&nbsp;&nbsp;%b - abbreviated month name<br />
	&nbsp;&nbsp;%B - full month name<br />
	&nbsp;&nbsp;%d - day of the month (01 to 31) <br />
	&nbsp;&nbsp;%D<strong>*</strong> - same as %m/%d/%y <br />
	&nbsp;&nbsp;%e<strong>*</strong> - day of the month (1 to 31) <br />
	&nbsp;&nbsp;%H - hour using a 24-hour clock (range 00 to 23) <br />
	&nbsp;&nbsp;%I - hour using a 12-hour clock (range 01 to 12) <br />
	&nbsp;&nbsp;%m - month as a number (01 to 12) <br />
	&nbsp;&nbsp;%M - minute as a number <br />
	&nbsp;&nbsp;%p - either &quot;am&quot; or &quot;pm&quot; according to the given time<br />
	&nbsp;&nbsp;%R<strong>*</strong> - time in 24 hour notation <br />
	&nbsp;&nbsp;%S - second as a decimal number <br />
	&nbsp;&nbsp;%T<strong>*</strong> - current time, equal to %H:%M:%S <br />
	&nbsp;&nbsp;%y - 2 digit year (00 to 99) <br />
	&nbsp;&nbsp;%Y - 4 digit year<br />
	&nbsp;&nbsp;%% - a literal \'%\' character <br />
	<br />
	<em>* Does not work on Windows-based servers.</em></span>';

$helptxt['deleteAccount_posts'] = 'Replies Only: This will remove just the posts this member made in reply to other posts.<br />
	Topics and Replies: This will do the same as above, and additionally will remove all topic threads started by this member.';

$helptxt['live_news'] = '<strong>Live announcements</strong><br />
	This box shows recently updated announcements from <a href="https://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a>.
	You should check here every now and then for updates, new releases, and important information from ElkArte.';

$helptxt['registrations'] = '<strong>Registration Management</strong><br />
	This section contains all the functions that could be necessary to manage new registrations on the forum. It contains up to four
	sections which are visible depending on your forum settings. These are:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Register new member</strong><br />
			From this screen you can choose to register accounts for new members on their behalf. This can be useful in forums where registration is closed
			to new members, or in cases where the admin wishes to create a test account. If the option to require activation of the account
			is selected the member will be emailed a activation link which must be clicked before they can use the account. Similarly you can
			select to email the users new password to the stated email address.<br /><br />
		</li>
		<li>
			<strong>Edit Registration Agreement</strong><br />
			This allows you to set the text for the registration agreement displayed when members sign up for your forum.
			You can add or remove anything from the default registration agreement, which is included with ElkArte.<br /><br />
		</li>
		<li>
			<strong>Set Reserved Names</strong><br />
			Using this interface you can specify names which may not be used by your users.<br /><br />
		</li>
		<li>
			<strong>Settings</strong><br />
			This section will only be visible if you have permission to administrate the forum. From this screen you can decide on the registration method
			for use on your forum, as well as other registration related settings.
		</li>
	</ul>';

$helptxt['modlog'] = '<strong>Moderation Log</strong><br />
	This section allows members of the moderation team to track all the moderation actions that the forum moderators have performed. To ensure that
	moderators cannot remove references to the actions they have performed, entries may not be deleted until 24 hours after the action was taken.';
$helptxt['adminlog'] = '<strong>Administration Log</strong><br />
	This section allows members of the admin team to track some of the administrative actions that have occurred on the forum. To ensure that
	admins cannot remove references to the actions they have performed, entries may not be deleted until 24 hours after the action was taken.';
$helptxt['warning_enable'] = '<strong>User Warning System</strong><br />
	This feature enables members of the admin and moderation team to issue warnings to members - and to use a members warning level to determine the
	actions available to them on the forum. Upon enabling this feature a permission will be available within the permissions section to define
	which groups may assign warnings to members. Warning levels can be adjusted from a members profile. The following additional options are available:';
$helptxt['watch_enable'] = '<strong>Warning Level for Member Watch</strong><br />This setting defines the percentage warning level a member must reach to automatically assign a &quot;watch&quot; to the member. Any member who is being &quot;watched&quot; will appear in the relevant area of the moderation center.';
$helptxt['moderate_enable'] = '<strong>Warning Level for Post Moderation</strong><br />Any member passing the value of this setting will find all their posts require moderator approval before they appear to the forum	community. This will override any local board permissions which may exist related to post moderation.';
$helptxt['mute_enable'] = '<strong>Warning Level for Member Muting</strong><br />If this warning level is passed by a member they will find themselves under a post ban. The member will lose all posting rights.';
$helptxt['perday_limit'] = '<strong>Maximum Member Warning Points per Day</strong><br />This setting limits the amount of points a moderator may add/remove to any particular member in a twenty four hour period. This can be used to limit what a moderator can do in a small period of time. This setting can be disabled by setting to a value of zero. Note that any member with administrator permissions are not affected by this value.';
$helptxt['error_log'] = '<strong>Error Log</strong><br />
	The error log tracks every serious error encountered by users using your forum. It lists all of these errors by date which can be sorted
	by clicking the black arrow next to each date. Additionally you can filter the errors by clicking the picture next to each error statistic. This
	allows you to filter, for example, by member. When a filter is active the only results that will be displayed will be those that match that filter.';
$helptxt['theme_settings'] = '<strong>Theme Settings</strong><br />
	The settings screen allows you to change settings specific to a theme. These settings include options such as the themes directory and URL information but
	also options that affect the layout of a theme on your forum. Most themes will have a variety of user configurable options, allowing you to adapt a theme
	to suit your individual forum needs.';
$helptxt['smileys'] = '<strong>Smiley Center</strong><br />
	Here you can add and remove smileys and smiley sets.  Note importantly that if a smiley is in one set, it\'s in all sets - otherwise, it might
	get confusing for your users using different sets.<br /><br />

	You are also able to edit message icons from here, if you have them enabled on the settings page.';

$helptxt['calendar'] = '<strong>Manage Calendar</strong><br />
	Here you can modify the current calendar settings as well as add and remove holidays that appear on the calendar.';
$helptxt['calendar_settings'] = 'The calendar can be used for showing birthdays or for showing important moments happening in your community.<br /><br />Remember that usage of the calendar (posting events, viewing events, etc.) is controlled by permissions set on the permissions screen.';
$helptxt['cal_days_for_index'] = 'Max days in advance on board index:<br />If this is set to 7, the next week\'s worth of events will be shown.';
$helptxt['cal_showevents'] = 'Enables the highlighting of events on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_showholidays'] = 'This setting allows you to highlight holidays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_showbdays'] = 'This setting allows you to highlight birthdays on the Mini Calendars, Main Calendar, both places, or disable event highlighting.';
$helptxt['cal_export'] = 'Exports a text file in the iCal format for importing into other calendar applications.';
$helptxt['cal_daysaslink'] = 'Show days as link to \'Post Event\':<br />This will allow members to post events for that day when they click on that date.';
$helptxt['cal_allow_unlinked'] = 'Allow events not linked to posts:<br />Allow members to post events without requiring it to be linked with a post in a board.';
$helptxt['cal_defaultboard'] = 'Default Board to Post In:<br />Enter the default board to post events in.';
$helptxt['cal_showInTopic'] = 'Show linked events in topic display:<br />Check to show a link to the event at the top of topic view.';
$helptxt['cal_allowspan'] = 'Allow events to span multiple days:<br />Check to allow events to span multiple days.';
$helptxt['cal_maxspan'] = 'Max number of days an event can span:<br />Enter the maximum number of days that a single event can span.';
$helptxt['cal_minyear'] = 'Minimum year:<br />Select the &quot;first&quot; year on the calendar list.';
$helptxt['cal_maxyear'] = 'Maximum year:<br />Select the &quot;last&quot; year on the calendar list<br />';

$helptxt['serversettings'] = '<strong>Server Settings</strong><br />
	Here you can perform the core configuration for your forum. This section includes the database and URL settings as well as other
	important configuration items such as mail settings and caching. Think carefully whenever editing these settings as an error may
	render the forum inaccessible';
$helptxt['manage_files'] = '
	<ul class="normallist">
		<li>
			<strong>Browse Files</strong><br />
			Browse through all the attachments, avatars and thumbnails stored by the system.<br /><br />
		</li><li>
			<strong>Attachment Settings</strong><br />
			Configure where attachments are stored and set restrictions on the types of attachments.<br /><br />
		</li><li>
			<strong>Avatar Settings</strong><br />
			Configure where avatars are stored and manage resizing of avatars.<br /><br />
		</li><li>
			<strong>File Maintenance</strong><br />
			Check and repair any error in the attachment directory and delete selected attachments.<br /><br />
		</li>
	</ul>';

$helptxt['topicSummaryPosts'] = 'This allows you to set the number of previous posts shown in the topic summary at the reply screen.';
$helptxt['enableAllMessages'] = 'Set this to the <em>maximum</em> number of posts a topic can have to show the all link.  Setting this lower than &quot;Maximum messages to display in a topic page&quot; will simply mean it never gets shown, and setting it too high could slow down your forum.';
$helptxt['allow_guestAccess'] = 'Unchecking this box will stop guests from doing anything but very basic actions - login, register, password reminder, etc. - on your forum.  This is not the same as disallowing guest access to boards.';
$helptxt['userLanguage'] = 'Turning this option on will allow users to select which language file they use. It will not affect the default selection.';
$helptxt['trackStats'] = 'Stats:<br />This will allow users to see the latest posts and the most popular topics on your forum.
		It will also show several statistics like the most members online, new members and new topics.<hr />
		Page views:<br />Adds another column to the stats page with the number of page views on your forum.';
$helptxt['enable_unwatch'] = 'Enabling this option will allow users to selectively turn off new reply notifications for topics in which they had previously posted.';
$helptxt['titlesEnable'] = 'Switching Custom Titles on will allow members with the relevant permission to create a special title for themselves.
		This will be shown underneath the name.<br /><em>Example:</em><br />Jeff<br />Cool Guy';
$helptxt['onlineEnable'] = 'This will show an image to indicate whether the member is online or offline';
$helptxt['todayMod'] = 'This will format &quot;Today&quot; or &quot;Yesterday&quot; in a variety of formats instead of the full date.<br /><br />
		<strong>Examples:</strong><br /><br />
		<dl class="settings">
			<dt>Disabled</dt>
			<dd>October 3, 2009 at 12:59:18 am</dd>
			<dt>Relative</dt>
			<dd>2 Hours Ago</dd>
			<dt>Only Today</dt>
			<dd>Today at 12:59:18 am</dd>
			<dt>Today &amp; Yesterday</dt>
			<dd>Yesterday at 09:36:55 pm</dd>
		</dl>';
$helptxt['disableCustomPerPage'] = 'Check this option to stop users from customizing the amount of messages and topics to display per page on the Message Index and Topic Display page respectively.';
$helptxt['enablePreviousNext'] = 'This will show a link to the next and previous topic.';
$helptxt['pollMode'] = 'This selects whether polls are enabled or not. If polls are disabled, the regular topic without their polls are shown.
<br /><br />To choose who can post polls, view polls, and similar, you can allow and disallow those permissions. Remember this if polls don\'t seem to be working.';
$helptxt['enableVBStyleLogin'] = 'This will show a more compact login on every page of the forum for guests.';
$helptxt['enableCompressedOutput'] = 'This option will compress output to lower bandwidth consumption, but it requires zlib to be installed.';
$helptxt['databaseSession_enable'] = 'This option makes use of the database for session storage - it is best for load balanced servers, but helps with all timeout issues and can make the forum faster.';
$helptxt['databaseSession_loose'] = 'Turning this on will decrease the bandwidth your forum uses, and make it so clicking back will not reload the page - the downside is that the (new) icons won\'t update, among other things. (unless you click to that page instead of going back to it.)';
$helptxt['databaseSession_lifetime'] = 'This is the number of seconds for sessions to last after they haven\'t been accessed.  If a session is not accessed for too long, it is said to have &quot;timed out&quot;.  Anything higher than 2400 is recommended.';
$helptxt['cache_enable'] = 'ElkArte performs caching at a variety of levels. The higher the level of caching enabled the more CPU time will be spent retrieving cached information. If caching is available on your machine it is recommended that you try caching at level 1 first.';
$helptxt['cache_memcached'] = 'If you are using memcached you need to provide the server details. This should be entered as a comma separated list as shown in the example below:<br /><br/>&quot;server1,server2,server3:port,server4&quot;<br /><br />Note that if no port is specified the software will use port 11211, set this to 0 when using UNIX domain sockets.';
$helptxt['cache_cachedir'] = 'This setting is only for the filesystem based cache system. It specifies the path to the cache directory.  It is recommended that you place this in /tmp/ if you are going to use this, although it will work in any directory';
$helptxt['enableErrorLogging'] = 'This will log any errors, like a failed login, so you can see what went wrong.';
$helptxt['enableErrorQueryLogging'] = 'This will include the full query sent to the database in the error log.  Requires error logging to be turned on.<br /><br /><strong>Note:  This will affect the ability to filter the error log by the error message.</strong>';
$helptxt['allow_disableAnnounce'] = 'This will allow users to opt out of notification of topics you announce by checking the &quot;announce topic&quot; checkbox when posting.';
$helptxt['metadata_enabled'] = 'This will provide OG (Open Graph) and Schema.org microdata to allow themes, that support it, to embed into the HTML.  It allows search engines to better understand the information on your site pages, and provides for a better link sharing experience on social sites.  Data is made available to the board listing, topic listing and topic display views.';
$helptxt['disallow_sendBody'] = 'This option removes the possibility to receive the text of replies, posts and personal messages in notification emails.<br /><br />Often, members will reply to the notification email, which in most cases means the webmaster receives the reply.';
$helptxt['enable_contactform'] = 'This option adds a contact us button to the registration screen';
$helptxt['jquery_source'] = 'This will determine the source used to load the jQuery Library.  Auto will use the CDN first and if not available fall back to the local source.  Local will only use the local source, CDN will only load it from Google\'s Content Delivery Network';
$helptxt['jquery_default'] = 'If you want to use a version of jQuery different than the one that came with ElkArte, select this box and enter the version numer X.XX.X The local file must follow the naming conventing of jquery-X.XX.X.min.js for it to be loaded.';
$helptxt['jqueryui_default'] = 'If you want to use a version of jQueryUI different than the one that came with ElkArte, select this box and enter the version numer X.XX.X The local file must follow the naming conventing of jquery-ui-X.XX.X.min.js for it to be loaded.';
$helptxt['minify_css_js'] = 'This will remove unnecessary whitespace and comments from the files to reduce their size.  The minimized files are saved so further requests can instantly serve those files.<br />Note that the first time a compilation is needed/created, there may be a slight delay on that page load in order to create the file (this will also happen after the cache is cleared)';
$helptxt['combine_css_js'] = 'This will combine multiple CSS or JavaScript files per page as needed.  The combined files are saved so further requests can instantly serve those files.';
$helptxt['compactTopicPagesEnable'] = 'This will show the supplied number of surrounding pages.<br /><em>Example:</em>
		&quot;3&quot; to display: 1 ... 4 [5] 6 ... 9 <br />
		&quot;5&quot; to display: 1 ... 3 4 [5] 6 7 ... 9';
$helptxt['timeLoadPageEnable'] = 'This will show the time in seconds taken to create that page at the bottom of the board.';
$helptxt['removeNestedQuotes'] = 'This will remove, or limit the number of, nested quotes in a reply when citing the post via the quote link.';
$helptxt['charactersBeforeShowMore'] = 'This will limit the number of characters allowed in a quote block.  Quotes exceeding this size will be limited with a "read more" link to see the full quote.';
$helptxt['hideQuickQuoteButton'] = 'This will hide the "Quote Selection" button until the user selects some text.';
$helptxt['search_dropdown'] = 'This will show a search selection dropdown next to the quick search box.  From this you can choose to search the current site, current board (if in a board), current topic (if in a topic) or search for members.';
$helptxt['max_image_width'] = 'This allows you to set a maximum size for posted pictures. Pictures smaller than the maximum will not be affected. This also determines how attached images are displayed when a thumbnail is clicked on.';
$helptxt['mail_type'] = 'This setting allows you to choose either PHP\'s default settings, or to override those settings with SMTP.  PHP doesn\'t support using authentication with SMTP (which many hosts require now) so if you want that you should select SMTP.  Please note that SMTP can be slower, and some servers will not take user names and passwords.<br /><br />You don\'t need to fill in the SMTP settings if this is set to PHP\'s default.';
$helptxt['mail_batch_size'] = 'This setting determines how many emails will be sent per page load and can not be set greater than the maximum allowed per minute.<br />Leaving this as 0, the system will automatically determine a batch size to evenly spread the load and fill the quota.<br />If you want to set your own values, setting this to the same value as your limit is a good option for low per minute limits, or 1/6 of the limit for higher per minute limits.';
$helptxt['smtp_client'] = 'Used to identify this client to the SMTP server.<br />The field should contain the fully-qualified domain name (FQDN) of the SMTP client. In situations in which the client system does not have a meaningful domain name you can instead use an address literal formatted as [ipv4] or [IPv6:ipv6 address].<br />If left blank the system will attempt to detect this value for you.';

$helptxt['attachmentEnable'] = 'Enable/Disable the attachment system or disable only new attachments leaving old one available.';
$helptxt['automanage_attachments'] = 'This will create a directory structure based on the selected option.  This can be post date (subdividing attachments by year, or by year and month or by year, month and day) or simply adding a new directory when the space limit is reached.  Each directory created will have the same file count and total size restrictions.  This will help prevent directories from reaching a file or size limit.';
$helptxt['use_sub-directories_for_attachments'] = 'This will create all new directories as sub-directories under the main attachment directory.';
$helptxt['attachmentDirSizeLimit'] = ' Set how large the attachment folder can be.';
$helptxt['attachmentDirFileLimit'] = 'Set the max. number of files an individual attachment directory may contain';
$helptxt['attachmentPostLimit'] = 'Specify how large a single post\'s total upload size can be (in KiB), this is the cumulative size of all attachments made in a post.';
$helptxt['attachmentSizeLimit'] = 'Specify the largest size a single attachment in a post can have.';
$helptxt['attachmentNumPerPostLimit'] = 'Select the number of attachments a member can add per post.';

$helptxt['attachment_image_resize_enabled'] = 'Master on/off switch for this function.  Enabling this will 
	resize attachment images (.jpg, .png, .gif, .bmp, .webp) to fit within the specified bounds.  The image format 
	will be maintained unless the resized image is over the specified max allowed file size.  In this event, if 
	change format is enabled, the system may convert the image to WebP or JPEG for better compression.';
$helptxt['attachment_image_resize_enabled_size'] = 'or larger than %s KB.';
$helptxt['attachment_image_resize_reformat'] = '
	Selecting this option will allow the system to change images into WebP, if available, or JPEG ONLY
	when necessary.  The system will try to maintain the existing format unless the resized image is in
	excess of the maximum allowed file size.  It will not reencode JPG or change a PNG with transparency to JPG';
$helptxt['attachment_image_resize_width'] = 'This allows you to set a maximum width for attachment images. 
	Pictures smaller than the maximum will not be affected, larger images will be resized proportionately. 
	This allows you to accept a larger image on upload and have it resized to save space. The maximum 
	filesize parameter is still enforced.';
$helptxt['attachment_image_resize_height'] = 'This allows you to set a maximum height for attachment images. 
	Pictures smaller than the maximum will not be affected, larger images will be resized proportionately. 
	This allows you to accept a larger image on upload and have it resized to save space. 
	The maximum filesize parameter is still enforced.';

$helptxt['attachmentCheckExtensions'] = 'Check this box to enable attachment filtering, which will only allow files to be uploaded with the file extensions that you have defined.';
$helptxt['attachmentExtensions'] = 'Specify what attachment types are allowed, for example: jpg,png,gif  Remember to be careful in what you allow as some file extensions can cause a security risk to your website.';
$helptxt['attachment_autorotate'] = 'Selecting this option will allow the system to detect rotated images, typical of phone cameras, and automatically adjust the orientation such that the image top is oriented up. Requires either ImageMagick or both GD and Exif modules to be available.';
$helptxt['attachmentShowImages'] = 'If the uploaded file is a picture, this will automatically display it underneath the post.  If not enabled you will only see a filename and its dsize';
$helptxt['attachmentThumbnails'] = 'Enable this to show post images as a smaller thumbnail image, which when selected will expand to the full sized image.';
$helptxt['attachment_webp_enable'] = 'Enabling this will allow the system to create/save thumbnails and avatars 
	in WebP format.  It will also allow the image resize function, when enabled, to save an attachment as Webp when
 	necessary.';
$helptxt['attachmentThumbWidth'] = 'Only used with the &quot;Resize images when showing under posts&quot; option, the maximum width to resize attachments down from.  They will be resized proportionally.';
$helptxt['attachmentThumbHeight'] = 'Only used with the &quot;Resize images when showing under posts&quot; option, the maximum height to resize attachments down from.  They will be resized proportionally.';
$helptxt['attachment_image_reencode'] = 'Selecting this option will enable the re-encode of uploaded image attachments. Image re-encoding offers better security, however it will also render all animated images static.';
$helptxt['max_image_height'] = 'The maximum displayed height of an attached image.';
$helptxt['max_image_width'] = 'The maximum displayed width of an attached image.';
$helptxt['attachmentUploadDir'] = 'Select where you want the files uploaded to be stored on your server. This can be located outside your public html directory for additional security.';
$helptxt['attachment_transfer_empty'] = 'Enabling this will move all the files from the source directory to the new location, otherwise only the maximum allowed number of files according to the per-directory setting will be moved.';
$helptxt['avatar_reencode'] = 'Selecting this option will enable the re-encode of uploaded avatars. Image re-encoding offers better security, however it will also render all animated images static.';
$helptxt['karmaMode'] = 'Karma is a feature that shows the popularity of a member. Members, if allowed, can
	\'applaud\' or \'smite\' other members, which is how their popularity is calculated. You can change the
	number of posts needed to have a &quot;karma&quot;, the time between smites or applauds, and if administrators
	have to wait this time as well.<br /><br />Whether or not groups of members can smite others is controlled by
	a permission.  If you have trouble getting this feature to work for everyone, double check your permissions.';
$helptxt['localCookies'] = 'The system uses cookies to store login information on the client computer.
	Cookies can be stored globally (myserver.com) or locally (myserver.com/path/to/forum).<br />
	Check this option if you\'re experiencing problems with users getting logged out automatically.<hr />
	Globally stored cookies are less secure when used on a shared webserver (like Tripod).<hr />
	Local cookies don\'t work outside the forum directory so, if your forum is stored at www.myserver.com/forum, pages like www.myserver.com/index.php cannot access the account information.
	Especially when using SSI.php, global cookies are recommended.';
$helptxt['enableBBC'] = 'Selecting this option will allow your members to use Bulletin Board Code (BBC) throughout the forum, allowing users to format their posts with images, type formatting and more.';
$helptxt['time_offset'] = 'Not all forum administrators want their forum to use the same time zone as the server upon which it is hosted. Use this option to specify a time difference (in hours) from which the forum should operate from the server time. Negative and decimal values are permitted.';
$helptxt['default_timezone'] = 'The server timezone tells PHP where your server is located. You should ensure this is set correctly, preferably to the country/city in which the server is located. You can find out more information on the <a href="https://www.php.net/manual/en/timezones.php" target="_blank">PHP Site</a>.';
$helptxt['spamWaitTime'] = 'Here you can select the amount of time that must pass between postings. This can be used to stop people from "spamming" your forum by limiting how often they can post.';

$helptxt['enablePostHTML'] = 'This will allow the posting of some basic HTML tags:
	<ul class="normallist enablePostHTML">
		<li>&lt;b&gt;, &lt;u&gt;, &lt;i&gt;, &lt;s&gt;, &lt;em&gt;, &lt;ins&gt;, &lt;del&gt;</li>
		<li>&lt;a href=&quot;&quot;&gt;</li>
		<li>&lt;img src=&quot;&quot; alt=&quot;&quot; /&gt;</li>
		<li>&lt;br /&gt;, &lt;hr /&gt;</li>
		<li>&lt;pre&gt;, &lt;blockquote&gt;</li>
	</ul>';

// Initial theme settings - Manage and Install
$helptxt['themes'] = 'Here you can select whether the default theme can be chosen, what theme guests will use, as well as other options. Click on a theme to the right to change the settings for it.';
$helptxt['theme_install'] = 'This section permits you to install new themes. You do this by uploading an archived file for the theme from your personal computer, installing from a theme directory on the host server or by copying the default theme and renaming that copied file.<br /><br />Please remember this: the archived file or directory must have a <span class="alert">theme_info.xml</span> definition file as a part of the archive or the directory.';
$helptxt['theme_forum_theme'] = 'Changing the overall forum default does not affect members that have selected another available theme. You must also \'Reset\' all members to force them to the new forum default. You can also set a forum default theme that is seen by guests and then reset members to a different theme. <br /><br />Remember that when permitted to select their own themes, members can overide the theme set by you.';

// Theme Management and Options - Theme settings
$helptxt['themeadmin_list_reset'] = 'On rare occasions the path to the theme will be lost and your forum will not display properly. This may be due to a mistake by an Admin, database errors, failed software updates, mod installations or some other event. Resetting the themes URLs and directories will usually fix this problem.';
$helptxt['themeadmin_delete_help'] = 'The default theme cannot be deleted as doing so would break your forum and would break other themes. However, you can delete any theme that has the red \'X\' next to it by clicking on that \'X\'. <br /><br /> Remember this: Deleting a theme does not remove it from the server, it only removes the themes availability to be used on the forum. You will need to FTP into your server or use the host provided panel to remove the custom theme from the server. Do not ever delete the theme named \'default\'.';

$helptxt['enableVideoEmbeding'] = 'This allows automatic conversion of standard URLs into an embedded video when the post is viewed.  Currently supports YouTube, Vimeo, TikTok, Twitter, Facebook, Instagram and Dailymotion links';
$helptxt['enableCodePrettify'] = 'This will load the Prettify script which will color highlight code used in code tags.  It adds styles to code snippets so that tokens stand out and your users can more easily read the code.';
// @todo Add more information about how to use them here.
$helptxt['xmlnews_enable'] = 'Allows people to link to <a href="%1$s?action=.xml;sa=news" target="_blank" class="new_win">Recent news</a>
	and similar data.  It is also recommended that you limit the number of recent posts/news because, when RSS data
	is displayed in some clients, like Trillian, it is expected to be truncated.';
$helptxt['hotTopicPosts'] = 'Change the number of posts for a topic to reach the state of a &quot;hot&quot; or
	&quot;very hot&quot; topic.  Select the likes option to base this state on the number of likes instead of the number of posts';
$helptxt['globalCookies'] = 'Makes login cookies available across subdomains.  For example, if...<br />
	Your site is at http://www.myserver.com/,<br />
	And your forum is at http://forum.myserver.com/,<br />
	Using this option will allow you to access the forum\'s cookie on your site.  Do not enable this if there are other subdomains (like hacker.elkarte.net) not controlled by you.<br />
	This option does not function when local cookies are enabled.';
$helptxt['globalCookiesDomain'] = 'Define the main domain to be used when login cookies are available across subdomains';
$helptxt['httponlyCookies'] = 'With this setting on, cookies will not be accessible by scripting languages, such as JavaScript. This setting can help to reduce identity theft through XSS attacks. This may cause issues with some third party scripts but is recommended to be on when possible.';
$helptxt['secureCookies'] = 'Enabling this option will force the cookies created for users on your forum to be marked as secure. Only enable this option if you are using HTTPS throughout your site as it will break cookie handling otherwise!';
$helptxt['admin_session_lifetime'] = 'This controls the length of time an admin session can remain active. Once this timer expires the session will end, requiring you to enter your admin credentials to continue accessing the admin area. The minimum value is 5 minutes, the maximum allowed value is 14400 minutes (equals a day). It is strongly recommended to use a value less than 60 minutes for security reasons.';
$helptxt['auto_admin_session'] = 'This controls whether an administrative session is activated during logon or not.';
$helptxt['securityDisable'] = 'This <em>disables</em> the additional password check for the administration section. This is not recommended!';
$helptxt['securityDisable_why'] = 'This is your current password. (the same one you use to login.)<br /><br />Having to type this helps ensure that you want to do whatever administration you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['securityDisable_moderate'] = 'This <em>disables</em> the additional password check for the moderation section. This is not recommended!';
$helptxt['securityDisable_moderate_why'] = 'This is your current password. (the same one you use to login.)<br /><br />Having to type this helps ensure that you want to do whatever moderation you are doing, and that it is <strong>you</strong> doing it.';
$helptxt['enableOTP'] = 'Enabling this feature allows another layer of security for a member\'s account. Two-factor authentication, or 2FA, is a way of logging into websites that requires more than just a password. Using a password to log into a website is susceptible to security threats, because it represents a single piece of information a malicious person needs to acquire. The added security that 2FA provides is requiring additional information to sign in.<br /><br />A Time-based One-Time Password (TOTP) application such as Google Authenticator or Authy automatically generates an authentication code that changes after a certain period of time.';
$helptxt['emailmembers'] = 'In this message you can use a few &quot;variables&quot;.  These are:<br />
	{$board_url} - The URL to your forum.<br />
	{$current_time} - The current time.<br />
	{$member.email} - The current member\'s email.<br />
	{$member.link} - The current member\'s link.<br />
	{$member.id} - The current member\'s ID.<br />
	{$member.name} - The current member\'s name.  (for personalization.)<br />
	{$latest_member.link} - The most recently registered member\'s link.<br />
	{$latest_member.id} - The most recently registered member\'s ID.<br />
	{$latest_member.name} - The most recently registered member\'s name.';
$helptxt['attachmentEncryptFilenames'] = 'Encrypting attachment file names allows you to have more than one attachment of the same name and heightens security.  It, however, could make it more difficult to rebuild your database if something drastic happened.';

$helptxt['failed_login_threshold'] = 'Set the number of failed login attempts before directing the user to the password reminder screen.';
$helptxt['loginHistoryDays'] = 'The number of days to keep login history under user profile history. Default is 30 days.';
$helptxt['oldTopicDays'] = 'If this option is enabled a warning will be displayed to the user when attempting to reply to a topic which has not had any new replies for the amount of time, in days, specified by this setting. Set this setting to 0 to disable the feature.';
$helptxt['edit_wait_time'] = 'Number of seconds allowed for a post to be edited before logging the last edit date.';
$helptxt['edit_disable_time'] = 'Number of minutes allowed to pass before a user can no longer edit a post they have made. Set to 0 disable. <br /><br /><em>Note: This will not affect any user who has permission to edit other people\'s posts.</em>';
$helptxt['preview_characters'] = 'This option sets the number of available characters for the first and last message of the topic preview.  <strong>Note</strong> this only makes the information available to the theme, the theme must support the &quot;Enable hover  previews on the message index&quot; setting';
$helptxt['posts_require_captcha'] = 'This setting will force users to pass anti-spam bot verification each time they make a post to a board. Only users with a post count below the number set will need to enter the code - this should help combat automated spamming scripts.';
$helptxt['lastActive'] = 'Set the number of minutes since their last activity to display people as active on the board index. Default is 15 minutes.';

$helptxt['customoptions'] = 'This section defines the options that a user may choose from a drop down list. There are a few key points to note in this section:
	<ul class="normallist">
		<li><strong>Default Option:</strong> Whichever option box has the &quot;radio button&quot; next to it selected will be the default selection for the user when they enter their profile.</li>
		<li><strong>Removing Options:</strong> To remove an option simply empty the text box for that option - all users with that selected will have their option cleared.</li>
		<li><strong>Reordering Options:</strong> You can reorder the options by moving text around between the boxes. However - an important note - you must make sure you do <strong>not</strong> change the text when reordering options as otherwise user data will be lost.</li>
	</ul>';

$helptxt['autoOptDatabase'] = 'This option optimizes the database every so many days.  Set it to 1 to make a daily optimization.  You can also specify a maximum number of online users, so that you won\'t overload your server or inconvenience too many users.';
$helptxt['autoFixDatabase'] = 'This will automatically fix broken tables and resume like nothing happened.  This can be useful, because the only way to fix it is to REPAIR the table, and this way your forum won\'t be down until you notice.  It does email you when this happens.';

$helptxt['enableParticipation'] = 'This shows a little icon on the topics the user has posted in.';
$helptxt['enableFollowup'] = 'This allows members to start new topics quoting the text of any message.';

$helptxt['db_persist'] = 'Keeps the connection active to increase performance.  If you aren\'t on a dedicated server, this may cause you problems with your host.';
$helptxt['ssi_db_user'] = 'Optional setting to use a different database user and password when you are using SSI.php.';

$helptxt['countChildPosts'] = 'Checking this option will mean that posts and topics in a board\'s sub-board will count toward its totals on the index page.<br /><br />This will make things notably slower, but means that a parent with no posts in it won\'t show \'0\'.';
$helptxt['allow_ignore_boards'] = 'Checking this option will allow users to select boards they wish to ignore.';
$helptxt['deny_boards_access'] = 'Checking this option will allow you to deny access to certain boards based on membergroup access';

$helptxt['who_enabled'] = 'This option allows you to turn on or off the ability for users to see who is browsing the forum and what they are doing.';

$helptxt['recycle_enable'] = '&quot;Recycles&quot; deleted topics and posts to the specified board.';

$helptxt['enableReportPM'] = 'This option allows your users to report personal messages they receive to the administration team. This may be useful in helping to track down any abuse of the personal messaging system.';
$helptxt['max_pm_recipients'] = 'This option allows you to set the maximum amount of recipients allowed in a single personal message sent by a forum member. This may be used to help stop spam abuse of the PM system. Note that users with permission to send newsletters are exempt from this restriction. Set to zero for no limit.';
$helptxt['pm_posts_verification'] = 'This setting will force users to enter a code shown on a verification image each time they are sending a personal message. Only users with a post count below the number set will need to enter the code - this should help combat automated spamming scripts.';
$helptxt['pm_posts_per_hour'] = 'This will limit the number of personal messages which may be sent by a user in a one hour period. This does not affect admins or moderators.';

$helptxt['default_personal_text'] = 'Sets the default text a new user will have as their &quot;personal text.&quot; This option is not available when personal text is disabled, or when users can set their personal text on registration for themselves.';

$helptxt['modlog_enabled'] = 'Logs all moderation actions.';

$helptxt['registration_method'] = 'This option determines what method of registration is used for people wishing to join your forum. You can select from:<br /><br />
	<ul class="normallist">
		<li>
			<strong>Registration Disabled</strong><br />
				Disables the registration process, which means that no new members can register to join your forum.<br />
		</li><li>
			<strong>Immediate Registration</strong><br />
				New members can login and post immediately after registering on your forum.<br />
		</li><li>
			<strong>Email Activation</strong><br />
				When this option is enabled any members registering with the forum will have an activation link emailed to them which they must click before they can become full members.<br />
		</li><li>
			<strong>Admin Approval</strong><br />
				This option will make it so that all new members registering with the forum will need to be approved by an admin before they become full members.
		</li>
	</ul>';

$helptxt['send_validation_onChange'] = 'When this option is checked all members who change their email address in their profile will have to reactivate their account from an email sent to the new address';
$helptxt['send_welcomeEmail'] = 'When this option is enabled all new members will be sent an email welcoming them to your community';
$helptxt['password_strength'] = 'This setting determines the strength required for passwords selected by your forum users. The stronger the password, the harder it should be to compromise the member\'s account.
	Its possible options are:
	<ul class="normallist">
		<li><strong>Low:</strong> The password must be at least four characters long.</li>
		<li><strong>Medium:</strong> The password must be at least eight characters long, and can not be part of a user name or email address.</li>
		<li><strong>High:</strong> As for medium, except the password must also contain a mixture of upper and lower case letters, and at least one digit.</li>
	</ul>';
$helptxt['enable_password_conversion'] = 'By enabling this setting, ElkArte will attempt to detect passwords stored in other formats and convert them for use in this software.  Typically this is used for converted forums, but may have other uses as well.  Disabling this prevents a user from logging in using their password after a conversion and would need to reset their password.';

$helptxt['coppaAge'] = 'The value specified in this box will determine the minimum age that new members must be to be granted immediate access to the forums.
	On registration they will be prompted to confirm whether they are over this age, and if not will either have their application rejected or suspended awaiting parental approval - dependant on the type of restriction chosen.
	If a value of 0 is chosen for this setting then all other age restriction settings shall be ignored.';
$helptxt['coppaType'] = 'If age restrictions are enabled, then this setting will define that happens when a user below the minimum age attempts to register with your forum. There are two possible choices:
	<ul class="normallist">
		<li>
			<strong>Reject Their Registration:</strong><br />
				Any new member below the minimum age will have their registration rejected immediately.<br />
		</li><li>
			<strong>Require Parent/Guardian Approval</strong><br />
				Any new member who attempts to register and is below the minimum permitted age will have their account marked as awaiting approval, and will be presented with a form upon which their parents must give permission to become a member of the forums.
				They will also be presented with the forum contact details entered on the settings page, so they can send the form to the administrator by mail or fax.
		</li>
	</ul>';
$helptxt['coppaPost'] = 'The contact boxes are required so that forms granting permission for underage registration can be sent to the forum administrator. These details will be shown to all new minors, they are required for parent/guardian approval. At the very least a postal address or fax number must be provided.';

$helptxt['allow_hideOnline'] = 'With this option enabled all members will be able to hide their online status from other users (except administrators). If disabled only users who can moderate the forum can hide their presence. Note that disabling this option will not change any member\'s current status - it just stops them from hiding themselves in the future.';

$helptxt['latest_support'] = 'This panel shows you some of the most common problems and questions on your server configuration. Don\'t worry, this information isn\'t logged or anything.<br /><br />If this stays as &quot;Retrieving support information...&quot;, your computer probably cannot connect to the website.';
$helptxt['latest_packages'] = 'Here you can see some of the most popular and some random packages, with quick and easy installations.<br /><br />If this section doesn\'t show up, your computer probably cannot connect to <a href="https://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a>.';
$helptxt['latest_themes'] = 'This area shows a few of the latest and most popular themes from <a href="https://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a>.  It may not show up properly if your computer can\'t find <a href="https://www.elkarte.net/" target="_blank" class="new_win">www.elkarte.net/</a>, though.';

$helptxt['secret_why_blank'] = 'For your security, your password and the answer to your secret question are encrypted so that ElkArte will never tell you, or anyone else, what they are.';
$helptxt['moderator_why_missing'] = 'Since moderation is done on a board-by-board basis, you have to make members moderators from the <a href="%1$s?action=admin;area=manageboards" target="_blank" class="new_win">board management interface</a>.';

$helptxt['permissions'] = 'Permissions are how you either allow groups to, or deny groups from, doing specific things.<br /><br />You can modify multiple boards at once with the checkboxes, or look at the permissions for a specific group by clicking \'Modify.\'';
$helptxt['permissions_board'] = 'If a board is set to \'Global,\' it means that the board will not have any special permissions.  \'Local\' means it will have its own permissions - separate from the global ones.  This allows you to have a board that has more or less permissions than another, without requiring you to set them for each and every board.';
$helptxt['permissions_quickgroups'] = 'These allow you to use the &quot;default&quot; permission setups - standard means \'nothing special\', restrictive means \'like a guest\', moderator means \'what a moderator has\', and lastly \'maintenance\' means permissions very close to those of an administrator.';
$helptxt['permission_enable_deny'] = 'Denying permissions can be useful when you want take away permission from certain members. You can add a membergroup with a \'deny\'-permission to the members you wish to deny a permission.<br /><br />Use with care, a denied permission will stay denied no matter what other membergroups the member is in.';
$helptxt['permission_enable_postgroups'] = 'Enabling permissions for post count based groups will allow you to attribute permissions to members that have posted a certain amount of messages. The permissions of the post count based groups are <em>added</em> to the permissions of the regular membergroups.';
$helptxt['membergroup_guests'] = 'The Guests membergroup are all users that are not logged in.';
$helptxt['membergroup_regular_members'] = 'The Regular Members are all members that are logged in, but that have no primary membergroup assigned.';
$helptxt['membergroup_administrator'] = 'The administrator can, per definition, do anything and see any board. Thus, there are no permission settings for the administrator.';
$helptxt['membergroup_moderator'] = 'The Moderator member group is a special member group. Permissions and settings assigned to this group apply to moderators but only <em>on the boards they moderate</em>. Outside these boards they\'re just like any other member.';
$helptxt['membergroups'] = 'There are two types of groups that your members can be part of. These are:
	<ul class="normallist">
		<li><strong>Regular Groups:</strong> A regular group is a group to which members are not automatically put into. To assign a member to be in a group simply go to their profile and click &quot;Account Settings&quot;. From here you can assign them any number of regular groups to which they will be part.</li>
		<li><strong>Post Groups:</strong> Unlike regular groups post based groups cannot be assigned. Instead, members are automatically assigned to a post based group when they reach the minimum number of posts required to be in that group.</li>
	</ul>';

$helptxt['calendar_how_edit'] = 'You can edit these events by clicking on the red asterisk (*) next to their names.';

$helptxt['maintenance_backup'] = 'This area allows you to save a copy of all the posts, settings, members, and other information in your forum to a very large file.<br /><br />It is recommended that you do this often, perhaps weekly, for safety and security.';
$helptxt['maintenance_rot'] = 'This allows you to <strong>completely</strong> and <strong>irrevocably</strong> remove old topics.  It is recommended that you try to make a backup first, just in case you remove something you didn\'t mean to.<br /><br />Use this option with care.';
$helptxt['maintenance_members'] = 'This allows you to <strong>completely</strong> and <strong>irrevocably</strong> remove member accounts from your forum.  It is <strong>highly</strong> recommended that you try to make a backup first, just in case you remove something you didn\'t mean to.<br /><br />Use this option with care.';

$helptxt['avatar_default'] = 'With this option enabled, a default avatar is shown for all users without their own avatar. The file named \'default_avatar.png\' is located in the images folder inside the themes directory.';
$helptxt['avatar_server_stored'] = 'This allows your members to pick an avatar from a number of avatars stored on your server themselves.  They are, generally, in the same place as the forum under the avatars directory.<br />As a tip, if you create directories in that folder, you can make &quot;categories&quot; of avatars.';
$helptxt['avatar_external'] = 'With this enabled, your members can type in a URL to their own avatar.  The downside of this is that, in some cases, they may use avatars that are overly large or portray images you don\'t want on your forum.';
$helptxt['avatar_download_external'] = 'With this option enabled, the URL given by the user is accessed to download the avatar at that location. On success, the avatar will be treated and stored as an uploaded avatar.';
$helptxt['avatar_upload'] = 'This option is much like "Allow members to select an external avatar", except that you have better control over the avatars, a better time resizing them, and your members do not have to have somewhere to put avatars.<br /><br />However, the downside is that it can take a lot of space on your server.';
$helptxt['avatar_resize_options'] = 'This set of options apply to any avatar loaded to the server by users, either uploaded or retrieved from an external URL.';
$helptxt['avatar_download_png'] = 'PNGs are larger, but offer higher quality.  If this is unchecked, JPEG will be used instead, which is often smaller, but also removes transparency and is of lesser or blurry quality.';
$helptxt['gravatar'] = 'Gravatar (globally recognized avatar) is a service for providing globally unique avatars. For more details please visit the Gravatar <a href="https://www.gravatar.com" target="_blank"><strong>website</strong>.</a>';
$helptxt['gravatar_rating'] = 'Gravatar allows users to self-rate their images so that they can indicate if an image is appropriate for a certain audience. By default, only \'G\' rated images are displayed unless you indicate that you would like to see higher ratings. <br /><br /><ul><li><strong>g:</strong> suitable for display on all websites with any audience type.</li><li><strong>pg:</strong> may contain rude gestures, provocatively dressed individuals, the lesser swear words, or mild violence.</li><li><strong>r:</strong> may contain such things as harsh profanity, intense violence, nudity, or hard drug use.</li><li><strong>x:</strong> may contain hardcore sexual imagery or extremely disturbing violence.</li></ul>';
$helptxt['disableHostnameLookup'] = 'This disables host name lookups, which on some servers are very slow.  Note that this will make banning less effective.';

$helptxt['search_weight_commonheader'] = 'Weight factors are used to determine the relevancy of a search result. Change these weight factors to match the things that are specifically important for your forum. For instance, a forum of a news site might want a relatively high value for \'age of last matching message\'. All values are relative in relation to each other and should be positive integers.';
$helptxt['search_weight_frequency'] = 'This factor counts the amount of matching messages and divides them by the total number of messages within a topic.';
$helptxt['search_weight_age'] = 'This factor rates the age of the last matching message within a topic. The more recent this message is, the higher the score.';
$helptxt['search_weight_length'] = 'This factor is based on the topic size. The more messages are within the topic, the higher the score.';
$helptxt['search_weight_subject'] = 'This factor looks whether a search term can be found within the subject of a topic.';
$helptxt['search_weight_first_message'] = 'This factor looks whether a match can be found in the first message of a topic.';
$helptxt['search_weight_sticky'] = 'This factor looks whether a topic is pinned and increases the relevancy score if it is.';
$helptxt['search_weight_likes'] = 'This factor looks whether a topic has likes and increases the relevancy score based on the number.';
$helptxt['search'] = 'Adjust all settings for the search function here.';
$helptxt['search_why_use_index'] = 'A search index can greatly improve the search performance of your forum. Especially when the number of messages on a forum grows bigger, searching without an index can take a long time and increase the pressure on your database. If your forum is bigger than 50,000 messages, you should consider creating a search index to assure peak performance.<br /><br />Note that a search index can take up quite some space. A fulltext index is a built-in index of the database. It\'s relatively compact (approximately the same size as the message table), but a lot of common words aren\'t indexed and it can, in some wildcard queries, turn out to be slow. The custom index is bigger (it could be up to 3 times the size of the messages table) but its performance is often better than fulltext and indexes most words.';

$helptxt['see_admin_ip'] = 'IP addresses are shown to administrators and moderators to facilitate moderation and to make it easier to track people up to no good.  Remember that IP addresses may not always be identifying, and most people\'s IP addresses change periodically.<br /><br />Members are also allowed to see their own IPs.';
$helptxt['see_member_ip'] = 'Your IP address is shown only to you and moderators.  Remember that this information is not identifying, and that most IPs change periodically.<br /><br />You cannot see other members\' IP addresses, and they cannot see yours.';
$helptxt['whytwoip'] = 'Various methods are used to detect user IP addresses. Usually these two methods result in the same address but in some cases more than one address may be detected. In this case both addresses will be logged, and both will be used for ban checks (etc). You can click on either address to track that IP and ban if necessary.';

$helptxt['ban_cannot_post'] = 'The \'cannot post\' restriction turns the forum into read-only mode for the banned user. The user cannot create new topics, or reply to existing ones, send personal messages or vote in polls. The banned user can however still read personal messages and topics.<br /><br />A warning message is shown to the users that are banned this way.';

$helptxt['posts_and_topics'] = '
	<ul class="normallist">
		<li>
			<strong>Post Settings</strong><br />
			Modify the settings related to the posting of messages and the way messages are shown.
		</li><li>
			<strong>Bulletin Board Code</strong><br />
			Enable the code that allows to format forum messages. Also adjust which BBCodes are allowed and which aren\'t.
		</li><li>
			<strong>Censored Words</strong>
			In order to keep the language on your forum under control, you can censor certain words. This function allows you to convert forbidden words into innocent versions.
		</li><li>
			<strong>Topic Settings</strong>
			Modify the settings related to topics. The number of topics per page, whether pinned topics are enabled or not, the number of messages needed for a topic to be hot, etc.
		</li>
	</ul>';
$helptxt['allow_no_censored'] = 'When checked, this global setting allows members to disable word censoring in their User Profile through the Look and Layout settings. The members\' ablility to disable word censoring is still limited by their permission profile.';
$helptxt['spider_mode'] = 'Sets the logging level.<br />
Standard - Logs minimal spider activity.<br />
Moderate - Provides more accurate statistics.<br />
Aggressive - As for &quot;Moderate&quot; but logs data about each page visited.';

$helptxt['spider_group'] = 'By selecting a restrictive group, when a guest is detected as a search crawler it will automatically be assigned any &quot;deny&quot; deny permissions of this group in addition to the normal permissions of a guest. You can use this to provide lesser access to a search engine than you would a normal guest. You might for example wish to create a new group called &quot;Spiders&quot; and select that here. You could then deny permission for that group to view profiles to stop spiders indexing your members profiles.<br />Note: Spider detection is not perfect and can be simulated by users so this feature is not guaranteed to restrict content only to those search engines you have added.';
$helptxt['show_spider_online'] = 'This setting allows you to select whether spiders should be listed in the who\'s online list on the board index and &quot;Who\'s Online&quot; page. Options are:
	<ul class="normallist">
		<li>
			<strong>Not at All</strong><br />
			Spiders will simply appear as guests to all users.
		</li><li>
			<strong>Show Spider Quantity</strong><br />
			The Board Index will display the number of spiders currently visiting the forum.
		</li><li>
			<strong>Show Spider Names</strong><br />
			Each spider name will be revealed, so users can see how many of each spider is currently visiting the forum - this takes effect in both the Board Index and Who\'s Online page.
		</li><li>
			<strong>Show Spider Names - Admin Only</strong><br />
			As above except only Administrators can see spider status - to all other users spiders appear as guests.
		</li>
	</ul>';

$helptxt['birthday_email'] = 'Choose the index of the birthday email message to use.  A preview will be shown in the Email Subject and Email Body fields.<br /><strong>Note:</strong> Setting this option does not automatically enable birthday emails.  To enable birthday emails use the <a href="%1$s?action=admin;area=scheduledtasks;%3$s=%2$s" target="_blank" class="new_win">Scheduled Tasks</a> page and enable the birthday email task.';
$helptxt['pm_bcc'] = 'When sending a personal message you can choose to add a recipient as BCC or &quot;Blind Carbon Copy&quot;. BCC recipients do not have their identities revealed to other recipients of the message.';

$helptxt['move_topics_maintenance'] = 'This will allow you to move all the posts from one board to another board.';
$helptxt['maintain_reattribute_posts'] = 'You can use this function to attribute guest posts on your board to a registered member. This is useful if, for example, a user deleted their account and changed their mind and wished to have their old posts associated with their account.';
$helptxt['chmod_flags'] = 'You can manually set the permissions you wish to set the selected files to. To do this enter the chmod value as a numeric (octet) value. Note that these flags will have no effect on Microsoft Windows operating systems.';

$helptxt['postmod'] = 'This section allows members of the moderation team (with sufficient permissions) to approve any posts and topics before they are shown.';

$helptxt['field_show_enclosed'] = 'Encloses the user input between some text or HTML code.  This will allow you to add more instant message providers, images or an embed, etc. For example:<br /><br />
		&lt;a href="http://website.com/{INPUT}"&gt;&lt;img src="{DEFAULT_IMAGES_URL}/icon.png" alt="{INPUT}" /&gt;&lt;/a&gt;<br /><br />
		You can use the following variables:<br />
		<ul class="normallist">
			<li>{INPUT} - The input specified by the user.</li>
			<li>{KEY} - The key specified for a certain value of select box or radio buttons in the admin panel. Usually to use in case of localization or use in CSS of Javascript elements (e.g. as class name).</li>
			<li>{SCRIPTURL} - Web address of forum.</li>
			<li>{IMAGES_URL} - URI of the images directory of the user\'s current theme.</li>
			<li>{DEFAULT_IMAGES_URL} - URI of the images directory of the default theme.</li>
		</ul>';

$helptxt['custom_mask'] = 'The input mask is important for your forum\'s security. Validating the input from a user can help to ensure that data is not used in a way you do not expect. We have provided some simple regular expressions as hints.<br /><br />
	<div class="smalltext custom_mask">
		&quot;~[A-Za-z]+~&quot; - Match all upper and lower case alphabet characters.<br />
		&quot;~[0-9]+~&quot; - Match all numeric characters.<br />
		&quot;~[A-Za-z0-9]{7}~&quot; - Match all upper and lower case alphabet and numeric characters seven times.<br />
		&quot;~[^0-9]?~&quot; - Forbid any number from being matched.<br />
		&quot;~^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$~&quot; - Only allow 3 or 6 character hexcodes.<br />
	</div><br /><br />
	Additionally, special metacharacters ?+*^$ and {xx} can be defined.
	<div class="smalltext custom_mask">
		? - None or one match of previous expression.<br />
		+ - One or more of previous expression.<br />
		* - None or more of previous expression.<br />
		{xx} - An exact number of the previous expression.<br />
		{xx,} - An exact number or more of the previous expression.<br />
		{,xx} - An exact number or less of the previous expression.<br />
		{xx,yy} - An exact match between the two numbers from previous expression.<br />
		^ - Start of string.<br />
		$ - End of string.<br />
		\ - Escapes the next character.<br />
	</div><br /><br />
	More information and advanced techniques may be found on the internet.';

$helptxt['badbehavior_httpbl_maxage'] = 'This is the number of days since suspicious activity was last observed from an IP address by Project Honey Pot. The system will block requests with a maximum age equal to or less than this setting.';
$helptxt['badbehavior_httpbl_threat'] = 'This number provides a measure of how suspicious an IP address is, based on activity observed at Project Honey Pot. The system will block requests with a threat level equal or higher to this setting. Project Honey Pot has <a href="https://www.projecthoneypot.org/threat_info.php" target="_blank">more information on this parameter</a>.';
$helptxt['badbehavior_httpbl_key'] = 'Use data from the <a href="https://www.projecthoneypot.org/faq.php#g" target="_blank">http:BL</a> service provided by <a href="https://www.projecthoneypot.org/" target="_blank">Project Honey Pot</a> to screen requests.<br /><br />This is optional setting; however if you wish to use it, you must <a href="https://www.projecthoneypot.org/httpbl_configure.php" target="_blank">sign up for the service</a> and obtain an Access key. To disable http:BL use, remove the API key from your settings.';
$helptxt['badbehavior_accept_header'] = 'This enforces that a proper Accept header be send with requests.  All browsers due this by default, but many scrappers/harvesters do not and will be blocked.';

$helptxt['filter_to'] = 'Replace the found text with this, leave blank to replace with found text with nothing (i.e. remove it)';
$helptxt['filter_from'] = 'Enter the text you want to find/replace.  If type is set to regex then this must be a valid regular expression, including delimiters.  If not regex it will do a simple text match and replace it with the replacement text';
$helptxt['filter_type'] = 'Standard will find the exact phrase and replace it with the text in the replace field.  Regular Expression is a wildcard option, but it must be in a valid regex format.';
$helptxt['pbe_post_enabled'] = 'Enable this to allow users to respond to email notifications and have them post as a reply.  They are still required to have posting permissions.';
$helptxt['pbe_pm_enabled'] = 'Enable this to allow users to reply by email to PM notifications.  They are still required to have PM permissions, this setting only allows them to receive and reply to notifications';
$helptxt['maillist_group_mode'] = 'If enabled outbound post/topic emails will come from the poster\'s display name, otherwise it will come from the site name.  This is simply a envelope, affecting only how the "From name" appears in the receiving mailbox, the actual from email address is unchanged.';
$helptxt['maillist_newtopic_change'] = 'This will allow a user to change the subject of a email notification and have it post as a new topic.  The new topic will be started on the same board as the reply was going to.';
$helptxt['maillist_sitename_address'] = 'This must be the address that is piped to the emailpost.php file or the address of the IMAP mailbox';
$helptxt['maillist_help_short'] = 'This feature allows users of your forum to reply to the site\'s email notifications and have those replies post on the forum.  Please visit the Wiki for full instructions';

$helptxt['frame_security'] = 'The X-Frame-Options HTTP response header can be used to indicate whether or not a browser should be allowed to render a page in a frame or an iframe. You can use this additional security restriction on your site against attempts at clickjacking attacks, by ensuring that the content of your site is not embedded into other sites.
	<br />
	More information about this header may be found on the internet.';

$helptxt['attachment_inline_title'] = '<b>Add an inline attachment</b><br />
		Example:
		<br /><b>[attach align=left width=400]123[/attach]</b>
		<br />This will show a left-aligned image resized to 400 pixels wide with the post text flowing around it. Except for the attachment tag and the attachment id all other parameters are optional
		<br /><b>[attach]123[/attach]</b>
		<br />This will show the attachment as a thumbnail if available, if no thumbnail is available it will use a full sized image. The image will be in line with the text of your post.
		<br /><br />
		<br /><b>Options:</b>
		<br />where x is the attachment id
		<br />align=left, center, right
		<br />width=### (where # is number in pixels)
		<br />height=### (where # is number in pixels)
		<br />
		<h3>Modes available</h3>
		<p>
			You can choose the inline mode you want for your attachment:
			<ul>
				<li>Thumbnail [attach]x[/attach] : Your image will be shown as a thumbnail</li>
				<li>Text Link [attachurl]x[/attachurl] : Only a link is show with size and view details. By clicking on it, the image is displayed.</li>
			</ul>
		</p><br />
		<p>
			You can choose how to align the inline image:
			<ul>
				<li>align=left : The image is aligned to the left and the text will flow around it</li>
				<li>align=right : The image is aligned to the right and the text will flow around it</li>
				<li>align=center : The image is centered and the text will be below it</li>
			</ul>
		</p><br />
		<p>
			You can choose how wide to show the image:
			<ul>
				<li>width=123 : The image is displayed 123 pixels wide</li>
				<li>If the width specified is larger than the image or larger than the forum allows the largest allowable width will be used</li>
				<li>Can be used to shrink a thumbnail as well [attach width=50]x[/attach] will display a 50px wide thumbnail</li>
			</ul>
		</p><br />
		<p>
			You can choose how tall to show the image:
			<ul>
				<li>height=123 : The image is displayed 123 pixels tall</li>
				<li>If the height specified is bigger than the image or bigger than the forum allows the biggest allowable width will be used</li>
				<li>Can be used to shrink a thumbnail as well [attach height=50]x[/attach] will display a 50px tall thumbnail</li>
			</ul>
		</p>';
