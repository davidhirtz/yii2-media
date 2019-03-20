<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;

/**
 * Class File.
 * @package davidhirtz\yii2\media\models\base
 *
 * @property int $id
 * @property int $status
 * @property string $name
 * @property string $folder
 * @property string $filename
 * @property string $type
 * @property integer $width
 * @property integer $height
 * @property integer $size
 * @method static \davidhirtz\yii2\media\models\File findOne($condition)
 */
class File extends ActiveRecord
{
    use ModuleTrait;

    /**
     * Constants.
     */
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * @return UserQuery
     */
    public function getUpdated(): UserQuery
    {
        return $this->hasOne(User::class, ['id' => 'updated_by_user_id']);
    }


    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), []);
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {

        parent::afterFind();
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        $this->attachBehaviors([
            'TimestampBehavior' => [
                'class' => TimestampBehavior::class,
                'value' => function () {
                    return new DateTime;
                },
            ],
            'BlameableBehavior' => [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    static::EVENT_BEFORE_INSERT => ['updated_by_user_id'],
                    static::EVENT_BEFORE_UPDATE => ['updated_by_user_id'],
                ],
            ],
        ]);

        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     * @return FileQuery
     */
    public static function find(): FileQuery
    {
        return new FileQuery(get_called_class());
    }

    /**
     * @return array
     */
    public static function getStatuses(): array
    {
        return [
            static::STATUS_ENABLED => [
                'name' => Yii::t('skeleton', 'Enabled'),
                'icon' => 'globe',
            ],
            static::STATUS_DISABLED => [
                'name' => Yii::t('skeleton', 'Disabled'),
                'icon' => 'lock',
            ],
        ];
    }

    /**
     * @return string|null
     */
    public function getStatusName(): string
    {
        return $this->status ? static::getStatuses()[$this->status]['name'] : null;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->status == static::STATUS_ENABLED;
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return $this->status == static::STATUS_DISABLED;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'slug' => Yii::t('media', 'Url'),
            'title' => Yii::t('skeleton', 'Meta title'),
            'description' => Yii::t('media', 'Meta description'),
            'section_count' => Yii::t('skeleton', 'Sections'),
            'file_count' => Yii::t('skeleton', 'Media'),
        ]);
    }

    /**
     * @return string
     */
    public function formName(): string
    {
        return 'File';
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return static::getModule()->getTableName('file');
    }
}