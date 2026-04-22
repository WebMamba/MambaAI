<?php

namespace MambaAi\Version_2\Renderer;

use MambaAi\Version_2\Message;

interface MessageRendererInterface
{
    /**
     * Transform a Message into a string to output via the channel.
     * Return null to skip sending this message entirely.
     */
    public function render(Message $message): ?string;
}
