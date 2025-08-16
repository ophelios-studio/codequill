<?php namespace Models\Core;

use Zephyrus\Database\DatabaseSession as BaseDatabaseSession;

final class DatabaseSession extends BaseDatabaseSession
{
    public function start(): void
    {
        $this->getDatabase()->registerTypeConversion('JSONB', function ($jsonValue) {
            return json_decode($jsonValue);
        });
    }
}
