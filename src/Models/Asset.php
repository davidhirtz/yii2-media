<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\datetime\DateTimeBehavior;
use Hirtz\Media\Models\CustomAttributes\AltTextCustomAttribute;
use Hirtz\Media\Models\CustomAttributes\EmbedUrlCustomAttribute;
use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Queries\AssetQuery;
use Hirtz\Media\Models\Traits\FileRelationTrait;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Behaviors\BlameableBehavior;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use Hirtz\Skeleton\Behaviors\TrailBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\HtmlCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\UrlCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\DraftStatusAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\I18nAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Traits\CustomAttributesTrait;
use Hirtz\Skeleton\Models\Traits\DraftStatusAttributeTrait;
use Hirtz\Skeleton\Models\Traits\I18nAttributesTrait;
use Hirtz\Skeleton\Models\Traits\TrailModelTrait;
use Hirtz\Skeleton\Models\Traits\TypeAttributeTrait;
use Hirtz\Skeleton\Models\Traits\UpdatedByUserTrait;
use Hirtz\Skeleton\Models\Traits\VisibleAttributeTrait;
use Hirtz\Skeleton\Validators\DynamicRangeValidator;
use Hirtz\Skeleton\Validators\RelationValidator;
use Override;
use ReflectionClass;
use Yii;
use yii\base\NotSupportedException;

/**
 * One row per file attached to a model. Every registered subclass shares this table and is dispatched on
 * `model_class`, so `updateAll()` and `deleteAll()` are unscoped and must name it themselves.
 *
 * @property int $id
 * @property int $status
 * @property int $type
 * @property string $model_class
 * @property int $model_id
 * @property int $file_id
 * @property int $position
 * @property int|null $updated_by_user_id
 * @property DateTime|null $updated_at
 * @property DateTime $created_at
 *
 * @property string|null $name
 * @property string|null $content
 * @property string|null $alt_text
 * @property string|null $link
 * @property string|null $embed_url
 *
 * @mixin TrailBehavior
 */
class Asset extends ActiveRecord implements
    AssetInterface,
    CustomAttributeInterface,
    DraftStatusAttributeInterface,
    I18nAttributeInterface,
    TrailModelInterface
{
    use CustomAttributesTrait {
        getCustomAttributes as getOwnCustomAttributes;
    }
    use DraftStatusAttributeTrait;
    use FileRelationTrait;
    use I18nAttributesTrait;
    use ModuleTrait;
    use TrailModelTrait;
    use TypeAttributeTrait;
    use UpdatedByUserTrait;
    use VisibleAttributeTrait;

    /**
     * @var list<string> the names of the default definitions that are stored per language
     */
    public array $translatableAttributes = [];

    public ?bool $shouldUpdateModelAfterInsert = null;

    /**
     * @return class-string<AssetModelInterface>
     */
    public static function getModelClass(): string
    {
        throw new NotSupportedException(static::class . ' must implement "getModelClass()".');
    }

    public static function getAdminControllerRoute(): string
    {
        return '/admin/media/asset';
    }

    /**
     * @param 'create'|'delete'|'order'|'update' $action
     */
    public function getPermissionName(string $action): string
    {
        throw new NotSupportedException(static::class . ' must implement "getPermissionName()".');
    }

    #[Override]
    public static function instantiate($row): static
    {
        /** @var class-string<static> $class */
        $class = static::getModule()->getAssetClass($row['model_class'] ?? null) ?? static::class;

        /** @var static */
        return $class::create();
    }

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'DateTimeBehavior' => DateTimeBehavior::class,
            'TrailBehavior' => TrailBehavior::class,
        ];
    }

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [
                ['status', 'type'],
                DynamicRangeValidator::class,
                'skipOnEmpty' => false,
            ],
            [
                ['model_class', 'model_id', 'file_id'],
                'required',
            ],
            [
                ['model_class'],
                'in',
                'range' => array_map(
                    fn (string $class): string => $class::getModelClass(),
                    static::getModule()->getAssetClasses()
                ),
            ],
            [
                ['model_id'],
                'filter',
                'filter' => 'intval',
            ],
            [
                ['file_id'],
                RelationValidator::class,
                'required' => true,
            ],
        ];
    }

    #[Override]
    public function beforeValidate(): bool
    {
        $this->status ??= static::STATUS_DEFAULT;
        $this->type ??= static::TYPE_DEFAULT;

        if (static::class !== self::class) {
            $this->model_class ??= static::getModelClass();
        }

        return parent::beforeValidate();
    }

    #[Override]
    public function afterValidate(): void
    {
        if (!$this->getIsNewRecord()) {
            foreach (['model_class', 'model_id'] as $attribute) {
                if ($this->isAttributeChanged($attribute)) {
                    $this->addInvalidAttributeError($attribute);
                }
            }
        }

        parent::afterValidate();
    }

    #[Override]
    public function beforeSave($insert): bool
    {
        $this->attachBehaviors([
            'BlameableBehavior' => BlameableBehavior::class,
            'TimestampBehavior' => TimestampBehavior::class,
        ]);

        $this->shouldUpdateModelAfterInsert ??= !$this->getIsBatch();
        $this->setDefaultPosition();

        return parent::beforeSave($insert);
    }

    #[Override]
    public function afterSave($insert, $changedAttributes): void
    {
        if ($insert) {
            if ($this->shouldUpdateModelAfterInsert) {
                $this->model->recalculateAssetCount()->update();
            }
        } elseif ($changedAttributes) {
            $this->model->updated_at = $this->updated_at;
            $this->model->update();
        }

        if (array_key_exists('file_id', $changedAttributes)) {
            $file = File::findOne($changedAttributes['file_id']);
            $file?->recalculateAssetCount()->update();

            $this->updateFileAssetCount();
        }

        static::getModule()->invalidatePageCache();

        parent::afterSave($insert, $changedAttributes);
    }

    #[Override]
    public function afterDelete(): void
    {
        if (!$this->model->isDeleted()) {
            $this->model->recalculateAssetCount()->update();
        }

        if (!$this->file->isDeleted()) {
            $this->updateFileAssetCount();
        }

        static::getModule()->invalidatePageCache();

        parent::afterDelete();
    }

    /**
     * The base is unscoped and returns mixed subclasses from one query; every subclass is scoped to its own
     * `model_class`, which is what makes `findOne()`, the relations and the eager load safe.
     *
     * @return AssetQuery<static>
     */
    #[Override]
    public static function find(): AssetQuery
    {
        /** @var AssetQuery<static> $query */
        $query = Yii::createObject(AssetQuery::class, [static::class]);

        return static::class === self::class ? $query : $query->whereModelClass(static::getModelClass());
    }

    /**
     * @return AssetQuery<static>
     */
    public function findSiblings(): AssetQuery
    {
        return static::find()->andWhere([
            'model_class' => $this->model_class,
            'model_id' => $this->model_id,
        ]);
    }

    protected function setDefaultPosition(): void
    {
        $this->position = $this->position ?: ($this->getMaxPosition() + 1);
    }

    public function getMaxPosition(): int
    {
        return (int)$this->findSiblings()->max('[[position]]');
    }

    public function updateFileAssetCount(): bool|int
    {
        return $this->file->recalculateAssetCount()->update();
    }

    public function getModel(): AssetModelInterface
    {
        if (!$this->isRelationPopulated('model')) {
            $this->populateRelation('model', Yii::createObject($this->model_class)::findOne($this->model_id));
        }

        /** @var AssetModelInterface */
        return $this->getRelatedRecords()['model'];
    }

    public function populateModelRelation(AssetModelInterface $model): void
    {
        $this->populateRelation('model', $model);
        $this->model_class = $model->getAssetClass()::getModelClass();
        $this->model_id = $model->id;
    }

    /**
     * @param Asset[] $assets
     */
    public static function populateModelRelations(array $assets): void
    {
        $modelIds = [];

        foreach ($assets as $asset) {
            if (!$asset->isRelationPopulated('model')) {
                $modelIds[$asset->model_class][$asset->model_id] = $asset->model_id;
            }
        }

        foreach ($modelIds as $modelClass => $ids) {
            $models = Yii::createObject($modelClass)::find()
                ->andWhere(['id' => array_values($ids)])
                ->indexBy('id')
                ->all();

            foreach ($assets as $asset) {
                if ($asset->model_class === $modelClass && isset($models[$asset->model_id])) {
                    $asset->populateRelation('model', $models[$asset->model_id]);
                }
            }
        }
    }

    /**
     * How the record an asset points at is named in the admin, whatever kind of model it is.
     */
    public static function getModelName(AssetModelInterface $model): string
    {
        return $model instanceof TrailModelInterface
            ? $model->getTrailModelName()
            : (new ReflectionClass($model))->getShortName() . ' ' . $model->id;
    }

    /**
     * @param class-string $class
     */
    public function isModel(string $class): bool
    {
        return is_a($this->model_class, $class, true);
    }

    /**
     * @return list<CustomAttribute>
     */
    #[Override]
    public function getCustomAttributes(): array
    {
        return [
            ...$this->getDefaultCustomAttributes(),
            ...$this->getOwnCustomAttributes(),
        ];
    }

    /**
     * Resolved for every loaded record, with neither `file` nor `model` populated, so nothing here may read them
     * unguarded.
     *
     * @return list<CustomAttribute>
     */
    protected function getDefaultCustomAttributes(): array
    {
        return [
            TextCustomAttribute::make('name')
                ->label(Lang::t('media', 'MODEL_NAME_LABEL'))
                ->translatable($this->isTranslatableAttribute('name')),
            HtmlCustomAttribute::make('content')
                ->label(Lang::t('media', 'ASSET_CONTENT_LABEL'))
                ->translatable($this->isTranslatableAttribute('content')),
            AltTextCustomAttribute::make('alt_text')
                ->label(Lang::t('media', 'ASSET_ALT_TEXT_LABEL'))
                ->translatable($this->isTranslatableAttribute('alt_text'))
                ->visible(fn (self $asset): bool => !$asset->isRelationPopulated('file') || $asset->file->hasPreview()),
            UrlCustomAttribute::make('link')
                ->label(Lang::t('media', 'ASSET_LINK_LABEL'))
                ->translatable($this->isTranslatableAttribute('link')),
            EmbedUrlCustomAttribute::make('embed_url')
                ->label(Lang::t('media', 'ASSET_EMBED_URL_LABEL'))
                ->translatable($this->isTranslatableAttribute('embed_url')),
        ];
    }

    protected function isTranslatableAttribute(string $name): bool
    {
        return in_array($name, $this->translatableAttributes, true);
    }

    public function getFormattedEmbedUrl(?string $language = null): string
    {
        if (!$link = $this->getI18nAttribute('embed_url', $language)) {
            return '';
        }

        $link .= (str_contains((string)$link, '?') ? '&' : '?') . 'autoplay=1';

        if (str_contains($link, 'youtube')) {
            $link .= '&disablekb=1&modestbranding=1&rel=0';
        }

        if (str_contains($link, 'vimeo')) {
            $link .= '&dnt=1';
        }

        return $link;
    }

    public function getAltText(): string
    {
        $text = $this->getI18nAttribute('alt_text');

        // The file can be gone when this is called from the trail behavior.
        return ($text ?: $this->file?->getI18nAttribute('alt_text')) ?: '';
    }

    /**
     * @param list<string>|string|null $transformations
     * @return array<string, string>
     */
    public function getSrcset(array|string|null $transformations = null, ?string $extension = null): array
    {
        return $this->file->getSrcset($transformations ?? $this->getTransformationNames(), $extension);
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/images.html#sizes-attributes
     */
    public function getSizes(): ?string
    {
        return $this->model->getAssetSizes();
    }

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array
    {
        return $this->model->getAssetTransformationNames() ?: $this->file->getTransformationNames();
    }

    public function getSitemapUrl(?string $language = null): array|false
    {
        if (!$this->includeInSitemap($language)) {
            return false;
        }

        $content = $this->getI18nAttribute('content', $language);

        if ($this->getCustomAttribute('content') instanceof HtmlCustomAttribute) {
            $content = strip_tags((string)$content);
        }

        return array_filter([
            'loc' => $this->file->getUrl(),
            'title' => $this->getAltText(),
            'caption' => $content,
        ]);
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function includeInSitemap(?string $language = null): bool
    {
        return $this->isEnabled() && $this->file->hasPreview();
    }

    public function getAdminRoute(): array|false
    {
        return $this->id ? [static::getAdminControllerRoute() . '/update', 'id' => $this->id] : false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getAdminIndexRoute(AssetModelInterface $model): array
    {
        return [
            static::getAdminControllerRoute() . '/index',
            $model->getParamName() => $model->id,
        ];
    }

    public function getRoute(): array|false
    {
        return false;
    }

    public function getTrailModelAdminRoute(): array|false
    {
        return $this->getAdminRoute();
    }

    public function getTrailModelName(): string
    {
        if ($this->id) {
            return Lang::t('skeleton', 'COMMON_MODEL_ID', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    public function getTrailModelType(): string
    {
        return Lang::t('media', 'ASSET_ASSET');
    }

    public function getTrailParents(): array
    {
        $model = $this->model;

        return [
            $model,
            ...$model instanceof TrailModelInterface ? (array)$model->getTrailParents() : [],
            $this->file,
        ];
    }

    public function getTrailAttributes(): array
    {
        return array_diff($this->attributes(), [
            $this->getCustomAttributesColumn(),
            'model_class',
            'model_id',
            'position',
            'updated_by_user_id',
            'updated_at',
            'created_at',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getViewportTypes(): array
    {
        return [
            static::TYPE_DEFAULT => [
                'name' => Lang::t('media', 'ASSET_ALL_DEVICES'),
            ],
            static::TYPE_VIEWPORT_MOBILE => [
                'name' => Lang::t('media', 'ASSET_MOBILE'),
            ],
            static::TYPE_VIEWPORT_DESKTOP => [
                'name' => Lang::t('media', 'ASSET_DESKTOP'),
            ],
        ];
    }

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'model_class' => Lang::t('media', 'ASSET_MODEL_CLASS_LABEL'),
            'model_id' => Lang::t('media', 'ASSET_MODEL_ID_LABEL'),
            'file_id' => Lang::t('media', 'ASSET_FILE_ID_LABEL'),
        ];
    }

    /**
     * Uniform across the subclasses, so the POST parameter and the sort script do not have to know which one they got.
     */
    #[Override]
    public function formName(): string
    {
        return 'Asset';
    }

    #[Override]
    public static function tableName(): string
    {
        return '{{%asset}}';
    }
}
