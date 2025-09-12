<?php

namespace Joomla\CMS\Extension;

/**
 * Mock interface for BootableExtensionInterface for testing
 */
interface BootableExtensionInterface
{
    public function boot(\Psr\Container\ContainerInterface $container);
}

/**
 * Mock class for MVCComponent for testing
 */
class MVCComponent
{
    // Empty implementation for testing
}
