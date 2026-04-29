<?php

declare(strict_types=1);

namespace MambaAi\Tui;

use Symfony\Component\Tui\Widget\CancellableLoaderWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;

class StreamAnimator
{
    private ?int $startTime = null;
    private int $lastSecond = -1;
    private bool $blinkOn = true;
    private float $blinkTime = 0.0;

    public function __construct(
        private CancellableLoaderWidget $loader,
        private MarkdownWidget $toolText,
        private string $verb,
    ) {
    }

    public function start(): void
    {
        $this->startTime = time();
        $this->lastSecond = -1;
        $this->blinkOn = true;
        $this->blinkTime = 0.0;
        $this->loader->setMessage('  '.$this->verb.'…');
    }

    public function stop(): void
    {
        $this->startTime = null;
        $this->lastSecond = -1;
        $this->loader->reset();
        $this->loader->setMessage('  '.$this->verb.'…');
    }

    /**
     * @param ?array{name: string, args: string} $currentTool
     */
    public function tick(?array $currentTool): void
    {
        $this->loader->tick();

        if ($this->loader->isRunning() && null !== $this->startTime) {
            $elapsed = time() - $this->startTime;
            if ($elapsed !== $this->lastSecond) {
                $this->lastSecond = $elapsed;
                $this->loader->setMessage('  '.$this->verb.'… ('.$elapsed.'s)');
            }
        }

        if (null !== $currentTool) {
            $now = microtime(true);
            if ($now - $this->blinkTime >= 0.3) {
                $this->blinkOn = !$this->blinkOn;
                $this->blinkTime = $now;
            }
            // Alternate between filled and empty circle: same visual width as ⏺,
            // so the text after the dot doesn't shift each frame (which would look
            // like a flickering "border" appearing/disappearing).
            $dot = $this->blinkOn ? '⏺' : '◯';
            $args = '' !== $currentTool['args'] ? '('.$currentTool['args'].')' : '';
            $this->toolText->setText('  '.$dot.'  '.$currentTool['name'].$args.'…');
        }
    }
}
