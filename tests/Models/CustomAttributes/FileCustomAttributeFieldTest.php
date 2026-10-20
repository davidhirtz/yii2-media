<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\CustomAttributes;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Models\FileAttributeRecord;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\FileAttributeRecordTrait;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Widgets\Forms\ActiveForm;
use Override;
use Yii;
use yii\web\Controller;

class FileCustomAttributeFieldTest extends TestCase
{
    use FileAttributeRecordTrait;
    use MediaFixtureTrait;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            'file' => FileFixture::class,
            'user' => UserFixture::class,
        ];
    }

    private Folder $heroes;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->controller = new Controller('test', Yii::$app);

        $this->heroes = Folder::create();
        $this->heroes->loadDefaultValues();
        $this->heroes->name = 'Héroes Å';
        $this->heroes->path = 'heroes';
        self::assertTrue($this->heroes->insert());

        $this->getFileFromFixture('file-3')->updateAttributes(['folder_id' => $this->heroes->id, 'name' => 'Москва']);
    }

    #[Override]
    protected function tearDown(): void
    {
        Yii::$container->clear(FileAttributeRecord::class);
        parent::tearDown();
    }

    #[Override]
    protected function setUpSchema(): void
    {
        $this->createFileAttributeRecordTable();
    }

    #[Override]
    protected function tearDownSchema(): void
    {
        $this->dropFileAttributeRecordTable();
    }

    public function testFewFilesAreASelectGroupedByFolder(): void
    {
        $this->declareAttributes(FileCustomAttribute::make('hero'));

        $html = $this->renderField(FileAttributeRecord::create(), 'hero');

        self::assertStringContainsString('<select id="fileattributerecord-hero"', $html);
        self::assertStringContainsString('<option value=""></option>', $html);
        self::assertStringContainsString('<optgroup label="Héroes Å"><option value="' . $this->getFileFromFixture('file-3')->id . '">Москва</option></optgroup>', $html);
    }

    public function testAFileOutsideTheScopeStaysOffered(): void
    {
        $this->declareAttributes(FileCustomAttribute::make('hero')->folders($this->heroes->id));
        $outside = $this->getFileFromFixture('file-1');

        $record = $this->createFileAttributeRecord();
        $record->updateAttributes(['custom_attributes' => ['hero' => $outside->id]]);

        $html = $this->renderField($this->findFileAttributeRecord($record->id), 'hero');

        self::assertStringNotContainsString('<optgroup', $html);
        self::assertStringContainsString("<option value=\"$outside->id\" selected>$outside->name</option>", $html);
    }

    public function testManyFilesAreAnAutocompleteShowingTheSelectedName(): void
    {
        $this->declareAttributes(FileCustomAttribute::make('hero')->selectLimit(1));
        $file = $this->getFileFromFixture('file-3');

        $record = $this->createFileAttributeRecord(['hero' => $file->id]);
        $html = $this->renderField($this->findFileAttributeRecord($record->id), 'hero');

        self::assertStringNotContainsString('<select', $html);
        self::assertStringContainsString("<input type=\"hidden\" name=\"FileAttributeRecord[hero]\" value=\"$file->id\" data-autocomplete-value-input>", $html);
        self::assertStringContainsString('value="Москва"', $html);
        self::assertMatchesRegularExpression('#hx-get="/admin/media/file-autocomplete/index\?scope=[\w-]+\.[0-9a-f]{64}"#', $html);
    }

    public function testTheScopeIsCountedOnceForEveryRowOfAGroup(): void
    {
        $this->declareAttributes(GroupCustomAttribute::make('gallery')
            ->multiple()
            ->attributes([
                FileCustomAttribute::make('image'),
                TextCustomAttribute::make('caption'),
            ]));

        $id = $this->getFileFromFixture('file-3')->id;
        $record = $this->createFileAttributeRecord(['gallery' => [['image' => $id], ['image' => $id], ['image' => $id]]]);
        $record = $this->findFileAttributeRecord($record->id);

        $queries = $this->countQueries(fn () => $this->renderField($record, 'gallery'), '/COUNT\(\*\)/');

        self::assertSame(1, $queries);
    }

    /**
     * The collapsed row is titled by the name the select shows, so picking another file reads the same.
     */
    public function testAGroupRowIsTitledByTheFileName(): void
    {
        $this->declareAttributes(GroupCustomAttribute::make('gallery')
            ->multiple()
            ->attributes([
                FileCustomAttribute::make('image'),
                TextCustomAttribute::make('caption'),
            ]));

        $file = $this->getFileFromFixture('file-3');
        $record = $this->createFileAttributeRecord(['gallery' => [['image' => $file->id]]]);

        $html = $this->renderField($this->findFileAttributeRecord($record->id), 'gallery');

        self::assertMatchesRegularExpression('/data-group-title[^>]*>Москва</u', $html);
        self::assertStringNotContainsString('>' . $file->getFilename() . '<', $html);
    }

    public function testTheSelectedFileOpensInANewTabForWhoMayEditFiles(): void
    {
        $this->declareAttributes(FileCustomAttribute::make('hero'));
        $file = $this->getFileFromFixture('file-3');
        $other = $this->getFileFromFixture('file-1');
        $record = $this->findFileAttributeRecord($this->createFileAttributeRecord(['hero' => $file->id])->id);

        self::assertStringNotContainsString('data-select-link', $this->renderField($record, 'hero'));

        $this->loginWithFilePermission();
        $html = $this->renderField($this->findFileAttributeRecord($record->id), 'hero');

        self::assertStringContainsString('data-select-link', $html);
        self::assertStringContainsString("<option value=\"$other->id\" data-url=\"/admin/media/file/update?id=$other->id\">", $html);
        self::assertMatchesRegularExpression("#<a[^>]+href=\"/admin/media/file/update\?id=$file->id\"[^>]+target=\"_blank\"[^>]*data-select-link-target#", $html);
    }

    public function testTheLinkIsHiddenWithoutAFile(): void
    {
        $this->declareAttributes(FileCustomAttribute::make('hero'));
        $this->loginWithFilePermission();

        $html = $this->renderField(FileAttributeRecord::create(), 'hero');

        self::assertMatchesRegularExpression('#<a[^>]+href="\#"[^>]+hidden[^>]*data-select-link-target#', $html);
    }

    /**
     * A group of the one attribute lays its rows out as the field, so the link joins the row's own buttons, first.
     */
    public function testTheLinkPrecedesTheButtonsOfAGroupRow(): void
    {
        $this->declareAttributes(GroupCustomAttribute::make('images')
            ->multiple()
            ->attributes([FileCustomAttribute::make('image')]));

        $this->loginWithFilePermission();
        $file = $this->getFileFromFixture('file-3');
        $record = $this->createFileAttributeRecord(['images' => [['image' => $file->id]]]);

        $html = $this->renderField($this->findFileAttributeRecord($record->id), 'images');

        $link = strpos($html, 'data-select-link-target');
        $remove = strpos($html, 'data-group-remove');

        self::assertNotFalse($link);
        self::assertNotFalse($remove);
        self::assertLessThan($remove, $link);
    }

    public function testTheAutocompleteCarriesTheLinkToo(): void
    {
        $this->declareAttributes(FileCustomAttribute::make('hero')->selectLimit(1));
        $this->loginWithFilePermission();
        $file = $this->getFileFromFixture('file-3');
        $record = $this->createFileAttributeRecord(['hero' => $file->id]);

        $html = $this->renderField($this->findFileAttributeRecord($record->id), 'hero');

        self::assertStringContainsString('data-autocomplete-value-input', $html);
        self::assertMatchesRegularExpression("#<a[^>]+href=\"/admin/media/file/update\?id=$file->id\"#", $html);
    }

    public function testTwoPostedGalleryRowsAreSaved(): void
    {
        $this->declareAttributes(GroupCustomAttribute::make('gallery')
            ->multiple()
            ->attributes([
                FileCustomAttribute::make('image'),
                TextCustomAttribute::make('caption'),
            ]));

        $a = $this->getFileFromFixture('file-1')->id;
        $b = $this->getFileFromFixture('file-3')->id;

        $record = $this->createFileAttributeRecord();
        $record->load(['FileAttributeRecord' => ['gallery' => [
            '1700000000001' => ['image' => (string)$a, 'caption' => 'Å'],
            '1700000000002' => ['image' => (string)$b, 'caption' => ''],
        ]]]);

        self::assertTrue($record->save(), implode(' ', $record->getErrorSummary(true)));
        self::assertSame([['image' => $a, 'caption' => 'Å'], ['image' => $b]], $this->findFileAttributeRecord($record->id)->gallery);
    }

    private function loginWithFilePermission(): void
    {
        /** @var UserFixture $fixture */
        $fixture = $this->getFixture('user');
        $user = User::findOne($fixture->data['admin']['id']) ?? self::fail('The user fixture was not loaded.');

        $permission = Yii::$app->getAuthManager()->getPermission(File::AUTH_FILE) ?? self::fail('No file permission.');
        Yii::$app->getAuthManager()->assign($permission, $user->id);
        $this->getWebUser()->setIdentity($user);
    }

    private function declareAttributes(mixed ...$attributes): void
    {
        Yii::$container->set(FileAttributeRecord::class, ['customAttributes' => array_values($attributes)]);
    }

    private function renderField(FileAttributeRecord $record, string $name): string
    {
        $definition = $record->getCustomAttribute($name) ?? self::fail("No attribute \"$name\".");

        return (string)ActiveForm::make()
            ->model($record)
            ->rows([$definition->createField($record)]);
    }
}
