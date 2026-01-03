<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Traits;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\Fixtures\FileFixture;

trait FileFixtureTrait
{
    public function fixtures(): array
    {
        return [
            'file' => [
                'class' => FileFixture::class,
            ],
        ];
    }

    protected function getFileFixture(): FileFixture
    {
        /** @var FileFixture $fixture */
        $fixture = $this->getFixture('file');
        return $fixture;
    }

    protected function getFileFromFixture(string $key): File
    {
        $fixture = $this->getFileFixture();
        return File::findOne($fixture->data[$key]['id']);
    }
}
