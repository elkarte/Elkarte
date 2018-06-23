<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
{
	require_once(dirname(__FILE__) . '/SSI.php');
}
elseif (!defined('ELK'))
{
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');
}

$db = database();
$db_table = db_table();

$request = $db->query('', '
	SELECT id_attach, filename
	FROM {db_prefix}attachments
	WHERE filename like \'/%\'',
	array()
);
while ($row = $db->fetch_assoc($request))
{
	$db->query('', '
		update {db_prefix}attachments
		set filename = {string:filename}
		where id_attach = {int:id_attach}',
		array(
			'filename' => basename($row['filename']),
			'id_attach' => $row['id_attach']
		)
	);
}

$db_table->db_create_table('{db_prefix}log_agreement_accept',
	array(
		array('name' => 'version',       'type' => 'varchar', 'size' => 20, 'default' => ''),
		array('name' => 'id_member',     'type' => 'mediumint', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'accepted_date', 'type' => 'date', 'default' => '0001-01-01'),
		array('name' => 'accepted_ip',   'type' => 'varchar', 'size' => 255, 'default' => ''),
	),
	array(
		array('name' => 'version', 'columns' => array('version', 'id_member'), 'type' => 'primary'),
	),
	array(),
	'ignore'
);

$db_table->db_create_table('{db_prefix}log_privacy_policy_accept',
	array(
		array('name' => 'version',       'type' => 'varchar', 'size' => 20, 'default' => ''),
		array('name' => 'id_member',     'type' => 'mediumint', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'accepted_date', 'type' => 'date', 'default' => '0001-01-01'),
		array('name' => 'accepted_ip',   'type' => 'varchar', 'size' => 255, 'default' => ''),
	),
	array(
		array('name' => 'version', 'columns' => array('version', 'id_member'), 'type' => 'primary'),
	),
	array(),
	'ignore'
);

// This is a cheat, but we may need those files written now
// The following lines were added when the changes to Agreement.class.php were
// part of modifications.xml, now that this has been changed to a require-file
// this trick should not be necessary any more.
if (ELK === 1)
{
	package_flush_cache();
}

// Reset the opcache
if (extension_loaded('Zend OPcache') && ini_get('opcache.enable') && stripos(BOARDDIR, ini_get('opcache.restrict_api')) !== 0)
{
	opcache_reset();
}

// Better safe, than sorry, just in case the autoloader doesn't cope well with the upgrade
require_once(SUBSDIR . '/Agreement.class.php');
require_once(SUBSDIR . '/PrivacyPolicy.class.php');

$agreement = new \Agreement('english');
$success = $agreement->storeBackup();
updateSettings(array('agreementRevision' => $success));

if (file_exists(BOARDDIR . '/privacypolicy.txt'))
{
	$privacypol = new \PrivacyPolicy('english');
	$success = $privacypol->storeBackup();
	updateSettings(array('privacypolicyRevision' => $success));
}
