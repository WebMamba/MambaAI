<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class MemorySystemPart implements SystemPromptPartInterface
{
    public function getTargetAgent(): ?string
    {
        return null;
    }

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
            if ($content === '') {
                return null;
            }

            return "## Memory\n\n".$content;
        }

        return null;
    }
}
