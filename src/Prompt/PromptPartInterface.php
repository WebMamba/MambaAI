<?php

declare(strict_types=1);

namespace MambaAi\Prompt;

interface PromptPartInterface
{
    /**
     * Returns the agent name this part applies to, or null to apply to all agents.
     */
    public function getTargetAgent(): ?string;
}
