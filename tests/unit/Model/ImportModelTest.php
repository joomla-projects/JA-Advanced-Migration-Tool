<?php

namespace Binary\Component\CmsMigrator\Tests\Model;

use Binary\Component\CmsMigrator\Administrator\Model\ImportModel;
use PHPUnit\Framework\TestCase;

class ImportModelTest extends TestCase
{
    public function testImportThrowsExceptionForInvalidFile()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('COM_CMSMIGRATOR_INVALID_FILE');

        $model = new ImportModel();
        $model->import(['tmp_name' => '', 'error' => 4], 'json'); // UPLOAD_ERR_NO_FILE = 4
    }
}