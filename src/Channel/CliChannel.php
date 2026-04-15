<?php

namespace MambaAi\Version_2\Channel;

use MambaAi\Version_2\ChannelInterface;
use MambaAi\Version_2\Message;
use Symfony\Component\HttpFoundation\Request;

class CliChannel implements ChannelInterface
{
    public function supports(Request $request): bool
    {
        return $request->attributes->get('_channel') === 'cli';
    }

    public function receive(Request $request): Message
    {
        return new Message(
            agent: $request->attributes->get('_agent', 'default'),
            content: $request->attributes->get('_content', ''),
        );
    }

    public function send(Message $message): void
    {
        echo $message->content;

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    public function finalize(): void
    {
        echo PHP_EOL;
    }
}
