<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\ReadmeContextPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ReadmeContextPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_returns_null_when_no_readme(): void
    {
        $part = new ReadmeContextPart($this->workspace);

        self::assertNull($part->getContent(AgentFactory::make(), MessageFactory::make()));
    }

    #[Test]
    public function it_returns_null_when_readme_is_empty(): void
    {
        file_put_contents($this->workspace.'/README.md', "   \n   \n");

        $part = new ReadmeContextPart($this->workspace);

        self::assertNull($part->getContent(AgentFactory::make(), MessageFactory::make()));
    }

    #[Test]
    public function it_wraps_readme_content_in_context_tags(): void
    {
        file_put_contents($this->workspace.'/README.md', "# My project\n\nA short description.");

        $part = new ReadmeContextPart($this->workspace);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringStartsWith('<context name="readme">', $content);
        self::assertStringEndsWith('</context>', $content);
        self::assertStringContainsString('# My project', $content);
        self::assertStringContainsString('A short description.', $content);
    }

    #[Test]
    public function it_targets_all_agents(): void
    {
        self::assertNull((new ReadmeContextPart($this->workspace))->getTargetAgent());
    }
}
