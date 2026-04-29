<?php

declare(strict_types=1);

namespace MambaAi;

interface AgentLoaderInterface
{
    /** @return iterable<Agent> */
    public function load(): iterable;
}
