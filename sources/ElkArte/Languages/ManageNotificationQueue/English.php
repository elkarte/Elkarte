<?php
// Version: 2.0; ManageNotificationQueue

$txt['notificationqueue_desc'] = 'From this page you can view and manage the deferred post notification queue, monitor processing status, or clear/purge pending jobs.';
$txt['notificationqueue_settings_desc'] = 'From this page you can configure deferred post notification settings, such as enabling background sending and setting the delay before processing notifications.';

$txt['notificationqueue_stats'] = 'Notification queue statistics';
$txt['notificationqueue_oldest'] = 'Oldest Job';
$txt['notificationqueue_oldest_not_available'] = 'N/A';
$txt['notificationqueue_size'] = 'Total queue items';
$txt['notificationqueue_pending'] = 'Ready to process';
$txt['notificationqueue_in_grace'] = 'In delay window';
$txt['notificationqueue_locked'] = 'Currently processing';

$txt['notificationqueue_task_type'] = 'Task Type';
$txt['notificationqueue_type_topic'] = 'New Topic';
$txt['notificationqueue_type_post'] = 'New Topic';
$txt['notificationqueue_type_reply'] = 'New Reply';
$txt['notificationqueue_subject'] = 'Subject';
$txt['notificationqueue_poster'] = 'Poster';
$txt['notificationqueue_status'] = 'Status';
$txt['notificationqueue_status_failed'] = 'Failed (%1$d/%2$d)';
$txt['notificationqueue_status_grace'] = 'Delay (%1$ds)';
$txt['notificationqueue_status_locked'] = 'Processing (%1$ds)';
$txt['notificationqueue_status_ready'] = 'Ready';
$txt['notificationqueue_attempts'] = 'Attempts';
$txt['notificationqueue_age'] = 'Age';

$txt['notificationqueue_clear_list'] = 'Process queue now';
$txt['notificationqueue_clear_list_warning'] = 'Are you sure you wish to process the notification queue now?';
$txt['notificationqueue_purge_list'] = 'Purge queue';
$txt['notificationqueue_purge_list_warning'] = 'Are you sure you wish to delete all items from the notification queue? This cannot be undone.';
$txt['notificationqueue_no_items'] = 'The notification queue is currently empty';

$txt['deferred_post_notifications'] = 'Send post notifications in the background';
$txt['deferred_post_notifications_delay'] = 'Notification delay (seconds)';
$txt['deferred_post_notifications_delay_desc'] = 'Wait time in seconds after posting before sending notifications, allowing for quick post edits.';
$txt['deferred_post_notifications_desc'] = 'When enabled, post notifications are queued and processed asynchronously in the background instead of during post creation, improving posting responsiveness.';
