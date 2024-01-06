<?php

namespace davidhirtz\yii2\media\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\models\traits\AssetParentTrait;
use davidhirtz\yii2\media\models\traits\AssetTrait;
use davidhirtz\yii2\media\modules\admin\widgets\forms\traits\AssetFieldsTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;

class AssetTest extends Unit
{
    public function testAssetActiveForm()
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

class TestAssetParent extends ActiveRecord implements AssetParentInterface
{
    use AssetParentTrait;

    public function getAssets(): ActiveQuery
    {
        return $this->hasMany(TestAsset::class, ['parent_id' => 'id']);
    }
}

class TestAsset extends ActiveRecord implements AssetInterface
{
    use AssetTrait;

    public function attributes(): array
    {
        return [
            'id',
            'file_id',
            'parent_id',
            'alt_text',
        ];
    }

    public function rules(): array
    {
        return [
            [
                ['alt_text'],
                'string',
            ],
        ];
    }

    public function getFileCountAttribute(): string
    {
        return 'asset_count';
    }

    public function getParent(): AssetParentInterface
    {
        return TestAssetParent::instance();
    }

    public function getParentGridView(): string
    {
        return '';
    }

    public function getParentName(): string
    {
        return 'Test Parent';
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
        $this->fields ??= [
            'alt_text',
        ];

        parent::init();
    }
}
