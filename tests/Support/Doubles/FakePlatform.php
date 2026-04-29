<?php

declare(strict_types=1);

namespace MambaAi\Tests\Support\Doubles;

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Stub minimal — les tests unitaires actuels n'invoquent jamais le LLM.
 * Si un test essaie, on lève une exception explicite.
 */
final class FakePlatform implements PlatformInterface
{
    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        throw new \LogicException('FakePlatform::invoke() should not be called in unit tests.');
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        throw new \LogicException('FakePlatform::getModelCatalog() should not be called in unit tests.');
    }
}
