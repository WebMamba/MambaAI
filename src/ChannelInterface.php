<?php

declare(strict_types=1);

namespace MambaAi;

use MambaAi\Renderer\MessageRendererInterface;
use Symfony\Component\HttpFoundation\Request;

interface ChannelInterface
{
    public function supports(Request $request): bool;

    public function receive(Request $request): Message;

    public function getRenderer(): MessageRendererInterface;
}
