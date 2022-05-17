<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

/**
 * Just a simple template for now to output json
 * used to output the json formatted data for ajax calls
 */
function template_send_json()
{
	global $context;

	echo json_encode($context['json_data']);
}

/**
 * Just a simple template for now to output json
 * used to output the json formatted data for ajax calls
 */
function template_send_json_raw()
{
	global $context;

	echo $context['json_data'];
}
