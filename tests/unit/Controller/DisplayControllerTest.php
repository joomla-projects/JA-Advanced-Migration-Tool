<?php

namespace Joomla\Component\CmsMigrator\Tests\Controller;

use Joomla\Component\CmsMigrator\Administrator\Controller\DisplayController;
use PHPUnit\Framework\TestCase;

/**
 * Test class for DisplayController
 *
 * @package Joomla\Component\CmsMigrator\Tests\Controller
 * @since   1.0.0
 */
class DisplayControllerTest extends TestCase
{
    /**
     * @var DisplayController
     */
    private $controller;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock necessary dependencies that BaseController requires
        $app = $this->getMockBuilder(\Joomla\CMS\Application\CMSApplication::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $input = $this->getMockBuilder(\Joomla\Input\Input::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $config = [];
        $this->controller = new DisplayController($config, null, $app, $input);
    }

    /**
     * Test that the controller extends BaseController
     */
    public function testControllerExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\MVC\Controller\BaseController::class,
            $this->controller
        );
    }

    /**
     * Test default view property
     */
    public function testDefaultViewProperty(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('default_view');
        $property->setAccessible(true);
        
        $this->assertEquals('Cpanel', $property->getValue($this->controller));
    }

    /**
     * Test controller can be instantiated
     */
    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DisplayController::class, $this->controller);
    }
}
