<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Db\DateTime;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\SearchableInterface;
use Override;
use Yii;
use yii\base\Model;

/**
 * A use of a file by a {@see FileCustomAttribute}: one row per owner and file, however often the owner names it.
 *
 * @property int $id
 * @property int $file_id
 * @property string $model_class
 * @property int $model_id
 * @property int $use_count
 * @property array<string, int>|null $attribute_counts how often each attribute names the file, nested names joined
 * by `.`
 * @property DateTime|null $updated_at
 * @property DateTime $created_at
 *
 * @property-read File|null $file {@see FileReference::getFile()}
 */
class FileReference extends ActiveRecord
{
    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'TimestampBehavior' => TimestampBehavior::class,
        ];
    }

    /**
     * @param array<string, mixed> $changedAttributes
     */
    #[Override]
    public function afterSave($insert, $changedAttributes): void
    {
        if (!$this->getIsBatch()) {
            $this->file?->updateReferenceCount();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    #[Override]
    public function afterDelete(): void
    {
        if (!$this->getIsBatch()) {
            $this->file?->updateReferenceCount();
        }

        parent::afterDelete();
    }

    /**
     * @return ActiveQuery<File>
     */
    public function getFile(): ActiveQuery
    {
        return $this->hasOne(File::class, ['id' => 'file_id']);
    }

    public function populateFileRelation(?File $file): void
    {
        $this->populateRelation('file', $file);
    }

    /**
     * The owner, `null` when it is gone or its class is.
     */
    public function getModel(): ?ActiveRecord
    {
        if (!$this->isRelationPopulated('model')) {
            static::populateModelRelations([$this]);
        }

        $model = $this->getRelatedRecords()['model'] ?? null;

        return $model instanceof ActiveRecord ? $model : null;
    }

    public function populateModelRelation(?ActiveRecord $model): void
    {
        $this->populateRelation('model', $model);
    }

    /**
     * @param FileReference[] $references
     */
    public static function populateModelRelations(array $references, bool $searchable = false): void
    {
        $modelIds = [];

        foreach ($references as $reference) {
            if (!$reference->isRelationPopulated('model')) {
                $modelIds[$reference->model_class][$reference->model_id] = $reference->model_id;
            }
        }

        foreach ($modelIds as $modelClass => $ids) {
            $models = [];

            if (class_exists($modelClass) && is_subclass_of($modelClass, ActiveRecord::class)) {
                $instance = Yii::createObject($modelClass);
                $query = $searchable && $instance instanceof SearchableInterface ? $instance::findSearchable() : $instance::find();

                $models = $query->andWhere(['id' => array_values($ids)])
                    ->indexBy('id')
                    ->all();
            }

            foreach ($references as $reference) {
                if ($reference->model_class === $modelClass && !$reference->isRelationPopulated('model')) {
                    $reference->populateModelRelation($models[$reference->model_id] ?? null);
                }
            }
        }
    }

    /**
     * @return array<string, int> how often each attribute names the file, by the owner's labels (`Gallery › Image`)
     */
    public function getAttributeLabelCounts(): array
    {
        $model = $this->getModel();
        $labels = [];

        foreach ($this->attribute_counts ?? [] as $name => $count) {
            $path = explode('.', (string)$name);
            $label = $model ? implode(' › ', static::getPathLabels($model, $path)) : (string)$name;
            $labels[$label] = ($labels[$label] ?? 0) + (int)$count;
        }

        return $labels;
    }

    /**
     * @return int how many of the uses are of a required attribute, which the owner asks for once the file is gone
     */
    public function getRequiredUseCount(): int
    {
        $model = $this->getModel();
        $count = 0;

        if ($model === null) {
            return 0;
        }

        foreach ($this->attribute_counts ?? [] as $name => $uses) {
            [$definition, $owner] = static::findDefinition($model, explode('.', (string)$name));

            if ($definition !== null && $owner !== null && $definition->isRequired($owner)) {
                $count += (int)$uses;
            }
        }

        return $count;
    }

    /**
     * @param list<string> $path
     * @return list<string>
     */
    protected static function getPathLabels(Model $owner, array $path): array
    {
        $name = array_shift($path);

        if ($name === null) {
            return [];
        }

        $label = $owner->getAttributeLabel($name);
        $definition = $owner instanceof CustomAttributeInterface ? $owner->getCustomAttribute($name) : null;

        if (!$path) {
            return [$label];
        }

        return $definition instanceof GroupCustomAttribute
            ? [$label, ...static::getPathLabels($definition->createItem($owner, '0'), $path)]
            : [$label, ...$path];
    }

    /**
     * @param list<string> $path
     * @return array{CustomAttribute|null, Model|null} the definition and the owner it is read on
     */
    protected static function findDefinition(Model $owner, array $path): array
    {
        $name = array_shift($path);
        $definition = $name !== null && $owner instanceof CustomAttributeInterface
            ? $owner->getCustomAttribute($name)
            : null;

        if (!$path) {
            return [$definition, $owner];
        }

        return $definition instanceof GroupCustomAttribute
            ? static::findDefinition($definition->createItem($owner, '0'), $path)
            : [null, null];
    }

    #[Override]
    public static function tableName(): string
    {
        return '{{%file_reference}}';
    }
}
