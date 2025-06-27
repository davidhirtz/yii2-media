<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models\forms;

use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\base\traits\ModelTrait;
use Yii;
use yii\base\Model;

/**
 * @property-write string $path {@see static::setPath()}
 * @property-read Transformation $transformation {@see static::getTransformation()}
 */
class TransformationForm extends Model
{
    use ModelTrait;
    use ModuleTrait;

    public ?string $basename = null;
    public ?string $extension = null;
    public ?string $filename = null;
    public ?File $file = null;
    public ?Folder $folder = null;
    public ?string $folderPath = null;
    public ?string $transformationName = null;

    private ?Transformation $_transformation = null;

    #[\Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [
                ['basename', 'folderPath', 'transformationName'],
                'required',
            ],
            [
                ['basename'],
                'string',
                'max' => File::BASENAME_MAX_LENGTH,
            ],
            [
                ['extension'],
                'in',
                'range' => [
                    ...static::getModule()->allowedExtensions,
                    ...static::getModule()->transformationExtensions,
                ],
            ],
            [
                ['transformationName'],
                'in',
                'range' => array_keys(static::getModule()->transformations),
            ],
            [
                ['folderPath'],
                'match',
                'pattern' => Folder::PATH_REGEX,
            ],
            [
                ['folderPath'],
                $this->validateFolderPath(...),
            ],
            [
                ['file'],
                $this->validateFile(...),
                'skipOnEmpty' => false,
            ],
        ];
    }

    #[\Override]
    public function beforeValidate(): bool
    {
        $this->extension ??= strtolower(pathinfo((string) $this->filename, PATHINFO_EXTENSION));
        $this->basename ??= substr((string) $this->filename, 0, -strlen($this->extension) - 1);

        return parent::beforeValidate();
    }

    public function validateFile(): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $this->file = $this->findFile();

        if (!$this->file) {
            $this->addInvalidAttributeError('file');
        }
    }

    public function validateFolderPath(): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $this->folder = FolderCollection::getByPath($this->folderPath) ?? $this->findFolder();

        if (!$this->folder) {
            $this->addInvalidAttributeError('folderPath');
        }
    }

    public function getTransformation(): Transformation
    {
        if ($this->_transformation === null) {
            $this->_transformation = Transformation::create();
            $this->_transformation->name = $this->transformationName;
            $this->_transformation->extension = $this->extension;

            $this->file->populateFolderRelation($this->folder);
            $this->_transformation->populateFileRelation($this->file);
        }

        return $this->_transformation;
    }

    protected function findFile(): ?File
    {
        $extension = !in_array($this->extension, static::getModule()->transformationExtensions)
            ? $this->extension
            : null;

        return File::find()
            ->filterWhere([
                'folder_id' => $this->folder->id,
                'basename' => $this->basename,
                'extension' => $extension,
            ])
            ->limit(1)
            ->one();
    }

    protected function findFolder(): ?Folder
    {
        return Folder::find()
            ->select(['id', 'path'])
            ->where(['path' => $this->folderPath])
            ->limit(1)
            ->one();
    }

    public function setPath(string $path): void
    {
        $parts = explode('/', $path);
        $this->folderPath = array_shift($parts);
        $this->transformationName = array_shift($parts);
        $this->filename = implode('/', $parts);
    }

    #[\Override]
    public function attributeLabels(): array
    {
        return [
            'basename' => Yii::t('media', 'Filename'),
            'extension' => Yii::t('media', 'Extension'),
            'file' => Yii::t('media', 'File'),
            'folderPath' => Yii::t('media', 'Folder'),
            'transformationName' => Yii::t('media', 'Transformation'),
        ];
    }
}
