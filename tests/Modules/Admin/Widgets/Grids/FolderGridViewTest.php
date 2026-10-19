<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FolderGridView;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Models\Types\Type;
use Override;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;

class FolderGridViewTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->controller = new Controller('test', Yii::$app);
    }

    public function testASingleTypeHasNeitherColumnNorFilter(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('?type=', $html);
        self::assertStringNotContainsString('<th><div>Type</div></th>', $html);
    }

    public function testSeveralTypesHaveAColumnAndAFilter(): void
    {
        Yii::$container->set(Folder::class, [
            'types' => fn (): array => [
                Type::make(Folder::TYPE_DEFAULT)->name('Default')->plural('Defaults'),
                Type::make(2)->name('Hero')->plural('Heroes'),
            ],
        ]);

        $html = $this->render();

        self::assertStringContainsString('?type=2', $html);
        self::assertStringContainsString('Heroes', $html);
        self::assertStringContainsString('<th><div>Type</div></th>', $html);
    }

    private function render(): string
    {
        // after the types are declared: the first read caches them per class
        FolderCollection::getDefault();

        return (string)FolderGridView::make()
            ->provider(new ActiveDataProvider([
                'query' => Folder::find(),
                'sort' => false,
                'pagination' => false,
            ]));
    }
}
