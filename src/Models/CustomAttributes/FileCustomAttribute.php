<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\CustomAttributes;

use Hirtz\Media\Models\Actions\LoadCustomAttributeFiles;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Helpers\SecretKey;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttributeGroupItem;
use Hirtz\Skeleton\Models\CustomAttributes\RecordCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\I18nAttributeInterface;
use Hirtz\Skeleton\Web\User as WebUser;
use Hirtz\Skeleton\Widgets\Forms\Fields\AutocompleteField;
use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Hirtz\Skeleton\Widgets\Forms\Fields\SelectField;
use JsonException;
use Override;
use Yii;
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

    /**
     * @var array<string, array{count: int, items?: array<int|string, mixed>}> per scope, since a field is created per
     * group row and for the row template
     */
    private static array $scopes = [];

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
    /**
     * Every column, as a field shows the name; the preload selects the site attributes alone.
     */
    #[Override]
    public function findRecords(Model $owner, array $ids): array
    {
        return LoadCustomAttributeFiles::findFiles($ids, false);
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

    /**
     * A select when the scope holds few files, an autocomplete when it holds many. A stored file outside the scope
     * stays offered, so a save does not drop it.
     */
    #[Override]
    public function createField(Model $owner): Field
    {
        $field = $this->getScopeCount() <= $this->getSelectLimit()
            ? $this->createSelectField($owner)
            : $this->createAutocompleteField();

        return $this->configureField($field, $owner);
    }

    protected function createSelectField(Model $owner): SelectField
    {
        $field = SelectField::make()
            ->items($this->getSelectItems())
            ->prepare(function (SelectField $field): void {
                $file = $this->getFieldFile($field);

                if ($file && !$this->hasSelectItem($file->id)) {
                    $field->addItem($file->id, $file->getI18nAttribute('name'));
                }
            });

        if (!$this->isRequired($owner)) {
            $field->prompt('');
        }

        return $field;
    }

    protected function createAutocompleteField(): AutocompleteField
    {
        $userId = WebUser::current()?->getId();

        return AutocompleteField::make()
            ->hidden()
            ->url(['/admin/media/file-autocomplete/index', 'scope' => $this->getSignedScope(is_numeric($userId) ? (int)$userId : 0)])
            ->placeholder($this->placeholder ?? Yii::t('media', 'FILE_AUTOCOMPLETE_PLACEHOLDER'))
            ->prepare(fn (AutocompleteField $field) => $field->text($this->getFieldFile($field)?->getI18nAttribute('name')));
    }

    /**
     * Read once the field knows its property, which a translated attribute's clone is given after creation.
     */
    protected function getFieldFile(Field $field): ?File
    {
        $model = $field->getModel();
        $property = $field->getProperty();

        if (!$model instanceof CustomAttributeInterface || $property === null) {
            return null;
        }

        $file = $model->getCustomAttributeRecord($property);

        return $file instanceof File ? $file : null;
    }

    public function getScopeCount(): int
    {
        $key = $this->getScopeKey();
        self::$scopes[$key] ??= ['count' => (int)$this->getFileQuery()->count()];

        return self::$scopes[$key]['count'];
    }

    /**
     * Grouped by folder when the files come from more than one.
     *
     * @return array<int|string, mixed>
     */
    public function getSelectItems(): array
    {
        $key = $this->getScopeKey();
        $scope = self::$scopes[$key] ?? ['count' => $this->getScopeCount()];

        if (!isset($scope['items'])) {
            $files = $this->getFileQuery()
                ->withTranslations()
                ->orderBy(['name' => SORT_ASC])
                ->all();

            $groups = [];

            foreach ($files as $file) {
                $groups[$file->folder_id][$file->id] = $file->getI18nAttribute('name');
            }

            if (count($groups) > 1) {
                $folders = FolderCollection::getAll();
                $scope['items'] = [];

                foreach ($folders as $folder) {
                    if (isset($groups[$folder->id])) {
                        $scope['items']["folder-$folder->id"] = ['label' => $folder->name, 'items' => $groups[$folder->id]];
                    }
                }
            } else {
                $scope['items'] = $groups ? reset($groups) : [];
            }

            self::$scopes[$key] = $scope;
        }

        return $scope['items'];
    }

    private function hasSelectItem(int $id): bool
    {
        foreach ($this->getSelectItems() as $value => $item) {
            if ($value === $id || (is_array($item) && is_array($item['items'] ?? null) && array_key_exists($id, $item['items']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Signed for one user, so the endpoint answers within this scope only, for an author who holds no file permission.
     */
    public function getSignedScope(int $userId): string
    {
        $data = json_encode([
            'folders' => $this->folders,
            'type' => $this->folderType,
            'extensions' => $this->extensions,
            'user' => $userId,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=') . '.' . hash_hmac('sha256', $data, SecretKey::get());
    }

    /**
     * @return static|null a definition holding the signed scope, `null` for a tampered scope or another user's
     */
    public static function fromSignedScope(string $scope, int $userId): ?static
    {
        [$encoded, $signature] = array_pad(explode('.', $scope, 2), 2, '');
        $data = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($data === false || !hash_equals(hash_hmac('sha256', $data, SecretKey::get()), $signature)) {
            return null;
        }

        try {
            $values = json_decode($data, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($values) || ($values['user'] ?? null) !== $userId) {
            return null;
        }

        $type = $values['type'] ?? null;

        return static::make('file')
            ->folders(...array_map(intval(...), (array)($values['folders'] ?? [])))
            ->folderType(is_int($type) ? $type : null)
            ->extensions(...array_map(strval(...), (array)($values['extensions'] ?? [])));
    }

    /**
     * The static outlives the application; `Bootstrap` resets it.
     */
    public static function reset(): void
    {
        self::$scopes = [];
    }

    private function getScopeKey(): string
    {
        return json_encode([$this->getFolderIds(), $this->extensions], JSON_THROW_ON_ERROR);
    }
}
