<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\panels;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\FileImportButton;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\FileUploadButton;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\DuplicateButtonTrait;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\FileLinkButtonTrait;
use davidhirtz\yii2\skeleton\widgets\panels\Panel;
use davidhirtz\yii2\skeleton\widgets\traits\ModelWidgetTrait;
use davidhirtz\yii2\skeleton\widgets\Widget;
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
