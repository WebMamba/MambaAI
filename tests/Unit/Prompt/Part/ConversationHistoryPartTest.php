<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\ConversationHistoryPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConversationHistoryPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_returns_null_when_memory_disabled(): void
    {
        $part = new ConversationHistoryPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: false);

        self::assertNull($part->getContent($agent, MessageFactory::make()));
    }

    #[Test]
    public function it_returns_null_when_history_jsonl_missing(): void
    {
        mkdir($this->workspace.'/memory');
        $part = new ConversationHistoryPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        self::assertNull($part->getContent($agent, MessageFactory::make()));
    }

    #[Test]
    public function it_parses_jsonl_into_formatted_blocks(): void
    {
        mkdir($this->workspace.'/memory');
        $lines = [
            json_encode(['role' => 'user', 'content' => 'salut', 'at' => '2026-01-01T10:00:00+00:00']),
            json_encode(['role' => 'assistant', 'content' => 'bonjour', 'at' => '2026-01-01T10:00:01+00:00']),
        ];
        file_put_contents($this->workspace.'/memory/history.jsonl', implode("\n", $lines)."\n");

        $part = new ConversationHistoryPart();
        $content = $part->getContent(
            AgentFactory::make(folder: $this->workspace, memory: true),
            MessageFactory::make(),
        );

        self::assertNotNull($content);
        self::assertStringStartsWith('## Recent conversation', $content);
        self::assertStringContainsString('[2026-01-01T10:00:00+00:00] User: salut', $content);
        self::assertStringContainsString('[2026-01-01T10:00:01+00:00] Assistant: bonjour', $content);
    }

    #[Test]
    public function it_skips_malformed_lines(): void
    {
        mkdir($this->workspace.'/memory');
        $lines = [
            json_encode(['role' => 'user', 'content' => 'a', 'at' => '2026-01-01T00:00:00+00:00']),
            'not-json{',
            json_encode(['role' => 'assistant', 'content' => 'b', 'at' => '2026-01-01T00:00:01+00:00']),
        ];
        file_put_contents($this->workspace.'/memory/history.jsonl', implode("\n", $lines)."\n");

        $part = new ConversationHistoryPart();
        $content = $part->getContent(
            AgentFactory::make(folder: $this->workspace, memory: true),
            MessageFactory::make(),
        );

        self::assertNotNull($content);
        self::assertStringContainsString('User: a', $content);
        self::assertStringContainsString('Assistant: b', $content);
        self::assertStringNotContainsString('not-json', $content);
    }

    #[Test]
    public function it_returns_null_when_only_malformed_lines(): void
    {
        mkdir($this->workspace.'/memory');
        file_put_contents($this->workspace.'/memory/history.jsonl', "garbage\nmore-garbage\n");

        $part = new ConversationHistoryPart();
        $content = $part->getContent(
            AgentFactory::make(folder: $this->workspace, memory: true),
            MessageFactory::make(),
        );

        self::assertNull($content);
    }
}
