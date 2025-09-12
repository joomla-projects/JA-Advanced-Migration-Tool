<?php

namespace Binary\Component\CmsMigrator\Tests\Model;

use Binary\Component\CmsMigrator\Administrator\Model\MediaModel;
use PHPUnit\Framework\TestCase;

class MediaModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('JPATH_ROOT')) {
            define('JPATH_ROOT', '/');
        }
    }

    /**
     * @dataProvider imageUrlProvider
     */
    public function testExtractImageUrlsFromContent(string $content, array $expectedUrls): void
    {
        $model = new MediaModel();
        $extractedUrls = $model->extractImageUrlsFromContent($content);
        $this->assertEquals($expectedUrls, $extractedUrls);
    }

    public static function imageUrlProvider(): array
    {
        return [
            ['<p>no images</p>', []],
            ['<img src="http://a.com/1.png">', ['http://a.com/1.png']],
            ['<a href="http://w.org/wp-content/uploads/img.jpg"></a>', ['http://w.org/wp-content/uploads/img.jpg']],
        ];
    }

    public function testMigrateMediaInContentReturnsEarlyForEmptyContent()
    {
        $model = new MediaModel();
        $this->assertEquals('', $model->migrateMediaInContent([], ''));
    }

    public function testMigrateMediaInContentFailsConnection()
    {
        $model = $this->getMockBuilder(MediaModel::class)
            ->onlyMethods(['connect'])
            ->getMock();
        $model->method('connect')->willReturn(false);

        $content = '<img src="http://a.com/1.png">';
        $this->assertEquals($content, $model->migrateMediaInContent([], $content));
    }

    /**
     * Test testConnection with missing required fields
     */
    public function testTestConnectionMissingFields()
    {
        $model = new MediaModel();
        $config = ['connection_type' => 'ftp']; // Missing host, username, password
        $result = $model->testConnection($config);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('COM_CMSMIGRATOR_MEDIA_CONNECTION_FIELDS_REQUIRED', $result['message']);
    }

    /**
     * Test testConnection with valid FTP config
     */
    public function testTestConnectionValidFtp()
    {
        $model = $this->getMockBuilder(MediaModel::class)
            ->onlyMethods(['testFtpConnection'])
            ->getMock();
        
        $model->method('testFtpConnection')->willReturn(['success' => true, 'message' => 'Connected']);
        
        $config = [
            'connection_type' => 'ftp',
            'host' => 'example.com',
            'username' => 'user',
            'password' => 'pass'
        ];
        $result = $model->testConnection($config);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Connected', $result['message']);
    }
}