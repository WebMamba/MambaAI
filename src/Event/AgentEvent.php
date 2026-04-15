<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;

class AgentEvent
{
    public function __construct(
        public Agent $agent,
        public Message $message
    ) {}
}
