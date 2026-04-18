<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;

class TerminateEvent
{
    public function __construct(
        public array $answers,
        public Agent $agent,
        public Message $userMessage,
    ) {}
}
