<?php

declare(strict_types=1);

namespace MambaAi\Tests\Support\Doubles;

use MambaAi\ChannelInterface;
use MambaAi\Message;
use MambaAi\Renderer\MessageRendererInterface;
use MambaAi\Renderer\NullRenderer;
use Symfony\Component\HttpFoundation\Request;

final class FakeChannel implements ChannelInterface
{
    public int $supportsCallCount = 0;

    public function __construct(
        private bool $supports = true,
        private ?Message $incoming = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        ++$this->supportsCallCount;

        return $this->supports;
    }

    public function receive(Request $request): Message
    {
        return $this->incoming ?? new Message(agent: 'default', content: 'fake');
    }

    public function getRenderer(): MessageRendererInterface
    {
        return new NullRenderer();
    }
}
