<?php

namespace MambaAi\Version_2\Prompt;

use MambaAi\Version_2\Agent;
use MambaAi\Version_2\Message;

interface SystemPromptPartInterface extends PromptPartInterface
{
    /**
     * Returns a string to include in the system message, or null to contribute nothing.
     */
    public function getContent(Agent $agent, Message $message): ?string;
}
