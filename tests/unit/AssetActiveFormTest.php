<?php

namespace davidhirtz\yii2\media\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\widgets\forms\traits\AssetFieldsTrait;
use davidhirtz\yii2\media\tests\unit\models\TestAsset;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;

class AssetActiveFormTest extends Unit
{
    public function testAssetFieldsTrait()
    {
        $file = File::create();
        $file->alt_text = 'Image Alt Text';
        $file->basename = 'image';
        $file->extension = 'jpg';

        $file->populateFolderRelation(Folder::getDefault());

        $model = TestAsset::create();
        $model->populateFileRelation($file);

        $html = TestAssetActiveForm::widget([
            'action' => '/',
            'model' => $model,
        ]);

        $this->assertStringContainsString($model->getAttributeLabel('alt_text'), $html);
        $this->assertStringContainsString($file->alt_text, $html);
        $this->assertStringContainsString($file->getUrl(), $html);
    }
}

class TestAssetActiveForm extends ActiveForm
{
    use AssetFieldsTrait;

    /**
     * @uses static::altTextField()
     */
    public function init(): void
    {
        $this->fields = [
            'alt_text',
        ];

        parent::init();
    }
}
