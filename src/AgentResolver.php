<?php

declare(strict_types=1);

namespace MambaAi;

class AgentResolver implements AgentResolverInterface
{
    /** @var array<string, Agent> */
    private array $agents = [];
    private bool $loaded = false;

    public function __construct(private AgentLoaderInterface $loader)
    {
    }

    #[\Override]
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
            ?? throw new \RuntimeException(\sprintf('No agent "%s" and no "default" agent configured.', $message->agent));
    }
}
