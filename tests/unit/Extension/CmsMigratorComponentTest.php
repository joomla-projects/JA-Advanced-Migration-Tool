<?php

namespace Joomla\Component\CmsMigrator\Tests\Extension;

use Joomla\Component\CmsMigrator\Administrator\Extension\CmsMigratorComponent;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Test class for CmsMigratorComponent
 *
 * @package Joomla\Component\CmsMigrator\Tests\Extension
 * @since   1.0.0
 */
class CmsMigratorComponentTest extends TestCase
{
    /**
     * @var CmsMigratorComponent
     */
    private $component;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new CmsMigratorComponent();
    }

    /**
     * Test that the component extends MVCComponent
     */
    public function testComponentExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\Extension\MVCComponent::class,
            $this->component
        );
    }

    /**
     * Test that the component implements BootableExtensionInterface
     */
    public function testComponentImplementsBootableInterface(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\Extension\BootableExtensionInterface::class,
            $this->component
        );
    }

    /**
     * Test the boot method
     */
    public function testBootMethod(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        
        // The boot method should not throw any exceptions
        $this->assertNull($this->component->boot($container));
    }

    /**
     * Test component can be instantiated
     */
    public function testComponentCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CmsMigratorComponent::class, $this->component);
    }
}
