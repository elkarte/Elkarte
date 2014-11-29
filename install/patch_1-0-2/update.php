<?php

if (!defined('ELK') && file_exists(dirname(__FILE__) . '/SSI.php'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');

updateSettings(array(
	'avatar_max_width' => $modSettings['avatar_max_width_external'],
	'avatar_max_height' => $modSettings['avatar_max_height_external'],
	'avatar_action_too_large' => in_array($modSettings['avatar_action_too_large'], array('option_html_resize', 'option_js_resize')) ? 'option_resize' : $modSettings['avatar_action_too_large'],
	'avatar_stored_enabled' => 1,
	'avatar_external_enabled' => 1,
	'avatar_gravatar_enabled' => 1,
	'avatar_upload_enabled' => 1,
));

if (ELK == 'SSI')
	echo 'Database changes were carried out successfully.';