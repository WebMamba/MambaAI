<?php

declare(strict_types=1);

namespace MambaAi\Tests\Support\Doubles;

use MambaAi\Agent;
use MambaAi\AgentLoaderInterface;

final class FakeAgentLoader implements AgentLoaderInterface
{
    public int $loadCallCount = 0;

    /**
     * @param Agent[] $agents
     */
    public function __construct(private array $agents)
    {
    }

    public function load(): iterable
    {
        ++$this->loadCallCount;

        return $this->agents;
    }
}
