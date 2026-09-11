<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Widgets\Buttons\CreateButton;
use Yii;

class FolderCreateButton extends CreateButton
{
    public function __construct(array $config = [])
    {
        $this->icon ??= 'plus';
        $this->label ??= Lang::t('media', 'FOLDER_CREATE_BUTTON');
        $this->url ??= Url::toRoute(['/admin/media/folder/create']);

        $this->visible = Yii::$app->getUser()->can(Folder::AUTH_FOLDER_CREATE);

        parent::__construct($config);
    }
}
