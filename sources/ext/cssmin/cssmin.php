<?php

/**
 * Initialize the CSS Minifier environment.  This is here as
 * the 1.1 autoloader is not compatible. Remove this in 2.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
 *
 */

// Load the files like the autoloader should
require_once dirname(__FILE__) . '/tubalmartin/Colors.php';
require_once dirname(__FILE__) . '/tubalmartin/Command.php';
require_once dirname(__FILE__) . '/tubalmartin/Utils.php';
require_once dirname(__FILE__) . '/tubalmartin/Minifier.php';

/**
 * Calls the css minifier
 *
 * @param string $input_css
 * @return string
 */
function CSSmin($input_css) {
	$compressor = new \tubalmartin\CssMin\Minifier();

	// Split long lines in the output approximately every 1000 chars.
	$compressor->setLineBreakPosition(1000);

	// Override / Set some PHP configuration options
	detectServer()->setMemoryLimit('256M');
	detectServer()->setTimeLimit(120);
	$compressor->setPcreBacktrackLimit(3000000);
	$compressor->setPcreRecursionLimit(150000);

	// Compress the CSS code!
	return $compressor->run($input_css);
}