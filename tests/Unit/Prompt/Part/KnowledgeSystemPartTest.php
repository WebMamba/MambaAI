<?php

declare(strict_types=1);

namespace MambaAi\Tests\Unit\Prompt\Part;

use MambaAi\Prompt\Part\KnowledgeSystemPart;
use MambaAi\Tests\Support\Factory\AgentFactory;
use MambaAi\Tests\Support\Factory\MessageFactory;
use MambaAi\Tests\TestCase\FilesystemTestCase;
use PHPUnit\Framework\Attributes\Test;

final class KnowledgeSystemPartTest extends FilesystemTestCase
{
    #[Test]
    public function it_returns_null_when_knowledge_dir_is_null(): void
    {
        $part = new KnowledgeSystemPart();

        self::assertNull(
            $part->getContent(AgentFactory::make(knowledgeDir: null), MessageFactory::make()),
        );
    }

    #[Test]
    public function it_lists_files_with_indented_tree_format(): void
    {
        $kdir = $this->workspace.'/knowledge';
        mkdir($kdir.'/sub', 0o755, true);
        file_put_contents($kdir.'/intro.md', '# intro');
        file_put_contents($kdir.'/sub/deep.md', '# deep');

        $part = new KnowledgeSystemPart();
        $content = $part->getContent(
            AgentFactory::make(knowledgeDir: $kdir),
            MessageFactory::make(),
        );

        self::assertNotNull($content);
        self::assertStringStartsWith('## Knowledge structure', $content);
        self::assertStringContainsString('- intro.md', $content);
        self::assertStringContainsString('- sub/', $content);
        self::assertStringContainsString('- deep.md', $content);
    }

    #[Test]
    public function it_ignores_dotfiles(): void
    {
        $kdir = $this->workspace.'/knowledge';
        mkdir($kdir, 0o755, true);
        file_put_contents($kdir.'/visible.md', 'x');
        file_put_contents($kdir.'/.hidden', 'y');

        $part = new KnowledgeSystemPart();
        $content = $part->getContent(
            AgentFactory::make(knowledgeDir: $kdir),
            MessageFactory::make(),
        );

        self::assertNotNull($content);
        self::assertStringContainsString('visible.md', $content);
        self::assertStringNotContainsString('.hidden', $content);
    }
}
