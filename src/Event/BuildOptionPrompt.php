<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\Agent;
use MambaAi\Message;

class BuildOptionPrompt
{
    public function __construct(
        public Agent $agent,
        public Message $message,
        public array $options,
    ) {
    }
}
