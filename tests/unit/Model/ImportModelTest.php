<?php

namespace Joomla\Component\CmsMigrator\Tests\Model;

use Joomla\Component\CmsMigrator\Administrator\Model\ImportModel;
use PHPUnit\Framework\TestCase;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

class ImportModelTest extends TestCase
{
    public function testImportThrowsExceptionForInvalidFile()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('COM_CMSMIGRATOR_INVALID_FILE');

        // Create a mock MVCFactory
        $mvcFactory = $this->createMock(MVCFactoryInterface::class);
        
        $model = new ImportModel();
        $model->setMVCFactory($mvcFactory);
        $model->import(['tmp_name' => '', 'error' => 4], 'json'); // UPLOAD_ERR_NO_FILE = 4
    }
}