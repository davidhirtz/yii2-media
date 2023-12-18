<?php

use davidhirtz\yii2\skeleton\models\User;
use yii\db\Expression;

return [
    'user' => [
        'id' => 1,
        'status' => User::STATUS_ENABLED,
        'name' => 'user',
        'email' => 'user@domain.com',
        'password_hash' => '$2y$13$fsHsH/ZbpVdOY85BaAsW8uWv12zR7NuzHYtgYE0qBtPzQmcjB.a1a', // password
        'password_salt' => 'tVe8JqR-jI',
        'auth_key' => Yii::$app->getSecurity()->generateRandomString(),
        'is_owner' => 0,
        'created_at' => new Expression('UTC_TIMESTAMP()'),
    ],
];
