<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\ProjectInstructionsContextPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProjectInstructionsContextPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_returns_null_when_no_claude_md_exists(): void
    {
        $part = new ProjectInstructionsContextPart($this->workspace, $this->workspace.'/home');

        self::assertNull($part->getContent(AgentFactory::make(), MessageFactory::make()));
    }

    #[Test]
    public function it_includes_project_claude_md_content(): void
    {
        file_put_contents($this->workspace.'/CLAUDE.md', "# Project rules\n\nDo X, Y, Z.");

        $part = new ProjectInstructionsContextPart($this->workspace, $this->workspace.'/home');
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringStartsWith('<context name="codeStyle">', $content);
        self::assertStringEndsWith('</context>', $content);
        self::assertStringContainsString('# Project CLAUDE.md', $content);
        self::assertStringContainsString('Do X, Y, Z.', $content);
    }

    #[Test]
    public function it_includes_user_claude_md_when_present(): void
    {
        $home = $this->workspace.'/home';
        mkdir($home.'/.claude', 0o755, true);
        file_put_contents($home.'/.claude/CLAUDE.md', '# User prefs');

        $part = new ProjectInstructionsContextPart($this->workspace, $home);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringContainsString('# User CLAUDE.md', $content);
        self::assertStringContainsString('# User prefs', $content);
    }

    #[Test]
    public function it_concatenates_project_and_user_claude_md(): void
    {
        file_put_contents($this->workspace.'/CLAUDE.md', '# project');
        $home = $this->workspace.'/home';
        mkdir($home.'/.claude', 0o755, true);
        file_put_contents($home.'/.claude/CLAUDE.md', '# user');

        $part = new ProjectInstructionsContextPart($this->workspace, $home);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringContainsString('# Project CLAUDE.md', $content);
        self::assertStringContainsString('# project', $content);
        self::assertStringContainsString('# User CLAUDE.md', $content);
        self::assertStringContainsString('# user', $content);
    }

    #[Test]
    public function it_targets_all_agents(): void
    {
        self::assertNull(
            (new ProjectInstructionsContextPart($this->workspace, $this->workspace.'/home'))->getTargetAgent(),
        );
    }
}
