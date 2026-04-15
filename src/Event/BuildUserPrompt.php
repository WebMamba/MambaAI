<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;
use Symfony\AI\Platform\Message\MessageBag;

class BuildUserPrompt
{
    public function __construct(
        public Agent $agent,
        public Message $message,
        public MessageBag $messages,
    ) {}
}
