<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
{
    require_once(dirname(__FILE__) . '/SSI.php');
}
elseif (!defined('ELK'))
{
    die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');
}

// Turn this off now
require_once(SOURCEDIR . '/Subs.php');
updateSettings(array('metadata_enabled' => 0));

// We need to reload the integration hooks to force a refresh back to old ILA tags or 
// a undefined constant error will occur during the uninstall 
$hooks = Hooks::instance();
$hooks->loadIntegrations();
$additional_bbc = array();
call_integration_hook('integrate_additional_bbc', array(&$additional_bbc));

// Reset the opcache
if (extension_loaded('Zend OPcache') && ini_get('opcache.enable') && stripos(BOARDDIR, ini_get('opcache.restrict_api')) !== 0)
{
	opcache_reset();
}
