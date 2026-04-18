<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class ConversationHistoryPart implements SystemPromptPartInterface
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

        foreach ((new Finder())->files()->in($memoryDir)->name('history.jsonl')->depth(0) as $file) {
            $lines = array_filter(explode("\n", trim($file->getContents())));
            if ($lines === []) {
                return null;
            }

            $entries = [];
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }

            if ($entries === []) {
                return null;
            }

            $formatted = array_map(
                fn (array $e) => sprintf('[%s] %s: %s', $e['at'], ucfirst($e['role']), $e['content']),
                $entries,
            );

            return "## Recent conversation\n\n".implode("\n", $formatted);
        }

        return null;
    }
}
