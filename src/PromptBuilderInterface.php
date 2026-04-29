<?php

declare(strict_types=1);

namespace MambaAi;

interface PromptBuilderInterface
{
    public function build(Agent $agent, Message $message): Prompt;
}
