<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class KnowledgeSystemPart implements SystemPromptPartInterface
{
    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        if (null === $agent->knowledgeDir) {
            return null;
        }

        $finder = (new Finder())->in($agent->knowledgeDir)->sortByName()->ignoreDotFiles(true);

        $lines = ['## Knowledge structure'];
        foreach ($finder as $item) {
            $depth = '' === $item->getRelativePath()
                ? 0
                : substr_count($item->getRelativePath(), \DIRECTORY_SEPARATOR) + 1;
            $indent = str_repeat('  ', $depth);
            $lines[] = $indent.'- '.$item->getFilename().($item->isDir() ? '/' : '');
        }

        return implode("\n", $lines);
    }
}
