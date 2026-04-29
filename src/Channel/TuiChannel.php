<?php

declare(strict_types=1);

namespace MambaAi\Channel;

use MambaAi\ChannelInterface;
use MambaAi\Message;
use MambaAi\Renderer\MessageRendererInterface;
use MambaAi\Renderer\TuiRenderer;
use Symfony\Component\HttpFoundation\Request;

class TuiChannel implements ChannelInterface
{
    public function __construct(private TuiRenderer $renderer)
    {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return 'tui' === $request->attributes->get('_channel');
    }

    #[\Override]
    public function receive(Request $request): Message
    {
        return new Message(
            agent: $request->attributes->get('_agent', 'default'),
            content: $request->attributes->get('_content', ''),
        );
    }

    #[\Override]
    public function getRenderer(): MessageRendererInterface
    {
        return $this->renderer;
    }
}
