<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Assets\ImageCropAssetBundle;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileReference;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileImportButton;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileUploadButton;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Buttons\DeleteButton;
use Hirtz\Skeleton\Widgets\Buttons\DuplicateButton;
use Hirtz\Skeleton\Widgets\Navs\ActionDropdown;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;
use Stringable;
use Yii;

class FileActionDropdown extends ActionDropdown
{
    /**
     * @use ModelTrait<File>
     */
    use ModelTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model->isTransformableImage()) {
            $this->registerClientScript();
            $this->addItem($this->getImageCropOpenButton(), $this->getImageCropCloseButton());
        }

        $this->addItem(
            $this->getDuplicateButton(),
            $this->getUploadFileButton(),
            $this->getImportFileButton(),
            $this->getFileLinkButton(),
            $this->getFileDeleteButton()
        );

        parent::configure();
    }

    protected function registerClientScript(): void
    {
        $this->view->registerAssetBundle(ImageCropAssetBundle::class);
    }

    protected function getImageCropOpenButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->icon('expand')
            ->text(Yii::t('media', 'FILE_ACTION_DROPDOWN_EDIT_DIMENSIONS'))
            ->attribute('data-id', 'image-open');
    }

    protected function getImageCropCloseButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->icon('compress')
            ->text(Yii::t('media', 'FILE_ACTION_DROPDOWN_RESET_DIMENSIONS'))
            ->attribute('data-id', 'image-cancel');
    }

    protected function getDuplicateButton(): ?Stringable
    {
        return DuplicateButton::make()
            ->label(Yii::t('media', 'FILE_BUTTON_DUPLICATE'))
            ->model($this->model);
    }

    protected function getUploadFileButton(): ?Stringable
    {
        return FileUploadButton::make()
            ->button(fn (Button $button) => $button->addClass('dropdown-item-btn'))
            ->label(Yii::t('media', 'FILE_ACTION_DROPDOWN_REPLACE_WITH_UPLOAD'))
            ->url(Url::current());
    }

    protected function getImportFileButton(): ?Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'FILE_ACTION_DROPDOWN_REPLACE_WITH_IMPORT'))
            ->url(Url::current());
    }

    protected function getFileLinkButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->text(Yii::t('media', 'FILE_ACTION_DROPDOWN_SHOW_FILE'))
            ->icon('external-link-alt')
            ->url($this->model->getUrl())
            ->target('blank');
    }

    /**
     * @see FileController::actionDelete()
     */
    protected function getFileDeleteButton(): ?Stringable
    {
        return DeleteButton::make()
            ->label(Yii::t('media', 'FILE_BUTTON_DELETE'))
            ->title(Yii::t('media', 'FILE_CONFIRM_DELETE'))
            ->message($this->getFileDeleteMessage())
            ->visible($this->webuser->can(File::AUTH_FILE))
            ->model($this->model);
    }

    /**
     * What the delete takes with it, so a required use cleared is not discovered after the fact.
     */
    protected function getFileDeleteMessage(): ?string
    {
        $file = $this->model;
        $sentences = [];

        if ($file->asset_count) {
            $sentences[] = Yii::t('media', 'FILE_CONFIRM_DELETE_ASSETS', ['count' => $file->asset_count]);
        }

        if ($file->reference_count) {
            $references = $file->references;
            FileReference::populateModelRelations($references);

            $sentences[] = Yii::t('media', 'FILE_CONFIRM_DELETE_REFERENCES', [
                'count' => $file->reference_count,
                'records' => count($references),
            ]);

            $required = array_sum(array_map(
                static fn (FileReference $reference): int => $reference->getRequiredUseCount(),
                $references,
            ));

            if ($required) {
                $sentences[] = Yii::t('media', 'FILE_CONFIRM_DELETE_REQUIRED', ['count' => $required]);
            }
        }

        return $sentences ? implode(' ', $sentences) : null;
    }
}
