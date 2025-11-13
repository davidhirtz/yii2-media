<?php

declare(strict_types=1);

/**
 * @see FolderController::actionCreate()
 *
 * @var View $this
 * @var Folder $folder
 */

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\controllers\FolderController;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FolderActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\navs\Submenu;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use davidhirtz\yii2\skeleton\widgets\forms\ErrorSummary;

$this->setTitle(Yii::t('media', 'Create New Folder'));
?>

<?= Submenu::widget(); ?>

<?= ErrorSummary::forModel($folder); ?>

<?= Panel::widget([
    'title' => $this->title,
    'content' => FolderActiveForm::widget([
        'model' => $folder,
    ]),
]); ?>
