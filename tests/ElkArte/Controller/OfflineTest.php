<?php

namespace ElkArte\Controller;

use tests\ElkArteCommonSetupTest;

class OfflineTest extends ElkArteCommonSetupTest
{
    /**
    * @var Offline
    */
    private $offlineController;

    public function setUp(): void
    {
        $this->offlineController = new Offline();
    }

    /**
     * Testing action_offline method in Offline Class.
     * This method loads the 'Offline' template and its 'offline' subtemplate for the display.
     */
    public function testActionOffline(): void
    {
        $this->expectOutputString('RETRY');
        $this->offlineController->action_offline();
    }
}