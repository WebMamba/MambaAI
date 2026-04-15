<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class KnowledgeSystemPart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

    public function getContent(Agent $agent, Message $message): ?string
    {
        if ($agent->knowledgeDir === null) {
            return null;
        }

        $finder = (new Finder())->in($agent->knowledgeDir)->sortByName()->ignoreDotFiles(true);

        $lines = ['## Knowledge structure'];
        foreach ($finder as $item) {
            $depth = $item->getRelativePath() === ''
                ? 0
                : substr_count($item->getRelativePath(), DIRECTORY_SEPARATOR) + 1;
            $indent = str_repeat('  ', $depth);
            $lines[] = $indent.'- '.$item->getFilename().($item->isDir() ? '/' : '');
        }

        return implode("\n", $lines);
    }
}
