<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\UserPromptPartInterface;
use Symfony\AI\Platform\Message\Content\Text;

final class MessageContentPart implements UserPromptPartInterface
{
    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getBlocks(Agent $agent, Message $message): array
    {
        return [new Text($message->content)];
    }
}
