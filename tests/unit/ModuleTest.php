<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\Module;
use Yii;

class ModuleTest extends Unit
{
    public function testAddTransformationsFromTypeOptionsWithWidthModifier(): void
    {
        $module = Yii::$app->getModule('media');
        $this->assertInstanceOf(Module::class, $module);

        $transformations = $module->transformations;
        $module->transformations = [];

        try {
            $module->addTransformationsFromTypeOptions([
                [
                    'transformations' => [
                        'w_200',
                        'w_300@2',
                        'w_400@1.5',
                        'w_500@.5',
                    ],
                ],
            ]);

            $this->assertSame(200, $module->transformations['w_200']['width']);
            $this->assertSame(600, $module->transformations['w_300@2']['width']);
            $this->assertSame(600, $module->transformations['w_400@1.5']['width']);
            $this->assertSame(250, $module->transformations['w_500@.5']['width']);
            $this->assertSame(['w_200', 'w_500@.5', 'w_300@2', 'w_400@1.5'], array_keys($module->transformations));
        } finally {
            $module->transformations = $transformations;
        }
    }
}
