<?php

declare(strict_types=1);

namespace Hirtz\Media\Setup;

use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Setup\Seeder;
use Override;
use Yii;

/**
 * Without a declaration, the folder is the default one.
 */
class FolderSeeder extends Seeder
{
    #[Override]
    public function run(?array $value): void
    {
        if (Folder::find()->count()) {
            return;
        }

        $value ??= [['name' => Yii::t('media', 'FOLDER_DEFAULT_NAME')]];

        foreach ($value as $attributes) {
            $folder = Folder::instantiateByType(self::getDeclaredType((array)$attributes) ?? Folder::TYPE_DEFAULT);
            $folder->setAttributes((array)$attributes);

            $this->insert($folder);
        }
    }
}
