<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileReference;
use Hirtz\Skeleton\Widgets\Buttons\DeleteButton;
use Override;
use Yii;

/**
 * @extends DeleteButton<File>
 */
class FileDeleteButton extends DeleteButton
{
    #[Override]
    public function isVisible(): bool
    {
        return parent::isVisible() && $this->webuser->can(File::AUTH_FILE);
    }

    #[Override]
    protected function configure(): void
    {
        $this->label ??= Yii::t('media', 'FILE_DELETE_BUTTON');
        $this->title ??= Yii::t('media', 'FILE_DELETE_CONFIRM');
        $this->message ??= $this->getUsageMessage();

        parent::configure();
    }

    /**
     * What the delete takes with it, so a required use cleared is not discovered after the fact.
     */
    protected function getUsageMessage(): ?string
    {
        $file = $this->model;
        $sentences = [];

        if ($file->asset_count) {
            $sentences[] = Yii::t('media', 'FILE_DELETE_ASSETS_TEXT', ['count' => $file->asset_count]);
        }

        if ($file->reference_count) {
            $references = $file->references;
            FileReference::populateModelRelations($references);

            $sentences[] = Yii::t('media', 'FILE_DELETE_REFERENCES_TEXT', [
                'count' => $file->reference_count,
                'records' => count($references),
            ]);

            $required = array_sum(array_map(
                static fn (FileReference $reference): int => $reference->getRequiredUseCount(),
                $references,
            ));

            if ($required) {
                $sentences[] = Yii::t('media', 'FILE_DELETE_REQUIRED_TEXT', ['count' => $required]);
            }
        }

        return $sentences ? implode(' ', $sentences) : null;
    }
}
