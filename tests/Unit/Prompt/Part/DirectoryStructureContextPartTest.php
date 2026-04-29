<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\DirectoryStructureContextPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DirectoryStructureContextPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_lists_root_files_and_directories(): void
    {
        mkdir($this->workspace.'/src', 0o755, true);
        file_put_contents($this->workspace.'/src/Foo.php', '<?php');
        file_put_contents($this->workspace.'/composer.json', '{}');

        $part = new DirectoryStructureContextPart($this->workspace);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringStartsWith('<context name="directoryStructure">', $content);
        self::assertStringEndsWith('</context>', $content);
        self::assertStringContainsString('- '.basename($this->workspace).'/', $content);
        self::assertStringContainsString('- src/', $content);
        self::assertStringContainsString('- Foo.php', $content);
        self::assertStringContainsString('- composer.json', $content);
    }

    #[Test]
    public function it_excludes_default_dirs(): void
    {
        mkdir($this->workspace.'/vendor/foo', 0o755, true);
        mkdir($this->workspace.'/node_modules/bar', 0o755, true);
        mkdir($this->workspace.'/var/cache', 0o755, true);
        mkdir($this->workspace.'/.git/refs', 0o755, true);
        file_put_contents($this->workspace.'/vendor/foo/bar.php', '');
        file_put_contents($this->workspace.'/node_modules/bar/index.js', '');
        file_put_contents($this->workspace.'/keep.txt', '');

        $part = new DirectoryStructureContextPart($this->workspace);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringContainsString('keep.txt', $content);
        self::assertStringNotContainsString('vendor', $content);
        self::assertStringNotContainsString('node_modules', $content);
        self::assertStringNotContainsString('.git', $content);
    }

    #[Test]
    public function it_respects_max_depth(): void
    {
        mkdir($this->workspace.'/a/b/c', 0o755, true);
        file_put_contents($this->workspace.'/a/b/c/deep.txt', 'x');
        file_put_contents($this->workspace.'/a/shallow.txt', 'x');

        $part = new DirectoryStructureContextPart($this->workspace, maxDepth: 1);
        $content = $part->getContent(AgentFactory::make(), MessageFactory::make());

        self::assertNotNull($content);
        self::assertStringContainsString('shallow.txt', $content);
        self::assertStringNotContainsString('deep.txt', $content);
    }

    #[Test]
    public function it_returns_null_when_project_dir_missing(): void
    {
        $part = new DirectoryStructureContextPart($this->workspace.'/missing');

        self::assertNull($part->getContent(AgentFactory::make(), MessageFactory::make()));
    }

    #[Test]
    public function it_targets_all_agents(): void
    {
        self::assertNull((new DirectoryStructureContextPart($this->workspace))->getTargetAgent());
    }
}
