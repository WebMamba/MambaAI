<?php

namespace MambaAi\Version_2;

enum MessageType: string
{
    case Text        = 'text';
    case Thinking    = 'thinking';
    case ToolCall    = 'tool_call';
    case ToolResult  = 'tool_result';
    case Error       = 'error';
}

class Message
{
    public function __construct(
        public string $agent,
        public string $content,
        public array $context = [],
        public MessageType $type = MessageType::Text,
    ) {}
}
