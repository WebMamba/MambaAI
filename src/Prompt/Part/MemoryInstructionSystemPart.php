<?php

declare(strict_types=1);

namespace MambaAi\Prompt\Part;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt\SystemPromptPartInterface;

final class MemoryInstructionSystemPart implements SystemPromptPartInterface
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

        return <<<'TEXT'
## Memory instructions

You have a persistent memory system. Use the `memory_write` tool ONLY to save information that is stable across conversations: user preferences, decisions, recurring context, long-term facts.

Rules:
- Do NOT save the current time or date.
- Do NOT save data retrieved from tools (Notion pages, search results, API responses, etc.). This data is dynamic — always call the tool again to get fresh data.
- Do NOT save information that will likely change between conversations.
- When writing, always provide the complete updated memory content — the tool overwrites the file entirely.
TEXT;
    }
}
