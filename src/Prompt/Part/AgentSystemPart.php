<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;

final class AgentSystemPart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        return $agent->systemPrompt !== '' ? $agent->systemPrompt : null;
    }
}
