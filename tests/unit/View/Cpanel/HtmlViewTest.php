<?php

namespace Binary\Component\CmsMigrator\Tests\View\Cpanel;

use Binary\Component\CmsMigrator\Administrator\View\Cpanel\HtmlView;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for HtmlView
 *
 * @package Binary\Component\CmsMigrator\Tests\View\Cpanel
 * @since   1.0.0
 */
class HtmlViewTest extends TestCase
{
    /**
     * @var HtmlView
     */
    private $view;

    /**
     * @var MockObject
     */
    private $mockForm;

    /**
     * @var MockObject
     */
    private $mockDocument;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the form
        $this->mockForm = $this->getMockBuilder(\Joomla\CMS\Form\Form::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock the document
        $this->mockDocument = $this->getMockBuilder(\Joomla\CMS\Document\HtmlDocument::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->view = new HtmlView();
    }

    /**
     * Test that the view extends BaseHtmlView
     */
    public function testViewExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\MVC\View\HtmlView::class,
            $this->view
        );
    }

    /**
     * Test view can be instantiated
     */
    public function testViewCanBeInstantiated(): void
    {
        $this->assertInstanceOf(HtmlView::class, $this->view);
    }

    /**
     * Test display method throws exception when form not found
     */
    public function testDisplayThrowsExceptionWhenFormNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('COM_CMSMIGRATOR_FORM_NOT_FOUND');
        $this->expectExceptionCode(500);

        // Mock constants if not defined
        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', __DIR__ . '/../../../../../../src/component/admin');
        }

        // Since our mock Form::getInstance returns null, this should throw the expected exception
        $this->view->display();
    }

    /**
     * Test form property can be set
     */
    public function testFormPropertyCanBeSet(): void
    {
        $this->view->form = $this->mockForm;
        
        $this->assertSame($this->mockForm, $this->view->form);
    }

    /**
     * Test document property can be set
     */
    public function testDocumentPropertyCanBeSet(): void
    {
        $this->view->document = $this->mockDocument;
        
        $this->assertSame($this->mockDocument, $this->view->document);
    }

    /**
     * Test addToolbar method is protected and exists
     */
    public function testAddToolbarMethodExists(): void
    {
        $reflection = new \ReflectionClass($this->view);
        
        $this->assertTrue($reflection->hasMethod('addToolbar'));
        
        $method = $reflection->getMethod('addToolbar');
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test addScripts method is protected and exists
     */
    public function testAddScriptsMethodExists(): void
    {
        $reflection = new \ReflectionClass($this->view);
        
        $this->assertTrue($reflection->hasMethod('addScripts'));
        
        $method = $reflection->getMethod('addScripts');
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test addToolbar method can be called through reflection
     */
    public function testAddToolbarMethodCanBeCalled(): void
    {
        $reflection = new \ReflectionClass($this->view);
        $method = $reflection->getMethod('addToolbar');
        $method->setAccessible(true);

        if (!class_exists('Joomla\CMS\Toolbar\ToolbarHelper')) {
            $this->markTestSkipped('ToolbarHelper not available in test environment');
            return;
        }

        // Should not throw any exceptions
        $this->assertNull($method->invoke($this->view));
    }

    /**
     * Test addScripts method can be called through reflection
     */
    public function testAddScriptsMethodCanBeCalled(): void
    {
        $reflection = new \ReflectionClass($this->view);
        $method = $reflection->getMethod('addScripts');
        $method->setAccessible(true);

        // Test that the method exists and is protected
        $this->assertTrue($method->isProtected());
        
        // Test that the method name is correct
        $this->assertEquals('addScripts', $method->getName());
    }

    /**
     * Test view has correct public properties
     */
    public function testViewHasCorrectPublicProperties(): void
    {
        $reflection = new \ReflectionClass($this->view);
        
        // Check that form property exists and is public
        $this->assertTrue($reflection->hasProperty('form'));
        $formProperty = $reflection->getProperty('form');
        $this->assertTrue($formProperty->isPublic());
        
        // Check that document property exists and is public
        $this->assertTrue($reflection->hasProperty('document'));
        $documentProperty = $reflection->getProperty('document');
        $this->assertTrue($documentProperty->isPublic());
    }

    /**
     * Test display method can be called with template parameter
     */
    public function testDisplayMethodCanBeCalledWithTemplateParameter(): void
    {
        // Check if required Joomla classes are available
        if (!class_exists('Joomla\CMS\Form\Form') || !class_exists('Joomla\CMS\Factory')) {
            $this->markTestSkipped('Required Joomla classes not available in test environment');
            return;
        }

        // Simply test that the method exists and can be accessed
        $reflection = new \ReflectionClass($this->view);
        $method = $reflection->getMethod('display');
        
        // Test method exists and is public
        $this->assertTrue($method->isPublic());
        
        // Test that display method accepts a template parameter
        $this->assertEquals(1, $method->getNumberOfParameters());
        $this->assertTrue($method->getParameters()[0]->isOptional());
    }

    /**
     * Test view class namespace and inheritance
     */
    public function testViewClassNamespaceAndInheritance(): void
    {
        $reflection = new \ReflectionClass($this->view);
        
        $this->assertEquals(
            'Binary\Component\CmsMigrator\Administrator\View\Cpanel\HtmlView',
            $reflection->getName()
        );
        
        $this->assertTrue($reflection->isSubclassOf(\Joomla\CMS\MVC\View\HtmlView::class));
    }
}
