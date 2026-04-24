<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Assets\ImageCropAssetBundle;
use Hirtz\Media\Models\File;
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
            ->text(Yii::t('media', 'Edit dimensions'))
            ->attribute('data-id', 'image-open');
    }

    protected function getImageCropCloseButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->icon('compress')
            ->text(Yii::t('media', 'Reset dimensions'))
            ->attribute('data-id', 'image-cancel');
    }

    protected function getDuplicateButton(): ?Stringable
    {
        return DuplicateButton::make()
            ->model($this->model);
    }

    protected function getUploadFileButton(): ?Stringable
    {
        return FileUploadButton::make()
            ->button(fn (Button $button) => $button->addClass('dropdown-item-btn'))
            ->label(Yii::t('media', 'Replace with upload'))
            ->url(Url::current());
    }

    protected function getImportFileButton(): ?Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'Replace with import'))
            ->url(Url::current());
    }

    protected function getFileLinkButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->text(Yii::t('media', 'Show file'))
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
            ->label(Yii::t('media', 'Delete file'))
            ->visible($this->webuser->can(File::AUTH_FILE_DELETE, ['file' => $this->model]))
            ->model($this->model);
    }
}
