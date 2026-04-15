<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Panels;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileImportButton;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileUploadButton;
use Hirtz\Media\Modules\Admin\Widgets\Panels\Traits\DuplicateButtonTrait;
use Hirtz\Media\Modules\Admin\Widgets\Panels\Traits\FileLinkButtonTrait;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Widgets\Panels\Panel;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;
use Yii;

class FilePanel extends Widget
{
    /**
     * @use ModelTrait<File>
     */
    use ModelTrait;
    use DuplicateButtonTrait;
    use FileLinkButtonTrait;

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return Panel::make()
            ->buttons(...$this->getButtons());
    }

    protected function getButtons(): array
    {
        $buttons = [];

        if (Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->model->folder])) {
            $buttons[] = $this->getDuplicateButton();
            $buttons[] = $this->getUploadFileButton();
            $buttons[] = $this->getImportFileButton();
        }

        $buttons[] = $this->getFileLinkButton();

        return $buttons;
    }

    protected function getUploadFileButton(): ?Stringable
    {
        return FileUploadButton::make()
            ->label(Yii::t('media', 'Replace file'))
            ->url(Url::current());
    }

    protected function getImportFileButton(): ?Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'Replace file'))
            ->url(Url::current());
    }
}
