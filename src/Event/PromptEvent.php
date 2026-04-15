<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use MambaAi\Version_2\Prompt;

class PromptEvent
{

    public function __construct(
        public Prompt $prompt,
        public readonly Agent $agent,
        public readonly Message $message
    ) {}
}
