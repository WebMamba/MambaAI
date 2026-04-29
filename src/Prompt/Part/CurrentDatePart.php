<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;

final class CurrentDatePart implements SystemPromptPartInterface
{
    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        return 'Current date and time: '.date('Y-m-d H:i:s').'. Use this only when relevant to the user\'s request.';
    }
}
