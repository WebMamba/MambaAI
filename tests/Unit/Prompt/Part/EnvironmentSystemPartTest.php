<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\EnvironmentSystemPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class EnvironmentSystemPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_renders_env_block_with_all_keys(): void
    {
        $part = new EnvironmentSystemPart($this->workspace);

        $content = $part->getContent(
            AgentFactory::make(model: 'claude-opus-4-7'),
            MessageFactory::make(),
        );

        self::assertNotNull($content);
        self::assertStringStartsWith('<env>', $content);
        self::assertStringEndsWith('</env>', $content);
        self::assertStringContainsString('Working directory: '.$this->workspace, $content);
        self::assertStringContainsString('Is directory a git repo: No', $content);
        self::assertStringContainsString('Platform: ', $content);
        self::assertStringContainsString('Today\'s date: '.date('Y-m-d'), $content);
        self::assertStringContainsString('Model: claude-opus-4-7', $content);
    }

    #[Test]
    public function it_reports_yes_when_dot_git_directory_exists(): void
    {
        mkdir($this->workspace.'/.git', 0o755, true);

        $part = new EnvironmentSystemPart($this->workspace);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringContainsString('Is directory a git repo: Yes', $content);
    }

    #[Test]
    public function it_targets_all_agents(): void
    {
        self::assertNull((new EnvironmentSystemPart($this->workspace))->getTargetAgent());
    }
}
