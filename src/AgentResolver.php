<?php

namespace MambaAi\Version_2;

use RuntimeException;

class AgentResolver implements AgentResolverInterface
{
    /** @var array<string, Agent> */
    private array $agents = [];
    private bool $loaded = false;

    public function __construct(private AgentLoaderInterface $loader) {}

    public function resolve(Message $message): Agent
    {
        if (!$this->loaded) {
            foreach ($this->loader->load() as $agent) {
                $this->agents[$agent->name] = $agent;
            }
            $this->loaded = true;
        }

        return $this->agents[$message->agent]
            ?? $this->agents['default']
            ?? throw new RuntimeException(
                sprintf('No agent "%s" and no "default" agent configured.', $message->agent)
            );
    }
}
