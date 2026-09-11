<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\CustomAttributes;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Hirtz\Skeleton\Widgets\Forms\Fields\InputField;
use Override;
use yii\base\Model;

class AltTextCustomAttribute extends TextCustomAttribute
{
    /**
     * The placeholder is read in `prepare()`: the per-language clone gets its language after the field was created.
     */
    #[Override]
    protected function configureField(Field $field, Model $owner): Field
    {
        $field = parent::configureField($field, $owner);

        if ($field instanceof InputField && $owner instanceof Asset) {
            $field->prepare(fn (InputField $field) => $field->placeholder(
                $owner->file?->getI18nAttribute('alt_text', $field->language)
            ));
        }

        return $field;
    }
}
