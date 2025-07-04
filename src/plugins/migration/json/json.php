<?php

defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgMigrationJson extends CMSPlugin
{
    public function onMigrationConvert(AbstractEvent $event)
    {
        $args = $event->getArguments();
        $sourceCms = $args['sourceCms'] ?? null;
        $filePath = $args['filePath'] ?? null;

        if ($sourceCms !== 'json' || empty($filePath)) {
            return;
        }

        $jsonContent = file_get_contents($filePath);
        if (empty($jsonContent)) {
            return;
        }

        // The content is already JSON, so we just pass it along.
        $event->addResult($jsonContent);
    }
} 