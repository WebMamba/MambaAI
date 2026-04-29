<?php

declare(strict_types=1);

namespace MambaAi;

class Message
{
    public function __construct(
        public string $agent,
        public string $content,
        public array $context = [],
        public MessageType $type = MessageType::Text,
    ) {
    }
}
