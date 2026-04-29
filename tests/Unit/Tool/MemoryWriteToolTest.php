<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Tool;

use MambaAi\Tests\TestCase\FilesystemTestCase;
use MambaAi\Tool\MemoryWriteTool;
use PHPUnit\Framework\Attributes\Test;

final class MemoryWriteToolTest extends FilesystemTestCase
{
    #[Test]
    public function it_creates_memory_dir_when_missing(): void
    {
        $agent = $this->workspace.'/agent-a';
        mkdir($agent);
        $tool = new MemoryWriteTool($agent);

        $tool('contenu mémoire');

        self::assertDirectoryExists($agent.'/memory');
        self::assertFileExists($agent.'/memory/MEMORY.md');
    }

    #[Test]
    public function it_writes_full_content_to_memory_md(): void
    {
        $agent = $this->workspace.'/agent-b';
        mkdir($agent.'/memory', 0o755, true);
        $tool = new MemoryWriteTool($agent);

        $tool('hello world');

        self::assertSame('hello world', file_get_contents($agent.'/memory/MEMORY.md'));
    }

    #[Test]
    public function it_overwrites_existing_memory(): void
    {
        $agent = $this->workspace.'/agent-c';
        mkdir($agent.'/memory', 0o755, true);
        file_put_contents($agent.'/memory/MEMORY.md', 'old');
        $tool = new MemoryWriteTool($agent);

        $tool('new');

        self::assertSame('new', file_get_contents($agent.'/memory/MEMORY.md'));
    }

    #[Test]
    public function it_returns_confirmation_string(): void
    {
        $agent = $this->workspace.'/agent-d';
        mkdir($agent);
        $tool = new MemoryWriteTool($agent);

        self::assertSame('Memory updated.', $tool('x'));
    }
}
