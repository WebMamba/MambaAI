<?php

declare(strict_types=1);

namespace MambaAi\Event;

use MambaAi\Message;

class MessageEvent
{
    public function __construct(
        public Message $message,
    ) {
    }
}
