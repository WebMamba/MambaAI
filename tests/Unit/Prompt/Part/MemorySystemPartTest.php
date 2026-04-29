<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\MemorySystemPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class MemorySystemPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_returns_null_when_memory_disabled_on_agent(): void
    {
        $part = new MemorySystemPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: false);

        self::assertNull($part->getContent($agent, MessageFactory::make()));
    }

    #[Test]
    public function it_returns_null_when_memory_directory_missing(): void
    {
        $part = new MemorySystemPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        self::assertNull($part->getContent($agent, MessageFactory::make()));
    }

    #[Test]
    public function it_returns_null_when_memory_md_missing(): void
    {
        mkdir($this->workspace.'/memory');
        $part = new MemorySystemPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        self::assertNull($part->getContent($agent, MessageFactory::make()));
    }

    #[Test]
    public function it_returns_null_when_memory_md_is_empty(): void
    {
        mkdir($this->workspace.'/memory');
        file_put_contents($this->workspace.'/memory/MEMORY.md', "   \n  \n");
        $part = new MemorySystemPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        self::assertNull($part->getContent($agent, MessageFactory::make()));
    }

    #[Test]
    public function it_returns_file_content_with_header(): void
    {
        mkdir($this->workspace.'/memory');
        file_put_contents($this->workspace.'/memory/MEMORY.md', "user prefers French\n");
        $part = new MemorySystemPart();
        $agent = AgentFactory::make(folder: $this->workspace, memory: true);

        $content = $part->getContent($agent, MessageFactory::make());

        self::assertSame("## Memory\n\nuser prefers French", $content);
    }
}
