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
* The offline sub-template, just says sorry bub
*/
function template_offline()
{
	global $txt;

	echo '
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<style>
	body {
	width: 100%;
		min-height: 100vh;
		margin: 0;
		padding: 0;
		background: -webkit-linear-gradient(-45deg, #222222 0, #222222 25%, #444444 50%, #888888 75%, #888888 100%);
	}
	.wrapper {
		position: absolute;
		top: 50%;
		left: 50%;
		align-items: center;
		justify-content: center;
		transform: translate(-50%, -50%);
		text-align: center;
	}
	h1 {
		color: #fefefe;
		font-weight: bold;
		font-size: 50px;
		letter-spacing: 5px;
		line-height: 1rem;
		text-shadow: 0 0 3px white;
	}
	h4 {
		color: #fefefe;
		font-weight: 300;
		font-size: 16px;
	}
	.button {
		display: block;
		margin: 20px 0 0;
		padding: 15px 30px;
		background: #3D6E32;
		color: #fefefe;
		letter-spacing: 5px;
		border-radius: .4rem;
		text-decoration: none;
		box-shadow: 0 0 5px #5BA048;
	}
	</style>
	<title>Document</title>
</head>
<body>
	<div class="wrapper">
	<h1>', $txt['offline'], '</h1>
	<h4>', $txt['check_connection'], '</h4>
	
	<a href="." class="button">', $txt['retry'], '</a>
	</div>
</body>
</html>';
}