<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\helpers\Image;
use Yii;

/**
 * Class Transformation.
 * @package davidhirtz\yii2\media\models\base
 *
 * @property int $id
 * @property int $file_id
 * @property string $name
 * @property string $extension
 * @property integer $width
 * @property integer $height
 * @property integer $size
 * @property DateTime $created_at
 * @property \davidhirtz\yii2\media\models\File $file
 *
 * @method static \davidhirtz\yii2\media\models\Transformation findOne($condition)
 */
class Transformation extends ActiveRecord
{
    use ModuleTrait;

    /**
     * @var
     */
    public $scaleUp = true;

    /**
     * @var string
     */
    public $backgroundColor;

    /**
     * @var int
     */
    public $backgroundAlpha;

    /**
     * @var array
     */
    public $imageOptions = [];

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [
                ['file_id'],
                'validateFile',
            ],
            [
                ['name'],
                'in',
                'range' => array_keys(static::getModule()->transformations),
            ],
        ];
    }

    /**
     * @see Transformation::rules()
     */
    public function validateFile()
    {
        if (!$this->file || !$this->file->isTransformableImage()) {
            $this->addInvalidAttributeError('file_id');
        }
    }

    /**
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        if ($this->file->isValidTransformation($this->name)) {

            $this->setAttributes(static::getModule()->transformations[$this->name], false);
            $this->file_id = $this->file->id;

            if (!$this->extension) {
                $this->extension = $this->file->extension;
            }

            FileHelper::createDirectory(pathinfo($this->getFilePath(), PATHINFO_DIRNAME));
            ini_set('memory_limit', '256M');

            $image = Image::smartResize($this->file->folder->getUploadPath() . $this->file->getFilename(), $this->width, $this->height, $this->scaleUp, $this->backgroundColor, $this->backgroundAlpha);
            $image->save($this->getFilePath(), $this->imageOptions);

            $this->width = $image->getSize()->getWidth();
            $this->height = $image->getSize()->getHeight();
            $this->size = filesize($this->getFilePath());

            // This should only ever be needed if a file was deleted or corrupted.
            static::deleteAll(['file_id' => $this->file_id, 'name' => $this->name]);

            $this->attachBehaviors([
                'TimestampBehavior' => [
                    'class' => 'davidhirtz\yii2\skeleton\behaviors\TimestampBehavior',
                    'attributes' => [
                        static::EVENT_BEFORE_INSERT => ['created_at'],
                    ],
                ],
            ]);

            return parent::beforeSave($insert);
        }

        return false;
    }

    public function afterSave($insert, $changedAttributes)
    {
        $this->file->recalculateTransformationCount();
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        $this->file->recalculateTransformationCount();
        @unlink($this->getFilePath());
        parent::afterDelete();
    }

    /**
     * @return ActiveQuery
     */
    public function getFile(): ActiveQuery
    {
        return $this->hasOne(File::class, ['id' => 'file_id']);
    }

    /**
     * @return string
     */
    public function getFileUrl()
    {
        return $this->file->folder->getUploadUrl() . $this->name . '/' . $this->file->basename . '.' . $this->extension;
    }
    
    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->file->folder->getUploadPath() . $this->name . DIRECTORY_SEPARATOR . $this->file->basename . '.' . $this->extension;
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'name' => Yii::t('media', 'Transformation'),
            'file_id' => Yii::t('media', 'File'),
            'dimensions' => Yii::t('media', 'Dimensions'),
            'size' => Yii::t('media', 'Size'),
            'created_at' => Yii::t('media', 'Created'),
        ]);
    }

    /**
     * @return string
     */
    public function formName(): string
    {
        return 'Transformation';
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return static::getModule()->getTableName('transformation');
    }
}