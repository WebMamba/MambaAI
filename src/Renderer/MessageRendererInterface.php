<?php

declare(strict_types=1);

namespace MambaAi\Renderer;

use MambaAi\Message;

interface MessageRendererInterface
{
    /**
     * Transform a Message into the format expected by the channel.
     * Return null to skip this message (it won't be yielded by the kernel).
     *
     * Implementations may return strings (text-based channels: CLI, Slack),
     * Message objects (passthrough for in-process channels: TUI), or any
     * other type appropriate for the channel.
     */
    public function render(Message $message): mixed;
}
