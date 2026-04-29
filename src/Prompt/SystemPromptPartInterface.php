<?php

declare(strict_types=1);

namespace MambaAi\Prompt;

use MambaAi\Agent;
use MambaAi\Message;

interface SystemPromptPartInterface extends PromptPartInterface
{
    /**
     * Returns a string to include in the system message, or null to contribute nothing.
     */
    public function getContent(Agent $agent, Message $message): ?string;
}
