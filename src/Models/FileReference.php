<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Db\DateTime;
use Hirtz\Skeleton\Models\Interfaces\ModelReferenceInterface;
use Hirtz\Skeleton\Models\Traits\ModelReferenceTrait;
use Override;
use Yii;

/**
 * One row per owner and file, however often its {@see FileCustomAttribute}s name it (nested names joined by `.`).
 *
 * @property int $id
 * @property int $file_id
 * @property int $use_count
 * @property DateTime|null $updated_at
 * @property DateTime $created_at
 *
 * @property-read File|null $file {@see FileReference::getFile()}
 */
class FileReference extends ActiveRecord implements ModelReferenceInterface
{
    use ModelReferenceTrait;

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
     * A required attribute's use, which the owner asks for once the file is gone.
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

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'model_class' => Yii::t('media', 'FILE_REFERENCE_MODEL_LABEL'),
            'attribute_counts' => Yii::t('media', 'FILE_REFERENCE_ATTRIBUTES_LABEL'),
        ];
    }

    #[Override]
    public static function tableName(): string
    {
        return '{{%file_reference}}';
    }
}
