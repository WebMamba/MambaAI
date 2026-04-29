<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\EventListener;

use MambaAi\Event\TerminateEvent;
use MambaAi\EventListener\ConversationHistoryListener;
use MambaAi\Message;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConversationHistoryListenerTest extends FilesystemTestCase
{
    #[Test]
    public function it_does_nothing_when_agent_memory_disabled(): void
    {
        $listener = new ConversationHistoryListener();
        $agent = AgentFactory::make(folder: $this->workspace, memory: false);

        $listener(new TerminateEvent(
            answers: [new Message(agent: 'a', content: 'reply')],
            agent: $agent,
            userMessage: MessageFactory::make(content: 'q'),
        ));

        self::assertFileDoesNotExist($this->workspace.'/memory/history.jsonl');
    }

    #[Test]
    public function it_creates_memory_dir_and_writes_two_lines_per_turn(): void
    {
        $listener = new ConversationHistoryListener();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        $listener(new TerminateEvent(
            answers: [new Message(agent: 'a', content: 'bonjour')],
            agent: $agent,
            userMessage: MessageFactory::make(content: 'salut'),
        ));

        $file = $this->workspace.'/memory/history.jsonl';
        self::assertFileExists($file);

        $lines = array_values(array_filter(explode("\n", file_get_contents($file))));
        self::assertCount(2, $lines);
        self::assertSame('user', json_decode($lines[0], true)['role']);
        self::assertSame('salut', json_decode($lines[0], true)['content']);
        self::assertSame('assistant', json_decode($lines[1], true)['role']);
        self::assertSame('bonjour', json_decode($lines[1], true)['content']);
    }

    #[Test]
    public function it_aggregates_streaming_chunks_into_single_assistant_line(): void
    {
        $listener = new ConversationHistoryListener();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        $listener(new TerminateEvent(
            answers: [
                new Message(agent: 'a', content: 'Hel'),
                new Message(agent: 'a', content: 'lo '),
                new Message(agent: 'a', content: 'world'),
            ],
            agent: $agent,
            userMessage: MessageFactory::make(content: 'q'),
        ));

        $lines = array_values(array_filter(explode("\n", file_get_contents($this->workspace.'/memory/history.jsonl'))));
        self::assertSame('Hello world', json_decode($lines[1], true)['content']);
    }

    #[Test]
    public function it_appends_to_existing_history_file(): void
    {
        $dir = $this->workspace.'/memory';
        mkdir($dir);
        $existing = json_encode(['role' => 'user', 'content' => 'old', 'at' => '2026-01-01T00:00:00+00:00']);
        file_put_contents($dir.'/history.jsonl', $existing."\n");

        $listener = new ConversationHistoryListener();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        $listener(new TerminateEvent(
            answers: [new Message(agent: 'a', content: 'new-reply')],
            agent: $agent,
            userMessage: MessageFactory::make(content: 'new-q'),
        ));

        $lines = array_values(array_filter(explode("\n", file_get_contents($dir.'/history.jsonl'))));
        self::assertCount(3, $lines);
        self::assertSame('old', json_decode($lines[0], true)['content']);
        self::assertSame('new-q', json_decode($lines[1], true)['content']);
        self::assertSame('new-reply', json_decode($lines[2], true)['content']);
    }

    #[Test]
    public function it_caps_history_at_max_entries(): void
    {
        $dir = $this->workspace.'/memory';
        mkdir($dir);

        // Pre-fill with 99 lines so a 2-line append (101 total) triggers slicing.
        $seed = [];
        for ($i = 0; $i < 99; ++$i) {
            $seed[] = json_encode(['role' => 'user', 'content' => "old-$i", 'at' => '2026-01-01T00:00:00+00:00']);
        }
        file_put_contents($dir.'/history.jsonl', implode("\n", $seed)."\n");

        $listener = new ConversationHistoryListener();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        $listener(new TerminateEvent(
            answers: [new Message(agent: 'a', content: 'fresh-reply')],
            agent: $agent,
            userMessage: MessageFactory::make(content: 'fresh-q'),
        ));

        $lines = array_values(array_filter(explode("\n", file_get_contents($dir.'/history.jsonl'))));
        self::assertCount(100, $lines);

        // Oldest entry dropped — old-0 must be gone, fresh-q + fresh-reply present at the tail.
        self::assertStringNotContainsString('old-0', file_get_contents($dir.'/history.jsonl'));
        self::assertSame('fresh-q', json_decode($lines[98], true)['content']);
        self::assertSame('fresh-reply', json_decode($lines[99], true)['content']);
    }
}
