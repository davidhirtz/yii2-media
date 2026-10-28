<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Actions\ReorderFolder;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Queries\FolderQuery;
use Hirtz\Media\Modules\Admin\Controllers\Traits\FolderControllerTrait;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FolderGridView;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Models\Forms\DeleteForm;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Widgets\Flashes;
use Override;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * @extends Controller<Module>
 */
class FolderController extends Controller
{
    use FolderControllerTrait;
    use ModuleTrait;

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['create', 'delete', 'index', 'reorder', 'update'],
                        'roles' => [Folder::AUTH_FOLDER],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'reorder' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(?int $type = null, ?string $q = null): Response|string
    {
        $query = $this->getQuery()
            ->orderBy(static::getModule()->defaultFolderOrder)
            ->andFilterWhere(['type' => $type])
            ->matching($q);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'sort' => false,
            'pagination' => false,
        ]);

        return $this->render('index', [
            'provider' => $provider,
        ]);
    }

    public function actionCreate(?int $type = null): Response|string
    {
        $folder = Folder::instantiateFromPost($this->request->post(), $type);
        $folder->loadDefaultValues();

        if (!$this->webuser->can(Folder::AUTH_FOLDER)) {
            throw new ForbiddenHttpException();
        }

        if ($folder->load($this->request->post()) && !$this->request->isFormReload() && $folder->insert()) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_CREATED'));
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'folder' => $folder,
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $folder = $this->findFolder($id);

        if ($folder->load($this->request->post()) && !$this->request->isFormReload()) {
            if ($folder->update()) {
                $this->success(Yii::t('media', 'FOLDER_SUCCESS_UPDATED'));
            }

            // An unchanged save ends like a changed one rather than redrawing the posted form
            if (!$folder->hasErrors()) {
                return $this->refresh();
            }
        }

        return $this->render('update', [
            'folder' => $folder,
        ]);
    }

    public function actionDelete(int $id): Response|string
    {
        $folder = $this->findFolder($id);

        // Its files and their assets go with it, which the folder permission alone does not grant
        if ($folder->file_count > 0 && !$this->webuser->can(File::AUTH_FILE)) {
            throw new ForbiddenHttpException();
        }

        $form = DeleteForm::create([
            'model' => $folder,
            'attribute' => 'name',
        ]);

        if ($form->load($this->request->post()) && $form->delete()) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_DELETED'));
        }

        $this->error($form);

        return $this->redirect(['index']);
    }

    public function actionReorder(): string
    {
        $default = FolderCollection::getDefault();
        $success = ReorderFolder::runWithBodyParam('folder');

        if ($success) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_ORDERED'));
        }

        $html = (string)Flashes::make();
        $current = FolderCollection::getDefault();

        if ($current->id !== $default->id) {
            $grid = FolderGridView::make();

            foreach ([$default, $current] as $folder) {
                $html .= $grid->getNameLink($folder)->attribute('hx-swap-oob', 'true');
            }
        }

        return $html;
    }

    protected function getQuery(): FolderQuery
    {
        return Folder::find();
    }
}
