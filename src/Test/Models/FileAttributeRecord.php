<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Models;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Db\I18nActiveQuery;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\TranslationInterface;
use Hirtz\Skeleton\Models\Traits\CustomAttributesTrait;
use Hirtz\Skeleton\Models\Traits\I18nAttributesTrait;
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
 */
class FileAttributeRecord extends ActiveRecord implements CustomAttributeInterface, TranslationInterface
{
    use CustomAttributesTrait;
    use I18nAttributesTrait;
    use TranslationTrait;

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
