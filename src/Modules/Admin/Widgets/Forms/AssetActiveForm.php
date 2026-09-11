<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Traits\AssetFieldsTrait;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Traits\AssetFormFieldsTrait;
use Hirtz\Skeleton\Widgets\Forms\ActiveForm;
use Hirtz\Skeleton\Widgets\Forms\Traits\CustomAttributeFieldsTrait;
use Override;

/**
 * @property Asset $model
 */
class AssetActiveForm extends ActiveForm
{
    use AssetFieldsTrait;
    use AssetFormFieldsTrait;
    use CustomAttributeFieldsTrait;

    #[Override]
    protected function configure(): void
    {
        $this->rows ??= [
            [
                $this->getPreview(),
            ],
            [
                $this->getStatusField(),
                $this->getTypeField(),
                ...$this->getCustomAttributeFields(),
            ],
        ];

        parent::configure();
    }
}
