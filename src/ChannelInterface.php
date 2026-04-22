<?php

namespace MambaAi\Version_2;

use MambaAi\Version_2\Renderer\MessageRendererInterface;
use Symfony\Component\HttpFoundation\Request;

interface ChannelInterface
{
    public function getRenderer(): MessageRendererInterface;

    public function supports(Request $request): bool;

    public function receive(Request $request): Message;

    public function send(string $output): void;

    /**
     * Appelé une seule fois après que tous les Messages ont été envoyés via send().
     * Permet au channel de finaliser la réponse (ex: newline final en CLI,
     * fermeture d'un stream SSE, acquittement webhook).
     */
    public function finalize(): void;
}
