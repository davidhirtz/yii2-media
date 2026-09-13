<?php

declare(strict_types=1);

return [
    ...require Yii::getAlias('@skeleton/../messages/config.php'),
    'messagePath' => __DIR__,
    'categories' => ['media'],
];
