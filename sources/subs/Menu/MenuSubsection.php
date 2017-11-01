<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version   1.1
 *
 */

namespace ElkArte\Menu;

class MenuSubsection
{
    /** @var string $label Text label for this subsection. */
    public $label = '';

    /** @var string $counter Index of counter specified in the menu options. */
    public $counter = '';

    /** @var string[] $permission Array of permissions to check for this subsection. */
    public $permission = [];

    /** @var bool $default Is this the default subaction - if not set for any will default to first... */
    public $default = false;

    /** @var bool $enabled Bool to say whether this should be enabled. */
    public $enabled = true;

    /** @var string[] $active Set the button active for other subsections. */
    public $active = [];

    /**
     * @param array $arr
     *
     * @return MenuSection
     */
    public static function buildFromArray(array $arr)
    {
        $subsection = new self;
        $vars = get_object_vars($subsection);
        foreach (array_replace(
                     $vars,
                     array_intersect_key($arr, $vars)
                 ) as $var => $val) {
            $subsection->{$var} = $val;
        }
        $subsection->label = $arr[0];
        $subsection->permission = isset($arr[1]) ? $arr[1] : [];
        $subsection->default = isset($arr[2]) ? $arr[2] : false;

        return $subsection;
    }
}
