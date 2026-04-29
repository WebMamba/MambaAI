<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\Agent;
use MambaAi\Message;
use Symfony\AI\Platform\Message\MessageBag;

class BuildSystemPrompt
{
    public function __construct(
        public Agent $agent,
        public Message $message,
        public MessageBag $messages,
    ) {
    }
}
