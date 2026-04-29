<?php

declare(strict_types=1);

namespace MambaAi;

final class Skill
{
    public function __construct(
        public readonly string $name,
        public readonly string $content,
    ) {
    }
}
