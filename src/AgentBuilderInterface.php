<?php

declare(strict_types=1);

namespace MambaAi;

interface AgentBuilderInterface
{
    public function build(string $name, string $path): Agent;
}
