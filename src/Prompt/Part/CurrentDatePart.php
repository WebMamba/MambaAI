<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;

final class CurrentDatePart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        return 'Current date and time: '.date('Y-m-d H:i:s').'. Use this only when relevant to the user\'s request.';
    }
}
