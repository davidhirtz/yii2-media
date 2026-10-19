<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Models;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Skeleton\Behaviors\TrailBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Db\DateTime;
use Hirtz\Skeleton\Db\I18nActiveQuery;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Interfaces\TranslationInterface;
use Hirtz\Skeleton\Models\Traits\AdminModelTrait;
use Hirtz\Skeleton\Models\Traits\CustomAttributesTrait;
use Hirtz\Skeleton\Models\Traits\I18nAttributesTrait;
use Hirtz\Skeleton\Models\Traits\TrailModelTrait;
use Hirtz\Skeleton\Models\Traits\TranslationTrait;
use Override;
use Yii;

/**
 * A record holding {@see FileCustomAttribute}s, configured per test through the container (`customAttributes`).
 * Its table is created by whichever test uses it.
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $hero
 * @property int|null $hero_de
 * @property int|null $icon
 * @property list<array<string, mixed>>|null $gallery
 * @property DateTime|null $updated_at
 */
class FileAttributeRecord extends ActiveRecord implements
    CustomAttributeInterface,
    TrailModelInterface,
    TranslationInterface
{
    use AdminModelTrait;
    use CustomAttributesTrait;
    use I18nAttributesTrait;
    use TrailModelTrait;
    use TranslationTrait;

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'TrailBehavior' => TrailBehavior::class,
        ];
    }

    public function getAdminRoute(): array|false
    {
        return ['/admin/test/update', 'id' => $this->id];
    }

    public function getPermissionName(): string
    {
        return 'fileAttributeRecord';
    }

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [
                ['name'],
                'string',
            ],
        ];
    }

    #[Override]
    public function getTranslationModelClass(): string
    {
        return self::class;
    }

    /**
     * @return I18nActiveQuery<static>
     */
    #[Override]
    public static function find(): I18nActiveQuery
    {
        return Yii::createObject(I18nActiveQuery::class, [static::class]);
    }

    #[Override]
    public static function tableName(): string
    {
        return 'file_attribute_record';
    }
}
