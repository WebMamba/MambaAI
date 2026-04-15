<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\UserPromptPartInterface;
use Symfony\AI\Platform\Message\Content\Text;

final class MessageContentPart implements UserPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getBlocks(Agent $agent, Message $message): array
    {
        return [new Text($message->content)];
    }
}
