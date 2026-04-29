<?php

declare(strict_types=1);

namespace MambaAi\Renderer;

use MambaAi\Message;
use MambaAi\MessageType;

/**
 * Minimal renderer: passes text through, drops everything else.
 * Useful for testing or channels that handle their own formatting.
 */
class NullRenderer implements MessageRendererInterface
{
    #[\Override]
    public function render(Message $message): ?string
    {
        return match ($message->type) {
            MessageType::Text => $message->content,
            MessageType::Error => '[Error] '.$message->content,
            default => null,
        };
    }
}
