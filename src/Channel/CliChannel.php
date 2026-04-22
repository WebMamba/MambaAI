<?php

namespace MambaAi\Version_2\Channel;

use MambaAi\Version_2\ChannelInterface;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Renderer\CliRenderer;
use MambaAi\Version_2\Renderer\MessageRendererInterface;
use Symfony\Component\HttpFoundation\Request;

class CliChannel implements ChannelInterface
{
    private bool $hasSentContent = false;

    public function getRenderer(): MessageRendererInterface
    {
        return new CliRenderer();
    }

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

    public function send(string $output): void
    {
        if ($output !== '') {
            $this->hasSentContent = true;
        }

        echo $output;

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    public function finalize(): void
    {
        if (!$this->hasSentContent) {
            echo '[mambaAI] Aucune réponse reçue. Vérifie ta clé API et la configuration du bundle.' . PHP_EOL;
            echo '[mambaAI] Astuce : passe stream: false dans config.yaml de ton agent pour voir l\'erreur complète.' . PHP_EOL;
        }

        echo PHP_EOL;
    }
}
