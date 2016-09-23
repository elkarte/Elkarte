<?php

/**
 * This file contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

/**
 * Create a menu.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param array $menuData
 * @param array $menuOptions
 */
function createMenu($menuData, $menuOptions = array())
{
	$menu = Menu::instance();
	$menu->addOptions($menuOptions);
	$menu->addAreas($menuData);
	$include_data = $menu->prepareMenu();
	$menu->setContext();

	return $include_data;
}

/**
 * Delete a menu.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param string $menu_id
 */
function destroyMenu($menu_id = 'last')
{
	$menu = new Menu();
	$menu->destroyMenu($menu_id);
}

/**
 * Call the function or method for the selected menu item.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param array $selectedMenu
 */
function callMenu($selectedMenu)
{
	$menu = new Menu();
	$menu->callMenu($selectedMenu);
}