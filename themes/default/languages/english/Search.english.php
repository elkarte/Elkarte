<?php
// Version: 1.1; Search

$txt['set_parameters'] = 'Set Search Parameters';
$txt['choose_board'] = 'Choose a board to search in, or search all';
$txt['all_words'] = 'Match all words';
$txt['any_words'] = 'Match any words';
$txt['by_user'] = 'By user';

$txt['search_post_age'] = 'Message age';
$txt['search_between'] = 'between';
$txt['search_and'] = 'and';
$txt['search_options'] = 'Options';
$txt['search_show_complete_messages'] = 'Show results as messages';
$txt['search_subject_only'] = 'Search in topic subjects only';
$txt['search_relevance'] = 'Relevance';
$txt['search_date_posted'] = 'Date Posted';
$txt['search_order'] = 'Search order';
$txt['search_orderby_relevant_first'] = 'Most relevant results first';
$txt['search_orderby_large_first'] = 'Largest topics first';
$txt['search_orderby_small_first'] = 'Smallest topics first';
$txt['search_orderby_recent_first'] = 'Most recent topics first';
$txt['search_orderby_old_first'] = 'Oldest topics first';
$txt['search_visual_verification_label'] = 'Verification';
$txt['search_visual_verification_desc'] = 'Please enter the code in the image above to use search.';

$txt['search_specific_topic'] = 'Searching only posts in the topic';

$txt['groups_search_posts'] = 'Membergroups with access to the search function';
$txt['search_dropdown'] = 'Enable the Quick Search dropdown';
$txt['search_results_per_page'] = 'Number of search results per page';
$txt['search_weight_frequency'] = 'Relative search weight for number of matching messages within a topic';
$txt['search_weight_age'] = 'Relative search weight for age of last matching message';
$txt['search_weight_length'] = 'Relative search weight for topic length';
$txt['search_weight_subject'] = 'Relative search weight for a matching subject';
$txt['search_weight_first_message'] = 'Relative search weight for a first message match';
$txt['search_weight_sticky'] = 'Relative search weight for a pinned topic';
$txt['search_weight_likes'] = 'Relative search weight for topic likes';

$txt['search_settings_desc'] = 'Here you can change the basic settings of the search function.';
$txt['search_settings_title'] = 'Search Settings';

$txt['search_weights_desc'] = 'Here you can change the individual components of the relevance rating.';
$txt['search_weights_sphinx'] = 'To update weight factors with Sphinx, you must generate and install a new sphinx.conf file.';
$txt['search_weights_title'] = 'Search - weights';
$txt['search_weights_total'] = 'Total';
$txt['search_weights_save'] = 'Save';

$txt['search_method_desc'] = 'Here you can set the way search is powered.';
$txt['search_method_title'] = 'Search - method';
$txt['search_method_save'] = 'Save';
$txt['search_method_messages_table_space'] = 'Space used by forum messages in the database';
$txt['search_method_messages_index_space'] = 'Space used to index messages in the database';
$txt['search_method_kilobytes'] = 'KB';
$txt['search_method_fulltext_index'] = 'Fulltext index';
$txt['search_method_no_index_exists'] = 'doesn\'t currently exist';
$txt['search_method_fulltext_create'] = 'Create a fulltext index';
$txt['search_method_fulltext_cannot_create'] = 'cannot be created because the max message length is above 65,535 or table type is not MyISAM';
$txt['search_method_index_already_exists'] = 'already created';
$txt['search_method_fulltext_remove'] = 'remove fulltext index';
$txt['search_method_index_partial'] = 'partially created';
$txt['search_index_custom_resume'] = 'resume';

// These strings are used in a javascript confirmation popup; don't use entities.
$txt['search_method_fulltext_warning'] = 'In order to be able to use fulltext search, you\\\'ll have to create a fulltext index first.';
$txt['search_index_custom_warning'] = 'In order to be able to use a custom index search, you\\\'ll have to create a custom index first!';

$txt['search_index'] = 'Search index';
$txt['search_index_none'] = 'No index';
$txt['search_index_custom'] = 'Custom index';
$txt['search_index_label'] = 'Index';
$txt['search_index_size'] = 'Size';
$txt['search_index_create_custom'] = 'Create custom index';
$txt['search_index_custom_remove'] = 'Remove custom index';

$txt['search_index_sphinx'] = 'SphinxAPI';
$txt['search_index_sphinx_desc'] = 'To adjust Sphinx settings, use <a class="linkbutton" href="{managesearch_url}">Configure Sphinx</a>';
$txt['search_index_sphinxql'] = 'SphinxQL';
$txt['search_index_sphinxql_desc'] = 'To adjust SphinxQL settings, use <a class="linkbutton" href="{managesearch_url}">Configure Sphinx</a>';

$txt['search_force_index'] = 'Force the use of a search index';
$txt['search_match_words'] = 'Match whole words only';
$txt['search_max_results'] = 'Maximum results to show';
$txt['search_max_results_disable'] = '(0: no limit)';
$txt['search_floodcontrol_time'] = 'Time required between searches from same user';
$txt['search_floodcontrol_time_desc'] = '(0 for no limit, in seconds)';

$txt['additional_search_engines'] = 'Additional search engines';
$txt['setup_search_engine_add_more'] = 'Add another search engine';

$txt['search_create_index'] = 'Create index';
$txt['search_create_index_why'] = 'Why create a search index?';
$txt['search_create_index_start'] = 'Create';
$txt['search_predefined'] = 'Pre-defined profile';
$txt['search_predefined_small'] = 'Small sized index';
$txt['search_predefined_moderate'] = 'Moderate sized index';
$txt['search_predefined_large'] = 'Large sized index';
$txt['search_create_index_continue'] = 'Continue';
$txt['search_create_index_not_ready'] = 'ElkArte is currently creating a search index of your messages. To avoid overloading your server, the process has been temporarily paused. It should automatically continue in a few seconds. If it doesn\'t, please click continue below.';
$txt['search_create_index_progress'] = 'Progress';
$txt['search_create_index_done'] = 'Custom search index successfully created.';
$txt['search_create_index_done_link'] = 'Continue';
$txt['search_double_index'] = 'You have currently created two indexes on the messages table. For best performance it is advisable to remove one of the two indexes.';

$txt['search_error_indexed_chars'] = 'Invalid number of indexed characters. At least 3 characters are needed for a useful index.';
$txt['search_error_max_percentage'] = 'Invalid percentage of words to be skipped. Use a value of at least 5%.';
$txt['error_string_too_long'] = 'Search string must be less than %1$d characters long.';

$txt['search_warning_ignored_word'] = 'The following term has been ignored in your search';
$txt['search_warning_ignored_words'] = 'The following terms have been ignored in your search';

$txt['search_adjust_query'] = 'Adjust Search Parameters';
$txt['search_adjust_submit'] = 'Revise Search';
$txt['search_did_you_mean'] = 'You may have meant to search for';

$txt['search_example'] = '<em>e.g.</em> Orwell "Animal Farm" -movie';

$txt['search_engines_description'] = 'From this area you can decide in what detail you wish to track search engines as they index your forum as well as review search engine logs.';
$txt['spider_mode'] = 'Search Engine Tracking Level';
$txt['spider_mode_note'] = 'Note higher level tracking increases server resource requirement.';
$txt['spider_mode_off'] = 'Disabled';
$txt['spider_mode_standard'] = 'Standard';
$txt['spider_mode_high'] = 'Moderate';
$txt['spider_mode_vhigh'] = 'Aggressive';
$txt['spider_settings_desc'] = 'You can change settings for spider tracking from this page. Note, if you wish to <a href="%1$s">enable automatic pruning of the hit logs you can set this up here</a>';

$txt['spider_group'] = 'Apply restrictive permissions from group';
$txt['spider_group_note'] = 'To enable you to stop spiders indexing some pages.';
$txt['spider_group_none'] = 'Disabled';

$txt['show_spider_online'] = 'Show spiders in the online list';
$txt['show_spider_online_no'] = 'Not at all';
$txt['show_spider_online_summary'] = 'Show spider quantity';
$txt['show_spider_online_detail'] = 'Show spider names';
$txt['show_spider_online_detail_admin'] = 'Show spider names - admin only';

$txt['spider_name'] = 'Spider Name';
$txt['spider_last_seen'] = 'Last Seen';
$txt['spider_last_never'] = 'Never';
$txt['spider_agent'] = 'User Agent';
$txt['spider_ip_info'] = 'IP Addresses';
$txt['spiders_add'] = 'Add New Spider';
$txt['spiders_edit'] = 'Edit Spider';
$txt['spiders_remove_selected'] = 'Remove Selected';
$txt['spider_remove_selected_confirm'] = 'Are you sure you want to remove these spiders?\\n\\nAll associated statistics will also be deleted!';
$txt['spiders_no_entries'] = 'There are currently no spiders configured.';

$txt['add_spider_desc'] = 'From this page you can edit the parameters against which a spider is categorised. If a guest\'s user agent/IP address matches those entered below it will be detected as a search engine spider and tracked as per the forum preferences.';
$txt['spider_name_desc'] = 'Name by which the spider will be referred.';
$txt['spider_agent_desc'] = 'User agent associated with this spider.';
$txt['spider_ip_info_desc'] = 'Comma separated list of IP addresses associated with this spider.';

$txt['spider_time'] = 'Time';
$txt['spider_viewing'] = 'Viewing';
$txt['spider_logs_empty'] = 'There are currently no spider log entries.';
$txt['spider_logs_info'] = 'Note that logging of every spider action only occurs if tracking is set to either &quot;high&quot; or &quot;very high&quot;. Detail of every spiders action is only logged if tracking is set to &quot;very high&quot;.';
$txt['spider_disabled'] = 'Disabled';
$txt['spider_log_empty_log'] = 'Clear Log';
$txt['spider_log_empty_log_confirm'] = 'Are you sure you want to completely clear the log';

$txt['spider_logs_delete'] = 'Delete Entries';
$txt['spider_logs_delete_older'] = 'Delete all entries older than %1$s days.';
$txt['spider_logs_delete_submit'] = 'Delete';

$txt['spider_stats_delete_older'] = 'Delete all spider statistics from spiders not seen in %1$s days.';

// Don't use entities in the below string.
$txt['spider_logs_delete_confirm'] = 'Are you sure you wish to empty out all log entries?';

$txt['spider_stats_select_month'] = 'Jump To Month';
$txt['spider_stats_page_hits'] = 'Page Hits';
$txt['spider_stats_no_entries'] = 'There are currently no spider statistics available.';

// strings for setting up sphinx search
$txt['sphinx_test_not_selected'] = 'You have not yet selected to use Sphinx or SphinxQL as your Search Method';
$txt['sphinx_test_passed'] = 'All tests were successful, the system was able to connect to the sphinx search daemon using the Sphinx API.';
$txt['sphinxql_test_passed'] = 'All tests were successful, the system was able to connect to the sphinx search daemon using SphinxQL commands.';
$txt['sphinx_test_connect_failed'] = 'Unable to connect to the Sphinx daemon. Make sure it is running and configured properly. Sphinx search will not work until you fix the problem.';
$txt['sphinxql_test_connect_failed'] = 'Unable to access SphinxQL. Make sure your sphinx.conf has a separate listen directive for the SphinxQL port. SphinxQL search will not work until you fix the problem';
$txt['sphinx_test_api_missing'] = 'The sphinxapi.php file is missing in your &quot;sources&quot; directory. You need to copy this file from the Sphinx distribution. Sphinx search will not work until you fix the problem.';
$txt['sphinx_description'] = 'Use this interface to supply the access details to your Sphinx search daemon. <strong>These settings are only used to create</strong> an initial sphinx.conf configuration file which you will need to save in your Sphinx configuration directory (typically /usr/local/etc or /etc/sphinxsearch). Generally the options below can be left untouched, however they assume that the Sphinx software was installed in /usr/local and use /var/sphinx for the search index data storage. In order to keep Sphinx up to date, you must use a cron job to update the indexes, otherwise new or deleted content will not be reflected in  the search results. The configuration file defines two indexes:<br /><br/><strong>elkarte_delta_index</strong>, an index that only stores recent changes and can be called frequently. <strong>elkarte_base_index</strong>, an index that stores the full database and should be called less frequently. Example:<br /><span class="tt">10 3 * * * /usr/local/bin/indexer --config /usr/local/etc/sphinx.conf --rotate elkarte_base_index<br />0 * * * * /usr/local/bin/indexer --config /usr/local/etc/sphinx.conf --rotate elkarte_delta_index</span>';
$txt['sphinx_index_prefix'] = 'Index prefix:';
$txt['sphinx_index_prefix_desc'] = 'This is the prefix for the base and delta indexes.<br />By default it uses elkarte and the two indexes will be elkarte_base_index and elkarte_delta_index. Sphinx will connect to elkarte_index (prefix_index).  If you change this be sure to use the correct prefix in your cron task.';
$txt['sphinx_index_data_path'] = 'Index data path:';
$txt['sphinx_index_data_path_desc'] = 'This is the path that contains the search index files used by Sphinx.<br />It <strong>must</strong> exist and be accessible for reading and writing by the Sphinx indexer and search daemon.';
$txt['sphinx_log_file_path'] = 'Log file path:';
$txt['sphinx_log_file_path_desc'] = 'Server path that will contain the log files created by Sphinx.<br />This directory must exist on your server and must be writable by the sphinx search daemon and indexer.';
$txt['sphinx_stop_word_path'] = 'Stopword path:';
$txt['sphinx_stop_word_path_desc'] = 'The server path to the stopword list (leave empty for no stopword list).';
$txt['sphinx_memory_limit'] = 'Sphinx indexer memory limit:';
$txt['sphinx_memory_limit_desc'] = 'The maximum amount of (RAM) memory the indexer is allowed to use.';
$txt['sphinx_searchd_server'] = 'Search daemon server:';
$txt['sphinx_searchd_server_desc'] = 'Address of the server running the search daemon. This must be a valid host name or IP address.<br />If not set, localhost will be used.';
$txt['sphinx_searchd_port'] = 'Sphinx API daemon port:';
$txt['sphinx_searchd_port_desc'] = 'Port on which the search daemon will listen for API queries.';
$txt['sphinx_searchd_qlport'] = 'Sphinx QL daemon port:';
$txt['sphinx_searchd_qlport_desc'] = 'Port on which the search daemon will listen for SphinxQL queries.';
$txt['sphinx_max_matches'] = 'Maximum # matches:';
$txt['sphinx_max_matches_desc'] = 'Maximum amount of matches the search daemon will return.';
$txt['sphinx_create_config'] = 'Create Sphinx config';
$txt['sphinx_test_connection'] = 'Test connection to Sphinx daemon';