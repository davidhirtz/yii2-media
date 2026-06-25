<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\Module;
use Yii;

class ModuleTest extends Unit
{
    public function testAddTransformationsFromTypeOptionsWithDimensionModifiers(): void
    {
        $module = Yii::$app->getModule('media');
        $this->assertInstanceOf(Module::class, $module);

        $module->addTransformationsFromTypeOptions([
            [
                'transformations' => [
                    'w_200',
                    'h_200',
                    'w_300@2',
                    'h_300@2',
                    'w_400@1.5',
                    'w_500@.5',
                    'w_200,h_300',
                    'w_200,h_300@2',
                ],
            ],
        ]);

        $this->assertSame(200, $module->transformations['w_200']['width']);
        $this->assertSame(200, $module->transformations['h_200']['height']);
        $this->assertSame(600, $module->transformations['w_300@2']['width']);
        $this->assertSame(600, $module->transformations['h_300@2']['height']);
        $this->assertSame(600, $module->transformations['w_400@1.5']['width']);
        $this->assertSame(250, $module->transformations['w_500@.5']['width']);
        $this->assertSame(200, $module->transformations['w_200,h_300']['width']);
        $this->assertSame(300, $module->transformations['w_200,h_300']['height']);
        $this->assertSame(400, $module->transformations['w_200,h_300@2']['width']);
        $this->assertSame(600, $module->transformations['w_200,h_300@2']['height']);
    }
}
