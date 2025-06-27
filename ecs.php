<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $ecsConfig->skip([
        __DIR__ . '/tests/_output',
        __DIR__ . '/tests/support',
    ]);

    $ecsConfig->sets([
        SetList::CLEAN_CODE,
        SetList::PSR_12,
    ]);
};
