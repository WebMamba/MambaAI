<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class MemorySystemPart implements SystemPromptPartInterface
{
    #[\Override]
    public function getTargetAgent(): ?string
    {
        return null;
    }

    #[\Override]
    public function getContent(Agent $agent, Message $message): ?string
    {
        if (!$agent->memory) {
            return null;
        }

        $memoryDir = $agent->folder.'/memory';

        if (!is_dir($memoryDir)) {
            return null;
        }

        foreach ((new Finder())->files()->in($memoryDir)->name('MEMORY.md')->depth(0) as $file) {
            $content = trim($file->getContents());
            if ('' === $content) {
                return null;
            }

            return "## Memory\n\n".$content;
        }

        return null;
    }
}
