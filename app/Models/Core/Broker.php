<?php namespace Models\Core;

use Zephyrus\Core\Application;
use Zephyrus\Database\DatabaseBroker;

abstract class Broker extends DatabaseBroker
{
    private Application $application;

    /**
     * @param Application|null $application
     */
    public function __construct(?Application $application = null)
    {
        $this->application = $application ?? Application::getInstance();
        parent::__construct($this->application->getDatabase());
    }

    public function getApplication(): Application
    {
        return $this->application;
    }
}
