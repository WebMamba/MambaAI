<?php

declare(strict_types=1);

namespace MambaAi\Renderer;

use MambaAi\Message;
use MambaAi\MessageType;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\CancellableLoaderWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;

/**
 * Renderer for the TUI surface.
 *
 * Owns all TUI state (conversation buffer, current tool, widget references)
 * and routes each Message to the right widget mutation. The kernel calls
 * render() for each Message; render() returns null because the side effect
 * (mutating widgets) is the rendering — there's no string to yield.
 *
 * The non-render() public methods are the TUI lifecycle hooks driven by
 * ChatCommand (user submit, ESC interrupt, Tab cycle, end of turn).
 */
class TuiRenderer implements MessageRendererInterface
{
    private const INTERNAL_TOOLS = ['memory_write', 'memory_read'];

    private string $conversation = '';
    /** @var ?array{name: string, args: string} */
    private ?array $currentTool = null;

    public function __construct(
        private Tui $tui,
        private MarkdownWidget $conversationWidget,
        private CancellableLoaderWidget $loader,
        private MarkdownWidget $toolText,
        private EditorWidget $editor,
        private MarkdownWidget $statusText,
    ) {
        $this->setStreaming(false);
    }

    #[\Override]
    public function render(Message $message): mixed
    {
        match ($message->type) {
            MessageType::Loading => $this->loader->start(),
            MessageType::LoadingStop => $this->stopLoader(),
            MessageType::Text => $this->appendText($message),
            MessageType::ToolCall => $this->appendToolCall($message),
            MessageType::Error => $this->appendError($message->content),
            default => null,
        };

        return null;
    }

    public function appendUserMessage(string $message): void
    {
        $this->conversation .= "\n\e[48;2;60;38;12m\e[38;2;255;185;95m ❯  ".$message."\e[K\e[0m\n\n";
        $this->conversationWidget->setText($this->conversation);
        $this->toolText->setText('');
        $this->currentTool = null;
    }

    public function markInterrupted(): void
    {
        $this->conversation .= " _(interrupted)_\n\n";
        $this->conversationWidget->setText($this->conversation);
        $this->currentTool = null;
        $this->toolText->setText('');
    }

    public function finalizeAssistantTurn(): void
    {
        $this->conversation .= "\n\n";
        $this->conversationWidget->setText($this->conversation);
        $this->toolText->setText('');
        $this->currentTool = null;
        $this->loader->stop();
        $this->tui->setFocus($this->editor);
    }

    public function setStreaming(bool $on): void
    {
        $this->statusText->setText($on ? '' : '_tab to switch agent_');
    }

    public function setAgentPrompt(string $name): void
    {
        $this->editor->setPrompt('  '.$name.' ❯  ');
    }

    /**
     * @return ?array{name: string, args: string}
     */
    public function getCurrentTool(): ?array
    {
        return $this->currentTool;
    }

    private function stopLoader(): void
    {
        $this->loader->stop();
        $this->toolText->setText('');
    }

    private function appendText(Message $msg): void
    {
        $this->conversation .= $msg->content;
        $this->conversationWidget->setText($this->conversation);
    }

    private function appendError(string $content): void
    {
        $this->loader->stop();
        $this->currentTool = null;
        $this->toolText->setText('');
        $this->conversation .= "\n\n**✗ ".$content."**\n\n";
        $this->conversationWidget->setText($this->conversation);
    }

    private function appendToolCall(Message $msg): void
    {
        $phase = $msg->context['phase'] ?? '';
        $name = $msg->content;
        $isInternalTool = \in_array($name, self::INTERNAL_TOOLS, true);

        if ($isInternalTool) {
            if ('complete' === $phase) {
                $this->currentTool = null;
                $this->toolText->setText('');
            }

            return;
        }

        if ('start' === $phase) {
            $this->loader->stop();
            $this->currentTool = ['name' => $name, 'args' => ''];

            return;
        }

        if ('input' === $phase) {
            // Update the live preview shown in $toolText while args stream in.
            $rawArgs = $msg->context['args'] ?? '';
            if (null !== $this->currentTool) {
                $this->currentTool['args'] = $this->formatToolArgs($rawArgs);
            }

            return;
        }

        if ('complete' === $phase) {
            // Always derive args from the message itself — currentTool may have been
            // reset by a prior tool's completion when the model emits multiple tool
            // calls in one turn (Anthropic order: start1, start2, complete1, complete2…).
            $rawArgs = $msg->context['args'] ?? '';
            $displayArgs = $this->formatToolArgs($rawArgs);
            $args = '' !== $displayArgs ? '('.$displayArgs.')' : '';
            $this->conversation .= "\n  ⏺  ".$name.$args."\n";
            $this->conversationWidget->setText($this->conversation);
            $this->currentTool = null;
            $this->toolText->setText('');
            $this->loader->start();

            return;
        }

        if ('error' === $phase) {
            $this->currentTool = null;
            $this->toolText->setText('');
            $this->conversation .= "\n**✗ ".$name." failed**\n";
            $this->conversationWidget->setText($this->conversation);
        }
    }

    /**
     * Pick the most descriptive value out of a tool's input JSON.
     * Recognizes common keys (command, file_path, query, url…) before falling back.
     */
    private function formatToolArgs(string $rawArgs): string
    {
        if ('' === $rawArgs) {
            return '';
        }

        $parsed = json_decode($rawArgs, true);
        if (!\is_array($parsed) || [] === $parsed) {
            // Partial JSON during streaming — show the truncated raw payload.
            return $this->trimArg($rawArgs, 40);
        }

        if (isset($parsed['command'])) {
            return $this->trimArg((string) $parsed['command'], 60);
        }
        if (isset($parsed['file_path'])) {
            return $this->trimArg(basename((string) $parsed['file_path']), 50);
        }
        if (isset($parsed['path'])) {
            return $this->trimArg(basename((string) $parsed['path']), 50);
        }
        if (isset($parsed['file'])) {
            return $this->trimArg(basename((string) $parsed['file']), 50);
        }
        if (isset($parsed['query'])) {
            return $this->trimArg((string) $parsed['query'], 60);
        }
        if (isset($parsed['url'])) {
            return $this->trimArg((string) $parsed['url'], 60);
        }
        if (isset($parsed['name'])) {
            return $this->trimArg((string) $parsed['name'], 50);
        }

        if (1 === \count($parsed)) {
            $first = reset($parsed);
            if (\is_scalar($first)) {
                return $this->trimArg((string) $first, 60);
            }
        }

        foreach ($parsed as $key => $value) {
            if (\is_scalar($value)) {
                return $key.'='.$this->trimArg((string) $value, 40);
            }
        }

        return '';
    }

    private function trimArg(string $value, int $max): string
    {
        return mb_strimwidth(str_replace(["\n", "\r"], ' ', $value), 0, $max, '…');
    }
}
