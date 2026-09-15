<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Widgets\Buttons\CreateButton;
use Yii;

class FolderCreateButton extends CreateButton
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->icon ??= 'plus';
        $this->label ??= Yii::t('media', 'FOLDER_CREATE_BUTTON');
        $this->url ??= Url::toRoute(['/admin/media/folder/create']);

        $this->visible = Application::current()->getUser()->can(Folder::AUTH_FOLDER);

        parent::__construct($config);
    }
}
