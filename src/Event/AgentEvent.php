<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\Agent;
use MambaAi\Message;

class AgentEvent
{
    public function __construct(
        public Agent $agent,
        public Message $message,
    ) {
    }
}
