<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;
use Symfony\Component\Finder\Finder;

final class ConversationHistoryPart implements SystemPromptPartInterface
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

        foreach ((new Finder())->files()->in($memoryDir)->name('history.jsonl')->depth(0) as $file) {
            $lines = array_filter(explode("\n", trim($file->getContents())));
            if ([] === $lines) {
                return null;
            }

            $entries = [];
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (null !== $entry) {
                    $entries[] = $entry;
                }
            }

            if ([] === $entries) {
                return null;
            }

            $formatted = array_map(
                static fn (array $e) => \sprintf('[%s] %s: %s', $e['at'], ucfirst($e['role']), $e['content']),
                $entries,
            );

            return "## Recent conversation\n\n".implode("\n", $formatted);
        }

        return null;
    }
}
