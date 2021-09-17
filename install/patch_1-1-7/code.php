<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
{
    require_once(dirname(__FILE__) . '/SSI.php');
}
elseif (!defined('ELK'))
{
    die('<b>Error:</b> Cannot install - please verify you put this in the same place as ELK\'s index.php.');
}

// Remove-file during install does not work?
if (file_exists(EXTDIR . '/cssmin.php'))
{
    unlink(EXTDIR . '/cssmin.php');
}

// Fulltext index was improved to include subjects, but needs a rebuild, so remove any existing one
global $modSettings, $db_type;

// Only mysql/MariaDB
if ($db_type !== 'mysql')
{
    return;
}

require_once(SUBSDIR . '/ManageSearch.subs.php');

$fulltext_index = detectFulltextIndex();
alterFullTextIndex('{db_prefix}messages', $fulltext_index);

// Now if they were actually using that index, set them back to a standard search
if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'fulltext')
{
    // Set the default method.
    updateSettings(array(
        'search_index' => '',
    ));
}