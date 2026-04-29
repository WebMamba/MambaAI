<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\GitStatusContextPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

final class GitStatusContextPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_returns_null_when_not_a_git_repo(): void
    {
        $part = new GitStatusContextPart($this->workspace);

        self::assertNull($part->getContent(AgentFactory::make(), MessageFactory::make()));
    }

    #[Test]
    public function it_renders_branch_status_and_commits_in_a_git_repo(): void
    {
        $this->initGitRepo();
        $this->git(['config', 'user.name', 'Test User']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'commit.gpgsign', 'false']);
        file_put_contents($this->workspace.'/foo.txt', 'hello');
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'feat: initial']);

        // Add an unstaged change to populate `git status --short`
        file_put_contents($this->workspace.'/foo.txt', 'changed');

        $part = new GitStatusContextPart($this->workspace);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringStartsWith('<context name="gitStatus">', $content);
        self::assertStringEndsWith('</context>', $content);
        self::assertStringContainsString('Current branch:', $content);
        self::assertStringContainsString('Git user: Test User', $content);
        self::assertStringContainsString('Status:', $content);
        self::assertStringContainsString('foo.txt', $content);
        self::assertStringContainsString('Recent commits:', $content);
        self::assertStringContainsString('feat: initial', $content);
    }

    #[Test]
    public function it_marks_status_clean_when_working_tree_is_clean(): void
    {
        $this->initGitRepo();
        $this->git(['config', 'user.name', 'Test User']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'commit.gpgsign', 'false']);
        file_put_contents($this->workspace.'/foo.txt', 'hello');
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'feat: initial']);

        $part = new GitStatusContextPart($this->workspace);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringContainsString("Status:\n(clean)", $content);
    }

    #[Test]
    public function it_targets_all_agents(): void
    {
        self::assertNull((new GitStatusContextPart($this->workspace))->getTargetAgent());
    }

    private function initGitRepo(): void
    {
        $this->git(['init', '--initial-branch=main']);
    }

    /** @param list<string> $args */
    private function git(array $args): void
    {
        $process = new Process(array_merge(['git'], $args), $this->workspace);
        $process->run();
        if (!$process->isSuccessful()) {
            self::fail('git '.implode(' ', $args).' failed: '.$process->getErrorOutput());
        }
    }
}
