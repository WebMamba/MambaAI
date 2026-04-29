<?php

declare(strict_types=1);

namespace MambaAi\Tests\Support\Factory;

use MambaAi\Message;
use MambaAi\MessageType;

final class MessageFactory
{
    public static function make(
        string $agent = 'default',
        string $content = 'hello',
        array $context = [],
        MessageType $type = MessageType::Text,
    ): Message {
        return new Message(agent: $agent, content: $content, context: $context, type: $type);
    }
}
