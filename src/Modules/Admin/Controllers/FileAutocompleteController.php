<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Widgets\Forms\AutocompleteList;
use Override;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;

/**
 * Answers the files a {@see FileCustomAttribute} offers. The scope is signed for the user, since an author editing a
 * section may hold no file permission.
 *
 * @extends Controller<Module>
 */
class FileAutocompleteController extends Controller
{
    public int $limit = 20;

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
                        'actions' => ['index'],
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(string $scope, ?string $q = null): string
    {
        $userId = $this->webuser->getId();
        $definition = FileCustomAttribute::fromSignedScope($scope, is_numeric($userId) ? (int)$userId : 0);

        if ($definition === null) {
            throw new ForbiddenHttpException();
        }

        $q = trim((string)$q);

        if ($q === '') {
            return '';
        }

        $files = $definition->getFileQuery()
            ->withTranslations()
            ->matching($q)
            ->orderBy(['name' => SORT_ASC])
            ->limit($this->limit)
            ->all();

        $folders = FolderCollection::getAll();
        $hasFolders = count($definition->getFolderIds() ?? $folders) > 1;
        $options = [];

        foreach ($files as $file) {
            $options[] = [
                'text' => $file->getI18nAttribute('name'),
                'value' => $file->id,
                'description' => $hasFolders ? ($folders[$file->folder_id]->name ?? null) : null,
            ];
        }

        return (string)AutocompleteList::make()
            ->options($options);
    }
}
