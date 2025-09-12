<?php

namespace Psr\Container;

/**
 * Mock interface for ContainerInterface for testing
 */
interface ContainerInterface
{
    public function get($id);
    public function has($id);
}
