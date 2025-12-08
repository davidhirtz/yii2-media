<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\panels;

use Hirtz\Media\models\File;
use Hirtz\Media\modules\admin\widgets\buttons\FileImportButton;
use Hirtz\Media\modules\admin\widgets\buttons\FileUploadButton;
use Hirtz\Media\modules\admin\widgets\panels\traits\DuplicateButtonTrait;
use Hirtz\Media\modules\admin\widgets\panels\traits\FileLinkButtonTrait;
use Hirtz\Skeleton\widgets\panels\Panel;
use Hirtz\Skeleton\widgets\traits\ModelWidgetTrait;
use Hirtz\Skeleton\widgets\Widget;
use Override;
use Stringable;
use Yii;
use yii\helpers\Url;

/**
 * @property File $model
 */
class FilePanel extends Widget
{
    use ModelWidgetTrait;
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
