<?php

namespace MambaAi\Version_2\EventListener;

use MambaAi\Version_2\Event\TerminateEvent;

final class ConversationHistoryListener
{
    private const int MAX_ENTRIES = 100;

    public function __invoke(TerminateEvent $event): void
    {
        $agent = $event->agent;

        if (!$agent->memory) {
            return;
        }

        $dir = $agent->folder.'/memory';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $historyFile = $dir.'/history.jsonl';
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        // Aggregate all streaming chunks into a single assistant response
        $assistantContent = implode('', array_map(fn ($a) => $a->content, $event->answers));

        $newLines = [];
        $newLines[] = json_encode([
            'role' => 'user',
            'content' => $event->userMessage->content,
            'at' => $now,
        ]);
        $newLines[] = json_encode([
            'role' => 'assistant',
            'content' => $assistantContent,
            'at' => $now,
        ]);

        $existing = [];
        if (file_exists($historyFile)) {
            $existing = array_filter(explode("\n", trim(file_get_contents($historyFile))));
        }

        $all = array_values([...$existing, ...$newLines]);

        if (count($all) > self::MAX_ENTRIES) {
            $all = array_slice($all, -self::MAX_ENTRIES);
        }

        file_put_contents($historyFile, implode("\n", $all)."\n");
    }
}
