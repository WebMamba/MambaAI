<?php

namespace MambaAi\Version_2\Prompt\Part;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt\SystemPromptPartInterface;

final class MemoryInstructionSystemPart implements SystemPromptPartInterface
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
