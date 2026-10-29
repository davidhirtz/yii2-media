<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\Redirect;
use Yii;

/**
 * Renaming a folder changes none of its files' records, so `Skeleton\Behaviors\RedirectBehavior` never fires for them.
 */
class SaveFolderRedirects
{
    private int $count = 0;
    private bool $isSkipped = false;

    public function __construct(
        protected Folder $folder,
        protected string $previousPath,
    ) {
    }

    public function run(): bool
    {
        if ($this->previousPath === $this->folder->path) {
            return true;
        }

        if (!static::isWithinLimit($this->folder)) {
            $this->isSkipped = true;
            Yii::warning(
                "Renaming folder {$this->folder->id} skipped the redirects of its {$this->folder->file_count} files",
                __METHOD__
            );

            return false;
        }

        // Both URLs are built from the folder at hand: `File::getUrl()` resolves its folder through
        // `Collections\FolderCollection`, whose cache the save has not invalidated yet.
        $previousUrl = $this->folder->getBaseUrl() . rtrim($this->previousPath, '/') . '/';
        $url = $this->folder->getUploadUrl();

        /** @var File $file */
        foreach ($this->folder->getFiles()->each() as $file) {
            $filename = $file->getFilename();
            $this->saveRedirect($previousUrl . $filename, $url . $filename);
        }

        return true;
    }

    protected function saveRedirect(string $previousUrl, string $url): void
    {
        $previousUrl = Url::sanitize($previousUrl);
        $url = Url::sanitize($url);

        if (!$previousUrl || !$url || $previousUrl === $url) {
            return;
        }

        $this->updatePreviousRedirects($previousUrl, $url);

        $redirect = Redirect::create();
        $redirect->request_uri = $previousUrl;
        $redirect->url = $url;

        if ($redirect->insert()) {
            $this->count++;
            return;
        }

        Yii::warning(
            "Redirect from $previousUrl could not be saved: " . implode(' ', $redirect->getErrorSummary(true)),
            __METHOD__
        );
    }

    /**
     * The no-op redirect is deleted first, since {@see Redirect::validateUrl()} would otherwise follow it back to the
     * old URL.
     */
    protected function updatePreviousRedirects(string $previousUrl, string $url): void
    {
        /** @var Redirect[] $redirects */
        $redirects = Redirect::find()
            ->where(['url' => $previousUrl])
            ->all();

        foreach ($redirects as $key => $redirect) {
            if ($redirect->request_uri === $url) {
                $redirect->delete();
                unset($redirects[$key]);
            }
        }

        foreach ($redirects as $redirect) {
            $redirect->url = $url;

            if (!$redirect->update()) {
                Yii::warning("Redirect from $redirect->request_uri could not be updated", __METHOD__);
            }
        }
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function isSkipped(): bool
    {
        return $this->isSkipped;
    }

    public static function isWithinLimit(Folder $folder): bool
    {
        $max = Folder::getModule()->maxFolderRedirects;
        return $max !== false && $folder->file_count <= $max;
    }

    public static function create(Folder $folder, string $previousPath): static
    {
        $action = Yii::createObject(static::class, [$folder, $previousPath]);
        $action->run();

        return $action;
    }
}
