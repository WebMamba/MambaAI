<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\UserPromptPartInterface;
use Symfony\AI\Platform\Message\Content\Text;

final class CurrentDatePart implements UserPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getBlocks(Agent $agent, Message $message): array
    {
        return [new Text('The current time is: '.date('Y-m-d H:i:s'))];
    }
}
