<?php

namespace Binary\Component\CmsMigrator\Administrator\Event;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractEvent;

/**
 * Event class for migration operations.
 */
class MigrationEvent extends AbstractEvent
{
    protected $arguments = [];

    public function __construct(string $name, array $arguments = [])
    {
        parent::__construct($name);
        $this->arguments = $arguments;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function addResult($result): void
    {
        $this->results[] = $result;
    }

    public function getResults(): array
    {
        return $this->results ?? [];
    }
}
