<?php

namespace davidhirtz\yii2\media\modules\admin\models\forms\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\models\forms\SectionForm;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use yii\behaviors\SluggableBehavior;

/**
 * Class FileForm
 * @package davidhirtz\yii2\media\modules\admin\models\forms\base
 *
 * @property SectionForm[] $sections
 * @method static \davidhirtz\yii2\media\modules\admin\models\forms\FileForm findOne($condition)
 */
class FileForm extends File
{
    /**
     * @return array
     */
    public function behaviors(): array
    {
        return $this->customSlugBehavior ? parent::behaviors() : array_merge(parent::behaviors(), [
            'SluggableBehavior' => [
                'class' => SluggableBehavior::class,
                'attribute' => 'name',
                'immutable' => true,
                'ensureUnique' => true,
                'uniqueValidator' => [
                    'targetAttribute' => ['slug', 'parent_id'],
                ],
            ],
        ]);
    }

    /**
     * @return ActiveQuery
     */
    public function getSections(): ActiveQuery
    {
        return $this->hasMany(SectionForm::class, ['file_id' => 'id'])
            ->orderBy(['position' => SORT_ASC])
            ->indexBy('id')
            ->inverseOf('file');
    }
}