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

// Remove-file during the install phase is not an option
if (file_exists($settings['default_theme_dir'] . '/fonts/OFL.txt'))
{
	unlink($settings['default_theme_dir'] . '/fonts/OFL.txt');
}

if (file_exists($settings['default_theme_dir'] . '/fonts/PressStart2P - License.txt'))
{
	unlink($settings['default_theme_dir'] . '/fonts/PressStart2P - License.txt');
}

if (file_exists($settings['default_theme_dir'] . '/fonts/PressStart2P.ttf'))
{
	unlink($settings['default_theme_dir'] . '/fonts/PressStart2P.ttf');
}

if (file_exists($settings['default_theme_dir'] . '/fonts/Segment14.ttf'))
{
	unlink($settings['default_theme_dir'] . '/fonts/Segment14.ttf');
}

if (file_exists($settings['default_theme_dir'] . '/fonts/vSHexagonica - License.txt'))
{
	unlink($settings['default_theme_dir'] . '/fonts/vSHexagonica - License.txt');
}

if (file_exists($settings['default_theme_dir'] . '/fonts/vSHexagonica.ttf'))
{
	unlink($settings['default_theme_dir'] . '/fonts/vSHexagonica.ttf');
}

// Update the password column so it fully supports password_hash
$db_table = db_table();
$db_table->db_change_column('{db_prefix}members',
	'passwd',
	array('type' => 'varchar', 'size' => 255, 'default' => '')
);

// Reset the opcache
if (extension_loaded('Zend OPcache') && ini_get('opcache.enable') &&
	((ini_get('opcache.restrict_api') === '' || stripos(ini_get('opcache.restrict_api'), BOARDDIR) !== 0)))
{
	@opcache_reset();
}