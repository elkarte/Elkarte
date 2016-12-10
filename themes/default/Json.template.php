<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
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