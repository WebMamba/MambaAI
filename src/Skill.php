<?php

namespace MambaAi\Version_2;

final class Skill
{
    public function __construct(
        public readonly string $name,
        public readonly string $content,
    ) {}
}
