<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\navs;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use Yii;
use yii\helpers\Html;

class Submenu extends \davidhirtz\yii2\skeleton\widgets\fontawesome\Submenu
{
    use ModuleTrait;

    public ?File $file = null;
    private ?Module $_parentModule = null;

    #[\Override]
    public function init(): void
    {
        if (!$this->items) {
            $user = Yii::$app->getUser();

            $this->items = [
                [
                    'label' => Yii::t('media', 'Files'),
                    'url' => ['file/index'],
                    'visible' => $user->can(File::AUTH_FILE_UPDATE),
                    'active' => ['file/'],
                    'icon' => 'images',
                    'labelOptions' => [
                        'class' => 'd-none d-md-inline'
                    ],
                ],
                [
                    'label' => Yii::t('media', 'Folders'),
                    'url' => ['folder/index'],
                    'visible' => $user->can(Folder::AUTH_FOLDER_UPDATE),
                    'active' => ['folder/'],
                    'icon' => 'folder-open',
                    'labelOptions' => [
                        'class' => 'd-none d-md-inline'
                    ],
                ],
            ];
        }

        $this->title ??= Html::a($this->getParentModule()->getName(), $this->getParentModule()->url);
        $this->setBreadcrumbs();

        parent::init();
    }

    protected function setBreadcrumbs(): void
    {
        $view = $this->getView();
        $view->setBreadcrumb($this->getParentModule()->getName(), ['/admin/file/index']);

        if ($this->file) {
            $view->setBreadcrumb($this->file->folder->name, ['/admin/file/index', 'folder' => $this->file->folder_id]);
        }
    }

    protected function getParentModule(): Module
    {
        if ($this->_parentModule === null) {
            /** @var Module $module */
            $module = Yii::$app->getModule('admin')->getModule('media');
            $this->_parentModule = $module;
        }

        return $this->_parentModule;
    }
}
