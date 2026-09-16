<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Types\Traits;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Models\Interfaces\TransformationTypeInterface;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Transformations\Transformation;
use yii\base\InvalidConfigException;

/**
 * **Trap:** a using class that declares `validate()` itself shadows the one below, since a class method wins over a
 * trait's — it has to alias it (`use TransformationTypeTrait { validate as validateTransformationType; }`) and call it.
 *
 * @mixin TransformationTypeInterface
 */
trait TransformationTypeTrait
{
    use ModuleTrait;

    /**
     * @var list<Size>
     */
    protected array $sizes = [];

    /**
     * @var list<Transformation|string>
     */
    protected array $transformations = [];

    /**
     * @param Size|string ...$sizes a plain string is the bare default length, which must come last
     */
    public function sizes(Size|string ...$sizes): static
    {
        $this->sizes = array_map(
            static fn (Size|string $size): Size => $size instanceof Size ? $size : Size::value($size),
            array_values($sizes),
        );

        return $this;
    }

    /**
     * @param Transformation|string ...$transformations a configured preset name, a self-describing one
     * ({@see Transformation::fromName()}) or an inline definition
     */
    public function transformations(Transformation|string ...$transformations): static
    {
        $this->transformations = array_values($transformations);
        return $this;
    }

    public function getSizes(): ?string
    {
        return $this->sizes ? implode(',', array_map(strval(...), $this->sizes)) : null;
    }

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array
    {
        return array_map(
            static fn (Transformation|string $transformation): string => is_string($transformation)
                ? $transformation
                : $transformation->name,
            $this->transformations,
        );
    }

    /**
     * Registers the transformations the type declares itself, so a self-describing or inline one needs no entry in
     * the module configuration.
     *
     * @param class-string $modelClass
     */
    public function validate(string $modelClass): void
    {
        parent::validate($modelClass);

        foreach ($this->sizes as $offset => $size) {
            if (!$size->isConditional() && $offset !== array_key_last($this->sizes)) {
                throw new InvalidConfigException("{$this->getDisplayValue()} of $modelClass declares the size \"$size\" before a conditional one, which a browser ignores.");
            }
        }

        $module = static::getModule();

        foreach ($this->transformations as $transformation) {
            if ($transformation instanceof Transformation) {
                $module->addTransformation($transformation);
                continue;
            }

            if ($module->hasTransformation($transformation)) {
                continue;
            }

            $parsed = Transformation::fromName($transformation);

            if (!$parsed) {
                throw new InvalidConfigException("{$this->getDisplayValue()} of $modelClass names the transformation \"$transformation\", which is neither configured nor a self-describing name.");
            }

            $module->addTransformation($parsed);
        }
    }
}
