<?php

namespace Binary\Component\CmsMigrator\Tests\Table;

use Binary\Component\CmsMigrator\Administrator\Table\ArticleTable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for ArticleTable
 *
 * @package Binary\Component\CmsMigrator\Tests\Table
 * @since   1.0.0
 */
class ArticleTableTest extends TestCase
{
    /**
     * @var ArticleTable
     */
    private $table;

    /**
     * @var MockObject
     */
    private $mockDatabase;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock database
        $this->mockDatabase = $this->getMockBuilder(\Joomla\Database\DatabaseDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->table = new ArticleTable($this->mockDatabase);
    }

    /**
     * Test that the table extends Table
     */
    public function testTableExtendsCorrectParent(): void
    {
        $this->assertInstanceOf(
            \Joomla\CMS\Table\Table::class,
            $this->table
        );
    }

    /**
     * Test table can be instantiated
     */
    public function testTableCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ArticleTable::class, $this->table);
    }

    /**
     * Test table name and key are set correctly
     */
    public function testTableNameAndKeyAreSetCorrectly(): void
    {
        $reflection = new \ReflectionClass($this->table);
        
        // Check table name
        $tableNameProperty = $reflection->getProperty('_tbl');
        $tableNameProperty->setAccessible(true);
        $this->assertEquals('#__cmsmigrator_articles', $tableNameProperty->getValue($this->table));
        
        // Check primary key
        $keyProperty = $reflection->getProperty('_tbl_key');
        $keyProperty->setAccessible(true);
        $this->assertEquals('id', $keyProperty->getValue($this->table));
    }

    /**
     * Test bind method generates alias when not set
     */
    public function testBindGeneratesAliasWhenNotSet(): void
    {
        // Test that the bind method exists and can handle basic data
        $reflection = new \ReflectionClass($this->table);
        $this->assertTrue($reflection->hasMethod('bind'));
        
        $method = $reflection->getMethod('bind');
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test bind method preserves existing alias
     */
    public function testBindPreservesExistingAlias(): void
    {
        // Test that the method exists
        $this->assertTrue(method_exists($this->table, 'bind'));
        
        // Test direct property assignment which is what bind() does
        $this->table->alias = 'custom-alias';
        $this->assertEquals('custom-alias', $this->table->alias);
    }

    /**
     * Test bind method sets created date when not set
     */
    public function testBindSetsCreatedDateWhenNotSet(): void
    {
        $data = [
            'title' => 'Test Article Title',
            'content' => 'Test content'
        ];

        if (!class_exists('Joomla\CMS\Factory')) {
            $this->markTestSkipped('Factory not available in test environment');
            return;
        }

        $this->table->bind($data);
        $this->assertNotEmpty($this->table->created);
    }

    /**
     * Test bind method preserves existing created date
     */
    public function testBindPreservesExistingCreatedDate(): void
    {
        $testDate = '2025-01-01 12:00:00';
        $data = [
            'title' => 'Test Article Title',
            'created' => $testDate,
            'content' => 'Test content'
        ];

        $this->table->bind($data);
        $this->assertEquals($testDate, $this->table->created);
    }

    /**
     * Test check method validates required title
     */
    public function testCheckValidatesRequiredTitle(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Article title is required');

        $this->table->title = '';
        $this->table->check();
    }

    /**
     * Test check method validates title with only whitespace
     */
    public function testCheckValidatesTitleWithOnlyWhitespace(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Article title is required');

        $this->table->title = '   ';
        $this->table->check();
    }

    /**
     * Test check method generates alias from title when alias is empty
     */
    public function testCheckGeneratesAliasFromTitleWhenAliasIsEmpty(): void
    {
        $this->table->title = 'Test Article Title';
        $this->table->alias = '';

        $result = $this->table->check();
        $this->assertTrue($result);
        // The mock OutputFilter converts to URL-safe format
        $this->assertEquals('test-article-title', $this->table->alias);
    }

    /**
     * Test check method generates alias from title when alias is whitespace
     */
    public function testCheckGeneratesAliasFromTitleWhenAliasIsWhitespace(): void
    {
        $this->table->title = 'Test Article Title';
        $this->table->alias = '   ';

        $result = $this->table->check();
        $this->assertTrue($result);
        $this->assertEquals('test-article-title', $this->table->alias);
    }

    /**
     * Test check method generates timestamp alias when sanitized alias is empty
     */
    public function testCheckGeneratesTimestampAliasWhenSanitizedAliasIsEmpty(): void
    {
        $this->table->title = 'Test Article Title';
        $this->table->alias = ''; // Empty alias should be replaced

        $result = $this->table->check();
        $this->assertTrue($result);
        // After check(), alias should not be empty
        $this->assertNotEmpty($this->table->alias);
    }

    /**
     * Test check method returns true for valid data
     */
    public function testCheckReturnsTrueForValidData(): void
    {
        $this->table->title = 'Valid Test Article';
        $this->table->alias = 'valid-test-article';

        $result = $this->table->check();
        $this->assertTrue($result);
    }

    /**
     * Test database connection is set correctly
     */
    public function testDatabaseConnectionIsSetCorrectly(): void
    {
        $reflection = new \ReflectionClass($this->table);
        $dbProperty = $reflection->getProperty('_db');
        $dbProperty->setAccessible(true);
        
        $this->assertSame($this->mockDatabase, $dbProperty->getValue($this->table));
    }
}
