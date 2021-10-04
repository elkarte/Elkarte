<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
{
    require_once(dirname(__FILE__) . '/SSI.php');
}
elseif (!defined('ELK'))
{
    die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');
}

global $settings;

// Remove-file during install does not work :(
if (file_exists($settings['default_theme_dir'] . '/scripts/jquery-3.1.1.min.js'))
{
	unlink($settings['default_theme_dir'] . '/scripts/jquery-3.1.1.min.js');
}
