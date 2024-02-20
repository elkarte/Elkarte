<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Calendar;

use ElkArte\AdminController\ManageCalendarModule;
use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * This class's task is to bind the posting of a topic to a calendar event.
 * Used when from the calendar controller the poster is redirected to the post page.
 *
 * @package Calendar
 */
class Admin extends AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		return [
			['addMenu', [Admin::class, 'addMenu'], []],
			['addSearch', [Admin::class, 'addSearch'], []],
		];
	}

	/**
	 * Used to add the Calendar entry to the admin menu.
	 *
	 * @param array $admin_areas The admin menu array
	 */
	public function addMenu(&$admin_areas)
	{
		global $txt, $modSettings;

		$admin_areas['layout']['areas']['managecalendar'] = [
			'label' => $txt['manage_calendar'],
			'controller' => ManageCalendarModule::class,
			'function' => 'action_index',
			'class' => 'i-calendar i-admin',
			'permission' => ['admin_forum'],
			'enabled' => featureEnabled('cd'),
			'subsections' => [
				'holidays' => [$txt['manage_holidays'], 'admin_forum', 'enabled' => !empty($modSettings['cal_enabled'])],
				'settings' => [$txt['calendar_settings'], 'admin_forum'],
			],
		];
	}

	/**
	 * Used to add the Calendar entry to the admin search.
	 *
	 * @param string[] $language_files
	 * @param string[] $include_files
	 * @param array $settings_search
	 */
	public function addSearch(&$language_files, &$include_files, &$settings_search)
	{
		$language_files[] = 'ManageCalendar';
		$include_files[] = 'ManageCalendarModule.controller';
		$settings_search[] = ['settings_search', 'area=managecalendar;sa=settings', ManageCalendarModule::class];
	}
}
