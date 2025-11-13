<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\panels;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\FileImportButton;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\FileUploadButton;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\DuplicateButtonTrait;
use davidhirtz\yii2\media\modules\admin\widgets\panels\traits\FileLinkButtonTrait;
use davidhirtz\yii2\skeleton\widgets\panels\HelpPanel;
use Override;
use Stringable;
use Yii;
use yii\helpers\Url;

class FileHelpPanel extends HelpPanel
{
    use DuplicateButtonTrait;
    use FileLinkButtonTrait;

    public ?File $model = null;

    #[Override]
    public function init(): void
    {
        $this->content ??= $this->renderButtonToolbar($this->getButtons());
        parent::init();
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
        return new FileUploadButton(
            Yii::t('media', 'Replace file'),
            Url::current(),
        );
    }

    protected function getImportFileButton(): ?Stringable
    {
        return new FileImportButton(
            Yii::t('media', 'Replace file'),
            Url::current(),
        );
    }
}
