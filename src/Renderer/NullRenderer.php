<?php

namespace MambaAi\Version_2\Renderer;

use MambaAi\Version_2\Message;
use MambaAi\Version_2\MessageType;

/**
 * Minimal renderer: passes text through, drops everything else.
 * Useful for testing or channels that handle their own formatting.
 */
class NullRenderer implements MessageRendererInterface
{
    public function render(Message $message): ?string
    {
        return match ($message->type) {
            MessageType::Text  => $message->content,
            MessageType::Error => '[Erreur] ' . $message->content,
            default            => null,
        };
    }
}
