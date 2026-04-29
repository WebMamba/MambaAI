<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class DirectoryStructureContextPart implements SystemPromptPartInterface
{
    private const DEFAULT_EXCLUDES = ['vendor', 'node_modules', 'var', 'cache', 'build', 'dist', '.git'];

    /**
     * @param string[] $excludes
     */
    public function __construct(
        private string $projectDir,
        private int $maxDepth = 2,
        private array $excludes = self::DEFAULT_EXCLUDES,
    ) {
    }

    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        if (!is_dir($this->projectDir)) {
            return null;
        }

        $finder = (new Finder())
            ->in($this->projectDir)
            ->depth('<= '.$this->maxDepth)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->exclude($this->excludes)
            ->sortByName();

        $lines = [
            '<context name="directoryStructure">',
            '- '.basename($this->projectDir).'/',
        ];

        foreach ($finder as $item) {
            $relativePath = $item->getRelativePathname();
            $depth = substr_count($relativePath, \DIRECTORY_SEPARATOR);
            $indent = str_repeat('  ', $depth + 1);
            $lines[] = $indent.'- '.$item->getFilename().($item->isDir() ? '/' : '');
        }

        $lines[] = '</context>';

        return implode("\n", $lines);
    }
}
