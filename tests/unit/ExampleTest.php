<?php

namespace Binary\Component\CmsMigrator\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Example test class to verify test environment setup
 *
 * @package Binary\Component\CmsMigrator\Tests
 * @since   1.0.0
 */
class ExampleTest extends TestCase
{
    /**
     * Test that true is true (basic test to verify PHPUnit is working)
     */
    public function testTrueIsTrue(): void
    {
        $this->assertTrue(true);
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
            class_exists('Binary\Component\CmsMigrator\Administrator\Extension\CmsMigratorComponent')
        );
        $this->assertTrue(
            class_exists('Binary\Component\CmsMigrator\Administrator\Controller\DisplayController')
        );
        $this->assertTrue(
            class_exists('Binary\Component\CmsMigrator\Administrator\Model\ImportModel')
        );
    }
}
