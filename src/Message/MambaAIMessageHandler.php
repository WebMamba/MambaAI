<?php

namespace MambaAi\Version_2\Message;

use MambaAi\Version_2\AgentKernel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MambaAIMessageHandler
{
    public function __construct(private AgentKernel $agentKernel) {}

    public function __invoke(MambaAIMessage $message): void
    {
        $this->agentKernel->handle($message->toRequest());
    }
}
