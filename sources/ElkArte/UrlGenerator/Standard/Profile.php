<?php

/**
 * Standard representation of profile URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\UrlGenerator\Standard;

class Profile extends Standard
{
    /** {@inheritDoc} */
    protected $_types = ['profile'];

    /**
     * {@inheritDoc}
     */
    public function generate($params)
    {
        // Delegate to the shared query generator to ensure consistent encoding and
        // proper preservation of substitution/sprintf tokens and hash handling.
        return $this->generateQuery($params);
    }
}
