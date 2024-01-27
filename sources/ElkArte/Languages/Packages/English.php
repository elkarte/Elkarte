<?php

// Version: 2.0; Packages

$txt['package_proceed'] = 'Proceed';
$txt['package_id'] = 'ID';
$txt['list_file'] = 'List files in package';
$txt['files_archive'] = 'Files in archive';
$txt['package_browse'] = 'Browse';
$txt['add_server'] = 'Add server';
$txt['server_name'] = 'Server name';
$txt['serverurl'] = 'URL';
$txt['no_packages'] = 'No packages yet.';
$txt['download'] = 'Download';
$txt['download_success'] = 'Package downloaded successfully';
$txt['package_downloaded_successfully'] = 'Package has been downloaded successfully';
$txt['package_manager'] = 'Package Manager';
$txt['install_mod'] = 'Install Add-on';
$txt['uninstall_mod'] = 'Uninstall Add-on';
$txt['no_adds_installed'] = 'No addons currently installed';
$txt['uninstall'] = 'Uninstall';
$txt['delete_list'] = 'Delete Add-on List';
$txt['package_installed_on'] = 'Installed On';
$txt['package_delete_list_warning'] = 'Are you sure you wish to clear the installed addons list?';

$txt['package_manager_desc'] = 'From this easy to use interface, you can download and install addons for use on your forum.';
$txt['installed_packages_desc'] = 'You can use the interface below to view those packages currently installed on the forum, and remove the ones you no longer require.';
$txt['download_packages_desc'] = 'From this section you can add or remove package servers, browse for packages, or download new packages from servers.';
$txt['package_servers_desc'] = 'From this easy to use interface, you can manage your package servers and download addon archives on your forum.';
$txt['upload_packages_desc'] = 'From this section you can upload a package file from your local computer directly to the forum.';

$txt['upload_new_package'] = 'Upload new package';
$txt['view_and_remove'] = 'View and remove installed packages';
$txt['modification_package'] = 'Add-on packages';
$txt['avatar_package'] = 'Avatar packages';
$txt['language_package'] = 'Language packages';
$txt['unknown_package'] = 'Other packages';
$txt['smiley_package'] = 'Smiley packages';
$txt['use_avatars'] = 'Use Avatars';
$txt['add_languages'] = 'Add Language';
$txt['list_files'] = 'List Files';
$txt['package_type'] = 'Package Type';
$txt['extracting'] = 'Extracting';
$txt['avatars_extracted'] = 'The avatars have been installed, you should now be able to use them.';
$txt['language_extracted'] = 'The language pack has been installed, you can now enable its use in the language settings area of your admin control panel.';

$txt['mod_name'] = 'Add-on Name';
$txt['mod_version'] = 'Version';
$txt['mod_author'] = 'Author';
$txt['author_website'] = 'Author\'s Homepage';
$txt['package_no_description'] = 'No description given';
$txt['package_description'] = 'Description';
$txt['file_location'] = 'Download';
$txt['bug_location'] = 'Issue tracker';
$txt['support_location'] = 'Support';
$txt['mod_hooks'] = 'No source edits';
$txt['mod_date'] = 'Last updated';
$txt['mod_section_count'] = 'Browse the (%1d) addons in this section';

// Package Server strings
$txt['package_current'] = '(%s <em>You have the Current version %s</em>)';
$txt['package_update'] = '(%s <em>An update for your %s version is available</em>)';
$txt['package_installed'] = 'installed';
$txt['package_downloaded'] = 'downloaded';

$txt['package_installed_key'] = 'Installed addons:';
$txt['package_installed_current'] = 'current version';
$txt['package_installed_old'] = 'older version';
$txt['package_installed_warning1'] = 'This package is already installed, and no upgrade was found.';
$txt['package_installed_warning2'] = 'You should uninstall the old version first to avoid problems, or ask the author to create an upgrade from your old version.';
$txt['package_installed_warning3'] = 'Please remember to always make regular backups of your sources and database before installing addons, especially beta versions.';
$txt['package_installed_extract'] = 'Extracting Package';
$txt['package_installed_done'] = 'The package was installed successfully.  You should now be able to use whatever functionality it adds or changes; or not be able to use functionality it removes.';
$txt['package_installed_redirecting'] = 'Redirecting...';
$txt['package_installed_redirect_go_now'] = 'Redirect Now';
$txt['package_installed_redirect_cancel'] = 'Return to Package Manager';

$txt['package_upgrade'] = 'Upgrade';
$txt['package_uninstall_readme'] = 'Uninstallation Readme';
$txt['package_install_readme'] = 'Installation Readme';
$txt['package_install_license'] = 'License';
$txt['package_install_type'] = 'Type';
$txt['package_install_action'] = 'Action';
$txt['package_install_desc'] = 'Description';
$txt['install_actions'] = 'Install Actions';
$txt['perform_actions'] = 'This will perform the following actions:';
$txt['corrupt_compatible'] = 'The package you are trying to download or install is either corrupt or not compatible with this version of the software.';
$txt['package_create'] = 'Create';
$txt['package_move'] = 'Move';
$txt['package_delete'] = 'Delete';
$txt['package_extract'] = 'Extract';
$txt['package_file'] = 'File';
$txt['package_tree'] = 'Tree';
$txt['execute_modification'] = 'Execute Modification';
$txt['execute_code'] = 'Execute Code';
$txt['execute_database_changes'] = 'Execute file';
$txt['execute_hook_add'] = 'Add Hook';
$txt['execute_hook_remove'] = 'Remove Hook';
$txt['execute_hook_action'] = 'Adapting hook %1$s';
$txt['package_requires'] = 'Requires Modification';
$txt['package_check_for'] = 'Check for installation:';
$txt['execute_credits_add'] = 'Add Credits';
$txt['execute_credits_action'] = 'Credits: %1$s';

$txt['package_install_actions'] = 'Installations actions for';
$txt['package_will_fail_title'] = 'Error in package %1$s';
$txt['package_will_fail_warning'] = 'At least one error was encountered during a test %1$s of this package.<br />It is <strong>strongly</strong> recommended that you do not continue with %1$s unless you know what you are doing, and have made a backup very recently.<br /><br />This error may be caused by a conflict between the package you\'re trying to install and another package you have already installed, an error in the package, a package which requires another package that you have not installed yet, or a package designed for another version of the software.';
$txt['package_will_fail_unknown_action'] = 'The package is trying to perform an unknown action: %1$s';
// Don't use entities in the below string.
$txt['package_will_fail_popup'] = 'Are you sure you wish to continue installing this addon, even though it will not install successfully?';
$txt['package_will_fail_popup_uninstall'] = 'Are you sure you wish to continue uninstalling this addon, even though it will not uninstall successfully?';
$txt['package_install'] = 'installation';
$txt['package_uninstall'] = 'removal';
$txt['package_install_now'] = 'Install now';
$txt['package_uninstall_now'] = 'Uninstall now';
$txt['package_other_themes'] = 'Install in other themes';
$txt['package_other_themes_uninstall'] = 'UnInstall in other themes';
$txt['package_other_themes_desc'] = 'To use this addon in themes other than the default, the package manager needs to make additional changes to the other themes. If you\'d like to install this addon in the other themes, please select these themes below.';
// Don't use entities in the below string.
$txt['package_theme_failure_warning'] = 'At least one error was encountered during a test install of this theme. Are you sure you wish to attempt installation?';

$txt['package_bytes'] = 'bytes';

$txt['package_action_missing'] = '<strong class="error">File not found</strong>';
$txt['package_action_error'] = '<strong class="error">Modification parse error</strong>';
$txt['package_action_failure'] = '<strong class="error">Test failed</strong>';
$txt['package_action_success'] = '<strong>Test successful</strong>';
$txt['package_action_skipping'] = '<strong>Skipping file</strong>';

$txt['package_uninstall_actions'] = 'Uninstall Actions';
$txt['package_uninstall_done'] = 'The package has been successfully uninstalled.';
$txt['package_uninstall_cannot'] = 'This package cannot be uninstalled, because there is no uninstaller.<br /><br />Please contact the addon author for more information.';

$txt['package_install_options'] = 'Installation Options';
$txt['package_install_options_desc'] = 'Set various options for how the package manager installs addons, including backups and ftp access';
$txt['package_install_options_ftp_why'] = 'Using the package manager\'s FTP functionality is the easiest way to avoid having to manually chmod the files writable through FTP yourself for the package manager to work.<br />Here you can set the default values for some fields.';
$txt['package_install_options_ftp_server'] = 'FTP Server';
$txt['package_install_options_ftp_port'] = 'Port';
$txt['package_install_options_ftp_user'] = 'Username';
$txt['package_install_options_make_backups'] = 'Create Backup versions of replaced files with a tilde (~) on the end of their names.';
$txt['package_install_options_make_full_backups'] = 'Create an entire backup (excluding smileys, avatars and attachments) of the ElkArte install.';

$txt['package_ftp_necessary'] = 'FTP Information Required';
$txt['package_ftp_why'] = 'Some files the package manager needs to modify are not writable.  This needs to be fixed by using FTP to chmod and/or create those files and directories.  Your FTP information will be temporarily cached for proper operation of the package manager, please create the connection before proceeding.  You can also do this manually using an FTP client.  <a href="#" class="linkbutton" onclick="%1$s">View the list of the affected files</a>.';
$txt['package_ftp_why_file_list'] = 'The following files need to made writable to continue installation:';
$txt['package_ftp_why_download'] = 'In order to download packages, the packages directory, and any files in it, must be writable.  Currently the system does not have the needed permissions to write to this directory.  The package manager can use your FTP information to attempt to fix this problem.';
$txt['package_ftp_server'] = 'FTP Server';
$txt['package_ftp_port'] = 'Port';
$txt['package_ftp_username'] = 'Username';
$txt['package_ftp_password'] = 'Password';
$txt['package_ftp_path'] = 'Local path to ElkArte';
$txt['package_ftp_test'] = 'Test';
$txt['package_ftp_test_connection'] = 'Create Connection';
$txt['package_ftp_test_success'] = 'FTP connection established.';
$txt['package_ftp_test_failed'] = 'Could not contact server.';
$txt['package_ftp_bad_server'] = 'Could not contact server.';

// For a break, use \\n instead of <br />... and don't use entities.
$txt['package_delete_bad'] = 'The package you are about to delete is currently installed!  If you delete it, you may not be able to uninstall it later.\\n\\nAre you sure?';

$txt['package_examine_file'] = 'View file in package';
$txt['package_file_contents'] = 'Contents of file';

$txt['package_upload_title'] = 'Upload a Package';
$txt['package_upload_select'] = 'Package to Upload';
$txt['package_upload'] = 'Upload';
$txt['package_uploaded_success'] = 'Package uploaded successfully';
$txt['package_uploaded_successfully'] = 'The package has been uploaded successfully';

$txt['package_modification_malformed'] = 'Malformed or invalid addon file.';
$txt['package_modification_missing'] = 'The file could not be found.';
$txt['package_no_zlib'] = 'Sorry, your PHP configuration doesn\'t have support for <strong>zlib</strong>.  Without this, the package manager cannot function.  Please contact your host about this for more information.';

$txt['package_download_by_url'] = 'Download a package by url';
$txt['package_download_filename'] = 'Name of the file';
$txt['package_download_filename_info'] = 'Optional value.  Should be used when the url does not end in the filename.  For example: index.php?mod=5';

$txt['package_db_uninstall'] = 'Remove all data associated with this addon.';
$txt['package_db_uninstall_details'] = 'Details';
$txt['package_db_uninstall_actions'] = 'Checking this option will result in the following actions';
$txt['package_db_remove_table'] = 'Drop table &quot;%1$s&quot;';
$txt['package_db_remove_column'] = 'Remove column &quot;%2$s&quot; from &quot;%1$s&quot;';
$txt['package_db_remove_index'] = 'Remove index &quot;%1$s&quot; from &quot;%2$s&quot;';

$txt['package_emulate_install'] = 'Install Emulating:';
$txt['package_emulate_uninstall'] = 'Uninstall Emulating:';

// Operations.
$txt['operation_find'] = 'Find';
$txt['operation_replace'] = 'Replace';
$txt['operation_after'] = 'Add After';
$txt['operation_before'] = 'Add Before';
$txt['operation_title'] = 'Operations';
$txt['operation_ignore'] = 'Ignore Errors';
$txt['operation_invalid'] = 'The operation that you selected is invalid.';

$txt['package_file_perms_writable'] = 'Writable';
$txt['package_file_perms_not_writable'] = 'Not Writable';

$txt['package_restore_permissions'] = 'Restore file permissions';
$txt['package_restore_permissions_desc'] = 'The following file permissions were changed in order to install the selected package(s). You can return these files back to their original status by clicking &quot;Restore&quot; below.';
$txt['package_restore_permissions_restore'] = 'Restore';
$txt['package_restore_permissions_filename'] = 'Filename';
$txt['package_restore_permissions_orig_status'] = 'Original Status';
$txt['package_restore_permissions_cur_status'] = 'Current Status';
$txt['package_restore_permissions_result'] = 'Result';
$txt['package_restore_permissions_pre_change'] = '%1$s (%3$s)';
$txt['package_restore_permissions_post_change'] = '%2$s (%3$s - was %2$s)';
$txt['package_restore_permissions_action_skipped'] = '<em>Skipped</em>';
$txt['package_restore_permissions_action_success'] = '<span class="success">Success</span>';
$txt['package_restore_permissions_action_failure'] = '<span class="error">Failed</span>';
$txt['package_restore_permissions_action_done'] = 'An attempt to restore the selected files back to their original permissions has been completed, the results can be seen below. If a change failed, or for a more detailed view of file permissions, please see the <a href="%1$s">File Permissions</a> section.';

$txt['package_confirm_view_package_content'] = 'Are you sure you want to view the package contents from this location:<br /><br />%1$s';
$txt['package_confirm_proceed'] = 'Proceed';
$txt['package_confirm_go_back'] = 'Go back';

$txt['package_readme_default'] = 'Default';
$txt['package_available_readme_language'] = 'Available Readme Languages:';
$txt['package_license_default'] = 'Default';
$txt['package_available_license_language'] = 'Available License Languages:';