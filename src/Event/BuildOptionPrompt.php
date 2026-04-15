<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;

class BuildOptionPrompt
{
    public function __construct(
        public Agent $agent,
        public Message $message,
        public array $options
    ) {}
}
