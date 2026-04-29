<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\Agent;
use MambaAi\Message;

class TerminateEvent
{
    public function __construct(
        public array $answers,
        public Agent $agent,
        public Message $userMessage,
    ) {
    }
}
