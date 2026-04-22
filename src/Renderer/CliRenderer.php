<?php

namespace MambaAi\Version_2\Renderer;

use MambaAi\Version_2\Message;
use MambaAi\Version_2\MessageType;

/**
 * ANSI renderer for the CLI channel.
 * Displays thinking blocks dimmed, tool calls with status indicators,
 * and plain text as-is for real-time streaming.
 */
class CliRenderer implements MessageRendererInterface
{
    private bool $thinkingStarted = false;
    private bool $thinkingEnded = false;
    private string $currentToolId = '';

    public function render(Message $message): ?string
    {
        return match ($message->type) {
            MessageType::Text     => $this->renderText($message),
            MessageType::Thinking => $this->renderThinking($message),
            MessageType::ToolCall => $this->renderToolCall($message),
            MessageType::Error    => "\n\033[31m[Erreur] " . $message->content . "\033[0m\n",
            default               => null,
        };
    }

    private function renderText(Message $message): ?string
    {
        if ($message->content === '') {
            return null;
        }

        // Close thinking block if we were in one
        if ($this->thinkingStarted && !$this->thinkingEnded) {
            $this->thinkingEnded = true;
            return "\033[0m\n" . $message->content;
        }

        return $message->content;
    }

    private function renderThinking(Message $message): ?string
    {
        $phase = $message->context['phase'] ?? 'delta';

        if ($phase === 'start' || (!$this->thinkingStarted && $message->content === '')) {
            $this->thinkingStarted = true;
            $this->thinkingEnded = false;
            return "\033[2m✦ Réflexion...\n";
        }

        if ($message->content === '') {
            return null;
        }

        // Stream thinking content dimmed
        return "\033[2m" . $message->content . "\033[0m\033[2m";
    }

    private function renderToolCall(Message $message): ?string
    {
        $phase = $message->context['phase'] ?? 'start';
        $name  = $message->content;
        $id    = $message->context['id'] ?? '';

        return match ($phase) {
            'start'    => $this->toolStart($id, $name),
            'input'    => $this->toolInput($id, $name, $message->context['args'] ?? ''),
            'complete' => $this->toolComplete($name, $message->context['args'] ?? ''),
            'error'    => $this->toolError($name, $message->context['args'] ?? ''),
            default    => null,
        };
    }

    private function toolStart(string $id, string $name): string
    {
        $this->currentToolId = $id;
        // Yellow spinner + tool name — no newline so 'input' phase can overwrite
        return "\n\033[33m⟳ " . $name . "\033[0m";
    }

    private function toolInput(string $id, string $name, string $partialArgs): ?string
    {
        if ($id !== $this->currentToolId) {
            return null;
        }

        $preview = $this->previewArgs($partialArgs);
        // Overwrite current line
        return "\r\033[33m⟳ " . $name . '(' . $preview . ")\033[0m";
    }

    private function toolComplete(string $name, string $args): string
    {
        $preview = $this->previewArgs($args);
        return "\r\033[32m✓ " . $name . '(' . $preview . ")\033[0m\n";
    }

    private function toolError(string $name, string $args): string
    {
        $preview = $this->previewArgs($args);
        return "\r\033[31m✗ " . $name . '(' . $preview . ")\033[0m\n";
    }

    private function previewArgs(string $json): string
    {
        if ($json === '') {
            return '';
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            // Partial JSON not yet valid — show raw, truncated
            return mb_strimwidth($json, 0, 60, '…');
        }

        $parts = [];
        foreach ($decoded as $key => $value) {
            $str = is_string($value) ? $value : json_encode($value);
            $parts[] = $key . ': ' . mb_strimwidth($str, 0, 40, '…');
        }

        return implode(', ', $parts);
    }
}
