<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Test\Traits\FunctionalTestTrait;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Override;

/**
 * The endpoint asks for no file permission: the signed scope is the authorization, for the user it was signed for.
 */
class FileAutocompleteControllerTest extends TestCase
{
    use FunctionalTestTrait;
    use UserFixtureTrait;

    private Folder $heroes;

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

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->heroes = Folder::create();
        $this->heroes->loadDefaultValues();
        $this->heroes->name = 'Héroes';
        $this->heroes->path = 'heroes';
        self::assertTrue($this->heroes->insert());

        $file = File::findOne(3) ?? self::fail('The file fixture was not loaded.');
        $file->updateAttributes(['folder_id' => $this->heroes->id, 'name' => 'Test Москва']);
    }

    public function testAGuestIsSentToTheLogin(): void
    {
        $this->open('/admin/media/file-autocomplete/index', ['scope' => $this->getScope(1), 'q' => 'Test']);

        self::assertCurrentUrlEquals('https://www.test.localhost/admin/account/login');
    }

    public function testTheFilesWithinTheScopeAreAnsweredWithTheirFolder(): void
    {
        $user = $this->login();

        $this->open('/admin/media/file-autocomplete/index', ['scope' => $this->getScope($user->id, all: true), 'q' => 'Test']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-autocomplete-value="3"]');
        self::assertSelectorExists('[data-autocomplete-value="1"]');
        self::assertSelectorTextContains('[data-autocomplete-value="3"]', 'Héroes');

        $this->open('/admin/media/file-autocomplete/index', ['scope' => $this->getScope($user->id), 'q' => 'Test']);

        self::assertSelectorExists('[data-autocomplete-value="3"]');
        self::assertSelectorNotExists('[data-autocomplete-value="1"]');
        self::assertSelectorTextContains('[data-autocomplete-value="3"]', 'Test Москва');
    }

    public function testAnEmptyQueryAnswersNothing(): void
    {
        $user = $this->login();

        $this->open('/admin/media/file-autocomplete/index', ['scope' => $this->getScope($user->id), 'q' => '']);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-autocomplete-value]');
    }

    public function testATamperedScopeIsRefused(): void
    {
        $user = $this->login();
        [$data, $signature] = explode('.', $this->getScope($user->id));
        $tampered = rtrim(strtr(base64_encode(str_replace('"folders":[' . $this->heroes->id . ']', '"folders":[]', (string)base64_decode(strtr($data, '-_', '+/')))), '+/', '-_'), '=');

        $this->open('/admin/media/file-autocomplete/index', ['scope' => "$tampered.$signature", 'q' => 'Test']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnotherUsersScopeIsRefused(): void
    {
        $this->login();
        $owner = $this->getUserFromFixture('owner');

        $this->open('/admin/media/file-autocomplete/index', ['scope' => $this->getScope($owner->id), 'q' => 'Test']);

        self::assertResponseStatusCodeSame(403);
    }

    private function getScope(int $userId, bool $all = false): string
    {
        $definition = FileCustomAttribute::make('hero');

        if (!$all) {
            $definition->folders($this->heroes->id);
        }

        return $definition->getSignedScope($userId);
    }

    private function login(): User
    {
        $user = $this->getUserFromFixture('admin');
        $this->getWebUser()->login($user);

        return $user;
    }
}
