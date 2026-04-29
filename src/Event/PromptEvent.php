<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\Agent;
use MambaAi\Message;
use MambaAi\Prompt;

class PromptEvent
{
    public function __construct(
        public Prompt $prompt,
        public readonly Agent $agent,
        public readonly Message $message,
    ) {
    }
}
