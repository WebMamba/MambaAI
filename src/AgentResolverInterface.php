<?php

namespace MambaAi\Version_2;

interface AgentResolverInterface
{
    public function resolve(Message $message): Agent;
}
