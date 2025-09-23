<?php

namespace Joomla\Component\CmsMigrator\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Example test class to verify test environment setup
 *
 * @package Joomla\Component\CmsMigrator\Tests
 * @since   1.0.0
 */
class ExampleTest extends TestCase
{
    /**
     * Test that PHPUnit is working correctly
     */
    public function testPHPUnitIsWorking(): void
    {
        $this->assertIsString('test');
    }

    /**
     * Test that constants are defined correctly
     */
    public function testConstantsAreDefined(): void
    {
        $this->assertTrue(defined('_JEXEC'));
        $this->assertTrue(defined('JPATH_BASE'));
        $this->assertTrue(defined('JPATH_ROOT'));
        $this->assertTrue(defined('JPATH_SITE'));
    }

    /**
     * Test that autoloader is working for component classes
     */
    public function testAutoloaderWorking(): void
    {
        $this->assertTrue(
            class_exists('Joomla\Component\CmsMigrator\Administrator\Extension\CmsMigratorComponent')
        );
        $this->assertTrue(
            class_exists('Joomla\Component\CmsMigrator\Administrator\Controller\DisplayController')
        );
        $this->assertTrue(
            class_exists('Joomla\Component\CmsMigrator\Administrator\Model\ImportModel')
        );
    }
}
