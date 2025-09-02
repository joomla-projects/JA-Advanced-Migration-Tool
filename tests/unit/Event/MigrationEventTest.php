<?php

namespace Binary\Component\CmsMigrator\Tests\Event;

use Binary\Component\CmsMigrator\Administrator\Event\MigrationEvent;
use PHPUnit\Framework\TestCase;

/**
 * Test class for MigrationEvent
 *
 * @package Binary\Component\CmsMigrator\Tests\Event
 * @since   1.0.0
 */
class MigrationEventTest extends TestCase
{
    /**
     * @var MigrationEvent
     */
    private $event;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->event = new MigrationEvent('test_event', ['key' => 'value']);
    }

    /**
     * Test that the event extends AbstractEvent
     */
    public function testEventExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\Event\AbstractEvent::class,
            $this->event
        );
    }

    /**
     * Test event can be instantiated
     */
    public function testEventCanBeInstantiated(): void
    {
        $this->assertInstanceOf(MigrationEvent::class, $this->event);
    }

    /**
     * Test constructor sets event name
     */
    public function testConstructorSetsEventName(): void
    {
        $eventName = 'onMigrationConvert';
        $event = new MigrationEvent($eventName);
        
        $this->assertEquals($eventName, $event->getName());
    }

    /**
     * Test constructor accepts arguments
     */
    public function testConstructorAcceptsArguments(): void
    {
        $arguments = ['sourceCms' => 'wordpress', 'filePath' => '/path/to/file'];
        $event = new MigrationEvent('test_event', $arguments);
        
        $this->assertEquals($arguments, $event->getArguments());
    }

    /**
     * Test constructor with empty arguments
     */
    public function testConstructorWithEmptyArguments(): void
    {
        $event = new MigrationEvent('test_event');
        
        $this->assertEquals([], $event->getArguments());
    }

    /**
     * Test getArguments returns correct data
     */
    public function testGetArgumentsReturnsCorrectData(): void
    {
        $arguments = ['key1' => 'value1', 'key2' => 'value2'];
        $event = new MigrationEvent('test_event', $arguments);
        
        $retrievedArguments = $event->getArguments();
        
        $this->assertEquals($arguments, $retrievedArguments);
        $this->assertEquals('value1', $retrievedArguments['key1']);
        $this->assertEquals('value2', $retrievedArguments['key2']);
    }

    /**
     * Test addResult method
     */
    public function testAddResult(): void
    {
        $result = 'test result';
        $this->event->addResult($result);
        
        $results = $this->event->getResults();
        
        $this->assertIsArray($results);
        $this->assertContains($result, $results);
        $this->assertCount(1, $results);
    }

    /**
     * Test addResult method with multiple results
     */
    public function testAddResultWithMultipleResults(): void
    {
        $result1 = 'first result';
        $result2 = 'second result';
        $result3 = ['array' => 'result'];
        
        $this->event->addResult($result1);
        $this->event->addResult($result2);
        $this->event->addResult($result3);
        
        $results = $this->event->getResults();
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertContains($result1, $results);
        $this->assertContains($result2, $results);
        $this->assertContains($result3, $results);
    }

    /**
     * Test getResults returns empty array when no results added
     */
    public function testGetResultsReturnsEmptyArrayWhenNoResultsAdded(): void
    {
        $event = new MigrationEvent('test_event');
        
        $results = $event->getResults();
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test getResults returns array even after adding null result
     */
    public function testGetResultsReturnsArrayEvenAfterAddingNullResult(): void
    {
        $this->event->addResult(null);
        
        $results = $this->event->getResults();
        
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    /**
     * Test event can handle different result types
     */
    public function testEventCanHandleDifferentResultTypes(): void
    {
        $this->event->addResult('string result');
        $this->event->addResult(123);
        $this->event->addResult(['array' => 'result']);
        $this->event->addResult(true);
        $this->event->addResult(null);
        
        $results = $this->event->getResults();
        
        $this->assertCount(5, $results);
        $this->assertEquals('string result', $results[0]);
        $this->assertEquals(123, $results[1]);
        $this->assertEquals(['array' => 'result'], $results[2]);
        $this->assertTrue($results[3]);
        $this->assertNull($results[4]);
    }

    /**
     * Test event arguments are immutable through getArguments
     */
    public function testEventArgumentsAreImmutableThroughGetArguments(): void
    {
        $originalArguments = ['key' => 'value'];
        $event = new MigrationEvent('test_event', $originalArguments);
        
        $retrievedArguments = $event->getArguments();
        $retrievedArguments['key'] = 'modified';
        
        // Original arguments should remain unchanged
        $this->assertEquals($originalArguments, $event->getArguments());
    }

    /**
     * Test event name is preserved
     */
    public function testEventNameIsPreserved(): void
    {
        $eventName = 'onMigrationComplete';
        $event = new MigrationEvent($eventName, ['data' => 'test']);
        
        $this->assertEquals($eventName, $event->getName());
    }
}
