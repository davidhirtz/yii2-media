<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\unit\widgets;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\forms\traits\AssetFieldsTrait;
use davidhirtz\yii2\media\tests\data\models\TestAsset;
use davidhirtz\yii2\skeleton\codeception\traits\AssetDirectoryTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;

class AssetActiveFormTest extends Unit
{
    use AssetDirectoryTrait;

    protected function _before(): void
    {
        $this->createAssetDirectory();
        parent::_before();
    }

    protected function _after(): void
    {
        $this->removeAssetDirectory();
        parent::_after();
    }

    public function testAssetFieldsTrait(): void
    {
        $file = File::create();
        $file->alt_text = 'Image Alt Text';
        $file->basename = 'image';
        $file->extension = 'jpg';

        $file->populateFolderRelation(FolderCollection::getDefault());

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
    #[\Override]
    public function init(): void
    {
        $this->fields = [
            'alt_text',
        ];

        parent::init();
    }
}
