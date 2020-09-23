<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\helpers\Image;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Class Transformation
 * @package davidhirtz\yii2\media\models\base
 *
 * @property int $id
 * @property int $file_id
 * @property string $name
 * @property string $extension
 * @property int $width
 * @property int $height
 * @property int $size
 * @property DateTime $created_at
 * @property \davidhirtz\yii2\media\models\File $file
 *
 * @method static \davidhirtz\yii2\media\models\Transformation findOne($condition)
 */
class Transformation extends ActiveRecord
{
    use ModuleTrait;

    /**
     * @var bool
     */
    public $scaleUp = true;

    /**
     * @var bool whether aspect ratio should be kept. Only applies if both width and height are set.
     */
    public $keepAspectRatio = false;

    /**
     * @var bool
     */
    public $tinyPngCompress = false;

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
     * @var string
     */
    private $_image;

    /**
     * Sets module parameters.
     */
    public function init()
    {
        $this->tinyPngCompress = static::getModule()->tinyPngCompress;
        parent::init();
    }

    /**
     * Rules are only needed for file id and name, as the attributes will be set by the model's
     * beforeSave method.
     *
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
            foreach (static::getModule()->transformations[$this->name] as $attribute => $value) {
                $this->$attribute = $value;
            }

            $this->file_id = $this->file->id;

            if (!$this->extension) {
                $this->extension = $this->file->extension;
            }

            FileHelper::createDirectory(pathinfo($this->getFilePath(), PATHINFO_DIRNAME));

            if ($this->tinyPngCompress && !$this->isWebp()) {
                $this->createTransformationWithTinyPng();
            } else {
                $this->createTransformation();
            }

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

    /**
     * @inheritDoc
     */
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
     * Creates transformation through TinyPNG web service. Note: $imageOptions are ignored
     * by this method.
     */
    protected function createTransformationWithTinyPng()
    {
        if (empty(Yii::$app->params['tinyPngApiKey'])) {
            throw new InvalidConfigException('The application parameter "tinyPngApiKey" must be set to use the TinyPNG web service.');
        }

        \Tinify\setKey(Yii::$app->params['tinyPngApiKey']);

        $image = \Tinify\fromFile($this->file->folder->getUploadPath() . $this->file->getFilename())->resize(array_filter([
            'method' => $this->width && $this->height ? 'cover' : 'scale',
            'width' => $this->width,
            'height' => $this->height,
        ]));

        $this->size = $image->toFile($this->getFilePath());
        $this->width = $image->result()->width();
        $this->height = $image->result()->height();
    }

    /**
     * Creates transformation through the installed image library.
     */
    protected function createTransformation()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $filename = $this->file->folder->getUploadPath() . $this->file->getFilename();

        if (!$this->width || !$this->height || $this->keepAspectRatio) {
            $image = Image::resize($filename, $this->width, $this->height, $this->keepAspectRatio, $this->scaleUp);
        } else {
            $image = Image::fit($filename, $this->width, $this->height, $this->backgroundColor, $this->backgroundAlpha);
        }

        $image->save($this->getFilePath(), $this->imageOptions);

        $this->width = $image->getSize()->getWidth();
        $this->height = $image->getSize()->getHeight();
        $this->size = filesize($this->getFilePath());
    }

    /**
     * @return ActiveQuery
     */
    public function getFile(): ActiveQuery
    {
        return $this->hasOne(File::class, ['id' => 'file_id']);
    }

    /**
     * @param string $extension
     * @return string
     */
    public function getFileUrl($extension = null): string
    {
        if (!$extension) {
            $extension = $this->extension;
        }

        return $this->file->folder->getUploadUrl() . $this->name . '/' . $this->file->basename . '.' . $extension;
    }

    /**
     * @param string $extension
     * @return string
     */
    public function getFilePath($extension = null): string
    {
        if (!$extension) {
            $extension = $this->extension;
        }

        return $this->getUploadPath() . $this->file->basename . '.' . $extension;
    }

    /**
     * @return string
     */
    public function getUploadPath(): string
    {
        return $this->file->folder->getUploadPath() . $this->name . DIRECTORY_SEPARATOR;
    }

    /**
     * @return bool
     */
    public function isWebp(): bool
    {
        return strtolower($this->extension) === 'webp';
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