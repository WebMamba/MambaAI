<?php

declare(strict_types=1);

namespace MambaAi;

enum MessageType: string
{
    case Loading = 'loading';
    case LoadingStop = 'loading_stop';
    case Text = 'text';
    case Thinking = 'thinking';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
    case Error = 'error';
}
