<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\CustomAttributes;

use Hirtz\Media\Models\Actions\LoadCustomAttributeFiles;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttributeGroupItem;
use Hirtz\Skeleton\Models\CustomAttributes\RecordCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\I18nAttributeInterface;
use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Hirtz\Skeleton\Widgets\Forms\Fields\SelectField;
use Override;
use yii\base\InvalidConfigException;
use yii\base\Model;

/**
 * Holds the id of a file of its scope. A file never owns one: a file's own files are upload attributes.
 */
class FileCustomAttribute extends RecordCustomAttribute
{
    use ModuleTrait;

    /**
     * @var list<int>
     */
    protected array $folders = [];

    protected ?int $folderType = null;

    /**
     * @var list<string>
     */
    protected array $extensions = [];

    protected ?int $selectLimit = null;

    public function folders(int ...$ids): static
    {
        $this->folders = array_values($ids);
        return $this;
    }

    public function folderType(?int $type): static
    {
        $this->folderType = $type;
        return $this;
    }

    public function extensions(string ...$extensions): static
    {
        $this->extensions = array_values(array_map(strtolower(...), $extensions));
        return $this;
    }

    public function selectLimit(?int $limit): static
    {
        $this->selectLimit = $limit;
        return $this;
    }

    /**
     * @return list<int>|null the folders of the scope, `null` for every folder
     */
    public function getFolderIds(): ?array
    {
        if ($this->folderType === null) {
            return $this->folders ?: null;
        }

        return array_values(array_unique([
            ...$this->folders,
            ...array_keys(FolderCollection::getByType($this->folderType)),
        ]));
    }

    /**
     * @return list<string>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function getSelectLimit(): int
    {
        return $this->selectLimit ?? static::getModule()->fileSelectLimit;
    }

    public function getFileQuery(): FileQuery
    {
        return $this->applyScope(File::find());
    }

    public function applyScope(FileQuery $query): FileQuery
    {
        $tableName = File::tableName();
        $folderIds = $this->getFolderIds();

        if ($folderIds !== null) {
            $query->andWhere(["$tableName.[[folder_id]]" => $folderIds]);
        }

        if ($this->extensions) {
            $query->andWhere(["$tableName.[[extension]]" => $this->extensions]);
        }

        return $query;
    }

    #[Override]
    public function getAttributeNames(Model $owner): array
    {
        $record = $owner;

        while ($record instanceof CustomAttributeGroupItem) {
            $record = $record->getOwner();
        }

        if ($record instanceof File) {
            throw new InvalidConfigException("A file cannot own the file custom attribute \"$this->name\".");
        }

        return parent::getAttributeNames($owner);
    }

    /**
     * @return array<int, File>
     */
    #[Override]
    public function findRecords(Model $owner, array $ids): array
    {
        return LoadCustomAttributeFiles::findFiles($ids);
    }

    /**
     * Read in the current language, falling back to the source language when empty.
     *
     * @phpstan-impure
     */
    public static function getFile(Model $owner, string $name, ?string $language = null): ?File
    {
        if (!$owner instanceof CustomAttributeInterface) {
            return null;
        }

        if ($owner instanceof I18nAttributeInterface) {
            $name = $owner->getI18nAttributeName($name, $language, true);
        }

        $file = $owner->getCustomAttributeRecord($name);

        return $file instanceof File ? $file : null;
    }

    #[Override]
    protected function getRecordLabel(ActiveRecord $record): string
    {
        return $record instanceof File ? $record->getFilename() : (string)$record->getPrimaryKey();
    }

    /**
     * Only a changed value has to be within the scope, so a file moved out of it since blocks no unrelated save.
     */
    #[Override]
    protected function getValidationRules(Model $owner): array
    {
        return [
            [
                'exist',
                'targetClass' => File::class,
                'targetAttribute' => 'id',
                'filter' => fn (FileQuery $query) => $this->applyScope($query),
                'when' => fn (Model $model, string $attribute): bool => !$this->isBaselineValue(
                    $model,
                    $attribute,
                    $model->$attribute,
                ),
            ],
        ];
    }

    #[Override]
    public function createField(Model $owner): Field
    {
        $items = [];

        foreach ($this->getFileQuery()->orderBy(['name' => SORT_ASC])->all() as $file) {
            $items[$file->id] = $file->getI18nAttribute('name');
        }

        $field = SelectField::make()
            ->items($items);

        if (!$this->isRequired($owner)) {
            $field->prompt('');
        }

        return $this->configureField($field, $owner);
    }
}
