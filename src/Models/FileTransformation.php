<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use davidhirtz\yii2\datetime\DateTime;
use Exception;
use Hirtz\Media\Models\Traits\FileRelationTrait;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;

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
class FileTransformation extends ActiveRecord
{
    use ModuleTrait;
    use FileRelationTrait;

    /**
     * Event that is triggered before creating the transformation. Set {@see ModelEvent::isValid} to `false` to alter
     * the transformation method.
     */
    public const string EVENT_BEFORE_TRANSFORMATION = 'beforeTransformation';

    /**
     * Rules are only needed for file id and name, as the attributes will be set by the model's
     * beforeSave method.
     */
    #[Override]
    public function rules(): array
    {
        return [
            [
                ['file_id'],
                $this->validateFile(...),
            ],
            [
                ['extension'],
                function (): void {
                    if (!$this->extension) {
                        $this->extension = $this->file->extension ?? null;
                    }
                },
                // the rule exists to fill an empty value, which is exactly what a validator skips by default
                'skipOnEmpty' => false,
            ],
            [
                ['name'],
                $this->validateTransformationName(...),
            ],
            [
                ['name'],
                'unique',
                'targetAttribute' => ['file_id', 'name', 'extension'],
            ],
        ];
    }

    public function validateFile(): void
    {
        if (!$this->file || !$this->file->isTransformableImage()) {
            $this->addInvalidAttributeError('file_id');
        }
    }

    public function validateTransformationName(): void
    {
        if (!$this->file->isValidTransformation($this->name)) {
            $this->addInvalidAttributeError('name');
        }
    }

    #[Override]
    public function beforeSave($insert): bool
    {
        $this->attachBehaviors([
            'TimestampBehavior' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    static::EVENT_BEFORE_INSERT => ['created_at'],
                ],
            ],
        ]);

        if (parent::beforeSave($insert)) {
            FileHelper::createDirectory(pathinfo($this->getFilePath(), PATHINFO_DIRNAME));
            return $this->createTransformation();
        }

        return false;
    }

    /**
     * @param array<string, mixed> $changedAttributes
     */
    #[Override]
    public function afterSave($insert, $changedAttributes): void
    {
        $this->updateFileTransformationCount();
        parent::afterSave($insert, $changedAttributes);
    }

    #[Override]
    public function afterDelete(): void
    {
        $this->updateFileTransformationCount();
        FileHelper::unlink($this->getFilePath());

        parent::afterDelete();
    }

    /**
     * Updates related file {@see File::$transformation_count}
     */
    protected function updateFileTransformationCount(): void
    {
        $this->file->updateTransformationCount();
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
            Yii::error($exception->getMessage());
        }

        return false;
    }

    protected function createTransformationInternal(): bool
    {
        if ($this->beforeTransformation()) {
            ini_set('memory_limit', '-1');
            set_time_limit(0);

            $transformation = $this->getTransformation();
            $processor = static::getModule()->getImageProcessor();

            $filename = $this->file->folder->getUploadPath() . $this->file->getFilename();

            $image = $processor->transform($filename, $transformation);
            $processor->write($image, $this->getFilePath(), $transformation->getImageOptions());

            $this->width = $image->width();
            $this->height = $image->height();
            $this->size = filesize($this->getFilePath()) ?: 0;

            return true;
        }

        return false;
    }

    public function getTransformation(): Transformation
    {
        $transformation = static::getModule()->getTransformation($this->name);

        if (!$transformation) {
            throw new InvalidConfigException("Transformation \"$this->name\" does not exist.");
        }

        return $transformation;
    }

    public function getDisplayName(): string
    {
        return $this->name . ($this->isExtensionTransformation() ? strtolower(" ($this->extension)") : '');
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

    public function isExtensionTransformation(): bool
    {
        return in_array(strtolower($this->extension), self::getModule()->transformationExtensions);
    }

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'name' => Yii::t('media', 'TRANSFORMATION_NAME_LABEL'),
            'file_id' => Yii::t('media', 'TRANSFORMATION_FILE_ID_LABEL'),
            'dimensions' => Yii::t('media', 'TRANSFORMATION_DIMENSIONS_LABEL'),
            'size' => Yii::t('media', 'TRANSFORMATION_SIZE_LABEL'),
            'created_at' => Yii::t('media', 'TRANSFORMATION_CREATED_AT_LABEL'),
        ];
    }

    #[Override]
    public function formName(): string
    {
        return 'Transformation';
    }

    #[Override]
    public static function tableName(): string
    {
        return '{{%file_transformation}}';
    }
}
