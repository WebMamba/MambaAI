<?php

namespace MambaAi\Version_2;

interface PromptBuilderInterface
{
    public function build(Agent $agent, Message $message): Prompt;
}
