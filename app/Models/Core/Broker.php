<?php namespace Models\Core;

use Zephyrus\Core\Application;
use Zephyrus\Database\DatabaseBroker;

abstract class Broker extends DatabaseBroker
{
    private Application $application;

    /**
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
        parent::__construct($application->getDatabase());
    }

    public function getApplication(): Application
    {
        return $this->application;
    }
}
