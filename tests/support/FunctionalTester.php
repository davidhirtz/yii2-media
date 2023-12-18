<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\support;

use Codeception\Actor;

/**
 * Inherited Methods
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
 */
class FunctionalTester extends Actor
{
    use _generated\FunctionalTesterActions;

    public function seeValidationError(string $message): void
    {
        $this->see($message, '.alert-error');
    }

    public function dontSeeValidationError(string $message): void
    {
        $this->dontSee($message, '.alert-error');
    }
}
