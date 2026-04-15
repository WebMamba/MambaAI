<?php

namespace MambaAi\Version_2\Event;

use MambaAi\Version_2\Message;

class MessageEvent
{
    public function __construct(
        public Message $message
    ) {}
}
