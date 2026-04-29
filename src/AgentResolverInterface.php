<?php

declare(strict_types=1);

namespace MambaAi;

interface AgentResolverInterface
{
    public function resolve(Message $message): Agent;
}
