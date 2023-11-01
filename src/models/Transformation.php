<?php

namespace davidhirtz\yii2\media\models;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\models\traits\FileRelationTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\helpers\Image;
use Exception;
use Imagine\Image\ImageInterface;
use yii\base\ModelEvent;
use Yii;

/**
 * @property int $id
 * @property int $file_id
 * @property string $name
 * @property string $extension
 * @property int $width
 * @property int $height
 * @property int $size
 * @property DateTime $created_at
 */
class Transformation extends ActiveRecord
{
    use ModuleTrait;
    use FileRelationTrait;

    /**
     * @var bool whether image can be scaled up
     */
    public bool$scaleUp = true;

    /**
     * @var bool whether an aspect ratio should be kept. Only applies if both width and height are set.
     */
    public bool$keepAspectRatio = false;

    /**
     * @var string|int|null the background color for transformations
     */
    public string|int|null $backgroundColor = null;

    /**
     * @var int|null the background alpha for transformations
     */
    public ?int $backgroundAlpha = null;

    /**
     * @var array containing additional image options, allowed options are `jpeg_quality`, png_compression_level` and
     * `webp_quality`. Resolution via `resolution-units`, `resolution-x` and `resolution-y`.
     *
     * @see https://imagine.readthedocs.io/en/stable/usage/introduction.html#save-images
     */
    public array $imageOptions = [
        'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
        'resolution-x' => 72,
        'resolution-y' => 72,
        'jpeg_quality' => 75,
        'png_compression_level' => 7,
        'webp_quality' => 80,
    ];

    /**
     * Event that is triggered before creating the transformation. Set {@link ModelEvent::isValid} to `false` to alter
     * the transformation method.
     */
    public const EVENT_BEFORE_TRANSFORMATION = 'beforeTransformation';

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
                ['extension'],
                function () {
                    if (!$this->extension) {
                        $this->extension = $this->file->extension ?? null;
                    }
                },
            ],
            [
                ['name'],
                'validateTransformationName',
            ],
            [
                ['name'],
                'unique',
                'targetAttribute' => ['file_id', 'name', 'extension'],
            ],
        ];
    }

    /**  @noinspection PhpUnused {@see static::rules()} */
    public function validateFile(): void
    {
        if (!$this->file || !$this->file->isTransformableImage()) {
            $this->addInvalidAttributeError('file_id');
        }
    }

    /**  @noinspection PhpUnused {@see static::rules()} */
    public function validateTransformationName(): void
    {
        if (!$this->file->isValidTransformation($this->name)) {
            $this->addInvalidAttributeError('name');
        }
    }

    public function beforeSave($insert): bool
    {
        $this->attachBehaviors([
            'TimestampBehavior' => [
                'class' => 'davidhirtz\yii2\skeleton\behaviors\TimestampBehavior',
                'attributes' => [
                    static::EVENT_BEFORE_INSERT => ['created_at'],
                ],
            ],
        ]);

        foreach (static::getModule()->transformations[$this->name] as $attribute => $value) {
            $this->$attribute = $value;
        }

        if (parent::beforeSave($insert)) {
            FileHelper::createDirectory(pathinfo($this->getFilePath(), PATHINFO_DIRNAME));
            return $this->createTransformation();
        }

        return false;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        $this->recalculateFileTransformationCount();
        parent::afterSave($insert, $changedAttributes);
    }

    public function afterDelete(): void
    {
        $this->recalculateFileTransformationCount();
        FileHelper::unlink($this->getFilePath());

        parent::afterDelete();
    }

    /**
     * Updates related file {@link File::$transformation_count}
     */
    protected function recalculateFileTransformationCount(): void
    {
        $this->file->recalculateTransformationCount()
            ->update();
    }

    public function beforeTransformation(): bool
    {
        $event = new ModelEvent();
        $this->trigger(self::EVENT_BEFORE_TRANSFORMATION, $event);

        return $event->isValid;
    }

    /**
     * Creates transformation through the installed image library.
     */
    protected function createTransformation(): bool
    {
        try {
            return $this->createTransformationInternal();
        } catch (Exception $exception) {
            Yii::error($exception);
        }

        return false;
    }

    protected function createTransformationInternal(): bool
    {
        if ($this->beforeTransformation()) {
            ini_set('memory_limit', '-1');
            set_time_limit(0);

            $filename = $this->file->folder->getUploadPath() . $this->file->getFilename();

            if (!$this->width || !$this->height || $this->keepAspectRatio) {
                $image = Image::resize($filename, $this->width, $this->height, $this->keepAspectRatio, $this->scaleUp);
            } else {
                $image = Image::fit($filename, $this->width, $this->height, $this->backgroundColor, $this->backgroundAlpha);
            }

            Image::saveImage($image, $this->getFilePath(), $this->imageOptions);

            $this->width = $image->getSize()->getWidth();
            $this->height = $image->getSize()->getHeight();
            $this->size = filesize($this->getFilePath());

            return true;
        }

        return false;
    }

    public function getFileUrl(?string $extension = null): string
    {
        if (!$extension) {
            $extension = $this->extension;
        }

        return $this->file->folder->getUploadUrl() . $this->name . '/' . $this->file->basename . '.' . $extension;
    }

    public function getFilePath(?string $extension = null): string
    {
        if (!$extension) {
            $extension = $this->extension;
        }

        return $this->getUploadPath() . $this->file->basename . '.' . $extension;
    }

    public function getUploadPath(): string
    {
        return $this->file->folder->getUploadPath() . $this->name . DIRECTORY_SEPARATOR;
    }

    public function isWebp(): bool
    {
        return strtolower($this->extension) === 'webp';
    }

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

    public function formName(): string
    {
        return 'Transformation';
    }

    public static function tableName(): string
    {
        return static::getModule()->getTableName('transformation');
    }
}